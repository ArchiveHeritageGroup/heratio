<?php

/**
 * RepatriationDashboardController - the PUBLIC repatriation dashboard, the next
 * slice of the repatriation engine (north-star heratio#1207).
 *
 *   GET /repatriation        (name repatriation.dashboard)   - HTML dashboard
 *   GET /repatriation.json   (name repatriation.dashboard.json) - machine read
 *
 * A read-only aggregate VIEW over the existing claims register
 * (displaced_heritage_claim, owned by RepatriationClaimService). It builds NO new
 * table and writes nothing: it asks the service for a single cheap aggregate
 * (counts by claim_status, top origin places / claimant communities, the virtual-
 * return vs physically-returned split, the grand total, and a short recent-
 * activity tail), then renders big numbers and simple CSS bars. Each recent entry
 * and each status row links onward to a claim's own /virtual-return/{id} page.
 *
 * Both routes are bound (register() + callAfterResolving('router')) on the
 * single-segment public path /repatriation so they win the match ahead of the
 * single-segment /{slug} archival-record catch-all in ahg-information-object-
 * manage - see memory/reference_slug_catchall_route_precedence.md. The .json
 * suffix keeps the machine route a distinct, non-colliding path.
 *
 * Sensitive subject matter. The dashboard is factual, non-partisan and
 * jurisdiction-neutral: a claim status describes WHERE A DIALOGUE STANDS, never a
 * legal determination. The standing disclaimer from the claim feature
 * (RepatriationClaimService::DISCLAIMER) is surfaced prominently. When no claims
 * have been recorded the page renders a dignified empty-state; it never 500s. The
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

use AhgSemanticSearch\Services\RepatriationClaimService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RepatriationDashboardController extends Controller
{
    protected RepatriationClaimService $service;

    public function __construct()
    {
        $this->service = new RepatriationClaimService;
    }

    /**
     * Public HTML dashboard. Read-only aggregate over the claims register. Any
     * failure (missing table, DB hiccup) degrades to the zeroed aggregate and the
     * empty-state - this page never 500s.
     */
    public function index()
    {
        $data = $this->safeDashboard();

        return view('ahg-semantic-search::repatriation.dashboard', [
            'data' => $data,
            'disclaimer' => RepatriationClaimService::DISCLAIMER,
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
            'feature' => 'repatriation-dashboard',
            'north_star' => 'heratio#1207',
            'generated_at' => now()->toIso8601String(),
            'disclaimer' => RepatriationClaimService::DISCLAIMER,
            'available' => (bool) ($data['available'] ?? false),
            'total' => (int) ($data['total'] ?? 0),
            'by_status' => array_map(static function ($s) {
                return [
                    'status' => (string) ($s['key'] ?? ''),
                    'label' => (string) ($s['label'] ?? ''),
                    'count' => (int) ($s['count'] ?? 0),
                ];
            }, is_array($data['by_status'] ?? null) ? $data['by_status'] : []),
            'by_origin_place' => is_array($data['by_origin'] ?? null) ? $data['by_origin'] : [],
            'by_community' => is_array($data['by_community'] ?? null) ? $data['by_community'] : [],
            'virtual_return' => (int) ($data['virtual_return'] ?? 0),
            'returned' => (int) ($data['returned'] ?? 0),
            'in_dialogue' => (int) ($data['in_dialogue'] ?? 0),
            'recent' => array_map(static function ($r) {
                return [
                    'claim_id' => (int) ($r['id'] ?? 0),
                    'item_title' => $r['item_title'] ?? null,
                    'origin_place' => $r['origin_place'] ?? null,
                    'claimant_community' => $r['claimant_community'] ?? null,
                    'current_holder' => $r['current_holder'] ?? null,
                    'claim_status' => (string) ($r['claim_status'] ?? ''),
                    'status_label' => (string) ($r['status_meta']['label'] ?? ''),
                    'updated_at' => $r['updated_at'] ?? null,
                    'virtual_return_url' => (int) ($r['id'] ?? 0) > 0 ? url('/virtual-return/'.(int) $r['id']) : null,
                ];
            }, is_array($data['recent'] ?? null) ? $data['recent'] : []),
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
            Log::info('[repatriation] dashboard build failed: '.$e->getMessage());

            return [
                'available' => false,
                'total' => 0,
                'by_status' => [],
                'raw_status_counts' => [],
                'by_origin' => [],
                'by_community' => [],
                'virtual_return' => 0,
                'returned' => 0,
                'in_dialogue' => 0,
                'recent' => [],
            ];
        }
    }
}
