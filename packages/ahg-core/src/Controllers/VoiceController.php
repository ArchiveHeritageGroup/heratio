<?php

/**
 * VoiceController - Controller for Heratio
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

namespace AhgCore\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voice settings endpoint for the front-end voice widget.
 *
 * voiceCommands.js fetches /index.php/ahgVoice/getSettings at init and uses
 * the returned object to apply user-configured language, speech rate,
 * confidence threshold, hover-read behaviour and floating-button visibility.
 * The endpoint had no controller before — closes issue #94.
 *
 * Response shape matches what voiceCommands.js already expects:
 *   { success: true, settings: { voice_enabled, voice_language, ... } }
 *
 * Values are stringified ('true'/'false', numeric strings) because the JS
 * compares them as strings. The defaults below mirror the seeded values in
 * ahg_settings so a fresh install behaves the same whether or not the
 * operator has visited the Voice & AI settings page.
 */
class VoiceController extends Controller
{
    public function getSettings(Request $request)
    {
        $defaults = [
            'voice_enabled'              => 'true',
            'voice_language'             => 'en-US',
            'voice_confidence_threshold' => '0.7',
            'voice_speech_rate'          => '1.0',
            'voice_continuous_listening' => 'false',
            'voice_show_floating_btn'    => 'true',
            'voice_hover_read_enabled'   => 'false',
            'voice_hover_read_delay'     => '400',
        ];

        $settings = $defaults;
        if (Schema::hasTable('ahg_settings')) {
            $rows = DB::table('ahg_settings')
                ->where('setting_group', 'voice_ai')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
            foreach ($rows as $key => $value) {
                $settings[$key] = (string) $value;
            }
        }

        return response()->json([
            'success'  => true,
            'settings' => $settings,
        ]);
    }
}
