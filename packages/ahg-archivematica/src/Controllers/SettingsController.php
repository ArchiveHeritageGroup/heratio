<?php

/**
 * SettingsController - Archivematica connector admin settings for Heratio
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

namespace AhgArchivematica\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin settings page for the Archivematica connector. Reads and writes the
 * connector config keys directly to ahg_settings (setting_key / setting_value),
 * the same table the rest of the AHG Settings surface uses. Rendered under the
 * AHG Settings look-and-feel at /admin/archivematica.
 */
class SettingsController extends Controller
{
    /**
     * The config keys this page manages. Kept in one place so the form, the
     * read, and the save all agree.
     *
     * @var string[]
     */
    private array $keys = [
        'am_ss_url',
        'am_ss_api_key',
        'am_ss_username',
        'am_dashboard_url',
        'am_dashboard_api_key',
        'am_dashboard_username',
        'am_default_pipeline_uuid',
        'am_transfer_source_path',
        'am_dip_match_strategy',
    ];

    /**
     * Show the settings form.
     */
    public function edit(Request $request)
    {
        $settings = [];
        foreach ($this->keys as $key) {
            $settings[$key] = $this->getSetting($key);
        }
        // Sensible default for the match strategy dropdown.
        if (($settings['am_dip_match_strategy'] ?? '') === '') {
            $settings['am_dip_match_strategy'] = 'identifier';
        }

        return view('ahg-archivematica::settings', [
            'settings' => $settings,
            'menu'     => [],
        ]);
    }

    /**
     * Persist the settings form back to ahg_settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings'                          => ['required', 'array'],
            'settings.am_ss_url'                => ['nullable', 'string', 'max:500'],
            'settings.am_ss_api_key'            => ['nullable', 'string', 'max:500'],
            'settings.am_ss_username'           => ['nullable', 'string', 'max:255'],
            'settings.am_dashboard_url'         => ['nullable', 'string', 'max:500'],
            'settings.am_dashboard_api_key'     => ['nullable', 'string', 'max:500'],
            'settings.am_dashboard_username'    => ['nullable', 'string', 'max:255'],
            'settings.am_default_pipeline_uuid' => ['nullable', 'string', 'max:36'],
            'settings.am_transfer_source_path'  => ['nullable', 'string', 'max:1000'],
            'settings.am_dip_match_strategy'    => ['required', 'in:uuid,identifier,slug'],
        ]);

        $input = $validated['settings'];

        foreach ($this->keys as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $this->putSetting($key, (string) ($input[$key] ?? ''));
        }

        return redirect()
            ->route('archivematica.settings')
            ->with('success', __('Archivematica settings saved.'));
    }

    /**
     * Read a single key from ahg_settings. Empty string on miss.
     */
    private function getSetting(string $key): string
    {
        try {
            $val = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');

            return $val === null ? '' : (string) $val;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Upsert a single key into ahg_settings.
     */
    private function putSetting(string $key, string $value): void
    {
        try {
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        } catch (\Throwable $e) {
            // best-effort; surfaced to the user via the absence of a change.
        }
    }
}
