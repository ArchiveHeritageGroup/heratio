<?php

/**
 * archivematica.php - configuration for Heratio
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

/*
|--------------------------------------------------------------------------
| Archivematica connector configuration
|--------------------------------------------------------------------------
|
| Every value is read from the ahg_settings table first (where the admin
| settings page at /admin/archivematica writes it) and falls back to an
| environment variable, then to a hard default. Credentials must live in
| ahg_settings or .env only - never commit a key here.
|
| The DB lookup is wrapped so a missing table / early boot (e.g. before
| migrations, or during config:cache with no DB) never fatals; it simply
| falls through to the env value.
|
*/

$amSetting = static function (string $key, string $env, ?string $default = null) {
    $value = null;

    try {
        if (class_exists(\Illuminate\Support\Facades\DB::class)) {
            $value = \Illuminate\Support\Facades\DB::table('ahg_settings')
                ->where('setting_key', $key)
                ->value('setting_value');
        }
    } catch (\Throwable $e) {
        // ahg_settings not available yet (fresh install / no DB / config cache).
        $value = null;
    }

    if ($value === null || $value === '') {
        $value = env($env, $default);
    }

    return $value;
};

return [

    // --- Storage Service (SS) API - where AIPs/DIPs land ---
    'am_ss_url'      => $amSetting('am_ss_url', 'AM_SS_URL'),
    'am_ss_api_key'  => $amSetting('am_ss_api_key', 'AM_SS_API_KEY'),
    'am_ss_username' => $amSetting('am_ss_username', 'AM_SS_USERNAME'),

    // --- Dashboard API - drives processing ---
    'am_dashboard_url'      => $amSetting('am_dashboard_url', 'AM_DASHBOARD_URL'),
    'am_dashboard_api_key'  => $amSetting('am_dashboard_api_key', 'AM_DASHBOARD_API_KEY'),
    'am_dashboard_username' => $amSetting('am_dashboard_username', 'AM_DASHBOARD_USERNAME'),

    // --- Transfer defaults ---
    'am_default_pipeline_uuid' => $amSetting('am_default_pipeline_uuid', 'AM_DEFAULT_PIPELINE_UUID'),
    'am_transfer_source_path'  => $amSetting('am_transfer_source_path', 'AM_TRANSFER_SOURCE_PATH'),

    // --- DIP -> information_object matching strategy: uuid | identifier | slug ---
    'am_dip_match_strategy' => $amSetting('am_dip_match_strategy', 'AM_DIP_MATCH_STRATEGY', 'identifier'),

];
