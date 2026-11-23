<?php

/**
 * LibraryLedgerService - supplier invoices, credit notes, payments, three-way
 * matching, aged creditors and finance export (heratio#1475 item 5).
 *
 * Budget figures are read, never written: LibraryAcquisitionService recomputes
 * committed and spent from orders and receipts, so a write here would be lost.
 *
 * @author    Dr Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services\Ledger;

use AhgCore\Support\DropdownOptions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LibraryLedgerService
{
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_CREDIT = 'credit_note';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_VOID = 'void';
    public const MAX_LINES = 500;
    /** The only credit reason that frees quantity for rebilling. */
    public const CREDIT_BILLING_CORRECTION = 'billing_correction';

    public function __construct(private LedgerTaxProvider $tax) {}

    // ---- Reading ----------------------------------------------------------

    public function listDocuments(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $q = DB::table('library_ledger_document as d')
            ->leftJoin('library_vendor as v', 'v.id', '=', 'd.vendor_id')
            ->leftJoin('library_order as o', 'o.id', '=', 'd.order_id')
            ->select('d.*', 'v.name as vendor_name', 'o.order_number');

        foreach (['status', 'doc_type'] as $f) {
            if (!empty($filters[$f])) {
                $q->where('d.' . $f, $filters[$f]);
            }
        }
        if (!empty($filters['q'])) {
            $like = '%' . addcslashes((string) $filters['q'], '%_\\') . '%';
            $q->where(fn ($w) => $w->where('d.document_number', 'like', $like)
                ->orWhere('d.supplier_reference', 'like', $like)
                ->orWhere('v.name', 'like', $like));
        }

        return $q->orderByDesc('d.document_date')->orderByDesc('d.id')->paginate(50)->withQueryString();
    }

    public function getDocument(int $id): ?object
    {
        return DB::table('library_ledger_document as d')
            ->leftJoin('library_vendor as v', 'v.id', '=', 'd.vendor_id')
            ->leftJoin('library_order as o', 'o.id', '=', 'd.order_id')
            ->leftJoin('library_ledger_document as p', 'p.id', '=', 'd.credited_document_id')
            ->leftJoin('library_ledger_credit_reason as r', 'r.document_id', '=', 'd.id')
            ->select('d.*', 'v.name as vendor_name', 'v.vendor_code', 'o.order_number', 'p.document_number as credited_number', 'r.reason_code as credit_reason')
            ->where('d.id', $id)
            ->first();
    }

    public function getLines(int $documentId): array
    {
        return DB::table('library_ledger_line')->where('document_id', $documentId)->orderBy('id')->get()->all();
    }

    public function getPayments(int $documentId): array
    {
        return DB::table('library_ledger_payment')->where('document_id', $documentId)->orderBy('payment_date')->orderBy('id')->get()->all();
    }

    public function getCredits(int $invoiceId): array
    {
        return DB::table('library_ledger_document')
            ->where('credited_document_id', $invoiceId)
            ->where('status', '!=', self::STATUS_VOID)
            ->orderBy('document_date')
            ->get()->all();
    }

    /** Order lines with their received quantity, for prefilling an invoice. */
    public function orderLinesForInvoice(int $orderId): array
    {
        $rows = DB::table('library_order_line')->where('order_id', $orderId)->orderBy('id')->get()->all();
        $billed = $this->billedQuantities(array_map(fn ($r) => (int) $r->id, $rows));

        foreach ($rows as $r) {
            $qty = max(1, (int) $r->quantity);
            $r->billed = $billed[(int) $r->id] ?? 0;
            $r->to_bill = max(0, (int) $r->quantity_received - $r->billed);
            // Net unit price after the order line's discount.
            $r->net_unit = LedgerMoney::fromCents((int) round(LedgerMoney::toCents($r->line_total) / $qty));
        }

        return $rows;
    }

    /** Remaining creditable net and tax on each line of a posted invoice. */
    public function creditableLines(int $invoiceId): array
    {
        $lines = $this->getLines($invoiceId);
        $used = $this->creditedByLine(array_map(fn ($l) => (int) $l->id, $lines));

        foreach ($lines as $l) {
            $u = $used[(int) $l->id] ?? ['net' => 0, 'tax' => 0];
            $l->remaining_net = LedgerMoney::toCents($l->net_amount) - $u['net'];
            $l->remaining_tax = LedgerMoney::toCents($l->tax_amount) - $u['tax'];
        }

        return $lines;
    }

    // ---- Drafts -----------------------------------------------------------

    /**
     * Create or replace a draft supplier invoice.
     *
     * @param array{vendor_id:int, order_id:int, supplier_reference:string, document_date:string, due_date?:?string, notes?:?string, lines:array} $data
     */
    public function saveInvoiceDraft(array $data, ?int $id, ?int $userId): int
    {
        $order = DB::table('library_order')->where('id', (int) $data['order_id'])->first();
        if (!$order) {
            throw new LedgerException('The order does not exist.');
        }
        if (!$order->currency) {
            throw new LedgerException('The order has no currency.');
        }
        $vendorId = (int) $data['vendor_id'];
        if (!DB::table('library_vendor')->where('id', $vendorId)->exists()) {
            throw new LedgerException('The supplier does not exist.');
        }
        if ($order->vendor_id && (int) $order->vendor_id !== $vendorId) {
            throw new LedgerException('The supplier does not match the supplier on the order.');
        }

        $orderLineIds = DB::table('library_order_line')->where('order_id', $order->id)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            $lines[] = $this->normaliseLine($line, $orderLineIds);
        }
        if (!$lines || count($lines) > self::MAX_LINES) {
            throw new LedgerException('A document needs between 1 and ' . self::MAX_LINES . ' lines.');
        }

        return $this->storeDraft([
            'doc_type' => self::TYPE_INVOICE,
            'vendor_id' => $vendorId,
            'order_id' => (int) $order->id,
            'credited_document_id' => null,
            'supplier_reference' => trim((string) $data['supplier_reference']),
            'document_date' => $data['document_date'],
            'due_date' => $data['due_date'] ?? null,
            'currency' => strtoupper((string) $order->currency),
            'notes' => $data['notes'] ?? null,
        ], $lines, $id, $userId);
    }

    /**
     * Create a draft credit note against a posted invoice. Each entry names an
     * invoice line. The original tax code and rate are kept, so a later rate
     * change cannot alter what is being reversed.
     *
     * @param array{credit_reason:string, supplier_reference:string, document_date:string, notes:string, lines:array<int,array{credited_line_id:int,quantity:int,unit_price:string}>} $data
     */
    public function createCreditDraft(int $invoiceId, array $data, ?int $userId): int
    {
        $invoice = DB::table('library_ledger_document')->where('id', $invoiceId)->first();
        if (!$invoice || $invoice->doc_type !== self::TYPE_INVOICE || $invoice->status !== self::STATUS_POSTED) {
            throw new LedgerException('A credit note must be raised against a posted invoice.');
        }
        if ((string) $data['document_date'] < (string) $invoice->document_date) {
            throw new LedgerException('A credit note cannot be dated before its invoice.');
        }
        $reason = (string) ($data['credit_reason'] ?? '');
        if (!array_key_exists($reason, DropdownOptions::resolve('library_ledger_credit_reason', []))) {
            throw new LedgerException('Choose why this credit note was raised.');
        }

        $originals = [];
        foreach ($this->getLines($invoiceId) as $l) {
            $originals[(int) $l->id] = $l;
        }

        $lines = [];
        foreach ($data['lines'] ?? [] as $entry) {
            $orig = $originals[(int) ($entry['credited_line_id'] ?? 0)] ?? null;
            if (!$orig) {
                throw new LedgerException('A credit line does not belong to this invoice.');
            }
            $line = $this->normaliseLine([
                'description' => $orig->description,
                'quantity' => $entry['quantity'] ?? 0,
                'unit_price' => $entry['unit_price'] ?? '',
                'tax_code' => $orig->tax_code,
                'order_line_id' => $orig->order_line_id,
            ], $orig->order_line_id ? [(int) $orig->order_line_id] : []);
            $line['credited_line_id'] = (int) $orig->id;
            $line['tax_rate'] = (string) $orig->tax_rate;
            $lines[] = $line;
        }
        if (!$lines || count($lines) > self::MAX_LINES) {
            throw new LedgerException('A credit note needs at least one line.');
        }

        $header = [
            'doc_type' => self::TYPE_CREDIT,
            'vendor_id' => (int) $invoice->vendor_id,
            'order_id' => (int) $invoice->order_id,
            'credited_document_id' => (int) $invoice->id,
            'supplier_reference' => trim((string) $data['supplier_reference']),
            'document_date' => $data['document_date'],
            'due_date' => null,
            'currency' => $invoice->currency,
            'notes' => $data['notes'] ?? null,
        ];

        return DB::transaction(function () use ($header, $lines, $userId, $reason) {
            $id = $this->storeDraft($header, $lines, null, $userId);
            DB::table('library_ledger_credit_reason')->insert(['document_id' => $id, 'reason_code' => $reason]);

            return $id;
        });
    }

    private function normaliseLine(array $line, array $allowedOrderLineIds): array
    {
        $description = trim((string) ($line['description'] ?? ''));
        $qty = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $code = trim((string) ($line['tax_code'] ?? ''));
        if ($description === '' || $qty === false || $code === '') {
            throw new LedgerException('Every line needs a description, a whole quantity and a tax code.');
        }
        $unit = LedgerMoney::toCents($line['unit_price'] ?? '');
        if ($unit > intdiv(LedgerMoney::MAX_LINE_CENTS, $qty)) {
            throw new LedgerException('A line amount is too large.');
        }

        $orderLineId = !empty($line['order_line_id']) ? (int) $line['order_line_id'] : null;
        if ($orderLineId !== null && !in_array($orderLineId, $allowedOrderLineIds, true)) {
            throw new LedgerException('A line refers to an order line on a different order.');
        }

        return [
            'order_line_id' => $orderLineId,
            'credited_line_id' => null,
            'description' => mb_substr($description, 0, 500),
            'quantity' => $qty,
            'unit_price' => LedgerMoney::fromCents($unit),
            'net_amount' => LedgerMoney::fromCents($qty * $unit),
            'tax_code' => $code,
            'tax_rate' => '0.0000',
            'tax_amount' => '0.00',
            'gross_amount' => LedgerMoney::fromCents($qty * $unit),
        ];
    }

    private function storeDraft(array $header, array $lines, ?int $id, ?int $userId): int
    {
        $header['dedupe_key'] = $this->dedupeKey($header['vendor_id'], $header['doc_type'], $header['supplier_reference']);
        $net = array_sum(array_map(fn ($l) => LedgerMoney::toCents($l['net_amount']), $lines));
        $header['subtotal'] = LedgerMoney::fromCents($net);
        $header['tax_total'] = '0.00';
        $header['total'] = LedgerMoney::fromCents($net);
        $header['updated_at'] = now();

        try {
            return DB::transaction(function () use ($header, $lines, $id, $userId) {
                if ($id !== null) {
                    $existing = DB::table('library_ledger_document')->where('id', $id)->lockForUpdate()->first();
                    if (!$existing || $existing->status !== self::STATUS_DRAFT || $existing->doc_type !== $header['doc_type']) {
                        throw new LedgerException('Only a draft can be edited.');
                    }
                    DB::table('library_ledger_document')->where('id', $id)->update($header);
                    DB::table('library_ledger_line')->where('document_id', $id)->delete();
                } else {
                    $id = (int) DB::table('library_ledger_document')->insertGetId($header + [
                        'status' => self::STATUS_DRAFT,
                        'tax_provider' => $this->tax->key(),
                        'created_by' => $userId,
                        'created_at' => now(),
                    ]);
                }
                foreach ($lines as $line) {
                    DB::table('library_ledger_line')->insert($line + ['document_id' => $id]);
                }

                return $id;
            });
        } catch (UniqueConstraintViolationException) {
            throw new LedgerException('This supplier reference is already captured for this supplier.');
        }
    }

    private function dedupeKey(int $vendorId, string $docType, string $reference): string
    {
        return sha1($vendorId . '|' . $docType . '|' . mb_strtolower(preg_replace('/\s+/', '', $reference)));
    }

    // ---- Posting ----------------------------------------------------------

    /**
     * Post a draft: calculate tax, check it against the order and receipts,
     * and assign the number. Numbers are allocated in this transaction, so a
     * failed post does not leave a gap.
     *
     * @throws LedgerException with ->variances when a reason is needed
     */
    public function post(int $id, ?string $varianceReason, ?int $userId): void
    {
        DB::transaction(function () use ($id, $varianceReason, $userId) {
            $doc = DB::table('library_ledger_document')->where('id', $id)->lockForUpdate()->first();
            if (!$doc || $doc->status !== self::STATUS_DRAFT) {
                throw new LedgerException('Only a draft can be posted.');
            }
            foreach ($this->tax->requiredFields($doc->doc_type) as $field) {
                if (!isset($doc->{$field}) || $doc->{$field} === '') {
                    throw new LedgerException("Required field missing: {$field}.");
                }
            }
            // One post at a time per order, so two invoices cannot both claim
            // the same received quantity.
            DB::table('library_order')->where('id', $doc->order_id)->lockForUpdate()->first();

            $lines = $this->getLines($id);
            if (!$lines) {
                throw new LedgerException('The document has no lines.');
            }

            $reason = trim((string) $varianceReason);
            if ($doc->doc_type === self::TYPE_CREDIT) {
                $taxes = $this->creditTaxes($doc, $lines);
            } else {
                $taxes = $this->tax->calculate(array_map(fn ($l) => [
                    'net_cents' => LedgerMoney::toCents($l->net_amount),
                    'tax_code' => $l->tax_code,
                ], $lines), (string) $doc->document_date);

                $variances = $this->matchVariances($doc, $lines);
                if ($variances && $reason === '') {
                    throw new LedgerException('This invoice does not match the order or receipts. Give a reason to post it anyway.', $variances);
                }
                if (!$variances) {
                    $reason = '';
                }
            }

            $net = $tax = 0;
            foreach ($lines as $i => $line) {
                $lineNet = LedgerMoney::toCents($line->net_amount);
                $lineTax = $taxes[$i]['tax_cents'];
                $net += $lineNet;
                $tax += $lineTax;
                DB::table('library_ledger_line')->where('id', $line->id)->update([
                    'tax_rate' => LedgerMoney::rateToString($taxes[$i]['rate']),
                    'tax_amount' => LedgerMoney::fromCents($lineTax),
                    'gross_amount' => LedgerMoney::fromCents($lineNet + $lineTax),
                ]);
            }

            DB::table('library_ledger_document')->where('id', $id)->update([
                'status' => self::STATUS_POSTED,
                'document_number' => $this->tax->nextNumber($doc->doc_type, (string) $doc->document_date),
                'tax_provider' => $this->tax->key(),
                'subtotal' => LedgerMoney::fromCents($net),
                'tax_total' => LedgerMoney::fromCents($tax),
                'total' => LedgerMoney::fromCents($net + $tax),
                'variance_reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
                'posted_at' => now(),
                'posted_by' => $userId,
                'updated_at' => now(),
            ]);
        });
    }

    /** Tax on credit lines at the original rate, never above what is left. */
    private function creditTaxes(object $doc, array $lines): array
    {
        $invoice = DB::table('library_ledger_document')->where('id', $doc->credited_document_id)->lockForUpdate()->first();
        if (!$invoice || $invoice->status !== self::STATUS_POSTED) {
            throw new LedgerException('The credited invoice is no longer posted.');
        }

        $originals = [];
        foreach ($this->getLines((int) $invoice->id) as $l) {
            $originals[(int) $l->id] = $l;
        }
        $used = $this->creditedByLine(array_keys($originals));

        $out = [];
        foreach ($lines as $i => $line) {
            $orig = $originals[(int) $line->credited_line_id] ?? null;
            if (!$orig) {
                throw new LedgerException('A credit line no longer matches its invoice.');
            }
            $key = (int) $orig->id;
            $u = $used[$key] ?? ['net' => 0, 'tax' => 0];
            $remainingNet = LedgerMoney::toCents($orig->net_amount) - $u['net'];
            $remainingTax = LedgerMoney::toCents($orig->tax_amount) - $u['tax'];
            $net = LedgerMoney::toCents($line->net_amount);
            if ($net > $remainingNet) {
                throw new LedgerException('A credit exceeds what is left on invoice line: ' . $orig->description);
            }
            $rate = LedgerMoney::rateToInt($orig->tax_rate);
            // Crediting the whole remainder reverses the remaining tax exactly.
            $tax = $net === $remainingNet ? $remainingTax : min(LedgerMoney::tax($net, $rate), $remainingTax);
            $out[$i] = ['rate' => $rate, 'tax_cents' => $tax];
            $used[$key] = ['net' => $u['net'] + $net, 'tax' => $u['tax'] + $tax];
        }

        return $out;
    }

    /**
     * Three-way match of an invoice against order lines and goods received.
     *
     * @return string[] human-readable variances, empty when clean
     */
    public function matchVariances(object $doc, array $lines): array
    {
        $variances = [];
        $order = DB::table('library_order')->where('id', $doc->order_id)->first(['status', 'vendor_id']);
        if (!$order || $order->status === 'cancelled') {
            $variances[] = 'The order is cancelled or no longer exists.';
        } elseif ($order->vendor_id && (int) $order->vendor_id !== (int) $doc->vendor_id) {
            $variances[] = 'The supplier no longer matches the order.';
        }
        $orderLineIds = array_values(array_unique(array_filter(array_map(fn ($l) => (int) $l->order_line_id, $lines))));
        $orderLines = DB::table('library_order_line')->whereIn('id', $orderLineIds)->get()->keyBy('id');
        $billed = $this->billedQuantities($orderLineIds, (int) $doc->id);
        $thisDoc = [];

        foreach ($lines as $line) {
            if (!$line->order_line_id) {
                $variances[] = "Not on the order: {$line->description}";
                continue;
            }
            $ol = $orderLines->get($line->order_line_id);
            if (!$ol) {
                $variances[] = "Order line no longer exists: {$line->description}";
                continue;
            }
            $olId = (int) $ol->id;
            $qty = (int) $line->quantity;
            $thisDoc[$olId] = ($thisDoc[$olId] ?? 0) + $qty;
            $total = ($billed[$olId] ?? 0) + $thisDoc[$olId];
            if ($total > (int) $ol->quantity_received) {
                $variances[] = "Billed {$total} but received {$ol->quantity_received}: {$line->description}";
            }

            // The order holds a line total, so compare pro rata. A unit price
            // rounded to cents may differ by up to half a cent per unit.
            $orderedQty = max(1, (int) $ol->quantity);
            $expected = (int) round(LedgerMoney::toCents($ol->line_total) * $qty / $orderedQty);
            $net = LedgerMoney::toCents($line->net_amount);
            if (abs($net - $expected) > (int) ceil($qty / 2)) {
                $variances[] = 'Price differs from the order (' . LedgerMoney::fromCents($net) . ' against ' . LedgerMoney::fromCents($expected) . "): {$line->description}";
            }
        }

        return $variances;
    }

    /**
     * Quantity billed per order line: posted invoices less full-price billing
     * corrections. A return keeps its quantity billed: receipts are capped at
     * the ordered quantity and keep no history, so a replacement delivery
     * cannot be told apart, and rebilling it needs a stated variance reason.
     * A credit with no recorded reason is treated as a return.
     *
     * @return array<int,int>
     */
    private function billedQuantities(array $orderLineIds, ?int $excludeDocumentId = null): array
    {
        if (!$orderLineIds) {
            return [];
        }
        $out = [];

        $invoiced = DB::table('library_ledger_line as l')
            ->join('library_ledger_document as d', 'd.id', '=', 'l.document_id')
            ->whereIn('l.order_line_id', $orderLineIds)
            ->where('d.doc_type', self::TYPE_INVOICE)
            ->where('d.status', self::STATUS_POSTED)
            ->when($excludeDocumentId, fn ($q) => $q->where('d.id', '!=', $excludeDocumentId))
            ->groupBy('l.order_line_id')
            ->selectRaw('l.order_line_id as id, SUM(l.quantity) as qty')
            ->get();
        foreach ($invoiced as $r) {
            $out[(int) $r->id] = (int) $r->qty;
        }

        $returned = DB::table('library_ledger_line as l')
            ->join('library_ledger_document as d', 'd.id', '=', 'l.document_id')
            ->join('library_ledger_line as o', 'o.id', '=', 'l.credited_line_id')
            ->join('library_ledger_credit_reason as r', 'r.document_id', '=', 'd.id')
            ->whereIn('l.order_line_id', $orderLineIds)
            ->where('d.doc_type', self::TYPE_CREDIT)
            ->where('d.status', self::STATUS_POSTED)
            ->where('r.reason_code', self::CREDIT_BILLING_CORRECTION)
            ->whereColumn('l.unit_price', 'o.unit_price')
            ->groupBy('l.order_line_id')
            ->selectRaw('l.order_line_id as id, SUM(l.quantity) as qty')
            ->get();
        foreach ($returned as $r) {
            $out[(int) $r->id] = ($out[(int) $r->id] ?? 0) - (int) $r->qty;
        }

        return $out;
    }

    /** @return array<int, array{net:int, tax:int}> posted credits per invoice line */
    private function creditedByLine(array $lineIds): array
    {
        if (!$lineIds) {
            return [];
        }
        $rows = DB::table('library_ledger_line as l')
            ->join('library_ledger_document as d', 'd.id', '=', 'l.document_id')
            ->whereIn('l.credited_line_id', $lineIds)
            ->where('d.status', self::STATUS_POSTED)
            ->groupBy('l.credited_line_id')
            ->selectRaw('l.credited_line_id as id, SUM(l.net_amount) as net, SUM(l.tax_amount) as tax')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = ['net' => LedgerMoney::toCents($r->net), 'tax' => LedgerMoney::toCents($r->tax)];
        }

        return $out;
    }

    // ---- Void -------------------------------------------------------------

    public function void(int $id, string $reason, ?int $userId): void
    {
        DB::transaction(function () use ($id, $reason, $userId) {
            $doc = DB::table('library_ledger_document')->where('id', $id)->lockForUpdate()->first();
            if (!$doc || $doc->status === self::STATUS_VOID) {
                throw new LedgerException('This document cannot be voided.');
            }
            if ($doc->export_batch) {
                throw new LedgerException('This document has been exported to finance. Raise a credit note instead.');
            }
            if ($doc->doc_type === self::TYPE_INVOICE) {
                $hasPayment = DB::table('library_ledger_payment')->where('document_id', $id)->whereNull('reversed_at')->exists();
                $hasCredit = DB::table('library_ledger_document')->where('credited_document_id', $id)->where('status', '!=', self::STATUS_VOID)->exists();
                if ($hasPayment || $hasCredit) {
                    throw new LedgerException('Reverse its payments and void its credit notes first.');
                }
            }
            DB::table('library_ledger_document')->where('id', $id)->update([
                'status' => self::STATUS_VOID,
                'dedupe_key' => null,
                'void_reason' => mb_substr(trim($reason), 0, 255),
                'voided_at' => now(),
                'voided_by' => $userId,
                'updated_at' => now(),
            ]);
        });
    }

    // ---- Payments ---------------------------------------------------------

    /**
     * Amount still owed on a posted invoice at the end of a date, in cents.
     * Credits and payments count by their own date. One undone after the date
     * still counts, unless it was also captured after the date.
     */
    public function outstandingCents(object $invoice, ?string $asOf = null): int
    {
        $asOf ??= now()->toDateString();
        $end = $asOf . ' 23:59:59';

        $credits = $this->inForceAt(DB::table('library_ledger_document'), $end)
            ->where('credited_document_id', $invoice->id)
            ->where('document_date', '<=', $asOf)
            ->sum('total');
        $paid = DB::table('library_ledger_payment')
            ->where('document_id', $invoice->id)
            ->where(fn ($q) => $q->whereNull('reversed_at')
                ->orWhere(fn ($r) => $r->where('reversed_at', '>', $end)->where('created_at', '<=', $end)))
            ->where('payment_date', '<=', $asOf)
            ->sum('amount');

        return LedgerMoney::toCents($invoice->total)
            - LedgerMoney::toCents((string) $credits)
            - LedgerMoney::toCents((string) $paid);
    }

    /**
     * Documents in force at a moment. A late-captured document counts from its
     * document date, so a backdated invoice appears in the period it belongs
     * to. A voided one counts only if it was posted by then and voided later.
     */
    private function inForceAt($query, string $end, string $prefix = '')
    {
        return $query->whereNotNull($prefix . 'posted_at')
            ->where(fn ($q) => $q->whereNull($prefix . 'voided_at')
                ->orWhere(fn ($v) => $v->where($prefix . 'voided_at', '>', $end)->where($prefix . 'posted_at', '<=', $end)));
    }

    public function recordPayment(int $invoiceId, array $data, ?int $userId): int
    {
        return DB::transaction(function () use ($invoiceId, $data, $userId) {
            $doc = DB::table('library_ledger_document')->where('id', $invoiceId)->lockForUpdate()->first();
            if (!$doc || $doc->doc_type !== self::TYPE_INVOICE || $doc->status !== self::STATUS_POSTED) {
                throw new LedgerException('Payments can only be recorded against a posted invoice.');
            }
            $date = (string) $data['payment_date'];
            if ($date < (string) $doc->document_date || $date > now()->toDateString()) {
                throw new LedgerException('The payment date must be between the invoice date and today.');
            }
            $amount = LedgerMoney::toCents($data['amount'] ?? '');
            if ($amount <= 0) {
                throw new LedgerException('The payment must be more than zero.');
            }
            if ($amount > $this->outstandingCents($doc, '9999-12-31')) {
                throw new LedgerException('The payment is more than the amount outstanding.');
            }

            return (int) DB::table('library_ledger_payment')->insertGetId([
                'document_id' => $invoiceId,
                'payment_date' => $date,
                'amount' => LedgerMoney::fromCents($amount),
                'reference' => mb_substr(trim((string) $data['reference']), 0, 100),
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    public function reversePayment(int $invoiceId, int $paymentId, ?int $userId): void
    {
        $done = DB::table('library_ledger_payment')
            ->where('id', $paymentId)
            ->where('document_id', $invoiceId)
            ->whereNull('reversed_at')
            ->update(['reversed_at' => now(), 'reversed_by' => $userId]);
        if (!$done) {
            throw new LedgerException('That payment cannot be reversed.');
        }
    }

    // ---- Reports ----------------------------------------------------------

    /**
     * Aged creditors at a date, per supplier and currency. Amounts in
     * different currencies are never added together.
     */
    public function agedCreditors(string $asOf): array
    {
        $date = Carbon::parse($asOf)->startOfDay();
        $invoices = $this->inForceAt(DB::table('library_ledger_document as d'), $date->toDateString() . ' 23:59:59', 'd.')
            ->join('library_vendor as v', 'v.id', '=', 'd.vendor_id')
            ->where('d.doc_type', self::TYPE_INVOICE)
            ->where('d.document_date', '<=', $date->toDateString())
            ->select('d.id', 'd.total', 'd.document_date', 'd.due_date', 'd.currency', 'd.vendor_id', 'v.name as vendor_name')
            ->orderBy('v.name')
            ->get();

        $rows = [];
        foreach ($invoices as $inv) {
            $owed = $this->outstandingCents($inv, $date->toDateString());
            if ($owed === 0) {
                continue;
            }
            $key = $inv->vendor_id . '|' . $inv->currency;
            $rows[$key] ??= [
                'vendor_id' => (int) $inv->vendor_id, 'vendor_name' => $inv->vendor_name, 'currency' => $inv->currency,
                'current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'over90' => 0, 'total' => 0,
            ];
            $due = Carbon::parse($inv->due_date ?: $inv->document_date)->startOfDay();
            $days = (int) $due->diffInDays($date, false);
            $bucket = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => 'd30',
                $days <= 60 => 'd60',
                $days <= 90 => 'd90',
                default => 'over90',
            };
            $rows[$key][$bucket] += $owed;
            $rows[$key]['total'] += $owed;
        }

        return array_values($rows);
    }

    /**
     * Orders, receipts and posted invoices net of credits, per budget row. Tax
     * is left out because budgets carry order values before tax. A fund-split
     * line is shared in the proportions of its split, and a part-received line
     * counts pro rata.
     *
     * A budget code can exist in several fiscal years. Each order belongs to
     * the row covering its order date; see budgetRowFor(). Committed and
     * received are worked out here because the stored totals are per code.
     */
    public function budgetMatch(): array
    {
        $budgets = DB::table('library_budget')->orderBy('budget_code')->orderBy('fiscal_year')->get();
        $byCode = $budgets->groupBy('budget_code');
        $splits = DB::table('library_order_line_fund')->orderBy('id')->get()->groupBy('order_line_id');
        $totals = [];
        $excluded = [];
        $unattributed = [];

        $charge = function (string $field, int $cents, object $row, ?int $orderLineId) use ($byCode, $splits, &$totals, &$excluded, &$unattributed) {
            if ($cents === 0) {
                return;
            }
            foreach ($this->shareAcrossFunds($cents, $row->budget_code, $orderLineId ? $splits->get($orderLineId) : null) as $code => $part) {
                $candidates = $byCode->get($code);
                if (!$candidates) {
                    continue;
                }
                $budget = $this->budgetRowFor($candidates, (string) $row->order_date);
                if (!$budget) {
                    $unattributed[$code][$row->order_id] = true;
                    continue;
                }
                if ($row->currency !== null && strtoupper($row->currency) !== strtoupper((string) $budget->currency)) {
                    $excluded[$budget->id][$row->order_id] = true;
                    continue;
                }
                $totals[$budget->id][$field] = ($totals[$budget->id][$field] ?? 0) + $part;
            }
        };

        $ledgerLines = DB::table('library_ledger_line as l')
            ->join('library_ledger_document as d', 'd.id', '=', 'l.document_id')
            ->join('library_order as o', 'o.id', '=', 'd.order_id')
            ->where('d.status', self::STATUS_POSTED)
            ->select('l.order_line_id', 'l.net_amount', 'd.doc_type', 'd.currency', 'd.order_id', 'o.budget_code', 'o.order_date')
            ->cursor();
        foreach ($ledgerLines as $line) {
            $net = LedgerMoney::toCents($line->net_amount);
            $charge('invoiced', $line->doc_type === self::TYPE_CREDIT ? -$net : $net, $line, $line->order_line_id ? (int) $line->order_line_id : null);
        }

        // Same order statuses as the acquisitions budget totals.
        $orderLines = DB::table('library_order_line as l')
            ->join('library_order as o', 'o.id', '=', 'l.order_id')
            ->where('o.status', '!=', 'cancelled')
            ->select('l.id', 'l.quantity', 'l.quantity_received', 'l.line_total', 'l.order_id', 'o.status', 'o.budget_code', 'o.order_date', 'o.currency')
            ->cursor();
        foreach ($orderLines as $line) {
            $value = LedgerMoney::toCents($line->line_total);
            $charge('committed', $value, $line, (int) $line->id);
            if (in_array($line->status, ['ordered', 'partial', 'received'], true)) {
                $qty = max(1, (int) $line->quantity);
                $charge('received', (int) round($value * min((int) $line->quantity_received, $qty) / $qty), $line, (int) $line->id);
            }
        }

        $out = [];
        foreach ($budgets as $b) {
            $t = $totals[$b->id] ?? [];
            $inv = $t['invoiced'] ?? 0;
            $committed = $t['committed'] ?? 0;
            $received = $t['received'] ?? 0;
            $out[] = (object) [
                'budget_code' => $b->budget_code,
                'fiscal_year' => $b->fiscal_year,
                'fund_name' => $b->fund_name,
                'currency' => $b->currency,
                'allocated' => LedgerMoney::toCents($b->allocated_amount),
                'committed' => $committed,
                'received' => $received,
                'invoiced' => $inv,
                'received_not_invoiced' => $received - $inv,
                'commitment_not_invoiced' => $committed - $inv,
                'excluded_orders' => count($excluded[$b->id] ?? []),
                'unattributed_orders' => count($unattributed[$b->budget_code] ?? []),
            ];
        }

        return $out;
    }

    /**
     * The budget row for an order date. A code with one row takes everything.
     * With several, the row whose fiscal year covers the order's calendar year
     * wins. Budgets hold no year-start month, so a date that two split years
     * could both cover (2025/26 and 2026/27 for 2026) is left unattributed.
     */
    private function budgetRowFor(Collection $rows, string $orderDate): ?object
    {
        if ($rows->count() === 1) {
            return $rows->first();
        }
        $year = (int) substr($orderDate, 0, 4);
        $matches = $rows->filter(function ($b) use ($year) {
            if (!preg_match('/^(\d{4})(?:[\/-](\d{2}|\d{4}))?$/', trim((string) $b->fiscal_year), $m)) {
                return false;
            }
            $from = (int) $m[1];
            $to = $from;
            if (isset($m[2])) {
                $to = strlen($m[2]) === 4 ? (int) $m[2] : (int) (substr($m[1], 0, 2) . $m[2]);
                $to += $to < $from ? 100 : 0;
            }

            return $year >= $from && $year <= $to;
        });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @return array<string,int> */
    private function shareAcrossFunds(int $cents, ?string $budgetCode, $splits): array
    {
        if (!$splits || $splits->isEmpty()) {
            return $budgetCode ? [$budgetCode => $cents] : [];
        }
        $weights = $splits->map(fn ($s) => LedgerMoney::toCents($s->amount))->values();
        $whole = $weights->sum();
        if ($whole <= 0) {
            return $budgetCode ? [$budgetCode => $cents] : [];
        }

        $out = [];
        $given = 0;
        $last = $splits->count() - 1;
        foreach ($splits->values() as $i => $split) {
            // The last fund takes the remainder, so the shares always add up exactly.
            $part = $i === $last ? $cents - $given : (int) round($cents * ($weights[$i] / $whole));
            $given += $part;
            $out[$split->fund_code] = ($out[$split->fund_code] ?? 0) + $part;
        }

        return $out;
    }

    // ---- Finance export ---------------------------------------------------

    /** Stamp every posted, unexported document into a new batch. */
    public function createExportBatch(): ?string
    {
        return DB::transaction(function () {
            $ids = DB::table('library_ledger_document')
                ->where('status', self::STATUS_POSTED)
                ->whereNull('export_batch')
                ->lockForUpdate()
                ->pluck('id');
            if ($ids->isEmpty()) {
                return null;
            }
            $batch = 'LEDGER-' . now()->format('Ymd-His') . '-' . Str::lower(Str::random(6));
            DB::table('library_ledger_document')->whereIn('id', $ids)->update([
                'export_batch' => $batch,
                'exported_at' => now(),
                'updated_at' => now(),
            ]);

            return $batch;
        });
    }

    public function listExportBatches(): array
    {
        return DB::table('library_ledger_document')
            ->whereNotNull('export_batch')
            ->groupBy('export_batch')
            ->selectRaw('export_batch, MIN(exported_at) as exported_at, COUNT(*) as documents')
            ->orderByDesc('exported_at')
            ->limit(100)
            ->get()->all();
    }

    public const EXPORT_HEADER = [
        'batch', 'document_type', 'document_number', 'supplier_reference', 'credited_document',
        'supplier_code', 'supplier_name', 'document_date', 'due_date', 'currency', 'order_number',
        'description', 'quantity', 'unit_price', 'net_amount', 'tax_code', 'tax_rate', 'tax_amount',
        'gross_amount',
    ];

    /** Export rows, one per line. Credit note amounts are negative. */
    public function exportRows(string $batch): \Generator
    {
        $rows = DB::table('library_ledger_line as l')
            ->join('library_ledger_document as d', 'd.id', '=', 'l.document_id')
            ->leftJoin('library_vendor as v', 'v.id', '=', 'd.vendor_id')
            ->leftJoin('library_order as o', 'o.id', '=', 'd.order_id')
            ->leftJoin('library_ledger_document as p', 'p.id', '=', 'd.credited_document_id')
            ->where('d.export_batch', $batch)
            ->orderBy('d.id')->orderBy('l.id')
            ->select('l.*', 'd.doc_type', 'd.document_number', 'd.supplier_reference', 'd.document_date', 'd.due_date', 'd.currency',
                'v.vendor_code', 'v.name as vendor_name', 'o.order_number', 'p.document_number as credited_number')
            ->cursor();

        foreach ($rows as $r) {
            $sign = $r->doc_type === self::TYPE_CREDIT ? -1 : 1;
            yield [
                $batch,
                $r->doc_type,
                self::safeCell($r->document_number),
                self::safeCell($r->supplier_reference),
                self::safeCell($r->credited_number),
                self::safeCell($r->vendor_code),
                self::safeCell($r->vendor_name),
                $r->document_date,
                $r->due_date,
                self::safeCell($r->currency),
                self::safeCell($r->order_number),
                self::safeCell($r->description),
                $r->quantity,
                $r->unit_price,
                LedgerMoney::fromCents($sign * LedgerMoney::toCents($r->net_amount)),
                self::safeCell($r->tax_code),
                $r->tax_rate,
                LedgerMoney::fromCents($sign * LedgerMoney::toCents($r->tax_amount)),
                LedgerMoney::fromCents($sign * LedgerMoney::toCents($r->gross_amount)),
            ];
        }
    }

    /** Stops a text value being run as a formula when the CSV is opened. */
    public static function safeCell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && preg_match('/^[\s]*[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    // ---- Tax rates --------------------------------------------------------

    public function listRates(): array
    {
        return DB::table('library_tax_rate')->orderBy('tax_code')->orderByDesc('valid_from')->get()->all();
    }

    public function activeTaxCodes(): array
    {
        $today = now()->toDateString();

        return DB::table('library_tax_rate')
            ->where('is_active', 1)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->orderBy('tax_code')
            ->get(['tax_code', 'label', 'rate_percent', 'valid_from'])
            ->unique('tax_code')
            ->values()->all();
    }

    public function addRate(array $data, ?int $userId): int
    {
        $code = strtoupper(trim((string) $data['tax_code']));
        $from = (string) $data['valid_from'];
        $to = !empty($data['valid_to']) ? (string) $data['valid_to'] : null;
        $rate = LedgerMoney::rateToInt($data['rate_percent']);

        return DB::transaction(function () use ($code, $from, $to, $rate, $data, $userId) {
            $overlap = DB::table('library_tax_rate')
                ->where('tax_code', $code)
                ->where('is_active', 1)
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $from))
                ->when($to, fn ($q) => $q->where('valid_from', '<=', $to))
                ->lockForUpdate()
                ->exists();
            if ($overlap) {
                throw new LedgerException("Code {$code} already has a rate in that period. End it first.");
            }

            return (int) DB::table('library_tax_rate')->insertGetId([
                'tax_code' => $code,
                'label' => trim((string) $data['label']),
                'rate_percent' => LedgerMoney::rateToString($rate),
                'valid_from' => $from,
                'valid_to' => $to,
                'is_active' => 1,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** Rates are ended, never edited, so posted history stays explainable. */
    public function endRate(int $id, string $validTo): void
    {
        $rate = DB::table('library_tax_rate')->where('id', $id)->first();
        if (!$rate || $validTo < (string) $rate->valid_from) {
            throw new LedgerException('The end date cannot be before the rate starts.');
        }
        if ($rate->valid_to !== null && $validTo > (string) $rate->valid_to) {
            throw new LedgerException('A rate end date can only be brought forward.');
        }
        DB::table('library_tax_rate')->where('id', $id)->update(['valid_to' => $validTo, 'updated_at' => now()]);
    }

    public function getRounding(): string
    {
        return (string) (DB::table('library_settings')->where('setting_key', TableLedgerTaxProvider::ROUNDING_SETTING)->value('setting_value') ?: 'line');
    }

    public function setRounding(string $mode): void
    {
        DB::table('library_settings')->updateOrInsert(
            ['setting_key' => TableLedgerTaxProvider::ROUNDING_SETTING],
            ['setting_value' => $mode, 'setting_type' => 'string', 'description' => 'Invoice tax rounding rule', 'updated_at' => now()],
        );
    }
}
