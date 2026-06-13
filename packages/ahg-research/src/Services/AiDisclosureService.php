<?php

/**
 * AiDisclosureService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AiDisclosureService - Research OS Part IV "AI Containment" (heratio#1242).
 *
 * Builds a one-click AI-use disclosure for any project output. It AGGREGATES the
 * project's AI usage from already-landed slices, READ-ONLY, and never alters those
 * tables. The only write surface is the slice's own research_ai_disclosure_log,
 * which captures AI assistance the system cannot detect on its own.
 *
 * Detected (read-only) sources, each Schema::hasTable-guarded and try/catch
 * wrapped so a missing table contributes nothing rather than 500ing:
 *
 *   - research_review_run      (#1230 Review Studio)  persona/model/created_at
 *   - research_source_triage   (#1227 Source Triage)  ai_preview/ai_preview_at
 *   - research_contradiction   (#1236 Contradiction Engine) rows where source='ai'
 *   - research_studio_artefact (#1240 Research Studio) every non-errored row is a
 *                              gateway generation; model/created_at per row
 *
 * #1252 added a per-row AI marker (ai_model + ai_at) to the remaining AI-capable
 * slices, populated ONLY on the write where the AHG gateway actually produced the
 * output - never on a purely manual write - so they can be detected without
 * falsely disclosing hand-authored work. Each now has its own detector keyed to
 * the project (rows WHERE ai_at IS NOT NULL):
 *   - Writing Studio   (research_writing_version via research_writing_doc) - AI section draft
 *   - Question Builder (#1226, research_question_brief)  - AI brief diagnosis
 *   - Analysis Bridge  (#1234, research_analysis_result) - AI result caption
 *   - Grant Engine     (research_grant_draft)            - AI section draft
 *   - Argument Builder (research_argument)               - AI assistance (no AI write path
 *                       ships today, so the detector stays empty until one is wired)
 *   - Publication Studio (research_submission)           - AI venue-fit note (stamped only
 *                       when a specific submission is in context)
 *   - Research Copilot (research_copilot_answer, +project_id) - AI source-cited answer;
 *                       attributed per project via the new project_id column
 * Whatever still cannot be detected, researchers record through the manual log.
 *
 * The disclosure statement is ASSEMBLED from these records - no AI call is needed
 * to produce it. (An optional narrative summary may be requested separately; if so
 * it routes only through AhgAiServices\Services\LlmService - the gateway
 * abstraction - and is labelled as AI-generated. No node port is contacted here.)
 */
class AiDisclosureService
{
    /** Name of the AHG AI gateway, used in disclosure copy. */
    public const GATEWAY_LABEL = 'AHG AI gateway';

    /**
     * Resolve the linked actor id for a Research-OS project id.
     *
     * The slice tables (review run, triage, contradiction) key on `project_id`,
     * which is the research_project.id primary key. We pass it straight through.
     */
    private function projectKey(int $projectId): int
    {
        return $projectId;
    }

    /**
     * Aggregate every detected + logged AI usage line for a project.
     *
     * @return array<int,array{
     *   slice:string, purpose:string, tool:string, model:?string,
     *   when:?string, source:string
     * }>
     */
    public function gather(int $projectId): array
    {
        $pid   = $this->projectKey($projectId);
        $lines = [];

        foreach ([
            'detectReviewRuns',
            'detectSourceTriage',
            'detectContradictions',
            'detectStudioArtefacts',
            'detectWritingDrafts',
            'detectQuestionBriefs',
            'detectAnalysisResults',
            'detectGrantDrafts',
            'detectArguments',
            'detectSubmissions',
            'detectCopilotAnswers',
            'detectManualLog',
        ] as $detector) {
            try {
                foreach ($this->{$detector}($pid) as $row) {
                    $lines[] = $row;
                }
            } catch (\Throwable $e) {
                Log::warning('[ahg-research] ai-disclosure '.$detector.' failed: '.$e->getMessage());
            }
        }

        // Newest first; rows without a timestamp sort last.
        usort($lines, function ($a, $b) {
            return strcmp((string) ($b['when'] ?? ''), (string) ($a['when'] ?? ''));
        });

        return $lines;
    }

    /**
     * Review Studio AI runs (#1230). Each run is a gateway persona pass over the
     * project; model is recorded per run.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectReviewRuns(int $projectId): array
    {
        if (! Schema::hasTable('research_review_run')) {
            return [];
        }

        $rows = DB::table('research_review_run')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['persona', 'model', 'created_at']);

        $out = [];
        foreach ($rows as $r) {
            $persona = trim((string) ($r->persona ?? ''));
            $out[] = [
                'slice'   => 'Review Studio',
                'purpose' => 'AI peer-review pass'.($persona !== '' ? ' (persona: '.$persona.')' : ''),
                'tool'    => self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->model ?? null),
                'when'    => $this->nullableStr($r->created_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Source Triage AI previews (#1227). Only rows that actually have an
     * ai_preview were produced with AI.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectSourceTriage(int $projectId): array
    {
        if (! Schema::hasTable('research_source_triage')) {
            return [];
        }

        $rows = DB::table('research_source_triage')
            ->where('project_id', $projectId)
            ->whereNotNull('ai_preview')
            ->orderByDesc('ai_preview_at')
            ->limit(1000)
            ->get(['source_type', 'source_id', 'ai_preview_at']);

        $out = [];
        foreach ($rows as $r) {
            $ref = trim((string) ($r->source_type ?? '')).' #'.(int) ($r->source_id ?? 0);
            $out[] = [
                'slice'   => 'Source Triage',
                'purpose' => 'AI relevance preview of a candidate source ('.$ref.')',
                'tool'    => self::GATEWAY_LABEL,
                'model'   => null,
                'when'    => $this->nullableStr($r->ai_preview_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Contradiction Engine AI findings (#1236). Only rows flagged source='ai'.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectContradictions(int $projectId): array
    {
        if (! Schema::hasTable('research_contradiction')) {
            return [];
        }

        $rows = DB::table('research_contradiction')
            ->where('project_id', $projectId)
            ->where('source', 'ai')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get(['kind', 'created_at']);

        $out = [];
        foreach ($rows as $r) {
            $kind = trim((string) ($r->kind ?? ''));
            $out[] = [
                'slice'   => 'Contradiction Engine',
                'purpose' => 'AI contradiction-detection pass over the claim ledger'
                    .($kind !== '' ? ' ('.str_replace('_', ' ', $kind).')' : ''),
                'tool'    => self::GATEWAY_LABEL,
                'model'   => null,
                'when'    => $this->nullableStr($r->created_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Research Studio AI artefacts (#1240). Every artefact is generated by the
     * gateway from the project's evidence set, so every non-errored row is AI
     * output. The per-row `model` column records the model when the gateway
     * returned one; `created_at` is the generation time.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectStudioArtefacts(int $projectId): array
    {
        if (! Schema::hasTable('research_studio_artefact')) {
            return [];
        }

        $rows = DB::table('research_studio_artefact')
            ->where('project_id', $projectId)
            ->where('status', '!=', 'error')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get(['output_type', 'title', 'model', 'created_at']);

        $out = [];
        foreach ($rows as $r) {
            $type  = trim((string) ($r->output_type ?? ''));
            $title = trim((string) ($r->title ?? ''));
            $what  = $type !== '' ? str_replace('_', ' ', $type) : 'artefact';
            $out[] = [
                'slice'   => 'Research Studio',
                'purpose' => 'AI-generated '.$what.' synthesised from the project evidence set'
                    .($title !== '' ? ' ("'.$title.'")' : ''),
                'tool'    => self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->model ?? null),
                'when'    => $this->nullableStr($r->created_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Writing Studio AI drafts (#1252). Each successful gateway draft is recorded
     * as an AI-stamped version snapshot in research_writing_version; the per-row
     * ai_at marks it AI-produced. Tied to the project through research_writing_doc
     * (doc_id -> project_id), mirroring how the Writing Studio resolves a project.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectWritingDrafts(int $projectId): array
    {
        if (! Schema::hasTable('research_writing_version')
            || ! Schema::hasTable('research_writing_doc')
            || ! Schema::hasColumn('research_writing_version', 'ai_at')) {
            return [];
        }

        $rows = DB::table('research_writing_version as v')
            ->join('research_writing_doc as d', 'd.id', '=', 'v.doc_id')
            ->where('d.project_id', $projectId)
            ->whereNotNull('v.ai_at')
            ->orderByDesc('v.ai_at')
            ->limit(1000)
            ->get(['d.title as doc_title', 'v.note', 'v.ai_model', 'v.ai_at']);

        $out = [];
        foreach ($rows as $r) {
            $docTitle = trim((string) ($r->doc_title ?? ''));
            $out[] = [
                'slice'   => 'Writing Studio',
                'purpose' => 'AI-drafted prose for a document section'
                    .($docTitle !== '' ? ' (document "'.$docTitle.'")' : ''),
                'tool'    => self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->ai_model ?? null),
                'when'    => $this->nullableStr($r->ai_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Question Builder AI diagnoses (#1252). research_question_brief.ai_at is set
     * when the gateway produced a diagnosis for the project's brief. Keys directly
     * on project_id, like the other brief-level detectors.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectQuestionBriefs(int $projectId): array
    {
        if (! Schema::hasTable('research_question_brief')
            || ! Schema::hasColumn('research_question_brief', 'ai_at')) {
            return [];
        }

        $rows = DB::table('research_question_brief')
            ->where('project_id', $projectId)
            ->whereNotNull('ai_at')
            ->orderByDesc('ai_at')
            ->limit(500)
            ->get(['ai_model', 'ai_at']);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'slice'   => 'Question Builder',
                'purpose' => 'AI-assisted diagnosis of the research design brief',
                'tool'    => self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->ai_model ?? null),
                'when'    => $this->nullableStr($r->ai_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Analysis Bridge AI captions (#1252). research_analysis_result.ai_at is set
     * when the gateway suggested a caption for a registered result. Keys on
     * project_id.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectAnalysisResults(int $projectId): array
    {
        if (! Schema::hasTable('research_analysis_result')
            || ! Schema::hasColumn('research_analysis_result', 'ai_at')) {
            return [];
        }

        $rows = DB::table('research_analysis_result')
            ->where('project_id', $projectId)
            ->whereNotNull('ai_at')
            ->orderByDesc('ai_at')
            ->limit(1000)
            ->get(['title', 'ai_model', 'ai_at']);

        $out = [];
        foreach ($rows as $r) {
            $title = trim((string) ($r->title ?? ''));
            $out[] = [
                'slice'   => 'Analysis Bridge',
                'purpose' => 'AI-suggested plain-language caption for an analysis result'
                    .($title !== '' ? ' ("'.$title.'")' : ''),
                'tool'    => self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->ai_model ?? null),
                'when'    => $this->nullableStr($r->ai_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Grant Engine AI section drafts (#1252). research_grant_draft.ai_at is set
     * when the gateway drafted a section for the draft. Keys on project_id.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectGrantDrafts(int $projectId): array
    {
        if (! Schema::hasTable('research_grant_draft')
            || ! Schema::hasColumn('research_grant_draft', 'ai_at')) {
            return [];
        }

        $rows = DB::table('research_grant_draft')
            ->where('project_id', $projectId)
            ->whereNotNull('ai_at')
            ->orderByDesc('ai_at')
            ->limit(1000)
            ->get(['title', 'ai_model', 'ai_at']);

        $out = [];
        foreach ($rows as $r) {
            $title = trim((string) ($r->title ?? ''));
            $out[] = [
                'slice'   => 'Grant Engine',
                'purpose' => 'AI-drafted grant-application section'
                    .($title !== '' ? ' (draft "'.$title.'")' : ''),
                'tool'    => self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->ai_model ?? null),
                'when'    => $this->nullableStr($r->ai_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Argument Builder AI assistance (#1252). research_argument.ai_at is set when
     * AI assistance was applied to the argument canvas. Keys on project_id. NOTE:
     * the Argument Builder ships no AI write path today, so this detector returns
     * nothing until an AI critique is wired and populates ai_at on that write.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectArguments(int $projectId): array
    {
        if (! Schema::hasTable('research_argument')
            || ! Schema::hasColumn('research_argument', 'ai_at')) {
            return [];
        }

        $rows = DB::table('research_argument')
            ->where('project_id', $projectId)
            ->whereNotNull('ai_at')
            ->orderByDesc('ai_at')
            ->limit(500)
            ->get(['title', 'ai_model', 'ai_at']);

        $out = [];
        foreach ($rows as $r) {
            $title = trim((string) ($r->title ?? ''));
            $out[] = [
                'slice'   => 'Argument Builder',
                'purpose' => 'AI assistance applied to the argument canvas'
                    .($title !== '' ? ' ("'.$title.'")' : ''),
                'tool'    => self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->ai_model ?? null),
                'when'    => $this->nullableStr($r->ai_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Publication Studio AI venue-fit notes (#1252). research_submission.ai_at is
     * set when the gateway produced a venue-fit suggestion against a submission.
     * Keys on project_id.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectSubmissions(int $projectId): array
    {
        if (! Schema::hasTable('research_submission')
            || ! Schema::hasColumn('research_submission', 'ai_at')) {
            return [];
        }

        $rows = DB::table('research_submission')
            ->where('project_id', $projectId)
            ->whereNotNull('ai_at')
            ->orderByDesc('ai_at')
            ->limit(1000)
            ->get(['venue_name', 'ai_model', 'ai_at']);

        $out = [];
        foreach ($rows as $r) {
            $venue = trim((string) ($r->venue_name ?? ''));
            $out[] = [
                'slice'   => 'Publication Studio',
                'purpose' => 'AI venue-fit assessment for a submission'
                    .($venue !== '' ? ' (venue: '.$venue.')' : ''),
                'tool'    => self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->ai_model ?? null),
                'when'    => $this->nullableStr($r->ai_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Research Copilot answers (#1252). Every saved research_copilot_answer is
     * pure gateway output; ai_at marks the generation. The slice keys on
     * workspace_id, so attribution requires the new project_id column - rows with
     * no project_id cannot be tied to a project and are intentionally excluded.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectCopilotAnswers(int $projectId): array
    {
        if (! Schema::hasTable('research_copilot_answer')
            || ! Schema::hasColumn('research_copilot_answer', 'ai_at')
            || ! Schema::hasColumn('research_copilot_answer', 'project_id')) {
            return [];
        }

        $rows = DB::table('research_copilot_answer')
            ->where('project_id', $projectId)
            ->whereNotNull('ai_at')
            ->orderByDesc('ai_at')
            ->limit(1000)
            ->get(['question', 'ai_model', 'ai_at']);

        $out = [];
        foreach ($rows as $r) {
            $q = trim((string) ($r->question ?? ''));
            $out[] = [
                'slice'   => 'Research Copilot',
                'purpose' => 'AI-generated, source-cited answer to a research question'
                    .($q !== '' ? ' ("'.mb_substr($q, 0, 120).'")' : ''),
                'tool'    => self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->ai_model ?? null),
                'when'    => $this->nullableStr($r->ai_at ?? null),
                'source'  => 'detected',
            ];
        }
        return $out;
    }

    /**
     * Manual log entries (#1242, this slice's own table).
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectManualLog(int $projectId): array
    {
        if (! Schema::hasTable('research_ai_disclosure_log')) {
            return [];
        }

        $rows = DB::table('research_ai_disclosure_log')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get(['tool', 'model', 'purpose', 'output_ref', 'created_at']);

        $out = [];
        foreach ($rows as $r) {
            $purpose = trim((string) ($r->purpose ?? ''));
            $ref     = trim((string) ($r->output_ref ?? ''));
            if ($ref !== '') {
                $purpose = ($purpose !== '' ? $purpose.' ' : '').'[output: '.$ref.']';
            }
            $out[] = [
                'slice'   => 'Manual log',
                'purpose' => $purpose !== '' ? $purpose : 'AI assistance (recorded by the researcher)',
                'tool'    => trim((string) ($r->tool ?? '')) !== '' ? (string) $r->tool : self::GATEWAY_LABEL,
                'model'   => $this->nullableStr($r->model ?? null),
                'when'    => $this->nullableStr($r->created_at ?? null),
                'source'  => 'logged',
            ];
        }
        return $out;
    }

    /**
     * Add a manual disclosure-log entry. This is the ONLY write the slice makes.
     *
     * @param array<string,mixed> $data
     * @return bool
     */
    public function addLogEntry(int $projectId, array $data, ?int $userId): bool
    {
        if (! Schema::hasTable('research_ai_disclosure_log')) {
            return false;
        }

        try {
            DB::table('research_ai_disclosure_log')->insert([
                'project_id' => $projectId,
                'tool'       => $this->clip((string) ($data['tool'] ?? ''), 160),
                'model'      => $this->clipNullable($data['model'] ?? null, 160),
                'purpose'    => $data['purpose'] !== null ? (string) $data['purpose'] : null,
                'output_ref' => $this->clipNullable($data['output_ref'] ?? null, 500),
                'logged_by'  => $userId,
                'created_at' => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] ai-disclosure log insert failed: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Delete one manual log entry for a project (scoped, never cross-project).
     */
    public function deleteLogEntry(int $projectId, int $entryId): bool
    {
        if (! Schema::hasTable('research_ai_disclosure_log')) {
            return false;
        }
        try {
            return DB::table('research_ai_disclosure_log')
                ->where('project_id', $projectId)
                ->where('id', $entryId)
                ->delete() > 0;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] ai-disclosure log delete failed: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Distinct models seen across detected + logged lines, for the statement.
     *
     * @param array<int,array<string,mixed>> $lines
     * @return array<int,string>
     */
    public function modelsUsed(array $lines): array
    {
        $models = [];
        foreach ($lines as $l) {
            $m = trim((string) ($l['model'] ?? ''));
            if ($m !== '') {
                $models[$m] = true;
            }
        }
        return array_keys($models);
    }

    /**
     * Distinct slices/tools seen, for the statement.
     *
     * @param array<int,array<string,mixed>> $lines
     * @return array<int,string>
     */
    public function slicesUsed(array $lines): array
    {
        $slices = [];
        foreach ($lines as $l) {
            $s = trim((string) ($l['slice'] ?? ''));
            if ($s !== '') {
                $slices[$s] = true;
            }
        }
        return array_keys($slices);
    }

    /**
     * Assemble the plain-text AI Disclosure Statement from the records (no AI
     * call). Suitable for pasting into a journal's AI-use statement. Asserts the
     * researcher remained the author, that AI was assistive, and that every AI
     * call routed through the AHG gateway.
     *
     * @param object $project The research_project row.
     * @param array<int,array<string,mixed>> $lines Output of gather().
     */
    public function buildStatement(object $project, array $lines): string
    {
        $title  = trim((string) ($project->title ?? ''));
        $models = $this->modelsUsed($lines);
        $slices = $this->slicesUsed($lines);

        if (empty($lines)) {
            $head = $title !== ''
                ? 'AI-use disclosure for "'.$title.'":'
                : 'AI-use disclosure:';

            return $head."\n\n"
                ."No generative-AI assistance was recorded for this project. The "
                ."author conducted the research and prepared the output without "
                ."AI assistance recorded in the system. Any incidental tool use "
                ."did not contribute to the substance, argument, or conclusions of "
                ."the work, which remain the author's own.";
        }

        $head = $title !== ''
            ? 'AI-use disclosure for "'.$title.'":'
            : 'AI-use disclosure:';

        $body = [];
        $body[] = $head;
        $body[] = '';
        $body[] = 'During the preparation of this work the author used AI tools in '
            .'an assistive capacity. All AI calls were routed through the '
            .self::GATEWAY_LABEL.', which logs and meters each request; no model '
            .'was contacted directly.';
        $body[] = '';

        // Where it was used.
        if (! empty($slices)) {
            $body[] = 'AI assistance was applied in the following areas: '
                .$this->joinList($slices).'.';
        }

        // Which models.
        if (! empty($models)) {
            $body[] = 'Model(s) recorded: '.$this->joinList($models).'.';
        } else {
            $body[] = 'Specific model identifiers were not recorded for every '
                .'call; routing and model selection were handled by the gateway.';
        }

        $body[] = '';

        // Detail lines (capped so the statement stays paste-able).
        $detailed = array_slice($lines, 0, 25);
        if (! empty($detailed)) {
            $body[] = 'Recorded AI interactions:';
            foreach ($detailed as $l) {
                $when    = trim((string) ($l['when'] ?? ''));
                $model   = trim((string) ($l['model'] ?? ''));
                $segment = '- '.((string) ($l['slice'] ?? 'AI')).': '
                    .((string) ($l['purpose'] ?? 'AI assistance'))
                    .' (via '.((string) ($l['tool'] ?? self::GATEWAY_LABEL))
                    .($model !== '' ? ', model '.$model : '')
                    .($when !== '' ? ', '.$this->dateOnly($when) : '')
                    .')';
                $body[] = $segment;
            }
            if (count($lines) > count($detailed)) {
                $body[] = '- ... and '.(count($lines) - count($detailed))
                    .' further recorded interaction(s).';
            }
            $body[] = '';
        }

        $body[] = 'The author reviewed, verified, and takes full responsibility '
            .'for all AI-assisted content. AI was used to support - not to '
            .'replace - the author\'s scholarship; the analysis, interpretation, '
            .'and conclusions are the author\'s own.';

        return implode("\n", $body);
    }

    /**
     * Optional AI-written narrative summary of the disclosure. Gateway-only,
     * clearly labelled. Returns null when AI services are unavailable or the call
     * fails; the assembled statement remains the canonical artefact.
     *
     * @param array<int,array<string,mixed>> $lines
     */
    public function summariseViaGateway(object $project, array $lines): ?string
    {
        if (empty($lines)) {
            return null;
        }
        if (! class_exists(\AhgAiServices\Services\LlmService::class)) {
            return null;
        }

        try {
            $facts = [];
            foreach (array_slice($lines, 0, 25) as $l) {
                $facts[] = '- '.((string) ($l['slice'] ?? 'AI')).': '
                    .((string) ($l['purpose'] ?? 'AI assistance'));
            }

            $prompt = "Write a concise, formal AI-use disclosure paragraph for an "
                ."academic journal, in the first person ('the author'). Use ONLY "
                ."the recorded interactions below; never invent tools, models, or "
                ."uses. State plainly that all AI calls routed through the "
                .self::GATEWAY_LABEL.", that AI was assistive only, and that the "
                ."author remains responsible for the work. Return only the "
                ."paragraph.\n\nRECORDED INTERACTIONS:\n".implode("\n", $facts);

            $raw = (string) app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 400, 'temperature' => 0.2]);

            $raw = trim($raw);
            return $raw !== '' ? $raw : null;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] ai-disclosure summarise failed: '.$e->getMessage());
            return null;
        }
    }

    // --- small helpers ----------------------------------------------------

    private function nullableStr($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function clip(string $v, int $max): string
    {
        return mb_substr($v, 0, $max);
    }

    private function clipNullable($v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    /** Render only the date portion of a datetime string for the statement. */
    private function dateOnly(string $when): string
    {
        return substr($when, 0, 10);
    }

    /**
     * Join a list as "a, b and c".
     *
     * @param array<int,string> $items
     */
    private function joinList(array $items): string
    {
        $items = array_values(array_filter($items, fn ($i) => trim((string) $i) !== ''));
        $n = count($items);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $items[0];
        }
        $last = array_pop($items);
        return implode(', ', $items).' and '.$last;
    }
}
