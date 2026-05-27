<?php

/**
 * VocabularyApiController - JSON autocomplete endpoints for Museum.
 *
 * Exposes three GET endpoints used by Spectrum / Museum edit-form
 * autocomplete widgets:
 *
 *   GET /api/museum/getty-aat?q=...
 *   GET /api/museum/vocabulary-search?group=...&q=...
 *   GET /api/museum/authority-search?type=actor|term&q=...
 *
 * Issue: #739
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
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

namespace AhgMuseum\Controllers;

use AhgMuseum\Services\VocabularyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VocabularyApiController extends Controller
{
    public function __construct(private VocabularyService $service = new VocabularyService())
    {
    }

    public function gettyAat(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 10);

        if (mb_strlen(trim($q)) < 2) {
            return response()->json([
                'query'   => $q,
                'results' => [],
                'error'   => 'Query must be at least 2 characters',
            ], 200);
        }

        $results = $this->service->searchGettyAat($q, $limit);

        return response()->json([
            'query'    => $q,
            'source'   => 'getty-aat',
            'count'    => count($results),
            'results'  => $results,
            'cache_ttl' => VocabularyService::GETTY_CACHE_TTL,
        ]);
    }

    public function vocabularySearch(Request $request): JsonResponse
    {
        $group = (string) $request->query('group', '');
        $q     = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 20);

        if ($group === '') {
            return response()->json([
                'error'   => 'group parameter is required',
                'results' => [],
            ], 400);
        }

        $results = $this->service->searchVocabulary($group, $q, $limit);

        return response()->json([
            'query'   => $q,
            'group'   => $group,
            'count'   => count($results),
            'results' => $results,
        ]);
    }

    public function authoritySearch(Request $request): JsonResponse
    {
        $type  = (string) $request->query('type', 'actor');
        $q     = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 10);

        if (!in_array($type, ['actor', 'term'], true)) {
            return response()->json([
                'error'   => 'type must be one of: actor, term',
                'results' => [],
            ], 400);
        }

        if (mb_strlen(trim($q)) < 2) {
            return response()->json([
                'query'   => $q,
                'type'    => $type,
                'results' => [],
                'error'   => 'Query must be at least 2 characters',
            ], 200);
        }

        $results = $this->service->searchAuthority($type, $q, $limit);

        return response()->json([
            'query'   => $q,
            'type'    => $type,
            'count'   => count($results),
            'results' => $results,
        ]);
    }
}
