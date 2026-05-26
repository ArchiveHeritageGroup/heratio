<?php

/**
 * LibraryIsbnProviderService - persisted ISBN lookup provider registry
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
 * Stores configured providers (Open Library, Google Books, WorldCat, etc.) so
 * the ISBN lookup workflow can fan out across them in priority order. The
 * /library-manage/isbn-lookup workflow currently hits Open Library directly;
 * once a provider has an api_url + api_key persisted here the controller can
 * iterate them in priority order until a hit is returned.
 */
class LibraryIsbnProviderService
{
    /** Ordered seed list used the first time the providers table is queried. */
    private const SEED = [
        ['name' => 'Open Library', 'api_url' => 'https://openlibrary.org/api/books', 'api_key' => '', 'priority' => 10, 'active' => 1],
        ['name' => 'Google Books', 'api_url' => 'https://www.googleapis.com/books/v1/volumes', 'api_key' => '', 'priority' => 20, 'active' => 0],
        ['name' => 'WorldCat',     'api_url' => 'https://www.worldcat.org/webservices/catalog/content', 'api_key' => '', 'priority' => 30, 'active' => 0],
    ];

    public function list(): array
    {
        if (!Schema::hasTable('library_isbn_provider')) {
            return $this->fallbackSeed();
        }

        $count = (int) DB::table('library_isbn_provider')->count();
        if ($count === 0) {
            $this->seedDefaults();
        }

        return DB::table('library_isbn_provider')
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function get(int $id): ?object
    {
        if (!Schema::hasTable('library_isbn_provider')) {
            return null;
        }
        return DB::table('library_isbn_provider')->where('id', $id)->first() ?: null;
    }

    public function save(int $id, array $data): int
    {
        if (!Schema::hasTable('library_isbn_provider')) {
            return 0;
        }
        $now = now();
        $row = [
            'name'     => $data['name'] ?? '',
            'api_url'  => $data['api_url'] ?? '',
            'api_key'  => $data['api_key'] ?? '',
            'priority' => (int) ($data['priority'] ?? 0),
            'active'   => !empty($data['active']) ? 1 : 0,
            'updated_at' => $now,
        ];

        if ($id > 0 && DB::table('library_isbn_provider')->where('id', $id)->exists()) {
            DB::table('library_isbn_provider')->where('id', $id)->update($row);
            return $id;
        }

        $row['created_at'] = $now;
        return (int) DB::table('library_isbn_provider')->insertGetId($row);
    }

    public function delete(int $id): bool
    {
        if (!Schema::hasTable('library_isbn_provider')) {
            return false;
        }
        return DB::table('library_isbn_provider')->where('id', $id)->delete() > 0;
    }

    /** In-memory placeholder list returned when the table doesn't exist yet. */
    private function fallbackSeed(): array
    {
        $out = [];
        $id = 1;
        foreach (self::SEED as $row) {
            $row['id'] = $id++;
            $out[] = (object) $row;
        }
        return $out;
    }

    private function seedDefaults(): void
    {
        $now = now();
        foreach (self::SEED as $row) {
            DB::table('library_isbn_provider')->insertOrIgnore(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
}
