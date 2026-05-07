<?php

/**
 * SpectrumSettings - typed accessors for the spectrum_* settings group
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

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Reads from `ahg_settings` (setting_group='spectrum'). Pre-#91 this class
 * read from a non-existent `spectrum` table and every accessor silently
 * returned its default; the existing EnsureSpectrumEnabled middleware was
 * effectively no-op as a result. Defaults below mirror the install seed
 * shown in /admin/ahgSettings/spectrum so behaviour without DB rows matches
 * what the form shows the operator before they save.
 *
 * Closes #91 phase 2 (the 8 unwired keys + the master gate that was
 * functionally unwired due to the table-name bug).
 */
class SpectrumSettings
{
    public function get(string $key, $default = null)
    {
        try {
            if (!Schema::hasTable('ahg_settings')) {
                return $default;
            }
            $val = DB::table('ahg_settings')
                ->where('setting_group', 'spectrum')
                ->where('setting_key', $key)
                ->value('setting_value');
            return ($val === null || $val === '') ? $default : $val;
        } catch (\Throwable $e) {
            Log::warning('SpectrumSettings::get error: ' . $e->getMessage());
            return $default;
        }
    }

    private function bool(string $key, bool $default = false): bool
    {
        $v = (string) $this->get($key, $default ? 'true' : 'false');
        return in_array($v, ['true', '1', 1, true], true);
    }

    private function int(string $key, int $default = 0): int
    {
        $v = $this->get($key);
        return $v !== null && is_numeric($v) ? (int) $v : $default;
    }

    // ── Master gate ────────────────────────────────────────────────────

    public function isEnabled(): bool
    {
        return $this->bool('spectrum_enabled', false);
    }

    // ── Currency + loan defaults ───────────────────────────────────────

    public function defaultCurrency(): string
    {
        return (string) $this->get('spectrum_default_currency', 'USD');
    }

    public function defaultLoanPeriodDays(): int
    {
        return $this->int('spectrum_loan_default_period', 30);
    }

    // ── Reminder windows (#91) ─────────────────────────────────────────

    /**
     * Days after the last valuation_date when a reminder email should fire.
     * 0 disables the reminder schedule (the SpectrumValuationReminderCommand
     * no-ops in that case).
     */
    public function valuationReminderDays(): int
    {
        return $this->int('spectrum_valuation_reminder_days', 365);
    }

    /**
     * Days between condition checks. 0 disables the reminder schedule.
     */
    public function conditionCheckIntervalDays(): int
    {
        return $this->int('spectrum_condition_check_interval', 90);
    }

    // ── Workflow toggles (#91) ─────────────────────────────────────────

    /**
     * When true (default), completing object_entry / acquisition / loans_*
     * auto-triggers a location_movement procedure. When false, operator
     * has to start the movement manually from the workflow page.
     */
    public function autoCreateMovement(): bool
    {
        return $this->bool('spectrum_auto_create_movement', true);
    }

    /**
     * When true, condition-check create/update requires at least one photo
     * (enforced by ConditionService::uploadPhoto + the form validator).
     */
    public function requirePhotos(): bool
    {
        return $this->bool('spectrum_require_photos', false);
    }

    public function enableBarcodes(): bool
    {
        return $this->bool('spectrum_enable_barcodes', false);
    }

    public function requireValuation(): bool
    {
        return $this->bool('spectrum_require_valuation', false);
    }

    public function requireInsurance(): bool
    {
        return $this->bool('spectrum_require_insurance', false);
    }

    public function autoNumbering(): bool
    {
        return $this->bool('spectrum_auto_numbering', false);
    }

    // ── Notifications (already wired pre-#91 in SpectrumNotificationService) ─

    public function emailNotifications(): bool
    {
        return $this->bool('spectrum_email_notifications', true);
    }
}
