<?php

/**
 * MarketplaceService - Service for Heratio
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



namespace AhgMarketplace\Services;

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
    protected string $favouriteTable     = 'marketplace_favourite';

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
            if (is_array($filters['sector'])) {
                $query->whereIn('sector', $filters['sector']);
            } else {
                $query->where('sector', $filters['sector']);
            }
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
            $sectorVals = is_array($filters['sector']) ? $filters['sector'] : [$filters['sector']];
            $query->where(function ($q) use ($sectorVals) {
                foreach ($sectorVals as $s) {
                    $q->orWhereRaw("JSON_CONTAINS(sectors, ?)", ['"' . $s . '"']);
                }
            });
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
    /**
     * PSIS-named alias for updateTransactionShipping (Phase X.1.7).
     * Matches PSIS TransactionService::updateShipping signature exactly.
     */
    public function updateShipping(int $txnId, array $data): array
    {
        return $this->updateTransactionShipping($txnId, $data);
    }

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
     * PSIS-named alias for processPayouts (Phase X.1.7).
     * Matches PSIS PayoutService::batchProcess signature exactly.
     *
     * @param int[] $payoutIds
     */
    public function batchProcessPayouts(array $payoutIds, int $processedBy): array
    {
        return $this->processPayouts($payoutIds, $processedBy);
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
    /**
     * @param string|array|null $sector single sector slug, an array of slugs, or null for all.
     */
    public function getCategories($sector = null, bool $activeOnly = true): array
    {
        $query = DB::table($this->categoryTable);
        if (is_array($sector) && !empty($sector)) {
            $query->whereIn('sector', $sector);
        } elseif (is_string($sector) && $sector !== '') {
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
     * Get a category by sector + slug.
     */
    public function getCategoryBySlug(string $sector, string $slug): ?object
    {
        return DB::table($this->categoryTable)
            ->where('sector', $sector)
            ->where('slug', $slug)
            ->first();
    }

    // ---- Category CRUD (Phase X.1.6 — matches PSIS SettingsRepository) ----

    public function createCategory(array $data): int
    {
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        }
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        }
        if (!isset($data['created_at'])) {
            $data['created_at'] = now();
        }
        return (int) DB::table($this->categoryTable)->insertGetId($data);
    }

    public function updateCategory(int $id, array $data): bool
    {
        $data['updated_at'] = now();
        return DB::table($this->categoryTable)->where('id', $id)->update($data) >= 0;
    }

    public function deleteCategory(int $id): bool
    {
        return DB::table($this->categoryTable)->where('id', $id)->delete() > 0;
    }

    // ---- Currency CRUD (Phase X.1.6 — matches PSIS SettingsRepository) ----

    public function addCurrency(array $data): int
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        }
        if (!isset($data['created_at'])) {
            $data['created_at'] = now();
        }
        return (int) DB::table($this->currencyTable)->insertGetId($data);
    }

    public function updateCurrency(string $code, array $data): bool
    {
        $data['updated_at'] = now();
        return DB::table($this->currencyTable)
            ->where('code', strtoupper($code))
            ->update($data) >= 0;
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
    public function getDashboardStats(?int $sellerId = null): array
    {
        // Site-wide stats (admin dashboard)
        if ($sellerId === null) {
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

        // Seller-scoped stats (seller dashboard)
        $listingCounts = DB::table($this->listingTable)
            ->where('seller_id', $sellerId)
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as drafts,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as withdrawn,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sold', ['draft', 'published', 'withdrawn', 'sold'])
            ->first();

        $totalRevenue = DB::table($this->transactionTable)
            ->where('seller_id', $sellerId)
            ->where('payment_status', 'paid')
            ->sum('sale_price') ?? 0;

        $pendingOffers = $this->getPendingOfferCount($sellerId);

        $activeAuctions = DB::table($this->auctionTable . ' as a')
            ->join($this->listingTable . ' as l', 'l.id', '=', 'a.listing_id')
            ->where('l.seller_id', $sellerId)
            ->where('a.status', 'active')
            ->where('a.end_time', '>', now())
            ->count();

        $totalEnquiries = Schema::hasTable($this->enquiryTable)
            ? DB::table($this->enquiryTable)
                ->join($this->listingTable . ' as l', 'l.id', '=', $this->enquiryTable . '.listing_id')
                ->where('l.seller_id', $sellerId)
                ->count()
            : 0;

        $totalViews = (int) DB::table($this->listingTable)
            ->where('seller_id', $sellerId)
            ->sum('view_count');

        $totalFavourites = (int) DB::table($this->listingTable)
            ->where('seller_id', $sellerId)
            ->sum('favourite_count');

        return [
            'total_listings'    => (int) ($listingCounts->total ?? 0),
            'draft_listings'    => (int) ($listingCounts->drafts ?? 0),
            'published_listings'=> (int) ($listingCounts->published ?? 0),
            'withdrawn_listings'=> (int) ($listingCounts->withdrawn ?? 0),
            'sold_listings'     => (int) ($listingCounts->sold ?? 0),
            'total_revenue'     => (float) $totalRevenue,
            'pending_offers'    => $pendingOffers,
            'active_auctions'   => (int) $activeAuctions,
            'total_enquiries'   => (int) $totalEnquiries,
            'total_views'       => $totalViews,
            'total_favourites'  => $totalFavourites,
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
    // ---- Settings aliases matching PSIS names (Phase X.1.6) ----

    public function getAllSettings(?string $group = null): array
    {
        return $this->getSettings($group);
    }

    public function setSetting(string $key, $value, string $type = 'text', string $group = 'general', ?string $description = null): void
    {
        $this->updateSetting($key, $value, $type, $group, $description);
    }

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
            if (is_array($filters['sector'])) {
                $query->whereIn('l.sector', $filters['sector']);
            } else {
                $query->where('l.sector', $filters['sector']);
            }
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

    // =========================================================================
    //  LISTING + AUCTION HELPERS (Phase X.1.3)
    //
    //  Alias and support methods the controllers call. `getListingById()` is
    //  a non-view-incrementing counterpart to `getListing()` for admin flows
    //  where we don't want the view count to tick up.
    // =========================================================================

    /**
     * Fetch a listing by ID without incrementing the view count.
     * Used from admin review pages and seller edit flows where a "view"
     * would be misleading.
     */
    public function getListingById(int $id): ?object
    {
        return DB::table($this->listingTable)->where('id', $id)->first();
    }

    /**
     * Change a listing's status. Returns true if a row was updated.
     * Accepted values: draft, pending_review, active, reserved, sold, expired,
     * withdrawn, suspended. The caller is responsible for state-machine validity
     * (e.g. don't move a `completed` back to `draft`).
     */
    public function updateListingStatus(int $listingId, string $status): bool
    {
        return DB::table($this->listingTable)
            ->where('id', $listingId)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Get the auction row tied to a listing (1:1). Returns null if the listing
     * is not an auction type or has no auction row yet.
     */
    public function getAuctionForListing(int $listingId): ?object
    {
        return DB::table($this->auctionTable)->where('listing_id', $listingId)->first();
    }

    /**
     * Same as getAuctionForListing() but takes a slug. Used by the public
     * listing detail page which has the slug in the URL.
     */
    public function getAuctionForListingBySlug(string $slug): ?object
    {
        $listing = DB::table($this->listingTable)->where('slug', $slug)->first(['id']);
        if (!$listing) {
            return null;
        }
        return $this->getAuctionForListing((int) $listing->id);
    }

    /**
     * Recent bid history for an auction, newest first. Joins to user for
     * display name. Anonymised bidder labels are generated in the view layer
     * (Bidder #abc123) so we just pass user_id through.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getBidHistory(int $auctionId, int $limit = 50): \Illuminate\Support\Collection
    {
        // Matches PSIS AuctionRepository::getBids — highest bid first, default limit 50.
        return DB::table($this->bidTable)
            ->where('auction_id', $auctionId)
            ->orderByDesc('bid_amount')
            ->limit($limit)
            ->get();
    }

    /**
     * Return the primary image for a listing.
     *
     * Accepts either an array/Collection of already-fetched images (in which case
     * we scan for `is_primary = 1` without another query) or an int listing_id
     * (in which case we query directly). The controller uses the array form for
     * the listing detail page where images were fetched separately.
     *
     * @param  array|\Illuminate\Support\Collection|int $imagesOrListingId
     * @return object|null
     */
    public function getPrimaryImage($imagesOrListingId): ?object
    {
        if (is_int($imagesOrListingId)) {
            return DB::table($this->imageTable)
                ->where('listing_id', $imagesOrListingId)
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->first();
        }

        // Array / Collection path
        $first = null;
        foreach ($imagesOrListingId as $img) {
            if (!empty($img->is_primary)) {
                return $img;
            }
            if ($first === null) {
                $first = $img;
            }
        }
        return $first;
    }

    /**
     * Related listings — same category or sector, excluding the current listing,
     * limited to active status, ordered by recency. Accepts a listing object or
     * a listing id.
     *
     * @param  object|int $listingOrId
     * @return \Illuminate\Support\Collection
     */
    public function getRelatedListings($listingOrId, int $limit = 4): \Illuminate\Support\Collection
    {
        if (is_int($listingOrId)) {
            $listing = $this->getListingById($listingOrId);
        } else {
            $listing = $listingOrId;
        }
        if (!$listing) {
            return collect();
        }

        // Matches PSIS marketplaceListingAction — applies sector AND category_id
        // simultaneously, fetches slightly more than needed, excludes current
        // listing, slices to $limit. PSIS fetches 6 and slices to 4.
        $query = DB::table($this->listingTable)
            ->where('status', 'active')
            ->where('id', '!=', $listing->id);

        if (!empty($listing->sector)) {
            $query->where('sector', $listing->sector);
        }
        if (!empty($listing->category_id)) {
            $query->where('category_id', $listing->category_id);
        }

        return $query->orderByDesc('listed_at')
            ->limit($limit + 2)
            ->get()
            ->take($limit)
            ->values();
    }

    /**
     * Upload and register a listing image. Stores the file under
     * `{heratio.uploads_path}/marketplace/{listing_id}/` and inserts a row into
     * `marketplace_listing_image`. First image uploaded auto-becomes primary.
     *
     * @param  int $listingId
     * @param  \Illuminate\Http\UploadedFile $file
     * @param  string|null $caption
     * @param  int $sortOrder
     * @return array{success:bool, image_id?:int, error?:string}
     */
    public function uploadListingImage(int $listingId, $file, ?string $caption = null, int $sortOrder = 0): array
    {
        if (!$file || !method_exists($file, 'isValid') || !$file->isValid()) {
            return ['success' => false, 'error' => 'Invalid file upload'];
        }

        $uploadsBase = rtrim(config('heratio.uploads_path', storage_path('app/uploads')), '/');
        $destDir = $uploadsBase . '/marketplace/' . $listingId;
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return ['success' => false, 'error' => 'Could not create upload directory'];
        }

        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = 'listing_' . $listingId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $file->move($destDir, $filename);

        // Is this the first image? If so, mark primary.
        $existingCount = DB::table($this->imageTable)->where('listing_id', $listingId)->count();
        $isPrimary = $existingCount === 0 ? 1 : 0;

        $id = $this->addListingImage($listingId, [
            'file_path'   => '/uploads/r/marketplace/' . $listingId . '/' . $filename,
            'file_name'   => $filename,
            'mime_type'   => $file->getClientMimeType(),
            'caption'     => $caption,
            'is_primary'  => $isPrimary,
            'sort_order'  => $sortOrder,
            'created_at'  => now(),
        ]);

        // Cache first primary image path on the listing for faster card rendering.
        if ($isPrimary) {
            DB::table($this->listingTable)
                ->where('id', $listingId)
                ->update(['featured_image_path' => '/uploads/r/marketplace/' . $listingId . '/' . $filename]);
        }

        return ['success' => true, 'image_id' => $id];
    }

    // =========================================================================
    //  SELLER HELPERS (Phase X.1.4)
    // =========================================================================

    public function getSellerById(int $id): ?object
    {
        return DB::table($this->sellerTable)->where('id', $id)->first();
    }

    public function getSellerPayouts(int $sellerId, int $limit = 50, int $offset = 0): array
    {
        // Matches PSIS TransactionRepository::getSellerPayouts signature.
        $query = DB::table($this->payoutTable)->where('seller_id', $sellerId);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get();
        return ['items' => $items, 'total' => (int) $total];
    }

    public function getSellerRecentTransactions(int $sellerId, int $limit = 5): \Illuminate\Support\Collection
    {
        return DB::table($this->transactionTable . ' as t')
            ->leftJoin($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->where('t.seller_id', $sellerId)
            ->orderByDesc('t.created_at')
            ->limit($limit)
            ->get(['t.*', 'l.title as listing_title', 'l.slug as listing_slug']);
    }

    public function getSellerReviews(int $sellerId, int $limit = 20, int $offset = 0): array
    {
        // Matches PSIS ReviewRepository::getSellerReviews — filters to buyer_to_seller only.
        $query = DB::table($this->reviewTable)
            ->where('reviewed_seller_id', $sellerId)
            ->where('review_type', 'buyer_to_seller')
            ->where('is_visible', 1);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => (int) $total];
    }

    public function getRatingStats(int $sellerId): array
    {
        // Matches PSIS ReviewRepository::getSellerRatingStats — filters to buyer_to_seller only.
        $base = DB::table($this->reviewTable)
            ->where('reviewed_seller_id', $sellerId)
            ->where('is_visible', 1)
            ->where('review_type', 'buyer_to_seller');

        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = (int) (clone $base)->where('rating', $i)->count();
        }

        return [
            'average' => (float) ((clone $base)->avg('rating') ?? 0),
            'count' => (int) (clone $base)->count(),
            'distribution' => $distribution,
        ];
    }

    public function getSellerCollections(int $sellerId): \Illuminate\Support\Collection
    {
        // Matches PSIS CollectionRepository::getSellerCollections — sort_order ASC only.
        return DB::table($this->collectionTable)
            ->where('seller_id', $sellerId)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    public function getSellerPublicCollections(int $sellerId): \Illuminate\Support\Collection
    {
        return DB::table($this->collectionTable)
            ->where('seller_id', $sellerId)
            ->where('is_public', 1)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();
    }

    public function getFollowedSellers(int $userId, int $limit = 50, int $offset = 0): array
    {
        // Matches PSIS SettingsRepository::getFollowedSellers — filters to active sellers only.
        $query = DB::table($this->followTable . ' as f')
            ->join($this->sellerTable . ' as s', 'f.seller_id', '=', 's.id')
            ->where('f.user_id', $userId)
            ->where('s.is_active', 1);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('f.created_at')
            ->offset($offset)
            ->limit($limit)
            ->get(['s.*', 'f.created_at as followed_at']);
        return ['items' => $items, 'total' => (int) $total];
    }

    public function uploadAvatar(int $sellerId, $file): ?string
    {
        return $this->uploadSellerAsset($sellerId, $file, 'avatar');
    }

    public function uploadBanner(int $sellerId, $file): ?string
    {
        return $this->uploadSellerAsset($sellerId, $file, 'banner');
    }

    protected function uploadSellerAsset(int $sellerId, $file, string $kind): ?string
    {
        if (!$file || !method_exists($file, 'isValid') || !$file->isValid()) {
            return null;
        }
        $uploadsBase = rtrim(config('heratio.uploads_path', storage_path('app/uploads')), '/');
        $destDir = $uploadsBase . '/marketplace/sellers/' . $sellerId;
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return null;
        }
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = $kind . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $file->move($destDir, $filename);
        $relPath = '/uploads/r/marketplace/sellers/' . $sellerId . '/' . $filename;
        DB::table($this->sellerTable)
            ->where('id', $sellerId)
            ->update([$kind . '_path' => $relPath, 'updated_at' => now()]);
        return $relPath;
    }

    // =========================================================================
    //  BUYER ACTIONS (Phase X.1.5)
    // =========================================================================

    public function createOffer(int $listingId, int $buyerId, float $amount, ?string $message = null): array
    {
        $listing = DB::table($this->listingTable)->where('id', $listingId)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }
        if (($listing->status ?? '') !== 'active') {
            return ['success' => false, 'error' => 'Listing is not available'];
        }
        if (($listing->listing_type ?? '') === 'auction') {
            return ['success' => false, 'error' => 'Cannot make offer on auction listing'];
        }
        if (!empty($listing->minimum_offer) && $amount < (float) $listing->minimum_offer) {
            return ['success' => false, 'error' => 'Offer must be at least ' . number_format((float) $listing->minimum_offer, 2)];
        }
        if ($this->hasPendingOffer($listingId, $buyerId)) {
            return ['success' => false, 'error' => 'You already have a pending offer on this listing'];
        }

        $expiryDays = (int) $this->getSetting('offer_expiry_days', 7);

        $id = DB::table($this->offerTable)->insertGetId([
            'listing_id' => $listingId,
            'buyer_id' => $buyerId,
            'status' => 'pending',
            'offer_amount' => $amount,
            'currency' => $listing->currency ?? config('heratio.base_currency', 'ZAR'),
            'message' => $message,
            'expires_at' => now()->addDays($expiryDays),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->listingTable)
            ->where('id', $listingId)
            ->update([
                'enquiry_count' => (int) ($listing->enquiry_count ?? 0) + 1,
                'updated_at' => now(),
            ]);

        return ['success' => true, 'id' => (int) $id];
    }

    protected function hasPendingOffer(int $listingId, int $buyerId): bool
    {
        return DB::table($this->offerTable)
            ->where('listing_id', $listingId)
            ->where('buyer_id', $buyerId)
            ->whereIn('status', ['pending', 'countered'])
            ->exists();
    }

    public function acceptCounterOffer(int $offerId, int $buyerId): array
    {
        $offer = DB::table($this->offerTable)->where('id', $offerId)->first();
        if (!$offer || (int) $offer->buyer_id !== $buyerId || $offer->status !== 'countered') {
            return ['success' => false, 'error' => 'Counter-offer cannot be accepted'];
        }
        if (empty($offer->counter_amount)) {
            return ['success' => false, 'error' => 'No counter amount on offer'];
        }

        DB::table($this->offerTable)
            ->where('id', $offerId)
            ->update([
                'status' => 'accepted',
                'offer_amount' => $offer->counter_amount,
                'responded_at' => now(),
                'updated_at' => now(),
            ]);

        // Reserve the listing (matches PSIS OfferService::acceptCounter)
        DB::table($this->listingTable)
            ->where('id', $offer->listing_id)
            ->update(['status' => 'reserved', 'updated_at' => now()]);

        return ['success' => true, 'price' => (float) $offer->counter_amount];
    }

    public function createEnquiry(array $data): int
    {
        $row = [
            'listing_id' => $data['listing_id'] ?? 0,
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'] ?? '',
            'status' => 'new',
            'created_at' => now(),
        ];
        return (int) DB::table($this->enquiryTable)->insertGetId($row);
    }

    public function replyToEnquiry(int $enquiryId, string $reply): array
    {
        $enquiry = DB::table($this->enquiryTable)->where('id', $enquiryId)->first();
        if (!$enquiry) {
            return ['success' => false, 'error' => 'Enquiry not found'];
        }
        DB::table($this->enquiryTable)
            ->where('id', $enquiryId)
            ->update([
                'reply' => $reply,
                'replied_by' => Auth::id(),
                'replied_at' => now(),
                'status' => 'replied',
            ]);
        return ['success' => true, 'enquiry_id' => $enquiryId];
    }

    public function createReview(int $transactionId, int $reviewerId, int $rating, ?string $title, ?string $comment = null, string $reviewType = 'buyer_to_seller'): array
    {
        $txn = DB::table($this->transactionTable)->where('id', $transactionId)->first();
        if (!$txn) {
            return ['success' => false, 'error' => 'Transaction not found'];
        }
        if (($txn->status ?? '') !== 'completed') {
            return ['success' => false, 'error' => 'Transaction must be completed before leaving a review'];
        }
        if ($this->hasReviewed($transactionId, $reviewerId)) {
            return ['success' => false, 'error' => 'You have already reviewed this transaction'];
        }
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => 'Rating must be between 1 and 5'];
        }

        $reviewedSellerId = $reviewType === 'buyer_to_seller' ? (int) $txn->seller_id : null;

        $id = DB::table($this->reviewTable)->insertGetId([
            'transaction_id' => $transactionId,
            'reviewer_id' => $reviewerId,
            'reviewed_seller_id' => $reviewedSellerId,
            'review_type' => $reviewType,
            'rating' => $rating,
            'title' => $title,
            'comment' => $comment,
            'is_visible' => 1,
            'flagged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($reviewedSellerId) {
            $this->recalculateSellerRating($reviewedSellerId);
        }
        return ['success' => true, 'id' => (int) $id];
    }

    public function hasReviewed(int $transactionId, int $reviewerId): bool
    {
        return DB::table($this->reviewTable)
            ->where('transaction_id', $transactionId)
            ->where('reviewer_id', $reviewerId)
            ->exists();
    }

    public function getReviewedMap($transactions, int $reviewerId): array
    {
        $ids = [];
        foreach ($transactions as $t) {
            if (!empty($t->id)) $ids[] = (int) $t->id;
        }
        if (empty($ids)) return [];
        $rows = DB::table($this->reviewTable)
            ->whereIn('transaction_id', $ids)
            ->where('reviewer_id', $reviewerId)
            ->pluck('transaction_id');
        $map = [];
        foreach ($rows as $txnId) $map[(int) $txnId] = true;
        return $map;
    }

    public function getBuyerOffers(int $buyerId, int $limit = 20, int $offset = 0): array
    {
        $query = DB::table($this->offerTable . ' as o')
            ->leftJoin($this->listingTable . ' as l', 'o.listing_id', '=', 'l.id')
            ->where('o.buyer_id', $buyerId);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('o.created_at')
            ->offset($offset)
            ->limit($limit)
            ->get(['o.*', 'l.title as listing_title', 'l.slug as listing_slug', 'l.featured_image_path as listing_image']);
        return ['items' => $items, 'total' => (int) $total];
    }

    public function getBuyerTransactions(int $buyerId, int $limit = 20, int $offset = 0): array
    {
        $query = DB::table($this->transactionTable . ' as t')
            ->leftJoin($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->leftJoin($this->sellerTable . ' as s', 't.seller_id', '=', 's.id')
            ->where('t.buyer_id', $buyerId);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('t.created_at')
            ->offset($offset)
            ->limit($limit)
            ->get([
                't.*',
                'l.title as listing_title',
                'l.slug as listing_slug',
                'l.featured_image_path as listing_image',
                's.display_name as seller_name',
                's.slug as seller_slug',
            ]);
        return ['items' => $items, 'total' => (int) $total];
    }

    public function getPendingOfferCount(int $sellerId): int
    {
        return (int) DB::table($this->offerTable . ' as o')
            ->join($this->listingTable . ' as l', 'o.listing_id', '=', 'l.id')
            ->where('l.seller_id', $sellerId)
            ->where('o.status', 'pending')
            ->count();
    }

    public function isFavourited(int $userId, int $listingId): bool
    {
        return DB::table($this->favouriteTable)
            ->where('user_id', $userId)
            ->where('listing_id', $listingId)
            ->exists();
    }

    // =========================================================================
    //  HERATIO-SPECIFIC HELPERS (Phase X.1.8)
    //
    //  These methods have NO PSIS equivalent. They exist only in Heratio
    //  because Heratio integrates with the GLAM information_object model
    //  (not present in PSIS's AtoM-plugin flavour) and uses Laravel's users
    //  table rather than AtoM's user table.
    // =========================================================================

    /**
     * Upload a cover image for a marketplace collection. Heratio-specific:
     * PSIS inlines cover uploads in the collection controller action instead
     * of delegating to a service helper.
     */
    public function uploadCollectionCover(int $sellerId, $file): ?string
    {
        if (!$file || !method_exists($file, 'isValid') || !$file->isValid()) {
            return null;
        }
        $uploadsBase = rtrim(config('heratio.uploads_path', storage_path('app/uploads')), '/');
        $destDir = $uploadsBase . '/marketplace/collections/' . $sellerId;
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return null;
        }
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = 'cover_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $file->move($destDir, $filename);
        return '/uploads/r/marketplace/collections/' . $sellerId . '/' . $filename;
    }

    /**
     * Pre-fill listing creation form from an archival information object.
     * Heratio-specific: PSIS does not integrate marketplace listings with
     * the GLAM information_object model — Heratio does.
     *
     * Returns an object with: information_object_id, title, description, slug.
     */
    public function getIOPrefillData(int $ioId): ?object
    {
        $row = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $ioId)
            ->select([
                'io.id as information_object_id',
                'i18n.title',
                'i18n.scope_and_content as description',
                's.slug',
            ])
            ->first();
        return $row ?: null;
    }

    /**
     * If a listing is linked to an IO and has no marketplace_listing_image rows,
     * create a primary image pointing at the IO's reference/master digital object.
     * Also populates featured_image_path on the listing. Idempotent — does nothing
     * if the listing already has at least one image, or no linked IO with a DO.
     *
     * Returns true if a default image was inserted, false otherwise.
     */
    public function defaultListingImageFromIo(int $listingId): bool
    {
        $listing = DB::table($this->listingTable)->where('id', $listingId)->first();
        if (!$listing || empty($listing->information_object_id)) {
            return false;
        }
        $hasImage = DB::table($this->imageTable)->where('listing_id', $listingId)->exists();
        if ($hasImage) {
            return false;
        }

        // Only auto-default with image-mime digital objects. PDFs / audio /
        // video master files would render as broken images on the marketplace.
        $imageOnly = function ($q) {
            $q->where('mime_type', 'like', 'image/%');
        };

        $cardDo = DB::table('digital_object')
            ->where('object_id', $listing->information_object_id)
            ->whereIn('usage_id', [141, 142, 140]) // reference > thumbnail > master
            ->where($imageOnly)
            ->orderByRaw("FIELD(usage_id, 141, 142, 140)")
            ->first(['id', 'name', 'mime_type', 'path']);

        $masterDo = DB::table('digital_object')
            ->where('object_id', $listing->information_object_id)
            ->where('usage_id', 140)
            ->where($imageOnly)
            ->first(['id', 'name', 'mime_type', 'path']);

        // Fallback 1: legacy AtoM derivatives may exist on disk without
        // matching digital_object rows (typical for migrated PDFs whose _141
        // and _142 jpgs were generated outside Heratio). Scan the IO's
        // storage dir, register any image we find as a thumbnail DO.
        if (!$cardDo) {
            $cardDo = $this->registerOrphanImageDerivative((int) $listing->information_object_id);
        }

        // Fallback 2: render PDF page 1 as a fresh JPG.
        if (!$cardDo) {
            $pdfMaster = DB::table('digital_object')
                ->where('object_id', $listing->information_object_id)
                ->where('usage_id', 140)
                ->where('mime_type', 'application/pdf')
                ->first(['id', 'name', 'path']);

            if ($pdfMaster) {
                $cardDo = $this->generatePdfPageOneThumbnail((int) $listing->information_object_id, $pdfMaster);
            }
        }

        if (!$cardDo) {
            return false; // no image, no PDF, nothing to default to — let the seller upload manually
        }

        $primaryDo = $masterDo ?: $cardDo;
        $webBase = $this->doWebBase($cardDo, (int) $listing->information_object_id);
        $primaryWebBase = $this->doWebBase($primaryDo, (int) $listing->information_object_id);

        DB::table($this->imageTable)->insert([
            'listing_id' => $listingId,
            'file_path'  => $primaryWebBase . $primaryDo->name,
            'file_name'  => $primaryDo->name,
            'mime_type'  => $primaryDo->mime_type ?? 'image/jpeg',
            'caption'    => 'Default image (from linked GLAM record)',
            'is_primary' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        DB::table($this->listingTable)->where('id', $listingId)->update([
            'featured_image_path' => $webBase . $cardDo->name,
            'updated_at'          => now(),
        ]);

        return true;
    }

    /**
     * Resolve a digital_object's web base URL. Honours the legacy AtoM hashed
     * `path` when set; otherwise falls back to the new gallery convention
     * /uploads/r/<io_id>/.
     */
    private function doWebBase(object $do, int $ioId): string
    {
        if (!empty($do->path)) {
            return rtrim((string) $do->path, '/') . '/';
        }
        return '/uploads/r/' . $ioId . '/';
    }

    /**
     * Resolve the on-disk directory for an IO, honouring both path conventions.
     * Returns null if nothing on disk matches.
     */
    private function ioDiskDir(int $ioId, ?object $referenceDo = null): ?string
    {
        $base = rtrim((string) config('heratio.uploads_path', ''), '/');
        if ($base === '') {
            return null;
        }

        // Legacy AtoM convention: digital_object.path holds /uploads/r/null/HASH/
        if ($referenceDo && !empty($referenceDo->path)) {
            $rel = ltrim((string) $referenceDo->path, '/');
            $rel = preg_replace('#^uploads/r/#', '', $rel);
            $candidate = $base . '/' . rtrim($rel, '/');
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        // New gallery convention: <uploads_path>/<io_id>/
        $candidate = $base . '/' . $ioId;
        return is_dir($candidate) ? $candidate : null;
    }

    /**
     * If image derivatives exist on disk for an IO but aren't registered as
     * digital_object rows (typical for AtoM-migrated PDFs whose _141/_142 jpgs
     * were generated outside Heratio), register the smallest one as a thumbnail
     * DO and return it.
     */
    private function registerOrphanImageDerivative(int $ioId): ?object
    {
        $sampleDo = DB::table('digital_object')
            ->where('object_id', $ioId)
            ->orderBy('usage_id')
            ->first(['path']);
        $dir = $this->ioDiskDir($ioId, $sampleDo);
        if (!$dir) {
            return null;
        }

        $candidates = [];
        foreach (glob($dir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE) ?: [] as $file) {
            $candidates[] = $file;
        }
        if (empty($candidates)) {
            return null;
        }

        // Prefer files containing _142 (thumbnail), then _141 (reference), then any.
        usort($candidates, function ($a, $b) {
            $rank = function ($n) {
                if (str_contains($n, '_142')) return 0;
                if (str_contains($n, '_141')) return 1;
                return 2;
            };
            return $rank($a) <=> $rank($b);
        });
        $pick = $candidates[0];
        $name = basename($pick);
        $webPath = $sampleDo && !empty($sampleDo->path)
            ? rtrim((string) $sampleDo->path, '/') . '/'
            : ('/uploads/r/' . $ioId . '/');

        $now = now();
        $doObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);
        DB::table('digital_object')->insert([
            'id' => $doObjectId,
            'object_id' => $ioId,
            'usage_id' => 142,
            'mime_type' => 'image/jpeg',
            'name' => $name,
            'path' => $webPath,
            'byte_size' => @filesize($pick) ?: null,
            'checksum' => @md5_file($pick) ?: null,
            'checksum_type' => 'md5',
            'parent_id' => null,
        ]);

        return (object) [
            'id'        => $doObjectId,
            'name'      => $name,
            'mime_type' => 'image/jpeg',
            'path'      => $webPath,
        ];
    }

    /**
     * Render page 1 of a PDF master into a JPG, persist it as a new image
     * digital_object (usage_id=142, thumbnail), and return a row matching the
     * shape used by defaultListingImageFromIo (id, name, mime_type).
     *
     * Requires `pdftoppm` (poppler-utils) on the host. Returns null on failure.
     */
    private function generatePdfPageOneThumbnail(int $ioId, object $pdfMaster): ?object
    {
        if (!is_executable('/usr/bin/pdftoppm')) {
            return null;
        }

        $sourceDir = $this->ioDiskDir($ioId, $pdfMaster);
        if (!$sourceDir) {
            return null;
        }
        $sourcePath = $sourceDir . '/' . $pdfMaster->name;
        if (!is_file($sourcePath)) {
            return null;
        }

        $stem = 'derived_pdfpage1_' . time();
        $outBase = $sourceDir . '/' . $stem;
        $outFile = $outBase . '.jpg';

        // pdftoppm -jpeg -f 1 -l 1 -r 150 INPUT OUTPUT_BASE  (writes OUTPUT_BASE-1.jpg or OUTPUT_BASE.jpg depending on version)
        $cmd = sprintf(
            '/usr/bin/pdftoppm -jpeg -f 1 -l 1 -singlefile -r 150 %s %s 2>&1',
            escapeshellarg($sourcePath),
            escapeshellarg($outBase)
        );
        @exec($cmd, $output, $rc);
        if ($rc !== 0 || !is_file($outFile)) {
            return null;
        }

        // Insert a new digital_object row so the linkage survives future calls.
        // Use the same web path convention as the master (legacy hashed or new).
        $webPath = !empty($pdfMaster->path)
            ? rtrim((string) $pdfMaster->path, '/') . '/'
            : ('/uploads/r/' . $ioId . '/');

        $now = now();
        $doObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);
        $name = basename($outFile);
        DB::table('digital_object')->insert([
            'id' => $doObjectId,
            'object_id' => $ioId,
            'usage_id' => 142, // thumbnail
            'mime_type' => 'image/jpeg',
            'name' => $name,
            'path' => $webPath,
            'byte_size' => @filesize($outFile) ?: null,
            'checksum' => @md5_file($outFile) ?: null,
            'checksum_type' => 'md5',
            'parent_id' => $pdfMaster->id,
        ]);

        return (object) [
            'id'        => $doObjectId,
            'name'      => $name,
            'mime_type' => 'image/jpeg',
            'path'      => $webPath,
        ];
    }

    /**
     * Find the marketplace listing linked to a given information object, if any.
     * Returns the most-recently-updated listing (a single IO could in theory be
     * relisted after withdrawal). Heratio-specific: PSIS has no GLAM linkage.
     */
    public function getListingByInformationObjectId(int $ioId): ?object
    {
        return DB::table('marketplace_listing')
            ->where('information_object_id', $ioId)
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Pre-fill enquiry/offer forms from the authenticated user's profile.
     * Heratio-specific: PSIS uses AtoM's `user` table which has a different
     * shape (authorized_form_of_name + email across joined tables).
     *
     * @return array{name: string, email: string}
     */
    public function getUserPrefillData(int $userId): array
    {
        $user = DB::table('users')->where('id', $userId)->first(['name', 'email']);
        return [
            'name'  => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
        ];
    }

    /**
     * Auto-provision a minimal seller profile for admin users who don't yet
     * have one, so the /dashboard page doesn't bounce them to registration.
     * Heratio-specific convenience: PSIS requires explicit seller registration.
     */
    public function autoProvisionAdminSeller(int $userId): ?object
    {
        $user = DB::table('users')->where('id', $userId)->first(['name', 'email']);
        if (!$user) {
            return null;
        }
        $displayName = (string) ($user->name ?: ('Admin #' . $userId));
        $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $displayName), '-'));
        if ($baseSlug === '') {
            $baseSlug = 'admin-' . $userId;
        }
        $slug = $baseSlug;
        $counter = 1;
        while (DB::table($this->sellerTable)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        $id = DB::table($this->sellerTable)->insertGetId([
            'seller_type'         => 'individual',
            'display_name'        => $displayName,
            'slug'                => $slug,
            'email'               => (string) ($user->email ?? ''),
            'commission_rate'     => 10.00,
            'payout_method'       => 'bank_transfer',
            'payout_currency'     => config('heratio.base_currency', 'ZAR'),
            'verification_status' => 'verified',
            'trust_level'         => 'trusted',
            'is_active'           => 1,
            'created_by'          => $userId,
            'terms_accepted_at'   => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        return DB::table($this->sellerTable)->where('id', $id)->first();
    }

    public function toggleFavourite(int $userId, int $listingId): array
    {
        $listing = DB::table($this->listingTable)->where('id', $listingId)->first();
        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }
        $exists = DB::table($this->favouriteTable)
            ->where('user_id', $userId)
            ->where('listing_id', $listingId)
            ->exists();
        if ($exists) {
            DB::table($this->favouriteTable)
                ->where('user_id', $userId)
                ->where('listing_id', $listingId)
                ->delete();
            DB::table($this->listingTable)
                ->where('id', $listingId)
                ->where('favourite_count', '>', 0)
                ->decrement('favourite_count');
            $favourited = false;
        } else {
            DB::table($this->favouriteTable)->insert([
                'user_id' => $userId,
                'listing_id' => $listingId,
                'created_at' => now(),
            ]);
            DB::table($this->listingTable)
                ->where('id', $listingId)
                ->increment('favourite_count');
            $favourited = true;
        }
        $count = (int) (DB::table($this->listingTable)->where('id', $listingId)->value('favourite_count') ?? 0);
        return ['success' => true, 'favourited' => $favourited, 'count' => $count];
    }

    // =========================================================================
    //  ADMIN BROWSE HELPERS (Phase X.1.1)
    //
    //  These methods back the marketplace admin list pages. Each returns an array
    //  with shape `['items' => Collection, 'total' => int]`. They honour the same
    //  filter conventions as `getListings()` but expose draft/suspended/all statuses
    //  since admins need to see every row regardless of lifecycle.
    // =========================================================================

    /**
     * Admin listings list — shows all statuses including drafts/suspended/withdrawn.
     *
     * @param array{status?:string, sector?:string, search?:string} $filters
     * @return array{items: \Illuminate\Support\Collection, total: int}
     */
    public function adminBrowseListings(array $filters = [], int $limit = 30, int $offset = 0): array
    {
        $q = DB::table($this->listingTable . ' as l')
            ->leftJoin($this->sellerTable . ' as s', 'l.seller_id', '=', 's.id')
            ->select(
                'l.*',
                's.display_name as seller_name',
                's.slug as seller_slug'
            );

        if (!empty($filters['status'])) {
            $q->where('l.status', $filters['status']);
        }
        if (!empty($filters['sector'])) {
            $q->where('l.sector', $filters['sector']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($term) {
                $w->where('l.title', 'like', $term)
                  ->orWhere('l.listing_number', 'like', $term)
                  ->orWhere('s.display_name', 'like', $term);
            });
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('l.created_at')->limit($limit)->offset($offset)->get();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Admin sellers list.
     *
     * @param array{verification_status?:string, search?:string} $filters
     * @return array{items: \Illuminate\Support\Collection, total: int}
     */
    public function adminBrowseSellers(array $filters = [], int $limit = 30, int $offset = 0): array
    {
        $q = DB::table($this->sellerTable . ' as s')
            ->select(
                's.*',
                DB::raw('(SELECT COUNT(*) FROM ' . $this->transactionTable . ' t WHERE t.seller_id = s.id AND t.status = "completed") as total_sales'),
                DB::raw('(SELECT COALESCE(SUM(seller_amount), 0) FROM ' . $this->transactionTable . ' t WHERE t.seller_id = s.id AND t.status = "completed") as total_revenue')
            );

        if (!empty($filters['verification_status'])) {
            $q->where('s.verification_status', $filters['verification_status']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($term) {
                $w->where('s.display_name', 'like', $term)
                  ->orWhere('s.email', 'like', $term)
                  ->orWhere('s.slug', 'like', $term);
            });
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('s.created_at')->limit($limit)->offset($offset)->get();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Admin transactions list.
     *
     * @param array{status?:string, payment_status?:string, search?:string} $filters
     * @return array{items: \Illuminate\Support\Collection, total: int}
     */
    public function adminBrowseTransactions(array $filters = [], int $limit = 30, int $offset = 0): array
    {
        $q = DB::table($this->transactionTable . ' as t')
            ->leftJoin($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->leftJoin($this->sellerTable . ' as s', 't.seller_id', '=', 's.id')
            ->leftJoin('user as b', 't.buyer_id', '=', 'b.id')
            ->select(
                't.*',
                'l.title',
                'l.slug as listing_slug',
                's.display_name as seller_name',
                DB::raw('COALESCE(b.username, CONCAT("Buyer #", t.buyer_id)) as buyer_name')
            );

        if (!empty($filters['status'])) {
            $q->where('t.status', $filters['status']);
        }
        if (!empty($filters['payment_status'])) {
            $q->where('t.payment_status', $filters['payment_status']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($term) {
                $w->where('t.transaction_number', 'like', $term)
                  ->orWhere('s.display_name', 'like', $term)
                  ->orWhere('l.title', 'like', $term);
            });
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('t.created_at')->limit($limit)->offset($offset)->get();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Admin payouts list.
     *
     * @param array{status?:string} $filters
     * @return array{items: \Illuminate\Support\Collection, total: int}
     */
    public function adminBrowsePayouts(array $filters = [], int $limit = 30, int $offset = 0): array
    {
        $q = DB::table($this->payoutTable . ' as p')
            ->leftJoin($this->sellerTable . ' as s', 'p.seller_id', '=', 's.id')
            ->select(
                'p.*',
                's.display_name as seller_name',
                's.slug as seller_slug'
            );

        if (!empty($filters['status'])) {
            $q->where('p.status', $filters['status']);
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('p.created_at')->limit($limit)->offset($offset)->get();

        return ['items' => $items, 'total' => $total];
    }

    // =========================================================================
    //  ADMIN DASHBOARD AGGREGATORS (Phase X.1.2)
    // =========================================================================

    /**
     * Top-level counts + alert badges for the marketplace admin dashboard.
     *
     * @return array<string,int|float>
     */
    public function getAdminDashboardStats(): array
    {
        return [
            'totalSellers'        => (int) DB::table($this->sellerTable)->count(),
            'totalListings'       => (int) DB::table($this->listingTable)->count(),
            'totalTransactions'   => (int) DB::table($this->transactionTable)->count(),
            'totalRevenue'        => (float) DB::table($this->transactionTable)
                                        ->where('status', 'completed')
                                        ->sum('grand_total'),
            'pendingListings'     => (int) DB::table($this->listingTable)
                                        ->where('status', 'pending_review')->count(),
            'unverifiedSellers'   => (int) DB::table($this->sellerTable)
                                        ->where('verification_status', 'unverified')->count(),
            'pendingPayoutsCount' => (int) DB::table($this->payoutTable)
                                        ->where('status', 'pending')->count(),
            'totalCommission'     => (float) DB::table($this->transactionTable)
                                        ->where('status', 'completed')
                                        ->sum('platform_commission_amount'),
        ];
    }

    /**
     * Most recent N completed-or-pending transactions with listing + seller joined.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAdminRecentTransactions(int $limit = 5): \Illuminate\Support\Collection
    {
        return DB::table($this->transactionTable . ' as t')
            ->leftJoin($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->leftJoin($this->sellerTable . ' as s', 't.seller_id', '=', 's.id')
            ->leftJoin('user as b', 't.buyer_id', '=', 'b.id')
            ->select(
                't.*',
                'l.title',
                'l.slug as listing_slug',
                's.display_name as seller_name',
                DB::raw('COALESCE(b.username, CONCAT("Buyer #", t.buyer_id)) as buyer_name')
            )
            ->orderByDesc('t.created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Count of sold listings grouped by sector — for the seller dashboard's sector pie.
     *
     * @return \Illuminate\Support\Collection each row has: sector, total
     */
    public function getSectorBreakdown(?int $sellerId = null): \Illuminate\Support\Collection
    {
        $q = DB::table($this->listingTable . ' as l')
            ->join($this->transactionTable . ' as t', 't.listing_id', '=', 'l.id')
            ->where('t.status', 'completed')
            ->select('l.sector', DB::raw('COUNT(*) as total'))
            ->groupBy('l.sector')
            ->orderByDesc('total');

        if ($sellerId !== null) {
            $q->where('l.seller_id', $sellerId);
        }

        return $q->get();
    }

    /**
     * Top items by completed-sale count (global or scoped to a seller).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTopItemsBySales(int $limit = 10): \Illuminate\Support\Collection
    {
        return DB::table($this->transactionTable . ' as t')
            ->join($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->where('t.status', 'completed')
            ->select(
                'l.id',
                'l.title',
                'l.slug',
                'l.featured_image_path',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(t.grand_total) as total_revenue'),
                't.currency'
            )
            ->groupBy('l.id', 'l.title', 'l.slug', 'l.featured_image_path', 't.currency')
            ->orderByDesc('sales_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Top listings by view_count for a specific seller.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTopListingsByViews(int $sellerId, int $limit = 10): \Illuminate\Support\Collection
    {
        return DB::table($this->listingTable)
            ->where('seller_id', $sellerId)
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'view_count', 'featured_image_path']);
    }

    /**
     * Top sellers by completed-sale revenue.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTopSellersByRevenue(int $limit = 10): \Illuminate\Support\Collection
    {
        return DB::table($this->transactionTable . ' as t')
            ->join($this->sellerTable . ' as s', 't.seller_id', '=', 's.id')
            ->where('t.status', 'completed')
            ->select(
                's.id',
                's.display_name',
                's.slug',
                's.avatar_path',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(t.grand_total) as total_revenue'),
                't.currency'
            )
            ->groupBy('s.id', 's.display_name', 's.slug', 's.avatar_path', 't.currency')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
    }

    /**
     * Top listings by completed-sale count for a specific seller.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTopSellingListings(int $sellerId, int $limit = 10): \Illuminate\Support\Collection
    {
        return DB::table($this->transactionTable . ' as t')
            ->join($this->listingTable . ' as l', 't.listing_id', '=', 'l.id')
            ->where('l.seller_id', $sellerId)
            ->where('t.status', 'completed')
            ->select(
                'l.id',
                'l.title',
                'l.slug',
                'l.featured_image_path',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(t.seller_amount) as total_earned'),
                't.currency'
            )
            ->groupBy('l.id', 'l.title', 'l.slug', 'l.featured_image_path', 't.currency')
            ->orderByDesc('sales_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Admin reviews moderation list.
     *
     * @param array{flagged?:int, is_visible?:int} $filters
     * @return array{items: \Illuminate\Support\Collection, total: int}
     */
    public function adminBrowseReviews(array $filters = [], int $limit = 30, int $offset = 0): array
    {
        $q = DB::table($this->reviewTable . ' as r')
            ->leftJoin($this->sellerTable . ' as s', 'r.reviewed_seller_id', '=', 's.id')
            ->leftJoin('user as u', 'r.reviewer_id', '=', 'u.id')
            ->select(
                'r.*',
                's.display_name as seller_name',
                DB::raw('COALESCE(u.username, CONCAT("Reviewer #", r.reviewer_id)) as reviewer_name'),
                'r.flagged as is_flagged'
            );

        if (!empty($filters['flagged'])) {
            $q->where('r.flagged', 1);
        }
        if (isset($filters['is_visible']) && $filters['is_visible'] !== '') {
            $q->where('r.is_visible', (int) $filters['is_visible']);
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('r.created_at')->limit($limit)->offset($offset)->get();

        return ['items' => $items, 'total' => $total];
    }
}
