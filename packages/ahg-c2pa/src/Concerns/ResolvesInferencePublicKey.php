<?php

/**
 * ResolvesInferencePublicKey - shared inference-signing public-key lookup for C2PA.
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

namespace AhgC2pa\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve the public key for a key id (kid): the ai_inference_key table
 * first, then the on-disk inference-signing key if its fingerprint matches.
 * Was byte-identical in PublicCheckController and ProvenanceRecordService.
 */
trait ResolvesInferencePublicKey
{
    private function resolvePublicKey(string $kid): ?string
    {
        try {
            if (Schema::hasTable('ai_inference_key')) {
                $row = DB::table('ai_inference_key')->where('kid', $kid)->first(['public_key']);
                if ($row !== null && is_string($row->public_key) && $row->public_key !== '') {
                    return $row->public_key;
                }
            }
        } catch (\Throwable) {
            // fall through to filesystem
        }

        if (!function_exists('storage_path')) {
            return null;
        }
        $pkPath = storage_path('keys/inference-signing.pk');
        if (!is_readable($pkPath)) {
            return null;
        }
        $raw = @file_get_contents($pkPath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $candidateKid = substr(hash('sha256', $raw), 0, 16);
        return $candidateKid === $kid ? $raw : null;
    }
}
