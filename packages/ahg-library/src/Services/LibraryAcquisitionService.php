<?php

/**
 * LibraryAcquisitionService - orders, lines, and budgets (PSISA schema)
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;

/**
 * Backs the /library-manage/acquisitions surface against the PSISA
 * library_order / library_order_line / library_budget schema.
 *
 * Real-time budget accounting: committed and spent are updated after every
 * order/receive/cancel action so no nightly job is required.
 */
class LibraryAcquisitionService
{
    // ── Orders ────────────────────────────────────────────────────────────

    /**
     * List orders with optional filters (status, search).
     * Attaches line_count and total_amount from library_order_line.
     */
    public function listOrders(array $filters = []): array
    {
        $q = DB::table('library_order');

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('order_number', 'LIKE', $needle)
                    ->orWhere('vendor_name', 'LIKE', $needle);
            });
        }

        $rows = $q->orderByDesc('order_date')->orderByDesc('id')->get()->all();

        if ($rows) {
            $ids = array_map(fn($r) => (int) $r->id, $rows);
            $aggs = DB::table('library_order_line')
                ->select(
                    'order_id',
                    DB::raw('COUNT(*) as line_count'),
                    DB::raw('COALESCE(SUM(line_total), 0) as total_amount')
                )
                ->whereIn('order_id', $ids)
                ->groupBy('order_id')
                ->get()
                ->keyBy('order_id');
            foreach ($rows as $r) {
                $agg = $aggs->get($r->id);
                $r->line_count   = $agg ? (int) $agg->line_count : 0;
                $r->total_amount = $agg ? (float) $agg->total_amount : 0.0;
            }
        }

        return $rows;
    }

    public function getOrder(int $id): ?object
    {
        return DB::table('library_order')->where('id', $id)->first() ?: null;
    }

    /**
     * Create a new order. Order number is auto-generated if not supplied.
     */
    public function createOrder(array $data): int
    {
        $now = now();

        // If a registered vendor was chosen but no free-text name supplied,
        // resolve the display name from library_vendor.
        if (!empty($data['vendor_id']) && empty($data['vendor_name'])) {
            $data['vendor_name'] = (string) DB::table('library_vendor')
                ->where('id', $data['vendor_id'])
                ->value('name');
        }

        $row = [
            'order_number'    => $data['order_number'] ?? $this->generateOrderNumber(),
            'order_date'      => $data['order_date'] ?? $now->toDateString(),
            'expected_date'   => $data['expected_date'] ?? null,
            'vendor_name'     => $data['vendor_name'] ?? '',
            'vendor_id'       => $data['vendor_id'] ?? null,
            'vendor_reference'=> $data['vendor_reference'] ?? '',
            'budget_code'     => $data['budget_code'] ?? null,
            'order_type'      => $data['order_type'] ?? 'purchase',
            'status'          => $data['status'] ?? 'draft',
            'shipping'        => (float) ($data['shipping'] ?? $data['shipping_cost'] ?? 0),
            'subtotal'        => 0.0,
            'tax'             => 0.0,
            'total'           => 0.0,
            'currency'        => $data['currency'] ?? 'ZAR',
            'payment_status' => 'unpaid',
            'shipping_address'=> $data['shipping_address'] ?? '',
            'notes'           => $data['notes'] ?? null,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
        $id = (int) DB::table('library_order')->insertGetId($row);

        // Recalculate the budget's committed amount now that this order is live
        if (!empty($row['budget_code'])) {
            $this->recalculateBudgetByCode($row['budget_code']);
        }

        return $id;
    }

    /**
     * Update order header fields. Null values are preserved (no overwrite).
     */
    public function updateOrder(int $id, array $data): bool
    {
        $order = $this->getOrder($id);
        if (!$order) {
            return false;
        }

        // Map service-layer field names to PSISA column names
        if (array_key_exists('shipping_cost', $data) && !array_key_exists('shipping', $data)) {
            $data['shipping'] = $data['shipping_cost'];
        }

        $updatable = [
            'order_number', 'order_date', 'expected_date', 'vendor_name',
            'vendor_id', 'vendor_reference', 'budget_code', 'order_type',
            'status', 'shipping', 'tax', 'currency', 'payment_status',
            'shipping_address', 'notes',
        ];
        $payload = array_intersect_key($data, array_flip($updatable));
        if (empty($payload)) {
            return false;
        }

        $payload['updated_at'] = now();

        $oldBudgetCode = $order->budget_code ?? null;
        $newBudgetCode = $payload['budget_code'] ?? $oldBudgetCode;

        $affected = DB::table('library_order')->where('id', $id)->update($payload);

        // Recalculate budgets whose committed totals changed
        if ($oldBudgetCode && $oldBudgetCode !== $newBudgetCode) {
            $this->recalculateBudgetByCode($oldBudgetCode);
        }
        if ($newBudgetCode) {
            $this->recalculateBudgetByCode($newBudgetCode);
        }

        return $affected > 0;
    }

    /**
     * Transition order to a new status with guardrails.
     */
    public function transitionOrder(int $id, string $newStatus): bool
    {
        $valid = ['draft', 'submitted', 'approved', 'ordered', 'partial', 'received', 'cancelled'];
        if (!in_array($newStatus, $valid)) {
            return false;
        }

        $order = $this->getOrder($id);
        if (!$order) {
            return false;
        }

        $payload = ['status' => $newStatus, 'updated_at' => now()];

        // Set received_date when marking as received
        if ($newStatus === 'received') {
            $payload['received_date'] = now()->toDateString();
        }

        DB::table('library_order')->where('id', $id)->update($payload);

        if ($order->budget_code) {
            $this->recalculateBudgetByCode($order->budget_code);
        }

        return true;
    }

    /**
     * GRAP 103 / IPSAS 17 disposal: write off an order (or its remaining
     * outstanding value) with an audited reason code. Sets the order status to
     * 'cancelled' so its committed amount is released from the budget, and
     * records who/when/why on the order header.
     *
     * @param string      $reason  one of the acq_disposal_reason dropdown codes
     * @param string|null $by      identifier of the acting account
     */
    public function writeOffOrder(int $id, string $reason, ?string $by = null, ?string $note = null): bool
    {
        $order = $this->getOrder($id);
        if (!$order) {
            return false;
        }

        $payload = [
            'status'            => 'cancelled',
            'written_off_reason' => $reason,
            'written_off_by'    => $by ?? (string) (auth()->id() ?? ''),
            'written_off_date'  => now()->toDateString(),
            'updated_at'        => now(),
        ];
        if ($note !== null && $note !== '') {
            $existing = (string) ($order->notes ?? '');
            $stamp    = '[write-off ' . now()->toDateString() . " / {$reason}] " . $note;
            $payload['notes'] = $existing === '' ? $stamp : ($existing . "\n" . $stamp);
        }

        DB::table('library_order')->where('id', $id)->update($payload);

        // Cancelled orders are excluded from the committed total, so refresh the
        // budget to release the commitment.
        if ($order->budget_code) {
            $this->recalculateBudgetByCode($order->budget_code);
        }

        return true;
    }

    /**
     * Receive specific quantities per line (partial or full delivery).
     * $perLine maps line_id => quantity_received (the cumulative received count).
     * Pass an empty array with $receiveAll=true to receive every outstanding unit.
     * Returns the count of lines touched.
     */
    public function receiveLines(int $orderId, array $perLine = [], bool $receiveAll = false): int
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            return 0;
        }

        if ($receiveAll && empty($perLine)) {
            return $this->receiveAllLines($orderId);
        }

        $now   = now();
        $today = $now->toDateString();
        $touched = 0;

        foreach ($perLine as $lineId => $received) {
            $line = $this->getLine((int) $lineId);
            if (!$line || (int) $line->order_id !== $orderId) {
                continue;
            }
            $qty      = (int) $line->quantity;
            $received = max(0, min($qty, (int) $received));

            $status = $received <= 0
                ? ($line->status ?? 'pending')
                : ($received >= $qty ? 'received' : 'partial');

            DB::table('library_order_line')->where('id', $line->id)->update([
                'quantity_received' => $received,
                'received_date'     => $received > 0 ? $today : null,
                'status'            => $status,
                'updated_at'        => $now,
            ]);
            $touched++;
        }

        $this->recalculateOrderTotals($orderId);
        $this->recalculateBudgetForOrder($orderId);

        if ($touched > 0) {
            // Materialise copies for any fully received lines.
            $this->createCopiesForOrder($orderId);
        }

        return $touched;
    }

    public function generateOrderNumber(): string
    {
        $base = 'PO-' . date('Ymd');
        $count = (int) DB::table('library_order')
            ->where('order_number', 'LIKE', $base . '-%')
            ->count();
        return $base . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    // ── Order Lines ────────────────────────────────────────────────────────

    public function getOrderLines(int $orderId): array
    {
        return DB::table('library_order_line')
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function getLine(int $lineId): ?object
    {
        return DB::table('library_order_line')->where('id', $lineId)->first() ?: null;
    }

    /**
     * Add a line to an order. Returns the new line ID.
     */
    public function addLine(int $orderId, array $data): int
    {
        $now = now();
        $qty       = (int) ($data['quantity'] ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $discount  = (float) ($data['discount_percent'] ?? 0);
        $lineTotal = $qty * $unitPrice * (1 - ($discount / 100));

        $row = [
            'order_id'         => $orderId,
            'library_item_id'  => $data['library_item_id'] ?? null,
            'isbn'             => $data['isbn'] ?? '',
            'title'            => $data['title'] ?? '',
            'author'           => $data['author'] ?? '',
            'publisher'        => $data['publisher'] ?? '',
            'edition'          => $data['edition'] ?? '',
            'material_type'    => $data['format'] ?? $data['material_type'] ?? '',
            'quantity'         => $qty,
            'unit_price'       => $unitPrice,
            'discount_percent' => $discount,
            'line_total'       => $lineTotal,
            'quantity_received'=> 0,
            'received_date'    => null,
            'status'           => $data['line_status'] ?? 'pending',
            'budget_code'      => $data['budget_code'] ?? null,
            'fund_code'        => $data['fund_code'] ?? '',
            'notes'            => $data['notes'] ?? '',
            'created_at'       => $now,
        ];
        $lineId = (int) DB::table('library_order_line')->insertGetId($row);

        $this->recalculateOrderTotals($orderId);
        $this->recalculateBudgetForOrder($orderId);

        return $lineId;
    }

    /**
     * Refresh the committed/spent totals on every budget an order touches:
     * the order's own budget_code (legacy single-fund lines) PLUS every
     * fund_code referenced by split rows on this order's lines (#1311).
     */
    protected function recalculateBudgetForOrder(int $orderId): void
    {
        $codes = [];

        $code = DB::table('library_order')->where('id', $orderId)->value('budget_code');
        if ($code) {
            $codes[$code] = true;
        }

        if ($this->hasFundSplitTable()) {
            $splitCodes = DB::table('library_order_line_fund as f')
                ->join('library_order_line as l', 'l.id', '=', 'f.order_line_id')
                ->where('l.order_id', $orderId)
                ->distinct()
                ->pluck('f.fund_code')
                ->all();
            foreach ($splitCodes as $c) {
                if ($c) {
                    $codes[$c] = true;
                }
            }
        }

        foreach (array_keys($codes) as $c) {
            $this->recalculateBudgetByCode($c);
        }
    }

    /**
     * Update a line's fields.
     */
    public function updateLine(int $lineId, array $data): bool
    {
        $line = $this->getLine($lineId);
        if (!$line) {
            return false;
        }

        // Remap received_qty to PSISA column quantity_received
        $map = [
            'received_qty' => 'quantity_received',
            'line_status'  => 'status',
        ];
        foreach ($map as $from => $to) {
            if (array_key_exists($from, $data) && !array_key_exists($to, $data)) {
                $data[$to] = $data[$from];
            }
        }

        $updatable = [
            'library_item_id', 'isbn', 'title', 'author', 'publisher',
            'edition', 'material_type', 'quantity', 'unit_price',
            'discount_percent', 'quantity_received', 'received_date',
            'status', 'budget_code', 'fund_code', 'notes',
        ];
        $payload = array_intersect_key($data, array_flip($updatable));

        // Recalculate line_total if price/qty/discount changed
        $recalc = false;
        if (isset($payload['quantity']) || isset($payload['unit_price']) || isset($payload['discount_percent'])) {
            $qty    = (float) ($payload['quantity']     ?? $line->quantity);
            $price  = (float) ($payload['unit_price']   ?? $line->unit_price);
            $disc   = (float) ($payload['discount_percent'] ?? $line->discount_percent);
            $payload['line_total'] = $qty * $price * (1 - ($disc / 100));
            $recalc = true;
        }

        if (empty($payload)) {
            return false;
        }
        $payload['updated_at'] = now();

        DB::table('library_order_line')->where('id', $lineId)->update($payload);

        $this->recalculateOrderTotals((int) $line->order_id);
        $this->recalculateBudgetForOrder((int) $line->order_id);

        return true;
    }

    /**
     * Remove a line from an order.
     */
    public function removeLine(int $lineId): bool
    {
        $line = $this->getLine($lineId);
        if (!$line) {
            return false;
        }
        $orderId = (int) $line->order_id;

        // Capture split fund codes before deletion so their budgets refresh.
        $splitCodes = [];
        foreach ($this->getLineFundSplits($lineId) as $s) {
            if ($s->fund_code) {
                $splitCodes[$s->fund_code] = true;
            }
        }

        DB::table('library_order_line')->where('id', $lineId)->delete();
        if ($this->hasFundSplitTable()) {
            DB::table('library_order_line_fund')->where('order_line_id', $lineId)->delete();
        }

        $this->recalculateOrderTotals($orderId);
        $this->recalculateBudgetForOrder($orderId);
        foreach (array_keys($splitCodes) as $c) {
            $this->recalculateBudgetByCode($c);
        }

        return true;
    }

    /**
     * Recalculate subtotal / total on the order header from all lines.
     * Also derives an order-level status from line statuses.
     */
    public function recalculateOrderTotals(int $orderId): void
    {
        $lineRows = DB::table('library_order_line')->where('order_id', $orderId)->get();
        $subtotal = (float) $lineRows->sum('line_total');

        $order = $this->getOrder($orderId);
        if (!$order) {
            return;
        }

        $shipping  = (float) ($order->shipping ?? 0);
        $tax       = (float) ($order->tax ?? 0);
        $total     = $subtotal + $shipping + $tax;

        // A cancelled / written-off order keeps its terminal status; only its
        // monetary totals are refreshed.
        if (($order->status ?? '') === 'cancelled') {
            DB::table('library_order')->where('id', $orderId)->update([
                'subtotal'   => $subtotal,
                'total'      => $total,
                'updated_at' => now(),
            ]);
            return;
        }

        // Derive order-level status from line statuses
        if ($lineRows->isEmpty()) {
            $derived = 'draft';
        } elseif ($lineRows->every(fn($l) => $l->status === 'received')) {
            $derived = 'received';
        } elseif ($lineRows->contains(fn($l) => $l->status === 'received')) {
            $derived = 'partial';
        } else {
            $derived = 'ordered';
        }

        DB::table('library_order')->where('id', $orderId)->update([
            'subtotal'   => $subtotal,
            'total'      => $total,
            'status'     => $derived,
            'updated_at' => now(),
        ]);
    }

    /**
     * Mark all pending lines as received (full delivery).
     * Auto-creates library_copy rows on full receipt (Phase 2 decision).
     * Returns the count of lines updated.
     */
    public function receiveAllLines(int $orderId): int
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            return 0;
        }

        $count = DB::table('library_order_line')
            ->where('order_id', $orderId)
            ->where('status', 'pending')
            ->update([
                'quantity_received' => DB::raw('quantity'),
                'received_date'     => $now ?? ($now = now())->toDateString(),
                'status'            => 'received',
                'updated_at'        => now(),
            ]);

        $this->recalculateOrderTotals($orderId);
        $this->recalculateBudgetForOrder($orderId);

        // Auto-create copies when all lines are received
        if ($count > 0) {
            $this->createCopiesForOrder($orderId);
        }

        return $count;
    }

    /**
     * Auto-create library_copy rows for each received line.
     * One row per unit (quantity > 1 = multiple copies of same title).
     */
    protected function createCopiesForOrder(int $orderId): void
    {
        $lines = DB::table('library_order_line')
            ->where('order_id', $orderId)
            ->where('status', 'received')
            ->get();

        $now      = now();
        $copyRows = [];

        foreach ($lines as $line) {
            $qty = (int) ($line->quantity > 0 ? $line->quantity : 1);
            for ($i = 0; $i < $qty; $i++) {
                $copyRows[] = [
                    'title'       => $line->title ?? '',
                    'isbn'        => $line->isbn ?? '',
                    'author'      => $line->author ?? '',
                    'publisher'   => $line->publisher ?? '',
                    'pub_year'    => $line->pub_year ?? null,
                    'format'      => $line->material_type ?? '',
                    'barcode'     => $this->generateBarcode(),
                    'order_id'    => $orderId,
                    'copy_number' => $i + 1,
                    'status'      => 'available',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        if (!empty($copyRows)) {
            try {
                if (\Schema::hasTable('library_copy')) {
                    DB::table('library_copy')->insert($copyRows);
                }
            } catch (\Throwable) {
                // library_copy may not exist in a minimal deployment; skip
            }
        }
    }

    protected function generateBarcode(): string
    {
        if (\Schema::hasTable('library_copy')) {
            $last = DB::table('library_copy')
                ->whereNotNull('barcode')
                ->orderByDesc('id')
                ->value('barcode');

            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                return 'LBC' . str_pad((string) ((int) $m[1] + 1), 8, '0', STR_PAD_LEFT);
            }
        }
        return 'LBC' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    // ── Budgets ───────────────────────────────────────────────────────────

    /**
     * List budgets with optional fiscal_year filter.
     * Spent = sum of received lines; committed = sum of all non-cancelled lines.
     */
    public function listBudgets(array $filters = []): array
    {
        $q = DB::table('library_budget');

        if (!empty($filters['fiscal_year'])) {
            $q->where('fiscal_year', (string) $filters['fiscal_year']);
        }

        $rows = $q->orderByDesc('fiscal_year')->orderBy('fund_name')->get()->all();

        if ($rows) {
            $ids = array_map(fn($r) => (int) $r->id, $rows);

            // Spent: sum of line_total on received lines across orders with this budget
            $spent = DB::table('library_order_line as l')
                ->join('library_order as o', 'o.id', '=', 'l.order_id')
                ->whereIn('o.budget_code', function ($q) use ($ids) {
                    $q->select('budget_code')->from('library_budget')->whereIn('id', $ids);
                })
                ->whereIn('o.status', ['ordered', 'partial', 'received'])
                ->where('l.status', 'received')
                ->selectRaw('o.budget_code, SUM(l.line_total) as spent')
                ->groupBy('o.budget_code')
                ->pluck('spent', 'budget_code')
                ->all();

            // Committed: sum of all non-cancelled line totals
            $committed = DB::table('library_order_line as l')
                ->join('library_order as o', 'o.id', '=', 'l.order_id')
                ->whereIn('o.budget_code', function ($q) use ($ids) {
                    $q->select('budget_code')->from('library_budget')->whereIn('id', $ids);
                })
                ->whereNotIn('o.status', ['cancelled'])
                ->selectRaw('o.budget_code, SUM(l.line_total) as committed')
                ->groupBy('o.budget_code')
                ->pluck('committed', 'budget_code')
                ->all();

            foreach ($rows as $r) {
                $r->spent_amount = (float) ($spent[$r->budget_code] ?? 0);
                $r->committed_amount = (float) ($committed[$r->budget_code] ?? 0);
            }
        }

        return $rows;
    }

    public function getBudget(int $id): ?object
    {
        return DB::table('library_budget')->where('id', $id)->first() ?: null;
    }

    public function getBudgetByCode(string $code): ?object
    {
        return DB::table('library_budget')->where('budget_code', $code)->first() ?: null;
    }

    public function createBudget(array $data): int
    {
        $now = now();

        // Accept either the real column name (fund_name) or the legacy alias (name).
        $fundName = $data['fund_name'] ?? $data['name'] ?? '';

        $row = [
            'budget_code' => $data['budget_code'] ?? 'BUD-' . strtoupper(bin2hex(random_bytes(3))),
            'fund_name'   => $fundName,
            // fiscal_year is VARCHAR(9) in the live schema (e.g. "2026" or "2026/27").
            'fiscal_year' => (string) ($data['fiscal_year'] ?? date('Y')),
            'allocated_amount' => (float) ($data['allocated_amount'] ?? $data['allocated'] ?? 0),
            'spent_amount'     => 0.0,
            'committed_amount' => 0.0,
            'currency'    => $data['currency'] ?? 'ZAR',
            'category'    => $data['category'] ?? null,
            'department'  => $data['department'] ?? null,
            'status'      => $data['status'] ?? 'active',
            'created_by'  => $data['created_by'] ?? (auth()->id() ?? null),
            'notes'       => $data['notes'] ?? null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
        return (int) DB::table('library_budget')->insertGetId($row);
    }

    public function updateBudget(int $id, array $data): bool
    {
        // Map service/legacy aliases onto the real PSISA column names so web
        // edits actually persist (the old whitelist used non-existent columns).
        if (array_key_exists('name', $data) && !array_key_exists('fund_name', $data)) {
            $data['fund_name'] = $data['name'];
        }
        if (array_key_exists('allocated', $data) && !array_key_exists('allocated_amount', $data)) {
            $data['allocated_amount'] = $data['allocated'];
        }
        if (array_key_exists('fiscal_year', $data)) {
            $data['fiscal_year'] = (string) $data['fiscal_year'];
        }
        if (array_key_exists('allocated_amount', $data)) {
            $data['allocated_amount'] = (float) $data['allocated_amount'];
        }

        $updatable = [
            'budget_code', 'fund_name', 'fiscal_year', 'allocated_amount',
            'currency', 'category', 'department', 'status', 'notes',
        ];
        $payload = array_intersect_key($data, array_flip($updatable));
        if (empty($payload)) {
            return false;
        }
        $payload['updated_at'] = now();
        return DB::table('library_budget')->where('id', $id)->update($payload) > 0;
    }

    public function deleteBudget(int $id): bool
    {
        return DB::table('library_budget')->where('id', $id)->delete() > 0;
    }

    /**
     * Recalculate spent + committed for a budget by its code.
     * Called after every order/receive/cancel action.
     *
     * Multi-fund splitting (#1311): a budget's committed/spent now aggregate
     * two disjoint sources:
     *   1. Single-fund (legacy) lines whose order.budget_code is this $code AND
     *      which have NO rows in library_order_line_fund - the whole line_total
     *      charges here, exactly as before (fast path, behaviour unchanged).
     *   2. Split portions in library_order_line_fund whose fund_code is this
     *      $code - each portion's `amount` charges here regardless of which
     *      order.budget_code the parent line carries.
     * A line that has split rows is excluded from source (1) so its value is
     * never double-counted.
     */
    public function recalculateBudgetByCode(string $code): void
    {
        $budget = $this->getBudgetByCode($code);
        if (!$budget) {
            return;
        }

        $hasSplitTable = $this->hasFundSplitTable();

        // ── Source 1: legacy single-fund lines (no split rows) ────────────────
        $spentQ = DB::table('library_order_line as l')
            ->join('library_order as o', 'o.id', '=', 'l.order_id')
            ->where('o.budget_code', $code)
            ->whereIn('o.status', ['ordered', 'partial', 'received'])
            ->where('l.status', 'received');

        $committedQ = DB::table('library_order_line as l')
            ->join('library_order as o', 'o.id', '=', 'l.order_id')
            ->where('o.budget_code', $code)
            ->whereNotIn('o.status', ['cancelled']);

        if ($hasSplitTable) {
            $spentQ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('library_order_line_fund as f')
                    ->whereColumn('f.order_line_id', 'l.id');
            });
            $committedQ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('library_order_line_fund as f')
                    ->whereColumn('f.order_line_id', 'l.id');
            });
        }

        $spent     = (float) $spentQ->sum('l.line_total');
        $committed = (float) $committedQ->sum('l.line_total');

        // ── Source 2: split portions charged directly to this fund_code ───────
        if ($hasSplitTable) {
            $spent += (float) DB::table('library_order_line_fund as f')
                ->join('library_order_line as l', 'l.id', '=', 'f.order_line_id')
                ->join('library_order as o', 'o.id', '=', 'l.order_id')
                ->where('f.fund_code', $code)
                ->whereIn('o.status', ['ordered', 'partial', 'received'])
                ->where('l.status', 'received')
                ->sum('f.amount');

            $committed += (float) DB::table('library_order_line_fund as f')
                ->join('library_order_line as l', 'l.id', '=', 'f.order_line_id')
                ->join('library_order as o', 'o.id', '=', 'l.order_id')
                ->where('f.fund_code', $code)
                ->whereNotIn('o.status', ['cancelled'])
                ->sum('f.amount');
        }

        DB::table('library_budget')->where('id', $budget->id)->update([
            'spent_amount'     => $spent,
            'committed_amount' => $committed,
            'updated_at'       => now(),
        ]);
    }

    // ── Multi-fund line splitting (#1311) ────────────────────────────────────

    /**
     * Whether the split junction table is present (graceful on minimal installs).
     */
    protected function hasFundSplitTable(): bool
    {
        static $cached = null;
        if ($cached === null) {
            try {
                $cached = \Schema::hasTable('library_order_line_fund');
            } catch (\Throwable) {
                $cached = false;
            }
        }
        return $cached;
    }

    /**
     * Return the fund-split portions for an order line, oldest first.
     * Empty array means the line uses the legacy single fund_code path.
     */
    public function getLineFundSplits(int $lineId): array
    {
        if (!$this->hasFundSplitTable()) {
            return [];
        }
        return DB::table('library_order_line_fund')
            ->where('order_line_id', $lineId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Validate that supplied splits sum to the line's line_total (to the cent).
     *
     * $splits is a list of ['fund_code' => string, 'amount' => numeric].
     * Returns null when valid, otherwise a human-readable error string.
     */
    public function validateLineFundSplits(int $lineId, array $splits): ?string
    {
        $line = $this->getLine($lineId);
        if (!$line) {
            return 'Order line not found.';
        }

        // An empty set is valid: it clears the split and reverts to single-fund.
        if (empty($splits)) {
            return null;
        }

        $sum = 0.0;
        foreach ($splits as $i => $s) {
            $code = trim((string) ($s['fund_code'] ?? ''));
            if ($code === '') {
                return 'Fund code is required on every split row.';
            }
            if (!$this->getBudgetByCode($code)) {
                return "Unknown fund code '{$code}'.";
            }
            $amount = (float) ($s['amount'] ?? 0);
            if ($amount < 0) {
                return 'Split amounts cannot be negative.';
            }
            $sum += $amount;
        }

        $lineTotal = (float) $line->line_total;
        // Compare to the cent to tolerate float representation noise.
        if (round($sum, 2) !== round($lineTotal, 2)) {
            return sprintf(
                'Fund splits must sum to the line total (%.2f) but sum to %.2f.',
                $lineTotal,
                $sum
            );
        }

        return null;
    }

    /**
     * Persist the fund-split portions for a line, replacing any existing rows.
     *
     * Validates the sum against line_total first; on failure nothing is written
     * and the error string is returned. On success returns null and refreshes
     * every affected budget (old + new fund codes, plus the order's budget_code).
     *
     * Passing an empty $splits clears the split and reverts to the single
     * fund_code stored on the line.
     */
    public function saveLineFundSplits(int $lineId, array $splits): ?string
    {
        if (!$this->hasFundSplitTable()) {
            return 'Multi-fund splitting is not available on this installation.';
        }

        $line = $this->getLine($lineId);
        if (!$line) {
            return 'Order line not found.';
        }

        // Normalise: drop empty rows (blank fund_code AND zero amount).
        $clean = [];
        foreach ($splits as $s) {
            $code   = trim((string) ($s['fund_code'] ?? ''));
            $amount = (float) ($s['amount'] ?? 0);
            if ($code === '' && $amount == 0.0) {
                continue;
            }
            $clean[] = ['fund_code' => $code, 'amount' => $amount];
        }

        $error = $this->validateLineFundSplits($lineId, $clean);
        if ($error !== null) {
            return $error;
        }

        // Capture fund codes touched before and after so every budget refreshes.
        $affectedCodes = [];
        foreach ($this->getLineFundSplits($lineId) as $old) {
            $affectedCodes[$old->fund_code] = true;
        }

        DB::transaction(function () use ($lineId, $clean, &$affectedCodes) {
            DB::table('library_order_line_fund')->where('order_line_id', $lineId)->delete();
            $now = now();
            foreach ($clean as $s) {
                DB::table('library_order_line_fund')->insert([
                    'order_line_id' => $lineId,
                    'fund_code'     => $s['fund_code'],
                    'amount'        => $s['amount'],
                    'created_at'    => $now,
                ]);
                $affectedCodes[$s['fund_code']] = true;
            }
        });

        // The order's own budget_code may now lose this line's value (it moved
        // to splits), so refresh it too.
        if (!empty($line->budget_code)) {
            $affectedCodes[$line->budget_code] = true;
        }
        $orderBudget = DB::table('library_order')->where('id', $line->order_id)->value('budget_code');
        if ($orderBudget) {
            $affectedCodes[$orderBudget] = true;
        }

        foreach (array_keys($affectedCodes) as $code) {
            $this->recalculateBudgetByCode($code);
        }

        return null;
    }
}