<?php

/**
 * DataProtectionSettings - central reader for /admin/ahgSettings/dataProtection
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

namespace AhgPrivacy\Support;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Closes audit issue #72. Wraps the seven dp_* keys that the Data
 * Protection settings tile writes to ahg_settings, with regulation-keyed
 * lookups so per-jurisdiction extensions (dp_gdpr_response_days etc.)
 * slot into the same API without touching consumers.
 *
 * Defaults match the seeded values so a fresh install behaves identically
 * pre-/post- wiring.
 */
class DataProtectionSettings
{
    /** Master toggle. When false the whole /admin/privacy/* surface 404s. */
    public static function enabled(): bool
    {
        return AhgSettingsService::getBool('dp_enabled', true);
    }

    /**
     * Default regulation for new DSARs. Used when neither the public form
     * nor the admin form supplies a jurisdiction. Returns a privacy_jurisdiction
     * code (e.g. 'popia', 'gdpr'); falls through to 'popia' on a fresh
     * install.
     */
    public static function defaultRegulation(): string
    {
        $r = trim((string) AhgSettingsService::get('dp_default_regulation', 'popia'));
        return $r !== '' ? strtolower($r) : 'popia';
    }

    /** Email notified on DSAR creation + breach add. Empty string = no notification. */
    public static function notifyEmail(): string
    {
        return trim((string) AhgSettingsService::get('dp_notify_email', ''));
    }

    /**
     * Whether the daily overdue-DSAR cron should email when DSARs go overdue.
     * Default false so an operator who hasn't set notify_email never gets
     * surprise mail from the cron.
     */
    public static function notifyOverdue(): bool
    {
        return AhgSettingsService::getBool('dp_notify_overdue', false);
    }

    /**
     * Response window in days for the given jurisdiction. Generic
     * convention: dp_<juris>_response_days wins when set (popia 30, gdpr 30,
     * ccpa 45 are seeded out of the box), falling through to
     * privacy_jurisdiction.dsar_days for unmapped codes, then 30 as the
     * cross-jurisdiction default.
     */
    public static function responseDaysFor(string $jurisdiction): int
    {
        $j = strtolower(trim($jurisdiction));
        // Per-jurisdiction setting wins. Sanitise the slug so no malicious
        // jurisdiction string can build a setting key with metacharacters.
        if (preg_match('/^[a-z0-9_]{1,30}$/', $j)) {
            $key  = "dp_{$j}_response_days";
            $days = AhgSettingsService::getInt($key, 0);
            if ($days > 0) {
                return $days;
            }
        }
        if (Schema::hasTable('privacy_jurisdiction')) {
            $row = DB::table('privacy_jurisdiction')->where('code', $j)->first(['dsar_days']);
            if ($row && (int) $row->dsar_days > 0) {
                return (int) $row->dsar_days;
            }
        }
        return 30;
    }

    /**
     * Standard or special-category fee for the given jurisdiction. Returns
     * null when no fee applies (jurisdiction other than popia, or fee key
     * unset / set to 0).
     */
    public static function feeFor(string $jurisdiction, bool $special = false): ?float
    {
        if (strtolower(trim($jurisdiction)) !== 'popia') {
            return null;
        }
        $key = $special ? 'dp_popia_fee_special' : 'dp_popia_fee';
        $raw = AhgSettingsService::get($key, null);
        if ($raw === null || $raw === '') {
            return null;
        }
        $f = (float) $raw;
        return $f > 0 ? $f : null;
    }

    /** Aggregator for any future JS / API consumer. */
    public static function payload(): array
    {
        return [
            'enabled'             => self::enabled(),
            'default_regulation'  => self::defaultRegulation(),
            'notify_email'        => self::notifyEmail(),
            'notify_overdue'      => self::notifyOverdue(),
            'popia_response_days' => self::responseDaysFor('popia'),
            'popia_fee'           => self::feeFor('popia', false),
            'popia_fee_special'   => self::feeFor('popia', true),
        ];
    }
}
