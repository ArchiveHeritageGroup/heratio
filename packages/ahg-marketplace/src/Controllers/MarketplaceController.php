<?php

namespace AhgMarketplace\Controllers;

use AhgMarketplace\Services\MarketplaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        $listingId = (int) $request->input('id');
        if (!$listingId) {
            return redirect()->route('ahgmarketplace.admin-listings');
        }

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

        $sellerId = (int) $request->input('id');
        if (!$sellerId) {
            return redirect()->route('ahgmarketplace.admin-sellers');
        }

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

        $selectedIds = $request->input('payout_ids', []);
        if (!is_array($selectedIds) || empty($selectedIds)) {
            session()->flash('error', 'No payouts selected.');

            return redirect()->route('ahgmarketplace.admin-payouts');
        }

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
        $offset = ($page - 1) * $limit;
        $sort = $request->input('sort', 'newest');

        $results = $this->service->browse($filters, $limit, $offset, $sort);
        $facets = $this->service->getFacetCounts($filters);

        $sectorFilter = !empty($filters['sector']) ? $filters['sector'] : null;
        $categories = $this->service->getCategories($sectorFilter);
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
        $offset = ($page - 1) * $limit;

        $results = $this->service->search($query, $filters, $limit, $offset);

        return view('marketplace::search', [
            'results' => $results['items'],
            'total' => $results['total'],
            'query' => $results['query'],
            'filters' => $filters,
            'facets' => $results['facets'],
            'page' => $page,
        ]);
    }

    public function listing(Request $request)
    {
        $slug = $request->input('slug');
        if (empty($slug)) {
            return redirect()->route('ahgmarketplace.browse');
        }

        $listing = $this->service->getListing($slug);
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
        $offset = ($page - 1) * $limit;
        $sort = $request->input('sort', 'newest');

        $results = $this->service->browse(
            ['sector' => $sector, 'category_id' => $category->id],
            $limit,
            $offset,
            $sort
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

        if (empty($sector) || !in_array($sector, $validSectors, true)) {
            abort(404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $sort = $request->input('sort', 'newest');

        $results = $this->service->browse(['sector' => $sector], $limit, $offset, $sort);
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

        $results = $this->service->getActiveAuctions($limit, $offset);
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
        $offset = ($page - 1) * $limit;

        $listingsResult = $this->service->browse(['seller_id' => $seller->id], $limit, $offset, 'newest');
        $reviews = $this->service->getSellerReviews($seller->id, 10, 0);
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
            'reviews' => $reviews,
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

        return view('marketplace::dashboard', [
            'seller' => $seller,
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'pendingOfferCount' => $pendingOfferCount,
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

        return view('marketplace::my-bids', [
            'bids' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
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

        return view('marketplace::seller-listing-create', compact(
            'seller',
            'sectors',
            'categories',
            'currencies',
            'prefill',
        ));
    }

    public function sellerListingCreatePost(Request $request)
    {
        $userId = $this->requireAuth($request);
        $seller = $this->requireSeller($userId);

        $request->validate([
            'title' => 'required|string|max:255',
            'sector' => 'required|string|in:gallery,museum,archive,library,dam',
            'listing_type' => 'required|string|in:fixed_price,auction,offer_only',
        ]);

        $data = [
            'title' => trim($request->input('title')),
            'sector' => $request->input('sector'),
            'listing_type' => $request->input('listing_type'),
            'information_object_id' => $request->input('information_object_id') ?: null,
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
            'condition_notes' => trim($request->input('condition_notes', '')),
            'is_digital' => $request->input('is_digital') ? 1 : 0,
            'requires_shipping' => $request->input('requires_shipping') ? 1 : 0,
            'shipping_from_country' => trim($request->input('shipping_from_country', '')),
            'shipping_domestic_price' => $request->input('shipping_domestic_price') ? (float) $request->input('shipping_domestic_price') : null,
            'shipping_international_price' => $request->input('shipping_international_price') ? (float) $request->input('shipping_international_price') : null,
        ];

        $result = $this->service->createListing($seller->id, $data);

        if ($result['success']) {
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
            'condition_notes' => trim($request->input('condition_notes', '')),
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

        $listingId = (int) $request->input('id');
        if (!$listingId) {
            return redirect()->route('ahgmarketplace.seller-listings');
        }

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

        $reviews = $this->service->getSellerReviews($seller->id, $limit, $offset);
        $ratingStats = $this->service->getRatingStats($seller->id);

        return view('marketplace::seller-reviews', [
            'seller' => $seller,
            'reviews' => $reviews,
            'ratingStats' => $ratingStats,
        ]);
    }
}
