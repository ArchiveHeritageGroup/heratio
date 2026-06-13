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

    /**
     * heratio#1198 - save a cited answer into a research workspace. Sources are stored as
     * [{id,title,slug}] so the saved answer keeps its citations even if the live catalogue
     * changes. Returns the new row id.
     *
     * @return array{ok:bool, id:int, error?:string}
     */
    public function saveAnswer(int $workspaceId, ?int $researcherId, string $question, string $answer, array $sources, ?int $projectId = null): array
    {
        $question = trim($question);
        $answer = trim($answer);
        if ($workspaceId <= 0 || $question === '' || $answer === '') {
            return ['ok' => false, 'id' => 0, 'error' => 'workspace, question and answer are required'];
        }

        $clean = [];
        foreach ($sources as $s) {
            $id = (int) (is_array($s) ? ($s['id'] ?? 0) : 0);
            $title = trim((string) (is_array($s) ? ($s['title'] ?? '') : ''));
            if ($title === '') {
                continue;
            }
            $clean[] = ['id' => $id, 'title' => mb_substr($title, 0, 300), 'slug' => is_array($s) ? ($s['slug'] ?? null) : null];
        }

        // #1252 AI-use disclosure: a saved copilot answer is pure gateway output,
        // so every row carries the AI marker. project_id is set when the saving
        // UI supplies it, letting the disclosure aggregator attribute the answer
        // to a project; without it the marker still records that AI was used.
        $row = [
            'workspace_id' => $workspaceId,
            'researcher_id' => $researcherId,
            'question' => mb_substr($question, 0, 500),
            'answer' => $answer,
            'sources_json' => json_encode($clean),
            'created_at' => now(),
        ];
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('research_copilot_answer', 'ai_at')) {
                $row['project_id'] = ($projectId !== null && $projectId > 0) ? $projectId : null;
                $row['ai_model']   = $this->resolveAiModel();
                $row['ai_at']      = now();
            }
        } catch (\Throwable $e) {
            // columns not present yet - save without the marker.
        }

        $id = (int) DB::table('research_copilot_answer')->insertGetId($row);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * #1252 - best-effort model name from the LlmService default config; falls
     * back to the gateway label. Config read only; never contacts a node.
     */
    protected function resolveAiModel(): string
    {
        try {
            if (class_exists(\AhgAiServices\Services\LlmService::class)) {
                $cfg = (new \AhgAiServices\Services\LlmService())->getDefaultConfig();
                $model = trim((string) ($cfg->model ?? ''));
                if ($model !== '') {
                    return mb_substr($model, 0, 120);
                }
            }
        } catch (\Throwable $e) {
            // fall through to label.
        }
        return 'AHG AI gateway';
    }

    /** Saved copilot answers for a workspace (newest first), with decoded sources. */
    public function listAnswers(int $workspaceId, int $limit = 50): array
    {
        return DB::table('research_copilot_answer')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('id')->limit($limit)->get()
            ->map(function ($r) {
                $src = json_decode((string) ($r->sources_json ?? '[]'), true);
                $r->sources = is_array($src) ? $src : [];

                return $r;
            })->all();
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
