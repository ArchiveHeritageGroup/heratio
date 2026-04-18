<?php

/**
 * ApiKeyService - Service for Heratio
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



namespace AhgApi\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiKeyService
{
    /**
     * Create a new API key. Returns the raw key (shown once only).
     */
    public function createKey(int $userId, string $name, array $scopes = ['read'], ?int $rateLimit = 1000, ?string $expiresAt = null): array
    {
        $rawKey = Str::random(48);
        $hashedKey = hash('sha256', $rawKey);
        $prefix = substr($rawKey, 0, 8);

        $id = DB::table('ahg_api_key')->insertGetId([
            'user_id' => $userId,
            'name' => $name,
            'api_key' => $hashedKey,
            'api_key_prefix' => $prefix,
            'scopes' => json_encode($scopes),
            'rate_limit' => $rateLimit,
            'expires_at' => $expiresAt,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'api_key' => $rawKey, // Only shown on creation
            'name' => $name,
            'prefix' => $prefix,
            'scopes' => $scopes,
            'rate_limit' => $rateLimit,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * List API keys for a user (never exposes the secret).
     */
    public function listKeys(int $userId): array
    {
        return DB::table('ahg_api_key')
            ->where('user_id', $userId)
            ->select('id', 'name', 'api_key_prefix', 'scopes', 'rate_limit', 'expires_at', 'last_used_at', 'is_active', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($key) {
                $key->scopes = json_decode($key->scopes, true) ?: [];
                return $key;
            })
            ->toArray();
    }

    /**
     * Delete an API key (only if owned by the user).
     */
    public function deleteKey(int $id, int $userId): bool
    {
        return DB::table('ahg_api_key')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete() > 0;
    }
}
