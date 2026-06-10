<?php

/**
 * SuggestedConnectionsController - Controller for Heratio
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

namespace AhgAiServices\Controllers;

use AhgAiServices\Services\SuggestedConnectionsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Suggested Connections - North Star generative scholarship (#1210).
 *
 * Admin surface for the "AI finds connections no human spotted" first slice.
 * Pick a record (or run a collection-wide scan) to see non-obvious candidate
 * connections - records that share access points but are not directly linked -
 * ranked by shared-signal strength, with on-demand LLM hypotheses generated
 * through the gateway.
 */
class SuggestedConnectionsController extends Controller
{
    public function __construct(private SuggestedConnectionsService $service)
    {
    }

    /**
     * GET /admin/ai/connections - landing + collection-wide scan, or per-record
     * scan when ?object_id= is supplied.
     */
    public function index(Request $request)
    {
        $objectId  = $request->integer('object_id') ?: null;
        $minShared = max(2, $request->integer('min_shared') ?: 2);

        $seed  = $objectId ? $this->seedMeta($objectId) : null;
        $pairs = [];

        if ($objectId && $seed) {
            $pairs = $this->service->candidatesForObject($objectId, $minShared, 25);
        } elseif ($request->boolean('scan')) {
            $pairs = $this->service->topPairs($minShared, 25);
        }

        return view('ahg-ai-services::connections.index', [
            'objectId'  => $objectId,
            'minShared' => $minShared,
            'seed'      => $seed,
            'pairs'     => $pairs,
            'scanned'   => $request->boolean('scan') || ($objectId && $seed),
        ]);
    }

    /**
     * POST /admin/ai/connections/explain - generate (or fetch cached) the LLM
     * hypothesis for one pair. Returns JSON for the inline reveal.
     */
    public function explain(Request $request)
    {
        $data = $request->validate([
            'object_id_1' => ['required', 'integer', 'min:1'],
            'object_id_2' => ['required', 'integer', 'min:1', 'different:object_id_1'],
        ]);

        $id1 = (int) $data['object_id_1'];
        $id2 = (int) $data['object_id_2'];

        // Rebuild the pair from authoritative data - never trust client titles.
        $meta = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i', function ($j) {
                $j->on('i.id', '=', 'io.id')->where('i.culture', '=', app()->getLocale());
            })
            ->whereIn('io.id', [$id1, $id2])
            ->groupBy('io.id')
            ->pluck(DB::raw('MAX(i.title)'), 'io.id');

        $pair = [
            'object_id_1'  => $id1,
            'object_id_2'  => $id2,
            'title_1'      => $meta[$id1] ?? null,
            'title_2'      => $meta[$id2] ?? null,
            'shared'       => 0,
            'shared_terms' => $this->sharedTerms($id1, $id2),
        ];

        $result = $this->service->explainPair($pair);

        if (empty($result['success'])) {
            return response()->json([
                'success' => false,
                'error'   => $result['error'] ?? 'Could not generate explanation',
            ], 422);
        }

        return response()->json([
            'success'     => true,
            'explanation' => $result['explanation'],
            'cached'      => $result['cached'] ?? false,
            'model'       => $result['model'] ?? null,
        ]);
    }

    /**
     * Title + slug + identifier for the seed record header.
     *
     * @return array<string, mixed>|null
     */
    private function seedMeta(int $objectId): ?array
    {
        $row = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i', function ($j) {
                $j->on('i.id', '=', 'io.id')->where('i.culture', '=', app()->getLocale());
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $objectId)
            ->groupBy('io.id')
            ->first([
                'io.id',
                'io.identifier',
                DB::raw('MAX(i.title) as title'),
                DB::raw('MAX(s.slug) as slug'),
            ]);

        if (!$row) {
            return null;
        }

        return [
            'id'         => (int) $row->id,
            'identifier' => $row->identifier,
            'title'      => $row->title,
            'slug'       => $row->slug,
        ];
    }

    /**
     * Shared access-point term names for a pair (locale-aware), used to ground
     * the explain prompt server-side.
     *
     * @return array<int, string>
     */
    private function sharedTerms(int $id1, int $id2): array
    {
        return DB::table('object_term_relation as a')
            ->join('object_term_relation as b', 'a.term_id', '=', 'b.term_id')
            ->join('term as t', 't.id', '=', 'a.term_id')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('ti.id', '=', 't.id')->where('ti.culture', '=', app()->getLocale());
            })
            ->where('a.object_id', $id1)
            ->where('b.object_id', $id2)
            ->whereIn('t.taxonomy_id', SuggestedConnectionsService::ACCESS_POINT_TAXONOMIES)
            ->whereNotNull('ti.name')
            ->distinct()
            ->orderBy('ti.name')
            ->pluck('ti.name')
            ->all();
    }
}
