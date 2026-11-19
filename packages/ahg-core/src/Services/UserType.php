<?php

/**
 * UserType - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1769 / #1770: the type a user belongs to, and what it allows.
 *
 * A user's type is, in order:
 *  1. the type an active subscription grants (ahg_user_subscription), else
 *  2. the type of their approved, unexpired researcher registration, else
 *  3. "General Public" (code public).
 * Types are the researcher types (Research > Researcher types), so one list
 * drives registration, rights and prices:
 *  - can_request_reproductions: may send a basket request or accept a quotation;
 *  - can_remote_access: may fetch protected originals online (Protect originals);
 *  - discount_percent / free_access: what the type pays in the cart.
 */
class UserType
{
    private static array $memo = [];

    public static function available(): bool
    {
        return Schema::hasTable('research_researcher_type');
    }

    /** The user's type row, or null when no types exist. */
    public static function forUser(?int $userId): ?object
    {
        if (! self::available()) {
            return null;
        }
        $key = (int) $userId;
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $typeId = null;
        if ($userId) {
            if (Schema::hasTable('ahg_user_subscription')) {
                $typeId = DB::table('ahg_user_subscription as s')
                    ->join('ahg_subscription_tier as t', 't.id', '=', 's.tier_id')
                    ->where('s.user_id', $userId)->where('s.status', 'active')
                    ->where('s.starts_at', '<=', now())->where('s.ends_at', '>', now())
                    ->orderByDesc('s.ends_at')->value('t.researcher_type_id');
            }
            $typeId ??= DB::table('research_researcher')
                ->where('user_id', $userId)->where('status', 'approved')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString()))
                ->orderByDesc('id')->value('researcher_type_id');
        }
        $row = $typeId ? DB::table('research_researcher_type')->where('id', $typeId)->where('is_active', 1)->first() : null;

        return self::$memo[$key] = $row ?? DB::table('research_researcher_type')->where('code', 'public')->first();
    }

    /** Does the user's type carry this flag (can_request_reproductions, can_remote_access, can_export_data)? */
    public static function allows(?int $userId, string $flag): bool
    {
        $type = self::forUser($userId);

        // No types configured: nothing to restrict.
        return $type === null || (bool) ($type->{$flag} ?? true);
    }

    /** The type whose email domains match this address (most specific first), or null. */
    public static function forEmail(string $email): ?object
    {
        if (! self::available() || ! Schema::hasColumn('research_researcher_type', 'email_domains')) {
            return null;
        }
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '') {
            return null;
        }
        $best = null;
        $bestLen = 0;
        foreach (DB::table('research_researcher_type')->where('is_active', 1)->whereNotNull('email_domains')->get() as $t) {
            foreach (preg_split('/[\s,;]+/', strtolower((string) $t->email_domains), -1, PREG_SPLIT_NO_EMPTY) as $d) {
                $d = ltrim($d, '.@');
                if (($domain === $d || str_ends_with($domain, '.'.$d)) && strlen($d) > $bestLen) {
                    [$best, $bestLen] = [$t, strlen($d)];
                }
            }
        }

        return $best;
    }

    /** A price as the user's type pays it: free, discounted or unchanged. */
    public static function price(?int $userId, float $price): float
    {
        $type = self::forUser($userId);
        if (! $type || $price <= 0) {
            return max(0, $price);
        }
        if (! empty($type->free_access)) {
            return 0.0;
        }
        $pct = min(100, max(0, (float) ($type->discount_percent ?? 0)));

        return round($price * (100 - $pct) / 100, 2);
    }

    public static function forget(): void
    {
        self::$memo = [];
    }
}
