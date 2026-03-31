<?php

/**
 * MarketplaceService - Service for Heratio
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
use Illuminate\Support\Facades\Schema;

class MarketplaceService
{
    // =========================================================================
    // Table Names
    // =========================================================================

    protected string $listingTable       = 'marketplace_listing';
    protected string $imageTable         = 'marketplace_listing_image';
    protected string $sellerTable        = 'marketplace_seller';
    protected string $auctionTable       = 'marketplace_auction';
    protected string $bidTable           = 'marketplace_bid';
    protected string $offerTable         = 'marketplace_offer';
    protected string $transactionTable   = 'marketplace_transaction';
    protected string $payoutTable        = 'marketplace_payout';
    protected string $reviewTable        = 'marketplace_review';
    protected string $collectionTable    = 'marketplace_collection';
    protected string $collectionItemTable = 'marketplace_collection_item';
    protected string $enquiryTable       = 'marketplace_enquiry';
    protected string $followTable        = 'marketplace_follow';
    protected string $settingsTable      = 'marketplace_settings';
    protected string $currencyTable      = 'marketplace_currency';
    protected string $categoryTable      = 'marketplace_category';

    // =========================================================================
    // Table Availability Check
    // =========================================================================

    /**
     * Verify that all required marketplace tables exist.
     */
    public function tablesExist(): bool
    {
        return Schema::hasTable($this->listingTable)
            && Schema::hasTable($this->sellerTable)
            && Schema::hasTable($this->settingsTable);
    }

    // =========================================================================
    //  LISTINGS
    // =========================================================================

    /**
     * Browse listings with filters, pagination, and sorting.
     */
    public function getListings(array $filters = [], int $page = 1, int $limit = 24): array
    {
        $offset = ($page - 1) * $limit;
        $sort = $filters['sort'] ?? 'newest';

        $query = DB::table($this->listingTable . ' as l')
            ->leftJoin($this->sellerTable . ' as s', 'l.seller_id', '=', 's.id')
            ->select(
                'l.*',
                's.display_name as seller_name',
                's.slug as seller_slug',
                's.average_rating as seller_rating',
                's.verification_status as seller_verified'
            );

        // Only active listings for public browse unless overridden
        if (!isset($filters['include_all_statuses'])) {
            $query->where('l.status', 'active');
        }

        $this->applyListingFilters($query, $filters);

        $total = $query->count();

        switch ($sort) {
            case 'price_asc':
                $query->orderBy('l.price', 'ASC');
                break;
            case 'price_desc':
                $query->orderBy('l.price', 'DESC');
                break;
            case 'popular':
                $query->orderBy('l.view_count', 'DESC');
                break;
            case 'ending_soon':
                $query->orderBy('l.expires_at', 'ASC');
                break;
            case 'oldest':
                $query->orderBy('l.listed_at', 'ASC');
                break;
            case 'newest':
            default:
                $query->orderBy('l.listed_at', 'DESC');
                break;
        }

        $items = $query->limit($limit)->offset($offset)->get()->all();

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => $total > 0 ? (int) ceil($total / $limit) : 1,
        ];
    }

    /**
     * Get a single listing by ID, incrementing view count.
     */
    public function getListing(int $id): ?object
    {
        $listing = DB::table($this->listingTable)->where('id', $id)->first();
        if ($listing) {
            DB::table($this->listingTable)->where('id', $id)->increment('view_count');
        }

        return $listing;
    }

    /**
     * Get a single listing by slug, incrementing view count.
     */
    public function getListingBySlug(string $slug): ?object
    {
        $listing = DB::table($this->listingTable)->where('slug', $slug)->first();
        if ($listing) {
            DB::table($this->listingTable)->where('id', $listing->id)->increment('view_count');
        }

        return $listing;
    }

    /**
     * Create a new listing.
     */
    public function createListing(array $data): array
    {
        $data['listing_number'] = $this->generateListingNumber();
        $data['slug']           = $this->generateSlug($data['title'] ?? 'listing', $this->listingTable);
        $data['status']         = 'draft';
        $data['created_at']     = now();
        $data['updated_at']     = now();

        $id = DB::table($this->listingTable)->insertGetId($data);

        return ['success' => true, 'id' => $id, 'listing_number' => $data['listing_number']];
    }

    /**
     * Update an existing listing.
     */
    public function updateListing(int $id, array $data): array
    {
        $listing = DB::table($this->listingTable)->where('id', $id)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        if (isset($data['title']) && $data['title'] !== $listing->title) {
            $data['slug'] = $this->generateSlug($data['title'], $this->listingTable);
        }

        $data['updated_at'] = now();
        DB::table($this->listingTable)->where('id', $id)->update($data);

        return ['success' => true];
    }

    /**
     * Delete a listing and its images.
     */
    public function deleteListing(int $id): array
    {
        $listing = DB::table($this->listingTable)->where('id', $id)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        DB::table($this->imageTable)->where('listing_id', $id)->delete();
        DB::table($this->listingTable)->where('id', $id)->delete();

        return ['success' => true];
    }

    /**
     * Publish a listing (moves to pending_review or active depending on moderation setting).
     */
    public function publishListing(int $id): array
    {
        $listing = DB::table($this->listingTable)->where('id', $id)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        if (!in_array($listing->status, ['draft', 'expired'])) {
            return ['success' => false, 'error' => 'Listing cannot be published from status: ' . $listing->status];
        }

        $moderationEnabled = $this->getSetting('listing_moderation_enabled', true);
        $durationDays = (int) $this->getSetting('listing_duration_days', 90);
        $now = now();

        $updateData = [
            'status'     => $moderationEnabled ? 'pending_review' : 'active',
            'listed_at'  => $moderationEnabled ? null : $now,
            'expires_at' => $moderationEnabled ? null : $now->copy()->addDays($durationDays),
            'updated_at' => $now,
        ];

        DB::table($this->listingTable)->where('id', $id)->update($updateData);

        return ['success' => true, 'status' => $updateData['status']];
    }

    /**
     * Approve a pending listing.
     */
    public function approveListing(int $id): array
    {
        $listing = DB::table($this->listingTable)->where('id', $id)->first();
        if (!$listing || $listing->status !== 'pending_review') {
            return ['success' => false, 'error' => 'Listing is not pending review'];
        }

        $durationDays = (int) $this->getSetting('listing_duration_days', 90);
        $now = now();

        DB::table($this->listingTable)->where('id', $id)->update([
            'status'     => 'active',
            'listed_at'  => $now,
            'expires_at' => $now->copy()->addDays($durationDays),
            'updated_at' => $now,
        ]);

        return ['success' => true];
    }

    /**
     * Reject a pending listing (back to draft).
     */
    public function rejectListing(int $id): array
    {
        $listing = DB::table($this->listingTable)->where('id', $id)->first();
        if (!$listing || $listing->status !== 'pending_review') {
            return ['success' => false, 'error' => 'Listing is not pending review'];
        }

        DB::table($this->listingTable)->where('id', $id)->update([
            'status'     => 'draft',
            'updated_at' => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Withdraw an active or pending listing.
     */
    public function withdrawListing(int $id): array
    {
        $listing = DB::table($this->listingTable)->where('id', $id)->first();
        if (!$listing || !in_array($listing->status, ['active', 'pending_review'])) {
            return ['success' => false, 'error' => 'Listing cannot be withdrawn'];
        }

        DB::table($this->listingTable)->where('id', $id)->update([
            'status'     => 'withdrawn',
            'updated_at' => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Mark a listing as sold.
     */
    public function markListingSold(int $id): void
    {
        DB::table($this->listingTable)->where('id', $id)->update([
            'status'     => 'sold',
            'sold_at'    => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get images for a listing.
     */
    public function getListingImages(int $listingId): array
    {
        return DB::table($this->imageTable)
            ->where('listing_id', $listingId)
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    /**
     * Add an image to a listing. First image automatically becomes primary.
     */
    public function addListingImage(int $listingId, array $data): int
    {
        $data['listing_id'] = $listingId;
        $data['created_at'] = now();

        $imageCount = DB::table($this->imageTable)->where('listing_id', $listingId)->count();
        if ($imageCount === 0) {
            $data['is_primary'] = 1;
        }

        return DB::table($this->imageTable)->insertGetId($data);
    }

    /**
     * Set a specific image as the primary image for a listing.
     */
    public function setPrimaryImage(int $listingId, int $imageId): void
    {
        DB::table($this->imageTable)->where('listing_id', $listingId)->update(['is_primary' => 0]);
        DB::table($this->imageTable)->where('id', $imageId)->update(['is_primary' => 1]);

        $image = DB::table($this->imageTable)->where('id', $imageId)->first();
        if ($image) {
            DB::table($this->listingTable)->where('id', $listingId)->update([
                'featured_image_path' => $image->file_path,
                'updated_at'         => now(),
            ]);
        }
    }

    /**
     * Delete a listing image.
     */
    public function deleteListingImage(int $imageId): bool
    {
        return DB::table($this->imageTable)->where('id', $imageId)->delete() > 0;
    }

    /**
     * Get featured listings (from featured sellers or high view count).
     */
    public function getFeaturedListings(int $limit = 12): array
    {
        return DB::table($this->listingTable . ' as l')
            ->leftJoin($this->sellerTable . ' as s', 'l.seller_id', '=', 's.id')
            ->select('l.*', 's.display_name as seller_name', 's.slug as seller_slug')
            ->where('l.status', 'active')
            ->where(function ($q) {
                $q->where('s.is_featured', 1)
                  ->orWhere('l.view_count', '>', 50);
            })
            ->orderByRaw('RAND()')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Get facet counts for active listings (sectors, types, conditions).
     */
    public function getFacetCounts(array $filters = []): array
    {
        $query = DB::table($this->listingTable)->where('status', 'active');

        if (!empty($filters['sector'])) {
            $query->where('sector', $filters['sector']);
        }

        $sectors    = (clone $query)->selectRaw("sector, COUNT(*) as cnt")->groupBy('sector')->pluck('cnt', 'sector')->all();
        $types      = (clone $query)->selectRaw("listing_type, COUNT(*) as cnt")->groupBy('listing_type')->pluck('cnt', 'listing_type')->all();
        $conditions = (clone $query)->whereNotNull('condition_rating')->selectRaw("condition_rating, COUNT(*) as cnt")->groupBy('condition_rating')->pluck('cnt', 'condition_rating')->all();

        return [
            'sectors'       => $sectors,
            'listing_types' => $types,
            'conditions'    => $conditions,
        ];
    }

    /**
     * Process expired listings (active past expires_at).
     */
    public function processExpiredListings(): int
    {
        $expired = DB::table($this->listingTable)
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $listing) {
            DB::table($this->listingTable)->where('id', $listing->id)->update([
                'status'     => 'expired',
                'updated_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    // =========================================================================
    //  SELLERS
    // =========================================================================

    /**
     * Browse sellers with filters and pagination.
     */
    public function getSellers(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $query = DB::table($this->sellerTable)->where('is_active', 1);

        if (!empty($filters['seller_type'])) {
            $query->where('seller_type', $filters['seller_type']);
        }
        if (!empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }
        if (!empty($filters['sector'])) {
            $query->whereRaw("JSON_CONTAINS(sectors, ?)", ['"' . $filters['sector'] . '"']);
        }
        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }
        if (!empty($filters['is_featured'])) {
            $query->where('is_featured', 1);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('display_name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('bio', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        $total = $query->count();
        $items = $query->orderBy('is_featured', 'DESC')
                       ->orderBy('total_sales', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get a seller by ID.
     */
    public function getSeller(int $id): ?object
    {
        return DB::table($this->sellerTable)->where('id', $id)->first();
    }

    /**
     * Get a seller by slug.
     */
    public function getSellerBySlug(string $slug): ?object
    {
        return DB::table($this->sellerTable)->where('slug', $slug)->first();
    }

    /**
     * Get a seller profile by user ID (created_by).
     */
    public function getSellerByUserId(int $userId): ?object
    {
        return DB::table($this->sellerTable)
            ->where('created_by', $userId)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Get a seller by linked actor ID.
     */
    public function getSellerByActorId(int $actorId): ?object
    {
        return DB::table($this->sellerTable)
            ->where('actor_id', $actorId)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Register a new seller.
     */
    public function registerSeller(int $userId, array $data): array
    {
        $registrationOpen = $this->getSetting('seller_registration_open', true);
        if (!$registrationOpen) {
            return ['success' => false, 'error' => 'Seller registration is currently closed'];
        }

        $existing = $this->getSellerByUserId($userId);
        if ($existing) {
            return ['success' => false, 'error' => 'You already have a seller profile', 'seller' => $existing];
        }

        $slug = $this->generateSlug($data['display_name'], $this->sellerTable);
        $defaultCommission = (float) $this->getSetting('default_commission_rate', 10);

        $id = DB::table($this->sellerTable)->insertGetId([
            'seller_type'              => $data['seller_type'] ?? 'artist',
            'actor_id'                 => $data['actor_id'] ?? null,
            'gallery_artist_id'        => $data['gallery_artist_id'] ?? null,
            'repository_id'            => $data['repository_id'] ?? null,
            'heritage_contributor_id'  => $data['heritage_contributor_id'] ?? null,
            'display_name'             => $data['display_name'],
            'slug'                     => $slug,
            'bio'                      => $data['bio'] ?? null,
            'country'                  => $data['country'] ?? null,
            'city'                     => $data['city'] ?? null,
            'website'                  => $data['website'] ?? null,
            'instagram'                => $data['instagram'] ?? null,
            'email'                    => $data['email'] ?? null,
            'phone'                    => $data['phone'] ?? null,
            'commission_rate'          => $defaultCommission,
            'payout_method'            => $data['payout_method'] ?? 'bank_transfer',
            'payout_details'           => isset($data['payout_details']) ? json_encode($data['payout_details']) : null,
            'payout_currency'          => $data['payout_currency'] ?? 'ZAR',
            'sectors'                  => isset($data['sectors']) ? json_encode($data['sectors']) : null,
            'verification_status'      => 'unverified',
            'trust_level'              => 'new',
            'is_active'                => 1,
            'terms_accepted_at'        => now(),
            'created_by'               => $userId,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        return ['success' => true, 'id' => $id, 'slug' => $slug];
    }

    /**
     * Update a seller profile (only allowed fields).
     */
    public function updateSellerProfile(int $id, array $data): array
    {
        $seller = DB::table($this->sellerTable)->where('id', $id)->first();
        if (!$seller) {
            return ['success' => false, 'error' => 'Seller not found'];
        }

        $updateData = [];
        $allowedFields = [
            'display_name', 'bio', 'country', 'city', 'website', 'instagram',
            'email', 'phone', 'payout_method', 'payout_currency', 'avatar_path',
            'banner_path', 'seller_type',
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['payout_details'])) {
            $updateData['payout_details'] = json_encode($data['payout_details']);
        }
        if (isset($data['sectors'])) {
            $updateData['sectors'] = json_encode($data['sectors']);
        }

        if (isset($data['display_name']) && $data['display_name'] !== $seller->display_name) {
            $updateData['slug'] = $this->generateSlug($data['display_name'], $this->sellerTable);
        }

        $updateData['updated_at'] = now();
        DB::table($this->sellerTable)->where('id', $id)->update($updateData);

        return ['success' => true];
    }

    /**
     * Mark a seller as verified.
     */
    public function verifySeller(int $id): array
    {
        DB::table($this->sellerTable)->where('id', $id)->update([
            'verification_status' => 'verified',
            'trust_level'         => 'active',
            'updated_at'          => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Suspend a seller.
     */
    public function suspendSeller(int $id): array
    {
        DB::table($this->sellerTable)->where('id', $id)->update([
            'verification_status' => 'suspended',
            'is_active'           => 0,
            'updated_at'          => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Get all listings for a specific seller.
     */
    public function getSellerListings(int $sellerId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->listingTable)->where('seller_id', $sellerId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $total = $query->count();
        $items = $query->orderBy('created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get seller dashboard statistics.
     */
    public function getSellerStats(int $sellerId): array
    {
        $listings = $this->getSellerListings($sellerId);
        $revenue  = $this->getRevenueStats($sellerId);

        $pendingPayout = $this->getSellerPendingPayoutAmount($sellerId);
        $followers     = DB::table($this->followTable)->where('seller_id', $sellerId)->count();

        $activeListings = 0;
        $draftListings  = 0;
        foreach ($listings['items'] as $l) {
            if ($l->status === 'active') {
                $activeListings++;
            }
            if ($l->status === 'draft') {
                $draftListings++;
            }
        }

        return [
            'total_listings'  => $listings['total'],
            'active_listings' => $activeListings,
            'draft_listings'  => $draftListings,
            'total_sales'     => $revenue['total_sales'],
            'total_revenue'   => $revenue['total_seller_amount'],
            'pending_payout'  => $pendingPayout,
            'followers'       => $followers,
        ];
    }

    // =========================================================================
    //  AUCTIONS
    // =========================================================================

    /**
     * Get active auctions with listing details.
     */
    public function getActiveAuctions(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        $now = now();

        $query = DB::table($this->auctionTable . ' as a')
            ->join($this->listingTable . ' as l', 'a.listing_id', '=', 'l.id')
            ->leftJoin($this->sellerTable . ' as s', 'l.seller_id', '=', 's.id')
            ->select(
                'a.*',
                'l.title', 'l.slug', 'l.featured_image_path', 'l.sector', 'l.artist_name',
                's.display_name as seller_name', 's.slug as seller_slug'
            )
            ->where('a.status', 'active')
            ->where('l.status', 'active')
            ->where('a.end_time', '>', $now);

        $total = $query->count();
        $items = $query->orderBy('a.end_time', 'ASC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get auction details for a listing.
     */
    public function getAuction(int $listingId): ?object
    {
        return DB::table($this->auctionTable)->where('listing_id', $listingId)->first();
    }

    /**
     * Get auction by its own ID.
     */
    public function getAuctionById(int $id): ?object
    {
        return DB::table($this->auctionTable)->where('id', $id)->first();
    }

    /**
     * Create an auction for a listing.
     */
    public function createAuction(int $listingId, array $data): array
    {
        $listing = DB::table($this->listingTable)->where('id', $listingId)->first();
        if (!$listing || $listing->listing_type !== 'auction') {
            return ['success' => false, 'error' => 'Listing is not an auction type'];
        }

        $existing = DB::table($this->auctionTable)->where('listing_id', $listingId)->first();
        if ($existing) {
            return ['success' => false, 'error' => 'Auction already exists for this listing'];
        }

        $id = DB::table($this->auctionTable)->insertGetId([
            'listing_id'           => $listingId,
            'auction_type'         => $data['auction_type'] ?? 'english',
            'status'               => 'upcoming',
            'starting_bid'         => $data['starting_bid'] ?? $listing->starting_bid ?? 1.00,
            'reserve_price'        => $data['reserve_price'] ?? $listing->reserve_price ?? null,
            'bid_increment'        => $data['bid_increment'] ?? 1.00,
            'buy_now_price'        => $data['buy_now_price'] ?? $listing->buy_now_price ?? null,
            'start_time'           => $data['start_time'],
            'end_time'             => $data['end_time'],
            'auto_extend_minutes'  => (int) $this->getSetting('auction_auto_extend_minutes', 5),
            'max_extensions'       => (int) $this->getSetting('auction_max_extensions', 10),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Place a bid on an auction.
     */
    public function placeBid(int $auctionId, int $userId, float $amount, ?float $maxBid = null): array
    {
        $auction = DB::table($this->auctionTable)->where('id', $auctionId)->first();
        if (!$auction) {
            return ['success' => false, 'error' => 'Auction not found'];
        }

        if ($auction->status !== 'active') {
            return ['success' => false, 'error' => 'Auction is not active'];
        }

        if (now()->gt($auction->end_time)) {
            return ['success' => false, 'error' => 'Auction has ended'];
        }

        // Minimum bid check
        $minBid = $auction->current_bid
            ? $auction->current_bid + $auction->bid_increment
            : $auction->starting_bid;

        if ($amount < $minBid) {
            return ['success' => false, 'error' => 'Minimum bid is ' . number_format($minBid, 2)];
        }

        // Seller cannot bid on own item
        $listing = DB::table($this->listingTable)->where('id', $auction->listing_id)->first();
        if ($listing) {
            $seller = DB::table($this->sellerTable)->where('id', $listing->seller_id)->first();
            if ($seller && $seller->created_by == $userId) {
                return ['success' => false, 'error' => 'You cannot bid on your own listing'];
            }
        }

        // Clear previous winning flags
        DB::table($this->bidTable)->where('auction_id', $auctionId)->update(['is_winning' => 0]);

        // Place the bid
        $bidId = DB::table($this->bidTable)->insertGetId([
            'auction_id'  => $auctionId,
            'user_id'     => $userId,
            'bid_amount'  => $amount,
            'max_bid'     => $maxBid,
            'is_auto_bid' => false,
            'is_winning'  => true,
            'ip_address'  => request()->ip(),
            'user_agent'  => substr(request()->userAgent() ?? '', 0, 255),
            'created_at'  => now(),
        ]);

        // Update auction current state
        DB::table($this->auctionTable)->where('id', $auctionId)->update([
            'current_bid'       => $amount,
            'current_bidder_id' => $userId,
            'bid_count'         => $auction->bid_count + 1,
            'updated_at'        => now(),
        ]);

        // Anti-sniping: extend if bid placed in last N minutes
        $endTime      = strtotime($auction->end_time);
        $timeLeft     = $endTime - time();
        $extendSeconds = $auction->auto_extend_minutes * 60;

        if ($timeLeft < $extendSeconds && $auction->extension_count < $auction->max_extensions) {
            $newEndTime = date('Y-m-d H:i:s', time() + $extendSeconds);
            DB::table($this->auctionTable)->where('id', $auctionId)->update([
                'end_time'        => $newEndTime,
                'extension_count' => $auction->extension_count + 1,
                'updated_at'      => now(),
            ]);
        }

        // Process proxy (auto) bids from other users
        $this->processProxyBids($auctionId, $userId, $amount);

        return ['success' => true, 'bid_id' => $bidId];
    }

    /**
     * Buy Now for an auction listing.
     */
    public function buyNow(int $auctionId, int $userId): array
    {
        $auction = DB::table($this->auctionTable)->where('id', $auctionId)->first();
        if (!$auction || $auction->status !== 'active') {
            return ['success' => false, 'error' => 'Auction is not active'];
        }

        if (!$auction->buy_now_price) {
            return ['success' => false, 'error' => 'Buy Now is not available for this auction'];
        }

        DB::table($this->auctionTable)->where('id', $auctionId)->update([
            'status'      => 'ended',
            'winner_id'   => $userId,
            'winning_bid' => $auction->buy_now_price,
            'updated_at'  => now(),
        ]);

        return ['success' => true, 'price' => $auction->buy_now_price];
    }

    /**
     * End an auction, determining winner based on reserve price.
     */
    public function endAuction(int $auctionId): array
    {
        $auction = DB::table($this->auctionTable)->where('id', $auctionId)->first();
        if (!$auction) {
            return ['success' => false, 'error' => 'Auction not found'];
        }

        $highestBid = $this->getHighestBid($auctionId);

        $updateData = ['status' => 'ended', 'updated_at' => now()];

        if ($highestBid) {
            $reserveMet = !$auction->reserve_price || $highestBid->bid_amount >= $auction->reserve_price;

            if ($reserveMet) {
                $updateData['winner_id']   = $highestBid->user_id;
                $updateData['winning_bid'] = $highestBid->bid_amount;
            }
        }

        DB::table($this->auctionTable)->where('id', $auctionId)->update($updateData);

        return [
            'success'     => true,
            'has_winner'  => isset($updateData['winner_id']),
            'winner_id'   => $updateData['winner_id'] ?? null,
            'winning_bid' => $updateData['winning_bid'] ?? null,
        ];
    }

    /**
     * Get real-time auction status info.
     */
    public function getAuctionStatus(int $auctionId): ?array
    {
        $auction = DB::table($this->auctionTable)->where('id', $auctionId)->first();
        if (!$auction) {
            return null;
        }

        return [
            'id'             => $auction->id,
            'status'         => $auction->status,
            'current_bid'    => $auction->current_bid,
            'bid_count'      => $auction->bid_count,
            'end_time'       => $auction->end_time,
            'reserve_met'    => $auction->reserve_price ? ($auction->current_bid >= $auction->reserve_price) : true,
            'buy_now_price'  => $auction->buy_now_price,
            'time_remaining' => max(0, strtotime($auction->end_time) - time()),
        ];
    }

    /**
     * Get bid history for an auction.
     */
    public function getBids(int $auctionId, int $limit = 50): array
    {
        return DB::table($this->bidTable)
            ->where('auction_id', $auctionId)
            ->orderBy('bid_amount', 'DESC')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Get the highest bid for an auction.
     */
    public function getHighestBid(int $auctionId): ?object
    {
        return DB::table($this->bidTable)
            ->where('auction_id', $auctionId)
            ->orderBy('bid_amount', 'DESC')
            ->first();
    }

    /**
     * Get all bids placed by a user across all auctions.
     */
    public function getUserBids(int $userId, int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->bidTable . ' as b')
            ->join($this->auctionTable . ' as a', 'b.auction_id', '=', 'a.id')
            ->join($this->listingTable . ' as l', 'a.listing_id', '=', 'l.id')
            ->select('b.*', 'a.status as auction_status', 'a.end_time', 'a.current_bid', 'l.title', 'l.slug', 'l.featured_image_path')
            ->where('b.user_id', $userId);

        $total = $query->count();
        $items = $query->orderBy('b.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get auctions ending soon (within N minutes).
     */
    public function getAuctionsEndingSoon(int $minutes = 60, int $limit = 10): array
    {
        $now    = now();
        $cutoff = now()->addMinutes($minutes);

        return DB::table($this->auctionTable . ' as a')
            ->join($this->listingTable . ' as l', 'a.listing_id', '=', 'l.id')
            ->select('a.*', 'l.title', 'l.slug', 'l.featured_image_path')
            ->where('a.status', 'active')
            ->where('a.end_time', '>', $now)
            ->where('a.end_time', '<=', $cutoff)
            ->orderBy('a.end_time', 'ASC')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Process auction lifecycle: start upcoming auctions, end expired ones.
     */
    public function processAuctionLifecycle(): array
    {
        $started = 0;
        $ended   = 0;
        $now     = now();

        // Start upcoming auctions whose start_time has passed
        $toStart = DB::table($this->auctionTable)
            ->where('status', 'upcoming')
            ->where('start_time', '<=', $now)
            ->get();

        foreach ($toStart as $auction) {
            DB::table($this->auctionTable)->where('id', $auction->id)->update(['status' => 'active', 'updated_at' => $now]);
            DB::table($this->listingTable)->where('id', $auction->listing_id)->update(['status' => 'active', 'updated_at' => $now]);
            $started++;
        }

        // End auctions whose end_time has passed
        $toEnd = DB::table($this->auctionTable)
            ->where('status', 'active')
            ->where('end_time', '<=', $now)
            ->get();

        foreach ($toEnd as $auction) {
            $this->endAuction($auction->id);
            $ended++;
        }

        return ['started' => $started, 'ended' => $ended];
    }

    // =========================================================================
    //  OFFERS
    // =========================================================================

    /**
     * Submit an offer on a listing.
     */
    public function submitOffer(int $listingId, int $userId, array $data): array
    {
        $listing = DB::table($this->listingTable)->where('id', $listingId)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        if ($listing->status !== 'active') {
            return ['success' => false, 'error' => 'Listing is not available'];
        }

        if ($listing->listing_type === 'auction') {
            return ['success' => false, 'error' => 'Cannot make offer on auction listing'];
        }

        $amount = (float) ($data['amount'] ?? 0);

        // Check minimum offer
        if ($listing->minimum_offer && $amount < $listing->minimum_offer) {
            return ['success' => false, 'error' => 'Offer must be at least ' . number_format($listing->minimum_offer, 2)];
        }

        // Check for existing pending offer
        $hasPending = DB::table($this->offerTable)
            ->where('listing_id', $listingId)
            ->where('buyer_id', $userId)
            ->whereIn('status', ['pending', 'countered'])
            ->exists();

        if ($hasPending) {
            return ['success' => false, 'error' => 'You already have a pending offer on this listing'];
        }

        $expiryDays = (int) $this->getSetting('offer_expiry_days', 7);

        $id = DB::table($this->offerTable)->insertGetId([
            'listing_id'   => $listingId,
            'buyer_id'     => $userId,
            'status'       => 'pending',
            'offer_amount' => $amount,
            'currency'     => $listing->currency,
            'message'      => $data['message'] ?? null,
            'expires_at'   => now()->addDays($expiryDays),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Increment enquiry count on listing
        DB::table($this->listingTable)->where('id', $listingId)->update([
            'enquiry_count' => ($listing->enquiry_count ?? 0) + 1,
            'updated_at'    => now(),
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Get all offers for a listing.
     */
    public function getOffers(int $listingId, ?string $status = null): array
    {
        $query = DB::table($this->offerTable)->where('listing_id', $listingId);
        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'DESC')->get()->all();
    }

    /**
     * Get all offers placed by a user.
     */
    public function getUserOffers(int $userId, int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->offerTable . ' as o')
            ->join($this->listingTable . ' as l', 'o.listing_id', '=', 'l.id')
            ->select('o.*', 'l.title', 'l.slug', 'l.featured_image_path', 'l.price as listing_price')
            ->where('o.buyer_id', $userId);

        $total = $query->count();
        $items = $query->orderBy('o.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get offers received by a seller.
     */
    public function getSellerOffers(int $sellerId, ?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->offerTable . ' as o')
            ->join($this->listingTable . ' as l', 'o.listing_id', '=', 'l.id')
            ->select('o.*', 'l.title', 'l.slug', 'l.featured_image_path', 'l.price as listing_price', 'l.seller_id')
            ->where('l.seller_id', $sellerId);

        if ($status) {
            $query->where('o.status', $status);
        }

        $total = $query->count();
        $items = $query->orderBy('o.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get offer with full listing and seller details.
     */
    public function getOfferWithDetails(int $id): ?object
    {
        return DB::table($this->offerTable . ' as o')
            ->join($this->listingTable . ' as l', 'o.listing_id', '=', 'l.id')
            ->leftJoin($this->sellerTable . ' as s', 'l.seller_id', '=', 's.id')
            ->select('o.*', 'l.title', 'l.slug', 'l.price as listing_price', 'l.seller_id', 's.display_name as seller_name')
            ->where('o.id', $id)
            ->first();
    }

    /**
     * Respond to an offer: accept, reject, or counter.
     */
    public function respondToOffer(int $offerId, string $status, ?array $response = null): array
    {
        $offer = DB::table($this->offerTable)->where('id', $offerId)->first();
        if (!$offer) {
            return ['success' => false, 'error' => 'Offer not found'];
        }

        switch ($status) {
            case 'accepted':
                return $this->acceptOffer($offerId);

            case 'rejected':
                return $this->rejectOffer($offerId, $response['message'] ?? null);

            case 'countered':
                if (!isset($response['counter_amount'])) {
                    return ['success' => false, 'error' => 'Counter amount is required'];
                }
                return $this->counterOffer($offerId, (float) $response['counter_amount'], $response['message'] ?? null);

            default:
                return ['success' => false, 'error' => 'Invalid response status'];
        }
    }

    /**
     * Accept an offer and reserve the listing.
     */
    public function acceptOffer(int $offerId): array
    {
        $offer = DB::table($this->offerTable)->where('id', $offerId)->first();
        if (!$offer || !in_array($offer->status, ['pending', 'countered'])) {
            return ['success' => false, 'error' => 'Offer cannot be accepted'];
        }

        DB::table($this->offerTable)->where('id', $offerId)->update([
            'status'       => 'accepted',
            'responded_at' => now(),
            'updated_at'   => now(),
        ]);

        // Reserve the listing
        DB::table($this->listingTable)->where('id', $offer->listing_id)->update([
            'status'     => 'reserved',
            'updated_at' => now(),
        ]);

        return ['success' => true, 'offer' => $offer];
    }

    /**
     * Reject an offer.
     */
    public function rejectOffer(int $offerId, ?string $responseMessage = null): array
    {
        $offer = DB::table($this->offerTable)->where('id', $offerId)->first();
        if (!$offer || !in_array($offer->status, ['pending', 'countered'])) {
            return ['success' => false, 'error' => 'Offer cannot be rejected'];
        }

        DB::table($this->offerTable)->where('id', $offerId)->update([
            'status'          => 'rejected',
            'seller_response' => $responseMessage,
            'responded_at'    => now(),
            'updated_at'      => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Counter an offer with a new amount.
     */
    public function counterOffer(int $offerId, float $counterAmount, ?string $responseMessage = null): array
    {
        $offer = DB::table($this->offerTable)->where('id', $offerId)->first();
        if (!$offer || $offer->status !== 'pending') {
            return ['success' => false, 'error' => 'Offer cannot be countered'];
        }

        $expiryDays = (int) $this->getSetting('offer_expiry_days', 7);

        DB::table($this->offerTable)->where('id', $offerId)->update([
            'status'          => 'countered',
            'counter_amount'  => $counterAmount,
            'seller_response' => $responseMessage,
            'expires_at'      => now()->addDays($expiryDays),
            'responded_at'    => now(),
            'updated_at'      => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Withdraw an offer (buyer-initiated).
     */
    public function withdrawOffer(int $offerId, int $buyerId): array
    {
        $offer = DB::table($this->offerTable)->where('id', $offerId)->first();
        if (!$offer || $offer->buyer_id != $buyerId) {
            return ['success' => false, 'error' => 'Offer not found'];
        }

        if (!in_array($offer->status, ['pending', 'countered'])) {
            return ['success' => false, 'error' => 'Offer cannot be withdrawn'];
        }

        DB::table($this->offerTable)->where('id', $offerId)->update([
            'status'       => 'withdrawn',
            'responded_at' => now(),
            'updated_at'   => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Accept a counter-offer (buyer-initiated).
     */
    public function acceptCounter(int $offerId, int $buyerId): array
    {
        $offer = DB::table($this->offerTable)->where('id', $offerId)->first();
        if (!$offer || $offer->buyer_id != $buyerId || $offer->status !== 'countered') {
            return ['success' => false, 'error' => 'Counter-offer cannot be accepted'];
        }

        DB::table($this->offerTable)->where('id', $offerId)->update([
            'status'       => 'accepted',
            'offer_amount' => $offer->counter_amount,
            'responded_at' => now(),
            'updated_at'   => now(),
        ]);

        // Reserve the listing
        DB::table($this->listingTable)->where('id', $offer->listing_id)->update([
            'status'     => 'reserved',
            'updated_at' => now(),
        ]);

        return ['success' => true, 'price' => $offer->counter_amount];
    }

    /**
     * Process expired offers.
     */
    public function processExpiredOffers(): int
    {
        $expired = DB::table($this->offerTable)
            ->whereIn('status', ['pending', 'countered'])
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $offer) {
            DB::table($this->offerTable)->where('id', $offer->id)->update([
                'status'     => 'expired',
                'updated_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    // =========================================================================
    //  TRANSACTIONS
    // =========================================================================

    /**
     * Create a transaction from any source (fixed_price, offer, auction).
     */
    public function createTransaction(array $data): array
    {
        $source = $data['source'] ?? null;

        switch ($source) {
            case 'fixed_price':
                return $this->createTransactionFromFixedPrice($data['listing_id'], $data['buyer_id']);
            case 'offer':
                return $this->createTransactionFromOffer($data['offer_id'], $data['buyer_id']);
            case 'auction':
                return $this->createTransactionFromAuction($data['auction_id']);
            default:
                return ['success' => false, 'error' => 'Invalid transaction source'];
        }
    }

    /**
     * Get a transaction with full details.
     */
    public function getTransaction(int $id): ?object
    {
        return DB::table($this->transactionTable . ' as t')
            ->join($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->leftJoin($this->sellerTable . ' as s', 't.seller_id', '=', 's.id')
            ->select(
                't.*',
                'l.title', 'l.slug', 'l.featured_image_path', 'l.description',
                's.display_name as seller_name', 's.slug as seller_slug', 's.email as seller_email'
            )
            ->where('t.id', $id)
            ->first();
    }

    /**
     * Get transaction by transaction number.
     */
    public function getTransactionByNumber(string $number): ?object
    {
        return DB::table($this->transactionTable)->where('transaction_number', $number)->first();
    }

    /**
     * Get all purchases for a user (buyer).
     */
    public function getUserPurchases(int $userId, int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->transactionTable . ' as t')
            ->join($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->leftJoin($this->sellerTable . ' as s', 't.seller_id', '=', 's.id')
            ->select('t.*', 'l.title', 'l.slug', 'l.featured_image_path', 's.display_name as seller_name')
            ->where('t.buyer_id', $userId);

        $total = $query->count();
        $items = $query->orderBy('t.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get all transactions for a seller.
     */
    public function getSellerTransactions(int $sellerId, int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->transactionTable . ' as t')
            ->join($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->select('t.*', 'l.title', 'l.slug', 'l.featured_image_path')
            ->where('t.seller_id', $sellerId);

        $total = $query->count();
        $items = $query->orderBy('t.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Mark a transaction as paid.
     */
    public function markTransactionPaid(int $txnId, string $gateway, string $gatewayTxnId, ?array $gatewayResponse = null): array
    {
        $txn = DB::table($this->transactionTable)->where('id', $txnId)->first();
        if (!$txn) {
            return ['success' => false, 'error' => 'Transaction not found'];
        }

        DB::table($this->transactionTable)->where('id', $txnId)->update([
            'payment_status'         => 'paid',
            'payment_gateway'        => $gateway,
            'payment_transaction_id' => $gatewayTxnId,
            'gateway_response'       => $gatewayResponse ? json_encode($gatewayResponse) : null,
            'paid_at'                => now(),
            'status'                 => 'paid',
            'updated_at'             => now(),
        ]);

        // Mark listing as sold
        DB::table($this->listingTable)->where('id', $txn->listing_id)->update([
            'status'     => 'sold',
            'sold_at'    => now(),
            'updated_at' => now(),
        ]);

        // Update seller stats
        DB::table($this->sellerTable)->where('id', $txn->seller_id)->increment('total_sales');
        DB::table($this->sellerTable)->where('id', $txn->seller_id)->increment('total_revenue', $txn->seller_amount);

        return ['success' => true];
    }

    /**
     * Update shipping info on a transaction.
     */
    public function updateTransactionShipping(int $txnId, array $data): array
    {
        $txn = DB::table($this->transactionTable)->where('id', $txnId)->first();
        if (!$txn) {
            return ['success' => false, 'error' => 'Transaction not found'];
        }

        $updateData = ['updated_at' => now()];
        if (isset($data['tracking_number'])) {
            $updateData['tracking_number'] = $data['tracking_number'];
        }
        if (isset($data['courier'])) {
            $updateData['courier'] = $data['courier'];
        }
        if (isset($data['shipping_status'])) {
            $updateData['shipping_status'] = $data['shipping_status'];
            if ($data['shipping_status'] === 'shipped') {
                $updateData['shipped_at'] = now();
                $updateData['status']     = 'shipping';
            } elseif ($data['shipping_status'] === 'delivered') {
                $updateData['delivered_at'] = now();
                $updateData['status']       = 'delivered';
            }
        }

        DB::table($this->transactionTable)->where('id', $txnId)->update($updateData);

        return ['success' => true];
    }

    /**
     * Confirm receipt of item (buyer-side). Creates a pending payout.
     */
    public function confirmReceipt(int $txnId, int $buyerId): array
    {
        $txn = DB::table($this->transactionTable)->where('id', $txnId)->first();
        if (!$txn || $txn->buyer_id != $buyerId) {
            return ['success' => false, 'error' => 'Transaction not found'];
        }

        $now = now();
        DB::table($this->transactionTable)->where('id', $txnId)->update([
            'buyer_confirmed_receipt' => 1,
            'receipt_confirmed_at'    => $now,
            'status'                  => 'completed',
            'completed_at'            => $now,
            'updated_at'              => $now,
        ]);

        // Auto-create pending payout
        $coolingDays = (int) $this->getSetting('payout_cooling_period_days', 5);

        DB::table($this->payoutTable)->insertGetId([
            'seller_id'      => $txn->seller_id,
            'transaction_id' => $txnId,
            'payout_number'  => $this->generatePayoutNumber(),
            'amount'         => $txn->seller_amount,
            'currency'       => $txn->currency,
            'method'         => 'bank_transfer',
            'status'         => 'pending',
            'notes'          => "Auto-created on receipt confirmation. Release after {$coolingDays}-day cooling period.",
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return ['success' => true];
    }

    /**
     * Get revenue statistics (platform-wide or per seller).
     */
    public function getRevenueStats(?int $sellerId = null): array
    {
        $query = DB::table($this->transactionTable)->where('payment_status', 'paid');
        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        return [
            'total_sales'         => (clone $query)->count(),
            'total_revenue'       => (clone $query)->sum('sale_price') ?? 0,
            'total_commission'    => (clone $query)->sum('platform_commission_amount') ?? 0,
            'total_seller_amount' => (clone $query)->sum('seller_amount') ?? 0,
        ];
    }

    /**
     * Get monthly revenue breakdown.
     */
    public function getMonthlyRevenue(?int $sellerId = null, int $months = 12): array
    {
        $query = DB::table($this->transactionTable)
            ->where('payment_status', 'paid')
            ->where('paid_at', '>=', now()->subMonths($months))
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(sale_price) as revenue, SUM(platform_commission_amount) as commission, COUNT(*) as sales")
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        return $query->get()->all();
    }

    // =========================================================================
    //  PAYOUTS
    // =========================================================================

    /**
     * Get payouts for a seller.
     */
    public function getPayouts(int $sellerId, int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->payoutTable)->where('seller_id', $sellerId);

        $total = $query->count();
        $items = $query->orderBy('created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Request a payout (creates a pending payout record).
     */
    public function requestPayout(int $sellerId, float $amount): array
    {
        $seller = DB::table($this->sellerTable)->where('id', $sellerId)->first();
        if (!$seller) {
            return ['success' => false, 'error' => 'Seller not found'];
        }

        $pending = $this->getSellerPendingPayoutAmount($sellerId);
        if ($amount > $pending) {
            return ['success' => false, 'error' => 'Requested amount exceeds available balance'];
        }

        $id = DB::table($this->payoutTable)->insertGetId([
            'seller_id'      => $sellerId,
            'payout_number'  => $this->generatePayoutNumber(),
            'amount'         => $amount,
            'currency'       => $seller->payout_currency ?? 'ZAR',
            'method'         => $seller->payout_method ?? 'bank_transfer',
            'status'         => 'pending',
            'notes'          => 'Seller-requested payout',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Process a single payout (admin action).
     */
    public function processPayout(int $payoutId, int $processedBy): array
    {
        $payout = DB::table($this->payoutTable)->where('id', $payoutId)->first();
        if (!$payout) {
            return ['success' => false, 'error' => 'Payout not found'];
        }

        if ($payout->status !== 'pending') {
            return ['success' => false, 'error' => 'Payout is not in pending status'];
        }

        // Check cooling period
        $coolingDays = (int) $this->getSetting('payout_cooling_period_days', 5);
        $createdAt   = strtotime($payout->created_at);
        $releaseDate = strtotime("+{$coolingDays} days", $createdAt);

        if (time() < $releaseDate) {
            $remainingDays = ceil(($releaseDate - time()) / 86400);
            return ['success' => false, 'error' => "Cooling period not met. {$remainingDays} day(s) remaining."];
        }

        DB::table($this->payoutTable)->where('id', $payoutId)->update([
            'status'       => 'processing',
            'processed_by' => $processedBy,
            'processed_at' => now(),
            'updated_at'   => now(),
        ]);

        return ['success' => true, 'status' => 'processing'];
    }

    /**
     * Complete a payout (admin action).
     */
    public function completePayout(int $payoutId, ?string $reference = null): array
    {
        $payout = DB::table($this->payoutTable)->where('id', $payoutId)->first();
        if (!$payout) {
            return ['success' => false, 'error' => 'Payout not found'];
        }

        if ($payout->status !== 'processing') {
            return ['success' => false, 'error' => 'Payout must be in processing status to complete'];
        }

        $updateData = [
            'status'       => 'completed',
            'completed_at' => now(),
            'updated_at'   => now(),
        ];

        if ($reference) {
            $updateData['payment_reference'] = $reference;
        }

        DB::table($this->payoutTable)->where('id', $payoutId)->update($updateData);

        return ['success' => true];
    }

    /**
     * Batch process multiple payouts.
     */
    public function processPayouts(array $payoutIds = [], int $processedBy = 0): array
    {
        // If no IDs given, process all eligible pending payouts
        if (empty($payoutIds)) {
            $pending = DB::table($this->payoutTable . ' as p')
                ->join($this->sellerTable . ' as s', 'p.seller_id', '=', 's.id')
                ->select('p.*', 's.display_name as seller_name', 's.payout_method', 's.payout_details', 's.payout_currency')
                ->where('p.status', 'pending')
                ->orderBy('p.created_at', 'ASC')
                ->limit(100)
                ->get();

            $payoutIds = $pending->pluck('id')->all();
        }

        $results = [
            'processed' => 0,
            'skipped'   => 0,
            'errors'    => [],
        ];

        foreach ($payoutIds as $payoutId) {
            $result = $this->processPayout($payoutId, $processedBy);
            if ($result['success']) {
                $results['processed']++;
            } else {
                $results['skipped']++;
                $results['errors'][] = [
                    'payout_id' => $payoutId,
                    'error'     => $result['error'],
                ];
            }
        }

        return $results;
    }

    /**
     * Get payout statistics (platform-wide or per seller).
     */
    public function getPayoutStats(?int $sellerId = null): array
    {
        $query = DB::table($this->payoutTable);
        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        $items = $query->get();

        $stats = [
            'total_payouts'     => $items->count(),
            'total_amount'      => 0,
            'pending_count'     => 0,
            'pending_amount'    => 0,
            'processing_count'  => 0,
            'processing_amount' => 0,
            'completed_count'   => 0,
            'completed_amount'  => 0,
        ];

        foreach ($items as $payout) {
            $amount = (float) $payout->amount;
            $stats['total_amount'] += $amount;

            switch ($payout->status) {
                case 'pending':
                    $stats['pending_count']++;
                    $stats['pending_amount'] += $amount;
                    break;
                case 'processing':
                    $stats['processing_count']++;
                    $stats['processing_amount'] += $amount;
                    break;
                case 'completed':
                    $stats['completed_count']++;
                    $stats['completed_amount'] += $amount;
                    break;
            }
        }

        $stats['total_amount']      = round($stats['total_amount'], 2);
        $stats['pending_amount']    = round($stats['pending_amount'], 2);
        $stats['processing_amount'] = round($stats['processing_amount'], 2);
        $stats['completed_amount']  = round($stats['completed_amount'], 2);

        return $stats;
    }

    // =========================================================================
    //  REVIEWS
    // =========================================================================

    /**
     * Get reviews for a seller.
     */
    public function getReviews(int $sellerId, int $limit = 20, int $offset = 0): array
    {
        $query = DB::table($this->reviewTable)
            ->where('reviewed_seller_id', $sellerId)
            ->where('review_type', 'buyer_to_seller')
            ->where('is_visible', 1);

        $total = $query->count();
        $items = $query->orderBy('created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Submit a review for a completed transaction.
     */
    public function submitReview(int $transactionId, int $userId, array $data): array
    {
        $txn = DB::table($this->transactionTable)->where('id', $transactionId)->first();
        if (!$txn) {
            return ['success' => false, 'error' => 'Transaction not found'];
        }

        if ($txn->status !== 'completed') {
            return ['success' => false, 'error' => 'Transaction must be completed before leaving a review'];
        }

        // Check if already reviewed
        $hasReviewed = DB::table($this->reviewTable)
            ->where('transaction_id', $transactionId)
            ->where('reviewer_id', $userId)
            ->exists();

        if ($hasReviewed) {
            return ['success' => false, 'error' => 'You have already reviewed this transaction'];
        }

        $rating = (int) ($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => 'Rating must be between 1 and 5'];
        }

        $type = $data['review_type'] ?? 'buyer_to_seller';
        $reviewedSellerId = ($type === 'buyer_to_seller') ? $txn->seller_id : null;

        $id = DB::table($this->reviewTable)->insertGetId([
            'transaction_id'     => $transactionId,
            'reviewer_id'        => $userId,
            'reviewed_seller_id' => $reviewedSellerId,
            'review_type'        => $type,
            'rating'             => $rating,
            'title'              => $data['title'] ?? '',
            'comment'            => $data['comment'] ?? null,
            'is_visible'         => 1,
            'flagged'            => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Recalculate seller average rating
        if ($reviewedSellerId) {
            $this->recalculateSellerRating($reviewedSellerId);
        }

        return ['success' => true, 'id' => $id];
    }

    /**
     * Get average rating for a seller.
     */
    public function getAverageRating(int $sellerId): array
    {
        $reviews = DB::table($this->reviewTable)
            ->where('reviewed_seller_id', $sellerId)
            ->where('is_visible', 1)
            ->where('review_type', 'buyer_to_seller');

        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = (clone $reviews)->where('rating', $i)->count();
        }

        return [
            'average'      => round((clone $reviews)->avg('rating') ?? 0, 2),
            'count'        => (clone $reviews)->count(),
            'distribution' => $distribution,
        ];
    }

    /**
     * Flag a review for moderation.
     */
    public function flagReview(int $reviewId, string $reason): array
    {
        $review = DB::table($this->reviewTable)->where('id', $reviewId)->first();
        if (!$review) {
            return ['success' => false, 'error' => 'Review not found'];
        }

        DB::table($this->reviewTable)->where('id', $reviewId)->update([
            'flagged'     => 1,
            'flag_reason' => $reason,
            'flagged_at'  => now(),
            'updated_at'  => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Moderate a review (set visibility).
     */
    public function moderateReview(int $reviewId, bool $visible): array
    {
        $review = DB::table($this->reviewTable)->where('id', $reviewId)->first();
        if (!$review) {
            return ['success' => false, 'error' => 'Review not found'];
        }

        DB::table($this->reviewTable)->where('id', $reviewId)->update([
            'is_visible'   => $visible ? 1 : 0,
            'flagged'      => 0,
            'moderated_at' => now(),
            'updated_at'   => now(),
        ]);

        // Recalculate seller rating after moderation
        if ($review->reviewed_seller_id) {
            $this->recalculateSellerRating($review->reviewed_seller_id);
        }

        return ['success' => true];
    }

    // =========================================================================
    //  COLLECTIONS
    // =========================================================================

    /**
     * Get collections for a seller.
     */
    public function getCollections(int $sellerId): array
    {
        return DB::table($this->collectionTable)
            ->where('seller_id', $sellerId)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->all();
    }

    /**
     * Get a collection by ID with its items.
     */
    public function getCollection(int $id): ?array
    {
        $collection = DB::table($this->collectionTable)->where('id', $id)->first();
        if (!$collection) {
            return null;
        }

        $items = DB::table($this->collectionItemTable . ' as ci')
            ->join($this->listingTable . ' as l', 'ci.listing_id', '=', 'l.id')
            ->leftJoin($this->sellerTable . ' as s', 'l.seller_id', '=', 's.id')
            ->select(
                'ci.*',
                'l.title', 'l.slug', 'l.price', 'l.currency', 'l.featured_image_path',
                'l.status', 'l.listing_type', 'l.artist_name',
                's.display_name as seller_name', 's.slug as seller_slug'
            )
            ->where('ci.collection_id', $id)
            ->where('l.status', 'active')
            ->orderBy('ci.sort_order', 'ASC')
            ->get()
            ->all();

        return [
            'collection' => $collection,
            'items'      => $items,
        ];
    }

    /**
     * Get a collection by slug.
     */
    public function getCollectionBySlug(string $slug): ?array
    {
        $collection = DB::table($this->collectionTable)->where('slug', $slug)->first();
        if (!$collection) {
            return null;
        }

        return $this->getCollection($collection->id);
    }

    /**
     * Create a new collection.
     */
    public function createCollection(int $sellerId, array $data): array
    {
        if (empty($data['title'])) {
            return ['success' => false, 'error' => 'Collection title is required'];
        }

        $slug = $this->generateCollectionSlug($data['title']);

        $id = DB::table($this->collectionTable)->insertGetId([
            'seller_id'        => $sellerId,
            'title'            => $data['title'],
            'slug'             => $slug,
            'description'      => $data['description'] ?? null,
            'cover_image_path' => $data['cover_image_path'] ?? null,
            'is_public'        => $data['is_public'] ?? 1,
            'is_featured'      => 0,
            'sort_order'       => $data['sort_order'] ?? 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return ['success' => true, 'id' => $id, 'slug' => $slug];
    }

    /**
     * Update a collection.
     */
    public function updateCollection(int $id, array $data): array
    {
        $collection = DB::table($this->collectionTable)->where('id', $id)->first();
        if (!$collection) {
            return ['success' => false, 'error' => 'Collection not found'];
        }

        $updateData  = ['updated_at' => now()];
        $allowedFields = ['title', 'description', 'cover_image_path', 'is_public', 'sort_order'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['title']) && $data['title'] !== $collection->title) {
            $updateData['slug'] = $this->generateCollectionSlug($data['title']);
        }

        DB::table($this->collectionTable)->where('id', $id)->update($updateData);

        return ['success' => true];
    }

    /**
     * Delete a collection and its items.
     */
    public function deleteCollection(int $id): array
    {
        $collection = DB::table($this->collectionTable)->where('id', $id)->first();
        if (!$collection) {
            return ['success' => false, 'error' => 'Collection not found'];
        }

        DB::table($this->collectionItemTable)->where('collection_id', $id)->delete();
        DB::table($this->collectionTable)->where('id', $id)->delete();

        return ['success' => true];
    }

    /**
     * Add a listing to a collection.
     */
    public function addToCollection(int $collectionId, int $listingId, int $sortOrder = 0, ?string $note = null): array
    {
        $collection = DB::table($this->collectionTable)->where('id', $collectionId)->first();
        if (!$collection) {
            return ['success' => false, 'error' => 'Collection not found'];
        }

        $itemId = DB::table($this->collectionItemTable)->insertGetId([
            'collection_id' => $collectionId,
            'listing_id'    => $listingId,
            'sort_order'    => $sortOrder,
            'curator_note'  => $note,
            'created_at'    => now(),
        ]);

        // Update item count
        $count = DB::table($this->collectionItemTable)->where('collection_id', $collectionId)->count();
        DB::table($this->collectionTable)->where('id', $collectionId)->update([
            'item_count'  => $count,
            'updated_at'  => now(),
        ]);

        return ['success' => true, 'id' => $itemId];
    }

    /**
     * Remove a listing from a collection.
     */
    public function removeFromCollection(int $collectionId, int $listingId): array
    {
        $removed = DB::table($this->collectionItemTable)
            ->where('collection_id', $collectionId)
            ->where('listing_id', $listingId)
            ->delete() > 0;

        if (!$removed) {
            return ['success' => false, 'error' => 'Item not found in collection'];
        }

        // Update item count
        $count = DB::table($this->collectionItemTable)->where('collection_id', $collectionId)->count();
        DB::table($this->collectionTable)->where('id', $collectionId)->update([
            'item_count'  => $count,
            'updated_at'  => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Get public collections with pagination.
     */
    public function getPublicCollections(int $limit = 20, int $offset = 0): array
    {
        $query = DB::table($this->collectionTable)->where('is_public', 1);

        $total = $query->count();
        $items = $query->orderBy('is_featured', 'DESC')
                       ->orderBy('sort_order', 'ASC')
                       ->orderBy('created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get featured collections.
     */
    public function getFeaturedCollections(int $limit = 6): array
    {
        return DB::table($this->collectionTable)
            ->where('is_public', 1)
            ->where('is_featured', 1)
            ->orderBy('sort_order', 'ASC')
            ->limit($limit)
            ->get()
            ->all();
    }

    // =========================================================================
    //  SEARCH
    // =========================================================================

    /**
     * Full-text search for listings with filters.
     */
    public function searchListings(string $query, array $filters = [], int $limit = 24, int $offset = 0): array
    {
        $query = trim($query);
        if (empty($query)) {
            return ['items' => [], 'total' => 0, 'query' => '', 'facets' => []];
        }

        $filters['search'] = $query;

        $dbQuery = DB::table($this->listingTable . ' as l')
            ->leftJoin($this->sellerTable . ' as s', 'l.seller_id', '=', 's.id')
            ->select('l.*', 's.display_name as seller_name', 's.slug as seller_slug', 's.average_rating as seller_rating', 's.verification_status as seller_verified')
            ->where('l.status', 'active');

        $this->applyListingFilters($dbQuery, $filters);

        $total = $dbQuery->count();

        $sort = $filters['sort'] ?? 'newest';
        switch ($sort) {
            case 'price_asc':
                $dbQuery->orderBy('l.price', 'ASC');
                break;
            case 'price_desc':
                $dbQuery->orderBy('l.price', 'DESC');
                break;
            case 'popular':
                $dbQuery->orderBy('l.view_count', 'DESC');
                break;
            case 'newest':
            default:
                $dbQuery->orderBy('l.listed_at', 'DESC');
                break;
        }

        $items = $dbQuery->limit($limit)->offset($offset)->get()->all();

        // Get facets scoped to the search
        $facets = $this->getFacetCounts(['search' => $query]);

        return [
            'items'  => $items,
            'total'  => $total,
            'query'  => $query,
            'facets' => $facets,
        ];
    }

    /**
     * Autocomplete suggestions for search.
     */
    public function getAutocompleteSuggestions(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        // Search listings
        $listings = DB::table($this->listingTable)
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', '%' . $query . '%')
                  ->orWhere('artist_name', 'LIKE', '%' . $query . '%');
            })
            ->select('id', 'title', 'slug', 'artist_name', 'featured_image_path', 'price', 'currency', 'listing_type')
            ->orderByRaw("CASE WHEN title LIKE ? THEN 0 ELSE 1 END", [$query . '%'])
            ->limit($limit)
            ->get()
            ->all();

        $suggestions = [];
        foreach ($listings as $listing) {
            $suggestions[] = [
                'id'       => $listing->id,
                'title'    => $listing->title,
                'slug'     => $listing->slug,
                'artist'   => $listing->artist_name,
                'image'    => $listing->featured_image_path,
                'price'    => $listing->price,
                'currency' => $listing->currency,
                'type'     => $listing->listing_type,
            ];
        }

        // Also search sellers
        $sellers = DB::table($this->sellerTable)
            ->where('is_active', 1)
            ->where('display_name', 'LIKE', '%' . $query . '%')
            ->select('id', 'display_name', 'slug', 'avatar_path', 'seller_type')
            ->limit(3)
            ->get()
            ->all();

        foreach ($sellers as $seller) {
            $suggestions[] = [
                'id'          => $seller->id,
                'title'       => $seller->display_name,
                'slug'        => $seller->slug,
                'artist'      => null,
                'image'       => $seller->avatar_path,
                'price'       => null,
                'currency'    => null,
                'type'        => 'seller',
                'seller_type' => $seller->seller_type,
            ];
        }

        return $suggestions;
    }

    /**
     * Get popular search terms (sectors and artists).
     */
    public function getPopularSearches(int $limit = 10): array
    {
        $sectors = DB::table($this->listingTable)
            ->where('status', 'active')
            ->selectRaw("sector, COUNT(*) as cnt")
            ->groupBy('sector')
            ->orderBy('cnt', 'DESC')
            ->limit($limit)
            ->get()
            ->all();

        $popular = [];
        foreach ($sectors as $sector) {
            $popular[] = [
                'term'  => ucfirst($sector->sector),
                'count' => $sector->cnt,
                'type'  => 'sector',
            ];
        }

        $artists = DB::table($this->listingTable)
            ->where('status', 'active')
            ->whereNotNull('artist_name')
            ->where('artist_name', '!=', '')
            ->selectRaw("artist_name, COUNT(*) as cnt")
            ->groupBy('artist_name')
            ->orderBy('cnt', 'DESC')
            ->limit(5)
            ->get()
            ->all();

        foreach ($artists as $artist) {
            $popular[] = [
                'term'  => $artist->artist_name,
                'count' => $artist->cnt,
                'type'  => 'artist',
            ];
        }

        usort($popular, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return array_slice($popular, 0, $limit);
    }

    /**
     * Get all active categories (optionally by sector).
     */
    public function getCategories(?string $sector = null, bool $activeOnly = true): array
    {
        $query = DB::table($this->categoryTable);
        if ($sector) {
            $query->where('sector', $sector);
        }
        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('sector')->orderBy('sort_order')->get()->all();
    }

    /**
     * Get a category by ID.
     */
    public function getCategoryById(int $id): ?object
    {
        return DB::table($this->categoryTable)->where('id', $id)->first();
    }

    /**
     * Get all active currencies.
     */
    public function getCurrencies(bool $activeOnly = true): array
    {
        $query = DB::table($this->currencyTable);
        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('sort_order')->get()->all();
    }

    /**
     * Get a single currency by code.
     */
    public function getCurrency(string $code): ?object
    {
        return DB::table($this->currencyTable)->where('code', strtoupper($code))->first();
    }

    /**
     * Get the default currency code.
     */
    public function getDefaultCurrency(): string
    {
        return $this->getSetting('default_currency', 'ZAR');
    }

    /**
     * Convert between currencies (via ZAR as base).
     */
    public function convertCurrency(float $amount, string $from, string $to): array
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return [
                'success'          => true,
                'original_amount'  => $amount,
                'converted_amount' => $amount,
                'from'             => $from,
                'to'               => $to,
                'rate'             => 1.0,
            ];
        }

        // Convert source to ZAR
        $zarAmount = $amount;
        if ($from !== 'ZAR') {
            $fromCurrency = $this->getCurrency($from);
            if ($fromCurrency && $fromCurrency->exchange_rate_to_zar > 0) {
                $zarAmount = $amount / $fromCurrency->exchange_rate_to_zar;
            }
        }

        // Convert ZAR to target
        $convertedAmount = $zarAmount;
        if ($to !== 'ZAR') {
            $toCurrency = $this->getCurrency($to);
            if ($toCurrency && $toCurrency->exchange_rate_to_zar > 0) {
                $convertedAmount = $zarAmount * $toCurrency->exchange_rate_to_zar;
            }
        }

        $rate = $amount > 0 ? round($convertedAmount / $amount, 6) : 0;

        return [
            'success'          => true,
            'original_amount'  => $amount,
            'converted_amount' => round($convertedAmount, 2),
            'from'             => $from,
            'to'               => $to,
            'rate'             => $rate,
        ];
    }

    /**
     * Format a price with the correct currency symbol.
     */
    public function formatPrice(float $amount, string $currencyCode): string
    {
        $currency = $this->getCurrency($currencyCode);

        if (!$currency) {
            return strtoupper($currencyCode) . ' ' . number_format($amount, 2);
        }

        $symbol   = $currency->symbol ?? strtoupper($currencyCode);
        $decimals = (int) ($currency->decimal_places ?? 2);
        $formatted = number_format($amount, $decimals, '.', ',');

        $symbolPosition = $currency->symbol_position ?? 'before';

        if ($symbolPosition === 'after') {
            return $formatted . ' ' . $symbol;
        }

        return $symbol . $formatted;
    }

    /**
     * Update an exchange rate for a currency.
     */
    public function updateExchangeRate(string $code, float $rate): array
    {
        $code = strtoupper($code);
        $currency = $this->getCurrency($code);

        if (!$currency) {
            return ['success' => false, 'error' => 'Currency not found: ' . $code];
        }

        if ($rate <= 0) {
            return ['success' => false, 'error' => 'Exchange rate must be greater than zero'];
        }

        DB::table($this->currencyTable)->where('code', $code)->update([
            'exchange_rate_to_zar' => $rate,
            'updated_at'           => now(),
        ]);

        return ['success' => true, 'code' => $code, 'rate' => $rate];
    }

    /**
     * Build search filters from request parameters.
     */
    public function buildSearchFilters(array $params): array
    {
        $filters = [];

        if (!empty($params['sector'])) {
            $filters['sector'] = $params['sector'];
        }
        if (!empty($params['category_id'])) {
            $filters['category_id'] = (int) $params['category_id'];
        }
        if (!empty($params['listing_type'])) {
            $filters['listing_type'] = $params['listing_type'];
        }
        if (!empty($params['seller_id'])) {
            $filters['seller_id'] = (int) $params['seller_id'];
        }
        if (isset($params['price_min']) && is_numeric($params['price_min'])) {
            $filters['price_min'] = (float) $params['price_min'];
        }
        if (isset($params['price_max']) && is_numeric($params['price_max'])) {
            $filters['price_max'] = (float) $params['price_max'];
        }
        if (!empty($params['condition_rating'])) {
            $filters['condition_rating'] = $params['condition_rating'];
        }
        if (!empty($params['medium'])) {
            $filters['medium'] = $params['medium'];
        }
        if (!empty($params['country'])) {
            $filters['country'] = $params['country'];
        }
        if (isset($params['is_digital'])) {
            $filters['is_digital'] = (int) $params['is_digital'];
        }
        if (!empty($params['sort'])) {
            $filters['sort'] = $params['sort'];
        }

        return $filters;
    }

    // =========================================================================
    //  ADMIN
    // =========================================================================

    /**
     * Get admin dashboard statistics.
     */
    public function getDashboardStats(): array
    {
        $now = now();

        return [
            'total_listings'         => DB::table($this->listingTable)->count(),
            'active_listings'        => DB::table($this->listingTable)->where('status', 'active')->count(),
            'pending_listings'       => DB::table($this->listingTable)->where('status', 'pending_review')->count(),
            'total_sellers'          => DB::table($this->sellerTable)->count(),
            'active_sellers'         => DB::table($this->sellerTable)->where('is_active', 1)->count(),
            'unverified_sellers'     => DB::table($this->sellerTable)->where('verification_status', 'unverified')->count(),
            'total_transactions'     => DB::table($this->transactionTable)->count(),
            'pending_transactions'   => DB::table($this->transactionTable)->where('status', 'pending_payment')->count(),
            'total_revenue'          => DB::table($this->transactionTable)->where('payment_status', 'paid')->sum('sale_price') ?? 0,
            'total_commission'       => DB::table($this->transactionTable)->where('payment_status', 'paid')->sum('platform_commission_amount') ?? 0,
            'pending_payouts'        => DB::table($this->payoutTable)->where('status', 'pending')->count(),
            'pending_payout_amount'  => DB::table($this->payoutTable)->where('status', 'pending')->sum('amount') ?? 0,
            'active_auctions'        => DB::table($this->auctionTable)->where('status', 'active')->where('end_time', '>', $now)->count(),
            'flagged_reviews'        => DB::table($this->reviewTable)->where('flagged', 1)->count(),
            'pending_enquiries'      => Schema::hasTable($this->enquiryTable) ? DB::table($this->enquiryTable)->where('status', 'new')->count() : 0,
        ];
    }

    /**
     * Get all settings (optionally by group).
     */
    public function getSettings(?string $group = null): array
    {
        $query = DB::table($this->settingsTable);
        if ($group) {
            $query->where('setting_group', $group);
        }

        return $query->orderBy('setting_key')->get()->all();
    }

    /**
     * Update or create a setting.
     */
    public function updateSetting(string $name, $value, string $type = 'text', string $group = 'general', ?string $description = null): array
    {
        if (is_array($value)) {
            $value = json_encode($value);
            $type  = 'json';
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
            $type  = 'boolean';
        }

        $exists = DB::table($this->settingsTable)->where('setting_key', $name)->exists();

        if ($exists) {
            DB::table($this->settingsTable)->where('setting_key', $name)->update([
                'setting_value' => (string) $value,
                'setting_type'  => $type,
                'updated_at'    => now(),
            ]);
        } else {
            DB::table($this->settingsTable)->insert([
                'setting_key'   => $name,
                'setting_value' => (string) $value,
                'setting_type'  => $type,
                'setting_group' => $group,
                'description'   => $description,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        return ['success' => true];
    }

    /**
     * Get listings pending admin review.
     */
    public function getPendingListings(int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->listingTable . ' as l')
            ->leftJoin($this->sellerTable . ' as s', 'l.seller_id', '=', 's.id')
            ->select('l.*', 's.display_name as seller_name')
            ->where('l.status', 'pending_review');

        $total = $query->count();
        $items = $query->orderBy('l.created_at', 'ASC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get sellers pending verification.
     */
    public function getPendingSellerVerifications(int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->sellerTable)
            ->where('verification_status', 'unverified')
            ->where('is_active', 1);

        $total = $query->count();
        $items = $query->orderBy('created_at', 'ASC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get all transactions for admin view with filters.
     */
    public function getAdminTransactions(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->transactionTable . ' as t')
            ->join($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->leftJoin($this->sellerTable . ' as s', 't.seller_id', '=', 's.id')
            ->select('t.*', 'l.title', 's.display_name as seller_name');

        if (!empty($filters['status'])) {
            $query->where('t.status', $filters['status']);
        }
        if (!empty($filters['payment_status'])) {
            $query->where('t.payment_status', $filters['payment_status']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('t.transaction_number', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('l.title', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        $total = $query->count();
        $items = $query->orderBy('t.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get all payouts for admin view with filters.
     */
    public function getAdminPayouts(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->payoutTable . ' as p')
            ->join($this->sellerTable . ' as s', 'p.seller_id', '=', 's.id')
            ->select('p.*', 's.display_name as seller_name');

        if (!empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }

        $total = $query->count();
        $items = $query->orderBy('p.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get all reviews for admin moderation with filters.
     */
    public function getAdminReviews(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->reviewTable . ' as r')
            ->leftJoin($this->sellerTable . ' as s', 'r.reviewed_seller_id', '=', 's.id')
            ->select('r.*', 's.display_name as seller_name');

        if (!empty($filters['flagged'])) {
            $query->where('r.flagged', 1);
        }
        if (isset($filters['is_visible'])) {
            $query->where('r.is_visible', $filters['is_visible']);
        }

        $total = $query->count();
        $items = $query->orderBy('r.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get all sellers for admin view with filters.
     */
    public function getAdminSellers(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->sellerTable);

        if (!empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }
        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('display_name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        $total = $query->count();
        $items = $query->orderBy('created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    // =========================================================================
    //  ENQUIRIES
    // =========================================================================

    /**
     * Submit an enquiry on a listing.
     */
    public function submitEnquiry(int $listingId, int $userId, array $data): array
    {
        $listing = DB::table($this->listingTable)->where('id', $listingId)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        $id = DB::table($this->enquiryTable)->insertGetId([
            'listing_id'  => $listingId,
            'user_id'     => $userId,
            'name'        => $data['name'] ?? null,
            'email'       => $data['email'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'subject'     => $data['subject'] ?? null,
            'message'     => $data['message'] ?? '',
            'status'      => 'new',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Increment enquiry count on listing
        DB::table($this->listingTable)->where('id', $listingId)->update([
            'enquiry_count' => ($listing->enquiry_count ?? 0) + 1,
            'updated_at'    => now(),
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Get enquiries with optional filters.
     */
    public function getEnquiries(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->enquiryTable . ' as e')
            ->leftJoin($this->listingTable . ' as l', 'e.listing_id', '=', 'l.id')
            ->select('e.*', 'l.title as listing_title', 'l.slug as listing_slug');

        if (!empty($filters['status'])) {
            $query->where('e.status', $filters['status']);
        }
        if (!empty($filters['listing_id'])) {
            $query->where('e.listing_id', $filters['listing_id']);
        }

        $total = $query->count();
        $items = $query->orderBy('e.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get enquiries for listings belonging to a seller.
     */
    public function getSellerEnquiries(int $sellerId, ?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->enquiryTable . ' as e')
            ->join($this->listingTable . ' as l', 'e.listing_id', '=', 'l.id')
            ->select('e.*', 'l.title as listing_title', 'l.slug as listing_slug')
            ->where('l.seller_id', $sellerId);

        if ($status) {
            $query->where('e.status', $status);
        }

        $total = $query->count();
        $items = $query->orderBy('e.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get a single enquiry.
     */
    public function getEnquiry(int $id): ?object
    {
        return DB::table($this->enquiryTable)->where('id', $id)->first();
    }

    /**
     * Update enquiry status/response.
     */
    public function updateEnquiry(int $id, array $data): array
    {
        $enquiry = DB::table($this->enquiryTable)->where('id', $id)->first();
        if (!$enquiry) {
            return ['success' => false, 'error' => 'Enquiry not found'];
        }

        $data['updated_at'] = now();
        DB::table($this->enquiryTable)->where('id', $id)->update($data);

        return ['success' => true];
    }

    // =========================================================================
    //  FOLLOWS
    // =========================================================================

    /**
     * Follow a seller.
     */
    public function followSeller(int $userId, int $sellerId): array
    {
        $exists = DB::table($this->followTable)
            ->where('user_id', $userId)
            ->where('seller_id', $sellerId)
            ->exists();

        if ($exists) {
            return ['success' => false, 'error' => 'Already following this seller'];
        }

        DB::table($this->followTable)->insert([
            'user_id'    => $userId,
            'seller_id'  => $sellerId,
            'created_at' => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Unfollow a seller.
     */
    public function unfollowSeller(int $userId, int $sellerId): array
    {
        $deleted = DB::table($this->followTable)
            ->where('user_id', $userId)
            ->where('seller_id', $sellerId)
            ->delete() > 0;

        if (!$deleted) {
            return ['success' => false, 'error' => 'Not following this seller'];
        }

        return ['success' => true];
    }

    /**
     * Toggle follow/unfollow for a seller.
     */
    public function toggleFollow(int $userId, int $sellerId): array
    {
        $exists = $this->isFollowing($userId, $sellerId);

        if ($exists) {
            $this->unfollowSeller($userId, $sellerId);
            return ['success' => true, 'following' => false];
        }

        $this->followSeller($userId, $sellerId);
        return ['success' => true, 'following' => true];
    }

    /**
     * Get all sellers a user is following.
     */
    public function getFollowing(int $userId, int $limit = 50, int $offset = 0): array
    {
        $query = DB::table($this->followTable . ' as f')
            ->join($this->sellerTable . ' as s', 'f.seller_id', '=', 's.id')
            ->select('s.*', 'f.created_at as followed_at')
            ->where('f.user_id', $userId)
            ->where('s.is_active', 1);

        $total = $query->count();
        $items = $query->orderBy('f.created_at', 'DESC')
                       ->limit($limit)
                       ->offset($offset)
                       ->get()
                       ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Check if a user follows a specific seller.
     */
    public function isFollowing(int $userId, int $sellerId): bool
    {
        return DB::table($this->followTable)
            ->where('user_id', $userId)
            ->where('seller_id', $sellerId)
            ->exists();
    }

    /**
     * Get follower count for a seller.
     */
    public function getFollowerCount(int $sellerId): int
    {
        return DB::table($this->followTable)->where('seller_id', $sellerId)->count();
    }

    // =========================================================================
    //  SHIPPING
    // =========================================================================

    /**
     * Get a shipping estimate for a listing to a country.
     */
    public function getShippingEstimate(int $listingId, string $country): array
    {
        $listing = DB::table($this->listingTable)->where('id', $listingId)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        if ($listing->is_digital) {
            return [
                'success'     => true,
                'type'        => 'digital',
                'cost'        => 0,
                'currency'    => $listing->currency,
                'description' => 'Digital delivery - no shipping required',
            ];
        }

        if (!$listing->requires_shipping) {
            return [
                'success'     => true,
                'type'        => 'collection',
                'cost'        => 0,
                'currency'    => $listing->currency,
                'description' => 'Collection only - no shipping available',
            ];
        }

        $sellerCountry = $listing->shipping_from_country ?? $this->getSetting('default_country', 'ZA');
        $isDomestic    = strtoupper($country) === strtoupper($sellerCountry);

        if ($isDomestic) {
            $cost        = (float) ($listing->shipping_domestic_price ?? 0);
            $type        = 'domestic';
            $description = 'Domestic shipping';
        } else {
            $cost        = (float) ($listing->shipping_international_price ?? 0);
            $type        = 'international';
            $description = 'International shipping to ' . strtoupper($country);

            if ($cost <= 0) {
                return [
                    'success' => false,
                    'error'   => 'International shipping not available for this listing',
                ];
            }
        }

        return [
            'success'      => true,
            'type'         => $type,
            'cost'         => $cost,
            'currency'     => $listing->currency,
            'description'  => $description,
            'from_country' => $sellerCountry,
            'to_country'   => strtoupper($country),
        ];
    }

    /**
     * Get tracking info for a transaction.
     */
    public function getTrackingInfo(int $txnId): ?array
    {
        $txn = DB::table($this->transactionTable)->where('id', $txnId)->first();
        if (!$txn) {
            return null;
        }

        return [
            'transaction_id'          => $txn->id,
            'transaction_number'      => $txn->transaction_number,
            'tracking_number'         => $txn->tracking_number ?? null,
            'courier'                 => $txn->courier ?? null,
            'shipping_status'         => $txn->shipping_status ?? 'pending',
            'shipped_at'              => $txn->shipped_at ?? null,
            'delivered_at'            => $txn->delivered_at ?? null,
            'buyer_confirmed_receipt' => (bool) ($txn->buyer_confirmed_receipt ?? false),
            'receipt_confirmed_at'    => $txn->receipt_confirmed_at ?? null,
        ];
    }

    // =========================================================================
    //  SETTINGS HELPER
    // =========================================================================

    /**
     * Get a marketplace setting value with typed casting.
     */
    public function getSetting(string $key, $default = null)
    {
        $row = DB::table($this->settingsTable)->where('setting_key', $key)->first();

        if (!$row) {
            return $default;
        }

        return match ($row->setting_type) {
            'boolean' => (bool) $row->setting_value,
            'number'  => is_numeric($row->setting_value) ? (float) $row->setting_value : $default,
            'json'    => json_decode($row->setting_value, true) ?? $default,
            default   => $row->setting_value,
        };
    }

    // =========================================================================
    //  PRIVATE HELPERS
    // =========================================================================

    /**
     * Apply listing filter conditions to a query builder.
     */
    private function applyListingFilters($query, array $filters): void
    {
        if (!empty($filters['sector'])) {
            $query->where('l.sector', $filters['sector']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('l.category_id', $filters['category_id']);
        }
        if (!empty($filters['listing_type'])) {
            $query->where('l.listing_type', $filters['listing_type']);
        }
        if (!empty($filters['seller_id'])) {
            $query->where('l.seller_id', $filters['seller_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('l.status', $filters['status']);
        }
        if (isset($filters['price_min'])) {
            $query->where('l.price', '>=', $filters['price_min']);
        }
        if (isset($filters['price_max'])) {
            $query->where('l.price', '<=', $filters['price_max']);
        }
        if (!empty($filters['condition_rating'])) {
            $query->where('l.condition_rating', $filters['condition_rating']);
        }
        if (!empty($filters['medium'])) {
            $query->where('l.medium', 'LIKE', '%' . $filters['medium'] . '%');
        }
        if (!empty($filters['country'])) {
            $query->where('l.shipping_from_country', $filters['country']);
        }
        if (isset($filters['is_digital'])) {
            $query->where('l.is_digital', $filters['is_digital']);
        }
        if (!empty($filters['search'])) {
            $query->whereRaw("MATCH(l.title, l.description, l.artist_name, l.medium) AGAINST(? IN BOOLEAN MODE)", [$filters['search']]);
        }
    }

    /**
     * Generate a unique slug for a given table.
     */
    private function generateSlug(string $title, string $table): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        $slug = preg_replace('/-+/', '-', $slug);

        $baseSlug = $slug;
        $counter  = 1;
        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate a unique slug for collections.
     */
    private function generateCollectionSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        $slug = preg_replace('/-+/', '-', $slug);

        $baseSlug = $slug;
        $counter  = 1;
        while (DB::table($this->collectionTable)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate a unique listing number (MKT-YYYYMMDD-NNNN).
     */
    private function generateListingNumber(): string
    {
        $date = date('Ymd');
        $last = DB::table($this->listingTable)
            ->where('listing_number', 'LIKE', 'MKT-' . $date . '-%')
            ->orderBy('id', 'DESC')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->listing_number);
            $seq   = (int) end($parts) + 1;
        }

        return sprintf('MKT-%s-%04d', $date, $seq);
    }

    /**
     * Generate a unique payout number (PAY-YYYYMMDD-NNNN).
     */
    private function generatePayoutNumber(): string
    {
        $date = date('Ymd');
        $last = DB::table($this->payoutTable)
            ->where('payout_number', 'LIKE', 'PAY-' . $date . '-%')
            ->orderBy('id', 'DESC')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->payout_number);
            $seq   = (int) end($parts) + 1;
        }

        return sprintf('PAY-%s-%04d', $date, $seq);
    }

    /**
     * Generate a unique transaction number (TXN-YYYYMMDD-NNNN).
     */
    private function generateTransactionNumber(): string
    {
        $date = date('Ymd');
        $last = DB::table($this->transactionTable)
            ->where('transaction_number', 'LIKE', 'TXN-' . $date . '-%')
            ->orderBy('id', 'DESC')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->transaction_number);
            $seq   = (int) end($parts) + 1;
        }

        return sprintf('TXN-%s-%04d', $date, $seq);
    }

    /**
     * Process proxy (auto) bids from other users after a new bid.
     */
    private function processProxyBids(int $auctionId, int $excludeUserId, float $currentBid): void
    {
        $auction = DB::table($this->auctionTable)->where('id', $auctionId)->first();
        if (!$auction) {
            return;
        }

        $proxyBids = DB::table($this->bidTable)
            ->where('auction_id', $auctionId)
            ->whereNotNull('max_bid')
            ->where('max_bid', '>', DB::raw('bid_amount'))
            ->orderBy('max_bid', 'DESC')
            ->get()
            ->all();

        foreach ($proxyBids as $proxy) {
            if ($proxy->user_id == $excludeUserId) {
                continue;
            }

            $autoBidAmount = $currentBid + $auction->bid_increment;
            if ($autoBidAmount <= $proxy->max_bid) {
                DB::table($this->bidTable)->where('auction_id', $auctionId)->update(['is_winning' => 0]);

                DB::table($this->bidTable)->insertGetId([
                    'auction_id'  => $auctionId,
                    'user_id'     => $proxy->user_id,
                    'bid_amount'  => $autoBidAmount,
                    'max_bid'     => $proxy->max_bid,
                    'is_auto_bid' => true,
                    'is_winning'  => true,
                    'created_at'  => now(),
                ]);

                DB::table($this->auctionTable)->where('id', $auctionId)->update([
                    'current_bid'       => $autoBidAmount,
                    'current_bidder_id' => $proxy->user_id,
                    'bid_count'         => $auction->bid_count + 1,
                    'updated_at'        => now(),
                ]);

                break; // Only one auto-bid per round
            }
        }
    }

    /**
     * Create a transaction from a fixed-price purchase.
     */
    private function createTransactionFromFixedPrice(int $listingId, int $buyerId): array
    {
        $listing = DB::table($this->listingTable)->where('id', $listingId)->first();
        if (!$listing || $listing->status !== 'active') {
            return ['success' => false, 'error' => 'Listing is not available'];
        }

        return $this->buildTransaction($listing, $buyerId, 'fixed_price', $listing->price);
    }

    /**
     * Create a transaction from an accepted offer.
     */
    private function createTransactionFromOffer(int $offerId, int $buyerId): array
    {
        $offer = DB::table($this->offerTable)->where('id', $offerId)->first();
        if (!$offer || $offer->status !== 'accepted') {
            return ['success' => false, 'error' => 'Offer is not accepted'];
        }

        $listing = DB::table($this->listingTable)->where('id', $offer->listing_id)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        $price = $offer->counter_amount ?? $offer->offer_amount;

        return $this->buildTransaction($listing, $buyerId, 'offer', $price, $offerId);
    }

    /**
     * Create a transaction from an ended auction with a winner.
     */
    private function createTransactionFromAuction(int $auctionId): array
    {
        $auction = DB::table($this->auctionTable)->where('id', $auctionId)->first();
        if (!$auction || $auction->status !== 'ended' || !$auction->winner_id) {
            return ['success' => false, 'error' => 'Auction has no winner'];
        }

        $listing = DB::table($this->listingTable)->where('id', $auction->listing_id)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        return $this->buildTransaction($listing, $auction->winner_id, 'auction', $auction->winning_bid, null, $auctionId);
    }

    /**
     * Build and insert a transaction record with commission, VAT, and shipping calculations.
     */
    private function buildTransaction(object $listing, int $buyerId, string $source, float $salePrice, ?int $offerId = null, ?int $auctionId = null): array
    {
        $seller = DB::table($this->sellerTable)->where('id', $listing->seller_id)->first();
        if (!$seller) {
            return ['success' => false, 'error' => 'Seller not found'];
        }

        // Commission
        $commissionRate   = $seller->commission_rate ?? (float) $this->getSetting('default_commission_rate', 10);
        $commissionAmount = round($salePrice * ($commissionRate / 100), 2);
        $sellerAmount     = round($salePrice - $commissionAmount, 2);

        // VAT (prices include VAT)
        $vatRate   = (float) $this->getSetting('vat_rate', 15);
        $vatAmount = round($salePrice - ($salePrice / (1 + ($vatRate / 100))), 2);
        $totalWithVat = $salePrice;

        // Shipping
        $shippingCost  = $listing->requires_shipping ? ($listing->shipping_domestic_price ?? 0) : 0;
        $insuranceCost = 0;
        $grandTotal    = round($totalWithVat + $shippingCost + $insuranceCost, 2);

        $now = now();
        $txnId = DB::table($this->transactionTable)->insertGetId([
            'transaction_number'         => $this->generateTransactionNumber(),
            'listing_id'                 => $listing->id,
            'seller_id'                  => $listing->seller_id,
            'buyer_id'                   => $buyerId,
            'source'                     => $source,
            'offer_id'                   => $offerId,
            'auction_id'                 => $auctionId,
            'sale_price'                 => $salePrice,
            'currency'                   => $listing->currency,
            'platform_commission_rate'   => $commissionRate,
            'platform_commission_amount' => $commissionAmount,
            'seller_amount'              => $sellerAmount,
            'vat_amount'                 => $vatAmount,
            'total_with_vat'             => $totalWithVat,
            'shipping_cost'              => $shippingCost,
            'insurance_cost'             => $insuranceCost,
            'grand_total'                => $grandTotal,
            'status'                     => 'pending_payment',
            'payment_status'             => 'pending',
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ]);

        // Reserve the listing
        DB::table($this->listingTable)->where('id', $listing->id)->update([
            'status'     => 'reserved',
            'updated_at' => $now,
        ]);

        return [
            'success'        => true,
            'transaction_id' => $txnId,
            'transaction'    => DB::table($this->transactionTable)->where('id', $txnId)->first(),
        ];
    }

    /**
     * Recalculate and update a seller's average rating from visible reviews.
     */
    private function recalculateSellerRating(int $sellerId): void
    {
        $stats = DB::table($this->reviewTable)
            ->where('reviewed_seller_id', $sellerId)
            ->where('is_visible', 1)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        if ($stats) {
            DB::table($this->sellerTable)->where('id', $sellerId)->update([
                'average_rating' => round($stats->avg_rating ?? 0, 2),
                'rating_count'   => $stats->cnt ?? 0,
                'updated_at'     => now(),
            ]);
        }
    }

    /**
     * Get the pending payout amount for a seller (earned minus already paid out).
     */
    private function getSellerPendingPayoutAmount(int $sellerId): float
    {
        $paidOut = DB::table($this->payoutTable)
            ->where('seller_id', $sellerId)
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->sum('amount') ?? 0;

        $earned = DB::table($this->transactionTable)
            ->where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->sum('seller_amount') ?? 0;

        return max(0, $earned - $paidOut);
    }
}
