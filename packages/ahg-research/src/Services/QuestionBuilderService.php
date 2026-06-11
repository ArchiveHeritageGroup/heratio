<?php

/**
 * QuestionBuilderService - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1226 - Research OS #4: Question Builder (ROS Stage 2, epic #1222).
 *
 * Refines a project's research question into a structured, VERSIONED Research
 * Design Brief. Every save creates a NEW immutable version row (never an
 * UPDATE of an existing version) and records the reason for the change, so the
 * brief's evolution is fully auditable before deep source collection begins.
 *
 * A lightweight DIAGNOSIS panel flags common design weaknesses. The diagnosis
 * is heuristic/checklist by default and works with no AI at all. When the
 * operator has AI configured it can OPTIONALLY enrich the diagnosis, routing
 * exclusively through the AHG gateway via the existing LlmService abstraction
 * (never a direct GPU node port).
 */

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class QuestionBuilderService
{
    /** Editable brief fields carried on every version row, in display order. */
    public const FIELDS = [
        'broad_topic',
        'problem_statement',
        'research_gap',
        'primary_question',
        'secondary_questions',
        'hypothesis',
        'scope_boundaries',
        'key_definitions',
        'assumptions',
        'bias_risks',
    ];

    /**
     * Are the Question Builder tables present? Callers use this for a clean
     * empty-state instead of letting a query 500 on a fresh install.
     */
    public function isReady(): bool
    {
        try {
            return Schema::hasTable('research_question_brief')
                && Schema::hasTable('research_question_brief_version');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The brief row for a project, or null when none exists yet. */
    public function getBrief(int $projectId): ?object
    {
        if (! $this->isReady()) {
            return null;
        }
        try {
            return DB::table('research_question_brief')
                ->where('project_id', $projectId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** The current (latest) version row for a project, or null. */
    public function getCurrentVersion(int $projectId): ?object
    {
        $brief = $this->getBrief($projectId);
        if (! $brief) {
            return null;
        }
        try {
            return DB::table('research_question_brief_version')
                ->where('brief_id', $brief->id)
                ->orderByDesc('version_no')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Full version history (newest first) for a project. */
    public function getVersions(int $projectId): array
    {
        $brief = $this->getBrief($projectId);
        if (! $brief) {
            return [];
        }
        try {
            return DB::table('research_question_brief_version')
                ->where('brief_id', $brief->id)
                ->orderByDesc('version_no')
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A single version row by id, scoped to a project for safety. */
    public function getVersion(int $projectId, int $versionId): ?object
    {
        $brief = $this->getBrief($projectId);
        if (! $brief) {
            return null;
        }
        try {
            return DB::table('research_question_brief_version')
                ->where('brief_id', $brief->id)
                ->where('id', $versionId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Save the brief for a project. This NEVER updates an existing version: it
     * appends a new version_no, copies the supplied fields, and stamps the
     * change reason. The parent brief row is created on first save and its
     * current_version pointer is advanced.
     *
     * Returns ['ok' => bool, 'version_no' => int, 'error' => ?string].
     */
    public function saveVersion(int $projectId, array $data, ?string $changeReason, ?int $userId): array
    {
        if (! $this->isReady()) {
            return ['ok' => false, 'version_no' => 0, 'error' => 'Question Builder storage is not ready.'];
        }

        try {
            $now = date('Y-m-d H:i:s');

            $brief = $this->getBrief($projectId);
            if (! $brief) {
                $briefId = DB::table('research_question_brief')->insertGetId([
                    'project_id'      => $projectId,
                    'current_version' => 0,
                    'status'          => $data['status'] ?? 'draft',
                    'created_by'      => $userId,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            } else {
                $briefId = (int) $brief->id;
            }

            $nextVersion = (int) (DB::table('research_question_brief_version')
                ->where('brief_id', $briefId)
                ->max('version_no') ?? 0) + 1;

            $row = ['brief_id' => $briefId, 'version_no' => $nextVersion];
            foreach (self::FIELDS as $f) {
                $value = $data[$f] ?? null;
                // secondary_questions arrives as a textarea (one per line); keep as text.
                $row[$f] = ($value === '' ? null : $value);
            }
            $row['change_reason'] = ($changeReason === '' ? null : $changeReason);
            $row['created_by'] = $userId;
            $row['created_at'] = $now;

            DB::table('research_question_brief_version')->insert($row);

            DB::table('research_question_brief')
                ->where('id', $briefId)
                ->update([
                    'current_version' => $nextVersion,
                    'status'          => $data['status'] ?? ($brief->status ?? 'draft'),
                    'updated_at'      => $now,
                ]);

            return ['ok' => true, 'version_no' => $nextVersion, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] question-builder save failed: ' . $e->getMessage());

            return ['ok' => false, 'version_no' => 0, 'error' => 'Could not save the brief. Please try again.'];
        }
    }

    /**
     * Heuristic diagnosis of the current (or supplied) brief. Returns a list of
     * flags, each ['key', 'label', 'level' (info|warning|danger|success),
     * 'message']. Pure PHP - no AI, never throws on missing data.
     *
     * Flags: too broad, too narrow, possibly already answered, methodologically
     * weak, ethically sensitive, misaligned with available evidence, likely
     * publishable.
     */
    public function diagnose(array $b): array
    {
        $flags = [];

        $topic   = trim((string) ($b['broad_topic'] ?? ''));
        $problem = trim((string) ($b['problem_statement'] ?? ''));
        $gap     = trim((string) ($b['research_gap'] ?? ''));
        $primary = trim((string) ($b['primary_question'] ?? ''));
        $secondary = trim((string) ($b['secondary_questions'] ?? ''));
        $hyp     = trim((string) ($b['hypothesis'] ?? ''));
        $scope   = trim((string) ($b['scope_boundaries'] ?? ''));
        $defs    = trim((string) ($b['key_definitions'] ?? ''));
        $assume  = trim((string) ($b['assumptions'] ?? ''));
        $bias    = trim((string) ($b['bias_risks'] ?? ''));

        $primaryWords = $primary === '' ? 0 : count(preg_split('/\s+/', $primary));
        $secondaryCount = $secondary === '' ? 0 : count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $secondary))));

        // ---- too broad -------------------------------------------------------
        $broadTriggers = preg_match('/\b(everything|all|global|the world|history of|impact of)\b/i', $primary . ' ' . $topic);
        if ($primary !== '' && ($primaryWords <= 6 || ($scope === '' && $broadTriggers))) {
            $flags[] = [
                'key' => 'too_broad', 'level' => 'warning', 'label' => 'Possibly too broad',
                'message' => 'The primary question reads broadly and the scope boundaries are thin. Add temporal, geographic, or population limits so the question is answerable within the project.',
            ];
        }

        // ---- too narrow ------------------------------------------------------
        if ($primary !== '' && $primaryWords >= 35 && $secondaryCount === 0) {
            $flags[] = [
                'key' => 'too_narrow', 'level' => 'info', 'label' => 'Possibly too narrow',
                'message' => 'The question is long and highly specific with no secondary questions. Consider whether it yields enough material for a sustained study, or break it into linked sub-questions.',
            ];
        }

        // ---- possibly already answered --------------------------------------
        if ($primary !== '' && $gap === '') {
            $flags[] = [
                'key' => 'already_answered', 'level' => 'warning', 'label' => 'Gap not stated',
                'message' => 'No research gap is recorded, so the question may already be answered in the literature. State what is currently unknown and why it matters.',
            ];
        }

        // ---- methodologically weak ------------------------------------------
        if ($primary !== '' && $hyp === '' && $defs === '') {
            $flags[] = [
                'key' => 'method_weak', 'level' => 'info', 'label' => 'Method scaffolding thin',
                'message' => 'No hypothesis and no key definitions are recorded. Even for exploratory work, define the central terms so the question can be operationalised.',
            ];
        }

        // ---- ethically sensitive --------------------------------------------
        $ethicsTriggers = preg_match('/\b(human subjects?|personal data|patient|child(ren)?|indigenous|vulnerable|consent|health|medical|sacred|repatriation|human remains|ethnic|race|religion|sexual|minor)\b/i',
            $topic . ' ' . $problem . ' ' . $primary . ' ' . $secondary);
        if ($ethicsTriggers) {
            $flags[] = [
                'key' => 'ethics', 'level' => 'danger', 'label' => 'Ethically sensitive',
                'message' => 'The brief touches subjects that often require ethics review, informed consent, or cultural protocols. Record the relevant safeguards in scope boundaries and assumptions before collecting sources.',
            ];
        }

        // ---- misaligned with available evidence -----------------------------
        if ($primary !== '' && $assume === '' && $bias === '') {
            $flags[] = [
                'key' => 'evidence_alignment', 'level' => 'info', 'label' => 'Evidence assumptions unrecorded',
                'message' => 'No assumptions about evidence availability and no bias risks are recorded. Note whether the sources needed to answer the question are likely to exist and be accessible.',
            ];
        }

        // ---- likely publishable (positive signal) ---------------------------
        $completeness = 0;
        foreach (self::FIELDS as $f) {
            if (trim((string) ($b[$f] ?? '')) !== '') {
                $completeness++;
            }
        }
        if ($completeness >= 8 && $gap !== '' && $scope !== '') {
            $flags[] = [
                'key' => 'publishable', 'level' => 'success', 'label' => 'Looks publishable',
                'message' => 'The brief is well formed: a clear gap, bounded scope, and most design fields completed. This question is in good shape to take into source collection.',
            ];
        }

        if (empty($flags)) {
            $flags[] = [
                'key' => 'ok', 'level' => 'success', 'label' => 'No issues detected',
                'message' => 'The heuristic checks did not flag anything. Keep refining as your reading develops.',
            ];
        }

        return $flags;
    }

    /**
     * Is an AI-assisted diagnosis available? True only when the optional
     * ahg-ai-services LlmService class is installed. The actual transport
     * (gateway routing, key, quota) is owned by LlmService - we never talk to a
     * node directly.
     */
    public function aiAvailable(): bool
    {
        return class_exists(\AhgAiServices\Services\LlmService::class);
    }

    /**
     * OPTIONAL AI enrichment of the diagnosis. Routes through the AHG gateway
     * via LlmService::complete() (https://ai.theahg.co.za/ai/v1/...), never a
     * direct GPU node port. Returns a short labelled note, or null when AI is
     * unavailable / disabled / errors. Callers MUST label this as AI output.
     */
    public function aiDiagnosis(array $b): ?string
    {
        if (! $this->aiAvailable()) {
            return null;
        }

        try {
            $lines = [];
            $labels = [
                'broad_topic' => 'Broad topic', 'problem_statement' => 'Problem statement',
                'research_gap' => 'Research gap', 'primary_question' => 'Primary question',
                'secondary_questions' => 'Secondary questions', 'hypothesis' => 'Hypothesis',
                'scope_boundaries' => 'Scope boundaries', 'key_definitions' => 'Key definitions',
                'assumptions' => 'Assumptions', 'bias_risks' => 'Bias and risks',
            ];
            foreach ($labels as $f => $label) {
                $v = trim((string) ($b[$f] ?? ''));
                if ($v !== '') {
                    $lines[] = $label . ': ' . $v;
                }
            }
            if (empty($lines)) {
                return null;
            }

            $prompt = "You are a research methodology adviser reviewing a draft Research Design Brief. "
                . "Assess the question for: scope (too broad / too narrow), whether it may already be answered, "
                . "methodological soundness, ethical sensitivity, alignment with likely available evidence, and "
                . "publishability. Be concise and constructive. Reply in 4 to 6 short bullet points, no preamble.\n\n"
                . "Brief:\n" . implode("\n", $lines);

            $out = app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 400, 'temperature' => 0.3]);

            $out = is_string($out) ? trim($out) : '';

            return $out === '' ? null : $out;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] question-builder AI diagnosis failed: ' . $e->getMessage());

            return null;
        }
    }
}
