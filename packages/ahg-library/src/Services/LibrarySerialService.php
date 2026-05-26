<?php

/**
 * LibrarySerialService - serial holdings + issue tracking
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
 * Backs the /library-manage/serials surface. Serial titles + their per-issue
 * holdings. Listing is enriched with issue_count so the index template renders
 * a usable count without per-row lookups.
 */
class LibrarySerialService
{
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_CEASED   = 'ceased';
    public const STATUS_SUSPENDED = 'suspended';

    public function list(array $filters = []): array
    {
        if (!Schema::hasTable('library_serial')) {
            return [];
        }

        $q = DB::table('library_serial');

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('title', 'LIKE', $needle)
                    ->orWhere('issn', 'LIKE', $needle)
                    ->orWhere('publisher', 'LIKE', $needle);
            });
        }

        $rows = $q->orderBy('title')->get()->all();

        if ($rows && Schema::hasTable('library_serial_issue')) {
            $ids = array_map(static fn($r) => (int) $r->id, $rows);
            $counts = DB::table('library_serial_issue')
                ->select('serial_id', DB::raw('COUNT(*) as cnt'))
                ->whereIn('serial_id', $ids)
                ->groupBy('serial_id')
                ->pluck('cnt', 'serial_id')
                ->all();
            foreach ($rows as $r) {
                $r->issue_count = (int) ($counts[$r->id] ?? 0);
            }
        }

        return $rows;
    }

    public function get(int $id): ?object
    {
        if (!Schema::hasTable('library_serial')) {
            return null;
        }
        $row = DB::table('library_serial')->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        $row->issues = Schema::hasTable('library_serial_issue')
            ? DB::table('library_serial_issue')
                ->where('serial_id', $id)
                ->orderByDesc('issue_date')
                ->get()
                ->all()
            : [];
        return $row;
    }

    public function create(array $data): int
    {
        if (!Schema::hasTable('library_serial')) {
            return 0;
        }
        $now = now();
        $row = [
            'title'      => $data['title'] ?? '',
            'issn'       => $data['issn'] ?? '',
            'frequency'  => $data['frequency'] ?? '',
            'publisher'  => $data['publisher'] ?? '',
            'status'     => $data['status'] ?? self::STATUS_ACTIVE,
            'notes'      => $data['notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        return (int) DB::table('library_serial')->insertGetId($row);
    }

    public function update(int $id, array $data): bool
    {
        if (!Schema::hasTable('library_serial')) {
            return false;
        }
        $payload = array_intersect_key($data, array_flip([
            'title', 'issn', 'frequency', 'publisher', 'status', 'notes',
        ]));
        if (!$payload) {
            return false;
        }
        $payload['updated_at'] = now();
        return DB::table('library_serial')->where('id', $id)->update($payload) > 0;
    }

    public function delete(int $id): bool
    {
        if (!Schema::hasTable('library_serial')) {
            return false;
        }
        DB::table('library_serial_issue')->where('serial_id', $id)->delete();
        return DB::table('library_serial')->where('id', $id)->delete() > 0;
    }

    public function addIssue(int $serialId, array $data): int
    {
        if (!Schema::hasTable('library_serial_issue')) {
            return 0;
        }
        $now = now();
        $row = [
            'serial_id'    => $serialId,
            'volume'       => $data['volume'] ?? '',
            'issue_number' => $data['issue_number'] ?? '',
            'issue_date'   => $data['issue_date'] ?? $now->toDateString(),
            'received_at'  => $data['received_at'] ?? null,
            'status'       => $data['status'] ?? 'received',
            'notes'        => $data['notes'] ?? null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        return (int) DB::table('library_serial_issue')->insertGetId($row);
    }
}
