<?php

/**
 * RelatedRecordsController - the PUBLIC "Related records" discovery surface.
 *
 * Given ONE published archival record, surface the most-similar OTHER published
 * records by REUSING the existing semantic vector index (no new index, no AI
 * call of its own - see RelatedRecordsService). Each surfaced record is itself
 * published; the source record and the catalogue root are always excluded.
 *
 *   GET /related/{idOrSlug}.json  json - the top N most-similar published records
 *                                        as {id, slug, title, score, url}. CORS-
 *                                        open, cacheable, machine-readable. An
 *                                        empty / unavailable index yields an
 *                                        empty list at HTTP 200, never a 500.
 *   GET /related/{idOrSlug}       show - a small Bootstrap-5 / central-theme page
 *                                        listing the related records as cards,
 *                                        with an honest note on how relatedness
 *                                        is computed and a plain "no related
 *                                        records available" empty-state.
 *
 * Routing / catch-all safety: both paths are TWO-segment (/related/...), so the
 * single-segment /{slug} archival-record catch-all in
 * ahg-information-object-manage (constrained to one path segment, no slash) can
 * never intercept them. The routes are nonetheless bound in the provider's
 * register() via callAfterResolving('router') for the same precedence guarantee
 * as the other public discovery surfaces, and the .json route is declared BEFORE
 * the HTML route so a record slug ending in a literal ".json" can never be
 * swallowed by the HTML matcher. The {idOrSlug} matcher allows multi-segment
 * slugs. See memory/reference_slug_catchall_route_precedence.md.
 *
 * READ-ONLY and published-only: every record surfaced is published (status
 * type_id = 158 / status_id = 160), the catalogue root is excluded, and no table
 * is ever written. An unknown OR unpublished record 404s; an empty / unreachable
 * index degrades to an empty list (never a 500). International, jurisdiction-
 * neutral.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Controllers;

use AhgSemanticSearch\Services\RelatedRecordsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RelatedRecordsController extends Controller
{
    protected RelatedRecordsService $service;

    public function __construct()
    {
        $this->service = new RelatedRecordsService;
    }

    /**
     * Machine-readable twin (CORS-open, cacheable). The top N most-similar
     * published records for one published record. Unknown / unpublished record ->
     * 404. An empty / unavailable index -> HTTP 200 with an empty list, never a
     * 500.
     *
     * @param  string  $idOrSlug
     */
    public function json(Request $request, $idOrSlug): JsonResponse
    {
        $objectId = $this->service->resolvePublishedId((string) $idOrSlug);
        if ($objectId === null) {
            return response()
                ->json([
                    'surface' => 'related',
                    'error' => 'not_found',
                    'message' => 'No published record matches that identifier.',
                ], 404, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
        }

        $limit = $this->clampLimit($request->query('limit'));

        $related = [];
        try {
            $related = $this->service->relatedTo($objectId, $limit);
        } catch (\Throwable $e) {
            Log::info('[related] json('.$objectId.') failed: '.$e->getMessage());
            $related = [];
        }

        $payload = [
            'surface' => 'related',
            'description' => 'The most similar OTHER published records, by semantic similarity of the catalogue description (reusing the existing vector index).',
            'method' => 'semantic-vector-knn',
            'record_id' => $objectId,
            'count' => count($related),
            'related' => array_map(static function (array $r) {
                return [
                    'id' => $r['id'],
                    'slug' => $r['slug'],
                    'title' => $r['title'],
                    'score' => $r['score'],
                    'url' => $r['url'],
                ];
            }, $related),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Public HTML page: the related records as cards, with an honest note on how
     * relatedness is computed. Unknown / unpublished record -> 404. An empty /
     * unavailable index renders a calm empty-state, never a 500.
     *
     * @param  string  $idOrSlug
     */
    public function show(Request $request, $idOrSlug)
    {
        $objectId = $this->service->resolvePublishedId((string) $idOrSlug);
        if ($objectId === null) {
            abort(404, 'Record not found');
        }

        $limit = $this->clampLimit($request->query('limit'));

        $related = [];
        try {
            $related = $this->service->relatedTo($objectId, $limit);
        } catch (\Throwable $e) {
            Log::info('[related] show('.$objectId.') failed: '.$e->getMessage());
            $related = [];
        }

        $header = $this->service->recordHeader($objectId);

        return view('ahg-semantic-search::related.show', [
            'recordId' => $objectId,
            'recordTitle' => $header['title'],
            'recordSlug' => $header['slug'],
            'recordUrl' => $header['slug'] ? url('/'.$header['slug']) : null,
            'related' => $related,
            'count' => count($related),
        ]);
    }

    /**
     * Clamp the requested result count into [1, MAX_LIMIT], defaulting to
     * DEFAULT_LIMIT. Keeps the surface bounded.
     */
    protected function clampLimit($raw): int
    {
        $n = (int) ($raw ?? RelatedRecordsService::DEFAULT_LIMIT);
        if ($n <= 0) {
            $n = RelatedRecordsService::DEFAULT_LIMIT;
        }

        return max(1, min($n, RelatedRecordsService::MAX_LIMIT));
    }
}
