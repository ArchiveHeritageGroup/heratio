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
        // Prevent duplicates within the same kind
        $exists = DB::table('cart')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn ($q) => $q->where('session_id', $sessionId))
            ->where('kind', 'reproduction')
            ->where('archival_description_id', $objectId)
            ->whereNull('completed_at')
            ->exists();

        if ($exists) {
            return false;
        }

        DB::table('cart')->insert([
            'kind' => 'reproduction',
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

    /**
     * Add a marketplace listing to the cart. Idempotent: a listing already
     * in the user's cart returns false rather than duplicating.
     */
    public function addListingToCart(?int $userId, ?string $sessionId, int $listingId): bool
    {
        $listing = DB::table('marketplace_listing')->where('id', $listingId)->first();
        if (!$listing) {
            return false;
        }
        if ($listing->status !== 'active') {
            return false;
        }

        $exists = DB::table('cart')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn ($q) => $q->where('session_id', $sessionId))
            ->where('kind', 'marketplace')
            ->where('listing_id', $listingId)
            ->whereNull('completed_at')
            ->exists();

        if ($exists) {
            return false;
        }

        DB::table('cart')->insert([
            'kind' => 'marketplace',
            'user_id' => $userId,
            'session_id' => $sessionId,
            'listing_id' => $listingId,
            'archival_description_id' => $listing->information_object_id,
            'archival_description' => $listing->title,
            'slug' => $listing->slug,
            'unit_price' => $listing->price,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Marketplace-only cart with pricing joined back from marketplace_listing.
     * Returns ['items' => Collection, 'subtotal' => float, 'currency' => 'ZAR'].
     */
    public function getMarketplaceCart(?int $userId, ?string $sessionId): array
    {
        $items = DB::table('cart as c')
            ->join('marketplace_listing as l', 'l.id', '=', 'c.listing_id')
            ->leftJoin('marketplace_seller as s', 's.id', '=', 'l.seller_id')
            ->when($userId, fn ($q) => $q->where('c.user_id', $userId))
            ->when(!$userId, fn ($q) => $q->where('c.session_id', $sessionId))
            ->where('c.kind', 'marketplace')
            ->whereNull('c.completed_at')
            ->select(
                'c.id as cart_id',
                'c.created_at as added_at',
                'c.quantity',
                'l.id as listing_id', 'l.title', 'l.slug', 'l.price', 'l.currency',
                'l.featured_image_path', 'l.status', 'l.listing_type',
                's.display_name as seller_name', 's.slug as seller_slug'
            )
            ->orderByDesc('c.created_at')
            ->get();

        $subtotal = $items->reduce(
            fn (float $carry, $item) => $carry + ((float) $item->price * (int) ($item->quantity ?: 1)),
            0.0
        );
        $currency = $items->first()->currency ?? 'ZAR';

        return ['items' => $items, 'subtotal' => $subtotal, 'currency' => $currency];
    }

    public function getCart(?int $userId, ?string $sessionId): \Illuminate\Support\Collection
    {
        // Reproduction kind only — marketplace listings have their own getter.
        return DB::table('cart')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn ($q) => $q->where('session_id', $sessionId))
            ->where('kind', 'reproduction')
            ->whereNull('completed_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getCartCount(?int $userId, ?string $sessionId): int
    {
        // Combined count across both kinds — used for badge displays.
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

    /**
     * Remove a marketplace listing from the user's cart by listing_id.
     * Used by the in-place "Remove from cart" affordance on the browse grid
     * where the page only knows the listing id, not the cart row id.
     */
    public function removeListingFromCart(int $userId, int $listingId): bool
    {
        return DB::table('cart')
            ->where('user_id', $userId)
            ->where('kind', 'marketplace')
            ->where('listing_id', $listingId)
            ->whereNull('completed_at')
            ->delete() > 0;
    }

    /**
     * Listing IDs currently in the user's marketplace cart, optionally
     * scoped to a candidate set so the browse page can mark cards.
     */
    public function getCartListingIds(int $userId, array $listingIds = []): array
    {
        $q = DB::table('cart')
            ->where('user_id', $userId)
            ->where('kind', 'marketplace')
            ->whereNull('completed_at');
        if (!empty($listingIds)) {
            $q->whereIn('listing_id', $listingIds);
        }
        return $q->pluck('listing_id')->map(fn ($v) => (int) $v)->all();
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
