<?php

/**
 * EcommerceService - Service for Heratio
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

class EcommerceService
{
    public function isEcommerceEnabled(): bool
    {
        if (!Schema::hasTable('ahg_ecommerce_settings')) {
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

    public function getProductTypes(): \Illuminate\Support\Collection
    {
        return DB::table('ahg_product_type')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();
    }

    public function getProductPricing(?int $repositoryId = null): \Illuminate\Support\Collection
    {
        return DB::table('ahg_product_pricing')
            ->where('is_active', 1)
            ->when($repositoryId, fn ($q) => $q->where('repository_id', $repositoryId))
            ->when(!$repositoryId, fn ($q) => $q->whereNull('repository_id'))
            ->orderBy('product_type_id')
            ->get();
    }

    public function calculateCartTotals(\Illuminate\Support\Collection $cartItems, ?int $repositoryId = null): array
    {
        $settings = $this->getSettings();
        $vatRate = $settings->vat_rate ?? 15.00;
        $pricing = $this->getProductPricing($repositoryId);

        $subtotal = 0;
        $enrichedItems = $cartItems->map(function ($item) use ($pricing, &$subtotal) {
            $price = $pricing->firstWhere('product_type_id', $item->product_type_id);
            $unitPrice = $price->price ?? 0;
            $lineTotal = $unitPrice * ($item->quantity ?? 1);
            $subtotal += $lineTotal;

            $item->unit_price = $unitPrice;
            $item->line_total = $lineTotal;
            $item->product_name = $price->name ?? '';
            return $item;
        });

        $vatAmount = round($subtotal * ($vatRate / (100 + $vatRate)), 2); // VAT included
        $total = $subtotal;

        return [
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
        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(
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

    public function createRtpFromCart(array $data, \Illuminate\Support\Collection $cartItems): void
    {
        foreach ($cartItems as $item) {
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitRequestToPublish',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('request_to_publish')->insert([
                'id' => $objectId,
                'parent_id' => $item->archival_description_id,
                'lft' => 0,
                'rgt' => 1,
                'source_culture' => 'en',
            ]);

            DB::table('request_to_publish_i18n')->insert([
                'id' => $objectId,
                'object_id' => $item->archival_description_id,
                'rtp_name' => $data['rtp_name'] ?? '',
                'rtp_surname' => $data['rtp_surname'] ?? '',
                'rtp_email' => $data['rtp_email'] ?? '',
                'rtp_phone' => $data['rtp_phone'] ?? '',
                'rtp_institution' => $data['rtp_institution'] ?? '',
                'rtp_planned_use' => $data['rtp_planned_use'] ?? '',
                'rtp_motivation' => $data['rtp_motivation'] ?? '',
                'rtp_need_image_by' => $data['rtp_need_image_by'] ?? null,
                'status_id' => 220,
                'created_at' => now(),
                'culture' => 'en',
            ]);

            DB::table('cart')->where('id', $item->id)->update(['completed_at' => now()]);
        }
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
        ];
    }
}
