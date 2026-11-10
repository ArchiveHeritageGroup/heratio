<?php

/**
 * DocumentFieldExtractor - invoice and purchase-order fields from a record's
 * PDF through the AI gateway, grounded in the document (Eskom World Quality
 * Day, 10 Nov 2026).
 *
 * The LLM reads the document text (OCR for a scan) and returns the fields as
 * JSON. A value is kept only if it appears in that text: verbatim for words,
 * digit for digit for numbers and amounts. Anything else is dropped and
 * reported, so the model can suggest but never invent. Kept values are saved
 * as pending ahg_ai_suggestion rows (field 'invoice.*' / 'po.*') with the
 * model, the inference receipt and the evidence, for an archivist to review.
 *
 * match() checks an invoice against its purchase order: the PO the invoice
 * quotes was extracted from another record, and the totals agree.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgAiServices\Services;

use AhgPrivacy\Services\PiiRedactionSuggester;
use Illuminate\Support\Facades\DB;

class DocumentFieldExtractor
{
    /** Scalar fields, in display order. */
    public const FIELDS = ['document_type', 'number', 'date', 'supplier', 'supplier_vat', 'po_number', 'subtotal', 'vat_amount', 'total', 'currency'];

    private const AMOUNTS = ['subtotal', 'vat_amount', 'total'];

    private const PROMPT = <<<'TXT'
You read one business document and return its fields as JSON. Use only text that is in the document; if a field is not there, use null. Do not calculate or correct anything.
Return exactly this JSON object:
{"document_type": "invoice", "purchase_order" or "delivery_note",
 "number": the invoice, purchase order or delivery note number,
 "date": the invoice date or order date as written,
 "supplier": the supplier's company name as written,
 "supplier_vat": the VAT number printed with the supplier's own name and address at the top - never the VAT number under "Bill to", "Deliver to" or the customer,
 "po_number": for an invoice or a delivery note, the purchase order number it quotes (often under "Your order no.", "Order no." or "PO no."); for a purchase order, null,
 "subtotal": the amount before VAT as written, "vat_amount": the VAT amount as written, "total": the total as written,
 "currency": the currency code or symbol,
 "line_items": [{"description": as written, "quantity": as written, "unit_price": as written, "amount": as written}]}
TXT;

    public function __construct(private LlmService $llm, private PiiRedactionSuggester $pages)
    {
    }

    /**
     * @return array{fields: array<string,string>, line_items: list<array>, dropped: array<string,string>, model: ?string, receipt: ?array, saved: int, error?: string}
     */
    public function extract(int $ioId, ?int $userId = null): array
    {
        $pages = $this->pages->pageTexts($ioId);
        if ($pages === null) {
            return $this->fail('This record has no PDF master file.');
        }
        $text = trim(implode("\n", array_column($pages, 'text')));
        if ($text === '') {
            return $this->fail('No text could be read from the document.');
        }

        $logger = class_exists(\AhgAiCompliance\Services\InferenceLogger::class) ? app(\AhgAiCompliance\Services\InferenceLogger::class) : null;
        $before = $logger?->last();
        // ai_document_fields_model picks a gateway model for this reading; empty
        // = the default LLM configuration's model.
        $opts = ['temperature' => 0, 'max_tokens' => 1500];
        $model = trim((string) \AhgCore\Services\AhgSettingsService::get('ai_document_fields_model', ''));
        if ($model !== '') {
            $opts['model'] = $model;
        }
        $res = $this->llm->completeFull(self::PROMPT, mb_substr($text, 0, 12000), null, $opts);
        if (empty($res['success'])) {
            return $this->fail('The AI gateway did not answer: '.($res['error'] ?? 'unknown error'));
        }
        $data = self::json((string) $res['text']);
        if ($data === null) {
            return $this->fail('The model did not return readable JSON.');
        }
        $receipt = $logger?->last();
        $receipt = $receipt !== $before && $receipt ? ['seq' => $receipt->seq, 'hash' => $receipt->entryHash] : null;

        [$fields, $dropped] = [[], []];
        foreach (self::FIELDS as $f) {
            $v = isset($data[$f]) && is_scalar($data[$f]) ? trim((string) $data[$f]) : '';
            if ($v === '' || strtolower($v) === 'null') {
                continue;
            }
            if ($f === 'supplier_vat') {
                $v = trim((string) preg_replace('/^(?:vat|btw)\s*(?:no\.?|nr\.?|number)?\s*[:#]?\s*/i', '', $v));
            }
            if ($f === 'document_type') {
                $fields[$f] = in_array($v, ['invoice', 'purchase_order', 'delivery_note'], true) ? $v : 'invoice';
            } elseif (self::grounded($v, $text, in_array($f, self::AMOUNTS, true))) {
                $fields[$f] = $v;
            } else {
                $dropped[$f] = $v;
            }
        }
        // Rules before the model (10 Nov 2026: mistral:7b dropped the PO number
        // on invoices and called a delivery note an invoice). The page heading
        // decides the type, and an order number printed in the document is
        // taken by pattern when the model gave none.
        $fields = self::applyRules($fields, $text);

        $lines = [];
        foreach ((array) ($data['line_items'] ?? []) as $li) {
            if (is_array($li) && isset($li['description']) && self::grounded((string) $li['description'], $text, false)
                && (! isset($li['amount']) || self::grounded((string) $li['amount'], $text, true))) {
                $lines[] = array_intersect_key($li, array_flip(['description', 'quantity', 'unit_price', 'amount']));
            } elseif (is_array($li)) {
                $dropped['line_item'] = (string) ($li['description'] ?? '?');
            }
        }

        $prefix = ['purchase_order' => 'po', 'delivery_note' => 'dn'][$fields['document_type'] ?? 'invoice'] ?? 'invoice';
        $saved = 0;
        $note = json_encode(['source' => 'document-field-extraction', 'grounded' => true, 'receipt' => $receipt, 'ocr' => (bool) array_filter(array_column($pages, 'ocr'))]);
        DB::table('ahg_ai_suggestion')->where('object_id', $ioId)->where('status', 'pending')
            ->where(fn ($q) => $q->where('field_name', 'like', 'invoice.%')->orWhere('field_name', 'like', 'po.%')->orWhere('field_name', 'like', 'dn.%'))->delete();
        foreach ($fields + ($lines ? ['line_items' => json_encode($lines, JSON_UNESCAPED_UNICODE)] : []) as $f => $v) {
            DB::table('ahg_ai_suggestion')->insert([
                'object_id' => $ioId, 'field_name' => $prefix.'.'.$f, 'suggested_value' => $v,
                'status' => 'pending', 'model' => $res['model'] ?? null, 'confidence' => 1.0, 'notes' => $note,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $saved++;
        }

        return ['fields' => $fields, 'line_items' => $lines, 'dropped' => $dropped, 'model' => $res['model'] ?? null, 'receipt' => $receipt, 'saved' => $saved];
    }

    /** The extracted fields of a record, from its suggestions (any status but rejected). */
    public function fieldsOf(int $ioId): array
    {
        $out = [];
        foreach (DB::table('ahg_ai_suggestion')->where('object_id', $ioId)->where('status', '!=', 'rejected')
            ->where(fn ($q) => $q->where('field_name', 'like', 'invoice.%')->orWhere('field_name', 'like', 'po.%')->orWhere('field_name', 'like', 'dn.%'))
            ->orderBy('id')->get(['field_name', 'suggested_value', 'status', 'model', 'notes']) as $r) {
            $out[substr($r->field_name, strpos($r->field_name, '.') + 1)] = $r;
        }

        return $out;
    }

    /**
     * Check an invoice against the purchase order it quotes.
     *
     * @return array{status: string, po_io?: int, invoice_total?: string, po_total?: string}
     *   status: no_po_number | po_not_found | totals_differ | matched,
     *   or delivered / delivered_po_not_found for a delivery note (no total to compare)
     */
    public function match(int $invoiceIo): array
    {
        $inv = $this->fieldsOf($invoiceIo);
        $po = $inv['po_number']->suggested_value ?? null;
        if (! $po) {
            return ['status' => 'no_po_number'];
        }
        $isDelivery = ($inv['document_type']->suggested_value ?? '') === 'delivery_note';
        $poIo = (int) DB::table('ahg_ai_suggestion')->where('field_name', 'po.number')->where('status', '!=', 'rejected')
            ->whereRaw('REPLACE(suggested_value, " ", "") = ?', [str_replace(' ', '', $po)])->value('object_id');
        if ($poIo === 0) {
            return ['status' => $isDelivery ? 'delivered_po_not_found' : 'po_not_found', 'po_number' => $po];
        }
        if ($isDelivery) {
            return ['status' => 'delivered', 'po_io' => $poIo, 'po_number' => $po];
        }
        $a = $inv['total']->suggested_value ?? null;
        $b = $this->fieldsOf($poIo)['total']->suggested_value ?? null;
        $same = $a !== null && $b !== null && self::amount($a) === self::amount($b);

        return ['status' => $same ? 'matched' : 'totals_differ', 'po_io' => $poIo, 'po_number' => $po, 'invoice_total' => $a, 'po_total' => $b];
    }

    /** Is $value in $text? Words verbatim (spacing and case aside); amounts by their digits. */
    public static function grounded(string $value, string $text, bool $amount): bool
    {
        $squash = fn (string $s) => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($s)));
        if ($amount) {
            // The amount must be one of the numbers written in the document.
            $want = self::amount($value);
            preg_match_all('/\d{1,3}(?:[ ,]\d{3})+(?:\.\d+)?|\d+(?:\.\d+)?/', $text, $m);

            return $want !== '' && in_array($want, array_map([self::class, 'amount'], $m[0]), true);
        }

        return $value !== '' && str_contains($squash($text), $squash($value));
    }

    /**
     * Deterministic corrections from the document text.
     *
     * @param array<string,string> $fields
     * @return array<string,string>
     */
    public static function applyRules(array $fields, string $text): array
    {
        $head = mb_strtoupper(mb_substr($text, 0, 600));
        if (str_contains($head, 'DELIVERY NOTE')) {
            $fields['document_type'] = 'delivery_note';
        } elseif (str_contains($head, 'PURCHASE ORDER')) {
            $fields['document_type'] = 'purchase_order';
        } elseif (preg_match('/\b(TAX\s+)?INVOICE\b/', $head)) {
            $fields['document_type'] = 'invoice';
        }

        if (($fields['document_type'] ?? 'invoice') !== 'purchase_order' && empty($fields['po_number'])) {
            // An order number under (or beside) its label, else a PO-style token.
            $own = (string) ($fields['number'] ?? '');
            $found = null;
            if (preg_match('/(?:your\s+order\s+no|order\s+no|order\s+number|p\.?o\.?\s+no|purchase\s+order)\.?\s*[:#]?\s*(?:\R\s*)?(?:[^\n]*\R\s*)?([A-Z]{1,5}-?\d{3,}[A-Z0-9-]*)/iu', $text, $m)) {
                $found = $m[1];
            } elseif (preg_match_all('/\bPO-?\d{3,}\b/u', $text, $mm)) {
                $found = collect($mm[0])->first(fn ($v) => $v !== $own);
            }
            if ($found && $found !== $own && ! preg_match('/^VAT/i', $found)) {
                $fields['po_number'] = $found;
            }
        }

        return $fields;
    }

    /** "R 153,617.00" -> "153617.00" */
    public static function amount(string $v): string
    {
        $d = (string) preg_replace('/[^0-9.]/', '', str_replace(',', '', $v));

        return $d === '' ? '' : number_format((float) $d, 2, '.', '');
    }

    private static function json(string $text): ?array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $d = json_decode($m[0], true);

            return is_array($d) ? $d : null;
        }

        return null;
    }

    private function fail(string $error): array
    {
        return ['fields' => [], 'line_items' => [], 'dropped' => [], 'model' => null, 'receipt' => null, 'saved' => 0, 'error' => $error];
    }
}
