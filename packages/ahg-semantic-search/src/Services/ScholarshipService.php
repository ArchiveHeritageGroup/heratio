<?php

/**
 * ScholarshipService - generative scholarship discovery (north-star heratio#1210).
 *
 * "AI finds connections no human spotted." This service surfaces NON-OBVIOUS
 * cross-collection connections and concrete research leads for a single record,
 * grounded strictly in the actual knowledge graph - it never invents people,
 * places, dates or relationships.
 *
 * It builds ON the just-shipped graph foundation (read-only):
 *   - AhgRic\Services\RelationshipService::crossCollectionNeighbours() - the
 *     real, name-resolved graph neighbours of a record across every domain
 *     (records / agents / repositories / terms / RiC places / activities /
 *     rules / instantiations).
 *   - AhgSemanticSearch\Services\GraphKmBridgeService - the factual,
 *     no-AI natural-language rendering of those connections.
 *
 * Discovery is bounded:
 *   - 1st hop: the record's own neighbours (capped).
 *   - 2nd hop (optional, bounded): the neighbours of this record's STRONGEST
 *     first-hop entities, to surface indirect links (e.g. two records produced
 *     by the same agent that are not directly related). The candidate set is
 *     hard-capped so the prompt - and the gateway cost - stay small.
 *
 * The AI is handed ONLY the supplied entities (id + name + type) and is
 * instructed, as a hard constraint, to use nothing else: cite by exact name,
 * never invent. The model output is parsed into a flat list of insight strings.
 *
 * Everything degrades gracefully: if the AI gateway is down (LlmService::complete
 * returns null) or returns garbage, discover() returns the real connections with
 * an empty insight list. It NEVER throws on the AI path. All AI calls route
 * through the AHG gateway via LlmService - never a direct node.
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

namespace AhgSemanticSearch\Services;

use AhgCore\Services\AhgSettingsService;
use AhgRic\Services\RelationshipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ScholarshipService
{
    /**
     * Cap on cross-institutional (federated) connections returned. Bounds both
     * the number of peer hits we explain with the LLM and the rendered list.
     */
    protected int $federatedCap = 15;

    /**
     * Cap on the number of access-point terms we send to the federation search.
     * The strongest (most-shared) terms first; bounds the peer query size.
     */
    protected int $federatedTermCap = 12;

    /**
     * Cap on how many peer hits we ask the LLM to write a one-line rationale for
     * per request. The remainder still render (with no AI line) so the section
     * is never empty just because the model was slow or down.
     */
    protected int $federatedExplainCap = 8;

    /**
     * Cap on first-hop neighbour entities fed to the prompt. Keeps the prompt
     * (and gateway cost) bounded on densely-linked records.
     */
    protected int $firstHopCap = 40;

    /**
     * How many of the record's STRONGEST first-hop neighbours we expand for a
     * second hop. "Strongest" = the entities that appear in the largest graph
     * neighbourhoods (most other things hang off them - the hubs/agents/places
     * most likely to reveal an indirect link). Kept small on purpose.
     */
    protected int $secondHopSeedCap = 6;

    /**
     * Hard cap on the TOTAL second-hop candidate entities added to the prompt
     * (deduped, excluding the record and its first-hop set). Bounds the
     * candidate set so a hub entity with thousands of links can't explode it.
     */
    protected int $secondHopCandidateCap = 30;

    /** Cap on insight strings returned / requested from the model. */
    protected int $maxInsights = 8;

    /**
     * Discover non-obvious connections and research leads for one record.
     *
     * @return array{
     *     record: array{id:int,title:?string,slug:?string},
     *     connections: array<int,array{domain:string,count:int,items:array}>,
     *     total: int,
     *     insights: array<int,string>,
     *     second_hop_count: int,
     *     ai_available: bool,
     *     grounded_entities: int
     * }
     */
    public function discover(int $objectId): array
    {
        $bridge = app(GraphKmBridgeService::class);
        /** @var RelationshipService $rel */
        $rel = app(RelationshipService::class);

        $title = $bridge->recordTitle($objectId);
        $slug = $bridge->recordSlug($objectId);

        $neighbours = $rel->crossCollectionNeighbours($objectId);
        $groups = $neighbours['groups'] ?? [];
        $total = (int) ($neighbours['total'] ?? 0);

        $record = ['id' => $objectId, 'title' => $title, 'slug' => $slug];

        // No graph connections at all - nothing to ground an insight on.
        if (! $groups || $total === 0) {
            return [
                'record' => $record,
                'connections' => [],
                'total' => 0,
                'insights' => [],
                'second_hop_count' => 0,
                'ai_available' => false,
                'grounded_entities' => 0,
            ];
        }

        // ---- First-hop entity set (bounded) ----
        // Flatten the grouped neighbours into a single id-keyed entity map so we
        // can dedupe across domains and against the second hop.
        [$firstHop, $firstHopIds] = $this->flattenGroups($groups, $this->firstHopCap);

        // ---- Second hop (optional, bounded) ----
        // Expand only the strongest first-hop seeds and collect their neighbours
        // that are NOT the record itself and NOT already in the first hop.
        $secondHop = $this->secondHop($rel, $objectId, $firstHop, $firstHopIds);

        // ---- Build the grounded prompt ----
        $groundedEntities = count($firstHop) + count($secondHop);
        $insights = [];
        $aiAvailable = false;

        if ($groundedEntities > 0) {
            $prompt = $this->buildPrompt($title, $objectId, $groups, $firstHop, $secondHop);
            $parsed = $this->runAi($prompt);
            $aiAvailable = $parsed !== null;
            $insights = $parsed ?? [];
        }

        return [
            'record' => $record,
            'connections' => $this->shapeConnections($groups),
            'total' => $total,
            'insights' => array_slice($insights, 0, $this->maxInsights),
            'second_hop_count' => count($secondHop),
            'ai_available' => $aiAvailable,
            'grounded_entities' => $groundedEntities,
        ];
    }

    /**
     * Flatten the grouped neighbour structure into a single id-keyed entity map,
     * capped at $cap total entities (taking from the largest groups first, which
     * crossCollectionNeighbours already sorts to the front).
     *
     * @return array{0: array<int,array{id:int,name:string,domain:string}>, 1: array<int,bool>}
     */
    protected function flattenGroups(array $groups, int $cap): array
    {
        $entities = [];
        $ids = [];
        foreach ($groups as $group) {
            $domain = (string) ($group['domain'] ?? 'Other');
            foreach (($group['items'] ?? []) as $item) {
                $id = (int) ($item['id'] ?? 0);
                $name = trim((string) ($item['name'] ?? ''));
                if ($id <= 0 || $name === '' || isset($ids[$id])) {
                    continue;
                }
                $entities[$id] = ['id' => $id, 'name' => $name, 'domain' => $domain];
                $ids[$id] = true;
                if (count($entities) >= $cap) {
                    return [$entities, $ids];
                }
            }
        }

        return [$entities, $ids];
    }

    /**
     * Compute the bounded second-hop candidate set.
     *
     * Picks the strongest first-hop seeds (those with the largest neighbourhood
     * = most-connected hubs), pulls each seed's own neighbours, and keeps the
     * ones that are new (not the record, not already first-hop, not another
     * seed). Each candidate is annotated with the seed it was reached through so
     * the prompt can express the indirect path ("connected via <seed>").
     *
     * Hard-capped at $secondHopCandidateCap total.
     *
     * @param  array<int,array{id:int,name:string,domain:string}>  $firstHop
     * @param  array<int,bool>  $firstHopIds
     * @return array<int,array{id:int,name:string,domain:string,via:string}>
     */
    protected function secondHop(RelationshipService $rel, int $objectId, array $firstHop, array $firstHopIds): array
    {
        if (! $firstHop) {
            return [];
        }

        // Rank seeds by neighbourhood size (one bounded query per seed). We only
        // probe at most a few seeds, and only the strongest get expanded.
        $seeds = $this->rankStrongestSeeds($rel, $firstHop);

        $candidates = [];
        $seen = $firstHopIds;
        $seen[$objectId] = true;

        foreach ($seeds as $seed) {
            if (count($candidates) >= $this->secondHopCandidateCap) {
                break;
            }
            $seedNeighbours = $rel->crossCollectionNeighbours($seed['id']);
            foreach (($seedNeighbours['groups'] ?? []) as $group) {
                $domain = (string) ($group['domain'] ?? 'Other');
                foreach (($group['items'] ?? []) as $item) {
                    $id = (int) ($item['id'] ?? 0);
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($id <= 0 || $name === '' || isset($seen[$id])) {
                        continue;
                    }
                    $candidates[$id] = [
                        'id' => $id,
                        'name' => $name,
                        'domain' => $domain,
                        'via' => $seed['name'],
                    ];
                    $seen[$id] = true;
                    if (count($candidates) >= $this->secondHopCandidateCap) {
                        break 2;
                    }
                }
            }
        }

        return array_values($candidates);
    }

    /**
     * Rank the first-hop entities by neighbourhood size and return the top
     * $secondHopSeedCap as second-hop seeds. The neighbourhood size of each
     * candidate seed is fetched via one bounded crossCollectionNeighbours call.
     *
     * @param  array<int,array{id:int,name:string,domain:string}>  $firstHop
     * @return array<int,array{id:int,name:string,domain:string,strength:int}>
     */
    protected function rankStrongestSeeds(RelationshipService $rel, array $firstHop): array
    {
        $scored = [];
        // Probe at most a multiple of the seed cap to find the strongest, so we
        // don't run a neighbour query for every first-hop entity on dense records.
        $probeLimit = $this->secondHopSeedCap * 3;
        $probed = 0;
        foreach ($firstHop as $entity) {
            if ($probed >= $probeLimit) {
                break;
            }
            $n = $rel->crossCollectionNeighbours($entity['id']);
            $entity['strength'] = (int) ($n['total'] ?? 0);
            $scored[] = $entity;
            $probed++;
        }

        usort($scored, fn ($a, $b) => $b['strength'] <=> $a['strength']);

        return array_slice($scored, 0, $this->secondHopSeedCap);
    }

    /**
     * Build the grounded prompt. The model is given ONLY the real connected
     * entities and a hard "use nothing else" constraint.
     *
     * @param  array<int,array>  $groups
     * @param  array<int,array{id:int,name:string,domain:string}>  $firstHop
     * @param  array<int,array{id:int,name:string,domain:string,via:string}>  $secondHop
     */
    protected function buildPrompt(?string $title, int $objectId, array $groups, array $firstHop, array $secondHop): string
    {
        $label = $title !== null ? '"'.$title.'"' : ('record #'.$objectId);

        $lines = [];
        $lines[] = 'You are a research analyst working inside an archival catalogue. Your task is to'
            .' surface NON-OBVIOUS connections and concrete research leads for one record, using'
            .' ONLY the catalogue links supplied below.';
        $lines[] = '';
        $lines[] = 'HARD CONSTRAINTS - you MUST obey all of these:';
        $lines[] = '1. Use ONLY the entities listed below. Do NOT introduce any person, place,'
            .' organisation, date, event or record that is not in the lists.';
        $lines[] = '2. NEVER invent facts, names or dates. If you are unsure, say nothing.';
        $lines[] = '3. Refer to every entity by its EXACT name as written below.';
        $lines[] = '4. Focus on links that a cataloguer might NOT have spotted: shared agents,'
            .' shared places, shared activities, or two records reachable through the same'
            .' intermediary. Prefer indirect/second-hop links over restating direct ones.';
        $lines[] = '5. Each lead must be concrete and actionable, e.g. "X and Y were both'
            .' produced by Z - worth comparing" or "follow A through B to reach C".';
        $lines[] = '';
        $lines[] = 'THE RECORD: '.$label.' (catalogue id '.$objectId.').';
        $lines[] = '';
        $lines[] = 'DIRECTLY CONNECTED ENTITIES (first hop), grouped by domain:';
        foreach ($groups as $group) {
            $domain = (string) ($group['domain'] ?? 'Other');
            $names = [];
            foreach (($group['items'] ?? []) as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                $id = (int) ($item['id'] ?? 0);
                if ($name === '' || $id <= 0 || ! isset($firstHop[$id])) {
                    continue;
                }
                $names[] = $name.' [#'.$id.']';
            }
            if ($names) {
                $lines[] = '- '.$domain.': '.implode('; ', $names);
            }
        }
        $lines[] = '';

        if ($secondHop) {
            $lines[] = 'INDIRECTLY CONNECTED ENTITIES (second hop - reached THROUGH a directly'
                .' connected entity; the intermediary is shown in brackets):';
            foreach ($secondHop as $c) {
                $lines[] = '- '.$c['name'].' [#'.$c['id'].'] ('.$c['domain'].'; via '.$c['via'].')';
            }
            $lines[] = '';
        }

        $lines[] = 'OUTPUT FORMAT:';
        $lines[] = 'Return up to '.$this->maxInsights.' insights, one per line, each starting with'
            .' "- ". No preamble, no numbering, no closing remarks. If the supplied links reveal'
            .' nothing non-obvious, return a single line: "- No non-obvious connections found in'
            .' the supplied links."';

        return implode("\n", $lines);
    }

    /**
     * Call the AI gateway through LlmService and parse the response into insight
     * strings. Returns null when the gateway is unreachable / returns nothing
     * (so the caller can mark AI unavailable); returns an array (possibly empty)
     * on a successful call. NEVER throws.
     *
     * @return array<int,string>|null
     */
    protected function runAi(string $prompt): ?array
    {
        try {
            $llm = app(\AhgAiServices\Services\LlmService::class);
        } catch (\Throwable $e) {
            Log::info('[scholarship] LlmService unavailable: '.$e->getMessage());

            return null;
        }

        try {
            $raw = $llm->complete($prompt, [
                'system_prompt' => 'You are a careful archival research analyst. You ground every'
                    .' statement in the supplied catalogue links and never invent entities or facts.',
                'temperature' => 0.2,
                'max_tokens' => 700,
                'purpose' => 'generative-scholarship',
                'data_scope' => 'catalogue-graph',
            ]);
        } catch (\Throwable $e) {
            // Graceful degradation: log and report AI as unavailable.
            Log::info('[scholarship] AI gateway call failed: '.$e->getMessage());

            return null;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return $this->parseInsights($raw);
    }

    /**
     * Parse the model output (a bullet list) into a clean list of insight
     * strings. Tolerant of "- ", "* ", "1." and bare-line formats.
     *
     * @return array<int,string>
     */
    protected function parseInsights(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Strip common bullet / numbering prefixes.
            $line = preg_replace('/^\s*(?:[-*\x{2022}]|\d+[.)])\s*/u', '', $line);
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Drop the model's explicit "nothing found" sentinel - the view shows
            // its own empty state for that, no need to render it as an "insight".
            if (preg_match('/^no non-obvious connections found/i', $line)) {
                continue;
            }
            $out[] = $line;
            if (count($out) >= $this->maxInsights) {
                break;
            }
        }

        return $out;
    }

    /**
     * Shape the grouped connections for the view / command. Keeps the same
     * structure RelationshipService produced (domain / count / items) but
     * guarantees the keys exist.
     *
     * @param  array<int,array>  $groups
     * @return array<int,array{domain:string,count:int,items:array}>
     */
    protected function shapeConnections(array $groups): array
    {
        $out = [];
        foreach ($groups as $group) {
            $items = $group['items'] ?? [];
            $out[] = [
                'domain' => (string) ($group['domain'] ?? 'Other'),
                'count' => (int) ($group['count'] ?? count($items)),
                'items' => array_values($items),
            ];
        }

        return $out;
    }

    // -----------------------------------------------------------------
    // Cross-institutional discovery (heratio#1210, federation increment)
    // -----------------------------------------------------------------

    /**
     * Discover CROSS-INSTITUTIONAL connections for one record: related records
     * held by OTHER federation peers, surfaced through the live federated-search
     * primitive and explained with a grounded one-line AI rationale.
     *
     * This is the federation twin of discover(). It is strictly ADDITIVE - it
     * does not touch the local discovery path. The record's strongest local
     * access points (its title and the names of its directly-connected people,
     * subjects, places and records) become the query terms; each peer hit is
     * turned into a connection {source peer, matched title/url, shared access
     * points, an AI "why this likely connects" line}.
     *
     * FAIL-SOFT by contract: if the federation package is absent, no peers are
     * configured, a peer times out, or the AI gateway is down, this returns an
     * empty federated list. It NEVER throws and NEVER 500s. The local discover()
     * behaviour is unaffected and byte-identical whether or not this is called.
     *
     * @return array{
     *   record: array{id:int,title:?string,slug:?string},
     *   terms: array<int,string>,
     *   connections: array<int,array{
     *     title:string, url:?string, peer_name:string, peer_url:?string,
     *     shared:array<int,string>, shared_count:int, rationale:?string,
     *     also_present_in:array<int,string>
     *   }>,
     *   peer_stats: array<string,mixed>,
     *   available: bool,
     *   ai_available: bool
     * }
     */
    public function discoverFederated(int $objectId): array
    {
        $bridge = app(GraphKmBridgeService::class);
        $title = $bridge->recordTitle($objectId);
        $slug = $bridge->recordSlug($objectId);

        $empty = [
            'record' => ['id' => $objectId, 'title' => $title, 'slug' => $slug],
            'terms' => [],
            'connections' => [],
            'peer_stats' => [],
            'available' => false,
            'ai_available' => false,
        ];

        // Federation package must be present (calling a locked service is fine,
        // but it may not be installed at all). class_exists guards that.
        $searchClass = \AhgFederation\Services\FederatedSearchService::class;
        if (! class_exists($searchClass)) {
            return $empty;
        }

        // ---- Build the record's strongest access points ----
        $terms = $this->accessPointTerms($objectId, $title);
        if (! $terms) {
            // Nothing to query peers with - fail soft (still "available", just no hits).
            return array_merge($empty, ['available' => true]);
        }

        // ---- Live cross-peer search ----
        try {
            /** @var \AhgFederation\Services\FederatedSearchService $fed */
            $fed = app($searchClass);
            // One combined query (peer search engines OR the terms). Keep it a
            // single call so we hit each peer once, not once per term.
            $query = implode(' ', $terms);
            $result = $fed->search($query, ['limit' => 50]);
        } catch (\Throwable $e) {
            Log::info('[scholarship] federated search failed: '.$e->getMessage());

            return array_merge($empty, ['available' => true]);
        }

        $rows = is_object($result) && property_exists($result, 'results')
            ? (array) $result->results
            : (is_array($result) ? ($result['results'] ?? []) : []);
        $peerStats = is_object($result) && property_exists($result, 'peerStats')
            ? (array) $result->peerStats
            : [];

        if (! $rows) {
            return array_merge($empty, ['available' => true, 'peer_stats' => $peerStats]);
        }

        // ---- Shape + dedupe + rank ----
        $connections = $this->shapeFederatedConnections($rows, $terms);
        if (! $connections) {
            return array_merge($empty, ['available' => true, 'peer_stats' => $peerStats]);
        }

        // ---- Explain the top connections with a grounded AI one-liner ----
        $aiAvailable = $this->explainFederatedConnections($title, $objectId, $connections);

        return [
            'record' => ['id' => $objectId, 'title' => $title, 'slug' => $slug],
            'terms' => $terms,
            'connections' => array_slice($connections, 0, $this->federatedCap),
            'peer_stats' => $peerStats,
            'available' => true,
            'ai_available' => $aiAvailable,
        ];
    }

    /** Table persisting federated-discovery results (heratio#1210). */
    protected const FEDERATED_CACHE_TABLE = 'ahg_scholarship_federated_discovery';

    /** Default freshness window (minutes) before a persisted federated discovery is re-run. */
    protected const FEDERATED_CACHE_DEFAULT_MINUTES = 1440; // 24h - peers + access points change slowly

    /**
     * heratio#1210 - read-through cache over {@see discoverFederated()}. Federated
     * discovery is expensive (a live peer round-trip plus a per-connection AI
     * rationale), so we PERSIST each result and serve it until it ages past the
     * freshness window:
     *
     *   - a fresh persisted row (within the TTL) is returned as-is (cached=true);
     *   - a missing / expired row triggers ONE live {@see discoverFederated()},
     *     which is persisted and returned (cached=false);
     *   - if the live refresh yields nothing usable but a (stale) row exists, the
     *     stale row is returned (cached=true, stale=true) so a peer outage shows
     *     last-known results rather than a blank section.
     *
     * FAIL-SOFT like discoverFederated(): never throws. When the cache table is
     * absent it transparently falls back to a live call (no persistence).
     *
     * @return array the discoverFederated() shape, plus cached:bool, stale:bool, generated_at:?string
     */
    public function discoverFederatedCached(int $objectId, bool $forceRefresh = false): array
    {
        $cached = $this->readFederatedCache($objectId);

        if (! $forceRefresh && $cached !== null && ! ($cached['stale'] ?? true)) {
            return $cached; // fresh hit
        }

        // Refresh live, then persist. Any failure falls back to the cached row.
        try {
            $live = $this->discoverFederated($objectId);
        } catch (\Throwable $e) {
            Log::info('[scholarship] federated cache refresh failed for '.$objectId.': '.$e->getMessage());
            $live = null;
        }

        // Only persist + serve a live result that actually reached the peers.
        if (is_array($live) && ($live['available'] ?? false) && ! empty($live['connections'])) {
            $this->persistFederatedDiscovery($objectId, $live);

            return $live + ['cached' => false, 'stale' => false, 'generated_at' => now()->toDateTimeString()];
        }

        // Live gave nothing usable: prefer a stale persisted row over a blank.
        if ($cached !== null) {
            return $cached;
        }

        // Nothing persisted and nothing live: return whatever the live call gave
        // (an empty-but-available payload), tagged uncached.
        return (is_array($live) ? $live : ['record' => ['id' => $objectId], 'connections' => [], 'available' => false])
            + ['cached' => false, 'stale' => false, 'generated_at' => null];
    }

    /**
     * Read a persisted federated discovery and rebuild the discoverFederated()
     * shape from it, flagged with cached/stale/generated_at. Returns null when the
     * table or row is absent. Never throws.
     *
     * @return array<string,mixed>|null
     */
    protected function readFederatedCache(int $objectId): ?array
    {
        try {
            if (! Schema::hasTable(self::FEDERATED_CACHE_TABLE)) {
                return null;
            }
            $row = DB::table(self::FEDERATED_CACHE_TABLE)->where('information_object_id', $objectId)->first();
            if ($row === null) {
                return null;
            }

            $ttl = max(1, AhgSettingsService::getInt('scholarship_federated_cache_minutes', self::FEDERATED_CACHE_DEFAULT_MINUTES));
            $generatedAt = $row->generated_at ? \Illuminate\Support\Carbon::parse($row->generated_at) : null;
            $stale = $generatedAt === null || $generatedAt->diffInMinutes(now()) >= $ttl;

            return [
                'record' => ['id' => $objectId, 'title' => $row->title, 'slug' => null],
                'terms' => json_decode((string) ($row->terms ?? '[]'), true) ?: [],
                'connections' => json_decode((string) ($row->connections ?? '[]'), true) ?: [],
                'peer_stats' => json_decode((string) ($row->peer_stats ?? '[]'), true) ?: [],
                'available' => true,
                'ai_available' => (bool) $row->ai_available,
                'cached' => true,
                'stale' => $stale,
                'generated_at' => $row->generated_at ? (string) $row->generated_at : null,
            ];
        } catch (\Throwable $e) {
            Log::info('[scholarship] federated cache read failed for '.$objectId.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * Upsert one federated-discovery result into the cache table. Best effort: a
     * missing table or any failure is swallowed (federated discovery just stays
     * live-only). Never throws.
     *
     * @param  array<string,mixed>  $result  a discoverFederated() payload
     */
    public function persistFederatedDiscovery(int $objectId, array $result): void
    {
        try {
            if (! Schema::hasTable(self::FEDERATED_CACHE_TABLE)) {
                return;
            }
            $connections = is_array($result['connections'] ?? null) ? $result['connections'] : [];
            $now = now();
            $values = [
                'title' => $result['record']['title'] ?? null,
                'terms' => json_encode($result['terms'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'connections' => json_encode($connections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'peer_stats' => json_encode($result['peer_stats'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'connection_count' => count($connections),
                'ai_available' => (bool) ($result['ai_available'] ?? false),
                'generated_at' => $now,
                'updated_at' => $now,
            ];

            $table = DB::table(self::FEDERATED_CACHE_TABLE)->where('information_object_id', $objectId);
            if ($table->exists()) {
                $table->update($values); // leave created_at at its original insert time
            } else {
                DB::table(self::FEDERATED_CACHE_TABLE)->insert(
                    $values + ['information_object_id' => $objectId, 'created_at' => $now]
                );
            }
        } catch (\Throwable $e) {
            Log::info('[scholarship] federated cache persist failed for '.$objectId.': '.$e->getMessage());
        }
    }

    /**
     * Collect the record's strongest access-point terms for a federated query:
     * its title plus the names of its directly-connected people, subjects,
     * places and records (the cross-collection graph neighbours). Deduped
     * case-insensitively, short/noisy tokens dropped, capped at $federatedTermCap.
     *
     * @return array<int,string>
     */
    protected function accessPointTerms(int $objectId, ?string $title): array
    {
        $terms = [];
        $seen = [];

        $push = function (?string $t) use (&$terms, &$seen) {
            $t = trim((string) $t);
            // Drop empties, bare ids, and very short tokens that would match
            // everything across peers.
            if ($t === '' || mb_strlen($t) < 3 || preg_match('/^#?\d+$/', $t)) {
                return;
            }
            $key = mb_strtolower($t);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $terms[] = $t;
        };

        if ($title !== null) {
            $push($title);
        }

        try {
            /** @var RelationshipService $rel */
            $rel = app(RelationshipService::class);
            $neighbours = $rel->crossCollectionNeighbours($objectId);
        } catch (\Throwable $e) {
            Log::info('[scholarship] access-point lookup failed: '.$e->getMessage());
            $neighbours = ['groups' => []];
        }

        // Take the named entities from the strongest groups first (groups already
        // arrive sorted largest-first from crossCollectionNeighbours).
        foreach (($neighbours['groups'] ?? []) as $group) {
            foreach (($group['items'] ?? []) as $item) {
                $push($item['name'] ?? null);
                if (count($terms) >= $this->federatedTermCap) {
                    break 2;
                }
            }
        }

        return array_slice($terms, 0, $this->federatedTermCap);
    }

    /**
     * Turn raw federated-search result rows into cross-institutional connection
     * cards: {title, url, peer name/url, shared access points, shared count}.
     * Dedupes by peer + normalised title, ranks by shared-term count (then
     * relevance score). The AI rationale is filled in later by
     * explainFederatedConnections().
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<int,string>  $terms
     * @return array<int,array<string,mixed>>
     */
    protected function shapeFederatedConnections(array $rows, array $terms): array
    {
        $byKey = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $source = (array) ($row['source'] ?? []);
            $peerName = trim((string) ($source['peerName'] ?? 'Partner institution'));
            $peerUrl = $source['peerUrl'] ?? null;
            $url = $source['originalUrl'] ?? ($row['url'] ?? null);

            // Which of our access-point terms appear in this hit's title /
            // description? That overlap is the "shared access points" + the rank.
            $haystack = mb_strtolower($title.' '.((string) ($row['description'] ?? '')));
            $shared = [];
            foreach ($terms as $t) {
                if (mb_strpos($haystack, mb_strtolower($t)) !== false) {
                    $shared[] = $t;
                }
            }

            // "Also present in" pills the federation layer may have stamped.
            $also = array_values(array_filter(array_map(
                fn ($p) => trim((string) $p),
                (array) ($row['also_present_in'] ?? [])
            )));

            $key = mb_strtolower($peerName.'|'.preg_replace('/\s+/', ' ', $title));
            $score = (float) ($row['score'] ?? 1.0);

            if (isset($byKey[$key])) {
                // Keep the richer shared set / higher score on a duplicate.
                if (count($shared) > count($byKey[$key]['shared'])) {
                    $byKey[$key]['shared'] = $shared;
                }
                $byKey[$key]['shared_count'] = count($byKey[$key]['shared']);
                $byKey[$key]['_score'] = max($byKey[$key]['_score'], $score);
                continue;
            }

            $byKey[$key] = [
                'title' => $title,
                'url' => is_string($url) && $url !== '' ? $url : null,
                'peer_name' => $peerName !== '' ? $peerName : 'Partner institution',
                'peer_url' => is_string($peerUrl) && $peerUrl !== '' ? $peerUrl : null,
                'shared' => $shared,
                'shared_count' => count($shared),
                'rationale' => null,
                'also_present_in' => $also,
                '_score' => $score,
            ];
        }

        $connections = array_values($byKey);

        // Rank: most shared access points first, then peer relevance score.
        usort($connections, function ($a, $b) {
            if ($a['shared_count'] !== $b['shared_count']) {
                return $b['shared_count'] <=> $a['shared_count'];
            }

            return $b['_score'] <=> $a['_score'];
        });

        // Strip the internal sort key before returning.
        foreach ($connections as &$c) {
            unset($c['_score']);
        }
        unset($c);

        return $connections;
    }

    /**
     * Fill in a grounded one-line "why this likely connects" rationale for the
     * top connections, using the SAME LlmService gateway path as the local
     * discover() flow. The model is given ONLY the local record label and the
     * peer hit's title + shared access points, and is told to ground its line in
     * those shared terms and never invent. Mutates $connections in place.
     *
     * Returns true when the gateway was reached, false otherwise. NEVER throws:
     * on any failure the connections simply keep their null rationale and still
     * render (with their verified shared access points).
     *
     * @param  array<int,array<string,mixed>>  $connections  (by reference)
     */
    protected function explainFederatedConnections(?string $title, int $objectId, array &$connections): bool
    {
        $label = $title !== null ? '"'.$title.'"' : ('record #'.$objectId);

        // Only explain the top N; the rest render without an AI line.
        $explainCount = min($this->federatedExplainCap, count($connections));
        if ($explainCount === 0) {
            return false;
        }

        $lines = [];
        $lines[] = 'You are a research analyst. A record in our archive, '.$label.', may relate to'
            .' records held by OTHER institutions found via federated search. For each numbered'
            .' candidate below, write ONE short sentence explaining why it likely connects to our'
            .' record.';
        $lines[] = '';
        $lines[] = 'HARD CONSTRAINTS:';
        $lines[] = '1. Ground every sentence ONLY in the shared access points listed for that'
            .' candidate. Do NOT introduce any person, place, date, organisation or fact not given.';
        $lines[] = '2. If a candidate shares no meaningful access point, say "Possible match - shared'
            .' catalogue terms only" for that number.';
        $lines[] = '3. Keep each line to one sentence. Refer to shared terms by their exact wording.';
        $lines[] = '';
        $lines[] = 'CANDIDATES:';
        for ($i = 0; $i < $explainCount; $i++) {
            $c = $connections[$i];
            $shared = $c['shared'] ? implode('; ', $c['shared']) : '(none beyond a catalogue keyword)';
            $lines[] = ($i + 1).'. "'.$c['title'].'" (held by '.$c['peer_name'].'). Shared access points: '.$shared.'.';
        }
        $lines[] = '';
        $lines[] = 'OUTPUT FORMAT: one line per candidate, in order, each starting with the candidate'
            .' number and ". ". No preamble, no closing remarks.';

        $prompt = implode("\n", $lines);

        try {
            $llm = app(\AhgAiServices\Services\LlmService::class);
        } catch (\Throwable $e) {
            Log::info('[scholarship] LlmService unavailable (federated): '.$e->getMessage());

            return false;
        }

        try {
            $raw = $llm->complete($prompt, [
                'system_prompt' => 'You are a careful archival research analyst. You ground every'
                    .' statement in the supplied shared catalogue terms and never invent entities or facts.',
                'temperature' => 0.2,
                'max_tokens' => 700,
                'purpose' => 'generative-scholarship-federated',
                'data_scope' => 'federated-access-points',
            ]);
        } catch (\Throwable $e) {
            Log::info('[scholarship] AI gateway call failed (federated): '.$e->getMessage());

            return false;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return false;
        }

        $byNumber = $this->parseNumberedLines($raw);
        foreach ($byNumber as $n => $sentence) {
            $idx = $n - 1;
            if ($idx >= 0 && $idx < $explainCount) {
                $connections[$idx]['rationale'] = $sentence;
            }
        }

        return true;
    }

    /**
     * Parse a numbered list ("1. ...", "2) ...") into [number => sentence].
     * Tolerant of bullet noise; ignores lines without a leading number.
     *
     * @return array<int,string>
     */
    protected function parseNumberedLines(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\s*(\d+)\s*[.)]\s*(.+)$/u', $line, $m)) {
                $n = (int) $m[1];
                $sentence = trim($m[2]);
                if ($n > 0 && $sentence !== '') {
                    $out[$n] = $sentence;
                }
            }
        }

        return $out;
    }
}
