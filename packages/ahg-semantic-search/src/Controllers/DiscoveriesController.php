<?php

/**
 * DiscoveriesController - public "Discoveries" surface (heratio#1210).
 *
 * "AI finds connections no human spotted." This is the public read surface for
 * generative scholarship. It selects a curated set of well-connected records and
 * runs ScholarshipService::discover() over each, surfacing the AI's non-obvious,
 * graph-grounded research leads alongside the real catalogue links they rest on.
 *
 * Discoveries are produced ON DEMAND by ScholarshipService (there is no stored
 * pool), so this controller curates a stable candidate set - the records with
 * the densest cross-collection graph neighbourhoods - and caches the rendered
 * result for a short window. That keeps the page fast and bounds the number of
 * AI gateway calls. All AI behaviour (gateway routing, graceful degradation,
 * grounding) is encapsulated in the service; the controller only reads what it
 * returns and shapes it for the view.
 *
 * Resilience: every step is guarded. If the relevant tables are missing, the
 * service errors, or no record has any connections, the page renders its
 * empty-state - it never 500s.
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

use AhgSemanticSearch\Services\ScholarshipService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DiscoveriesController extends Controller
{
    /** How many curated records we attempt to surface discoveries for. */
    protected int $maxDiscoveries = 8;

    /**
     * How many candidate (densely-connected) records we probe to find ones that
     * actually yield AI leads. Bounded so a sparse catalogue can't spin forever.
     */
    protected int $candidatePool = 24;

    /** Cache window for the rendered discovery set (seconds). */
    protected int $cacheTtl = 1800;

    protected ScholarshipService $service;

    public function __construct()
    {
        $this->service = new ScholarshipService;
    }

    /**
     * Public index: the connections across the collection that no one had noticed.
     *
     * Renders a curated, cached set of discoveries. Each discovery is one record
     * with at least one AI-surfaced, graph-grounded research lead, plus the real
     * linked entities the lead rests on. Falls back to an empty-state whenever
     * nothing can be produced - never 500s.
     */
    public function index()
    {
        $discoveries = [];
        $aiUnavailable = false;

        try {
            $payload = Cache::remember('scholarship.discoveries.index', $this->cacheTtl, function () {
                return $this->buildDiscoveries();
            });
            $discoveries = $payload['discoveries'] ?? [];
            $aiUnavailable = (bool) ($payload['ai_unavailable'] ?? false);
        } catch (\Throwable $e) {
            // Total failure (e.g. cache store down, schema gone) still renders
            // the empty-state rather than a 500.
            Log::info('[discoveries] index build failed: '.$e->getMessage());
            $discoveries = [];
            $aiUnavailable = false;
        }

        return view('ahg-semantic-search::discoveries.index', [
            'discoveries' => $discoveries,
            'aiUnavailable' => $aiUnavailable,
        ]);
    }

    /**
     * Build the curated discovery set. Picks the most-connected information
     * objects, runs discover() over them, and keeps those that yielded AI leads
     * (most-grounded first). If the AI gateway is down for every record, we still
     * return the records that have real catalogue connections so the page shows
     * something, and flag the AI as unavailable.
     *
     * @return array{discoveries: array<int,array>, ai_unavailable: bool}
     */
    protected function buildDiscoveries(): array
    {
        $candidateIds = $this->candidateRecordIds();
        if (! $candidateIds) {
            return ['discoveries' => [], 'ai_unavailable' => false];
        }

        $withInsights = [];   // records where the AI surfaced a lead
        $connectedOnly = [];  // records with real connections but no AI lead (yet)
        $aiSeen = false;      // did any record reach the AI gateway at all?

        foreach ($candidateIds as $id) {
            if (count($withInsights) >= $this->maxDiscoveries) {
                break;
            }

            try {
                $d = $this->service->discover($id);
            } catch (\Throwable $e) {
                Log::info('[discoveries] discover('.$id.') failed: '.$e->getMessage());
                continue;
            }

            if ((int) ($d['total'] ?? 0) === 0) {
                continue; // no graph links - nothing to surface
            }

            if (! empty($d['ai_available'])) {
                $aiSeen = true;
            }

            $card = $this->shapeCard($d);

            if (! empty($card['insights'])) {
                $withInsights[] = $card;
            } elseif (count($connectedOnly) < $this->maxDiscoveries) {
                $connectedOnly[] = $card;
            }
        }

        // Prefer AI-surfaced discoveries; fall back to connection-only cards so
        // the page is never blank when records clearly have links.
        $discoveries = $withInsights;
        if (count($discoveries) < $this->maxDiscoveries) {
            foreach ($connectedOnly as $c) {
                if (count($discoveries) >= $this->maxDiscoveries) {
                    break;
                }
                $discoveries[] = $c;
            }
        }

        // Highest-grounded first (most evidence behind the connection).
        usort($discoveries, fn ($a, $b) => ($b['grounded'] <=> $a['grounded']));

        return [
            'discoveries' => $discoveries,
            // Flag AI as unavailable only when we surfaced cards but none reached
            // the gateway (so the view can explain why leads are missing).
            'ai_unavailable' => $discoveries && ! $aiSeen,
        ];
    }

    /**
     * Reduce a ScholarshipService::discover() payload to the flat shape the card
     * view consumes: the record, its linked entities (with real slugs), the AI
     * leads, and a confidence band derived from how much real graph evidence
     * grounds the discovery.
     *
     * @param  array  $d
     * @return array<string,mixed>
     */
    protected function shapeCard(array $d): array
    {
        $rec = $d['record'] ?? ['id' => 0, 'title' => null, 'slug' => null];
        $total = (int) ($d['total'] ?? 0);
        $secondHop = (int) ($d['second_hop_count'] ?? 0);
        $grounded = (int) ($d['grounded_entities'] ?? 0);
        $insights = array_values(array_filter((array) ($d['insights'] ?? [])));

        // Flatten the grouped connections into a single linked-entity list for
        // the card (cap the visible set; the count shows the full total).
        $links = [];
        foreach (($d['connections'] ?? []) as $group) {
            $domain = (string) ($group['domain'] ?? 'Other');
            foreach (($group['items'] ?? []) as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $links[] = [
                    'name' => $name,
                    'slug' => $item['slug'] ?? null,
                    'domain' => $domain,
                ];
            }
        }

        // Confidence band: more grounded entities + a second hop = stronger
        // evidence that the connection is real and non-trivial.
        $confidence = $this->confidenceBand($total, $secondHop, $grounded, count($insights));

        return [
            'record' => [
                'id' => (int) ($rec['id'] ?? 0),
                'title' => $rec['title'] ?? null,
                'slug' => $rec['slug'] ?? null,
            ],
            'insights' => $insights,
            'links' => $links,
            'total' => $total,
            'second_hop' => $secondHop,
            'grounded' => $grounded,
            'ai_available' => (bool) ($d['ai_available'] ?? false),
            'confidence' => $confidence,
        ];
    }

    /**
     * Map the graph-evidence metrics to a human confidence band + a 0-100 score
     * for the meter. This reflects how much REAL catalogue evidence underpins the
     * connection, not the model's own self-assessment.
     *
     * @return array{label:string,level:string,score:int}
     */
    protected function confidenceBand(int $total, int $secondHop, int $grounded, int $insightCount): array
    {
        // Evidence score: direct links + grounded entities, lightly bonused for
        // an indirect (2nd-hop) path and for the AI actually producing a lead.
        $raw = $total + $grounded + ($secondHop > 0 ? 8 : 0) + ($insightCount > 0 ? 6 : 0);
        $score = (int) max(5, min(100, round($raw * 2)));

        if ($score >= 70) {
            return ['label' => 'High confidence', 'level' => 'success', 'score' => $score];
        }
        if ($score >= 40) {
            return ['label' => 'Moderate confidence', 'level' => 'warning', 'score' => $score];
        }

        return ['label' => 'Tentative', 'level' => 'secondary', 'score' => $score];
    }

    /**
     * Return candidate information_object ids ordered by graph degree (the
     * most-connected records first - the ones most likely to hide a non-obvious
     * link). Returns an empty array when the required tables are absent or no
     * record is connected. Read-only; bounded.
     *
     * @return array<int,int>
     */
    protected function candidateRecordIds(): array
    {
        if (! Schema::hasTable('relation') || ! Schema::hasTable('information_object')) {
            return [];
        }

        try {
            // Degree per entity across the generic relation table (subject OR
            // object), restricted to rows that are information objects. One
            // bounded, grouped, read-only query.
            $degrees = DB::table('relation')
                ->selectRaw('id, COUNT(*) AS degree')
                ->fromSub(function ($q) {
                    $q->from('relation')->select('subject_id AS id')
                        ->unionAll(
                            DB::table('relation')->select('object_id AS id')
                        );
                }, 'relation')
                ->whereIn('id', function ($q) {
                    $q->from('information_object')->select('id');
                })
                ->groupBy('id')
                ->orderByDesc('degree')
                ->limit($this->candidatePool)
                ->pluck('id');

            return $degrees->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->values()->all();
        } catch (\Throwable $e) {
            Log::info('[discoveries] candidate query failed: '.$e->getMessage());

            return [];
        }
    }
}
