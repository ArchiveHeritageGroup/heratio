<?php

/**
 * TimelineController - the PUBLIC "Collection timeline" discovery surface.
 *
 * The distribution of PUBLISHED records across time: an engaging way to browse the
 * holdings by period. Records are bucketed by century (drilled to decade where the
 * data is dense enough), derived from each record's earliest event start_date.
 * Records with no usable date are reported honestly as an "undated" group, never
 * dropped. Each period bar links into the canonical GLAM browse, filtered to that
 * date range.
 *
 *   GET /timeline       index - horizontal bars (CSS only) per period, sized by
 *                               count, each clickable into /glam/browse filtered to
 *                               that date range; an honest undated group; a calm
 *                               empty-state.
 *   GET /timeline.json  json  - the same buckets as machine data (CORS-open):
 *                               {period_label, from_year, to_year, count, browse_url}.
 *
 * READ-ONLY and published-only: every record counted is published (status
 * type_id = 158 / status_id = 160), the catalogue root (id = 1) is excluded, and
 * no table is ever written. Every path degrades to a calm empty-state rather than
 * a 500. International, jurisdiction-neutral - the only calendar assumption is the
 * Gregorian year already stored in the DATE columns.
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

use AhgSemanticSearch\Services\TimelineService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TimelineController extends Controller
{
    protected TimelineService $service;

    public function __construct()
    {
        $this->service = new TimelineService;
    }

    /**
     * Public landing: the collection timeline. Never 500s - any failure renders
     * the calm empty-state.
     */
    public function index()
    {
        $timeline = [
            'centuries' => [],
            'undated' => ['count' => 0],
            'dated_total' => 0,
            'max_count' => 0,
            'min_year' => null,
            'max_year' => null,
        ];

        try {
            $timeline = $this->service->timeline();
        } catch (\Throwable $e) {
            Log::info('[timeline] index failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::timeline.index', [
            'timeline' => $timeline,
        ]);
    }

    /**
     * Machine-readable bucket list (CORS-open, cacheable). Never 500s - degrades
     * to an empty buckets array.
     */
    public function json(): JsonResponse
    {
        $buckets = [];
        try {
            $buckets = $this->service->buckets();
        } catch (\Throwable $e) {
            Log::info('[timeline] json failed: '.$e->getMessage());
        }

        $payload = [
            'surface' => 'timeline',
            'description' => 'The distribution of published records across time, bucketed by century (drilled to decade where dense), with an honest undated group.',
            'date_source' => 'event.start_date (earliest dated event per record)',
            'count' => count($buckets),
            'buckets' => array_map(static function (array $b) {
                return [
                    'period_label' => $b['period_label'],
                    'from_year' => $b['from_year'],
                    'to_year' => $b['to_year'],
                    'count' => $b['count'],
                    'browse_url' => $b['browse_url'],
                ];
            }, $buckets),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
