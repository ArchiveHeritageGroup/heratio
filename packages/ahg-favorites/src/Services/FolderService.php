<?php

namespace AhgFavorites\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FolderService
{
    public function getUserFolders(int $userId): \Illuminate\Support\Collection
    {
        return DB::table('favorites_folder')
            ->where('user_id', $userId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($folder) use ($userId) {
                $folder->item_count = DB::table('favorites')
                    ->where('user_id', $userId)
                    ->where('folder_id', $folder->id)
                    ->count();
                return $folder;
            });
    }

    public function getUnfiledCount(int $userId): int
    {
        return DB::table('favorites')
            ->where('user_id', $userId)
            ->whereNull('folder_id')
            ->count();
    }

    public function createFolder(int $userId, string $name, ?string $description = null, ?string $color = null): int
    {
        return DB::table('favorites_folder')->insertGetId([
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'color' => $color,
            'visibility' => 'private',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateFolder(int $userId, int $id, array $data): bool
    {
        return DB::table('favorites_folder')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update(array_merge($data, ['updated_at' => now()])) > 0;
    }

    public function deleteFolder(int $userId, int $id): bool
    {
        // Move items in this folder to unfiled
        DB::table('favorites')
            ->where('user_id', $userId)
            ->where('folder_id', $id)
            ->update(['folder_id' => null, 'updated_at' => now()]);

        // Move child folders to root
        DB::table('favorites_folder')
            ->where('user_id', $userId)
            ->where('parent_id', $id)
            ->update(['parent_id' => null]);

        return DB::table('favorites_folder')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    public function getFolder(int $userId, int $id): ?object
    {
        return DB::table('favorites_folder')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function shareFolder(int $userId, int $id, int $days = 30): ?string
    {
        $token = Str::random(64);
        $updated = DB::table('favorites_folder')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update([
                'share_token' => $token,
                'share_expires_at' => now()->addDays($days),
                'visibility' => 'shared',
                'updated_at' => now(),
            ]);

        if (!$updated) return null;

        DB::table('favorites_share')->insert([
            'folder_id' => $id,
            'shared_via' => 'link',
            'token' => $token,
            'expires_at' => now()->addDays($days),
            'access_count' => 0,
            'created_at' => now(),
        ]);

        return $token;
    }

    public function revokeSharing(int $userId, int $id): bool
    {
        DB::table('favorites_share')
            ->where('folder_id', $id)
            ->delete();

        return DB::table('favorites_folder')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update([
                'share_token' => null,
                'share_expires_at' => null,
                'visibility' => 'private',
                'updated_at' => now(),
            ]) > 0;
    }

    public function getSharedFolder(string $token): ?object
    {
        $folder = DB::table('favorites_folder')
            ->where('share_token', $token)
            ->where(function ($q) {
                $q->whereNull('share_expires_at')
                    ->orWhere('share_expires_at', '>', now());
            })
            ->first();

        if ($folder) {
            // Track access
            DB::table('favorites_share')
                ->where('token', $token)
                ->update(['accessed_at' => now(), 'access_count' => DB::raw('access_count + 1')]);
        }

        return $folder;
    }

    public function getSharedFolderItems(int $folderId, int $userId): \Illuminate\Support\Collection
    {
        return DB::table('favorites')
            ->where('user_id', $userId)
            ->where('folder_id', $folderId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
