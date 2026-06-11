<?php

/**
 * GraphKmBridgeService - KM <-> knowledge-graph bridge.
 *
 * A concrete slice of heratio#1197 (the unified G/L/A/M knowledge graph) and
 * heratio#1214 (RiC-native node resolution). It turns each record's
 * cross-collection RiC graph connections into a concise, factual,
 * natural-language summary and writes that summary as a markdown file under
 * docs/reference/graph-connections/ so the KM inotify watcher auto-ingests it
 * (~2-3 min, no curl / no manual trigger). The effect: the cross-collection
 * graph becomes queryable through KM in plain language.
 *
 * Source of truth is AhgRic\Services\RelationshipService::crossCollectionNeighbours(),
 * which is read here only (ahg-ric is a locked package). This service renders -
 * it never invents. Every sentence is a plain catalogue fact derived from the
 * relation table + resolved entity names; there is NO AI generation in this path.
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GraphKmBridgeService
{
    /**
     * Max neighbours to name inline per domain before collapsing to
     * "... and N more". Keeps a summary readable and the KM chunk bounded.
     */
    protected int $perDomainCap = 8;

    /** Hard cap on total summary characters (safety net against runaway records). */
    protected int $maxChars = 4000;

    /**
     * Hard ceiling on how many entities a single graph export run may render,
     * even when a caller passes a larger --limit. Keeps the digest bounded and
     * the number of KM chunks predictable (no thousands-of-files dump).
     */
    protected int $maxEntities = 200;

    /**
     * heratio#1220 / #1197 - publication-status coordinates for "Published".
     *
     * Publication status lives in the generic `status` table (type_id 158), not
     * on information_object. status_id 160 = Published, 159 = Draft. The graph
     * digest only ever exports PUBLISHED records so KM never ingests drafts or
     * unpublished working data. See CLAUDE.md "Publication Status".
     */
    protected int $publicationStatusTypeId = 158;

    protected int $publishedStatusId = 160;

    /**
     * heratio#1220 - the synthetic top-level root of the information_object MPTT
     * tree (id = 1). It is a structural placeholder with no real title and must
     * never be exported as a "most-connected entity".
     */
    protected int $syntheticRootId = 1;

    /**
     * heratio#1220 - minimum number of DISTINCT cross-collection DOMAINS an
     * entity must connect into before it is worth a digest. A record that has a
     * high edge degree but only links into ONE domain (e.g. 180 edges all to one
     * hub of actors) is a single-hub artefact, not a genuine cross-collection
     * connector. Requiring >= 2 distinct domains keeps the digest focused on
     * records that actually bridge collections (records <-> people, places,
     * subjects, accessions, ...). Conservative on purpose: 2 is the lowest value
     * that still means "crosses a boundary". Tune here if the corpus changes.
     */
    protected int $minCrossDomains = 2;

    /**
     * heratio#1220 - test-fixture title patterns (case-insensitive PCRE, applied
     * to the resolved English title). On a real instance the raw edge-degree
     * ranking is dominated by synthetic QA/demo fixtures ("AI Test 19", "Test
     * AI", "Ironman", "3D People", "watermark test", ...) which are PUBLISHED but
     * carry no real catalogue value - ingesting them would pollute KM.
     *
     * Kept deliberately CONSERVATIVE so a genuine record is never dropped:
     *  - '/\btest\b/i'         : the word "test" as a standalone token (covers
     *                            "Test AI", "AI Test 19", "watermark test",
     *                            "Test DAM", "Test Gallery"). Does NOT match
     *                            substrings inside real words (e.g. "Testament",
     *                            "Contestant", "Protestant") because of the \b
     *                            boundaries.
     *  - '/^(test|ai test)\b/i': a title that simply STARTS with "test"/"ai test"
     *                            (belt-and-braces; already covered by \btest\b,
     *                            but documents intent).
     *  - '/\bironman\b/i'       : a known demo fixture title.
     *  - '/^3d people$/i'       : a known demo fixture title (exact match so a
     *                            real record merely mentioning "3D people"
     *                            survives).
     *
     * To tune: add/remove a pattern here. Each entry is a full PCRE with
     * delimiters and the /i flag; they are ORed (any match excludes the record).
     *
     * @var array<int,string>
     */
    protected array $testTitlePatterns = [
        '/\btest\b/i',
        '/^(test|ai test)\b/i',
        '/\bironman\b/i',
        '/^3d people$/i',
    ];

    /**
     * heratio#1220 - true when a resolved title looks like a synthetic test /
     * demo fixture (see $testTitlePatterns). An empty/absent title is NOT
     * treated as a test here (the root and untitled records are excluded by
     * other gates), so this only fires on a positive pattern hit.
     */
    public function looksLikeTestTitle(?string $title): bool
    {
        $title = is_string($title) ? trim($title) : '';
        if ($title === '') {
            return false;
        }
        foreach ($this->testTitlePatterns as $pattern) {
            if (preg_match($pattern, $title) === 1) {
                return true;
            }
        }

        return false;
    }

    /** heratio#1220 - the test-title patterns, for command-level reporting/tuning. @return array<int,string> */
    public function testTitlePatterns(): array
    {
        return $this->testTitlePatterns;
    }

    /** heratio#1220 - the minimum distinct cross-collection domains an entity must span to qualify. */
    public function minCrossDomains(): int
    {
        return $this->minCrossDomains;
    }

    /**
     * Build a concise, factual natural-language summary of how one record
     * connects across the collection. Plain catalogue facts only - grouped by
     * domain, capped per domain. Returns null when the record has no resolvable
     * connections (so callers can skip writing an empty doc).
     */
    public function connectionsSummary(int $objectId): ?string
    {
        $title = $this->recordTitle($objectId);
        $neighbours = app(\AhgRic\Services\RelationshipService::class)
            ->crossCollectionNeighbours($objectId);

        $groups = $neighbours['groups'] ?? [];
        $total = (int) ($neighbours['total'] ?? 0);

        if (! $groups || $total === 0) {
            return null;
        }

        $label = $title !== null ? '"'.$title.'"' : 'Record #'.$objectId;

        $sentences = [];
        $sentences[] = sprintf(
            '%s is connected to %d related %s across the collection.',
            $label,
            $total,
            $total === 1 ? 'entity' : 'entities'
        );

        foreach ($groups as $group) {
            $domain = (string) ($group['domain'] ?? 'Other');
            $items = $group['items'] ?? [];
            $count = (int) ($group['count'] ?? count($items));
            if (! $items) {
                continue;
            }

            $names = [];
            foreach ($items as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
                if (count($names) >= $this->perDomainCap) {
                    break;
                }
            }
            if (! $names) {
                continue;
            }

            $shown = $this->joinNames($names);
            $remainder = $count - count($names);
            $tail = $remainder > 0 ? sprintf(' and %d more', $remainder) : '';

            $sentences[] = sprintf(
                'Under %s (%d) it links to %s%s.',
                $this->lcFirstDomain($domain),
                $count,
                $shown,
                $tail
            );
        }

        $summary = implode(' ', $sentences);

        if (mb_strlen($summary) > $this->maxChars) {
            $summary = rtrim(mb_substr($summary, 0, $this->maxChars - 3)).'...';
        }

        return $summary;
    }

    /**
     * Write the connections summary (plus the record title and a per-domain
     * breakdown) as a markdown file under docs/reference/graph-connections/ so
     * the KM watcher ingests it. Returns the absolute path written, or null
     * when the record has no connections to summarise.
     */
    public function writeKmDoc(int $objectId): ?string
    {
        $summary = $this->connectionsSummary($objectId);
        if ($summary === null) {
            return null;
        }

        $title = $this->recordTitle($objectId);
        $neighbours = app(\AhgRic\Services\RelationshipService::class)
            ->crossCollectionNeighbours($objectId);
        $groups = $neighbours['groups'] ?? [];
        $total = (int) ($neighbours['total'] ?? 0);

        $dir = base_path('docs/reference/graph-connections');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $slug = $this->recordSlug($objectId);
        $fileBase = $slug !== null ? Str::slug($slug) : (string) $objectId;
        if ($fileBase === '') {
            $fileBase = (string) $objectId;
        }
        // Always suffix the id so two records that slugify the same never collide.
        $path = $dir.'/'.$fileBase.'-'.$objectId.'.md';

        $heading = $title !== null ? $title : ('Record #'.$objectId);

        $lines = [];
        $lines[] = '# Graph connections: '.$heading;
        $lines[] = '';
        $lines[] = '> Cross-collection knowledge-graph summary for information object #'
            .$objectId.'. Auto-generated from the RiC relation graph (heratio#1197 / #1214). '
            .'Plain catalogue facts only - no AI-generated content.';
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = $summary;
        $lines[] = '';
        $lines[] = '## Connections by domain';
        $lines[] = '';

        if ($groups) {
            foreach ($groups as $group) {
                $domain = (string) ($group['domain'] ?? 'Other');
                $items = $group['items'] ?? [];
                $count = (int) ($group['count'] ?? count($items));
                if (! $items) {
                    continue;
                }
                $lines[] = '### '.$domain.' ('.$count.')';
                $lines[] = '';
                $listed = 0;
                foreach ($items as $item) {
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $lines[] = '- '.$name;
                    $listed++;
                    if ($listed >= $this->perDomainCap) {
                        $remainder = $count - $listed;
                        if ($remainder > 0) {
                            $lines[] = '- _... and '.$remainder.' more_';
                        }
                        break;
                    }
                }
                $lines[] = '';
            }
        }

        $lines[] = '## Provenance';
        $lines[] = '';
        $lines[] = '- Record id: '.$objectId;
        if ($slug !== null) {
            $lines[] = '- Record slug: '.$slug;
        }
        $lines[] = '- Total related entities: '.$total;
        $lines[] = '- Generated by: ahg:km-graph-sync (GraphKmBridgeService)';
        $lines[] = '';

        $content = implode("\n", $lines);
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * heratio#1197 / #1214 - rank the most-connected information objects across
     * the unified G/L/A/M graph. Read-only aggregate over the generic relation
     * table: for every information object that participates in a relation (as
     * subject OR object), count its distinct relation edges and return the
     * top-N, highest degree first.
     *
     * Returned shape (one row per entity, already bounded by $limit):
     *   [
     *     'id'           => int,     // information_object id
     *     'title'        => string,  // resolved en title, or "Record #id"
     *     'slug'         => ?string, // for a /{slug} show link, when present
     *     'degree'       => int,     // relation edges touching it
     *     'crossDomains' => int,     // distinct collection domains it links into
     *   ]
     *
     * This is a SELECT-only ranking helper; it writes nothing. The command
     * layer turns the rows into bounded markdown digests.
     *
     * QUALITY FILTER (heratio#1220): the raw edge-degree ranking on a real
     * instance is dominated by synthetic test fixtures (titles like "AI Test
     * 19", "Test AI", "Ironman", "3D People") and by the synthetic tree root
     * (id 1). Ingesting those into KM pollutes it. This method now returns only
     * records that pass every gate below; see topConnectedEntitiesFiltered() for
     * the full accounting (excluded-as-test etc.).
     *
     * @return array<int,array{id:int,title:string,slug:?string,degree:int,crossDomains:int}>
     */
    public function topConnectedEntities(int $limit = 50): array
    {
        return $this->topConnectedEntitiesFiltered($limit)['included'];
    }

    /**
     * heratio#1220 - the quality-filtered ranking plus full exclusion
     * accounting. Read-only. Walks candidate records in descending edge-degree
     * order and keeps only those that pass EVERY gate, stopping once $limit
     * qualifiers are collected. Every candidate examined is accounted for so the
     * command can report counts and never silently truncate.
     *
     * Gates, in order (cheapest first):
     *   1. NOT the synthetic tree root (id != $syntheticRootId).
     *   2. PUBLISHED - has a status row (type_id 158, status_id 160). Drafts and
     *      unpublished working records are never exported to KM.
     *   3. Title does NOT look like a test/demo fixture ($testTitlePatterns).
     *   4. Connects into >= $minCrossDomains DISTINCT collection domains - i.e.
     *      it genuinely bridges collections rather than being a single-hub
     *      artefact with a huge degree into one bucket.
     *
     * The candidate pool is degree-ranked and capped (a generous multiple of the
     * ceiling) so the bounded sweep stays read-only and predictable even when
     * most high-degree records are test fixtures.
     *
     * @return array{
     *   included:array<int,array{id:int,title:string,slug:?string,degree:int,crossDomains:int}>,
     *   considered:int, excluded_root:int, excluded_unpublished:int,
     *   excluded_test:int, excluded_low_cross_domain:int, excluded_no_title:int
     * }
     */
    public function topConnectedEntitiesFiltered(int $limit = 50): array
    {
        $limit = max(1, min($limit, $this->maxEntities));

        $acc = [
            'included' => [],
            'considered' => 0,
            'excluded_root' => 0,
            'excluded_unpublished' => 0,
            'excluded_test' => 0,
            'excluded_low_cross_domain' => 0,
            'excluded_no_title' => 0,
        ];

        if (! Schema::hasTable('relation') || ! Schema::hasTable('information_object')) {
            return $acc;
        }

        // Degree per id, counting an edge once whether the id sits on the
        // subject or the object side. UNION ALL of both endpoint columns, then
        // group. Bounded to information objects only (the record domain) by the
        // join to information_object below.
        $endpoints = DB::table('relation')->select('subject_id as id')
            ->unionAll(DB::table('relation')->select('object_id as id'));

        // Pull a generous candidate pool (10x the ceiling, itself capped) so the
        // post-rank quality filter can drop a long run of test fixtures without
        // starving the result below $limit, while staying hard-bounded.
        $poolSize = min($this->maxEntities * 10, 2000);

        $ranked = DB::query()
            ->fromSub($endpoints, 'e')
            ->join('information_object as io', 'io.id', '=', 'e.id')
            ->groupBy('e.id')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('e.id')
            ->limit($poolSize)
            ->get(['e.id', DB::raw('COUNT(*) as degree')]);

        foreach ($ranked as $row) {
            if (count($acc['included']) >= $limit) {
                break; // enough qualifiers; remaining candidates are reported as over-limit by the command
            }

            $id = (int) $row->id;
            $acc['considered']++;

            // Gate 1 - synthetic tree root.
            if ($id === $this->syntheticRootId) {
                $acc['excluded_root']++;

                continue;
            }

            // Gate 2 - must be PUBLISHED.
            if (! $this->isPublished($id)) {
                $acc['excluded_unpublished']++;

                continue;
            }

            $title = $this->recordTitle($id);

            // Gate 2b - a high-degree record with no resolvable title is not
            // worth a digest (and cannot be name-checked); exclude it.
            if ($title === null) {
                $acc['excluded_no_title']++;

                continue;
            }

            // Gate 3 - title looks like a test / demo fixture.
            if ($this->looksLikeTestTitle($title)) {
                $acc['excluded_test']++;

                continue;
            }

            // Gate 4 - must bridge >= minCrossDomains distinct domains.
            $neighbours = app(\AhgRic\Services\RelationshipService::class)
                ->crossCollectionNeighbours($id);
            $groups = $neighbours['groups'] ?? [];
            $crossDomains = is_array($groups) ? count($groups) : 0;

            if ($crossDomains < $this->minCrossDomains) {
                $acc['excluded_low_cross_domain']++;

                continue;
            }

            $acc['included'][] = [
                'id' => $id,
                'title' => $title,
                'slug' => $this->recordSlug($id),
                'degree' => (int) $row->degree,
                'crossDomains' => $crossDomains,
            ];
        }

        return $acc;
    }

    /**
     * heratio#1220 - is this information object PUBLISHED? True when a status row
     * exists with type_id = publication-status (158) and status_id = Published
     * (160). Read-only. Records with no status row, or a Draft (159) row, return
     * false and are never exported to KM.
     */
    public function isPublished(int $objectId): bool
    {
        if (! Schema::hasTable('status')) {
            // No status table: cannot prove publication, so refuse (fail closed).
            return false;
        }

        return DB::table('status')
            ->where('object_id', $objectId)
            ->where('type_id', $this->publicationStatusTypeId)
            ->where('status_id', $this->publishedStatusId)
            ->exists();
    }

    /**
     * Count how many distinct information objects participate in at least one
     * relation (the candidate universe for the graph digest). Read-only. Used
     * by the export command to report what fraction of the graph a bounded run
     * covers ("wrote top N of M connected records").
     */
    public function connectedEntityCount(): int
    {
        if (! Schema::hasTable('relation') || ! Schema::hasTable('information_object')) {
            return 0;
        }

        $endpoints = DB::table('relation')->select('subject_id as id')
            ->unionAll(DB::table('relation')->select('object_id as id'));

        return (int) DB::query()
            ->fromSub($endpoints, 'e')
            ->join('information_object as io', 'io.id', '=', 'e.id')
            ->distinct()
            ->count('e.id');
    }

    /**
     * Hard ceiling on how many entities a single export run may render, even if
     * a caller passes a larger --limit. The command echoes this so an operator
     * sees why a big --limit was clamped.
     */
    public function maxEntities(): int
    {
        return $this->maxEntities;
    }

    /**
     * Resolve the record's own English title from information_object_i18n.
     * Returns null when no title is found.
     */
    public function recordTitle(int $objectId): ?string
    {
        if (! Schema::hasTable('information_object_i18n')) {
            return null;
        }
        $title = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', 'en')
            ->value('title');
        $title = is_string($title) ? trim($title) : null;

        return ($title !== null && $title !== '') ? $title : null;
    }

    /**
     * Resolve the record's slug from the slug table. Used for a stable,
     * human-readable doc filename. Returns null when none exists.
     */
    public function recordSlug(int $objectId): ?string
    {
        if (! Schema::hasTable('slug')) {
            return null;
        }
        $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');
        $slug = is_string($slug) ? trim($slug) : null;

        return ($slug !== null && $slug !== '') ? $slug : null;
    }

    /**
     * Join a list of names into an Oxford-comma English phrase:
     * ["A"] -> "A"; ["A","B"] -> "A and B"; ["A","B","C"] -> "A, B, and C".
     *
     * @param  array<int,string>  $names
     */
    protected function joinNames(array $names): string
    {
        $names = array_values(array_map(fn ($n) => "'".$n."'", $names));
        $n = count($names);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $names[0];
        }
        if ($n === 2) {
            return $names[0].' and '.$names[1];
        }
        $last = array_pop($names);

        return implode(', ', $names).', and '.$last;
    }

    /**
     * Lower-case the first letter of a domain label for mid-sentence use,
     * leaving acronyms / parenthetical "(RiC)" suffixes intact.
     */
    protected function lcFirstDomain(string $domain): string
    {
        return mb_strtolower(mb_substr($domain, 0, 1)).mb_substr($domain, 1);
    }
}
