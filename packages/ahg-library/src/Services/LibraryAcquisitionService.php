<?php

/**
 * LibraryAcquisitionService - purchase orders, lines, and budgets
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
use Illuminate\Support\Facades\Schema;

/**
 * Backs the /library-manage/acquisitions surface. Orders carry lines (ISBN +
 * title + qty + unit_price); budgets are a simple allocated/spent ledger.
 * Spent totals are derived from received order lines so no manual upkeep is
 * required after an order is marked received.
 */
class LibraryAcquisitionService
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ORDERED   = 'ordered';
    public const STATUS_RECEIVED  = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    // ── Orders ────────────────────────────────────────────────────────────

    public function listOrders(array $filters = []): array
    {
        if (!Schema::hasTable('library_acquisition_order')) {
            return [];
        }

        $q = DB::table('library_acquisition_order');

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

        if ($rows && Schema::hasTable('library_acquisition_order_line')) {
            $ids = array_map(static fn($r) => (int) $r->id, $rows);
            $aggs = DB::table('library_acquisition_order_line')
                ->select(
                    'order_id',
                    DB::raw('COUNT(*) as line_count'),
                    DB::raw('SUM(quantity * unit_price) as total_amount')
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
        if (!Schema::hasTable('library_acquisition_order')) {
            return null;
        }
        return DB::table('library_acquisition_order')->where('id', $id)->first() ?: null;
    }

    public function getOrderLines(int $orderId): array
    {
        if (!Schema::hasTable('library_acquisition_order_line')) {
            return [];
        }
        return DB::table('library_acquisition_order_line')
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function createOrder(array $data): int
    {
        if (!Schema::hasTable('library_acquisition_order')) {
            return 0;
        }
        $now = now();
        $row = [
            'order_number' => $data['order_number'] ?? $this->generateOrderNumber(),
            'vendor_name'  => $data['vendor_name'] ?? '',
            'order_date'   => $data['order_date'] ?? $now->toDateString(),
            'status'       => $data['status'] ?? self::STATUS_DRAFT,
            'budget_id'    => $data['budget_id'] ?? null,
            'notes'        => $data['notes'] ?? null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        return (int) DB::table('library_acquisition_order')->insertGetId($row);
    }

    public function updateOrder(int $id, array $data): bool
    {
        if (!Schema::hasTable('library_acquisition_order')) {
            return false;
        }
        $payload = array_intersect_key($data, array_flip([
            'order_number', 'vendor_name', 'order_date', 'status', 'budget_id', 'notes',
        ]));
        if (!$payload) {
            return false;
        }
        $payload['updated_at'] = now();
        return DB::table('library_acquisition_order')->where('id', $id)->update($payload) > 0;
    }

    public function addLine(int $orderId, array $data): int
    {
        if (!Schema::hasTable('library_acquisition_order_line')) {
            return 0;
        }
        $now = now();
        $row = [
            'order_id'    => $orderId,
            'isbn'        => $data['isbn'] ?? '',
            'title'       => $data['title'] ?? '',
            'quantity'    => (int) ($data['quantity'] ?? 1),
            'unit_price'  => (float) ($data['unit_price'] ?? 0),
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
        return (int) DB::table('library_acquisition_order_line')->insertGetId($row);
    }

    public function generateOrderNumber(): string
    {
        $base = 'PO-' . date('Ymd');
        if (!Schema::hasTable('library_acquisition_order')) {
            return $base . '-0001';
        }
        $count = (int) DB::table('library_acquisition_order')
            ->where('order_number', 'LIKE', $base . '-%')
            ->count();
        return $base . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    // ── Budgets ───────────────────────────────────────────────────────────

    public function listBudgets(array $filters = []): array
    {
        if (!Schema::hasTable('library_acquisition_budget')) {
            return [];
        }

        $q = DB::table('library_acquisition_budget');

        if (!empty($filters['fiscal_year'])) {
            $q->where('fiscal_year', $filters['fiscal_year']);
        }

        $rows = $q->orderByDesc('fiscal_year')->orderBy('name')->get()->all();

        if ($rows && Schema::hasTable('library_acquisition_order_line') && Schema::hasTable('library_acquisition_order')) {
            $ids = array_map(static fn($r) => (int) $r->id, $rows);
            $spent = DB::table('library_acquisition_order_line as l')
                ->join('library_acquisition_order as o', 'o.id', '=', 'l.order_id')
                ->select('o.budget_id', DB::raw('SUM(l.quantity * l.unit_price) as spent'))
                ->whereIn('o.budget_id', $ids)
                ->whereIn('o.status', [self::STATUS_ORDERED, self::STATUS_RECEIVED])
                ->groupBy('o.budget_id')
                ->pluck('spent', 'budget_id')
                ->all();
            foreach ($rows as $r) {
                $r->spent = (float) ($spent[$r->id] ?? 0);
            }
        }

        return $rows;
    }

    public function getBudget(int $id): ?object
    {
        if (!Schema::hasTable('library_acquisition_budget')) {
            return null;
        }
        return DB::table('library_acquisition_budget')->where('id', $id)->first() ?: null;
    }

    public function createBudget(array $data): int
    {
        if (!Schema::hasTable('library_acquisition_budget')) {
            return 0;
        }
        $now = now();
        $row = [
            'name'        => $data['name'] ?? '',
            'fiscal_year' => $data['fiscal_year'] ?? (int) date('Y'),
            'allocated'   => (float) ($data['allocated'] ?? 0),
            'notes'       => $data['notes'] ?? null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
        return (int) DB::table('library_acquisition_budget')->insertGetId($row);
    }
}
