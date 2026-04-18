<?php

/**
 * CartService - Service for Heratio
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



namespace AhgCart\Services;

use Illuminate\Support\Facades\DB;

class CartService
{
    public function addToCart(?int $userId, ?string $sessionId, int $objectId, string $title, string $slug): bool
    {
        // Prevent duplicates
        $exists = DB::table('cart')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn ($q) => $q->where('session_id', $sessionId))
            ->where('archival_description_id', $objectId)
            ->whereNull('completed_at')
            ->exists();

        if ($exists) {
            return false;
        }

        DB::table('cart')->insert([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'archival_description_id' => $objectId,
            'archival_description' => $title,
            'slug' => $slug,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    public function getCart(?int $userId, ?string $sessionId): \Illuminate\Support\Collection
    {
        return DB::table('cart')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn ($q) => $q->where('session_id', $sessionId))
            ->whereNull('completed_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getCartCount(?int $userId, ?string $sessionId): int
    {
        return DB::table('cart')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn ($q) => $q->where('session_id', $sessionId))
            ->whereNull('completed_at')
            ->count();
    }

    public function removeItem(int $id, ?int $userId): bool
    {
        return DB::table('cart')
            ->where('id', $id)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->delete() > 0;
    }

    public function clearAll(?int $userId, ?string $sessionId): int
    {
        return DB::table('cart')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn ($q) => $q->where('session_id', $sessionId))
            ->whereNull('completed_at')
            ->delete();
    }

    public function mergeGuestCart(string $sessionId, int $userId): void
    {
        $guestItems = DB::table('cart')
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->whereNull('completed_at')
            ->get();

        foreach ($guestItems as $item) {
            $exists = DB::table('cart')
                ->where('user_id', $userId)
                ->where('archival_description_id', $item->archival_description_id)
                ->whereNull('completed_at')
                ->exists();

            if (!$exists) {
                DB::table('cart')
                    ->where('id', $item->id)
                    ->update(['user_id' => $userId, 'session_id' => null]);
            } else {
                DB::table('cart')->where('id', $item->id)->delete();
            }
        }
    }
}
