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

        return $lineId;
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

        DB::table('library_order_line')->where('id', $lineId)->delete();
        $this->recalculateOrderTotals($orderId);

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
            $q->where('fiscal_year', (int) $filters['fiscal_year']);
        }

        $rows = $q->orderByDesc('fiscal_year')->orderBy('name')->get()->all();

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
        $row = [
            'budget_code' => $data['budget_code'] ?? 'BUD-' . strtoupper(bin2hex(random_bytes(3))),
            'name'        => $data['name'] ?? '',
            'fiscal_year' => (int) ($data['fiscal_year'] ?? date('Y')),
            'allocated_amount' => (float) ($data['allocated_amount'] ?? $data['allocated'] ?? 0),
            'spent_amount'     => 0.0,
            'committed_amount' => 0.0,
            'notes'       => $data['notes'] ?? null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
        return (int) DB::table('library_budget')->insertGetId($row);
    }

    public function updateBudget(int $id, array $data): bool
    {
        $updatable = ['code', 'name', 'fiscal_year', 'allocated', 'notes'];
        $payload   = array_intersect_key($data, array_flip($updatable));
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
     */
    public function recalculateBudgetByCode(string $code): void
    {
        $budget = $this->getBudgetByCode($code);
        if (!$budget) {
            return;
        }

        $spent = (float) DB::table('library_order_line as l')
            ->join('library_order as o', 'o.id', '=', 'l.order_id')
            ->where('o.budget_code', $code)
            ->whereIn('o.status', ['ordered', 'partial', 'received'])
            ->where('l.status', 'received')
            ->selectRaw('SUM(l.line_total)')
            ->value() ?: 0;

        $committed = (float) DB::table('library_order_line as l')
            ->join('library_order as o', 'o.id', '=', 'l.order_id')
            ->where('o.budget_code', $code)
            ->whereNotIn('o.status', ['cancelled'])
            ->selectRaw('SUM(l.line_total)')
            ->value() ?: 0;

        DB::table('library_budget')->where('id', $budget->id)->update([
            'spent'     => $spent,
            'committed' => $committed,
            'updated_at'=> now(),
        ]);
    }
}