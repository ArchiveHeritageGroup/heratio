<?php

/**
 * ReleaseLinkService - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1775: download links that release a record's original to one user,
 * created when an order is paid or an access request for the record is
 * approved. The link is checked against the signed-in user at download time,
 * so a forwarded link is useless to anyone else. Rows live in
 * ahg_cart_downloads (columns added by the 2026_11_19_090000 migration).
 */
class ReleaseLinkService
{
    public const DAYS = 30;

    public static function available(): bool
    {
        return Schema::hasTable('ahg_cart_downloads') && Schema::hasColumn('ahg_cart_downloads', 'user_id');
    }

    /**
     * Issue (or reuse a live) link for $userId to the original of $ioId.
     * Returns the token, or null when the record has no original file.
     *
     * @param  array{order_item_id?: int, access_request_id?: int}  $why
     */
    public static function issue(int $userId, int $ioId, array $why = [], int $days = self::DAYS): ?string
    {
        if (! self::available() || $userId <= 0) {
            return null;
        }
        $master = DB::table('digital_object')->where('object_id', $ioId)->whereNull('parent_id')->orderBy('id')->first(['id']);
        if (! $master) {
            return null;
        }

        $live = DB::table('ahg_cart_downloads')
            ->where('user_id', $userId)->where('digital_object_id', $master->id)
            ->whereNull('revoked_at')->where('expires_at', '>', now())
            ->when(isset($why['order_item_id']), fn ($q) => $q->where('order_item_id', $why['order_item_id']))
            ->when(isset($why['access_request_id']), fn ($q) => $q->where('access_request_id', $why['access_request_id']))
            ->value('token');
        if ($live) {
            return $live;
        }

        $token = bin2hex(random_bytes(32));
        DB::table('ahg_cart_downloads')->insert([
            'token' => $token,
            'user_id' => $userId,
            'information_object_id' => $ioId,
            'digital_object_id' => $master->id,
            'order_item_id' => $why['order_item_id'] ?? null,
            'access_request_id' => $why['access_request_id'] ?? null,
            'expires_at' => now()->addDays($days),
            'download_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    /** Links for every record line of a paid order; the URL is kept on the line. */
    public static function issueForOrder(int $orderId): int
    {
        $order = DB::table('ahg_order')->where('id', $orderId)->first(['id', 'user_id']);
        if (! $order || ! $order->user_id) {
            return 0;
        }
        $n = 0;
        foreach (DB::table('ahg_order_item')->where('order_id', $orderId)->whereNotNull('archival_description_id')->get() as $item) {
            $token = self::issue((int) $order->user_id, (int) $item->archival_description_id, ['order_item_id' => (int) $item->id]);
            if ($token) {
                DB::table('ahg_order_item')->where('id', $item->id)->update([
                    'download_url' => route('cart.download', $token, false),
                    'download_expires_at' => now()->addDays(self::DAYS),
                ]);
                $n++;
            }
        }

        return $n;
    }

    /**
     * Links for every record an approved (or conditionally released) object
     * request names - one for a single record, one per record for a basket
     * request (heratio#1768); not their descendants. Returns the first token.
     */
    public static function issueForAccessRequest(int $requestId): ?string
    {
        $req = DB::table('access_request')->where('id', $requestId)->whereIn('status', ['approved', 'conditional'])->first(['id', 'user_id', 'expires_at']);
        if (! $req) {
            return null;
        }
        $days = $req->expires_at ? max(1, (int) ceil(now()->diffInDays($req->expires_at, false))) : self::DAYS;
        $first = null;
        foreach (DB::table('access_request_scope')->where('request_id', $requestId)->where('object_type', 'information_object')->pluck('object_id') as $ioId) {
            $token = self::issue((int) $req->user_id, (int) $ioId, ['access_request_id' => $requestId], $days);
            $first ??= $token;
        }

        return $first;
    }

    /** The live link row for a token, or null. */
    public static function find(string $token): ?object
    {
        if (! self::available() || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return DB::table('ahg_cart_downloads')
            ->where('token', $token)->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Live links for a user (for My requests and the request page):
     * [access_request_id => [information_object_id => token]].
     */
    public static function forAccessRequests(int $userId): array
    {
        if (! self::available()) {
            return [];
        }
        $out = [];
        foreach (DB::table('ahg_cart_downloads')
            ->where('user_id', $userId)->whereNotNull('access_request_id')
            ->whereNull('revoked_at')->where('expires_at', '>', now())
            ->orderBy('id')
            ->get(['access_request_id', 'information_object_id', 'token']) as $r) {
            $out[(int) $r->access_request_id][(int) $r->information_object_id] = $r->token;
        }

        return $out;
    }

    public static function recordDownload(object $link): void
    {
        DB::table('ahg_cart_downloads')->where('id', $link->id)->update([
            'downloaded_at' => now(), 'download_count' => DB::raw('download_count + 1'), 'updated_at' => now(),
        ]);
        if ($link->order_item_id) {
            DB::table('ahg_order_item')->where('id', $link->order_item_id)->increment('download_count');
        }
    }
}
