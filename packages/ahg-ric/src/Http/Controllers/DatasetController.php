<?php

/**
 * DatasetController - #1321 dataset descriptor + changelog endpoints.
 *
 * GET /api/ric/v1/dataset   -> DCAT/VoID JSON-LD descriptor of the RiC-O dataset
 * GET /api/ric/v1/changelog -> versioned change log + pinned standard versions
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

namespace AhgRic\Http\Controllers;

use AhgRic\Services\RicDatasetService;
use Illuminate\Http\JsonResponse;

class DatasetController extends \Illuminate\Routing\Controller
{
    public function __construct(private RicDatasetService $dataset)
    {
    }

    /** DCAT/VoID dataset descriptor (JSON-LD). */
    public function dataset(): JsonResponse
    {
        return response()->json($this->dataset->descriptor(), 200, [
            'Content-Type' => 'application/ld+json; charset=utf-8',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Versioned change log + pinned standard versions. */
    public function changelog(): JsonResponse
    {
        return response()->json($this->dataset->changelog(), 200, [], JSON_UNESCAPED_SLASHES);
    }
}
