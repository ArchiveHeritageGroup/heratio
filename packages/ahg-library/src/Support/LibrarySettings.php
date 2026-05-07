<?php

/**
 * LibrarySettings - typed accessors for the library_* settings group
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

namespace AhgLibrary\Support;

use AhgCore\Services\AhgSettingsService;

/**
 * Defaults mirror the form values in
 * packages/ahg-settings/resources/views/library-group-settings.blade.php so
 * the runtime behaviour matches what the operator sees in the form before
 * they save anything.
 */
class LibrarySettings
{
    // ── Circulation ────────────────────────────────────────────────────

    public static function defaultLoanDays(): int
    {
        return AhgSettingsService::getInt('library_default_loan_days', 14);
    }

    public static function maxRenewals(): int
    {
        return AhgSettingsService::getInt('library_max_renewals', 2);
    }

    public static function autoFine(): bool
    {
        return AhgSettingsService::getBool('library_auto_fine', true);
    }

    public static function currency(): string
    {
        $val = (string) AhgSettingsService::get('library_currency', 'ZAR');
        return $val !== '' ? $val : 'ZAR';
    }

    // ── Holds ──────────────────────────────────────────────────────────

    public static function holdExpiryDays(): int
    {
        return AhgSettingsService::getInt('library_hold_expiry_days', 7);
    }

    public static function holdMaxQueue(): int
    {
        return AhgSettingsService::getInt('library_hold_max_queue', 50);
    }

    public static function autoExpireHolds(): bool
    {
        return AhgSettingsService::getBool('library_auto_expire_holds', true);
    }

    // ── Patrons ────────────────────────────────────────────────────────

    public static function autoExpirePatrons(): bool
    {
        return AhgSettingsService::getBool('library_auto_expire_patrons', true);
    }

    public static function patronDefaultType(): string
    {
        $val = (string) AhgSettingsService::get('library_patron_default_type', 'public');
        return $val !== '' ? $val : 'public';
    }

    public static function patronMaxCheckouts(): int
    {
        return AhgSettingsService::getInt('library_patron_max_checkouts', 5);
    }

    public static function patronMaxRenewals(): int
    {
        return AhgSettingsService::getInt('library_patron_max_renewals', 2);
    }

    public static function patronMaxHolds(): int
    {
        return AhgSettingsService::getInt('library_patron_max_holds', 3);
    }

    public static function patronMembershipMonths(): int
    {
        return AhgSettingsService::getInt('library_patron_membership_months', 12);
    }

    public static function patronFineThreshold(): float
    {
        $raw = AhgSettingsService::get('library_patron_fine_threshold', '50.00');
        return is_numeric($raw) ? (float) $raw : 50.00;
    }

    public static function patronExpiryGraceDays(): int
    {
        return AhgSettingsService::getInt('library_patron_expiry_grace_days', 7);
    }

    // ── Barcodes ───────────────────────────────────────────────────────

    public static function barcodeAutoGenerate(): bool
    {
        return AhgSettingsService::getBool('library_barcode_auto_generate', true);
    }

    // ── OPAC (public catalogue) ────────────────────────────────────────

    public static function opacEnabled(): bool
    {
        return AhgSettingsService::getBool('library_opac_enabled', true);
    }

    public static function opacResultsPerPage(): int
    {
        $n = AhgSettingsService::getInt('library_opac_results_per_page', 20);
        return max(5, min($n, 100));
    }

    public static function opacShowAvailability(): bool
    {
        return AhgSettingsService::getBool('library_opac_show_availability', true);
    }

    public static function opacShowCovers(): bool
    {
        return AhgSettingsService::getBool('library_opac_show_covers', true);
    }

    public static function opacAllowHolds(): bool
    {
        return AhgSettingsService::getBool('library_opac_allow_holds', true);
    }

    public static function opacNewArrivalsCount(): int
    {
        return AhgSettingsService::getInt('library_opac_new_arrivals_count', 12);
    }

    public static function opacPopularDays(): int
    {
        return AhgSettingsService::getInt('library_opac_popular_days', 30);
    }
}
