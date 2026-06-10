<?php

/**
 * StorytellingService - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * heratio#1202 - storytelling engine. Turns catalogue records into an engaging public
 * narrative: pick objects on a theme and let the AI gateway write a short, warm "story of the
 * collection" that weaves them together, for a general audience. Distinct from the exhibition
 * designer (#1186, which lays objects out in rooms) - this produces publishable prose.
 */
class StorytellingService
{
    /** @return array{ok:bool, theme:string, story:string, objects:array} */
    public function generate(string $theme, int $maxObjects = 10): array
    {
        $theme = trim($theme);
        $out = ['ok' => false, 'theme' => $theme, 'story' => '', 'objects' => []];
        if ($theme === '') {
            return $out;
        }

        $objects = $this->candidates($theme, $maxObjects);
        $out['objects'] = $objects;
        if (! $objects) {
            return $out;
        }

        $list = implode("\n", array_map(
            fn ($o) => '- '.$o['title'].($o['scope'] !== '' ? ': '.$o['scope'] : ''),
            $objects
        ));
        $prompt = "Write an engaging, warm public 'story of the collection' for a general audience, about 180 to 220 words, on the theme: \"{$theme}\". "
            ."Weave in these real objects from the collection, referring to them naturally by name. Be vivid but factual - do NOT invent specific dates, people or events that are not implied by the material. "
            ."No headings, no markdown, no preamble - just the story prose.\n\nOBJECTS:\n".$list;

        try {
            $story = trim((string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 520, 'temperature' => 0.7]));
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] storytelling LLM failed: '.$e->getMessage());

            return $out;
        }

        $out['story'] = $story;
        $out['ok'] = $story !== '';

        return $out;
    }

    /** On-theme objects (prefer exhibition room objects for context; else the catalogue). */
    private function candidates(string $theme, int $limit): array
    {
        $tokens = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($theme)), fn ($t) => mb_strlen($t) >= 3));
        if (! $tokens) {
            return [];
        }

        $rows = DB::table('ahg_exhibition_placement as ep')
            ->join('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'ep.information_object_id')->where('i.culture', '=', 'en'); })
            ->whereNotNull('ep.information_object_id')->where('i.title', '!=', '')
            ->select('ep.information_object_id as id', 'i.title', 'i.scope_and_content')->distinct()->get();

        if ($rows->isEmpty()) {
            $rows = DB::table('information_object_i18n as i')
                ->join('information_object as io', 'io.id', '=', 'i.id')
                ->where('i.culture', 'en')->where('io.parent_id', '!=', 1)
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) { $q->orWhere('i.title', 'like', '%'.$t.'%')->orWhere('i.scope_and_content', 'like', '%'.$t.'%'); }
                })
                ->where('i.title', '!=', '')->select('io.id', 'i.title', 'i.scope_and_content')->limit(80)->get();
        }

        $scored = [];
        foreach ($rows as $r) {
            $title = mb_strtolower((string) $r->title);
            $scope = mb_strtolower(strip_tags((string) ($r->scope_and_content ?? '')));
            $s = 0;
            foreach ($tokens as $t) {
                if (mb_strpos($title, $t) !== false) { $s += 2; }
                if (mb_strpos($scope, $t) !== false) { $s += 1; }
            }
            if ($s > 0) {   // story stays on-theme: only objects that actually match
                $scored[] = ['id' => (int) $r->id, 'title' => (string) $r->title, 'score' => $s,
                    'scope' => trim(mb_substr(strip_tags((string) ($r->scope_and_content ?? '')), 0, 160))];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }
}
