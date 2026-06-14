<?php

/**
 * GenerativeExhibitionService - Heratio ahg-exhibition
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
 * heratio#1186 - generative exhibitions. Given a theme: find candidate objects in the
 * catalogue (ES topical recall + theme ranking), let the AI gateway curate them into draft
 * rooms with one-line object labels (suggest()), then build that draft into a real multi-room
 * Exhibition Space - placing each object, persisting its label as the wall note, and generating
 * + saving an exhibition intro, per-room blurbs, and a highlights guided tour (buildExhibition()).
 * All AI calls route through the AHG gateway (LlmService / KmContextService), never a direct node.
 */
class GenerativeExhibitionService
{
    public function __construct(private ExhibitionSpaceService $spaces) {}

    /** @return array{ok:bool, theme:string, rooms:array, candidate_count:int} */
    public function suggest(string $theme, int $maxObjects = 12, bool $publishedOnly = true): array
    {
        $theme = trim($theme);
        $out = ['ok' => false, 'theme' => $theme, 'rooms' => [], 'candidate_count' => 0];
        if ($theme === '') {
            return $out;
        }

        $candidates = $this->candidateObjects($theme, 60, $publishedOnly);
        $out['candidate_count'] = count($candidates);
        if (! $candidates) {
            return $out;
        }

        $rooms = $this->curate($theme, $candidates, $maxObjects);
        if (! $rooms) {
            return $out;
        }
        $out['rooms'] = $rooms;
        $out['ok'] = true;

        return $out;
    }

    /**
     * heratio#1186 - turn a reviewed draft into a real Exhibition Space. Creates one room
     * (a sibling ahg_exhibition_space sharing a building_id) per draft room, lays each chosen
     * object out along the room walls as a real placement, and returns the first room's slug so
     * the caller can drop the curator straight into the builder. Idempotent only in the sense
     * that each call builds a fresh space - it never mutates an existing one.
     *
     * @param  array{theme?:string, rooms:array<int,array{room?:string, objects:array<int,array{id:int}>}>}  $draft
     * @return array{ok:bool, space_id:int, slug:string, rooms:int, placed:int, error?:string}
     */
    public function buildExhibition(array $draft): array
    {
        $theme = trim((string) ($draft['theme'] ?? ''));
        $rooms = array_values(array_filter((array) ($draft['rooms'] ?? []), 'is_array'));
        if (! $rooms) {
            return ['ok' => false, 'space_id' => 0, 'slug' => '', 'rooms' => 0, 'placed' => 0, 'error' => 'empty draft'];
        }

        try {
            $built = DB::transaction(function () use ($theme, $rooms) {
                $firstId = 0;
                $firstSlug = '';
                $roomCount = 0;
                $placed = 0;
                $first = null;
                $meta = [];   // per room: id, name, objects [{id,title,label}] - drives narrative + tour

                foreach ($rooms as $idx => $room) {
                    $name = trim((string) ($room['room'] ?? '')) ?: ($theme !== '' ? $theme.' - Room '.($idx + 1) : 'Room '.($idx + 1));

                    if ($idx === 0) {
                        $firstId = $this->spaces->create([
                            'name' => $name, 'space_type' => 'gallery', 'capacity_unit' => 'linear_wall_meters',
                            'room_w' => 10, 'room_d' => 8, 'room_h' => 4,
                        ]);
                        $first = $this->spaces->getById($firstId);
                        // Make the first room a building member (so addBuildingRoom appends siblings)
                        // and give it the same unit-rectangle shape + plan position a new room gets.
                        DB::table('ahg_exhibition_space')->where('id', $firstId)->update([
                            'building_id' => $first->slug, 'building_seq' => 0, 'bld_x' => 1, 'bld_y' => 1,
                            'shape_json' => json_encode([['x' => 0, 'z' => 0], ['x' => 1, 'z' => 0], ['x' => 1, 'z' => 1], ['x' => 0, 'z' => 1]]),
                            'updated_at' => now(),
                        ]);
                        $first = $this->spaces->getById($firstId);
                        $firstSlug = (string) $first->slug;
                        $roomId = $firstId;
                    } else {
                        $added = $this->spaces->addBuildingRoom($first, $name);
                        $roomId = (int) $added['id'];
                    }
                    $roomCount++;
                    $objs = array_values(array_filter((array) ($room['objects'] ?? []), 'is_array'));
                    $placed += $this->placeRoomObjects($roomId, $objs);
                    $meta[] = ['id' => $roomId, 'name' => $name, 'objects' => $objs];
                }

                return ['first' => $first, 'space_id' => $firstId, 'slug' => $firstSlug, 'rooms' => $roomCount, 'placed' => $placed, 'meta' => $meta];
            });

            // Narrative text (intro + per-room blurbs) and the guided tour are generated AFTER
            // the build commits, so a slow or failed gateway/KM call can never roll back the
            // built exhibition - it just lands without the generated text (fail-soft).
            $this->decorateWithNarrative($theme, $built);

            return ['ok' => true, 'space_id' => $built['space_id'], 'slug' => $built['slug'], 'rooms' => $built['rooms'], 'placed' => $built['placed']];
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] buildExhibition failed: '.$e->getMessage());

            return ['ok' => false, 'space_id' => 0, 'slug' => '', 'rooms' => 0, 'placed' => 0, 'error' => 'build failed'];
        }
    }

    /**
     * heratio#1186 - after a draft is built into a real space, generate the exhibition
     * introduction, a short blurb per room, and a guided tour, then persist them. Fully
     * fail-soft: any gateway/KM failure leaves the built exhibition intact (just without the
     * generated text). All AI calls route through the AHG gateway (LlmService / KmContextService)
     * - never a direct node.
     *
     * @param  array{first:?object, meta:array<int,array{id:int,name:string,objects:array}>}  $built
     */
    private function decorateWithNarrative(string $theme, array $built): void
    {
        $first = $built['first'] ?? null;
        $meta = $built['meta'] ?? [];
        if (! $first || ! $meta) {
            return;
        }

        // 1) Intro + per-room blurbs (one batched gateway call), persisted to ahg_exhibition_space.
        try {
            $narrative = $this->generateNarrative($theme, $meta);
            if ($narrative) {
                $intro = trim((string) ($narrative['intro'] ?? ''));
                $blurbs = (array) ($narrative['blurbs'] ?? []);
                foreach ($meta as $idx => $room) {
                    $update = ['updated_at' => now()];
                    if ($idx === 0 && $intro !== '') {
                        $update['intro_text'] = $intro;   // whole-exhibition intro on the main room
                    }
                    $blurb = trim((string) ($blurbs[$idx] ?? ''));
                    if ($blurb !== '') {
                        $update['room_blurb'] = $blurb;
                    }
                    if (count($update) > 1) {
                        DB::table('ahg_exhibition_space')->where('id', (int) $room['id'])->update($update);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('[ahg-exhibition] generative narrative skipped: '.$e->getMessage());
        }

        // 2) Guided tour: one "highlights" tour walking every placed object in room order,
        //    narrated by each object's one-line label. Persisted via the existing tour writer.
        try {
            $stops = [];
            foreach ($meta as $room) {
                foreach ((array) ($room['objects'] ?? []) as $o) {
                    if (! is_array($o) || (int) ($o['id'] ?? 0) <= 0) {
                        continue;
                    }
                    $stops[] = ['io_id' => (int) $o['id'], 'narration' => trim((string) ($o['label'] ?? '')), 'dwell' => 6];
                }
            }
            if ($stops) {
                $tourName = mb_substr(($theme !== '' ? $theme : 'Exhibition').' - Highlights', 0, 80);
                $this->spaces->saveGuidedTour($first, [['name' => $tourName, 'stops' => $stops]]);
            }
        } catch (\Throwable $e) {
            Log::info('[ahg-exhibition] generative tour skipped: '.$e->getMessage());
        }
    }

    /**
     * One batched gateway call: returns the exhibition intro + a blurb per room, grounded
     * (best-effort) in KM. Returns ['intro'=>string, 'blurbs'=>[roomIndex=>string]] or [] on failure.
     */
    private function generateNarrative(string $theme, array $meta): array
    {
        $roomLines = [];
        foreach ($meta as $idx => $room) {
            $titles = [];
            foreach ((array) ($room['objects'] ?? []) as $o) {
                if (is_array($o) && ! empty($o['title'])) {
                    $titles[] = (string) $o['title'];
                }
            }
            $roomLines[] = 'Room '.$idx.' ("'.((string) ($room['name'] ?? ('Room '.$idx))).'"): '.(implode('; ', array_slice($titles, 0, 12)) ?: '(no objects)');
        }
        $roomsBlock = implode("\n", $roomLines);

        $kmHint = '';
        try {
            if (class_exists(\AhgExhibition\Services\KmContextService::class)) {
                $km = app(\AhgExhibition\Services\KmContextService::class)->ask($theme);
                if (is_string($km) && trim($km) !== '') {
                    $kmHint = "\n\nBackground from the knowledge base (use only if relevant, do not quote verbatim):\n".mb_substr(trim($km), 0, 1200);
                }
            }
        } catch (\Throwable $e) {
            // KM is optional grounding; ignore failures.
        }

        $prompt = "You are a museum curator writing the wall text for an exhibition on the theme: \"{$theme}\".\n"
            ."The exhibition has these rooms and objects:\n{$roomsBlock}".$kmHint."\n\n"
            ."Write (1) a 2 to 3 sentence introduction to the whole exhibition, and (2) a 1 to 2 sentence blurb for EACH room that ties its objects to the theme. Be factual and engaging; do not invent objects.\n"
            ."Return ONLY valid JSON, no preamble, in exactly this shape:\n"
            ."{\"intro\":\"...\",\"rooms\":[{\"i\":0,\"blurb\":\"...\"}]}";

        try {
            $resp = (string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 700, 'temperature' => 0.5]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] generative narrative LLM failed: '.$e->getMessage());

            return [];
        }

        $json = $this->extractJsonObject($resp);
        $parsed = $json !== null ? json_decode($json, true) : null;
        if (! is_array($parsed)) {
            return [];
        }
        $blurbs = [];
        foreach ((array) ($parsed['rooms'] ?? []) as $r) {
            if (is_array($r) && isset($r['i'])) {
                $blurbs[(int) $r['i']] = trim((string) ($r['blurb'] ?? ''));
            }
        }

        return ['intro' => trim((string) ($parsed['intro'] ?? '')), 'blurbs' => $blurbs];
    }

    /** Pull the first JSON object out of a model response. */
    private function extractJsonObject(string $resp): ?string
    {
        $resp = trim($resp);
        $start = strpos($resp, '{');
        $end = strrpos($resp, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($resp, $start, $end - $start + 1);
        }

        return null;
    }

    /**
     * Lay a room's objects out along the walls and create a real placement for each. Objects
     * spread evenly along the back wall, wrapping to the front wall past six, so the walkthrough
     * has something coherent to render before the curator fine-tunes positions in the builder.
     */
    private function placeRoomObjects(int $roomId, array $objects): int
    {
        // Keep the AI's one-line label alongside each id (persisted as the placement note /
        // wall-label text), de-duplicated by object id while preserving order.
        $labels = [];
        foreach ($objects as $o) {
            $ioId = (int) (is_array($o) ? ($o['id'] ?? 0) : $o);
            if ($ioId > 0 && ! array_key_exists($ioId, $labels)) {
                $labels[$ioId] = is_array($o) ? trim((string) ($o['label'] ?? '')) : '';
            }
        }
        if (! $labels) {
            return 0;
        }

        $ids = array_keys($labels);
        $perWall = (int) ceil(count($ids) / 2);   // back wall first, then front wall
        $n = 0;
        foreach ($ids as $i => $ioId) {
            $onBack = $i < $perWall;
            $wallCount = $onBack ? min($perWall, count($ids)) : (count($ids) - $perWall);
            $slot = $onBack ? $i : ($i - $perWall);
            $posX = $wallCount > 0 ? ($slot + 1) / ($wallCount + 1) : 0.5;
            $posY = $onBack ? 0.12 : 0.88;
            try {
                $pl = $this->spaces->createPlacementAt($roomId, $ioId, $posX, $posY);
                $label = $labels[$ioId];
                if ($label !== '' && is_array($pl) && ! empty($pl['id'])) {
                    DB::table('ahg_exhibition_placement')->where('id', (int) $pl['id'])->update(['notes' => $label, 'updated_at' => now()]);
                }
                $n++;
            } catch (\Throwable $e) {
                Log::info('[ahg-exhibition] buildExhibition: skipped object '.$ioId.' - '.$e->getMessage());
            }
        }

        return $n;
    }

    /**
     * Candidate pool, theme-ranked. Prefers objects already placed in exhibition rooms
     * (real, curated, contextual), ranked by how well they match the theme; falls back to a
     * catalogue keyword search when no exhibition objects exist (fresh installs).
     *
     * @param  bool  $publishedOnly  restrict to published records (status type 158 = 160).
     */
    private function candidateObjects(string $theme, int $limit, bool $publishedOnly = true): array
    {
        $tokens = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($theme)), function ($t) {
            return mb_strlen($t) >= 3;
        }));

        // 1) Objects already placed in exhibition rooms.
        $q1 = DB::table('ahg_exhibition_placement as ep')
            ->join('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'ep.information_object_id')->where('i.culture', '=', 'en'); })
            ->whereNotNull('ep.information_object_id')
            ->whereNotNull('i.title')->where('i.title', '!=', '');
        $this->applyPublishedFilter($q1, 'ep.information_object_id', $publishedOnly);
        $rows = $q1->select('ep.information_object_id as id', 'i.title', 'i.scope_and_content')->distinct()->get();

        // 2) Fallback to the catalogue if no room objects exist yet. Prefer Elasticsearch
        //    semantic/topical recall (#1186); if ES is unavailable or yields nothing, fall back
        //    to the original keyword/LIKE catalogue search so the flow never hard-depends on ES.
        if ($rows->isEmpty() && $tokens) {
            $rows = $this->candidateObjectsEsRows($theme, 80, $publishedOnly);
        }
        if ($rows->isEmpty() && $tokens) {
            $q2 = DB::table('information_object_i18n as i')
                ->join('information_object as io', 'io.id', '=', 'i.id')
                ->where('i.culture', 'en')->where('io.parent_id', '!=', 1)
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) {
                        $q->orWhere('i.title', 'like', '%'.$t.'%')->orWhere('i.scope_and_content', 'like', '%'.$t.'%');
                    }
                })
                ->whereNotNull('i.title')->where('i.title', '!=', '');
            $this->applyPublishedFilter($q2, 'io.id', $publishedOnly);
            $rows = $q2->select('io.id', 'i.title', 'i.scope_and_content')->limit(80)->get();
        }

        // Rank by theme-keyword overlap (title weighted), so the most on-theme room objects
        // lead. With no usable tokens, keep natural order.
        $scored = [];
        foreach ($rows as $r) {
            $title = mb_strtolower((string) $r->title);
            $scope = mb_strtolower(strip_tags((string) ($r->scope_and_content ?? '')));
            $score = 0;
            foreach ($tokens as $t) {
                if (mb_strpos($title, $t) !== false) { $score += 2; }
                if (mb_strpos($scope, $t) !== false) { $score += 1; }
            }
            $scored[] = ['id' => (int) $r->id, 'title' => (string) $r->title, 'score' => $score,
                'scope' => trim(mb_substr(strip_tags((string) ($r->scope_and_content ?? '')), 0, 240))];
        }
        usort($scored, function ($a, $b) { return $b['score'] <=> $a['score']; });

        $out = [];
        foreach (array_slice($scored, 0, $limit) as $c) {
            $out[$c['id']] = ['id' => $c['id'], 'title' => $c['title'], 'scope' => $c['scope']];
        }

        return $this->enrichWithYears($out);
    }

    /**
     * heratio#1186 - Elasticsearch semantic/topical recall for the catalogue fallback. A multi_match
     * (best_fields + most_fields + a phrase boost) over the description's narrative fields, so the
     * generative flow recalls candidates by topic rather than just keyword. Returns rows shaped like the
     * keyword catalogue query (->id, ->title, ->scope_and_content) so the downstream theme-overlap
     * scorer is unchanged. Degrades to an empty collection (-> keyword LIKE fallback) when
     * ahg-search is absent, ES is down, or there are no hits - never a hard dependency on ES.
     */
    private function candidateObjectsEsRows(string $theme, int $limit, bool $publishedOnly, string $culture = 'en'): \Illuminate\Support\Collection
    {
        if (! class_exists(\AhgSearch\Services\ElasticsearchService::class)) {
            return collect();
        }

        $titleField = "i18n.{$culture}.title";
        $textFields = [
            "i18n.{$culture}.title^3",
            "i18n.{$culture}.scopeAndContent",
            "i18n.{$culture}.archivalHistory",
            "i18n.{$culture}.extentAndMedium",
            "i18n.{$culture}.accessConditions",
        ];

        $filter = [
            // Exclude the root collection (parent_id 1), as the keyword path does.
            ['bool' => ['must_not' => [['term' => ['parentId' => 1]]]]],
            // Require a title - the displayable handle every candidate needs.
            ['exists' => ['field' => $titleField]],
        ];
        if ($publishedOnly) {
            // Published records only (status 160) - same guarantee as applyPublishedFilter().
            $filter[] = ['term' => ['publicationStatusId' => 160]];
        }

        $body = [
            '_source' => [$titleField, "i18n.{$culture}.scopeAndContent"],
            'query' => [
                'bool' => [
                    'should' => [
                        ['multi_match' => ['query' => $theme, 'type' => 'best_fields', 'fields' => $textFields, 'operator' => 'or']],
                        ['multi_match' => ['query' => $theme, 'type' => 'most_fields', 'fields' => $textFields, 'operator' => 'or']],
                        ['multi_match' => ['query' => $theme, 'type' => 'phrase', 'fields' => $textFields, 'slop' => 2, 'boost' => 2]],
                    ],
                    'minimum_should_match' => 1,
                    'filter' => $filter,
                ],
            ],
        ];

        try {
            // search() reads ELASTICSEARCH_HOST/prefix from config and prepends "heratio_".
            $result = app(\AhgSearch\Services\ElasticsearchService::class)->search('qubitinformationobject', $body, 0, max($limit, 80));
        } catch (\Throwable $e) {
            Log::debug('[ahg-exhibition] generative ES recall failed, using keyword fallback: '.$e->getMessage());

            return collect();
        }

        $hits = $result['hits']['hits'] ?? null;
        if (! is_array($hits) || ! $hits) {
            return collect();
        }

        $rows = [];
        foreach ($hits as $h) {
            $id = (int) ($h['_id'] ?? 0);
            $i18n = $h['_source']['i18n'][$culture] ?? [];
            $title = (string) ($i18n['title'] ?? '');
            if ($id <= 0 || $title === '') {
                continue;
            }
            $rows[] = (object) [
                'id' => $id,
                'title' => $title,
                'scope_and_content' => (string) ($i18n['scopeAndContent'] ?? ''),
            ];
        }

        return collect($rows);
    }

    /** Restrict a query to published records (publication status type 158 = published 160). */
    private function applyPublishedFilter($query, string $idColumn, bool $publishedOnly): void
    {
        if (! $publishedOnly) {
            return;
        }
        $query->join('status as pub_st', function ($j) use ($idColumn) {
            $j->on('pub_st.object_id', '=', $idColumn)
                ->where('pub_st.type_id', '=', 158);
        })->where('pub_st.status_id', '=', \AhgCore\Constants\TermId::PUBLICATION_STATUS_PUBLISHED);
    }

    /**
     * Attach the earliest real calendar year to each candidate (date/era awareness). Year-only
     * AtoM dates store month/day as 00; we read the 4-digit year prefix so even those count.
     */
    private function enrichWithYears(array $candidates): array
    {
        if (! $candidates) {
            return $candidates;
        }
        $ids = array_keys($candidates);
        $years = DB::table('event')
            ->whereIn('object_id', $ids)
            ->whereNotNull('start_date')->where('start_date', '!=', '0000-00-00')
            ->selectRaw('object_id, MIN(start_date) as first_date')
            ->groupBy('object_id')->pluck('first_date', 'object_id');
        foreach ($candidates as $id => &$c) {
            $year = isset($years[$id]) ? (int) substr((string) $years[$id], 0, 4) : 0;
            $c['year'] = $year > 0 ? $year : null;
        }

        return $candidates;
    }

    /**
     * Ask the AI to curate candidates into grouped rooms with labels. Candidates are presented
     * with short 1-based NUMBERS (not their 6-digit ids) - the model copies small numbers
     * reliably, whereas it tends to half-invent long ids. Returns [] on failure.
     */
    private function curate(string $theme, array $candidates, int $maxObjects): array
    {
        $ordered = array_values(array_slice($candidates, 0, 50, true));   // position -> candidate
        $lines = [];
        $anyYear = false;
        foreach ($ordered as $i => $c) {
            $year = $c['year'] ?? null;
            if ($year) { $anyYear = true; }
            $lines[] = ($i + 1).'. '.$c['title'].($year ? ' ('.$year.')' : '');
        }
        $list = implode("\n", $lines);

        $eraHint = $anyYear
            ? 'Some objects show a year in parentheses. Use these dates: where a clear chronology or era emerges, group rooms by period and order them earliest-to-latest, and let the room titles reflect the era. '
            : '';
        $prompt = "You are a museum curator designing an exhibition on the theme: \"{$theme}\".\n"
            ."From the numbered candidate objects below, select the most relevant (up to {$maxObjects}) and arrange them into 2 to 4 themed rooms. "
            .$eraHint
            ."Refer to each object ONLY by its number from the list - never invent objects. For each chosen object write a one-line label that explains WHY it fits the theme or what it contributes - do NOT just repeat the object's title.\n"
            ."Return ONLY valid JSON, no preamble, in exactly this shape:\n"
            ."[{\"room\":\"Room title\",\"objects\":[{\"n\":1,\"label\":\"one line\"}]}]\n\n"
            ."CANDIDATES:\n".$list;

        try {
            $resp = (string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 900, 'temperature' => 0.5]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] generative curate LLM failed: '.$e->getMessage());

            return [];
        }

        $json = $this->extractJson($resp);
        $parsed = $json !== null ? json_decode($json, true) : null;
        if (! is_array($parsed)) {
            return [];
        }

        $rooms = [];
        foreach ($parsed as $room) {
            if (! is_array($room)) {
                continue;
            }
            $objs = [];
            foreach (($room['objects'] ?? []) as $o) {
                $n = (int) ($o['n'] ?? $o['id'] ?? 0);   // accept "n" (preferred) or a stray "id" = the number
                $c = ($n >= 1 && isset($ordered[$n - 1])) ? $ordered[$n - 1] : null;
                if ($c) {
                    $objs[] = ['id' => $c['id'], 'title' => $c['title'], 'label' => trim((string) ($o['label'] ?? '')), 'year' => $c['year'] ?? null];
                }
            }
            if ($objs) {
                $rooms[] = ['room' => trim((string) ($room['room'] ?? 'Room')) ?: 'Room', 'objects' => $objs];
            }
        }

        return $rooms;
    }

    /** Pull the first JSON array/object out of a model response. */
    private function extractJson(string $resp): ?string
    {
        $resp = trim($resp);
        $start = strpos($resp, '[');
        $end = strrpos($resp, ']');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($resp, $start, $end - $start + 1);
        }

        return null;
    }
}
