<?php

/**
 * PublicationStudioService - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1232 - Research OS #10: Publication Studio (ROS Stage 15, epic #1222).
 *
 * Per-project publication workflow built ON the existing target-journal
 * directory (ResearchTargetJournalService / research_target_journal, #1107).
 * Responsibilities:
 *
 *   - journal MATCHING: score directory journals for a project (subject scope,
 *     plus optional open-access / accreditation-market filters);
 *   - create a SUBMISSION against a matched venue (or a free-text venue);
 *   - manage the compliance REQUIREMENT checklist (word count, formatting,
 *     reference style, data-availability statement, ethics statement - a default
 *     checklist is seeded per submission and stays operator-editable);
 *   - the response-to-reviewers / revision-history thread;
 *   - status transitions (drafting -> submitted -> reviewed -> revised ->
 *     accepted -> published, with rejected as a terminal branch) plus the DOI /
 *     repository deposit fields.
 *
 * Works with NO AI. Any optional AI (a venue-fit suggestion) routes EXCLUSIVELY
 * through the AHG gateway via the LlmService abstraction (never a GPU node port),
 * and its output is always labelled as AI in the UI.
 *
 * Jurisdiction-neutral: DHET is one accreditation market among many in the
 * directory; nothing here assumes a South-African (or any other) regime.
 *
 * Every query is Schema::hasTable-guarded and wrapped in try/catch so a fresh
 * install yields a clean empty-state rather than a 500. Writes only ever touch
 * the three NEW tables; existing tables are read-only here.
 */

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PublicationStudioService
{
    /** Submission lifecycle. VARCHAR-backed (Dropdown Manager: submission_status), never an ENUM. */
    public const STATUSES = ['drafting', 'submitted', 'reviewed', 'revised', 'accepted', 'published', 'rejected'];

    /** Allowed forward transitions. 'rejected' is reachable from any active state. */
    public const TRANSITIONS = [
        'drafting'  => ['submitted', 'rejected'],
        'submitted' => ['reviewed', 'accepted', 'rejected'],
        'reviewed'  => ['revised', 'accepted', 'rejected'],
        'revised'   => ['submitted', 'reviewed', 'accepted', 'rejected'],
        'accepted'  => ['published'],
        'published' => [],
        'rejected'  => ['drafting'],
    ];

    /**
     * Default compliance checklist seeded per submission. Generic, international
     * publishing requirements - NOT tied to any one market. Operators add or
     * remove items per submission afterwards.
     */
    public const DEFAULT_REQUIREMENTS = [
        'Word count within the venue limit',
        'Formatting matches the venue template',
        'Reference style applied consistently',
        'Data availability statement included',
        'Ethics statement included',
        'Author contributions and conflicts declared',
        'ORCID and affiliations complete',
    ];

    public function __construct(
        private ResearchTargetJournalService $directory = new ResearchTargetJournalService(),
    ) {}

    // ── readiness ────────────────────────────────────────────────────────────

    /** Are the Publication Studio tables present? */
    public function isReady(): bool
    {
        try {
            return Schema::hasTable('research_submission')
                && Schema::hasTable('research_submission_requirement')
                && Schema::hasTable('research_submission_response');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Is the target-journal directory available to match against? */
    public function directoryReady(): bool
    {
        try {
            return Schema::hasTable('research_target_journal');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ── journal matching (reads the target-journal directory) ────────────────

    /**
     * Best-fit venues for a project. Builds a scope string from the project's
     * title + description (and any supplied extra text), scores each directory
     * journal by overlapping subject terms (delegating to the directory's own
     * suggestForScope), then applies simple post-filters:
     *
     *   - open_access => only open-access journals;
     *   - market      => only journals accredited in that market (e.g. 'ZA').
     *
     * Returns up to $limit journals, each with a 'match_score'. Never throws.
     */
    public function matchVenues(object $project, array $filters = [], int $limit = 10): array
    {
        if (! $this->directoryReady()) {
            return [];
        }

        try {
            $text = trim(($project->title ?? '') . ' ' . ($project->description ?? '') . ' ' . ($filters['scope_text'] ?? ''));

            // Score by subject overlap using the directory's own matcher.
            $matches = $this->directory->suggestForScope($text, max($limit * 3, 30));

            // Fallback: if the project has too little text to produce any match,
            // surface the directory so the user is never stranded on an empty page.
            if (empty($matches)) {
                $matches = array_map(function ($j) {
                    $j['match_score'] = 0;
                    return $j;
                }, $this->directory->list([]));
            }

            // Post-filters (simple scope / open-access / market).
            if (! empty($filters['open_access'])) {
                $matches = array_values(array_filter($matches, fn ($j) => ! empty($j['open_access'])));
            }
            if (! empty($filters['market'])) {
                $market = strtoupper((string) $filters['market']);
                $matches = array_values(array_filter(
                    $matches,
                    fn ($j) => strtoupper((string) ($j['accreditation_market'] ?? '')) === $market
                ));
            }
            if (! empty($filters['reference_style'])) {
                $rs = strtolower((string) $filters['reference_style']);
                $matches = array_values(array_filter(
                    $matches,
                    fn ($j) => strtolower((string) ($j['reference_style'] ?? '')) === $rs
                ));
            }

            return array_slice($matches, 0, $limit);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] publication-studio matchVenues failed: ' . $e->getMessage());

            return [];
        }
    }

    /** Distinct accreditation markets present in the directory (for the filter dropdown). */
    public function directoryMarkets(): array
    {
        if (! $this->directoryReady()) {
            return [];
        }
        try {
            return DB::table('research_target_journal')
                ->whereNotNull('accreditation_market')
                ->where('accreditation_market', '!=', '')
                ->distinct()->orderBy('accreditation_market')
                ->pluck('accreditation_market')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A directory journal row by id, or null. Read-only. */
    public function getJournal(?int $id): ?array
    {
        if (! $id || ! $this->directoryReady()) {
            return null;
        }
        try {
            $row = DB::table('research_target_journal')->where('id', $id)->first();

            return $row ? (array) $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── submissions ──────────────────────────────────────────────────────────

    /** All submissions for a project (newest first). */
    public function submissionsForProject(int $projectId): array
    {
        if (! $this->isReady()) {
            return [];
        }
        try {
            return DB::table('research_submission')
                ->where('project_id', $projectId)
                ->orderByDesc('id')
                ->get()->map(fn ($r) => (array) $r)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A single submission, scoped to a project for safety. */
    public function getSubmission(int $projectId, int $submissionId): ?array
    {
        if (! $this->isReady()) {
            return null;
        }
        try {
            $row = DB::table('research_submission')
                ->where('project_id', $projectId)
                ->where('id', $submissionId)
                ->first();

            return $row ? (array) $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create a submission for a project against a venue (matched directory
     * journal via $venueRef, or a free-text venue name). Seeds the default
     * compliance checklist. Returns ['ok','id','error'].
     */
    public function createSubmission(int $projectId, array $data, ?int $userId): array
    {
        if (! $this->isReady()) {
            return ['ok' => false, 'id' => 0, 'error' => 'Publication Studio storage is not ready.'];
        }

        try {
            $now = date('Y-m-d H:i:s');

            $venueRef = isset($data['venue_ref']) && $data['venue_ref'] !== '' ? (int) $data['venue_ref'] : null;
            $venueName = trim((string) ($data['venue_name'] ?? ''));

            // If a directory journal was chosen but no name typed, take its title.
            if ($venueRef && $venueName === '') {
                $journal = $this->getJournal($venueRef);
                $venueName = $journal['title'] ?? '';
            }
            if ($venueName === '') {
                return ['ok' => false, 'id' => 0, 'error' => 'A venue name is required.'];
            }

            $status = $this->normaliseStatus($data['status'] ?? 'drafting');

            $id = DB::table('research_submission')->insertGetId([
                'project_id'       => $projectId,
                'venue_ref'        => $venueRef,
                'venue_name'       => mb_substr($venueName, 0, 300),
                'status'           => $status,
                'manuscript_title' => ($data['manuscript_title'] ?? '') !== '' ? mb_substr((string) $data['manuscript_title'], 0, 500) : null,
                'doi'              => ($data['doi'] ?? '') !== '' ? $data['doi'] : null,
                'repository_url'   => ($data['repository_url'] ?? '') !== '' ? $data['repository_url'] : null,
                'notes'            => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
                'created_by'       => $userId,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            $this->seedRequirements((int) $id);

            return ['ok' => true, 'id' => (int) $id, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] publication-studio createSubmission failed: ' . $e->getMessage());

            return ['ok' => false, 'id' => 0, 'error' => 'Could not create the submission. Please try again.'];
        }
    }

    /** Update the deposit / metadata fields (DOI, repository, notes, manuscript title, venue name). */
    public function updateSubmissionMeta(int $projectId, int $submissionId, array $data): bool
    {
        if (! $this->getSubmission($projectId, $submissionId)) {
            return false;
        }
        try {
            $patch = ['updated_at' => date('Y-m-d H:i:s')];
            foreach (['manuscript_title' => 500, 'venue_name' => 300] as $f => $len) {
                if (array_key_exists($f, $data)) {
                    $patch[$f] = ($data[$f] === '' ? ($f === 'venue_name' ? '' : null) : mb_substr((string) $data[$f], 0, $len));
                }
            }
            foreach (['doi', 'repository_url', 'notes'] as $f) {
                if (array_key_exists($f, $data)) {
                    $patch[$f] = ($data[$f] === '' ? null : $data[$f]);
                }
            }
            if (! empty($patch['venue_name'] === '')) {
                unset($patch['venue_name']); // never blank a required column
            }

            DB::table('research_submission')
                ->where('project_id', $projectId)->where('id', $submissionId)
                ->update($patch);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] publication-studio updateSubmissionMeta failed: ' . $e->getMessage());

            return false;
        }
    }

    // ── status transitions ───────────────────────────────────────────────────

    /** Statuses reachable from the current one (for the UI buttons). */
    public function allowedNextStatuses(?string $current): array
    {
        $current = $this->normaliseStatus($current ?? 'drafting');

        return self::TRANSITIONS[$current] ?? [];
    }

    /**
     * Move a submission to a new status, validating the transition. Stamps
     * submitted_at on first 'submitted' and decision_at on accepted/rejected/
     * published. Returns ['ok','error'].
     */
    public function transition(int $projectId, int $submissionId, string $to): array
    {
        $submission = $this->getSubmission($projectId, $submissionId);
        if (! $submission) {
            return ['ok' => false, 'error' => 'Submission not found.'];
        }

        $to = $this->normaliseStatus($to);
        $from = $this->normaliseStatus($submission['status'] ?? 'drafting');

        if (! in_array($to, self::STATUSES, true)) {
            return ['ok' => false, 'error' => 'Unknown status.'];
        }
        if ($to !== $from && ! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return ['ok' => false, 'error' => 'That status change is not allowed from "' . $from . '".'];
        }

        try {
            $patch = ['status' => $to, 'updated_at' => date('Y-m-d H:i:s')];
            if ($to === 'submitted' && empty($submission['submitted_at'])) {
                $patch['submitted_at'] = date('Y-m-d');
            }
            if (in_array($to, ['accepted', 'rejected', 'published'], true) && empty($submission['decision_at'])) {
                $patch['decision_at'] = date('Y-m-d');
            }

            DB::table('research_submission')
                ->where('project_id', $projectId)->where('id', $submissionId)
                ->update($patch);

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] publication-studio transition failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not update the status.'];
        }
    }

    private function normaliseStatus(?string $s): string
    {
        $s = strtolower(trim((string) $s));

        return in_array($s, self::STATUSES, true) ? $s : 'drafting';
    }

    // ── compliance checklist (requirements) ──────────────────────────────────

    /** Requirements for a submission, in display order. */
    public function requirements(int $submissionId): array
    {
        if (! $this->isReady()) {
            return [];
        }
        try {
            return DB::table('research_submission_requirement')
                ->where('submission_id', $submissionId)
                ->orderBy('sort_order')->orderBy('id')
                ->get()->map(fn ($r) => (array) $r)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Seed the default checklist for a fresh submission (idempotent: only if empty). */
    public function seedRequirements(int $submissionId): void
    {
        if (! $this->isReady()) {
            return;
        }
        try {
            $exists = DB::table('research_submission_requirement')
                ->where('submission_id', $submissionId)->exists();
            if ($exists) {
                return;
            }
            $now = date('Y-m-d H:i:s');
            $rows = [];
            foreach (array_values(self::DEFAULT_REQUIREMENTS) as $i => $label) {
                $rows[] = [
                    'submission_id' => $submissionId,
                    'label'         => $label,
                    'met'           => 0,
                    'note'          => null,
                    'sort_order'    => $i,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
            DB::table('research_submission_requirement')->insert($rows);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] publication-studio seedRequirements failed: ' . $e->getMessage());
        }
    }

    /** Add a custom requirement to the checklist. */
    public function addRequirement(int $submissionId, string $label): bool
    {
        $label = trim($label);
        if ($label === '' || ! $this->isReady()) {
            return false;
        }
        try {
            $now = date('Y-m-d H:i:s');
            $next = (int) (DB::table('research_submission_requirement')
                ->where('submission_id', $submissionId)->max('sort_order') ?? -1) + 1;
            DB::table('research_submission_requirement')->insert([
                'submission_id' => $submissionId,
                'label'         => mb_substr($label, 0, 255),
                'met'           => 0,
                'sort_order'    => $next,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Update one requirement's met flag and note. */
    public function updateRequirement(int $submissionId, int $reqId, bool $met, ?string $note): bool
    {
        if (! $this->isReady()) {
            return false;
        }
        try {
            return DB::table('research_submission_requirement')
                ->where('submission_id', $submissionId)->where('id', $reqId)
                ->update([
                    'met'        => $met ? 1 : 0,
                    'note'       => ($note === '' || $note === null) ? null : mb_substr($note, 0, 1000),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Delete a requirement row. */
    public function deleteRequirement(int $submissionId, int $reqId): bool
    {
        if (! $this->isReady()) {
            return false;
        }
        try {
            DB::table('research_submission_requirement')
                ->where('submission_id', $submissionId)->where('id', $reqId)->delete();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Checklist completion as [met, total]. */
    public function requirementProgress(int $submissionId): array
    {
        $reqs = $this->requirements($submissionId);
        $met = count(array_filter($reqs, fn ($r) => ! empty($r['met'])));

        return [$met, count($reqs)];
    }

    // ── response-to-reviewers / revision history ─────────────────────────────

    /** Responses (newest first). */
    public function responses(int $submissionId): array
    {
        if (! $this->isReady()) {
            return [];
        }
        try {
            return DB::table('research_submission_response')
                ->where('submission_id', $submissionId)
                ->orderByDesc('id')
                ->get()->map(fn ($r) => (array) $r)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Add a reviewer point + author response + revision note. */
    public function addResponse(int $submissionId, array $data, ?int $userId): bool
    {
        if (! $this->isReady()) {
            return false;
        }
        $point = trim((string) ($data['point'] ?? ''));
        $response = trim((string) ($data['response'] ?? ''));
        $revision = trim((string) ($data['revision_note'] ?? ''));
        if ($point === '' && $response === '' && $revision === '') {
            return false;
        }
        try {
            DB::table('research_submission_response')->insert([
                'submission_id'  => $submissionId,
                'reviewer_label' => ($data['reviewer_label'] ?? '') !== '' ? mb_substr((string) $data['reviewer_label'], 0, 120) : null,
                'point'          => $point === '' ? null : $point,
                'response'       => $response === '' ? null : $response,
                'revision_note'  => $revision === '' ? null : $revision,
                'created_by'     => $userId,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] publication-studio addResponse failed: ' . $e->getMessage());

            return false;
        }
    }

    // ── optional AI venue-fit suggestion (gateway only) ──────────────────────

    /** Is the optional AI fit-suggestion available (LlmService installed)? */
    public function aiAvailable(): bool
    {
        return class_exists(\AhgAiServices\Services\LlmService::class);
    }

    /**
     * OPTIONAL: short AI note on how a project fits a chosen venue. Routes
     * through the AHG gateway via LlmService::complete() only - never a node
     * port. Returns null when unavailable / disabled / on error. Callers MUST
     * label the output as AI.
     */
    public function aiFitSuggestion(object $project, array $journal, ?int $projectId = null, ?int $submissionId = null): ?string
    {
        if (! $this->aiAvailable()) {
            return null;
        }
        try {
            $prompt = "You are a scholarly-publishing adviser. In 3 to 5 short bullet points, "
                . "assess how well this research project fits the target journal, and note any gaps "
                . "the author should close before submitting. Be concise and constructive, no preamble.\n\n"
                . "Project title: " . trim((string) ($project->title ?? '')) . "\n"
                . "Project summary: " . trim((string) ($project->description ?? '(none)')) . "\n\n"
                . "Journal: " . trim((string) ($journal['title'] ?? '')) . "\n"
                . "Scope: " . trim((string) ($journal['subject_scope'] ?? '(unspecified)')) . "\n"
                . "Article types: " . trim((string) ($journal['article_types'] ?? '(unspecified)')) . "\n"
                . "Open access: " . (! empty($journal['open_access']) ? 'yes' : 'no');

            $out = app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 350, 'temperature' => 0.3]);

            $out = is_string($out) ? trim($out) : '';

            // #1252 AI-use disclosure: a successful gateway fit-suggestion is AI
            // assistance applied to a submission. Stamp the submission row when a
            // specific submission is in context. A pure project-level advisory
            // (no submission id) leaves nothing to attribute the marker to, so it
            // is not recorded here - the researcher may log it via the manual log.
            if ($out !== '' && $projectId !== null && $submissionId !== null && $submissionId > 0) {
                $this->stampAiSubmission($projectId, $submissionId);
            }

            return $out === '' ? null : $out;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] publication-studio aiFitSuggestion failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * #1252 - mark a submission row as AI-assisted (project-scoped). Best-effort;
     * never throws into the caller.
     */
    protected function stampAiSubmission(int $projectId, int $submissionId): void
    {
        try {
            if (! Schema::hasTable('research_submission')
                || ! Schema::hasColumn('research_submission', 'ai_at')) {
                return;
            }
            DB::table('research_submission')
                ->where('project_id', $projectId)
                ->where('id', $submissionId)
                ->update([
                    'ai_model'   => $this->resolveAiModel(),
                    'ai_at'      => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (\Throwable $e) {
            // best-effort disclosure marker only.
        }
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
}
