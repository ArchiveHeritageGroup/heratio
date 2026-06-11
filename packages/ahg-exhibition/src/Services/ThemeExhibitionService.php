<?php

/**
 * ThemeExhibitionService - Heratio ahg-exhibition
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgExhibition\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * heratio#1186 - generative exhibitions (single-shot variant).
 *
 * Where {@see GenerativeExhibitionService} runs a two-step "draft -> review -> build" flow,
 * this service does the whole thing in one call: a curator enters a theme, the catalogue is
 * searched for on-theme objects, the AI gateway picks and orders the best of them into a
 * narrative, and a single real Exhibition Space (a gallery room) is created with each chosen
 * object laid out on the floor - ready for the curator to walk through or fine-tune in the
 * builder.
 *
 * It is built entirely on the existing public API of {@see ExhibitionSpaceService}
 * (create / createPlacementAt / getById) and the AI gateway via the LlmService abstraction -
 * it never touches a GPU node directly and never mutates an existing space.
 */
class ThemeExhibitionService
{
    public function __construct(private ExhibitionSpaceService $spaces) {}

    /**
     * Curate a themed exhibition end-to-end and return the created space.
     *
     * @param  string       $theme       Free-text theme / prompt (e.g. "maps and exploration").
     * @param  int          $maxObjects  Hard cap on objects to place (1-48).
     * @param  string|null  $building    Optional building name for the new gallery space.
     * @return array{space_id:int, slug:string, placed:int}
     *
     * @throws \InvalidArgumentException when the theme is empty.
     * @throws \RuntimeException when no on-theme catalogue objects can be found.
     */
    public function curate(string $theme, int $maxObjects = 12, ?string $building = null): array
    {
        $theme = trim($theme);
        if ($theme === '') {
            throw new \InvalidArgumentException('A theme is required to generate an exhibition.');
        }
        $maxObjects = max(1, min(48, $maxObjects));

        // 1) Candidate pool from the real catalogue (capped ~60).
        $candidates = $this->candidateObjects($theme, 60);
        if (! $candidates) {
            throw new \RuntimeException('No catalogue objects matched that theme. Try broader or different words.');
        }

        // 2) Let the AI pick + order the best fit; fall back to top keyword matches if unavailable.
        $chosenIds = $this->selectWithAi($theme, $candidates, $maxObjects);
        if (! $chosenIds) {
            $chosenIds = array_slice(array_keys($candidates), 0, $maxObjects);
        }
        $chosenIds = array_values(array_slice($chosenIds, 0, $maxObjects));
        if (! $chosenIds) {
            throw new \RuntimeException('Could not assemble an exhibition for that theme.');
        }

        // 3) Build a real Exhibition Space and place every chosen object across the floor.
        return DB::transaction(function () use ($theme, $building, $chosenIds) {
            $name = $this->spaceName($theme);
            $spaceId = $this->spaces->create([
                'name' => $name,
                'space_type' => 'gallery',
                'building' => $building ?: null,
                'capacity_unit' => 'linear_wall_meters',
                'room_w' => 12,
                'room_d' => 10,
                'room_h' => 4,
                'notes' => 'Auto-generated from the theme: '.$theme,
            ]);

            $placed = $this->placeAcrossFloor($spaceId, $chosenIds);

            $space = $this->spaces->getById($spaceId);
            $slug = $space ? (string) $space->slug : '';

            return ['space_id' => $spaceId, 'slug' => $slug, 'placed' => $placed];
        });
    }

    /**
     * Spread the chosen objects evenly across the gallery floor in a grid so the walkthrough
     * has a coherent layout straight away. pos_x / pos_y are normalised 0..1 (the builder's
     * convention). A real placement row is created per object via the existing service method.
     */
    private function placeAcrossFloor(int $spaceId, array $ioIds): int
    {
        $ioIds = array_values(array_unique(array_map('intval', $ioIds)));
        $count = count($ioIds);
        if ($count === 0) {
            return 0;
        }

        $cols = (int) max(1, ceil(sqrt($count)));
        $rows = (int) max(1, ceil($count / $cols));

        $placed = 0;
        foreach ($ioIds as $i => $ioId) {
            if ($ioId <= 0) {
                continue;
            }
            $col = $i % $cols;
            $row = intdiv($i, $cols);
            $posX = ($col + 1) / ($cols + 1);
            $posY = ($row + 1) / ($rows + 1);
            try {
                $this->spaces->createPlacementAt($spaceId, $ioId, $posX, $posY);
                $placed++;
            } catch (\Throwable $e) {
                Log::info('[ahg-exhibition] theme curate: skipped object '.$ioId.' - '.$e->getMessage());
            }
        }

        return $placed;
    }

    /**
     * On-theme candidate pool drawn from the real catalogue.
     *
     * Primary path is Elasticsearch semantic/topical recall (#1186): a multi_match over the
     * description's title + scope/content + the other narrative i18n fields, so a theme like
     * "Ndebele beadwork" or "WWI letters" pulls topically relevant records rather than only
     * literal substring matches. The ES path enforces the same guarantees as the keyword path -
     * published records only (publicationStatusId = 160), the root collection excluded, a
     * non-empty title - and applies the identical media-preference re-ranking so objects that
     * actually have something to display float to the top.
     *
     * If Elasticsearch is unreachable, the index is missing, or it returns zero hits, this
     * degrades gracefully to the original keyword/LIKE DB select ({@see candidateObjectsKeyword()}).
     * The return contract is identical for both paths - id => [id,title,scope] - so
     * {@see selectWithAi()} and {@see curate()} are untouched.
     *
     * @return array<int,array{id:int,title:string,scope:string}>
     */
    private function candidateObjects(string $theme, int $limit): array
    {
        try {
            $hits = $this->candidateObjectsEs($theme, $limit);
            if ($hits) {
                return $hits;
            }
            Log::debug('[ahg-exhibition] theme curate: ES returned no hits, falling back to keyword search.');
        } catch (\Throwable $e) {
            Log::debug('[ahg-exhibition] theme curate: ES candidate search unavailable, falling back to keyword - '.$e->getMessage());
        }

        return $this->candidateObjectsKeyword($theme, $limit);
    }

    /**
     * Elasticsearch candidate recall. Reuses the existing ES client in ahg-search
     * ({@see \AhgSearch\Services\ElasticsearchService::search()}) - the generic raw-search entry
     * point that prepends the configured index prefix - rather than hand-rolling any HTTP. The
     * query runs against the archival-description index (qubitinformationobject).
     *
     * Query: a multi_match (best_fields + a most_fields companion + a phrase boost) over the
     * indexed narrative text fields - title (boosted), scopeAndContent, archivalHistory,
     * extentAndMedium and accessConditions - which together carry the topical content of a
     * description. Filters mirror the keyword path exactly: published only (status 160), the root
     * collection (parentId 1) excluded, and a title required.
     *
     * Returns id => [id,title,scope] (identical shape to the keyword path) after the same
     * media-preference re-ranking. Throws on transport/index errors so the caller can fall back.
     *
     * @return array<int,array{id:int,title:string,scope:string}>
     */
    private function candidateObjectsEs(string $theme, int $limit, string $culture = 'en'): array
    {
        // Reuse the ahg-search ES client. Its constructor reads ELASTICSEARCH_HOST / prefix from
        // config, so no wiring is needed here; search() prepends the "heratio_" prefix to the
        // bare index name we pass.
        $es = app(\AhgSearch\Services\ElasticsearchService::class);

        $titleField = "i18n.{$culture}.title";
        $textFields = [
            "i18n.{$culture}.title^3",
            "i18n.{$culture}.scopeAndContent",
            "i18n.{$culture}.archivalHistory",
            "i18n.{$culture}.extentAndMedium",
            "i18n.{$culture}.accessConditions",
        ];

        // Recall as wide as we can score, then re-rank locally (media preference) and trim to
        // $limit - mirrors the keyword path, which pulls 120 then ranks down to $limit.
        $size = max($limit, 120);

        $body = [
            '_source' => [$titleField, "i18n.{$culture}.scopeAndContent"],
            'query' => [
                'bool' => [
                    // best_fields handles the "any one field is a strong match" case; the
                    // most_fields companion rewards records that hit the theme across several
                    // fields; the match_phrase boost lifts records carrying the theme as a phrase.
                    'should' => [
                        ['multi_match' => [
                            'query' => $theme,
                            'type' => 'best_fields',
                            'fields' => $textFields,
                            'operator' => 'or',
                        ]],
                        ['multi_match' => [
                            'query' => $theme,
                            'type' => 'most_fields',
                            'fields' => $textFields,
                            'operator' => 'or',
                        ]],
                        ['multi_match' => [
                            'query' => $theme,
                            'type' => 'phrase',
                            'fields' => $textFields,
                            'slop' => 2,
                            'boost' => 2,
                        ]],
                    ],
                    'minimum_should_match' => 1,
                    'filter' => [
                        // Published records only (status 160) - same guarantee as the keyword path.
                        ['term' => ['publicationStatusId' => 160]],
                        // Exclude the root collection (parent_id 1), as the keyword path does.
                        ['bool' => ['must_not' => [['term' => ['parentId' => 1]]]]],
                        // Require a title - the displayable handle every record needs.
                        ['exists' => ['field' => $titleField]],
                    ],
                ],
            ],
        ];

        $result = $es->search('qubitinformationobject', $body, 0, $size);
        $hits = $result['hits']['hits'] ?? null;
        if (! is_array($hits) || ! $hits) {
            return [];
        }

        // Map ES hits -> the rows shape the media-preference re-ranker expects, preserving the
        // relevance order ES returned them in (used as the tiebreaker in rankCandidates()).
        $rows = [];
        $rank = 0;
        foreach ($hits as $h) {
            $id = (int) ($h['_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $i18n = $h['_source']['i18n'][$culture] ?? [];
            $title = (string) ($i18n['title'] ?? '');
            if ($title === '') {
                continue;   // title is the displayable handle; skip blanks (matches keyword path)
            }
            $rows[] = (object) [
                'id' => $id,
                'title' => $title,
                'scope_and_content' => (string) ($i18n['scopeAndContent'] ?? ''),
                'es_rank' => $rank++,   // 0-based ES relevance position (lower = better)
            ];
        }
        if (! $rows) {
            return [];
        }

        return $this->rankCandidates($rows, $limit);
    }

    /**
     * Re-rank a candidate row set by media preference and return the id => [id,title,scope] map
     * the caller contracts on. Shared by the ES and keyword paths. A row exposes ->id, ->title
     * and ->scope_and_content; for ES rows ->es_rank carries the relevance order so equally-ranked
     * media/non-media records keep ES's order. The keyword path passes its own ->score instead.
     *
     * @param  iterable<object>  $rows
     * @return array<int,array{id:int,title:string,scope:string}>
     */
    private function rankCandidates(iterable $rows, int $limit): array
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows);

        // Flag objects that have something to show (a digital object or a 3D model) via batched
        // whereIn probes, merged in PHP - simpler and more portable than a correlated subquery.
        $ids = array_values(array_filter(array_map(fn ($r) => (int) $r->id, $rows)));
        $mediaIds = [];
        if ($ids) {
            $mediaIds = array_flip(DB::table('digital_object')
                ->whereIn('object_id', $ids)->distinct()->pluck('object_id')->all());
            if ($this->tableExists('object_3d_model')) {
                foreach (DB::table('object_3d_model')->whereIn('object_id', $ids)->distinct()->pluck('object_id') as $mid) {
                    $mediaIds[(int) $mid] = true;
                }
            }
        }

        $scored = [];
        foreach ($rows as $r) {
            $id = (int) $r->id;
            // Pre-computed relevance: keyword token score if present, else 0 for ES rows (ES has
            // already ordered them; es_rank breaks ties). +1 for having something to display.
            $score = (int) ($r->score ?? 0);
            if (isset($mediaIds[$id])) {
                $score += 1;   // prefer objects that have something to display
            }
            $scored[] = [
                'id' => $id,
                'title' => (string) $r->title,
                'scope' => trim(mb_substr(strip_tags((string) ($r->scope_and_content ?? '')), 0, 240)),
                'score' => $score,
                'es_rank' => isset($r->es_rank) ? (int) $r->es_rank : PHP_INT_MAX,
            ];
        }
        // Higher score first; within equal scores, keep the incoming (ES relevance) order.
        usort($scored, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['es_rank'] <=> $b['es_rank']));

        $out = [];
        foreach (array_slice($scored, 0, $limit) as $c) {
            $out[$c['id']] = ['id' => $c['id'], 'title' => $c['title'], 'scope' => $c['scope']];
        }

        return $out;
    }

    /**
     * On-theme candidate pool via the keyword/LIKE DB select. This is the original recall path,
     * kept intact as the graceful fallback for {@see candidateObjects()} when Elasticsearch is
     * unavailable or returns nothing. Matches the theme tokens against title + scope_and_content,
     * and prefers objects that actually have something to display (a digital_object or a 3D model)
     * so the generated show is not empty boxes. Ranked by keyword overlap (title weighted, +1 for
     * having media). Returns id => [id,title,scope].
     *
     * @return array<int,array{id:int,title:string,scope:string}>
     */
    private function candidateObjectsKeyword(string $theme, int $limit): array
    {
        $tokens = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($theme)) ?: [],
            fn ($t) => mb_strlen($t) >= 3
        ));

        $q = DB::table('information_object_i18n as i')
            ->join('information_object as io', 'io.id', '=', 'i.id')
            ->where('i.culture', 'en')
            ->where('io.parent_id', '!=', 1)
            ->whereNotNull('i.title')
            ->where('i.title', '!=', '');

        if ($tokens) {
            $q->where(function ($w) use ($tokens) {
                foreach ($tokens as $t) {
                    $w->orWhere('i.title', 'like', '%'.$t.'%')
                        ->orWhere('i.scope_and_content', 'like', '%'.$t.'%');
                }
            });
        }

        $rows = $q->select('io.id', 'i.title', 'i.scope_and_content')->limit(120)->get();

        // Pre-score each row by keyword overlap (title weighted 2, scope 1), then hand off to the
        // shared media-preference re-ranker + shaper so both recall paths produce the same shape.
        foreach ($rows as $r) {
            $title = mb_strtolower((string) $r->title);
            $scope = mb_strtolower(strip_tags((string) ($r->scope_and_content ?? '')));
            $score = 0;
            foreach ($tokens as $t) {
                if (mb_strpos($title, $t) !== false) {
                    $score += 2;
                }
                if (mb_strpos($scope, $t) !== false) {
                    $score += 1;
                }
            }
            $r->score = $score;
        }

        return $this->rankCandidates($rows, $limit);
    }

    /**
     * Ask the AI gateway to pick up to $maxObjects candidates that best fit the theme and order
     * them into a narrative. Candidates are shown with short 1-based NUMBERS (the model copies
     * small numbers reliably; it tends to half-invent long ids). The returned numbers are mapped
     * back to information_object ids and validated against the candidate set. Returns [] when the
     * LLM is unavailable or returns nothing usable, so the caller can fall back to keyword order.
     *
     * @param  array<int,array{id:int,title:string,scope:string}>  $candidates
     * @return array<int,int>  ordered information_object ids
     */
    private function selectWithAi(string $theme, array $candidates, int $maxObjects): array
    {
        $ordered = array_values(array_slice($candidates, 0, 50, true));   // 0-based position -> candidate
        $lines = [];
        foreach ($ordered as $i => $c) {
            $scope = $c['scope'] !== '' ? ' - '.mb_substr($c['scope'], 0, 120) : '';
            $lines[] = ($i + 1).'. '.$c['title'].$scope;
        }
        $list = implode("\n", $lines);

        $prompt = "You are a museum curator assembling an exhibition on the theme: \"{$theme}\".\n"
            ."From the numbered candidate objects below, choose up to {$maxObjects} that best fit the "
            ."theme and put them in the order a visitor should encounter them to tell a coherent story.\n"
            ."Refer to each object ONLY by its number from the list - never invent objects.\n"
            ."Return ONLY a JSON array of the chosen numbers, in narrative order, e.g. [3,1,8,5]. "
            ."No prose, no keys, just the array.\n\n"
            ."CANDIDATES:\n".$list;

        try {
            $resp = (string) app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 300, 'temperature' => 0.4]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] theme curate LLM unavailable: '.$e->getMessage());

            return [];
        }

        return $this->parseChosen($resp, $ordered, $maxObjects);
    }

    /**
     * Parse the model's chosen-number array back into validated information_object ids.
     * Accepts a bare JSON array of numbers, or any stray integers in the response as a
     * last resort. Only numbers that index a real candidate are kept, de-duplicated, in order.
     *
     * @param  array<int,array{id:int,title:string,scope:string}>  $ordered
     * @return array<int,int>
     */
    private function parseChosen(string $resp, array $ordered, int $maxObjects): array
    {
        $resp = trim($resp);
        $nums = [];

        $start = strpos($resp, '[');
        $end = strrpos($resp, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($resp, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                foreach ($decoded as $v) {
                    if (is_int($v) || (is_string($v) && ctype_digit(trim($v)))) {
                        $nums[] = (int) $v;
                    } elseif (is_array($v) && isset($v['n'])) {
                        $nums[] = (int) $v['n'];
                    }
                }
            }
        }
        if (! $nums && preg_match_all('/\d+/', $resp, $m)) {
            $nums = array_map('intval', $m[0]);
        }

        $ids = [];
        foreach ($nums as $n) {
            if ($n >= 1 && isset($ordered[$n - 1])) {
                $id = (int) $ordered[$n - 1]['id'];
                if ($id > 0 && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    if (count($ids) >= $maxObjects) {
                        break;
                    }
                }
            }
        }

        return $ids;
    }

    /** "Theme - generated" space name, trimmed to the column width. */
    private function spaceName(string $theme): string
    {
        $base = mb_substr($theme, 0, 80);

        return mb_substr($base.' - generated', 0, 120);
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
