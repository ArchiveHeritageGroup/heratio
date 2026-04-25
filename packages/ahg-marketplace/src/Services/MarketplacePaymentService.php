<?php

/**
 * MarketplacePaymentService — PayFast gateway integration for the
 * Heratio Marketplace.
 *
 * Reads PayFast credentials from ahg_ecommerce_settings (id=1).
 * Honours payfast_sandbox=1 → routes to sandbox.payfast.co.za.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgMarketplace\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MarketplacePaymentService
{
    private const HOST_LIVE = 'https://www.payfast.co.za';
    private const HOST_SANDBOX = 'https://sandbox.payfast.co.za';

    /** Returns the PayFast settings row (creates an empty one if missing). */
    public function getSettings(): object
    {
        $row = DB::table('ahg_ecommerce_settings')->where('id', 1)->first();
        if (!$row) {
            DB::table('ahg_ecommerce_settings')->insert([
                'id' => 1,
                'is_enabled' => 1,
                'currency' => 'ZAR',
                'payment_gateway' => 'payfast',
                'payfast_sandbox' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table('ahg_ecommerce_settings')->where('id', 1)->first();
        }
        return $row;
    }

    public function isSandbox(): bool
    {
        return (bool) ($this->getSettings()->payfast_sandbox ?? 1);
    }

    public function getProcessHost(): string
    {
        return $this->isSandbox() ? self::HOST_SANDBOX : self::HOST_LIVE;
    }

    public function getValidateUrl(): string
    {
        return $this->getProcessHost() . '/eng/query/validate';
    }

    /**
     * Build the URL the buyer should be redirected to in order to pay.
     * The transaction must already exist in marketplace_transaction.
     */
    public function buildProcessUrl(object $transaction, object $listing, string $buyerName, string $buyerEmail): string
    {
        $settings = $this->getSettings();

        if (empty($settings->payfast_merchant_id) || empty($settings->payfast_merchant_key)) {
            throw new RuntimeException('PayFast credentials are not configured. Set them under e-commerce settings.');
        }

        // PayFast requires fields in this exact order for signature generation.
        // Using rawurlencode w/ space-as-plus per PayFast docs.
        [$first, $last] = $this->splitName($buyerName);

        $fields = array_filter([
            'merchant_id'    => (string) $settings->payfast_merchant_id,
            'merchant_key'   => (string) $settings->payfast_merchant_key,
            'return_url'     => route('ahgmarketplace.payment-return', ['txn' => $transaction->transaction_number]),
            'cancel_url'     => route('ahgmarketplace.payment-cancel', ['txn' => $transaction->transaction_number]),
            'notify_url'     => route('ahgmarketplace.payfast-notify'),
            'name_first'     => $first,
            'name_last'      => $last,
            'email_address'  => $buyerEmail,
            'm_payment_id'   => $transaction->transaction_number,
            'amount'         => number_format((float) $transaction->grand_total, 2, '.', ''),
            'item_name'      => $this->trim($listing->title ?? 'Marketplace listing', 100),
            'item_description' => $this->trim(strip_tags((string) ($listing->description ?? '')), 250),
            'custom_str1'    => 'marketplace',
            'custom_int1'    => (string) $transaction->id,
        ], fn($v) => $v !== '' && $v !== null);

        $fields['signature'] = $this->signFields($fields, (string) ($settings->payfast_passphrase ?? ''));

        return $this->getProcessHost() . '/eng/process?' . http_build_query($fields);
    }

    /**
     * Generate the PayFast signature.
     * Per spec: build query string in submitted field order, urlencode values
     * (with spaces as '+'), append passphrase if set, MD5.
     */
    public function signFields(array $fields, string $passphrase = ''): string
    {
        unset($fields['signature']);
        $parts = [];
        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key . '=' . str_replace('%20', '+', rawurlencode((string) $value));
        }
        $payload = implode('&', $parts);
        if ($passphrase !== '') {
            $payload .= '&passphrase=' . str_replace('%20', '+', rawurlencode($passphrase));
        }
        return md5($payload);
    }

    /**
     * Verify an ITN payload posted by PayFast to the notify_url.
     * Returns true only if all four checks pass:
     *   1. Signature matches
     *   2. Source IP is in PayFast's published list (sandbox skips IP check)
     *   3. Server-to-server validate returns "VALID"
     *   4. Amount matches the transaction's grand_total
     */
    public function verifyItn(array $payload, string $sourceIp, ?object $transaction): bool
    {
        $settings = $this->getSettings();

        // 1. Signature
        $expected = $this->signFields($payload, (string) ($settings->payfast_passphrase ?? ''));
        if (empty($payload['signature']) || strcasecmp($payload['signature'], $expected) !== 0) {
            Log::warning('[PayFast ITN] signature mismatch', ['expected' => $expected, 'got' => $payload['signature'] ?? null]);
            return false;
        }

        // 2. Source IP (skip in sandbox — PayFast sandbox doesn't publish a stable IP list)
        if (!$this->isSandbox() && !$this->isPayFastIp($sourceIp)) {
            Log::warning('[PayFast ITN] source IP not in PayFast list', ['ip' => $sourceIp]);
            return false;
        }

        // 3. Server-to-server validate
        try {
            $resp = Http::asForm()
                ->timeout(10)
                ->post($this->getValidateUrl(), $payload);
            $body = trim((string) $resp->body());
            if ($resp->failed() || stripos($body, 'VALID') !== 0) {
                Log::warning('[PayFast ITN] validate response not VALID', ['body' => $body]);
                return false;
            }
        } catch (\Throwable $e) {
            Log::warning('[PayFast ITN] validate request failed', ['err' => $e->getMessage()]);
            return false;
        }

        // 4. Amount check
        if ($transaction) {
            $expectedAmount = number_format((float) $transaction->grand_total, 2, '.', '');
            $gotAmount = number_format((float) ($payload['amount_gross'] ?? 0), 2, '.', '');
            if ($expectedAmount !== $gotAmount) {
                Log::warning('[PayFast ITN] amount mismatch', ['expected' => $expectedAmount, 'got' => $gotAmount]);
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the request came from PayFast's published IP ranges.
     * https://developers.payfast.co.za/docs#step_4_confirm_source
     */
    public function isPayFastIp(string $ip): bool
    {
        $allowedHosts = [
            'www.payfast.co.za',
            'sandbox.payfast.co.za',
            'w1w.payfast.co.za',
            'w2w.payfast.co.za',
        ];
        $allowedIps = [];
        foreach ($allowedHosts as $h) {
            $resolved = @gethostbynamel($h);
            if (is_array($resolved)) {
                $allowedIps = array_merge($allowedIps, $resolved);
            }
        }
        return in_array($ip, $allowedIps, true);
    }

    private function splitName(string $name): array
    {
        $name = trim($name) ?: 'Buyer';
        $parts = preg_split('/\s+/', $name, 2);
        return [$this->trim($parts[0] ?? $name, 100), $this->trim($parts[1] ?? '', 100)];
    }

    private function trim(string $s, int $max): string
    {
        $s = trim($s);
        return mb_substr($s, 0, $max);
    }
}
