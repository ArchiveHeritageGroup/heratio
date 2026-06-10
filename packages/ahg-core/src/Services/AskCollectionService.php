<?php

/**
 * AskCollectionService - Heratio ahg-core
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
 * heratio#1208 - "Ask the collection" (public engagement). The public-facing sibling of the
 * researcher copilot (AhgResearch\Services\ResearchCopilotService): a member of the public asks
 * a plain-language question and gets a concise answer grounded ONLY in matching PUBLISHED
 * catalogue records, cited by number with links. Because this surface is anonymous-public, the
 * source set is restricted to records whose publication status is Published (status type_id 158,
 * status_id 160) - never drafts or unpublished material. The LLM is instructed to answer using
 * only those records, cite [n], and say plainly when the collection does not cover the question
 * (no invention). First slice of the North Star vision; language revival is out of scope here.
 */
class AskCollectionService
{
    /** @return array{ok:bool, question:string, answer:string, sources:array, covered:bool} */
    public function ask(string $question, int $maxSources = 8): array
    {
        $question = trim($question);
        $out = ['ok' => false, 'question' => $question, 'answer' => '', 'sources' => [], 'covered' => false];
        if ($question === '') {
            return $out;
        }

        $sources = $this->findPublishedSources($question, $maxSources);
        $out['sources'] = $sources;
        if (! $sources) {
            // No published record matched - tell the visitor plainly; never invent.
            $out['ok'] = true;
            $out['covered'] = false;
            $out['answer'] = "The published collection does not appear to cover this. No catalogue records matched your question, so there is nothing here to answer it from.";

            return $out;
        }

        $list = [];
        foreach ($sources as $i => $s) {
            $list[] = ($i + 1).'. '.$s['title'].($s['scope'] !== '' ? ' - '.$s['scope'] : '');
        }
        $prompt = "You are a friendly guide helping a member of the public explore an archive's PUBLISHED collection. "
            ."Answer the question using ONLY the numbered catalogue records below. "
            ."Cite the records you use inline as [1], [2], etc. "
            ."If the records do not contain enough to answer, say so plainly in one sentence and point to what is there - never invent facts, dates, names, places or events that are not in the records. "
            ."Write in plain, welcoming language for a general audience. Be concise (a short paragraph).\n\n"
            ."QUESTION: {$question}\n\nPUBLISHED RECORDS:\n".implode("\n", $list);

        try {
            $answer = trim((string) app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 600, 'temperature' => 0.3]));
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] ask-collection LLM failed: '.$e->getMessage());

            return $out;
        }

        $out['answer'] = $answer;
        $out['ok'] = $answer !== '';
        $out['covered'] = $answer !== '';

        return $out;
    }

    /**
     * Relevant PUBLISHED catalogue records for a question (keyword-scored over title + scope).
     * Mirrors the researcher copilot's keyword scoring, but hard-restricts the candidate set to
     * Published records (status type_id 158, status_id 160) because this is an anonymous-public
     * surface - drafts and unpublished material must never leak into a public answer.
     */
    private function findPublishedSources(string $question, int $limit): array
    {
        $tokens = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)),
            fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, [
                'the', 'and', 'what', 'when', 'who', 'where', 'why', 'how', 'was', 'were',
                'are', 'for', 'with', 'about', 'did', 'does', 'have', 'has', 'this', 'that',
                'tell', 'show', 'find', 'any', 'all', 'can', 'you',
            ], true)
        ));
        if (! $tokens) {
            return [];
        }

        $rows = DB::table('information_object_i18n as i')
            ->join('information_object as io', 'io.id', '=', 'i.id')
            ->leftJoin('slug as sl', function ($j) { $j->on('sl.object_id', '=', 'io.id'); })
            ->where('i.culture', 'en')->where('io.parent_id', '!=', 1)
            // Published-only: same gate the public GLAM browse uses for guests.
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status as pub_st')
                    ->whereRaw('pub_st.object_id = io.id')
                    ->where('pub_st.type_id', '=', 158)
                    ->where('pub_st.status_id', '=', 160);
            })
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) {
                    $q->orWhere('i.title', 'like', '%'.$t.'%')->orWhere('i.scope_and_content', 'like', '%'.$t.'%');
                }
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
                $scored[] = [
                    'id' => (int) $r->id,
                    'title' => (string) $r->title,
                    'slug' => $r->slug,
                    'score' => $s,
                    'scope' => trim(mb_substr(strip_tags((string) ($r->scope_and_content ?? '')), 0, 200)),
                ];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }
}
