<?php

/**
 * ResearchCopilotService - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * heratio#1198 - researcher copilot. A research question in, an annotated source set + a
 * grounded synthesis out: find relevant catalogue records, then let the AI gateway write a
 * concise answer that cites those records by number. Grounded only in the sources (no
 * invention). Saving the result into a research workspace is a later slice.
 */
class ResearchCopilotService
{
    /** @return array{ok:bool, question:string, answer:string, sources:array} */
    public function ask(string $question, int $maxSources = 8): array
    {
        $question = trim($question);
        $out = ['ok' => false, 'question' => $question, 'answer' => '', 'sources' => []];
        if ($question === '') {
            return $out;
        }

        $sources = $this->findSources($question, $maxSources);
        $out['sources'] = $sources;
        if (! $sources) {
            return $out;
        }

        $list = [];
        foreach ($sources as $i => $s) {
            $list[] = ($i + 1).'. '.$s['title'].($s['scope'] !== '' ? ' - '.$s['scope'] : '');
        }
        $prompt = "You are a research assistant working in an archive. Answer the researcher's question using ONLY the numbered sources below. "
            ."Cite the sources you use inline as [1], [2], etc. If the sources do not contain enough to answer, say so plainly and point to what is there. "
            ."Be concise, factual and neutral - never invent facts, dates, names or events that are not in the sources.\n\n"
            ."QUESTION: {$question}\n\nSOURCES:\n".implode("\n", $list);

        try {
            $answer = trim((string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 600, 'temperature' => 0.3]));
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] copilot LLM failed: '.$e->getMessage());

            return $out;
        }

        $out['answer'] = $answer;
        $out['ok'] = $answer !== '';

        return $out;
    }

    /** Relevant catalogue records for a question (keyword-scored over title + scope). */
    private function findSources(string $question, int $limit): array
    {
        $tokens = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)),
            fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, ['the', 'and', 'what', 'when', 'who', 'where', 'why', 'how', 'was', 'were', 'are', 'for', 'with', 'about', 'did', 'does'], true)
        ));
        if (! $tokens) {
            return [];
        }

        $rows = DB::table('information_object_i18n as i')
            ->join('information_object as io', 'io.id', '=', 'i.id')
            ->leftJoin('slug as sl', function ($j) { $j->on('sl.object_id', '=', 'io.id'); })
            ->where('i.culture', 'en')->where('io.parent_id', '!=', 1)
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) { $q->orWhere('i.title', 'like', '%'.$t.'%')->orWhere('i.scope_and_content', 'like', '%'.$t.'%'); }
            })
            ->where('i.title', '!=', '')
            ->select('io.id', 'i.title', 'i.scope_and_content', 'sl.slug')
            ->limit(120)->get();

        $scored = [];
        foreach ($rows as $r) {
            $title = mb_strtolower((string) $r->title);
            $scope = mb_strtolower(strip_tags((string) ($r->scope_and_content ?? '')));
            $s = 0;
            foreach ($tokens as $t) {
                if (mb_strpos($title, $t) !== false) { $s += 2; }
                if (mb_strpos($scope, $t) !== false) { $s += 1; }
            }
            if ($s > 0) {
                $scored[] = ['id' => (int) $r->id, 'title' => (string) $r->title, 'slug' => $r->slug,
                    'score' => $s, 'scope' => trim(mb_substr(strip_tags((string) ($r->scope_and_content ?? '')), 0, 200))];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }
}
