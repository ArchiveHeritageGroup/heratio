<?php

/**
 * EcommerceService - Service for Heratio
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
use Illuminate\Support\Facades\Schema;

class EcommerceService
{
    public function isEcommerceEnabled(): bool
    {
        if (! Schema::hasTable('ahg_ecommerce_settings')) {
            return false;
        }

        return (bool) DB::table('ahg_ecommerce_settings')
            ->where('is_enabled', 1)
            ->exists();
    }

    public function getSettings(): ?object
    {
        return DB::table('ahg_ecommerce_settings')->first();
    }

    public function getProductTypes(bool $activeOnly = true): \Illuminate\Support\Collection
    {
        $query = DB::table('ahg_product_type');
        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('sort_order')->get();
    }

    public function getProductPricing(?int $repositoryId = null, bool $activeOnly = true): \Illuminate\Support\Collection
    {
        $query = DB::table('ahg_product_pricing');
        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query
            ->when($repositoryId, fn ($q) => $q->where('repository_id', $repositoryId))
            ->when(! $repositoryId, fn ($q) => $q->whereNull('repository_id'))
            ->orderBy('product_type_id')
            ->get();
    }

    public function savePricing(array $data): void
    {
        $existing = DB::table('ahg_product_pricing')
            ->whereNull('repository_id')
            ->where('product_type_id', $data['product_type_id'])
            ->first();

        if ($existing) {
            DB::table('ahg_product_pricing')
                ->where('id', $existing->id)
                ->update([
                    'price' => $data['price'],
                    'is_active' => $data['is_active'],
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('ahg_product_pricing')->insert([
                'repository_id' => null,
                'product_type_id' => $data['product_type_id'],
                'name' => $data['name'] ?? '',
                'price' => $data['price'],
                'is_active' => $data['is_active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function calculateCartTotals(\Illuminate\Support\Collection $cartItems, ?int $repositoryId = null): array
    {
        $settings = $this->getSettings();
        $vatRate = $settings->vat_rate ?? 15.00;
        $pricing = $this->getProductPricing($repositoryId);

        $subtotal = 0;
        $userId = \Illuminate\Support\Facades\Auth::id();
        $enrichedItems = $cartItems->map(function ($item) use ($pricing, &$subtotal, $userId) {
            $price = $pricing->firstWhere('product_type_id', $item->product_type_id);
            // heratio#1770: the price the user's type pays (free or discounted).
            $item->list_price = (float) ($price->price ?? 0);
            $unitPrice = \AhgCore\Services\UserType::price($userId, $item->list_price);
            $lineTotal = $unitPrice * ($item->quantity ?? 1);
            $subtotal += $lineTotal;

            $item->unit_price = $unitPrice;
            $item->line_total = $lineTotal;
            $item->product_name = $price->name ?? '';

            return $item;
        });

        $vatAmount = round($subtotal * ($vatRate / (100 + $vatRate)), 2); // VAT included
        $total = $subtotal;

        $type = \AhgCore\Services\UserType::forUser($userId);

        return [
            'user_type' => $type && (! empty($type->free_access) || (float) ($type->discount_percent ?? 0) > 0) ? $type : null,
            'items' => $enrichedItems,
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'vat_rate' => $vatRate,
            'total' => $total,
            'currency' => $settings->currency ?? 'ZAR',
        ];
    }

    public function createOrderFromCart(array $data, \Illuminate\Support\Collection $cartItems): int
    {
        $orderNumber = 'ORD-'.date('Ymd').'-'.str_pad(
            DB::table('ahg_order')->whereDate('created_at', today())->count() + 1,
            4, '0', STR_PAD_LEFT
        );

        $orderId = DB::table('ahg_order')->insertGetId([
            'order_number' => $orderNumber,
            'user_id' => $data['user_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'status' => 'pending',
            'subtotal' => $data['subtotal'] ?? 0,
            'vat_amount' => $data['vat_amount'] ?? 0,
            'total' => $data['total'] ?? 0,
            'currency' => $data['currency'] ?? 'ZAR',
            'customer_name' => $data['customer_name'] ?? '',
            'customer_email' => $data['customer_email'] ?? '',
            'customer_phone' => $data['customer_phone'] ?? '',
            'billing_address' => $data['billing_address'] ?? '',
            'notes' => $data['notes'] ?? '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($cartItems as $item) {
            DB::table('ahg_order_item')->insert([
                'order_id' => $orderId,
                'archival_description_id' => $item->archival_description_id,
                'archival_description' => $item->archival_description,
                'slug' => $item->slug,
                'product_type_id' => $item->product_type_id,
                'product_name' => $item->product_name ?? '',
                'quantity' => $item->quantity ?? 1,
                'unit_price' => $item->unit_price ?? 0,
                'line_total' => $item->line_total ?? 0,
                'created_at' => now(),
            ]);
        }

        // Mark cart items as completed
        foreach ($cartItems as $item) {
            DB::table('cart')->where('id', $item->id)->update(['completed_at' => now()]);
        }

        return $orderId;
    }

    /**
     * heratio#1768: the basket becomes ONE access request naming every record
     * in it (previously one request-to-publish per item). The request is then
     * routed into the access-request workflow when one is switched on.
     */
    public function createRequestFromCart(int $userId, array $data, \Illuminate\Support\Collection $cartItems): int
    {
        $lines = array_filter([
            trim(($data['rtp_name'] ?? '').' '.($data['rtp_surname'] ?? '')),
            ! empty($data['rtp_institution']) ? __('Institution').': '.$data['rtp_institution'] : null,
            ! empty($data['rtp_email']) ? __('Email').': '.$data['rtp_email'] : null,
            ! empty($data['rtp_phone']) ? __('Phone').': '.$data['rtp_phone'] : null,
            ! empty($data['rtp_need_image_by']) ? __('Needed by').': '.$data['rtp_need_image_by'] : null,
            ! empty($data['rtp_motivation']) ? "\n".$data['rtp_motivation'] : null,
        ]);
        $needBy = ! empty($data['rtp_need_image_by']) ? strtotime((string) $data['rtp_need_image_by']) : false;

        $requestId = app(\AhgAccessRequest\Services\AccessRequestService::class)->createRequest($userId, [
            'request_type' => 'object',
            'scope_type' => 'basket',
            'reason' => ($data['rtp_planned_use'] ?? '') !== '' ? (string) $data['rtp_planned_use'] : __('Basket request'),
            'justification' => implode("\n", $lines),
            'urgency' => $needBy && $needBy < strtotime('+14 days') ? 'high' : 'normal',
            'items' => $cartItems->map(fn ($i) => ['id' => (int) $i->archival_description_id, 'title' => $i->archival_description])->all(),
        ]);

        DB::table('cart')->whereIn('id', $cartItems->pluck('id'))->update(['completed_at' => now()]);

        return $requestId;
    }

    /**
     * heratio#1774: the requester accepted a quotation - raise the order that
     * pays it. One line per record; the quotation is the order total (VAT
     * included, as for cart orders). Reuses an unpaid order already raised.
     */
    public function createOrderForQuote(object $request, \Illuminate\Support\Collection $scopes): int
    {
        if (! empty($request->order_id) && DB::table('ahg_order')->where('id', $request->order_id)->where('status', 'pending')->exists()) {
            return (int) $request->order_id;
        }
        $settings = $this->getSettings();
        $vatRate = (float) ($settings->vat_rate ?? 0);
        $total = round((float) $request->quote_amount, 2);
        $user = DB::table('user')->where('id', $request->user_id)->first(['email', 'username']);

        $orderId = $this->createOrderFromCart([
            'user_id' => (int) $request->user_id,
            'subtotal' => $total,
            'vat_amount' => round($total * ($vatRate / (100 + $vatRate)), 2),
            'total' => $total,
            'currency' => $request->quote_currency ?: ($settings->currency ?? 'ZAR'),
            'customer_name' => $request->user_name ?? ($user->username ?? ''),
            'customer_email' => $user->email ?? '',
            'notes' => __('Quotation for access request #:id', ['id' => $request->id]),
        ], collect());

        $n = max(1, $scopes->count());
        $each = round($total / $n, 2);
        foreach ($scopes->values() as $i => $sc) {
            $line = $i === $n - 1 ? round($total - $each * ($n - 1), 2) : $each;
            DB::table('ahg_order_item')->insert([
                'order_id' => $orderId,
                'archival_description_id' => (int) $sc->object_id,
                'archival_description' => $sc->object_title ?? '',
                'slug' => $sc->slug ?? null,
                'product_name' => __('Quotation'),
                'quantity' => 1,
                'unit_price' => $line,
                'line_total' => $line,
                'created_at' => now(),
            ]);
        }
        DB::table('ahg_order')->where('id', $orderId)->update(['access_request_id' => (int) $request->id]);

        return $orderId;
    }

    /** heratio#1770: raise the order that pays a pending subscription. */
    public function createOrderForSubscription(int $subscriptionId): int
    {
        $sub = DB::table('ahg_user_subscription as s')->join('ahg_subscription_tier as t', 't.id', '=', 's.tier_id')
            ->where('s.id', $subscriptionId)->first(['s.*', 't.name as tier_name', 't.price', 't.period_months']);
        $settings = $this->getSettings();
        $vatRate = (float) ($settings->vat_rate ?? 0);
        $total = round((float) $sub->price, 2);
        $user = DB::table('user')->where('id', $sub->user_id)->first(['email', 'username']);
        $orderId = $this->createOrderFromCart([
            'user_id' => (int) $sub->user_id,
            'subtotal' => $total,
            'vat_amount' => round($total * ($vatRate / (100 + $vatRate)), 2),
            'total' => $total,
            'currency' => $settings->currency ?? 'ZAR',
            'customer_name' => $user->username ?? '',
            'customer_email' => $user->email ?? '',
            'notes' => __('Subscription: :tier', ['tier' => $sub->tier_name]),
        ], collect());
        DB::table('ahg_order_item')->insert([
            'order_id' => $orderId, 'product_name' => __('Subscription: :tier (:months months)', ['tier' => $sub->tier_name, 'months' => $sub->period_months]),
            'archival_description' => '', 'quantity' => 1, 'unit_price' => $total, 'line_total' => $total, 'created_at' => now(),
        ]);
        DB::table('ahg_order')->where('id', $orderId)->update(['subscription_id' => $subscriptionId]);
        DB::table('ahg_user_subscription')->where('id', $subscriptionId)->update(['order_id' => $orderId, 'updated_at' => now()]);

        return $orderId;
    }

    /** Start a subscription now, or extend the user's current one of the same tier. */
    public function activateSubscription(int $subscriptionId): void
    {
        $sub = DB::table('ahg_user_subscription as s')->join('ahg_subscription_tier as t', 't.id', '=', 's.tier_id')
            ->where('s.id', $subscriptionId)->first(['s.*', 't.period_months']);
        if (! $sub || $sub->status === 'active') {
            return;
        }
        $current = DB::table('ahg_user_subscription')->where('user_id', $sub->user_id)->where('tier_id', $sub->tier_id)
            ->where('status', 'active')->where('ends_at', '>', now())->max('ends_at');
        $start = $current ? \Illuminate\Support\Carbon::parse($current) : now();
        DB::table('ahg_user_subscription')->where('id', $subscriptionId)->update([
            'status' => 'active', 'starts_at' => now(), 'ends_at' => $start->copy()->addMonths(max(1, (int) $sub->period_months)), 'updated_at' => now(),
        ]);
        \AhgCore\Services\UserType::forget();
    }

    public function getUserOrders(int $userId): \Illuminate\Support\Collection
    {
        return DB::table('ahg_order')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getOrder(int $id): ?object
    {
        return DB::table('ahg_order')->where('id', $id)->first();
    }

    public function getOrderItems(int $orderId): \Illuminate\Support\Collection
    {
        return DB::table('ahg_order_item')->where('order_id', $orderId)->get();
    }

    public function getAllOrders(int $page = 1, int $limit = 20, ?string $status = null): array
    {
        $query = DB::table('ahg_order');
        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $results = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['results' => $results, 'total' => $total, 'page' => $page, 'lastPage' => max(1, (int) ceil($total / $limit))];
    }

    public function getOrderStats(): array
    {
        return [
            'total' => DB::table('ahg_order')->count(),
            'pending' => DB::table('ahg_order')->where('status', 'pending')->count(),
            'paid' => DB::table('ahg_order')->where('status', 'paid')->count(),
            'completed' => DB::table('ahg_order')->where('status', 'completed')->count(),
            'cancelled' => DB::table('ahg_order')->where('status', 'cancelled')->count(),
            'revenue' => DB::table('ahg_order')->whereIn('status', ['paid', 'completed'])->sum('total'),
        ];
    }
}
