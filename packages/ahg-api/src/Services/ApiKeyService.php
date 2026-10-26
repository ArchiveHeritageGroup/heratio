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

    /** Scopes a key may carry, in display order, with a plain description. */
    public const SCOPES = [
        'read' => 'Read the catalogue',
        'write' => 'Create and update records, and propose changes for review',
        'delete' => 'Delete records',
        'publish:write' => 'Publish and unpublish records',
        'batch' => 'Batch operations',
        'oai' => 'Harvest over OAI-PMH',
    ];

    /**
     * The scopes this user's keys may hold (#1558): their own role's, never
     * more, plus OAI harvesting (public metadata). A user with no catalogue
     * role gets read and oai, and their keys read the public catalogue only
     * (ApiAuthenticate).
     */
    public function allowedScopes(int $ownerId): array
    {
        $own = \AhgApi\Middleware\ApiAuthenticate::sessionScopes($ownerId);
        $allowed = array_unique(array_merge($own ?: ['read'], ['oai']));

        return array_values(array_filter(array_keys(self::SCOPES), fn ($s) => in_array($s, $allowed, true)));
    }

    /** Does this user hold no catalogue role (keys read the public catalogue only)? */
    public function publicOnly(int $ownerId): bool
    {
        return ! in_array('read', \AhgApi\Middleware\ApiAuthenticate::sessionScopes($ownerId), true);
    }

    /**
     * Issue a key for $ownerId with the chosen scopes, cut to what the owner
     * may hold. Returns the createKey() result, raw key included (shown once).
     */
    public function issue(int $ownerId, string $name, array $scopes, ?string $expiresAt = null, ?int $dailyLimit = null): array
    {
        $scopes = array_values(array_intersect(array_keys(self::SCOPES), $scopes, $this->allowedScopes($ownerId)));
        if (! $scopes) {
            $scopes = ['read'];
        }
        $key = $this->createKey($ownerId, $name, $scopes, 1000, $expiresAt);
        if ($dailyLimit) {
            DB::table('ahg_api_key')->where('id', $key['id'])->update(['daily_limit' => $dailyLimit]);
        }

        return $key;
    }

    /** All of a user's keys, newest first, scopes decoded; never the secret. */
    public function keysFor(int $ownerId)
    {
        return DB::table('ahg_api_key')->where('user_id', $ownerId)->orderByDesc('created_at')
            ->get(['id', 'name', 'api_key_prefix', 'scopes', 'rate_limit', 'daily_limit', 'expires_at', 'last_used_at', 'is_active', 'created_at'])
            ->map(function ($k) {
                $k->scopes = json_decode($k->scopes ?? '[]', true) ?: [];

                return $k;
            });
    }

    /** Revoke one of a user's keys; anything using it is refused at once. */
    public function revoke(int $id, int $ownerId): bool
    {
        return DB::table('ahg_api_key')->where('id', $id)->where('user_id', $ownerId)
            ->update(['is_active' => 0, 'updated_at' => now()]) > 0;
    }
}
