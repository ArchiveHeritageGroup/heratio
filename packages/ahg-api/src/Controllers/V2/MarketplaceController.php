<?php

/**
 * MarketplaceController - Public REST API v2 for the marketplace.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author: Johan Pieterse <johan@plainsailingisystems.co.za>
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
 * Endpoints (all behind ahg-api bearer-token middleware api.auth:read or write):
 *   GET  /api/v2/marketplace/search        - listings search (q, filter, sort, page)
 *   POST /api/v2/marketplace/bid           - place a bid (listing_id, amount, bidder_id?)
 *   GET  /api/v2/marketplace/auction/{id}/status - high bid + time-to-close
 *   POST /api/v2/marketplace/favourite     - toggle favourite (listing_id, user_id?)
 *   GET  /api/v2/marketplace/currencies    - supported currencies
 *   GET  /api/v2/marketplace/categories    - listing categories
 *
 * PSIS twin: atom-ahg-plugins ahgMarketplacePlugin api*Action.class.php
 * Heratio issue: #736
 */

namespace AhgApi\Controllers\V2;

use AhgMarketplace\Services\MarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MarketplaceController extends BaseApiController
{
    protected MarketplaceService $service;

    public function __construct(MarketplaceService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    // =========================================================================
    //  GET /api/v2/marketplace/search
    // =========================================================================
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'        => 'nullable|string|max:255',
            'sort'     => 'nullable|string|in:newest,price_asc,price_desc,popular',
            'page'     => 'nullable|integer|min:1',
            'limit'    => 'nullable|integer|min:1|max:100',
            'filter'   => 'nullable|array',
            'sector'   => 'nullable|string',
            'category' => 'nullable|string',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'listing_type' => 'nullable|string',
        ]);

        if (!$this->service->tablesExist()) {
            return $this->error('marketplace_unavailable', 'Marketplace tables not installed.', 503);
        }

        $q = trim((string) $request->get('q', ''));
        [$page, $limit] = $this->paginationParams($request);
        $offset = ($page - 1) * $limit;

        // Build filter array - accept both flat params and filter[...] form
        $filterParam = $request->get('filter', []);
        if (!is_array($filterParam)) {
            $filterParam = [];
        }
        $filters = array_merge($filterParam, array_filter([
            'sector'       => $request->get('sector'),
            'category_id'  => $request->get('category_id'),
            'category'     => $request->get('category'),
            'listing_type' => $request->get('listing_type'),
            'seller_id'    => $request->get('seller_id'),
            'price_min'    => $request->get('price_min'),
            'price_max'    => $request->get('price_max'),
            'medium'       => $request->get('medium'),
            'country'      => $request->get('country'),
            'is_digital'   => $request->get('is_digital'),
            'sort'         => $request->get('sort'),
        ], fn ($v) => $v !== null && $v !== ''));

        try {
            if ($q === '') {
                // Browse mode - no search term, just filtered list
                $result = $this->service->getListings($filters, $page, $limit);
                $items  = $result['items'] ?? [];
                $total  = $result['total'] ?? 0;
            } else {
                $result = $this->service->searchListings($q, $filters, $limit, $offset);
                $items  = $result['items'] ?? [];
                $total  = $result['total'] ?? 0;
            }
        } catch (Throwable $e) {
            return $this->error('search_failed', $e->getMessage(), 500);
        }

        // Normalise output shape for mobile consumers
        $rows = [];
        foreach ($items as $row) {
            $r = (array) $row;
            $r['image'] = $r['featured_image_path'] ?? null;
            $rows[] = $r;
        }

        return $this->paginated(
            $rows,
            (int) $total,
            $page,
            $limit,
            '/api/v2/marketplace/search'
        );
    }

    // =========================================================================
    //  POST /api/v2/marketplace/bid
    // =========================================================================
    public function bid(Request $request): JsonResponse
    {
        $input = $request->validate([
            'listing_id' => 'required|integer|min:1',
            'amount'     => 'required|numeric|min:0.01',
            'max_bid'    => 'nullable|numeric|min:0.01',
            'bidder_id'  => 'nullable|integer|min:1',
        ]);

        if (!$this->service->tablesExist()) {
            return $this->error('marketplace_unavailable', 'Marketplace tables not installed.', 503);
        }

        $userId = $this->resolveActorId($request, (int) ($input['bidder_id'] ?? 0));
        if ($userId <= 0) {
            return $this->error('bidder_required', 'bidder_id is required when calling with an unauthenticated API key.', 422);
        }

        $listingId = (int) $input['listing_id'];
        $amount    = (float) $input['amount'];
        $maxBid    = isset($input['max_bid']) ? (float) $input['max_bid'] : null;

        $auction = DB::table('marketplace_auction')->where('listing_id', $listingId)->first();
        if (!$auction) {
            return $this->error('auction_not_found', 'No auction exists for this listing.', 404);
        }

        try {
            $result = $this->service->placeBid((int) $auction->id, $userId, $amount, $maxBid);
        } catch (Throwable $e) {
            return $this->error('bid_failed', $e->getMessage(), 500);
        }

        if (empty($result['success'])) {
            return $this->error('bid_rejected', (string) ($result['error'] ?? 'Bid rejected'), 422);
        }

        $status = $this->service->getAuctionStatus((int) $auction->id);

        return $this->success([
            'bid_id'    => $result['bid_id'] ?? null,
            'auction'   => $status,
            'listing_id' => $listingId,
        ], 201);
    }

    // =========================================================================
    //  GET /api/v2/marketplace/auction/{id}/status
    // =========================================================================
    public function auctionStatus(Request $request, int $id): JsonResponse
    {
        if ($id <= 0) {
            return $this->error('invalid_id', 'Auction id must be a positive integer.', 422);
        }

        if (!$this->service->tablesExist()) {
            return $this->error('marketplace_unavailable', 'Marketplace tables not installed.', 503);
        }

        $status = $this->service->getAuctionStatus($id);
        if (!$status) {
            return $this->error('auction_not_found', 'Auction not found.', 404);
        }

        // Expose time-to-close in both raw seconds and ISO-8601 end timestamp
        $status['time_to_close'] = (int) ($status['time_remaining'] ?? 0);
        if (!empty($status['end_time'])) {
            try {
                $status['end_time_iso'] = (new \DateTime((string) $status['end_time']))->format(\DateTimeInterface::ATOM);
            } catch (Throwable $e) {
                $status['end_time_iso'] = null;
            }
        }

        return $this->success($status);
    }

    // =========================================================================
    //  POST /api/v2/marketplace/favourite
    // =========================================================================
    public function favourite(Request $request): JsonResponse
    {
        $input = $request->validate([
            'listing_id' => 'required|integer|min:1',
            'user_id'    => 'nullable|integer|min:1',
        ]);

        if (!$this->service->tablesExist()) {
            return $this->error('marketplace_unavailable', 'Marketplace tables not installed.', 503);
        }

        $userId = $this->resolveActorId($request, (int) ($input['user_id'] ?? 0));
        if ($userId <= 0) {
            return $this->error('user_required', 'user_id is required when calling with an unauthenticated API key.', 422);
        }

        $listing = DB::table('marketplace_listing')->where('id', (int) $input['listing_id'])->first();
        if (!$listing) {
            return $this->error('listing_not_found', 'Listing not found.', 404);
        }

        try {
            $result = $this->service->toggleFavourite($userId, (int) $input['listing_id']);
        } catch (Throwable $e) {
            return $this->error('favourite_failed', $e->getMessage(), 500);
        }

        if (empty($result['success'])) {
            return $this->error('favourite_failed', (string) ($result['error'] ?? 'Could not toggle favourite'), 422);
        }

        return $this->success([
            'listing_id' => (int) $input['listing_id'],
            'favourited' => (bool) ($result['favourited'] ?? false),
            'count'      => (int) ($result['count'] ?? 0),
        ]);
    }

    // =========================================================================
    //  GET /api/v2/marketplace/currencies
    // =========================================================================
    public function currencies(Request $request): JsonResponse
    {
        $result = [];

        // Primary source: ahg_dropdown taxonomy='currency' (per CLAUDE.md dropdown rule).
        try {
            if (Schema::hasTable('ahg_dropdown')) {
                $rows = DB::table('ahg_dropdown')
                    ->where('taxonomy', 'currency')
                    ->where('is_active', 1)
                    ->orderBy('sort_order')
                    ->get();
                foreach ($rows as $row) {
                    $meta = [];
                    if (!empty($row->metadata)) {
                        $decoded = json_decode($row->metadata, true);
                        if (is_array($decoded)) {
                            $meta = $decoded;
                        }
                    }
                    $result[] = [
                        'code'           => $row->code,
                        'name'           => $row->label ?? $row->code,
                        'symbol'         => $meta['symbol']           ?? ($row->code ?? ''),
                        'rate'           => isset($meta['rate'])      ? (float) $meta['rate']         : 1.0,
                        'decimal_places' => isset($meta['decimal'])   ? (int) $meta['decimal']        : 2,
                        'symbol_position' => $meta['position']        ?? 'before',
                        'is_active'      => true,
                    ];
                }
            }
        } catch (Throwable $e) {
            // fall through to fallback
        }

        // Fallback: marketplace_currency table if present and ahg_dropdown empty.
        if (empty($result)) {
            try {
                if (Schema::hasTable('marketplace_currency')) {
                    $rows = DB::table('marketplace_currency')
                        ->where('is_active', 1)
                        ->orderBy('code')
                        ->get();
                    foreach ($rows as $row) {
                        $result[] = [
                            'code'           => $row->code,
                            'name'           => $row->name ?? $row->code,
                            'symbol'         => $row->symbol ?? '',
                            'rate'           => (float) ($row->exchange_rate_to_zar ?? 1.0),
                            'decimal_places' => (int) ($row->decimal_places ?? 2),
                            'symbol_position' => $row->symbol_position ?? 'before',
                            'is_active'      => (bool) $row->is_active,
                        ];
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        // Last-ditch fallback so the mobile client always gets something.
        if (empty($result)) {
            $result = [
                ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R',  'rate' => 1.0,  'decimal_places' => 2, 'symbol_position' => 'before', 'is_active' => true],
                ['code' => 'USD', 'name' => 'US Dollar',          'symbol' => '$',  'rate' => 0.05, 'decimal_places' => 2, 'symbol_position' => 'before', 'is_active' => true],
                ['code' => 'EUR', 'name' => 'Euro',                'symbol' => 'EUR','rate' => 0.05, 'decimal_places' => 2, 'symbol_position' => 'before', 'is_active' => true],
                ['code' => 'GBP', 'name' => 'British Pound',       'symbol' => 'GBP','rate' => 0.04, 'decimal_places' => 2, 'symbol_position' => 'before', 'is_active' => true],
            ];
        }

        return $this->success($result, 200, [
            'count' => count($result),
        ]);
    }

    // =========================================================================
    //  GET /api/v2/marketplace/categories
    // =========================================================================
    public function categories(Request $request): JsonResponse
    {
        $request->validate([
            'sector' => 'nullable|string|max:50',
        ]);

        $sector = $request->get('sector');
        $result = [];

        // Primary: ahg_dropdown taxonomy='marketplace_category'.
        try {
            if (Schema::hasTable('ahg_dropdown')) {
                $q = DB::table('ahg_dropdown')
                    ->where('taxonomy', 'marketplace_category')
                    ->where('is_active', 1);
                $rows = $q->orderBy('sort_order')->get();
                foreach ($rows as $row) {
                    $meta = [];
                    if (!empty($row->metadata)) {
                        $decoded = json_decode($row->metadata, true);
                        if (is_array($decoded)) {
                            $meta = $decoded;
                        }
                    }
                    if ($sector && !empty($meta['sector']) && $meta['sector'] !== $sector) {
                        continue;
                    }
                    $result[] = [
                        'id'         => (int) $row->id,
                        'code'       => $row->code,
                        'name'       => $row->label ?? $row->code,
                        'slug'       => $meta['slug']        ?? $row->code,
                        'sector'     => $meta['sector']      ?? null,
                        'description' => $meta['description'] ?? null,
                        'sort_order' => (int) ($row->sort_order ?? 0),
                        'is_active'  => true,
                    ];
                }
            }
        } catch (Throwable $e) {
            // fall through
        }

        // Fallback: marketplace_category table.
        if (empty($result)) {
            try {
                if (Schema::hasTable('marketplace_category')) {
                    $q = DB::table('marketplace_category')->where('is_active', 1);
                    if ($sector) {
                        $q->where('sector', $sector);
                    }
                    $rows = $q->orderBy('sort_order')->orderBy('name')->get();
                    foreach ($rows as $row) {
                        $result[] = [
                            'id'         => (int) $row->id,
                            'code'       => $row->slug ?? $row->name,
                            'name'       => $row->name,
                            'slug'       => $row->slug,
                            'sector'     => $row->sector,
                            'description' => $row->description ?? null,
                            'sort_order' => (int) ($row->sort_order ?? 0),
                            'is_active'  => (bool) $row->is_active,
                        ];
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        return $this->success($result, 200, [
            'sector' => $sector,
            'count'  => count($result),
        ]);
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    /**
     * Resolve the acting user id. Order of preference:
     *  1. The session-authenticated user (web admin calling the API)
     *  2. The explicit body parameter (mobile app on behalf of a known user)
     *  3. The api_user_id attached to the API key (set by api.auth middleware)
     */
    private function resolveActorId(Request $request, int $bodyValue): int
    {
        if ($request->user()) {
            return (int) $request->user()->id;
        }
        if ($bodyValue > 0) {
            return $bodyValue;
        }
        $attr = $request->attributes->get('api_user_id');

        return $attr ? (int) $attr : 0;
    }
}
