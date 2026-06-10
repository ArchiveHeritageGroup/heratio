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
 * heratio#1186 - generative exhibitions. Given a theme, find candidate objects in the
 * catalogue and let the AI gateway curate them into a draft exhibition: a few rooms, each
 * with a title and a short selection of objects with a one-line label/why. Nothing is built
 * or placed here - the curator reviews the draft (placement is a later slice).
 */
class GenerativeExhibitionService
{
    /** @return array{ok:bool, theme:string, rooms:array, candidate_count:int} */
    public function suggest(string $theme, int $maxObjects = 12): array
    {
        $theme = trim($theme);
        $out = ['ok' => false, 'theme' => $theme, 'rooms' => [], 'candidate_count' => 0];
        if ($theme === '') {
            return $out;
        }

        $candidates = $this->candidateObjects($theme, 60);
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
     * Candidate pool, theme-ranked. Prefers objects already placed in exhibition rooms
     * (real, curated, contextual), ranked by how well they match the theme; falls back to a
     * catalogue keyword search when no exhibition objects exist (fresh installs).
     */
    private function candidateObjects(string $theme, int $limit): array
    {
        $tokens = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($theme)), function ($t) {
            return mb_strlen($t) >= 3;
        }));

        // 1) Objects already placed in exhibition rooms.
        $rows = DB::table('ahg_exhibition_placement as ep')
            ->join('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'ep.information_object_id')->where('i.culture', '=', 'en'); })
            ->whereNotNull('ep.information_object_id')
            ->whereNotNull('i.title')->where('i.title', '!=', '')
            ->select('ep.information_object_id as id', 'i.title', 'i.scope_and_content')
            ->distinct()->get();

        // 2) Fallback to the catalogue if no room objects exist yet.
        if ($rows->isEmpty() && $tokens) {
            $rows = DB::table('information_object_i18n as i')
                ->join('information_object as io', 'io.id', '=', 'i.id')
                ->where('i.culture', 'en')->where('io.parent_id', '!=', 1)
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) {
                        $q->orWhere('i.title', 'like', '%'.$t.'%')->orWhere('i.scope_and_content', 'like', '%'.$t.'%');
                    }
                })
                ->whereNotNull('i.title')->where('i.title', '!=', '')
                ->select('io.id', 'i.title', 'i.scope_and_content')
                ->limit(80)->get();
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

        return $out;
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
        foreach ($ordered as $i => $c) {
            $lines[] = ($i + 1).'. '.$c['title'];
        }
        $list = implode("\n", $lines);

        $prompt = "You are a museum curator designing an exhibition on the theme: \"{$theme}\".\n"
            ."From the numbered candidate objects below, select the most relevant (up to {$maxObjects}) and arrange them into 2 to 4 themed rooms. "
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
                    $objs[] = ['id' => $c['id'], 'title' => $c['title'], 'label' => trim((string) ($o['label'] ?? ''))];
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
