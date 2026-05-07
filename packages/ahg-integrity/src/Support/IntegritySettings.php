<?php

/**
 * IntegritySettings - typed accessors for the integrity_* settings group
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

namespace AhgIntegrity\Support;

use AhgCore\Services\AhgSettingsService;

class IntegritySettings
{
    /**
     * Master gate. When off, scheduled fixity scans skip; on-demand
     * verifyObject still works (operator-triggered checks shouldn't be
     * silently disabled by a global flag that's typically set off during
     * incident response or platform migration).
     */
    public static function enabled(): bool
    {
        return AhgSettingsService::getBool('integrity_enabled', true);
    }

    /**
     * Default checksum algorithm. Validated against PHP's hash algo list at
     * read time so a malformed setting can't silently ship sha-foobar.
     */
    public static function defaultAlgorithm(): string
    {
        $algo = strtolower((string) AhgSettingsService::get('integrity_default_algorithm', 'sha256'));
        $available = hash_algos();
        return in_array($algo, $available, true) ? $algo : 'sha256';
    }

    public static function defaultBatchSize(): int
    {
        $n = AhgSettingsService::getInt('integrity_default_batch_size', 200);
        return max(1, min($n, 10000));
    }

    /**
     * Per-scan wall-clock cap, seconds. Used by IntegrityVerifyCommand to
     * abort a long batch before it wedges the worker; 0 disables the cap.
     */
    public static function defaultMaxRuntimeSeconds(): int
    {
        return max(0, AhgSettingsService::getInt('integrity_default_max_runtime', 120));
    }

    /**
     * Per-scan memory cap, megabytes. Translates to ini_set('memory_limit')
     * inside the command before the batch loop runs.
     */
    public static function defaultMaxMemoryMb(): int
    {
        return max(64, AhgSettingsService::getInt('integrity_default_max_memory', 512));
    }

    /**
     * Sleep between hash operations, milliseconds. Throttles disk + CPU on
     * busy production hosts so a fixity scan doesn't starve the web tier.
     */
    public static function ioThrottleMs(): int
    {
        return max(0, AhgSettingsService::getInt('integrity_io_throttle_ms', 10));
    }

    /**
     * Email + webhook destinations for fixity alerts. Empty string means
     * "no destination configured" - the corresponding notify path is then
     * a no-op without surfacing an error.
     */
    public static function alertEmail(): string
    {
        return trim((string) AhgSettingsService::get('integrity_alert_email', ''));
    }

    public static function webhookUrl(): string
    {
        return trim((string) AhgSettingsService::get('integrity_webhook_url', ''));
    }

    public static function notifyOnFailure(): bool
    {
        return AhgSettingsService::getBool('integrity_notify_on_failure', true);
    }

    public static function notifyOnMismatch(): bool
    {
        return AhgSettingsService::getBool('integrity_notify_on_mismatch', true);
    }

    /**
     * Auto-create a baseline checksum row on first scan of a digital_object
     * that doesn't yet have one. When off, scans only verify existing
     * baselines and ignore unbaselined files.
     */
    public static function autoBaseline(): bool
    {
        return AhgSettingsService::getBool('integrity_auto_baseline', true);
    }

    /**
     * Number of consecutive failures before a fixity job is dead-lettered.
     * The runner increments per-job failure count; when count reaches this
     * threshold, the job moves to integrity_ledger.status='dead_letter'
     * and stops being retried.
     */
    public static function deadLetterThreshold(): int
    {
        return max(1, AhgSettingsService::getInt('integrity_dead_letter_threshold', 3));
    }
}
