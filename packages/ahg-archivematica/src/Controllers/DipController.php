<?php

/**
 * DipController - inbound DIP push endpoint (Direction 1, mode B) for Heratio.
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

use AhgArchivematica\Jobs\IngestDipFromSs;
use AhgArchivematica\Services\DipIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/archivematica/dip
 *
 * Two accepted shapes (Direction 1, push mode):
 *
 *   1. Multipart upload of a DIP tarball  (field name: `dip`)
 *      -> unpacked + ingested inline; returns the ingest summary.
 *
 *   2. A Storage Service callback carrying a package UUID
 *      (JSON/form: `uuid` | `package_uuid` | `dip_uuid`)
 *      -> queues an IngestDipFromSs job that pulls the DIP from the SS.
 *
 * Route key-auth (api.auth middleware) is applied in routes/api.php.
 */
class DipController extends Controller
{
    public function receive(Request $request, DipIngestService $service): JsonResponse
    {
        // --- Mode 1: uploaded DIP tarball ---
        if ($request->hasFile('dip')) {
            $file = $request->file('dip');
            if (! $file->isValid()) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Uploaded DIP file is not valid: ' . $file->getErrorMessage(),
                ], 422);
            }

            $dipUuid = $this->extractUuid($request);
            $stored = $file->getRealPath()
                ?: $file->move($file->getPath(), $file->getClientOriginalName())->getRealPath();

            try {
                $summary = $service->ingestUploadedTarball($stored, $dipUuid);
            } catch (\Throwable $e) {
                Log::error('[ahg-archivematica] DIP tarball ingest failed: ' . $e->getMessage());

                return response()->json([
                    'ok'    => false,
                    'error' => $e->getMessage(),
                ], 500);
            }

            return response()->json([
                'ok'      => true,
                'mode'    => 'upload',
                'summary' => $summary,
            ]);
        }

        // --- Mode 2: SS callback with a package UUID ---
        $dipUuid = $this->extractUuid($request);
        if ($dipUuid === null) {
            return response()->json([
                'ok'    => false,
                'error' => 'Provide a DIP tarball (field "dip") or a package uuid.',
            ], 422);
        }

        IngestDipFromSs::dispatch($dipUuid);

        return response()->json([
            'ok'       => true,
            'mode'     => 'queued',
            'dip_uuid' => $dipUuid,
            'message'  => 'DIP ingest queued.',
        ], 202);
    }

    /**
     * Pull a DIP UUID out of the request under any of the accepted keys.
     */
    private function extractUuid(Request $request): ?string
    {
        foreach (['uuid', 'package_uuid', 'dip_uuid'] as $key) {
            $val = $request->input($key);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return null;
    }
}
