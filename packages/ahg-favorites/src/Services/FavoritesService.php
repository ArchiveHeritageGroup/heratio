<?php

/**
 * FavoritesService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use Illuminate\Support\Facades\DB;

class FavoritesService
{
    public function browse(int $userId, array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $sort = $params['sort'] ?? 'created_at';
        $sortDir = strtolower($params['sortDir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query = $params['query'] ?? null;
        $folderId = $params['folder_id'] ?? null;
        $unfiled = $params['unfiled'] ?? false;

        $q = DB::table('favorites')->where('user_id', $userId);

        if ($unfiled) {
            $q->whereNull('folder_id');
        } elseif ($folderId) {
            $q->where('folder_id', $folderId);
        }

        if ($query) {
            $q->where(function ($sub) use ($query) {
                $sub->where('archival_description', 'LIKE', "%{$query}%")
                    ->orWhere('reference_code', 'LIKE', "%{$query}%");
            });
        }

        $sortCol = match ($sort) {
            'title' => 'archival_description',
            'reference_code' => 'reference_code',
            'updated_at' => 'updated_at',
            default => 'created_at',
        };

        $total = $q->count();
        $results = $q->orderBy($sortCol, $sortDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'lastPage' => max(1, (int) ceil($total / $limit)),
            'limit' => $limit,
        ];
    }

    public function addToFavorites(int $userId, int $objectId, string $title, string $slug, string $objectType = 'information_object', ?string $referenceCode = null, ?int $folderId = null): bool
    {
        if ($this->isFavorited($userId, $objectId, $objectType)) {
            return false;
        }

        DB::table('favorites')->insert([
            'user_id' => $userId,
            'archival_description_id' => $objectId,
            'archival_description' => $title,
            'slug' => $slug,
            'object_type' => $objectType,
            'reference_code' => $referenceCode,
            'folder_id' => $folderId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    public function removeFromFavorites(int $userId, int $id): bool
    {
        return DB::table('favorites')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    public function toggle(int $userId, int $objectId, string $title, string $slug, string $objectType = 'information_object'): bool
    {
        if ($this->isFavorited($userId, $objectId, $objectType)) {
            DB::table('favorites')
                ->where('user_id', $userId)
                ->where('archival_description_id', $objectId)
                ->where('object_type', $objectType)
                ->delete();
            return false; // Removed
        }

        $this->addToFavorites($userId, $objectId, $title, $slug, $objectType);
        return true; // Added
    }

    public function isFavorited(int $userId, int $objectId, string $objectType = 'information_object'): bool
    {
        return DB::table('favorites')
            ->where('user_id', $userId)
            ->where('archival_description_id', $objectId)
            ->where('object_type', $objectType)
            ->exists();
    }

    public function getCount(int $userId): int
    {
        return DB::table('favorites')->where('user_id', $userId)->count();
    }

    public function updateNotes(int $userId, int $id, string $notes): bool
    {
        return DB::table('favorites')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update(['notes' => $notes, 'last_viewed_at' => now(), 'updated_at' => now()]) > 0;
    }

    public function bulkRemove(int $userId, array $ids): int
    {
        return DB::table('favorites')
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->delete();
    }

    public function moveToFolder(int $userId, array $ids, ?int $folderId): int
    {
        return DB::table('favorites')
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->update(['folder_id' => $folderId, 'updated_at' => now()]);
    }

    public function clearAll(int $userId): int
    {
        return DB::table('favorites')->where('user_id', $userId)->delete();
    }

    public function exportCsv(int $userId, ?int $folderId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = DB::table('favorites')->where('user_id', $userId);
        if ($folderId) {
            $query->where('folder_id', $folderId);
        }
        $items = $query->orderBy('created_at', 'desc')->get();

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Title', 'Reference Code', 'Type', 'Slug', 'Notes', 'Date Added']);
            foreach ($items as $item) {
                fputcsv($out, [
                    $item->archival_description,
                    $item->reference_code ?? '',
                    $item->object_type ?? '',
                    $item->slug ?? '',
                    $item->notes ?? '',
                    $item->created_at,
                ]);
            }
            fclose($out);
        }, 'favorites-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportJson(int $userId, ?int $folderId = null): \Illuminate\Http\JsonResponse
    {
        $query = DB::table('favorites')->where('user_id', $userId);
        if ($folderId) {
            $query->where('folder_id', $folderId);
        }
        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function importFromCsv(int $userId, string $content): int
    {
        $lines = explode("\n", trim($content));
        $count = 0;

        foreach ($lines as $line) {
            $slug = trim($line);
            if (empty($slug) || $slug === 'slug') continue;

            $slugRow = DB::table('slug')->where('slug', $slug)->first();
            if (!$slugRow) continue;

            $title = DB::table('information_object_i18n')
                ->where('id', $slugRow->object_id)
                ->where('culture', 'en')
                ->value('title') ?? $slug;

            if ($this->addToFavorites($userId, $slugRow->object_id, $title, $slug)) {
                $count++;
            }
        }

        return $count;
    }
}
