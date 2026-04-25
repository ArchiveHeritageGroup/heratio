<?php

/**
 * CartController - Controller for Heratio
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



namespace AhgCart\Controllers;

use AhgCart\Services\CartService;
use AhgCart\Services\EcommerceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    private CartService $cartService;
    private EcommerceService $ecommerceService;

    public function __construct(CartService $cartService, EcommerceService $ecommerceService)
    {
        $this->cartService = $cartService;
        $this->ecommerceService = $ecommerceService;
    }

    public function browse(Request $request)
    {
        $userId = Auth::id();
        $sessionId = $request->session()->getId();

        // Merge guest cart on login
        if ($userId) {
            $this->cartService->mergeGuestCart($sessionId, $userId);
        }

        $items = $this->cartService->getCart($userId, $userId ? null : $sessionId);
        $isEcommerce = $this->ecommerceService->isEcommerceEnabled();
        $productTypes = $isEcommerce ? $this->ecommerceService->getProductTypes() : collect();
        $pricing = $isEcommerce ? $this->ecommerceService->getProductPricing() : collect();

        // Marketplace cart (separate kind) — listings the user wants to buy
        $marketplaceCart = $this->cartService->getMarketplaceCart($userId, $userId ? null : $sessionId);

        return view('ahg-cart::browse', compact('items', 'isEcommerce', 'productTypes', 'pricing', 'marketplaceCart'));
    }

    /**
     * POST /cart/listing/add/{listingId} — add a marketplace listing to the cart.
     */
    public function addListing(Request $request, int $listingId)
    {
        $userId = Auth::id();
        if (!$userId) {
            return redirect()->route('login');
        }
        $sessionId = $request->session()->getId();

        $added = $this->cartService->addListingToCart($userId, null, $listingId);

        if ($added) {
            return redirect()->route('cart.browse')->with('success', 'Listing added to your cart.');
        }
        return redirect()->route('cart.browse')->with('info', 'That listing is already in your cart (or no longer active).');
    }

    /**
     * POST /cart/marketplace/checkout — combined PayFast checkout for every
     * marketplace listing currently in the cart.
     *
     * Strategy: create one marketplace_transaction per cart item in
     * pending_payment state, all sharing a generated cart_group_id and a
     * common transaction_number prefix. Redirect the buyer to PayFast with
     * the parent group id as m_payment_id and the combined total. The ITN
     * handler matches payments back via cart_group_id.
     */
    public function marketplaceCheckout(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return redirect()->route('login');
        }

        $cart = $this->cartService->getMarketplaceCart($userId, null);
        if ($cart['items']->isEmpty()) {
            return redirect()->route('cart.browse')->with('error', 'Your marketplace cart is empty.');
        }

        $payments = app(\AhgMarketplace\Services\MarketplacePaymentService::class);
        $marketplace = app(\AhgMarketplace\Services\MarketplaceService::class);

        $groupId = $this->generateCartGroupId();
        $createdTxnIds = [];
        $listingsCovered = [];

        foreach ($cart['items'] as $i => $item) {
            // Skip self-buy
            $listing = $marketplace->getListingById((int) $item->listing_id);
            if (!$listing) {
                continue;
            }
            $sellerForBuyer = $marketplace->getSellerByUserId($userId);
            if ($sellerForBuyer && (int) $sellerForBuyer->id === (int) $listing->seller_id) {
                continue;
            }

            $result = $marketplace->createTransaction([
                'source'     => 'fixed_price',
                'listing_id' => (int) $item->listing_id,
                'buyer_id'   => $userId,
            ]);
            if (empty($result['success'])) {
                continue;
            }
            $txnId = (int) ($result['transaction_id'] ?? ($result['transaction']->id ?? 0));
            if (!$txnId) {
                continue;
            }

            DB::table('marketplace_transaction')
                ->where('id', $txnId)
                ->update(['cart_group_id' => $groupId, 'updated_at' => now()]);

            $createdTxnIds[] = $txnId;
            $listingsCovered[] = (int) $item->listing_id;
        }

        if (empty($createdTxnIds)) {
            return redirect()->route('cart.browse')->with('error', 'No payable items in your cart.');
        }

        $grandTotal = (float) DB::table('marketplace_transaction')
            ->where('cart_group_id', $groupId)
            ->sum('grand_total');

        // Build a synthetic "transaction" object for PayFast — group id as
        // identifier, summed grand_total. Reuse the first listing's title for
        // a friendly item_name.
        $firstListing = $marketplace->getListingById($listingsCovered[0]);
        $syntheticTxn = (object) [
            'id'                 => 0,
            'transaction_number' => $groupId,
            'grand_total'        => number_format($grandTotal, 2, '.', ''),
            'currency'           => $cart['currency'],
        ];
        $itemTitle = count($createdTxnIds) === 1
            ? ($firstListing->title ?? 'Marketplace purchase')
            : 'Marketplace cart (' . count($createdTxnIds) . ' items)';
        $syntheticListing = (object) [
            'title' => $itemTitle,
            'description' => 'Combined PayFast checkout for ' . count($createdTxnIds) . ' marketplace listing(s).',
        ];

        $userRow = DB::table('users')->where('id', $userId)->first(['name', 'email']);
        $name = $userRow->name ?? 'Buyer';
        $email = $userRow->email ?? 'buyer@example.com';

        try {
            $url = $payments->buildProcessUrl($syntheticTxn, $syntheticListing, $name, $email);
        } catch (\Throwable $e) {
            return redirect()->route('cart.browse')->with('error', 'Payment gateway is not configured: ' . $e->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * Demo-mode cart checkout — used when e-commerce is disabled. Creates
     * marketplace_transaction rows with payment_gateway=demo + status=paid,
     * marks the cart items as completed (so they don't reappear in the cart),
     * but leaves the underlying marketplace_listing rows on 'active' so
     * the same demo can be repeated.
     */
    public function marketplaceDemoCheckout(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return redirect()->route('login');
        }

        $ecommerce = app(\AhgCart\Services\EcommerceService::class);
        if ($ecommerce->isEcommerceEnabled()) {
            session()->flash('error', 'E-commerce is enabled — use the real PayFast checkout.');
            return redirect()->route('cart.browse');
        }

        $cart = $this->cartService->getMarketplaceCart($userId, null);
        if ($cart['items']->isEmpty()) {
            return redirect()->route('cart.browse')->with('error', 'Your marketplace cart is empty.');
        }

        $marketplace = app(\AhgMarketplace\Services\MarketplaceService::class);
        $createdTxns = [];

        foreach ($cart['items'] as $item) {
            $listing = $marketplace->getListingById((int) $item->listing_id);
            if (!$listing) {
                continue;
            }
            $sellerForBuyer = $marketplace->getSellerByUserId($userId);
            if ($sellerForBuyer && (int) $sellerForBuyer->id === (int) $listing->seller_id) {
                continue; // skip self-buy
            }

            $result = $marketplace->createTransaction([
                'source'     => 'fixed_price',
                'listing_id' => (int) $item->listing_id,
                'buyer_id'   => $userId,
            ]);
            if (empty($result['success'])) {
                continue;
            }
            $txnId = (int) ($result['transaction_id'] ?? ($result['transaction']->id ?? 0));
            if (!$txnId) {
                continue;
            }

            // Mark the demo transaction as paid without flipping the listing
            // to 'sold' (so the demo data stays available for repeated demos).
            DB::table('marketplace_transaction')
                ->where('id', $txnId)
                ->update([
                    'payment_status'         => 'paid',
                    'payment_gateway'        => 'demo',
                    'payment_transaction_id' => 'TXN-DEMO-' . random_int(10000, 99999),
                    'gateway_response'       => json_encode(['demo' => true, 'note' => 'simulated payment — e-commerce disabled']),
                    'paid_at'                => now(),
                    'updated_at'             => now(),
                ]);
            $createdTxns[] = $txnId;

            // If the listing was on a reservation hold, release it back to
            // 'active' (so it stays buyable for the next demo run) and mark
            // any matching reservation row as converted.
            DB::table('marketplace_listing')
                ->where('id', $item->listing_id)
                ->where('status', 'reserved')
                ->update([
                    'status' => 'active',
                    'reserved_by_user_id' => null,
                    'reserved_until' => null,
                    'updated_at' => now(),
                ]);
            DB::table('marketplace_reservation')
                ->where('listing_id', $item->listing_id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->update(['status' => 'converted', 'updated_at' => now()]);
        }

        // Clear the cart rows
        DB::table('cart')
            ->where('user_id', $userId)
            ->where('kind', 'marketplace')
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);

        $count = count($createdTxns);
        $total = (float) DB::table('marketplace_transaction')->whereIn('id', $createdTxns)->sum('grand_total');

        session()->flash('notice', sprintf(
            'Demo sale completed — %d listing%s simulated for %s %s. Listings remain available for further demos.',
            $count,
            $count === 1 ? '' : 's',
            $cart['currency'],
            number_format($total, 2)
        ));
        return redirect()->route('cart.browse');
    }

    private function generateCartGroupId(): string
    {
        $date = date('Ymd');
        $last = DB::table('marketplace_transaction')
            ->where('cart_group_id', 'LIKE', 'CART-' . $date . '-%')
            ->orderByDesc('id')
            ->first();
        $seq = 1;
        if ($last && !empty($last->cart_group_id)) {
            $parts = explode('-', $last->cart_group_id);
            $seq = (int) end($parts) + 1;
        }
        return sprintf('CART-%s-%04d', $date, $seq);
    }

    public function add(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        // Resolve object from slug
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            return redirect()->back()->with('error', 'Item not found.');
        }

        $title = DB::table('information_object_i18n')
            ->where('id', $slugRow->object_id)
            ->where('culture', $culture)
            ->value('title') ?? $slug;

        $userId = Auth::id();
        $sessionId = $request->session()->getId();

        $added = $this->cartService->addToCart($userId, $userId ? null : $sessionId, $slugRow->object_id, $title, $slug);

        if ($added) {
            return redirect()->back()->with('success', 'Item added to cart.');
        }

        return redirect()->back()->with('info', 'Item is already in your cart.');
    }

    public function remove(Request $request, int $id)
    {
        $this->cartService->removeItem($id, Auth::id());
        return redirect()->route('cart.browse')->with('success', 'Item removed from cart.');
    }

    public function clear(Request $request)
    {
        $this->cartService->clearAll(Auth::id(), $request->session()->getId());
        return redirect()->route('cart.browse')->with('success', 'Cart cleared.');
    }

    public function checkout(Request $request)
    {
        $userId = Auth::id();
        $sessionId = $request->session()->getId();
        $items = $this->cartService->getCart($userId, $userId ? null : $sessionId);

        if ($items->isEmpty()) {
            return redirect()->route('cart.browse')->with('error', 'Your cart is empty.');
        }

        $isEcommerce = $this->ecommerceService->isEcommerceEnabled();

        if ($request->isMethod('post')) {
            if ($isEcommerce) {
                return $this->processEcommerceCheckout($request, $items);
            }
            return $this->processStandardCheckout($request, $items);
        }

        $totals = $isEcommerce ? $this->ecommerceService->calculateCartTotals($items) : null;

        return view('ahg-cart::checkout', compact('items', 'isEcommerce', 'totals'));
    }

    private function processStandardCheckout(Request $request, $items): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'rtp_name' => 'required|string|max:50',
            'rtp_surname' => 'required|string|max:50',
            'rtp_email' => 'required|email|max:50',
        ]);

        $this->ecommerceService->createRtpFromCart($request->only([
            'rtp_name', 'rtp_surname', 'rtp_email', 'rtp_phone',
            'rtp_institution', 'rtp_planned_use', 'rtp_motivation', 'rtp_need_image_by',
        ]), $items);

        return redirect()->route('cart.thankyou')->with('success', 'Your request has been submitted.');
    }

    private function processEcommerceCheckout(Request $request, $items): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
        ]);

        $totals = $this->ecommerceService->calculateCartTotals($items);

        $orderId = $this->ecommerceService->createOrderFromCart([
            'user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
            'subtotal' => $totals['subtotal'],
            'vat_amount' => $totals['vat_amount'],
            'total' => $totals['total'],
            'currency' => $totals['currency'],
            'customer_name' => $request->input('customer_name'),
            'customer_email' => $request->input('customer_email'),
            'customer_phone' => $request->input('customer_phone', ''),
            'billing_address' => $request->input('billing_address', ''),
            'notes' => $request->input('notes', ''),
        ], $totals['items']);

        return redirect()->route('cart.order-confirmation', $orderId)->with('success', 'Order placed successfully.');
    }

    public function orders()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        $orders = $this->ecommerceService->getUserOrders(Auth::id());
        return view('ahg-cart::orders', compact('orders'));
    }

    public function orderConfirmation(int $id)
    {
        $order = $this->ecommerceService->getOrder($id);
        if (!$order) {
            abort(404);
        }
        $items = $this->ecommerceService->getOrderItems($id);
        return view('ahg-cart::order-confirmation', compact('order', 'items'));
    }

    public function thankYou()
    {
        return view('ahg-cart::thank-you');
    }

    // Admin routes
    public function adminOrders(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $status = $request->get('status');
        $data = $this->ecommerceService->getAllOrders($page, 20, $status);
        $stats = $this->ecommerceService->getOrderStats();
        return view('ahg-cart::admin.orders', array_merge($data, ['stats' => $stats, 'filterStatus' => $status]));
    }

    public function adminSettings(Request $request)
    {
        if ($request->isMethod('post')) {
            $actionType = $request->input('action_type', 'save_settings');

            if ($actionType === 'save_pricing') {
                $this->savePricing($request);
                return redirect()->route('cart.admin.settings', ['tab' => 'pricing'])->with('success', 'Product pricing updated.');
            }

            // save_settings (General + Payment tabs)
            $settings = $this->ecommerceService->getSettings();
            if ($settings) {
                DB::table('ahg_ecommerce_settings')
                    ->where('id', $settings->id)
                    ->update([
                        'is_enabled' => $request->boolean('is_enabled') ? 1 : 0,
                        'currency' => $request->input('currency', 'ZAR'),
                        'vat_rate' => $request->input('vat_rate', 15.00),
                        'vat_number' => $request->input('vat_number', ''),
                        'admin_notification_email' => $request->input('admin_notification_email', ''),
                        'terms_conditions' => $request->input('terms_conditions', ''),
                        'payfast_merchant_id' => $request->input('payfast_merchant_id', ''),
                        'payfast_merchant_key' => $request->filled('payfast_merchant_key') ? $request->input('payfast_merchant_key') : $settings->payfast_merchant_key,
                        'payfast_passphrase' => $request->filled('payfast_passphrase') ? $request->input('payfast_passphrase') : $settings->payfast_passphrase,
                        'payfast_sandbox' => $request->boolean('payfast_sandbox') ? 1 : 0,
                        'updated_at' => now(),
                    ]);
            }
            return redirect()->route('cart.admin.settings')->with('success', 'E-commerce settings saved.');
        }

        $settings = $this->ecommerceService->getSettings();
        $productTypes = $this->ecommerceService->getProductTypes(false);
        $pricing = $this->ecommerceService->getProductPricing(null, false);
        $activeTab = $request->get('tab', 'general');
        return view('ahg-cart::admin.settings', compact('settings', 'productTypes', 'pricing', 'activeTab'));
    }

    private function savePricing(Request $request): void
    {
        $prices = $request->input('price', []);
        $active = $request->input('price_active', []);
        $productTypes = $this->ecommerceService->getProductTypes(false);

        foreach ($prices as $typeId => $price) {
            $pt = $productTypes->firstWhere('id', $typeId);
            $this->ecommerceService->savePricing([
                'product_type_id' => $typeId,
                'name' => $pt->name ?? 'Product ' . $typeId,
                'price' => floatval($price),
                'is_active' => isset($active[$typeId]) ? 1 : 0,
            ]);
        }
    }

    public function payment(int $id) { return view('ahg-cart::payment'); }

    /**
     * Download a cart export via token authentication.
     */
    public function download(string $token)
    {
        $download = DB::table('ahg_cart_downloads')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$download) {
            abort(404, 'Download link has expired or is invalid.');
        }

        $filePath = $download->file_path ?? null;
        if (!$filePath || !file_exists($filePath)) {
            abort(404, 'File not found.');
        }

        // Mark as downloaded
        DB::table('ahg_cart_downloads')
            ->where('id', $download->id)
            ->update(['downloaded_at' => now()]);

        return response()->download($filePath);
    }

    /**
     * Payment gateway notification (ITN / webhook).
     */
    public function paymentNotify(Request $request)
    {
        $data = $request->all();

        // Log the notification
        DB::table('ahg_payment_notifications')->insert([
            'gateway' => 'payfast',
            'payload' => json_encode($data),
            'status' => $data['payment_status'] ?? 'unknown',
            'order_id' => $data['m_payment_id'] ?? null,
            'created_at' => now(),
        ]);

        // Update order status if we have a valid order
        if (!empty($data['m_payment_id']) && ($data['payment_status'] ?? '') === 'COMPLETE') {
            DB::table('ahg_order')
                ->where('id', $data['m_payment_id'])
                ->update([
                    'status' => 'paid',
                    'payment_reference' => $data['pf_payment_id'] ?? null,
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return response('OK', 200);
    }
}
