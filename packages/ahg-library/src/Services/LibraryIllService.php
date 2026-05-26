<?php

/**
 * LibraryIllService - Interlibrary loan request CRUD
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
 * Backs the /library-manage/ill surface. Tolerates missing table so the page
 * stays addressable on a fresh install before `php artisan migrate` /
 * install.sql has been run.
 */
class LibraryIllService
{
    public const TYPE_BORROW = 'borrow';
    public const TYPE_LEND   = 'lend';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_RECEIVED  = 'received';
    public const STATUS_RETURNED  = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    public function list(array $filters = []): array
    {
        if (!Schema::hasTable('library_ill_request')) {
            return [];
        }

        $q = DB::table('library_ill_request');

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('ill_number', 'LIKE', $needle)
                    ->orWhere('title', 'LIKE', $needle)
                    ->orWhere('author', 'LIKE', $needle)
                    ->orWhere('isbn', 'LIKE', $needle)
                    ->orWhere('library_name', 'LIKE', $needle);
            });
        }

        return $q->orderByDesc('request_date')->orderByDesc('id')->get()->all();
    }

    public function get(int $id): ?object
    {
        if (!Schema::hasTable('library_ill_request')) {
            return null;
        }
        return DB::table('library_ill_request')->where('id', $id)->first() ?: null;
    }

    public function create(array $data): int
    {
        if (!Schema::hasTable('library_ill_request')) {
            return 0;
        }
        $now = now();
        $row = [
            'ill_number'   => $data['ill_number'] ?? $this->generateIllNumber(),
            'type'         => $data['type'] ?? self::TYPE_BORROW,
            'title'        => $data['title'] ?? '',
            'author'       => $data['author'] ?? '',
            'isbn'         => $data['isbn'] ?? '',
            'library_name' => $data['library_name'] ?? '',
            'patron_id'    => $data['patron_id'] ?? null,
            'request_date' => $data['request_date'] ?? $now->toDateString(),
            'due_date'     => $data['due_date'] ?? null,
            'status'       => $data['status'] ?? self::STATUS_PENDING,
            'notes'        => $data['notes'] ?? null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        return (int) DB::table('library_ill_request')->insertGetId($row);
    }

    public function update(int $id, array $data): bool
    {
        if (!Schema::hasTable('library_ill_request')) {
            return false;
        }
        $payload = array_intersect_key($data, array_flip([
            'type', 'title', 'author', 'isbn', 'library_name', 'patron_id',
            'request_date', 'due_date', 'status', 'notes',
        ]));
        if (!$payload) {
            return false;
        }
        $payload['updated_at'] = now();
        return DB::table('library_ill_request')->where('id', $id)->update($payload) > 0;
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    public function delete(int $id): bool
    {
        if (!Schema::hasTable('library_ill_request')) {
            return false;
        }
        return DB::table('library_ill_request')->where('id', $id)->delete() > 0;
    }

    /** Reserve a unique ill_number using ILL-YYYYMMDD-#### form. */
    public function generateIllNumber(): string
    {
        $base = 'ILL-' . date('Ymd');
        if (!Schema::hasTable('library_ill_request')) {
            return $base . '-0001';
        }
        $count = (int) DB::table('library_ill_request')
            ->where('ill_number', 'LIKE', $base . '-%')
            ->count();
        return $base . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
