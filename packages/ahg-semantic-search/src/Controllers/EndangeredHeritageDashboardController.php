<?php

/**
 * EndangeredHeritageDashboardController - the PUBLIC endangered-heritage
 * dashboard, the next slice of the "race against loss" (north-star heratio#1205).
 *
 *   GET /endangered-heritage        (name endangered.dashboard)      - HTML dashboard
 *   GET /endangered-heritage.json   (name endangered.dashboard.json) - machine read
 *
 * A read-only aggregate VIEW over the existing at-risk register
 * (endangered_heritage_item, owned by EndangeredHeritageService). It builds NO new
 * table and writes nothing: it asks the service for a single cheap aggregate
 * (counts by risk_category, by urgency, the capture-progress split
 * captured/in-progress/flagged, the grand total, and a short, bounded tail of the
 * highest-priority outstanding PUBLISHED items), then renders big numbers and
 * simple CSS bars (no charting library). Each priority row links onward to the
 * public /at-risk register so a reader can act.
 *
 * Both routes are bound (register() + callAfterResolving('router')) on the single-
 * segment public path /endangered-heritage so they win the match ahead of the
 * single-segment /{slug} archival-record catch-all in ahg-information-object-
 * manage - see memory/reference_slug_catchall_route_precedence.md. The .json
 * suffix keeps the machine route a distinct, non-colliding path.
 *
 * Framing is deliberately factual and non-alarmist: a flag records a curatorial
 * judgement that an item should be captured sooner rather than later, and the
 * documented reason why - it is never a prediction of certain loss or a claim
 * about any institution's stewardship. The standing disclaimer
 * (EndangeredHeritageService::DISCLAIMER) is surfaced prominently. When nothing
 * has been flagged the page renders a dignified empty-state; it never 500s. The
 * JSON response is CORS-open (read-only public data) for re-use by partner sites
 * and dashboards.
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

use AhgSemanticSearch\Services\EndangeredHeritageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EndangeredHeritageDashboardController extends Controller
{
    protected EndangeredHeritageService $service;

    public function __construct()
    {
        $this->service = new EndangeredHeritageService;
    }

    /**
     * Public HTML dashboard. Read-only aggregate over the at-risk register. Any
     * failure (missing table, DB hiccup) degrades to the zeroed aggregate and the
     * empty-state - this page never 500s.
     */
    public function index()
    {
        $data = $this->safeDashboard();

        return view('ahg-semantic-search::endangered.dashboard', [
            'data' => $data,
            'risks' => EndangeredHeritageService::RISK_CATEGORIES,
            'disclaimer' => EndangeredHeritageService::DISCLAIMER,
        ]);
    }

    /**
     * Machine-readable aggregate. CORS-open (public read-only data) so partner
     * sites and dashboards can consume it. Same read-only aggregate as the HTML
     * page; never 500s.
     */
    public function json(): JsonResponse
    {
        $data = $this->safeDashboard();

        $payload = [
            'feature' => 'endangered-heritage-dashboard',
            'north_star' => 'heratio#1205',
            'generated_at' => now()->toIso8601String(),
            'disclaimer' => EndangeredHeritageService::DISCLAIMER,
            'available' => (bool) ($data['available'] ?? false),
            'total' => (int) ($data['total'] ?? 0),
            'capture_progress' => [
                'captured' => (int) ($data['captured'] ?? 0),
                'in_progress' => (int) ($data['in_progress'] ?? 0),
                'flagged' => (int) ($data['flagged'] ?? 0),
                'outstanding' => (int) ($data['outstanding'] ?? 0),
                'captured_pct' => (int) ($data['capture_progress_pct'] ?? 0),
            ],
            'by_risk' => array_map(static function ($r) {
                return [
                    'risk_category' => (string) ($r['key'] ?? ''),
                    'label' => (string) ($r['label'] ?? ''),
                    'count' => (int) ($r['count'] ?? 0),
                ];
            }, is_array($data['by_risk'] ?? null) ? $data['by_risk'] : []),
            'by_urgency' => array_map(static function ($u) {
                return [
                    'urgency' => (string) ($u['key'] ?? ''),
                    'label' => (string) ($u['label'] ?? ''),
                    'count' => (int) ($u['count'] ?? 0),
                ];
            }, is_array($data['by_urgency'] ?? null) ? $data['by_urgency'] : []),
            'public_register_total' => (int) ($data['public_total'] ?? 0),
            'highest_priority' => array_map(static function ($p) {
                $slug = $p['item_slug'] ?? null;

                return [
                    'item_ref' => (int) ($p['item_ref'] ?? 0),
                    'item_title' => $p['item_title'] ?? null,
                    'risk_category' => (string) ($p['risk_category'] ?? ''),
                    'risk_label' => (string) ($p['risk_meta']['label'] ?? ''),
                    'urgency' => (string) ($p['urgency'] ?? ''),
                    'urgency_label' => (string) ($p['urgency_meta']['label'] ?? ''),
                    'capture_status' => (string) ($p['capture_status'] ?? ''),
                    'record_url' => ($slug !== null && $slug !== '') ? url('/'.$slug) : null,
                ];
            }, is_array($data['priority'] ?? null) ? $data['priority'] : []),
            'register_url' => url('/at-risk'),
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Fetch the dashboard aggregate, never throwing. Any failure resolves to the
     * fully-zeroed (unavailable) shape so both surfaces degrade gracefully.
     *
     * @return array<string,mixed>
     */
    protected function safeDashboard(): array
    {
        try {
            return $this->service->dashboard();
        } catch (\Throwable $e) {
            Log::info('[endangered] dashboard build failed: '.$e->getMessage());

            return [
                'available' => false,
                'total' => 0,
                'by_risk' => [],
                'by_urgency' => [],
                'captured' => 0,
                'in_progress' => 0,
                'flagged' => 0,
                'outstanding' => 0,
                'capture_progress_pct' => 0,
                'public_total' => 0,
                'priority' => [],
            ];
        }
    }
}
