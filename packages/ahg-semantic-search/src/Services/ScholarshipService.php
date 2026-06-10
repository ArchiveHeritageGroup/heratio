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

use AhgRic\Services\RelationshipService;
use Illuminate\Support\Facades\Log;

class ScholarshipService
{
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
}
