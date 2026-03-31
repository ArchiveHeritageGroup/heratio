<?php

/**
 * CartController - Controller for Heratio
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

        return view('ahg-cart::browse', compact('items', 'isEcommerce', 'productTypes', 'pricing'));
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
        $productTypes = $this->ecommerceService->getProductTypes();
        $pricing = $this->ecommerceService->getProductPricing();
        return view('ahg-cart::admin.settings', compact('settings', 'productTypes', 'pricing'));
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
            DB::table('ahg_orders')
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
