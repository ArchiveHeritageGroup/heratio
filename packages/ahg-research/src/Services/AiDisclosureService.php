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
 *   - research_review_run     (#1230 Review Studio)  persona/model/summary/created_at
 *   - research_source_triage  (#1227 Source Triage)  ai_preview/ai_preview_at
 *   - research_contradiction  (#1236 Contradiction Engine) rows where source='ai'
 *
 * Question Builder (#1226) and Analysis Bridge (#1234) call AI transiently and do
 * not persist an AI marker column, so they are not auto-detected; researchers can
 * record those uses through the manual log.
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
