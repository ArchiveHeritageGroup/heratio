<?php

/**
 * DisplacedHeritageRegisterController - PUBLIC face of the repatriation engine
 * (north-star heratio#1207): the displaced-heritage register.
 *
 * Where the admin DisplacedHeritageController renders the curatorial review
 * report, this controller renders the public, dignified register of traced
 * items at GET /displaced-heritage (name displaced-heritage.index). It reads
 * exactly what DisplacedHeritageService::scan() returns - museum-catalogued
 * objects whose recorded place/community of origin appears to differ from where
 * they are now held - and presents each as a respectful, factual entry: the
 * object, its place/community of origin, its current holding location, the
 * displacement (origin-vs-holding) context, and a confidence indicator. It adds
 * no fields the service does not produce.
 *
 * Framing is deliberately careful. This is sensitive subject matter. Every
 * entry is shown as a documented origin-vs-holding observation drawn from the
 * catalogue, never as a claim of wrongful removal, a legal determination, or a
 * call to action. The service's standing disclaimer is surfaced prominently.
 *
 * "Virtual return": for each entry we look for a way for the public to
 * re-encounter the object in its own right. If the object's record is linked to
 * a reconstruction exhibition space (ahg_lost_place_reconstruction ->
 * ahg_exhibition_space) and that walkthrough route exists, we offer a "virtual
 * return" walkthrough link; otherwise, if the object's record exists, we link
 * to the record's own show page (which hosts any digital surrogate / 3D viewer);
 * otherwise we show provenance/return-context only. Every link is existence-
 * checked (Route::has + Schema::hasTable + real slug) - never a dead link.
 *
 * Read-only. Resilient: a service error, missing tables, or nothing traced all
 * resolve to the empty-state - this page never 500s.
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

use AhgSemanticSearch\Services\DisplacedHeritageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class DisplacedHeritageRegisterController extends Controller
{
    protected DisplacedHeritageService $service;

    public function __construct()
    {
        $this->service = new DisplacedHeritageService;
    }

    /**
     * Public displaced-heritage register.
     *
     * Renders the items DisplacedHeritageService::scan() has traced, optionally
     * narrowed to one origin region (?origin=) - the service exposes the set of
     * origin regions via the by_origin summary, so we filter the returned set in
     * memory rather than inventing a new service contract. Never 500s: any
     * failure resolves to the grounded empty-state.
     */
    public function index(Request $request)
    {
        $report = [
            'disclaimer' => DisplacedHeritageService::DISCLAIMER,
            'scanned' => 0,
            'evaluated' => 0,
            'flagged_count' => 0,
            'truncated' => false,
            'limit' => 0,
            'records' => [],
            'by_origin' => [],
        ];

        try {
            $report = $this->service->scan(['limit' => 0]);
        } catch (\Throwable $e) {
            Log::info('[displaced-heritage] register scan failed: '.$e->getMessage());
        }

        $records = is_array($report['records'] ?? null) ? $report['records'] : [];
        $byOrigin = is_array($report['by_origin'] ?? null) ? $report['by_origin'] : [];

        // Optional origin-region filter. The available regions come straight from
        // the service's by_origin summary; an unknown value simply yields nothing.
        $originFilter = trim((string) $request->query('origin', ''));
        if ($originFilter !== '') {
            $records = array_values(array_filter($records, function ($r) use ($originFilter) {
                return strcasecmp((string) ($r['origin_region'] ?? ''), $originFilter) === 0;
            }));
        }

        // Enrich each traced item with its "virtual return" affordance. All look-ups
        // are read-only, existence-guarded, and degrade to null (provenance-only).
        $hasReconTable = false;
        $hasSpaceTable = false;
        $hasDigitalObject = false;
        try {
            $hasReconTable = Schema::hasTable('ahg_lost_place_reconstruction');
            $hasSpaceTable = Schema::hasTable('ahg_exhibition_space');
            $hasDigitalObject = Schema::hasTable('digital_object');
        } catch (\Throwable $e) {
            Log::info('[displaced-heritage] schema probe failed: '.$e->getMessage());
        }

        $walkthroughRouteExists = Route::has('exhibition-space.walkthrough');

        $entries = [];
        foreach ($records as $r) {
            $entries[] = $this->decorate($r, [
                'hasReconTable' => $hasReconTable,
                'hasSpaceTable' => $hasSpaceTable,
                'hasDigitalObject' => $hasDigitalObject,
                'walkthroughRouteExists' => $walkthroughRouteExists,
            ]);
        }

        return view('ahg-semantic-search::displaced-heritage.index', [
            'disclaimer' => (string) ($report['disclaimer'] ?? DisplacedHeritageService::DISCLAIMER),
            'entries' => $entries,
            'byOrigin' => $byOrigin,
            'totalTraced' => (int) ($report['flagged_count'] ?? count($records)),
            'shownCount' => count($entries),
            'originFilter' => $originFilter,
        ]);
    }

    /**
     * Decorate one scan() record with a display-confidence band and its gated
     * "virtual return" target. Reads ONLY the fields scan() produces plus the
     * existence-checked reconstruction / digital-object look-ups.
     *
     * @param  array<string,mixed>  $r
     * @param  array<string,bool>  $caps
     * @return array<string,mixed>
     */
    protected function decorate(array $r, array $caps): array
    {
        $id = (int) ($r['id'] ?? 0);
        $slug = isset($r['slug']) && $r['slug'] !== null ? (string) $r['slug'] : null;

        $virtualReturn = $this->virtualReturnFor($id, $slug, $caps);

        return [
            'id' => $id,
            'title' => isset($r['title']) && $r['title'] !== null ? (string) $r['title'] : null,
            'slug' => $slug,
            'origin_region' => (string) ($r['origin_region'] ?? ''),
            'holding_region' => (string) ($r['holding_region'] ?? ''),
            'origin' => [
                'label' => (string) ($r['origin']['label'] ?? ''),
                'value' => (string) ($r['origin']['value'] ?? ''),
            ],
            'holding' => [
                'label' => (string) ($r['holding']['label'] ?? ''),
                'value' => (string) ($r['holding']['value'] ?? ''),
            ],
            'reason' => (string) ($r['reason'] ?? ''),
            'confidence' => $this->confidenceBand($r),
            'virtual_return' => $virtualReturn,
        ];
    }

    /**
     * Resolve the "virtual return" affordance for a traced item, in priority
     * order, returning null when there is nothing better than provenance context:
     *
     *   1. A reconstruction digital twin: the object's record linked to an
     *      ahg_exhibition_space via ahg_lost_place_reconstruction, when the public
     *      walkthrough route exists. -> walk through its virtual return.
     *   2. The object's own record (its show page hosts any digital surrogate /
     *      3D object), when the record has a digital_object. -> view its surrogate.
     *
     * Every branch is existence-checked so the view never emits a dead link.
     *
     * @param  array<string,bool>  $caps
     * @return array{type:string,label:string,url:string}|null
     */
    protected function virtualReturnFor(int $id, ?string $slug, array $caps): ?array
    {
        // 1. Reconstruction exhibition-space walkthrough.
        if ($id > 0
            && $caps['hasReconTable']
            && $caps['hasSpaceTable']
            && $caps['walkthroughRouteExists']) {
            try {
                $spaceSlug = DB::table('ahg_lost_place_reconstruction as lpr')
                    ->join('ahg_exhibition_space as es', 'es.id', '=', 'lpr.exhibition_space_id')
                    ->where('lpr.information_object_id', $id)
                    ->whereNotNull('es.slug')
                    ->where('es.slug', '!=', '')
                    ->orderByDesc('lpr.id')
                    ->value('es.slug');

                if ($spaceSlug !== null && (string) $spaceSlug !== '') {
                    return [
                        'type' => 'reconstruction',
                        'label' => __('Experience its virtual return'),
                        'url' => route('exhibition-space.walkthrough', ['slug' => (string) $spaceSlug]),
                    ];
                }
            } catch (\Throwable $e) {
                Log::info('[displaced-heritage] reconstruction lookup failed for '.$id.': '.$e->getMessage());
            }
        }

        // 2. The object's own record / digital surrogate.
        if ($slug !== null && $slug !== '' && $caps['hasDigitalObject'] && $id > 0) {
            try {
                $hasDigital = DB::table('digital_object')
                    ->where('object_id', $id)
                    ->orWhere('information_object_id', $id)
                    ->exists();

                if ($hasDigital) {
                    return [
                        'type' => 'surrogate',
                        'label' => __('View its digital surrogate'),
                        'url' => url('/'.$slug),
                    ];
                }
            } catch (\Throwable $e) {
                Log::info('[displaced-heritage] digital-object lookup failed for '.$id.': '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Map a traced record to a human confidence band. The service's flag is
     * intentionally conservative (it fires only when BOTH origin AND holding
     * resolve to known regions), so every traced item is at least "documented".
     * We lift the band when the origin evidence comes from an explicit place
     * (creation/discovery place) rather than a cultural-context label, because a
     * recorded place is a firmer origin signal than a cultural descriptor.
     *
     * @param  array<string,mixed>  $r
     * @return array{label:string,level:string}
     */
    protected function confidenceBand(array $r): array
    {
        $originField = (string) ($r['origin']['field'] ?? '');
        $holdingField = (string) ($r['holding']['field'] ?? '');

        $originIsPlace = in_array($originField, ['creation_place', 'discovery_place'], true);
        $holdingIsPlace = in_array(
            $holdingField,
            ['current_location_geography', 'current_location', 'repository_country_code'],
            true
        );

        if ($originIsPlace && $holdingIsPlace) {
            return ['label' => __('Well-documented origin'), 'level' => 'success'];
        }
        if ($originIsPlace || $holdingIsPlace) {
            return ['label' => __('Documented origin'), 'level' => 'info'];
        }

        return ['label' => __('Indicative origin'), 'level' => 'secondary'];
    }
}
