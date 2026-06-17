<?php

/**
 * EndangeredApiController - PUBLIC, read-only JSON API over THIS instance's
 * endangered-heritage register (north-star heratio#1205, the "race against
 * loss"). The federation EXPOSE side: it is the endpoint that PEER instances
 * query to learn what THIS instance has flagged as at-risk, so a cross-
 * institution at-risk board can be assembled live.
 *
 *   GET /api/v1/endangered   - the PUBLISHED at-risk register as JSON, filterable
 *                              by ?risk= &urgency= &status= &limit=
 *
 * It serves the SAME published-only, urgency-ordered register the public /at-risk
 * page renders (EndangeredHeritageService::publicRegister), so the open API can
 * never leak an unpublished or draft record - the service applies the publication
 * gate (status.type_id=158, status_id=160; synthetic root id=1 excluded). This is
 * open at-risk data society should be able to see, so there is no API key and CORS
 * is permissive, exactly like the other /api/v1 open-data surfaces.
 *
 * Fail-soft by construction: if the register table is absent
 * (EndangeredHeritageService::available() is false) or any read fails, the
 * endpoint returns a valid empty list, never a 500.
 *
 * @author     Johan Pieterse <johan@plainsailingisystems.co.za>
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

namespace AhgApi\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EndangeredApiController extends Controller
{
    /** Default + hard-cap on rows returned, so the open door stays cheap. */
    protected const DEFAULT_LIMIT = 200;

    protected const MAX_LIMIT = 1000;

    /**
     * Pre-flight CORS for the open-data fetchers.
     */
    public function options(): JsonResponse
    {
        return response()
            ->json(null, 204)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Accept, Content-Type');
    }

    /**
     * The PUBLISHED at-risk register as JSON, urgency-ordered, optionally narrowed
     * by ?risk= / ?urgency= / ?status= and bounded by ?limit=. CORS-open, read-only,
     * fail-soft (a missing table -> an empty list, never a 500).
     */
    public function index(Request $request): JsonResponse
    {
        $serviceClass = \AhgSemanticSearch\Services\EndangeredHeritageService::class;

        $riskFilter = $this->cleanFilter($request->query('risk'));
        $urgencyFilter = $this->cleanFilter($request->query('urgency'));
        $statusFilter = $this->cleanFilter($request->query('status'));

        $limit = (int) $request->query('limit', (string) self::DEFAULT_LIMIT);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }
        $limit = min($limit, self::MAX_LIMIT);

        $items = [];
        $available = false;

        if (class_exists($serviceClass)) {
            try {
                /** @var \AhgSemanticSearch\Services\EndangeredHeritageService $service */
                $service = new $serviceClass;
                $available = $service->available();

                if ($available) {
                    // Reuse the published-only, urgency-ordered public register so
                    // the open API can never surface an unpublished record. Pass the
                    // risk filter through; urgency/status are applied below over the
                    // decorated rows (the service register is risk-filterable only).
                    $register = $service->publicRegister($riskFilter !== '' ? $riskFilter : null, 0);

                    foreach ($register as $row) {
                        if ($urgencyFilter !== '' && strcasecmp((string) ($row['urgency'] ?? ''), $urgencyFilter) !== 0) {
                            continue;
                        }
                        if ($statusFilter !== '' && strcasecmp((string) ($row['capture_status'] ?? ''), $statusFilter) !== 0) {
                            continue;
                        }

                        $items[] = $this->shapeRow($row);

                        if (count($items) >= $limit) {
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::info('[endangered-api] index failed: '.$e->getMessage());
                $items = [];
            }
        }

        $payload = [
            'feature' => 'endangered-heritage-register',
            'north_star' => 'heratio#1205',
            'generated_at' => now()->toIso8601String(),
            'institution' => (string) config('app.name', 'Heratio'),
            'base_url' => rtrim((string) url('/'), '/'),
            'available' => $available,
            'count' => count($items),
            'filters' => [
                'risk' => $riskFilter !== '' ? $riskFilter : null,
                'urgency' => $urgencyFilter !== '' ? $urgencyFilter : null,
                'status' => $statusFilter !== '' ? $statusFilter : null,
                'limit' => $limit,
            ],
            'register_url' => url('/at-risk'),
            'items' => $items,
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Shape one decorated register row into the stable public JSON contract that
     * peers consume. The slug is turned into an absolute catalogue_url so a peer
     * can link straight back to the holding record on this instance.
     *
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    protected function shapeRow(array $row): array
    {
        $slug = $row['item_slug'] ?? null;
        $slug = ($slug !== null && $slug !== '') ? (string) $slug : null;

        // A stable cross-instance reference: the slug when present (peers resolve
        // it against this instance's base_url), else the numeric item ref.
        $itemRef = $slug !== null ? $slug : (string) ((int) ($row['item_ref'] ?? 0));

        return [
            'item_ref' => $itemRef,
            'object_id' => (int) ($row['item_ref'] ?? 0),
            'title' => $row['item_title'] ?? null,
            'risk_category' => (string) ($row['risk_category'] ?? 'other'),
            'risk_label' => (string) ($row['risk_meta']['label'] ?? ''),
            'urgency' => (string) ($row['urgency'] ?? 'medium'),
            'urgency_label' => (string) ($row['urgency_meta']['label'] ?? ''),
            'capture_status' => (string) ($row['capture_status'] ?? 'flagged'),
            'capture_status_label' => (string) ($row['capture_meta']['label'] ?? ''),
            'reason' => $row['reason'] ?? null,
            'priority_score' => (int) ($row['priority_score'] ?? 0),
            'flagged_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'catalogue_url' => $slug !== null ? url('/'.$slug) : null,
        ];
    }

    /**
     * Normalise a query-string filter to a trimmed lower-case scalar, or '' when
     * absent / non-scalar.
     */
    protected function cleanFilter($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return strtolower(trim((string) $value));
    }
}
