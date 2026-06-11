<?php

/**
 * DiscoveriesController - public "Discoveries" surface (heratio#1210).
 *
 * "AI finds connections no human spotted." This is the public read surface for
 * generative scholarship. It surfaces a curated set of well-connected records
 * with the AI's non-obvious, graph-grounded research leads alongside the real
 * catalogue links they rest on.
 *
 * Two sources, in priority order:
 *   1. PERSISTED (preferred) - when ahg_scholarship_discovery has rows (refreshed
 *      by `php artisan ahg:generate-discoveries`), the page renders those STORED
 *      discoveries: stable, fast, paginated, ordered by confidence then recency,
 *      with a "generated <date>" note. No AI calls happen on the request path, so
 *      the discoveries are stable and citable across page loads.
 *   2. ON-DEMAND (fallback) - when the table is empty or missing, the controller
 *      falls back to the original behaviour: it curates a candidate set (the
 *      densest cross-collection neighbourhoods), runs ScholarshipService::discover()
 *      over each, and caches the rendered result for a short window. This keeps
 *      the page populated before the first generate run and if the table is gone.
 *
 * The controller is READ-ONLY: it never writes ahg_scholarship_discovery (that is
 * the command's job). All AI behaviour (gateway routing, graceful degradation,
 * grounding) is encapsulated in the service; the controller only reads.
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

    /** Page size when rendering the persisted discovery set. */
    protected int $perPage = 12;

    /** The persistence table the generate command writes and we read. */
    protected string $table = 'ahg_scholarship_discovery';

    protected ScholarshipService $service;

    public function __construct()
    {
        $this->service = new ScholarshipService;
    }

    /**
     * Public index: the connections across the collection that no one had noticed.
     *
     * Prefers the PERSISTED discovery set (stable, fast, paginated) when the
     * ahg_scholarship_discovery table has rows; otherwise falls back to the
     * original ON-DEMAND path so the page is never empty. Either way it degrades
     * to an empty-state rather than 500ing.
     */
    public function index()
    {
        // ---- Preferred: render stored discoveries when the table has rows. ----
        try {
            if ($this->hasStoredDiscoveries()) {
                return $this->renderStored();
            }
        } catch (\Throwable $e) {
            // Stored read failed (table dropped mid-request, etc.) - fall through
            // to the on-demand path rather than 500ing.
            Log::info('[discoveries] stored read failed, falling back to on-demand: '.$e->getMessage());
        }

        // ---- Fallback: original on-demand generation, short-cached. ----
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
            'persisted' => false,
            'generatedAt' => null,
            'paginator' => null,
        ]);
    }

    /**
     * Optional detail view for a single STORED discovery. Renders the full lead +
     * the snapshotted evidence the lead rests on. Falls back to redirecting to the
     * record when the stored row or table is absent - never 500s.
     *
     * @param  int|string  $id  The ahg_scholarship_discovery row id.
     */
    public function show($id)
    {
        try {
            if (Schema::hasTable($this->table)) {
                $row = DB::table($this->table)->where('id', (int) $id)->first();
                if ($row) {
                    return view('ahg-semantic-search::discoveries.show', [
                        'discovery' => $this->shapeStoredCard($row),
                        'generatedAt' => $row->generated_at ?? null,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::info('[discoveries] show('.$id.') failed: '.$e->getMessage());
        }

        // No stored row: send the user to the discoveries index rather than erroring.
        return redirect()->route('scholarship.discoveries');
    }

    /**
     * Whether the persisted table exists and has at least one row.
     */
    protected function hasStoredDiscoveries(): bool
    {
        return Schema::hasTable($this->table)
            && DB::table($this->table)->exists();
    }

    /**
     * Render the persisted discovery set: paginated, ordered by confidence then
     * recency. Read-only.
     */
    protected function renderStored()
    {
        $paginator = DB::table($this->table)
            ->orderByDesc('confidence')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->paginate($this->perPage)
            ->withQueryString();

        $discoveries = [];
        foreach ($paginator->items() as $row) {
            $discoveries[] = $this->shapeStoredCard($row);
        }

        // Newest generated_at across the stored set, for the "generated <date>" note.
        $generatedAt = DB::table($this->table)->max('generated_at');

        return view('ahg-semantic-search::discoveries.index', [
            'discoveries' => $discoveries,
            'aiUnavailable' => false,
            'persisted' => true,
            'generatedAt' => $generatedAt,
            'paginator' => $paginator,
        ]);
    }

    /**
     * Shape a stored ahg_scholarship_discovery row into the same card structure
     * the view consumes for on-demand cards, rehydrating the evidence snapshot
     * (connections, metrics) so the stored card renders identically. Adds the row
     * id so the view can link to the detail page.
     *
     * @param  object  $row
     * @return array<string,mixed>
     */
    protected function shapeStoredCard($row): array
    {
        $evidence = [];
        if (! empty($row->evidence)) {
            $decoded = json_decode((string) $row->evidence, true);
            if (is_array($decoded)) {
                $evidence = $decoded;
            }
        }

        $rec = $evidence['record'] ?? [];
        $total = (int) ($row->connection_count ?? 0);
        $secondHop = (int) ($evidence['second_hop_count'] ?? 0);
        $grounded = (int) ($evidence['grounded_entities'] ?? 0);
        $insights = array_values(array_filter((array) ($evidence['insights'] ?? [])));

        // Flatten the snapshotted grouped connections into the card's link list.
        $links = [];
        foreach (($evidence['connections'] ?? []) as $group) {
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

        return [
            'id' => (int) ($row->id ?? 0),
            'record' => [
                'id' => (int) ($rec['id'] ?? ($row->information_object_id ?? 0)),
                'title' => $rec['title'] ?? ($row->title ?? null),
                'slug' => $rec['slug'] ?? null,
            ],
            'summary' => (string) ($row->summary ?? ''),
            'insights' => $insights,
            'links' => $links,
            'total' => $total,
            'second_hop' => $secondHop,
            'grounded' => $grounded,
            'ai_available' => (bool) ($evidence['ai_available'] ?? false),
            'confidence' => $this->bandFromScore((int) ($row->confidence ?? 0)),
            'generated_at' => $row->generated_at ?? null,
        ];
    }

    /**
     * Map a stored 0-100 confidence score back to the label/level/score band the
     * view renders (same thresholds as confidenceBand()).
     *
     * @return array{label:string,level:string,score:int}
     */
    protected function bandFromScore(int $score): array
    {
        $score = (int) max(0, min(100, $score));
        if ($score >= 70) {
            return ['label' => 'High confidence', 'level' => 'success', 'score' => $score];
        }
        if ($score >= 40) {
            return ['label' => 'Moderate confidence', 'level' => 'warning', 'score' => $score];
        }

        return ['label' => 'Tentative', 'level' => 'secondary', 'score' => $score];
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
