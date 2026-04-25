<?php

/**
 * MarketplaceController - Controller for Heratio
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



namespace AhgMarketplace\Controllers;

use AhgMarketplace\Services\MarketplacePaymentService;
use AhgMarketplace\Services\MarketplaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceController extends Controller
{
    protected MarketplaceService $service;

    public function __construct(MarketplaceService $service)
    {
        $this->service = $service;
    }

    // ─── Helper: get authenticated user ID or redirect ────────────────
    private function requireAuth(Request $request): int
    {
        if (!Auth::check()) {
            session()->flash('error', 'Please log in to continue.');
            abort(redirect(route('login')));
        }

        return (int) Auth::id();
    }

    private function requireAdmin(): void
    {
        if (!Auth::check()) {
            abort(redirect(route('login')));
        }
        if (!Auth::user()->is_admin) {
            session()->flash('error', 'Admin access required.');
            abort(redirect(route('ahgmarketplace.browse')));
        }
    }

    private function requireSeller(int $userId): object
    {
        $seller = $this->service->getSellerByUserId($userId);

        if (!$seller) {
            abort(redirect(route('ahgmarketplace.seller-register')));
        }

        return $seller;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ADMIN METHODS
    // ═══════════════════════════════════════════════════════════════════

    public function adminDashboard(Request $request)
    {
        $this->requireAdmin();

        $stats = $this->service->getAdminDashboardStats();
        $recentTransactions = $this->service->getAdminRecentTransactions(5);
        $monthlyRevenue = $this->service->getMonthlyRevenue(null, 12);

        return view('marketplace::admin-dashboard', compact(
            'stats',
            'recentTransactions',
            'monthlyRevenue',
        ));
    }

    public function adminListings(Request $request)
    {
        $this->requireAdmin();

        $filters = [
            'status' => $request->input('status', ''),
            'sector' => $request->input('sector', ''),
            'search' => $request->input('search', ''),
            'include_all_statuses' => true,
        ];

        $page = max(1, (int) $request->input('page', 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $result = $this->service->adminBrowseListings($filters, $limit, $offset);

        return view('marketplace::admin-listings', [
            'listings' => $result['items'],
            'total' => $result['total'],
            'filters' => $filters,
            'page' => $page,
        ]);
    }

    public function adminListingReview(Request $request)
    {
        $this->requireAdmin();

        $listingId = (int) $request->input('id');
        if (!$listingId) {
            return redirect()->route('ahgmarketplace.admin-listings');
        }

        $listing = $this->service->getListingById($listingId);
        if (!$listing) {
            abort(404);
        }

        $seller = $this->service->getSellerById($listing->seller_id);
        $images = $this->service->getListingImages($listingId);

        return view('marketplace::admin-listing-review', compact('listing', 'seller', 'images'));
    }

    public function adminListingReviewPost(Request $request)
    {
        $this->requireAdmin();

        $request->validate([
            'id'          => 'required|integer|min:1',
            'form_action' => 'required|string|in:approve,reject,suspend',
        ]);

        $listingId = (int) $request->input('id');
        $formAction = $request->input('form_action');

        if ($formAction === 'approve') {
            $result = $this->service->approveListing($listingId);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Listing approved and now active.' : $result['error']
            );
        } elseif ($formAction === 'reject') {
            $result = $this->service->rejectListing($listingId);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Listing rejected and returned to draft.' : $result['error']
            );
        } elseif ($formAction === 'suspend') {
            $this->service->updateListingStatus($listingId, 'suspended');
            session()->flash('notice', 'Listing suspended.');
        }

        return redirect()->route('ahgmarketplace.admin-listings');
    }

    public function adminCategories(Request $request)
    {
        $this->requireAdmin();

        $categories = $this->service->getCategories(null, false);
        $sectors = ['gallery', 'museum', 'archive', 'library', 'dam'];

        return view('marketplace::admin-categories', compact('categories', 'sectors'));
    }

    public function adminCategoriesPost(Request $request)
    {
        $this->requireAdmin();

        $formAction = $request->input('form_action');

        if ($formAction === 'create') {
            $request->validate([
                'name' => 'required|string|max:255',
                'sector' => 'required|string|in:gallery,museum,archive,library,dam',
            ]);

            $this->service->createCategory([
                'name' => trim($request->input('name')),
                'slug' => strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $request->input('name')), '-')),
                'sector' => trim($request->input('sector')),
                'description' => trim($request->input('description', '')),
                'sort_order' => (int) $request->input('sort_order', 0),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            session()->flash('notice', 'Category created.');
        } elseif ($formAction === 'update') {
            $catId = (int) $request->input('category_id');
            if ($catId) {
                $name = trim($request->input('name', ''));
                $updateData = [
                    'name' => $name,
                    'sector' => trim($request->input('sector', '')),
                    'description' => trim($request->input('description', '')),
                    'sort_order' => (int) $request->input('sort_order', 0),
                    'is_active' => $request->input('is_active', 0) ? 1 : 0,
                    'updated_at' => now(),
                ];
                if (!empty($name)) {
                    $updateData['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                }
                $this->service->updateCategory($catId, $updateData);
                session()->flash('notice', 'Category updated.');
            }
        } elseif ($formAction === 'delete') {
            $catId = (int) $request->input('category_id');
            if ($catId) {
                $this->service->deleteCategory($catId);
                session()->flash('notice', 'Category deleted.');
            }
        }

        return redirect()->route('ahgmarketplace.admin-categories');
    }

    public function adminCurrencies(Request $request)
    {
        $this->requireAdmin();

        $currencies = $this->service->getCurrencies(false);

        return view('marketplace::admin-currencies', compact('currencies'));
    }

    public function adminCurrenciesPost(Request $request)
    {
        $this->requireAdmin();

        $formAction = $request->input('form_action');

        if ($formAction === 'update') {
            $code = trim($request->input('code', ''));
            $exchangeRate = (float) $request->input('exchange_rate_to_zar', 1);

            if (!empty($code) && $exchangeRate > 0) {
                $this->service->updateCurrency($code, ['exchange_rate_to_zar' => $exchangeRate]);
                session()->flash('notice', 'Exchange rate updated for ' . $code . '.');
            } else {
                session()->flash('error', 'Invalid currency code or exchange rate.');
            }
        } elseif ($formAction === 'add') {
            $request->validate([
                'code' => 'required|string|max:10',
                'name' => 'required|string|max:100',
            ]);

            $code = strtoupper(trim($request->input('code')));
            $existing = $this->service->getCurrency($code);

            if ($existing) {
                session()->flash('error', 'Currency ' . $code . ' already exists.');
            } else {
                $this->service->addCurrency([
                    'code' => $code,
                    'name' => trim($request->input('name')),
                    'symbol' => trim($request->input('symbol', '')),
                    'exchange_rate_to_zar' => (float) $request->input('exchange_rate_to_zar', 1),
                    'sort_order' => (int) $request->input('sort_order', 100),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                session()->flash('notice', 'Currency ' . $code . ' added.');
            }
        } elseif ($formAction === 'toggle') {
            $code = trim($request->input('code', ''));
            if (!empty($code)) {
                $currency = $this->service->getCurrency($code);
                if ($currency) {
                    $newStatus = $currency->is_active ? 0 : 1;
                    $this->service->updateCurrency($code, ['is_active' => $newStatus]);
                    $statusLabel = $newStatus ? 'activated' : 'deactivated';
                    session()->flash('notice', 'Currency ' . $code . ' ' . $statusLabel . '.');
                }
            }
        }

        return redirect()->route('ahgmarketplace.admin-currencies');
    }

    public function adminSellers(Request $request)
    {
        $this->requireAdmin();

        $filters = [
            'verification_status' => $request->input('verification_status', ''),
            'search' => $request->input('search', ''),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $result = $this->service->adminBrowseSellers($filters, $limit, $offset);

        return view('marketplace::admin-sellers', [
            'sellers' => $result['items'],
            'total' => $result['total'],
            'filters' => $filters,
            'page' => $page,
        ]);
    }

    public function adminSellerVerify(Request $request)
    {
        $this->requireAdmin();

        $sellerId = (int) $request->input('id');
        if (!$sellerId) {
            return redirect()->route('ahgmarketplace.admin-sellers');
        }

        $seller = $this->service->getSellerById($sellerId);
        if (!$seller) {
            abort(404);
        }

        return view('marketplace::admin-seller-verify', compact('seller'));
    }

    public function adminSellerVerifyPost(Request $request)
    {
        $this->requireAdmin();

        $request->validate([
            'id'          => 'required|integer|min:1',
            'form_action' => 'required|string|in:verify,suspend',
        ]);

        $sellerId = (int) $request->input('id');
        $formAction = $request->input('form_action');

        if ($formAction === 'verify') {
            $result = $this->service->verifySeller($sellerId);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Seller verified successfully.' : 'Failed to verify seller.'
            );
        } elseif ($formAction === 'suspend') {
            $result = $this->service->suspendSeller($sellerId);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Seller suspended.' : 'Failed to suspend seller.'
            );
        }

        return redirect()->route('ahgmarketplace.admin-sellers');
    }

    public function adminTransactions(Request $request)
    {
        $this->requireAdmin();

        $filters = [
            'status' => $request->input('status', ''),
            'payment_status' => $request->input('payment_status', ''),
            'search' => $request->input('search', ''),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $result = $this->service->adminBrowseTransactions($filters, $limit, $offset);

        return view('marketplace::admin-transactions', [
            'transactions' => $result['items'],
            'total' => $result['total'],
            'filters' => $filters,
            'page' => $page,
        ]);
    }

    public function adminPayouts(Request $request)
    {
        $this->requireAdmin();

        $statusFilter = $request->input('status', '');
        $page = max(1, (int) $request->input('page', 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $filters = [];
        if (!empty($statusFilter)) {
            $filters['status'] = $statusFilter;
        }

        $result = $this->service->adminBrowsePayouts($filters, $limit, $offset);

        return view('marketplace::admin-payouts', [
            'payouts' => $result['items'],
            'total' => $result['total'],
            'statusFilter' => $statusFilter,
            'page' => $page,
        ]);
    }

    public function adminPayoutsBatchPost(Request $request)
    {
        $this->requireAdmin();

        $request->validate([
            'payout_ids'   => 'required|array|min:1',
            'payout_ids.*' => 'integer|min:1',
        ]);

        $selectedIds = $request->input('payout_ids', []);
        $ids = array_filter(array_map('intval', $selectedIds), fn ($id) => $id > 0);
        if (empty($ids)) {
            session()->flash('error', 'No valid payouts selected.');

            return redirect()->route('ahgmarketplace.admin-payouts');
        }

        $adminUserId = (int) Auth::id();
        $result = $this->service->batchProcessPayouts($ids, $adminUserId);

        $message = sprintf(
            'Batch processing complete: %d processed, %d skipped.',
            $result['processed'],
            $result['skipped']
        );

        if (!empty($result['errors'])) {
            $errorMessages = [];
            foreach ($result['errors'] as $err) {
                $errorMessages[] = 'Payout #' . $err['payout_id'] . ': ' . $err['error'];
            }
            $message .= ' Errors: ' . implode('; ', $errorMessages);
        }

        session()->flash($result['processed'] > 0 ? 'notice' : 'error', $message);

        return redirect()->route('ahgmarketplace.admin-payouts');
    }

    public function adminReports(Request $request)
    {
        $this->requireAdmin();

        $revenueStats = $this->service->getRevenueStats();
        $monthlyRevenue = $this->service->getMonthlyRevenue(null, 12);
        $topSellers = $this->service->getTopSellersByRevenue(10);
        $topItems = $this->service->getTopItemsBySales(10);

        return view('marketplace::admin-reports', compact(
            'revenueStats',
            'monthlyRevenue',
            'topSellers',
            'topItems',
        ));
    }

    public function adminReviews(Request $request)
    {
        $this->requireAdmin();

        $filters = [
            'flagged' => $request->input('flagged', ''),
            'is_visible' => $request->input('is_visible', ''),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $repoFilters = [];
        if (!empty($filters['flagged'])) {
            $repoFilters['flagged'] = 1;
        }
        if ($filters['is_visible'] !== '') {
            $repoFilters['is_visible'] = (int) $filters['is_visible'];
        }

        $result = $this->service->adminBrowseReviews($repoFilters, $limit, $offset);

        return view('marketplace::admin-reviews', [
            'reviews' => $result['items'],
            'total' => $result['total'],
            'filters' => $filters,
            'page' => $page,
        ]);
    }

    public function adminReviewsPost(Request $request)
    {
        $this->requireAdmin();

        $request->validate([
            'form_action' => 'required|string|in:moderate',
            'review_id'   => 'required|integer|min:1',
            'is_visible'  => 'nullable|boolean',
        ]);

        $formAction = $request->input('form_action');

        if ($formAction === 'moderate') {
            $reviewId = (int) $request->input('review_id');
            $visible = (bool) $request->input('is_visible', 0);

            if ($reviewId) {
                $result = $this->service->moderateReview($reviewId, $visible);
                session()->flash(
                    $result['success'] ? 'notice' : 'error',
                    $result['success'] ? 'Review moderated successfully.' : $result['error']
                );
            }
        }

        return redirect()->route('ahgmarketplace.admin-reviews');
    }

    public function adminSettings(Request $request)
    {
        $this->requireAdmin();

        $settings = $this->service->getAllSettings();

        return view('marketplace::admin-settings', compact('settings'));
    }

    public function adminSettingsPost(Request $request)
    {
        $this->requireAdmin();

        // No validate() call: the input keys are dynamic (`setting_<key>`) and
        // types depend on each row's `setting_type`. Coercion happens inside
        // the loop below. Admin-gated + CSRF-protected at the middleware layer.
        $settings = $this->service->getAllSettings();

        foreach ($settings as $setting) {
            $key = $setting->setting_key;
            $newValue = $request->input('setting_' . $key);

            if ($newValue !== null) {
                if ($setting->setting_type === 'boolean') {
                    $this->service->setSetting($key, (bool) $newValue, 'boolean', $setting->setting_group);
                } else {
                    $this->service->setSetting($key, $newValue, $setting->setting_type, $setting->setting_group);
                }
            } elseif ($setting->setting_type === 'boolean') {
                $this->service->setSetting($key, false, 'boolean', $setting->setting_group);
            }
        }

        session()->flash('notice', 'Settings updated.');

        return redirect()->route('ahgmarketplace.admin-settings');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PUBLIC BROWSE METHODS
    // ═══════════════════════════════════════════════════════════════════

    public function browse(Request $request)
    {
        $filters = $this->service->buildSearchFilters([
            'sector' => $request->input('sector'),
            'category_id' => $request->input('category_id'),
            'listing_type' => $request->input('listing_type'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'condition_rating' => $request->input('condition_rating'),
            'medium' => $request->input('medium'),
            'country' => $request->input('country'),
            'is_digital' => $request->input('is_digital'),
            'sort' => $request->input('sort'),
        ]);

        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;

        $results = $this->service->getListings($filters, $page, $limit);
        $facets = $this->service->getFacetCounts($filters);

        $sectorFilter = !empty($filters['sector']) ? $filters['sector'] : null;
        $categories = method_exists($this->service, 'getCategories')
            ? $this->service->getCategories($sectorFilter)
            : collect();
        $sectors = ['gallery', 'museum', 'archive', 'library', 'dam'];

        return view('marketplace::browse', [
            'listings' => $results['items'],
            'total' => $results['total'],
            'filters' => $filters,
            'facets' => $facets,
            'page' => $page,
            'limit' => $limit,
            'sectors' => $sectors,
            'categories' => $categories,
        ]);
    }

    public function search(Request $request)
    {
        $query = trim($request->input('query', ''));

        $filters = $this->service->buildSearchFilters([
            'sector' => $request->input('sector'),
            'category_id' => $request->input('category_id'),
            'listing_type' => $request->input('listing_type'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'condition_rating' => $request->input('condition_rating'),
            'medium' => $request->input('medium'),
            'country' => $request->input('country'),
            'is_digital' => $request->input('is_digital'),
            'sort' => $request->input('sort'),
        ]);

        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;

        // Use getListings() with the query folded into filters; the service does not expose a
        // dedicated full-text search method yet, so free-text matching is done via the `q` filter
        // inside buildSearchFilters() / applyListingFilters().
        if (!empty($query)) {
            $filters['q'] = $query;
        }

        $results = $this->service->getListings($filters, $page, $limit);
        $facets = $this->service->getFacetCounts($filters);

        return view('marketplace::search', [
            'results' => $results['items'],
            'total' => $results['total'],
            'query' => $query,
            'filters' => $filters,
            'facets' => $facets,
            'page' => $page,
        ]);
    }

    public function listing(Request $request)
    {
        $slug = $request->input('slug');
        if (empty($slug)) {
            return redirect()->route('ahgmarketplace.browse');
        }

        $listing = $this->service->getListingBySlug($slug);
        if (!$listing) {
            abort(404);
        }

        $seller = $this->service->getSellerById($listing->seller_id);
        $images = $this->service->getListingImages($listing->id);

        // Auction details
        $auction = null;
        if ($listing->listing_type === 'auction') {
            $auction = $this->service->getAuctionForListing($listing->id);
        }

        $currencies = $this->service->getCurrencies();

        // User-specific checks
        $isFollowing = false;
        $isFavourited = false;
        if (Auth::check()) {
            $userId = (int) Auth::id();
            $isFollowing = $this->service->isFollowing($userId, $listing->seller_id);
            $isFavourited = $this->service->isFavourited($userId, $listing->id);
        }

        // Related listings
        $relatedListings = $this->service->getRelatedListings($listing, 4);

        return view('marketplace::listing', compact(
            'listing',
            'seller',
            'images',
            'auction',
            'currencies',
            'isFollowing',
            'isFavourited',
            'relatedListings',
        ));
    }

    public function category(Request $request)
    {
        $sector = $request->input('sector');
        $slug = $request->input('slug');

        if (empty($sector) || empty($slug)) {
            return redirect()->route('ahgmarketplace.browse');
        }

        $validSectors = ['gallery', 'museum', 'archive', 'library', 'dam'];
        if (!in_array($sector, $validSectors, true)) {
            abort(404);
        }

        $category = $this->service->getCategoryBySlug($sector, $slug);
        if (!$category) {
            abort(404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;
        $sort = $request->input('sort', 'newest');

        $results = $this->service->getListings(
            ['sector' => $sector, 'category_id' => $category->id, 'sort' => $sort],
            $page,
            $limit
        );

        return view('marketplace::category', [
            'category' => $category,
            'sector' => $sector,
            'listings' => $results['items'],
            'total' => $results['total'],
            'page' => $page,
        ]);
    }

    public function sector(Request $request)
    {
        $sector = $request->input('sector');
        $validSectors = ['gallery', 'museum', 'archive', 'library', 'dam'];

        // No sector selected → redirect to the browse page which has sector filters.
        if (empty($sector)) {
            return redirect()->route('ahgmarketplace.browse');
        }
        if (!in_array($sector, $validSectors, true)) {
            abort(404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;
        $sort = $request->input('sort', 'newest');

        $results = $this->service->getListings(['sector' => $sector, 'sort' => $sort], $page, $limit);
        $categories = $this->service->getCategories($sector);

        return view('marketplace::sector', [
            'sector' => $sector,
            'listings' => $results['items'],
            'total' => $results['total'],
            'categories' => $categories,
            'page' => $page,
        ]);
    }

    public function featured(Request $request)
    {
        $featuredListings = $this->service->getFeaturedListings(12);
        $featuredCollections = $this->service->getFeaturedCollections(6);

        return view('marketplace::featured', compact('featuredListings', 'featuredCollections'));
    }

    public function auctionBrowse(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $results = $this->service->getActiveAuctions([], $limit, $offset);
        $endingSoon = $this->service->getAuctionsEndingSoon(60);

        return view('marketplace::auction-browse', [
            'auctions' => $results['items'],
            'total' => $results['total'],
            'endingSoon' => $endingSoon,
            'page' => $page,
        ]);
    }

    public function collection(Request $request)
    {
        $slug = $request->input('slug');
        if (empty($slug)) {
            return redirect()->route('ahgmarketplace.browse');
        }

        $data = $this->service->getCollection($slug);
        if (!$data) {
            abort(404);
        }

        // Only show public collections to visitors unless owner
        if (!$data['collection']->is_public) {
            $allowed = false;
            if (Auth::check()) {
                $createdBy = $data['collection']->created_by ?? null;
                if ($createdBy && $createdBy == Auth::id()) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                abort(404);
            }
        }

        return view('marketplace::collection', [
            'collection' => $data['collection'],
            'items' => $data['items'],
        ]);
    }

    public function seller(Request $request)
    {
        $slug = $request->input('slug');
        if (empty($slug)) {
            return redirect()->route('ahgmarketplace.browse');
        }

        $seller = $this->service->getSellerBySlug($slug);
        if (!$seller) {
            abort(404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;

        $listingsResult = $this->service->getListings(['seller_id' => $seller->id, 'sort' => 'newest'], $page, $limit);
        $reviewsResult = $this->service->getSellerReviews($seller->id, 10, 0);
        $ratingStats = $this->service->getRatingStats($seller->id);
        $collections = $this->service->getSellerPublicCollections($seller->id);
        $followerCount = $this->service->getFollowerCount($seller->id);

        $isFollowing = false;
        if (Auth::check()) {
            $isFollowing = $this->service->isFollowing((int) Auth::id(), $seller->id);
        }

        return view('marketplace::seller', [
            'seller' => $seller,
            'listings' => $listingsResult['items'],
            'total' => $listingsResult['total'],
            'page' => $page,
            'reviews' => $reviewsResult['items'],
            'reviewCount' => $reviewsResult['total'],
            'ratingStats' => $ratingStats,
            'collections' => $collections,
            'followerCount' => $followerCount,
            'isFollowing' => $isFollowing,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  BUYER / USER METHODS
    // ═══════════════════════════════════════════════════════════════════

    public function dashboard(Request $request)
    {
        $userId = $this->requireAuth($request);

        $seller = $this->service->getSellerByUserId($userId);

        // Auto-provision seller profile for admins
        if (!$seller && Auth::user()->is_admin) {
            $seller = $this->service->autoProvisionAdminSeller($userId);
        }

        if (!$seller) {
            return redirect()->route('ahgmarketplace.seller-register');
        }

        $stats = $this->service->getDashboardStats($seller->id);
        $recentTransactions = $this->service->getSellerRecentTransactions($seller->id, 5);
        $pendingOfferCount = $this->service->getPendingOfferCount($seller->id);

        $recentListings = $this->service->getSellerListings($seller->id, [], 10, 0);
        $recentOffers = DB::table('marketplace_offer as o')
            ->join('marketplace_listing as l', 'l.id', '=', 'o.listing_id')
            ->where('l.seller_id', $seller->id)
            ->orderByDesc('o.created_at')
            ->limit(5)
            ->select('o.id', 'o.offer_amount', 'o.currency', 'o.status as offer_status', 'o.created_at', 'l.title as listing_title', 'l.slug as listing_slug')
            ->get();

        return view('marketplace::dashboard', [
            'seller' => $seller,
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'pendingOfferCount' => $pendingOfferCount,
            'recentListings' => $recentListings['items'],
            'recentListingsTotal' => $recentListings['total'],
            'recentOffers' => $recentOffers,
        ]);
    }

    public function bidForm(Request $request)
    {
        $userId = $this->requireAuth($request);

        $slug = $request->input('slug');
        if (empty($slug)) {
            return redirect()->route('ahgmarketplace.browse');
        }

        $listing = $this->service->getListingBySlug($slug);
        if (!$listing) {
            abort(404);
        }

        if ($listing->listing_type !== 'auction') {
            session()->flash('error', 'This listing is not an auction.');

            return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
        }

        $auction = $this->service->getAuctionForListing($listing->id);
        if (!$auction) {
            session()->flash('error', 'Auction not found for this listing.');

            return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
        }

        if ($auction->status !== 'active') {
            session()->flash('error', 'This auction is not currently active.');

            return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
        }

        if (strtotime($auction->end_time) <= time()) {
            session()->flash('error', 'This auction has ended.');

            return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
        }

        $images = $this->service->getListingImages($listing->id);
        $primaryImage = $this->service->getPrimaryImage($images);

        $currentBid = $auction->current_bid ?? $auction->starting_bid;
        $minBid = (float) $currentBid + (float) ($auction->bid_increment ?? 1);

        $bidHistory = $this->service->getBidHistory($auction->id, 5);

        return view('marketplace::bid-form', compact(
            'listing',
            'auction',
            'primaryImage',
            'currentBid',
            'minBid',
            'bidHistory',
        ));
    }

    public function bidFormPost(Request $request)
    {
        $userId = $this->requireAuth($request);

        $slug = $request->input('slug');
        $request->validate([
            'bid_amount' => 'required|numeric|min:0.01',
        ]);

        $bidAmount = (float) $request->input('bid_amount');
        $maxBid = $request->input('max_bid') ? (float) $request->input('max_bid') : null;

        $auction = $this->service->getAuctionForListingBySlug($slug);
        if (!$auction) {
            session()->flash('error', 'Auction not found.');

            return redirect()->route('ahgmarketplace.browse');
        }

        $result = $this->service->placeBid($auction->id, $userId, $bidAmount, $maxBid);

        if ($result['success']) {
            session()->flash('notice', 'Your bid has been placed successfully.');

            return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
        }

        session()->flash('error', $result['error']);

        return redirect()->route('ahgmarketplace.bid-form', ['slug' => $slug]);
    }

    public function enquiryForm(Request $request)
    {
        $guestEnquiriesEnabled = (bool) $this->service->getSetting('guest_enquiries_enabled', true);

        if (!$guestEnquiriesEnabled && !Auth::check()) {
            session()->flash('error', 'Please log in to send an enquiry.');

            return redirect()->route('login');
        }

        $slug = $request->input('slug');
        if (empty($slug)) {
            return redirect()->route('ahgmarketplace.browse');
        }

        $listing = $this->service->getListingBySlug($slug);
        if (!$listing) {
            abort(404);
        }

        $images = $this->service->getListingImages($listing->id);
        $primaryImage = $this->service->getPrimaryImage($images);

        // Pre-fill if authenticated
        $prefillName = '';
        $prefillEmail = '';
        if (Auth::check()) {
            $prefillData = $this->service->getUserPrefillData((int) Auth::id());
            $prefillName = $prefillData['name'];
            $prefillEmail = $prefillData['email'];
        }

        return view('marketplace::enquiry-form', compact(
            'listing',
            'primaryImage',
            'prefillName',
            'prefillEmail',
            'guestEnquiriesEnabled',
        ));
    }

    public function enquiryFormPost(Request $request)
    {
        $guestEnquiriesEnabled = (bool) $this->service->getSetting('guest_enquiries_enabled', true);

        if (!$guestEnquiriesEnabled && !Auth::check()) {
            session()->flash('error', 'Please log in to send an enquiry.');

            return redirect()->route('login');
        }

        $slug = $request->input('slug');

        $request->validate([
            'enquiry_name' => 'required|string|max:255',
            'enquiry_email' => 'required|email|max:255',
            'enquiry_subject' => 'required|string|max:255',
            'enquiry_message' => 'required|string',
        ]);

        $listing = $this->service->getListingBySlug($slug);
        if (!$listing) {
            abort(404);
        }

        $this->service->createEnquiry([
            'listing_id' => $listing->id,
            'user_id' => Auth::check() ? (int) Auth::id() : null,
            'name' => trim($request->input('enquiry_name')),
            'email' => trim($request->input('enquiry_email')),
            'phone' => trim($request->input('enquiry_phone', '')) ?: null,
            'subject' => trim($request->input('enquiry_subject')),
            'message' => trim($request->input('enquiry_message')),
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session()->flash('notice', 'Your enquiry has been sent successfully. The seller will respond to you by email.');

        return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
    }

    public function offerForm(Request $request)
    {
        $userId = $this->requireAuth($request);

        $slug = $request->input('slug');
        if (empty($slug)) {
            return redirect()->route('ahgmarketplace.browse');
        }

        $listing = $this->service->getListingBySlug($slug);
        if (!$listing) {
            abort(404);
        }

        if ($listing->status !== 'active') {
            session()->flash('error', 'This listing is no longer available.');

            return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
        }

        if ($listing->listing_type === 'auction') {
            session()->flash('error', 'Cannot make offers on auction listings. Please place a bid instead.');

            return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
        }

        $images = $this->service->getListingImages($listing->id);
        $primaryImage = $this->service->getPrimaryImage($images);
        $currencies = $this->service->getCurrencies();

        return view('marketplace::offer-form', compact('listing', 'primaryImage', 'currencies'));
    }

    public function offerFormPost(Request $request)
    {
        $userId = $this->requireAuth($request);

        $slug = $request->input('slug');

        $request->validate([
            'offer_amount' => 'required|numeric|min:0.01',
        ]);

        $listing = $this->service->getListingBySlug($slug);
        if (!$listing) {
            abort(404);
        }

        $amount = (float) $request->input('offer_amount');
        $message = trim($request->input('message', ''));

        $result = $this->service->createOffer($listing->id, $userId, $amount, $message ?: null);

        if ($result['success']) {
            session()->flash('notice', 'Your offer has been submitted successfully.');

            return redirect()->route('ahgmarketplace.my-offers');
        }

        session()->flash('error', $result['error']);

        return redirect()->route('ahgmarketplace.offer-form', ['slug' => $slug]);
    }

    public function reviewForm(Request $request)
    {
        $userId = $this->requireAuth($request);

        $txnId = (int) $request->input('id');
        if (!$txnId) {
            return redirect()->route('ahgmarketplace.my-purchases');
        }

        $transaction = $this->service->getTransaction($txnId);
        if (!$transaction) {
            session()->flash('error', 'Transaction not found.');

            return redirect()->route('ahgmarketplace.my-purchases');
        }

        if ((int) $transaction->buyer_id !== $userId) {
            session()->flash('error', 'You do not have permission to review this transaction.');

            return redirect()->route('ahgmarketplace.my-purchases');
        }

        if ($transaction->status !== 'completed') {
            session()->flash('error', 'Transaction must be completed before leaving a review.');

            return redirect()->route('ahgmarketplace.my-purchases');
        }

        if ($this->service->hasReviewed($txnId, $userId)) {
            session()->flash('error', 'You have already reviewed this transaction.');

            return redirect()->route('ahgmarketplace.my-purchases');
        }

        return view('marketplace::review-form', compact('transaction'));
    }

    public function reviewFormPost(Request $request)
    {
        $userId = $this->requireAuth($request);

        $txnId = (int) $request->input('id');

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review_title' => 'required|string|max:255',
        ]);

        $rating = (int) $request->input('rating');
        $title = trim($request->input('review_title'));
        $comment = trim($request->input('review_comment', ''));

        $result = $this->service->createReview(
            $txnId,
            $userId,
            $rating,
            $title,
            $comment ?: null,
            'buyer_to_seller'
        );

        if ($result['success']) {
            session()->flash('notice', 'Thank you for your review!');

            return redirect()->route('ahgmarketplace.my-purchases');
        }

        session()->flash('error', $result['error']);

        return redirect()->route('ahgmarketplace.review-form', ['id' => $txnId]);
    }

    public function myBids(Request $request)
    {
        $userId = $this->requireAuth($request);

        $page = max(1, (int) $request->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $result = $this->service->getUserBids($userId, $limit, $offset);

        // Auctions the user has won — surfaced separately so they can pay
        $wonAuctions = DB::table('marketplace_auction as a')
            ->join('marketplace_listing as l', 'l.id', '=', 'a.listing_id')
            ->leftJoin('marketplace_transaction as t', function ($j) use ($userId) {
                $j->on('t.auction_id', '=', 'a.id')->where('t.buyer_id', '=', $userId);
            })
            ->where('a.winner_id', $userId)
            ->select(
                'a.id as auction_id', 'a.winning_bid', 'a.end_time', 'a.status',
                'l.id as listing_id', 'l.title', 'l.slug', 'l.currency', 'l.featured_image_path',
                't.id as transaction_id', 't.transaction_number', 't.payment_status'
            )
            ->orderByDesc('a.end_time')
            ->get();

        return view('marketplace::my-bids', [
            'bids' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'wonAuctions' => $wonAuctions,
        ]);
    }

    public function myFollowing(Request $request)
    {
        $userId = $this->requireAuth($request);

        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $result = $this->service->getFollowedSellers($userId, $limit, $offset);

        return view('marketplace::my-following', [
            'sellers' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Phase X.3 — POST handlers cloned from PSIS marketplace actions
    // ─────────────────────────────────────────────────────────────────

    /**
     * Cloned from PSIS marketplaceBuyAction::execute.
     * Initiates purchase for a fixed-price or auction buy-now listing.
     */
    public function buy(Request $request)
    {
        $userId = $this->requireAuth($request);
        $slug = (string) $request->input('slug', '');
        if ($slug === '') {
            return redirect()->route('ahgmarketplace.browse');
        }

        $listing = $this->service->getListingBySlug($slug);
        if (!$listing) {
            abort(404);
        }
        if (($listing->status ?? '') !== 'active') {
            session()->flash('error', 'This listing is no longer available.');
            return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
        }
        if (($listing->listing_type ?? '') === 'offer_only') {
            session()->flash('error', 'This listing only accepts offers.');
            return redirect()->route('ahgmarketplace.offer-form', ['slug' => $slug]);
        }

        if (($listing->listing_type ?? '') === 'auction') {
            $auction = $this->service->getAuctionForListing((int) $listing->id);
            if (!$auction || empty($auction->buy_now_price)) {
                session()->flash('error', 'Buy Now is not available for this auction.');
                return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
            }
            $buyResult = $this->service->buyNow((int) $auction->id, $userId);
            if (empty($buyResult['success'])) {
                session()->flash('error', $buyResult['error'] ?? 'Unable to complete Buy Now.');
                return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
            }
            $result = $this->service->createTransaction([
                'source'     => 'auction',
                'auction_id' => (int) $auction->id,
                'buyer_id'   => $userId,
            ]);
        } else {
            $result = $this->service->createTransaction([
                'source'     => 'fixed_price',
                'listing_id' => (int) $listing->id,
                'buyer_id'   => $userId,
            ]);
        }

        if (empty($result['success'])) {
            session()->flash('error', $result['error'] ?? 'Unable to complete purchase.');
            return redirect()->route('ahgmarketplace.listing', ['slug' => $slug]);
        }

        $txnNumber = $result['transaction']->transaction_number
            ?? ($result['transaction_id'] ?? '');
        session()->flash('notice', 'Purchase initiated. Transaction #' . $txnNumber . ' created.');
        return redirect()->route('ahgmarketplace.my-purchases');
    }

    /**
     * Cloned from PSIS marketplaceFollowAction::execute.
     * Toggles the authenticated user's follow state for a seller.
     * Returns JSON for XHR, redirects otherwise.
     */
    public function follow(Request $request)
    {
        $userId = $this->requireAuth($request);

        $request->validate([
            'seller' => 'required|string|max:255',
        ]);

        $sellerSlug = (string) $request->input('seller', '');
        $seller = $sellerSlug !== '' ? $this->service->getSellerBySlug($sellerSlug) : null;

        if (!$seller) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Seller not found'], 404);
            }
            session()->flash('error', 'Seller not found.');
            return redirect()->route('ahgmarketplace.browse');
        }

        $followed = $this->service->toggleFollow($userId, (int) $seller->id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'followed' => $followed,
            ]);
        }

        session()->flash(
            'notice',
            $followed ? 'You are now following this seller.' : 'You have unfollowed this seller.'
        );
        return redirect()->route('ahgmarketplace.seller', ['slug' => $sellerSlug]);
    }

    /**
     * Cloned from PSIS marketplaceSellerListingPublishAction::execute.
     */
    public function sellerListingPublish(Request $request)
    {
        $userId = $this->requireAuth($request);

        $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $seller = $this->service->getSellerByUserId($userId);
        if (!$seller) {
            return redirect()->route('ahgmarketplace.seller-register');
        }

        $listingId = (int) $request->input('id');
        if ($listingId <= 0) {
            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $listing = $this->service->getListingById($listingId);
        if (!$listing) {
            abort(404);
        }
        if ((int) $listing->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to publish this listing.');
            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $result = $this->service->publishListing($listingId);
        if (!empty($result['success'])) {
            $msg = (($result['status'] ?? '') === 'pending_review')
                ? 'Listing submitted for review. It will be active once approved.'
                : 'Listing is now active.';
            session()->flash('notice', $msg);
        } else {
            session()->flash('error', $result['error'] ?? 'Unable to publish listing.');
        }
        return redirect()->route('ahgmarketplace.seller-listings');
    }

    /**
     * Cloned from PSIS marketplaceSellerListingWithdrawAction::execute.
     */
    public function sellerListingWithdraw(Request $request)
    {
        $userId = $this->requireAuth($request);

        $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $seller = $this->service->getSellerByUserId($userId);
        if (!$seller) {
            return redirect()->route('ahgmarketplace.seller-register');
        }

        $listingId = (int) $request->input('id');
        if ($listingId <= 0) {
            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $listing = $this->service->getListingById($listingId);
        if (!$listing) {
            abort(404);
        }
        if ((int) $listing->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to withdraw this listing.');
            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $result = $this->service->withdrawListing($listingId);
        if (!empty($result['success'])) {
            session()->flash('notice', 'Listing has been withdrawn.');
        } else {
            session()->flash('error', $result['error'] ?? 'Unable to withdraw listing.');
        }
        return redirect()->route('ahgmarketplace.seller-listings');
    }

    public function myOffers(Request $request)
    {
        $userId = $this->requireAuth($request);

        $page = max(1, (int) $request->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $result = $this->service->getBuyerOffers($userId, $limit, $offset);

        return view('marketplace::my-offers', [
            'offers' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    public function myOffersPost(Request $request)
    {
        $userId = $this->requireAuth($request);

        $request->validate([
            'form_action' => 'required|string|in:accept_counter,withdraw',
            'offer_id'    => 'required|integer|min:1',
        ]);

        $formAction = $request->input('form_action');
        $offerId = (int) $request->input('offer_id');

        if ($formAction === 'accept_counter') {
            $result = $this->service->acceptCounterOffer($offerId, $userId);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Counter-offer accepted. A transaction has been created.' : $result['error']
            );
        } elseif ($formAction === 'withdraw') {
            $result = $this->service->withdrawOffer($offerId, $userId);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Offer withdrawn successfully.' : $result['error']
            );
        }

        return redirect()->route('ahgmarketplace.my-offers');
    }

    public function myPurchases(Request $request)
    {
        $userId = $this->requireAuth($request);

        $page = max(1, (int) $request->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $result = $this->service->getBuyerTransactions($userId, $limit, $offset);
        $reviewedMap = $this->service->getReviewedMap($result['items'], $userId);

        return view('marketplace::my-purchases', [
            'transactions' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'reviewedMap' => $reviewedMap,
        ]);
    }

    public function myPurchasesPost(Request $request)
    {
        $userId = $this->requireAuth($request);

        $request->validate([
            'form_action'    => 'required|string|in:confirm_receipt',
            'transaction_id' => 'required|integer|min:1',
        ]);

        if ($request->input('form_action') === 'confirm_receipt') {
            $txnId = (int) $request->input('transaction_id');
            $result = $this->service->confirmReceipt($txnId, $userId);

            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Receipt confirmed. Thank you!' : $result['error']
            );
        }

        return redirect()->route('ahgmarketplace.my-purchases');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SELLER METHODS
    // ═══════════════════════════════════════════════════════════════════

    public function sellerRegister(Request $request)
    {
        $userId = $this->requireAuth($request);

        $existing = $this->service->getSellerByUserId($userId);
        if ($existing) {
            return redirect()->route('ahgmarketplace.dashboard');
        }

        $sectors = ['gallery', 'museum', 'archive', 'library', 'dam'];

        return view('marketplace::seller-register', compact('sectors'));
    }

    /**
     * Unified marketplace registration landing — lets a user pick whether they
     * want to register as a buyer (no extra signup beyond their Heratio
     * account) or as a seller (full seller profile).
     */
    public function register(Request $request)
    {
        $userId = Auth::id();
        $existingSeller = $userId ? $this->service->getSellerByUserId($userId) : null;

        return view('marketplace::register', [
            'isAuthenticated' => (bool) $userId,
            'existingSeller'  => $existingSeller,
        ]);
    }

    /**
     * Buyer-side "register" entry: there's no separate buyer table — any
     * authenticated user can buy, place offers, and bid. This route ensures
     * the user is signed in (auth middleware), flashes a confirmation, and
     * sends them to the marketplace browse page.
     */
    public function buyerStart(Request $request)
    {
        $this->requireAuth($request);
        session()->flash('notice', "You're all set. Browse the marketplace below — your Heratio account is your buyer account.");
        return redirect()->route('ahgmarketplace.browse');
    }

    // =========================================================================
    //  PAYMENT (PayFast) — buy-now, auction-win, ITN, return URLs
    // =========================================================================

    /**
     * Buy-now flow. Creates a transaction in pending_payment state, then
     * redirects the buyer to PayFast's process URL.
     */
    public function checkoutBuy(Request $request, int $listingId)
    {
        $userId = $this->requireAuth($request);

        $listing = $this->service->getListingById($listingId);
        if (!$listing) {
            abort(404);
        }
        if (!in_array($listing->status, ['active', 'published'], true)) {
            session()->flash('error', 'This listing is no longer available.');
            return redirect()->route('ahgmarketplace.listing', ['slug' => $listing->slug]);
        }
        if ($listing->listing_type === 'offer_only') {
            session()->flash('error', 'This listing only accepts offers.');
            return redirect()->route('ahgmarketplace.offer-form', ['slug' => $listing->slug]);
        }
        if ($listing->seller_id && (int) $listing->seller_id === $this->getSellerIdForUser($userId)) {
            session()->flash('error', 'You cannot buy your own listing.');
            return redirect()->route('ahgmarketplace.listing', ['slug' => $listing->slug]);
        }

        $result = $this->service->createTransaction([
            'source'     => 'fixed_price',
            'listing_id' => (int) $listing->id,
            'buyer_id'   => $userId,
        ]);

        if (empty($result['success'])) {
            session()->flash('error', $result['error'] ?? 'Unable to start checkout.');
            return redirect()->route('ahgmarketplace.listing', ['slug' => $listing->slug]);
        }

        return $this->redirectToPayFast($result, $listing, $userId);
    }

    /**
     * Auction-win flow. The auction's winner_id pays for the lot they won.
     */
    public function checkoutWin(Request $request, int $auctionId)
    {
        $userId = $this->requireAuth($request);

        $auction = DB::table('marketplace_auction')->where('id', $auctionId)->first();
        if (!$auction) {
            abort(404);
        }
        if ((int) ($auction->winner_id ?? 0) !== (int) $userId) {
            session()->flash('error', 'Only the auction winner can pay for this lot.');
            return redirect()->route('ahgmarketplace.my-bids');
        }

        $listing = $this->service->getListingById((int) $auction->listing_id);
        if (!$listing) {
            abort(404);
        }

        // Idempotent: reuse an existing pending transaction if one was already created
        $existing = DB::table('marketplace_transaction')
            ->where('auction_id', $auctionId)
            ->where('buyer_id', $userId)
            ->whereIn('payment_status', ['pending', 'pending_payment'])
            ->first();

        if ($existing) {
            return $this->redirectToPayFast(
                ['transaction_id' => $existing->id, 'transaction' => $existing],
                $listing,
                $userId
            );
        }

        $result = $this->service->createTransaction([
            'source'     => 'auction',
            'auction_id' => $auctionId,
            'buyer_id'   => $userId,
        ]);

        if (empty($result['success'])) {
            session()->flash('error', $result['error'] ?? 'Unable to start checkout.');
            return redirect()->route('ahgmarketplace.my-bids');
        }

        return $this->redirectToPayFast($result, $listing, $userId);
    }

    /**
     * PayFast ITN webhook (server-to-server). No CSRF, no auth — verified
     * via PayFast's signature + source-IP + server-to-server validate.
     */
    public function payfastNotify(Request $request, MarketplacePaymentService $payments)
    {
        $payload = $request->all();
        $sourceIp = $request->ip();
        $mPaymentId = (string) ($payload['m_payment_id'] ?? '');
        $isCart = str_starts_with($mPaymentId, 'CART-');

        // Always log raw payloads for audit
        DB::table('ahg_payment_notifications')->insert([
            'gateway'    => 'payfast-marketplace',
            'payload'    => json_encode($payload),
            'status'     => $payload['payment_status'] ?? 'unknown',
            'order_id'   => $mPaymentId,
            'created_at' => now(),
        ]);

        if ($isCart) {
            return $this->handleCartItn($payload, $sourceIp, $mPaymentId, $payments);
        }

        $txn = $mPaymentId ? $this->service->getTransactionByNumber($mPaymentId) : null;
        if (!$txn) {
            Log::warning('[PayFast ITN] unknown transaction', ['txn_number' => $mPaymentId]);
            return response('OK', 200);
        }

        if (!$payments->verifyItn($payload, $sourceIp, $txn)) {
            Log::warning('[PayFast ITN] verification failed', ['txn' => $mPaymentId, 'ip' => $sourceIp]);
            return response('OK', 200);
        }

        $status = strtoupper((string) ($payload['payment_status'] ?? ''));
        if ($status === 'COMPLETE') {
            $this->service->markTransactionPaid(
                (int) $txn->id,
                'payfast',
                (string) ($payload['pf_payment_id'] ?? ''),
                $payload
            );
        } elseif (in_array($status, ['FAILED', 'CANCELLED'], true)) {
            DB::table('marketplace_transaction')->where('id', $txn->id)->update([
                'payment_status' => 'failed',
                'gateway_response' => json_encode($payload),
                'updated_at' => now(),
            ]);
        }

        return response('OK', 200);
    }

    /**
     * ITN for a cart-mode payment (m_payment_id = CART-YYYYMMDD-NNNN).
     * Verifies the signature/IP/server-validate against the SUM of grand_totals
     * for transactions sharing the cart_group_id, then marks all of them paid
     * (or failed) and clears the cart rows.
     */
    private function handleCartItn(array $payload, string $sourceIp, string $cartGroupId, MarketplacePaymentService $payments): \Illuminate\Http\Response
    {
        $txns = DB::table('marketplace_transaction')->where('cart_group_id', $cartGroupId)->get();
        if ($txns->isEmpty()) {
            Log::warning('[PayFast ITN cart] unknown cart_group_id', ['group' => $cartGroupId]);
            return response('OK', 200);
        }

        $grandTotal = (float) $txns->sum('grand_total');
        $synthetic = (object) ['grand_total' => $grandTotal];
        if (!$payments->verifyItn($payload, $sourceIp, $synthetic)) {
            Log::warning('[PayFast ITN cart] verification failed', ['group' => $cartGroupId, 'ip' => $sourceIp]);
            return response('OK', 200);
        }

        $status = strtoupper((string) ($payload['payment_status'] ?? ''));
        if ($status === 'COMPLETE') {
            $buyerId = (int) ($txns->first()->buyer_id ?? 0);
            foreach ($txns as $t) {
                $this->service->markTransactionPaid(
                    (int) $t->id,
                    'payfast',
                    (string) ($payload['pf_payment_id'] ?? ''),
                    $payload
                );
            }
            // Clear the cart rows for these listings now that they're paid.
            $listingIds = $txns->pluck('listing_id')->filter()->all();
            if (!empty($listingIds) && $buyerId) {
                DB::table('cart')
                    ->where('user_id', $buyerId)
                    ->where('kind', 'marketplace')
                    ->whereIn('listing_id', $listingIds)
                    ->update(['completed_at' => now()]);
            }
        } elseif (in_array($status, ['FAILED', 'CANCELLED'], true)) {
            DB::table('marketplace_transaction')
                ->where('cart_group_id', $cartGroupId)
                ->update([
                    'payment_status' => 'failed',
                    'gateway_response' => json_encode($payload),
                    'updated_at' => now(),
                ]);
        }

        return response('OK', 200);
    }

    /** Buyer is bounced back to this URL after a successful payment. */
    public function paymentReturn(Request $request)
    {
        $key = (string) $request->input('txn');
        return view('marketplace::payment-return', $this->resolveReturnContext($key, false));
    }

    /** Buyer cancelled at PayFast — return to listing with a retry option. */
    public function paymentCancel(Request $request)
    {
        $key = (string) $request->input('txn');
        $ctx = $this->resolveReturnContext($key, true);

        // Mark pending transactions as cancelled
        if (str_starts_with($key, 'CART-')) {
            DB::table('marketplace_transaction')
                ->where('cart_group_id', $key)
                ->whereIn('payment_status', ['pending', 'pending_payment'])
                ->update(['payment_status' => 'cancelled', 'updated_at' => now()]);
        } elseif (!empty($ctx['transaction']) && in_array($ctx['transaction']->payment_status, ['pending', 'pending_payment'], true)) {
            DB::table('marketplace_transaction')->where('id', $ctx['transaction']->id)->update([
                'payment_status' => 'cancelled',
                'updated_at' => now(),
            ]);
        }
        return view('marketplace::payment-return', $ctx);
    }

    /**
     * Resolve $request['txn'] to a view context for both single and cart payments.
     */
    private function resolveReturnContext(string $key, bool $cancelled): array
    {
        if (str_starts_with($key, 'CART-')) {
            $txns = DB::table('marketplace_transaction')->where('cart_group_id', $key)->get();
            $grandTotal = (float) $txns->sum('grand_total');
            $allPaid = $txns->isNotEmpty() && $txns->every(fn ($t) => $t->payment_status === 'paid');
            $synthetic = (object) [
                'transaction_number' => $key,
                'grand_total'        => $grandTotal,
                'currency'           => $txns->first()->currency ?? 'ZAR',
                'payment_status'     => $allPaid ? 'paid' : ($cancelled ? 'cancelled' : 'pending'),
            ];
            return [
                'transaction' => $synthetic,
                'success'     => $allPaid && !$cancelled,
                'cancelled'   => $cancelled,
                'cartCount'   => $txns->count(),
            ];
        }

        $txn = $this->service->getTransactionByNumber($key);
        return [
            'transaction' => $txn,
            'success'     => $txn && $txn->payment_status === 'paid' && !$cancelled,
            'cancelled'   => $cancelled,
            'cartCount'   => 0,
        ];
    }

    /**
     * Redirect helper used by both buy-now and auction-win flows.
     */
    private function redirectToPayFast(array $result, object $listing, int $userId): \Illuminate\Http\RedirectResponse
    {
        $payments = app(MarketplacePaymentService::class);
        $txn = $result['transaction'] ?? $this->service->getTransaction((int) $result['transaction_id']);
        if (!$txn) {
            session()->flash('error', 'Transaction not found after creation.');
            return redirect()->route('ahgmarketplace.listing', ['slug' => $listing->slug]);
        }

        $userRow = DB::table('users')->where('id', $userId)->first(['name', 'email']);
        $name = $userRow->name ?? 'Buyer';
        $email = $userRow->email ?? 'buyer@example.com';

        try {
            $url = $payments->buildProcessUrl($txn, $listing, $name, $email);
        } catch (\Throwable $e) {
            Log::error('[PayFast] buildProcessUrl failed', ['err' => $e->getMessage()]);
            session()->flash('error', 'Payment gateway is not configured: ' . $e->getMessage());
            return redirect()->route('ahgmarketplace.listing', ['slug' => $listing->slug]);
        }

        return redirect()->away($url);
    }

    private function getSellerIdForUser(int $userId): int
    {
        $seller = $this->service->getSellerByUserId($userId);
        return $seller ? (int) $seller->id : 0;
    }

    // =========================================================================
    //  RESERVATIONS — 12-hour holds, max 2 per user per 24h
    // =========================================================================

    public function reserveListing(Request $request, int $listingId)
    {
        $userId = $this->requireAuth($request);

        $result = $this->service->reserveListing($listingId, $userId);

        if (!empty($result['success'])) {
            $expiresAt = $result['expires_at'] instanceof \DateTimeInterface
                ? \Carbon\Carbon::instance($result['expires_at'])
                : \Carbon\Carbon::parse((string) $result['expires_at']);
            session()->flash('notice', sprintf(
                'Reserved for 12 hours. Hold expires at %s — Buy Now to complete the purchase.',
                $expiresAt->format('Y-m-d H:i')
            ));
        } else {
            session()->flash('error', $result['error'] ?? 'Could not reserve this listing.');
        }

        $listing = $this->service->getListingById($listingId);
        return redirect()->route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']);
    }

    public function cancelReservation(Request $request, int $reservationId)
    {
        $userId = $this->requireAuth($request);
        $ok = $this->service->cancelReservation($reservationId, $userId);
        session()->flash($ok ? 'notice' : 'error', $ok ? 'Reservation cancelled.' : 'Could not cancel that reservation.');
        return redirect()->back();
    }

    /**
     * /marketplace/my-licences — buyer's licence agreements (active + expired).
     */
    public function myLicences(Request $request)
    {
        $userId = $this->requireAuth($request);
        $this->service->expireOldLicences();
        $licences = $this->service->getLicencesForBuyer($userId);
        return view('marketplace::my-licences', compact('licences'));
    }

    // =========================================================================
    //  BROKER — manage artists the seller represents
    // =========================================================================

    public function sellerArtists(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $artists = $this->service->getArtistsForSeller((int) $seller->id);
        $isBroker = $this->service->isBrokerSeller($seller);

        return view('marketplace::seller-artists', compact('seller', 'artists', 'isBroker'));
    }

    public function sellerArtistCreate(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);
        $artist = null;
        return view('marketplace::seller-artist-edit', compact('seller', 'artist'));
    }

    public function sellerArtistCreatePost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'display_name'         => 'required|string|max:255',
            'bio'                  => 'nullable|string|max:5000',
            'birth_year'           => 'nullable|integer|min:1000|max:2200',
            'death_year'           => 'nullable|integer|min:1000|max:2200',
            'nationality'          => 'nullable|string|max:100',
            'contact_email'        => 'nullable|email|max:255',
            'contact_phone'        => 'nullable|string|max:50',
            'website'              => 'nullable|url|max:255',
            'default_markup_type'  => 'nullable|string|in:percentage,fixed,none',
            'default_markup_value' => 'nullable|numeric|min:0',
            'default_commission_split' => 'nullable|numeric|min:0|max:100',
            'notes'                => 'nullable|string|max:2000',
        ]);

        $result = $this->service->createArtist((int) $seller->id, $request->only([
            'display_name', 'bio', 'birth_year', 'death_year', 'nationality',
            'contact_email', 'contact_phone', 'website',
            'default_markup_type', 'default_markup_value', 'default_commission_split',
            'notes',
        ]));

        if (!empty($result['success'])) {
            session()->flash('notice', "Artist '{$request->input('display_name')}' added.");
            return redirect()->route('ahgmarketplace.seller-artists');
        }
        session()->flash('error', $result['error'] ?? 'Failed to add artist.');
        return redirect()->route('ahgmarketplace.seller-artist-create');
    }

    public function sellerArtistEdit(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $artistId = (int) $request->input('id');
        $artist = $this->service->getArtistById($artistId);
        if (!$artist || (int) $artist->seller_id !== (int) $seller->id) {
            abort(404);
        }
        return view('marketplace::seller-artist-edit', compact('seller', 'artist'));
    }

    public function sellerArtistEditPost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'id'                   => 'required|integer|min:1',
            'display_name'         => 'required|string|max:255',
            'bio'                  => 'nullable|string|max:5000',
            'birth_year'           => 'nullable|integer|min:1000|max:2200',
            'death_year'           => 'nullable|integer|min:1000|max:2200',
            'nationality'          => 'nullable|string|max:100',
            'contact_email'        => 'nullable|email|max:255',
            'contact_phone'        => 'nullable|string|max:50',
            'website'              => 'nullable|url|max:255',
            'default_markup_type'  => 'nullable|string|in:percentage,fixed,none',
            'default_markup_value' => 'nullable|numeric|min:0',
            'default_commission_split' => 'nullable|numeric|min:0|max:100',
            'notes'                => 'nullable|string|max:2000',
            'status'               => 'nullable|string|in:active,inactive',
        ]);

        $artistId = (int) $request->input('id');
        $artist = $this->service->getArtistById($artistId);
        if (!$artist || (int) $artist->seller_id !== (int) $seller->id) {
            abort(404);
        }

        $result = $this->service->updateArtist($artistId, $request->only([
            'display_name', 'bio', 'birth_year', 'death_year', 'nationality',
            'contact_email', 'contact_phone', 'website',
            'default_markup_type', 'default_markup_value', 'default_commission_split',
            'notes', 'status',
        ]));

        session()->flash($result['success'] ? 'notice' : 'error', $result['success'] ? 'Artist updated.' : ($result['error'] ?? 'Failed to update artist.'));
        return redirect()->route('ahgmarketplace.seller-artists');
    }

    public function sellerArtistDelete(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $artistId = (int) $request->input('id');
        $artist = $this->service->getArtistById($artistId);
        if (!$artist || (int) $artist->seller_id !== (int) $seller->id) {
            abort(404);
        }

        $result = $this->service->deleteArtist($artistId);
        session()->flash($result['success'] ? 'notice' : 'error', $result['success'] ? 'Artist removed.' : ($result['error'] ?? 'Failed.'));
        return redirect()->route('ahgmarketplace.seller-artists');
    }

    public function sellerRegisterPost(Request $request)
    {
        $userId = $this->requireAuth($request);

        $existing = $this->service->getSellerByUserId($userId);
        if ($existing) {
            return redirect()->route('ahgmarketplace.dashboard');
        }

        $request->validate([
            'display_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'accept_terms' => 'required|accepted',
        ]);

        $data = [
            'display_name' => trim($request->input('display_name')),
            'seller_type' => $request->input('seller_type', 'artist'),
            'email' => trim($request->input('email')),
            'bio' => trim($request->input('bio', '')),
            'country' => trim($request->input('country', '')),
            'city' => trim($request->input('city', '')),
            'website' => trim($request->input('website', '')),
            'instagram' => trim($request->input('instagram', '')),
            'phone' => trim($request->input('phone', '')),
        ];

        $selectedSectors = $request->input('sectors');
        if (is_array($selectedSectors) && !empty($selectedSectors)) {
            $data['sectors'] = $selectedSectors;
        }

        $result = $this->service->registerSeller($userId, $data);

        if ($result['success']) {
            session()->flash('notice', 'Welcome! Your seller profile has been created.');

            return redirect()->route('ahgmarketplace.dashboard');
        }

        session()->flash('error', $result['error']);

        return redirect()->route('ahgmarketplace.seller-register');
    }

    public function sellerProfile(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $currencies = $this->service->getCurrencies();

        return view('marketplace::seller-profile', compact('seller', 'currencies'));
    }

    public function sellerProfilePost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'display_name'    => 'required|string|max:255',
            'seller_type'     => 'nullable|string|max:53',
            'bio'             => 'nullable|string|max:5000',
            'country'         => 'nullable|string|max:100',
            'city'            => 'nullable|string|max:100',
            'website'         => 'nullable|url|max:255',
            'instagram'       => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'payout_method'   => 'nullable|string|max:45',
            'payout_currency' => 'nullable|string|size:3',
            'sectors'         => 'nullable|array',
            'sectors.*'       => 'string|max:50',
            'avatar'          => 'nullable|image|max:5120',
            'banner'          => 'nullable|image|max:10240',
        ]);

        $data = [
            'display_name' => trim($request->input('display_name', '')),
            'seller_type' => $request->input('seller_type', $seller->seller_type),
            'bio' => trim($request->input('bio', '')),
            'country' => trim($request->input('country', '')),
            'city' => trim($request->input('city', '')),
            'website' => trim($request->input('website', '')),
            'instagram' => trim($request->input('instagram', '')),
            'email' => trim($request->input('email', '')),
            'phone' => trim($request->input('phone', '')),
            'payout_method' => $request->input('payout_method', 'bank_transfer'),
            'payout_currency' => $request->input('payout_currency', 'ZAR'),
            'notify_on_reservation'        => $request->boolean('notify_on_reservation') ? 1 : 0,
            'notify_reservation_reminders' => $request->boolean('notify_reservation_reminders') ? 1 : 0,
            'notify_on_reservation_expiry' => $request->boolean('notify_on_reservation_expiry') ? 1 : 0,
        ];

        $selectedSectors = $request->input('sectors');
        if (is_array($selectedSectors)) {
            $data['sectors'] = $selectedSectors;
        }

        // Handle avatar upload
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $data['avatar_path'] = $this->service->uploadAvatar($seller->id, $request->file('avatar'));
        }

        // Handle banner upload
        if ($request->hasFile('banner') && $request->file('banner')->isValid()) {
            $data['banner_path'] = $this->service->uploadBanner($seller->id, $request->file('banner'));
        }

        $result = $this->service->updateSellerProfile($seller->id, $data);

        if ($result['success']) {
            session()->flash('notice', 'Profile updated successfully.');
        } else {
            session()->flash('error', $result['error']);
        }

        return redirect()->route('ahgmarketplace.seller-profile');
    }

    public function sellerListings(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $statusFilter = $request->input('status', '');
        $page = max(1, (int) $request->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $filters = [];
        if (!empty($statusFilter)) {
            $filters['status'] = $statusFilter;
        }

        $result = $this->service->getSellerListings($seller->id, $filters, $limit, $offset);

        return view('marketplace::seller-listings', [
            'seller' => $seller,
            'listings' => $result['items'],
            'total' => $result['total'],
            'statusFilter' => $statusFilter,
            'page' => $page,
        ]);
    }

    public function sellerListingCreate(Request $request)
    {
        $userId = $this->requireAuth($request);

        $seller = $this->service->getSellerByUserId($userId);
        if (!$seller && Auth::user()->is_admin) {
            return redirect()->route('ahgmarketplace.dashboard');
        }
        if (!$seller) {
            return redirect()->route('ahgmarketplace.seller-register');
        }

        $sectors = ['gallery', 'museum', 'archive', 'library', 'dam'];
        $categories = $this->service->getCategories();
        $currencies = $this->service->getCurrencies();

        // Pre-fill from information object if ?io= parameter provided
        $prefill = null;
        $ioId = (int) $request->input('io', 0);
        if ($ioId > 0) {
            $prefill = $this->service->getIOPrefillData($ioId);
        }

        $brokerArtists = $this->service->isBrokerSeller($seller)
            ? $this->service->getArtistsForSeller((int) $seller->id, true)
            : collect();
        $licenceTypes = $this->service->getLicenceTypes();

        return view('marketplace::seller-listing-create', compact(
            'seller',
            'sectors',
            'categories',
            'currencies',
            'prefill',
            'brokerArtists',
            'licenceTypes',
        ));
    }

    public function sellerListingCreatePost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'title' => 'required|string|max:255',
            'sector' => 'required|string|in:gallery,museum,archive,library,dam',
            'listing_type' => 'required|string|in:fixed_price,auction,offer_only,licence',
        ]);

        // Broker mode — when an artist is selected, compute price from base + markup
        $artistId = (int) $request->input('artist_id', 0) ?: null;
        $artistBasePrice = $request->filled('artist_base_price') ? (float) $request->input('artist_base_price') : null;
        $markupType = $request->input('markup_type') ?: null;
        $markupValue = $request->filled('markup_value') ? (float) $request->input('markup_value') : null;

        $artistNameFromBroker = null;
        if ($artistId) {
            $artist = $this->service->getArtistById($artistId);
            if (!$artist || (int) $artist->seller_id !== (int) $seller->id) {
                session()->flash('error', 'Selected artist is not in your roster.');
                return redirect()->route('ahgmarketplace.seller-listing-create');
            }
            $artistNameFromBroker = $artist->display_name;
            // Apply artist defaults if the seller didn't override
            if (!$markupType) { $markupType = $artist->default_markup_type; }
            if ($markupValue === null) { $markupValue = (float) $artist->default_markup_value; }
        }

        $rawPrice = $request->filled('price') ? (float) $request->input('price') : null;
        $finalPrice = $artistBasePrice !== null
            ? $this->service->computePriceFromMarkup($artistBasePrice, $markupType, $markupValue)
            : $rawPrice;

        $data = [
            'title' => trim($request->input('title')),
            'sector' => $request->input('sector'),
            'listing_type' => $request->input('listing_type'),
            'information_object_id' => $request->input('information_object_id') ?: null,
            'category_id' => $request->input('category_id') ?: null,
            'description' => trim($request->input('description', '')),
            'price' => $finalPrice,
            'currency' => $request->input('currency', 'ZAR'),
            'minimum_offer' => $request->input('minimum_offer') ? (float) $request->input('minimum_offer') : null,
            'artist_id' => $artistId,
            'artist_name' => $artistNameFromBroker ?: trim($request->input('artist_name', '')),
            'artist_base_price' => $artistBasePrice,
            'markup_type' => $artistBasePrice !== null ? $markupType : null,
            'markup_value' => $artistBasePrice !== null ? $markupValue : null,
            'medium' => trim($request->input('medium', '')),
            'dimensions' => trim($request->input('dimensions', '')),
            'year_created' => trim($request->input('year_created', '')),
            'provenance' => trim($request->input('provenance', '')),
            'condition_rating' => $request->input('condition_rating') ?: null,
            'condition_description' => trim($request->input('condition_description', '')),
            'is_digital' => $request->input('is_digital') ? 1 : 0,
            'requires_shipping' => $request->input('requires_shipping') ? 1 : 0,
            'shipping_from_country' => trim($request->input('shipping_from_country', '')),
            'shipping_domestic_price' => $request->input('shipping_domestic_price') ? (float) $request->input('shipping_domestic_price') : null,
            'shipping_international_price' => $request->input('shipping_international_price') ? (float) $request->input('shipping_international_price') : null,
        ];

        // Licence template fields — only when listing_type=licence
        if ($request->input('listing_type') === 'licence') {
            $data['requires_shipping'] = 0;
            $data['is_physical'] = 0;
            $data['is_digital'] = 1;
            $data['licence_template_type'] = $request->input('licence_template_type', 'standard');
            $data['licence_template_duration_days'] = $request->filled('licence_template_duration_days')
                ? (int) $request->input('licence_template_duration_days') : null;
            $data['licence_template_scope'] = trim($request->input('licence_template_scope', '')) ?: null;
            $data['licence_template_territory'] = trim($request->input('licence_template_territory', 'Worldwide'));
            $data['licence_template_exclusivity'] = $request->input('licence_template_exclusivity', 'non-exclusive');
            $data['licence_template_attribution_required'] = $request->boolean('licence_template_attribution_required') ? 1 : 0;
            $data['licence_template_modifications_allowed'] = $request->boolean('licence_template_modifications_allowed') ? 1 : 0;
            $data['licence_template_sublicensing_allowed'] = $request->boolean('licence_template_sublicensing_allowed') ? 1 : 0;
            $data['licence_template_max_copies'] = $request->filled('licence_template_max_copies')
                ? (int) $request->input('licence_template_max_copies') : null;
        }

        $data['seller_id'] = $seller->id;
        $result = $this->service->createListing($data);

        if (!empty($result['success']) && $artistId) {
            DB::table('marketplace_artist')->where('id', $artistId)->increment('total_listings');
        }

        if ($result['success']) {
            // If the listing is linked to a GLAM record, default its primary
            // image to the linked record's reference/master image. The user can
            // override on the listing-images page.
            $this->service->defaultListingImageFromIo((int) $result['id']);

            session()->flash('notice', 'Listing created. Add images to complete your listing.');

            return redirect()->route('ahgmarketplace.seller-listing-images', ['id' => $result['id']]);
        }

        session()->flash('error', $result['error'] ?? 'Failed to create listing.');

        return redirect()->route('ahgmarketplace.seller-listing-create');
    }

    public function sellerListingEdit(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $listingId = (int) $request->input('id');
        if (!$listingId) {
            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $listing = $this->service->getListingById($listingId);
        if (!$listing) {
            abort(404);
        }

        if ((int) $listing->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to edit this listing.');

            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $sectors = ['gallery', 'museum', 'archive', 'library', 'dam'];
        $categories = $this->service->getCategories();
        $currencies = $this->service->getCurrencies();

        return view('marketplace::seller-listing-edit', compact(
            'seller',
            'listing',
            'sectors',
            'categories',
            'currencies',
        ));
    }

    public function sellerListingEditPost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $listingId = (int) $request->input('id');
        if (!$listingId) {
            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $listing = $this->service->getListingById($listingId);
        if (!$listing || (int) $listing->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to edit this listing.');

            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $data = [
            'title' => trim($request->input('title')),
            'sector' => $request->input('sector', $listing->sector),
            'listing_type' => $request->input('listing_type', $listing->listing_type),
            'category_id' => $request->input('category_id') ?: null,
            'description' => trim($request->input('description', '')),
            'price' => $request->input('price') ? (float) $request->input('price') : null,
            'currency' => $request->input('currency', 'ZAR'),
            'minimum_offer' => $request->input('minimum_offer') ? (float) $request->input('minimum_offer') : null,
            'artist_name' => trim($request->input('artist_name', '')),
            'medium' => trim($request->input('medium', '')),
            'dimensions' => trim($request->input('dimensions', '')),
            'year_created' => trim($request->input('year_created', '')),
            'provenance' => trim($request->input('provenance', '')),
            'condition_rating' => $request->input('condition_rating') ?: null,
            'condition_description' => trim($request->input('condition_description', '')),
            'is_digital' => $request->input('is_digital') ? 1 : 0,
            'requires_shipping' => $request->input('requires_shipping') ? 1 : 0,
            'shipping_from_country' => trim($request->input('shipping_from_country', '')),
            'shipping_domestic_price' => $request->input('shipping_domestic_price') ? (float) $request->input('shipping_domestic_price') : null,
            'shipping_international_price' => $request->input('shipping_international_price') ? (float) $request->input('shipping_international_price') : null,
        ];

        $result = $this->service->updateListing($listingId, $data);

        if ($result['success']) {
            session()->flash('notice', 'Listing updated successfully.');
        } else {
            session()->flash('error', $result['error']);
        }

        return redirect()->route('ahgmarketplace.seller-listing-edit', ['id' => $listingId]);
    }

    public function sellerListingImages(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $listingId = (int) $request->input('id');
        if (!$listingId) {
            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $listing = $this->service->getListingById($listingId);
        if (!$listing) {
            abort(404);
        }

        if ((int) $listing->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to manage this listing.');

            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $maxImages = (int) $this->service->getSetting('max_images_per_listing', 10);

        // If the listing has no images yet but is linked to a GLAM record with
        // digital objects, auto-attach the linked record's reference/master image
        // as the primary image so the listing isn't blank on the public site.
        if ($this->service->defaultListingImageFromIo($listingId)) {
            session()->flash('notice', 'Used the linked GLAM record\'s image as the default. Upload your own to replace it.');
        }

        $images = $this->service->getListingImages($listingId);

        return view('marketplace::seller-listing-images', compact(
            'listing',
            'images',
            'maxImages',
        ));
    }

    public function sellerListingImagesPost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'id'            => 'required|integer|min:1',
            'form_action'   => 'required|string|in:upload,set_primary,delete',
            'image_id'      => 'nullable|integer|min:1',
            'image_caption' => 'nullable|string|max:500',
            'listing_image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:10240',
        ]);

        $listingId = (int) $request->input('id');
        $listing = $this->service->getListingById($listingId);
        if (!$listing || (int) $listing->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to manage this listing.');

            return redirect()->route('ahgmarketplace.seller-listings');
        }

        $formAction = $request->input('form_action', '');

        if ($formAction === 'upload') {
            $maxImages = (int) $this->service->getSetting('max_images_per_listing', 10);
            $currentImages = $this->service->getListingImages($listingId);

            if (count($currentImages) >= $maxImages) {
                session()->flash('error', 'Maximum image limit reached (' . $maxImages . ').');
            } elseif (!$request->hasFile('listing_image') || !$request->file('listing_image')->isValid()) {
                session()->flash('error', 'Please select an image to upload.');
            } else {
                $file = $request->file('listing_image');

                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file->getMimeType(), $allowedTypes)) {
                    session()->flash('error', 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP.');
                } elseif ($file->getSize() > 10 * 1024 * 1024) {
                    session()->flash('error', 'File size exceeds the 10 MB limit.');
                } else {
                    $caption = trim($request->input('image_caption', ''));
                    $result = $this->service->uploadListingImage($listingId, $file, $caption, count($currentImages));

                    if ($result) {
                        session()->flash('notice', 'Image uploaded successfully.');
                    } else {
                        session()->flash('error', 'Failed to upload image. Please try again.');
                    }
                }
            }
        } elseif ($formAction === 'set_primary') {
            $imageId = (int) $request->input('image_id');
            if ($imageId) {
                $this->service->setPrimaryImage($listingId, $imageId);
                session()->flash('notice', 'Primary image updated.');
            }
        } elseif ($formAction === 'delete') {
            $imageId = (int) $request->input('image_id');
            if ($imageId) {
                $this->service->deleteListingImage($imageId);
                session()->flash('notice', 'Image removed.');
            }
        }

        return redirect()->route('ahgmarketplace.seller-listing-images', ['id' => $listingId]);
    }

    public function sellerCollections(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $collections = $this->service->getSellerCollections($seller->id);

        return view('marketplace::seller-collections', compact('seller', 'collections'));
    }

    public function sellerCollectionsPost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'form_action'   => 'required|string|in:delete',
            'collection_id' => 'required|integer|min:1',
        ]);

        $formAction = $request->input('form_action', '');

        if ($formAction === 'delete') {
            $collectionId = (int) $request->input('collection_id');
            if ($collectionId) {
                $result = $this->service->deleteCollection($collectionId);
                session()->flash(
                    $result['success'] ? 'notice' : 'error',
                    $result['success'] ? 'Collection deleted.' : $result['error']
                );
            }
        }

        return redirect()->route('ahgmarketplace.seller-collections');
    }

    public function sellerCollectionCreate(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        return view('marketplace::seller-collection-create', compact('seller'));
    }

    public function sellerCollectionCreatePost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $data = [
            'title' => trim($request->input('title')),
            'description' => trim($request->input('description', '')) ?: null,
            'is_public' => $request->input('is_public', 1) ? 1 : 0,
        ];

        // Handle cover image upload
        if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {
            $data['cover_image_path'] = $this->service->uploadCollectionCover($seller->id, $request->file('cover_image'));
        }

        $result = $this->service->createCollection($seller->id, $data);

        if ($result['success']) {
            session()->flash('notice', 'Collection created successfully.');

            return redirect()->route('ahgmarketplace.seller-collections');
        }

        session()->flash('error', $result['error']);

        return redirect()->route('ahgmarketplace.seller-collection-create');
    }

    public function sellerOffers(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $statusFilter = $request->input('status', '');
        $page = max(1, (int) $request->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $statusParam = !empty($statusFilter) ? $statusFilter : null;
        $result = $this->service->getSellerOffers($seller->id, $statusParam, $limit, $offset);

        return view('marketplace::seller-offers', [
            'seller' => $seller,
            'offers' => $result['items'],
            'total' => $result['total'],
            'statusFilter' => $statusFilter,
            'page' => $page,
        ]);
    }

    public function sellerOfferRespond(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $offerId = (int) $request->input('id');
        if (!$offerId) {
            return redirect()->route('ahgmarketplace.seller-offers');
        }

        $offer = $this->service->getOfferWithDetails($offerId);
        if (!$offer) {
            abort(404);
        }

        if ((int) $offer->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to respond to this offer.');

            return redirect()->route('ahgmarketplace.seller-offers');
        }

        return view('marketplace::seller-offer-respond', compact('offer'));
    }

    public function sellerOfferRespondPost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'id'              => 'required|integer|min:1',
            'form_action'     => 'required|string|in:accept,reject,counter',
            'seller_response' => 'nullable|string|max:2000',
            'counter_amount'  => 'nullable|numeric|min:0.01|max:99999999.99',
        ]);

        $offerId = (int) $request->input('id');
        if (!$offerId) {
            return redirect()->route('ahgmarketplace.seller-offers');
        }

        $offer = $this->service->getOfferWithDetails($offerId);
        if (!$offer || (int) $offer->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to respond to this offer.');

            return redirect()->route('ahgmarketplace.seller-offers');
        }

        $formAction = $request->input('form_action', '');
        $response = trim($request->input('seller_response', ''));

        if ($formAction === 'accept') {
            $result = $this->service->acceptOffer($offerId);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Offer accepted. A transaction will be created for the buyer.' : $result['error']
            );
        } elseif ($formAction === 'reject') {
            $result = $this->service->rejectOffer($offerId, $response ?: null);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Offer rejected.' : $result['error']
            );
        } elseif ($formAction === 'counter') {
            $counterAmount = (float) $request->input('counter_amount');
            if ($counterAmount <= 0) {
                session()->flash('error', 'Counter amount must be greater than zero.');

                return redirect()->route('ahgmarketplace.seller-offer-respond', ['id' => $offerId]);
            }

            $result = $this->service->counterOffer($offerId, $counterAmount, $response ?: null);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Counter-offer sent.' : $result['error']
            );
        }

        return redirect()->route('ahgmarketplace.seller-offers');
    }

    public function sellerTransactions(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $page = max(1, (int) $request->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $result = $this->service->getSellerTransactions($seller->id, $limit, $offset);

        return view('marketplace::seller-transactions', [
            'seller' => $seller,
            'transactions' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
        ]);
    }

    public function sellerTransactionDetail(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $txnId = (int) $request->input('id');
        if (!$txnId) {
            return redirect()->route('ahgmarketplace.seller-transactions');
        }

        $transaction = $this->service->getTransaction($txnId);
        if (!$transaction) {
            abort(404);
        }

        if ((int) $transaction->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to view this transaction.');

            return redirect()->route('ahgmarketplace.seller-transactions');
        }

        return view('marketplace::seller-transaction-detail', compact('transaction'));
    }

    public function sellerTransactionDetailPost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'id'              => 'required|integer|min:1',
            'tracking_number' => 'nullable|string|max:255',
            'courier'         => 'nullable|string|max:255',
            'shipping_status' => 'nullable|string|in:pending,preparing,shipped,in_transit,delivered,returned',
        ]);

        $txnId = (int) $request->input('id');
        if (!$txnId) {
            return redirect()->route('ahgmarketplace.seller-transactions');
        }

        $transaction = $this->service->getTransaction($txnId);
        if (!$transaction || (int) $transaction->seller_id !== (int) $seller->id) {
            session()->flash('error', 'You do not have permission to update this transaction.');

            return redirect()->route('ahgmarketplace.seller-transactions');
        }

        $shippingData = array_filter([
            'tracking_number' => trim($request->input('tracking_number', '')),
            'courier' => trim($request->input('courier', '')),
            'shipping_status' => $request->input('shipping_status', ''),
        ], fn ($v) => $v !== '');

        if (!empty($shippingData)) {
            $result = $this->service->updateShipping($txnId, $shippingData);
            session()->flash(
                $result['success'] ? 'notice' : 'error',
                $result['success'] ? 'Shipping information updated.' : $result['error']
            );
        }

        return redirect()->route('ahgmarketplace.seller-transaction-detail', ['id' => $txnId]);
    }

    public function sellerEnquiries(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $statusFilter = $request->input('status', '');
        $page = max(1, (int) $request->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $statusParam = !empty($statusFilter) ? $statusFilter : null;
        $result = $this->service->getSellerEnquiries($seller->id, $statusParam, $limit, $offset);

        return view('marketplace::seller-enquiries', [
            'seller' => $seller,
            'enquiries' => $result['items'],
            'total' => $result['total'],
            'statusFilter' => $statusFilter,
            'page' => $page,
        ]);
    }

    public function sellerEnquiriesPost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'form_action'   => 'required|string|in:reply',
            'enquiry_id'    => 'required|integer|min:1',
            'reply_message' => 'required|string|min:1|max:5000',
        ]);

        $formAction = $request->input('form_action', '');

        if ($formAction === 'reply') {
            $enquiryId = (int) $request->input('enquiry_id');
            $reply = trim($request->input('reply_message', ''));

            if (!$enquiryId || empty($reply)) {
                session()->flash('error', 'Reply message is required.');
            } else {
                $result = $this->service->replyToEnquiry($enquiryId, $reply);
                session()->flash(
                    $result['success'] ? 'notice' : 'error',
                    $result['success'] ? 'Reply sent successfully.' : ($result['error'] ?? 'Enquiry not found.')
                );
            }
        }

        return redirect()->route('ahgmarketplace.seller-enquiries');
    }

    public function sellerAnalytics(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $monthlyRevenue = $this->service->getMonthlyRevenue($seller->id, 12);
        $revenueStats = $this->service->getRevenueStats($seller->id);
        $topListings = $this->service->getTopListingsByViews($seller->id, 10);
        $topSelling = $this->service->getTopSellingListings($seller->id, 10);
        $sectorBreakdown = $this->service->getSectorBreakdown($seller->id);

        return view('marketplace::seller-analytics', [
            'seller' => $seller,
            'monthlyRevenue' => $monthlyRevenue,
            'revenueStats' => $revenueStats,
            'topListings' => $topListings,
            'topSelling' => $topSelling,
            'sectorBreakdown' => $sectorBreakdown,
        ]);
    }

    public function sellerPayouts(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $payoutsResult = $this->service->getSellerPayouts($seller->id);
        $pendingAmount = $this->service->getSellerPendingPayoutAmount($seller->id);

        return view('marketplace::seller-payouts', [
            'seller' => $seller,
            'payouts' => $payoutsResult['items'],
            'pendingAmount' => $pendingAmount,
        ]);
    }

    public function sellerReviews(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $page = max(1, (int) $request->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $reviewsResult = $this->service->getSellerReviews($seller->id, $limit, $offset);
        $ratingStats = $this->service->getRatingStats($seller->id);

        return view('marketplace::seller-reviews', [
            'seller' => $seller,
            'reviews' => $reviewsResult['items'],
            'reviewTotal' => $reviewsResult['total'],
            'page' => $page,
            'ratingStats' => $ratingStats,
        ]);
    }
}
