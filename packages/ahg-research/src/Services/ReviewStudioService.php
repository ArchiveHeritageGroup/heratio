<?php

/**
 * ReviewStudioService - Service for Heratio
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
 * ReviewStudioService - Research OS Stage 14 (heratio#1230, epic #1222).
 *
 * Two halves, both per-project:
 *
 *  (1) Supervisor / co-author comment threads. A comment anchors to a CLAIM
 *      (an assertion_id row in research_assertion) or to the project as a whole
 *      (assertion_id NULL). Replies self-reference the root comment via
 *      thread_id. Comments resolve / unresolve and order by created_at for a
 *      lightweight revision history. This half works FULLY WITHOUT AI.
 *
 *  (2) An adversarial reviewer-twin simulation. The project's claims + brief are
 *      assembled (read-only) into a persona-driven prompt and sent to the AHG
 *      gateway via AhgAiServices\Services\LlmService::complete() - NEVER a direct
 *      node port. The reply is parsed into grouped findings and stored in
 *      research_review_run. Every AI output is labelled in the UI as
 *      "AI reviewer - via the AHG gateway, not a human reviewer". When AI is
 *      unavailable the run degrades to a clear message and the comment half stays
 *      fully usable.
 *
 * Every read/write is Schema::hasTable-guarded and wrapped in try/catch so the
 * studio degrades to an empty state rather than throwing a 500 during a partial
 * install. Only the two NEW tables are written; existing tables are read-only.
 */
class ReviewStudioService
{
    /** The mandatory provenance label on every piece of AI output in this studio. */
    public const AI_LABEL = 'AI reviewer - via the AHG gateway, not a human reviewer';

    /**
     * Selectable adversarial reviewer personas. VARCHAR-stored (not ENUM).
     *
     * @var array<string,string>
     */
    public const PERSONAS = [
        'methodologist'   => 'Methodologist',
        'theory_purist'   => 'Theory purist',
        'statistician'    => 'Statistician',
        'reviewer_2'      => 'Reviewer 2',
    ];

    /**
     * Per-persona framing fed into the system prompt. Each is deliberately
     * adversarial - the twin's job is to find what a tough reviewer would.
     *
     * @var array<string,string>
     */
    protected const PERSONA_BRIEFS = [
        'methodologist' => 'You are a rigorous research methodologist. Attack the methodology: sampling, validity, reproducibility, confounders, whether the evidence actually supports each claim, and whether the method matches the research question.',
        'theory_purist' => 'You are a theory purist. Attack the conceptual framing: definitions, theoretical grounding, internal consistency, whether claims follow from the stated framework, and engagement with the canonical literature.',
        'statistician'  => 'You are a hard-nosed statistician. Attack the quantitative reasoning: sample sizes, inference, effect sizes, p-hacking risk, over-claiming from thin data, and any claim that rests on a single source or anecdote.',
        'reviewer_2'    => 'You are the notorious "Reviewer 2": maximally sceptical, demanding, and unimpressed. Find every weakness, demand more evidence, question novelty and contribution, and surface the objections a hostile peer reviewer would raise.',
    ];

    /**
     * The grouped finding buckets the reviewer-twin output is parsed into.
     * Order is the rendering order. Key = stored JSON key.
     *
     * @var array<string,string>
     */
    public const FINDING_GROUPS = [
        'major_concerns'        => 'Major concerns',
        'minor_concerns'        => 'Minor concerns',
        'likely_objections'     => 'Likely objections',
        'required_revisions'    => 'Required revisions',
        'rejection_risks'       => 'Rejection risks',
        'strongest_contribution'=> 'Strongest contribution',
        'weakest_section'       => 'Weakest section',
        'missing_literature'    => 'Missing literature',
    ];

    // =====================================================================
    // SCHEMA GUARDS
    // =====================================================================

    protected function commentsReady(): bool
    {
        try {
            return Schema::hasTable('research_review_comment');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function runsReady(): bool
    {
        try {
            return Schema::hasTable('research_review_run');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // HALF 1: COMMENT THREADS (works fully without AI)
    // =====================================================================

    /**
     * All comments for a project, optionally scoped to one claim. Root comments
     * (thread_id NULL) carry their replies nested under a `replies` array and an
     * `author_name`. Ordered by created_at for revision-history reading.
     *
     * @return array<int,object>
     */
    public function listComments(int $projectId, ?int $assertionId = null, bool $includeResolved = true): array
    {
        if (! $this->commentsReady()) {
            return [];
        }
        try {
            $q = DB::table('research_review_comment')
                ->where('project_id', $projectId);

            if ($assertionId !== null) {
                $q->where('assertion_id', $assertionId);
            }
            if (! $includeResolved) {
                $q->where('resolved', 0);
            }

            $rows = $q->orderBy('created_at', 'asc')->get();

            // Resolve author display names in one batched query (defensive).
            $names = $this->resolveAuthorNames($rows->pluck('author_id')->all());

            // Index roots and attach replies.
            $roots   = [];
            $replies = [];
            foreach ($rows as $r) {
                $r->author_name = $names[$r->author_id] ?? ('User #' . $r->author_id);
                $r->replies = [];
                if (empty($r->thread_id)) {
                    $roots[$r->id] = $r;
                } else {
                    $replies[] = $r;
                }
            }
            foreach ($replies as $r) {
                if (isset($roots[$r->thread_id])) {
                    $roots[$r->thread_id]->replies[] = $r;
                } else {
                    // Orphaned reply (root removed) - surface as its own root.
                    $roots[$r->id] = $r;
                }
            }
            return array_values($roots);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] review-studio listComments failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Claims (assertions) for a project, as anchor candidates for a comment.
     * Read-only over research_assertion. Returns id + a short label + comment
     * count so the panel can show anchored-to-claim chips.
     *
     * @return array<int,object>
     */
    public function claimAnchors(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_assertion')) {
                return [];
            }
            $rows = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->orderBy('updated_at', 'desc')
                ->limit(500)
                ->get(['id', 'subject_label', 'object_value', 'object_label', 'status']);

            // Per-claim open-comment counts (one batched query).
            $counts = [];
            if ($this->commentsReady() && $rows->count() > 0) {
                $counts = DB::table('research_review_comment')
                    ->where('project_id', $projectId)
                    ->whereNotNull('assertion_id')
                    ->select('assertion_id', DB::raw('COUNT(*) as n'))
                    ->groupBy('assertion_id')
                    ->pluck('n', 'assertion_id')->all();
            }
            foreach ($rows as $r) {
                $r->label = $this->claimLabel($r);
                $r->comment_count = (int) ($counts[$r->id] ?? 0);
            }
            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Resolve a single claim row scoped to the project, or null. */
    public function getClaim(int $projectId, int $assertionId): ?object
    {
        try {
            if (! Schema::hasTable('research_assertion')) {
                return null;
            }
            $r = DB::table('research_assertion')
                ->where('id', $assertionId)
                ->where('project_id', $projectId)
                ->first(['id', 'subject_label', 'object_value', 'object_label', 'status']);
            if ($r) {
                $r->label = $this->claimLabel($r);
            }
            return $r;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Build a short human label for a claim row. */
    protected function claimLabel(object $r): string
    {
        $text = $r->object_value ?? $r->subject_label ?? $r->object_label ?? '';
        $text = trim((string) $text);
        if ($text === '') {
            $text = 'Untitled claim #' . ($r->id ?? '');
        }
        return mb_substr($text, 0, 140);
    }

    /**
     * Add a root comment or a reply. assertion_id anchors to a claim (validated
     * to belong to the project) or NULL for a project-level comment. thread_id
     * makes it a reply (validated to belong to the same project). Returns the new
     * comment id, or null on failure.
     */
    public function addComment(int $projectId, int $authorId, string $body, ?int $assertionId = null, ?int $threadId = null): ?int
    {
        if (! $this->commentsReady()) {
            return null;
        }
        $body = trim($body);
        if ($body === '') {
            return null;
        }
        try {
            // Validate the claim anchor belongs to this project.
            if ($assertionId !== null) {
                $ok = Schema::hasTable('research_assertion')
                    && DB::table('research_assertion')
                        ->where('id', $assertionId)->where('project_id', $projectId)->exists();
                if (! $ok) {
                    return null;
                }
            }
            // Validate the parent thread belongs to this project; inherit its anchor.
            if ($threadId !== null) {
                $parent = DB::table('research_review_comment')
                    ->where('id', $threadId)->where('project_id', $projectId)->first();
                if (! $parent) {
                    return null;
                }
                // A reply always sits on the root thread, never on a reply.
                if (! empty($parent->thread_id)) {
                    $threadId = (int) $parent->thread_id;
                    $parent = DB::table('research_review_comment')
                        ->where('id', $threadId)->where('project_id', $projectId)->first();
                }
                // Replies share the root's claim anchor.
                $assertionId = $parent->assertion_id !== null ? (int) $parent->assertion_id : null;
            }

            $now = now();
            return DB::table('research_review_comment')->insertGetId([
                'project_id'   => $projectId,
                'assertion_id' => $assertionId,
                'thread_id'    => $threadId,
                'author_id'    => $authorId,
                'body'         => $body,
                'resolved'     => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] review-studio addComment failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve / unresolve a root comment thread (the toggle cascades to its
     * replies so a resolved thread reads as one unit). Project-scoped.
     */
    public function setResolved(int $projectId, int $commentId, bool $resolved): bool
    {
        if (! $this->commentsReady()) {
            return false;
        }
        try {
            $comment = DB::table('research_review_comment')
                ->where('id', $commentId)->where('project_id', $projectId)->first();
            if (! $comment) {
                return false;
            }
            // Normalise to the root id so resolving a reply resolves the thread.
            $rootId = empty($comment->thread_id) ? $commentId : (int) $comment->thread_id;

            DB::table('research_review_comment')
                ->where('project_id', $projectId)
                ->where(function ($w) use ($rootId) {
                    $w->where('id', $rootId)->orWhere('thread_id', $rootId);
                })
                ->update(['resolved' => $resolved ? 1 : 0, 'updated_at' => now()]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Delete a comment (and, when it is a root, its replies). Project-scoped. */
    public function deleteComment(int $projectId, int $commentId): bool
    {
        if (! $this->commentsReady()) {
            return false;
        }
        try {
            $comment = DB::table('research_review_comment')
                ->where('id', $commentId)->where('project_id', $projectId)->first();
            if (! $comment) {
                return false;
            }
            if (empty($comment->thread_id)) {
                // Root: remove its replies too.
                DB::table('research_review_comment')
                    ->where('project_id', $projectId)
                    ->where('thread_id', $commentId)->delete();
            }
            DB::table('research_review_comment')
                ->where('project_id', $projectId)->where('id', $commentId)->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Resolve users.id -> display name for a set of ids (defensive, batched). */
    protected function resolveAuthorNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return [];
        }
        try {
            if (! Schema::hasTable('users')) {
                return [];
            }
            $col = Schema::hasColumn('users', 'name') ? 'name'
                : (Schema::hasColumn('users', 'username') ? 'username' : null);
            if ($col === null) {
                return [];
            }
            return DB::table('users')->whereIn('id', $ids)
                ->pluck($col, 'id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =====================================================================
    // HALF 2: ADVERSARIAL REVIEWER TWIN (AI, gateway-only, always labelled)
    // =====================================================================

    /**
     * Past reviewer-twin runs for a project, newest first. findings is decoded
     * from JSON. created_by is resolved to a display name.
     *
     * @return array<int,object>
     */
    public function listRuns(int $projectId, int $limit = 25): array
    {
        if (! $this->runsReady()) {
            return [];
        }
        try {
            $rows = DB::table('research_review_run')
                ->where('project_id', $projectId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)->get();

            $names = $this->resolveAuthorNames($rows->pluck('created_by')->all());
            foreach ($rows as $r) {
                $r->author_name = $names[$r->created_by] ?? ('User #' . $r->created_by);
                $r->findings_decoded = $this->decodeFindings($r->findings ?? null);
                $r->persona_label = self::PERSONAS[$r->persona] ?? ucfirst((string) $r->persona);
            }
            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Load a single run scoped to a project, with decoded findings. */
    public function getRun(int $projectId, int $runId): ?object
    {
        if (! $this->runsReady()) {
            return null;
        }
        try {
            $r = DB::table('research_review_run')
                ->where('id', $runId)->where('project_id', $projectId)->first();
            if (! $r) {
                return null;
            }
            $r->findings_decoded = $this->decodeFindings($r->findings ?? null);
            $r->persona_label = self::PERSONAS[$r->persona] ?? ucfirst((string) $r->persona);
            return $r;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Run the adversarial reviewer twin for a project.
     *
     * Assembles the project's brief (research_project.description) + its claims
     * (research_assertion) read-only into a persona-driven prompt, calls the AHG
     * gateway through LlmService::complete(), parses the JSON reply into the
     * grouped finding buckets, and persists a research_review_run row.
     *
     * Degrades gracefully: on any AI failure / empty reply, returns
     * ['ok' => false, 'message' => ...] WITHOUT writing a run, so the caller can
     * show a clear "AI unavailable" message while the comment half stays usable.
     *
     * @return array{ok:bool, run_id?:int, message?:string, persona:string}
     */
    public function runReviewerTwin(object $project, string $persona, int $userId): array
    {
        $persona = array_key_exists($persona, self::PERSONAS) ? $persona : 'methodologist';
        $out = ['ok' => false, 'persona' => $persona];

        // Assemble read-only context.
        $brief = trim((string) ($project->description ?? ''));
        $claims = $this->collectClaimsText((int) $project->id);

        if ($brief === '' && empty($claims)) {
            $out['message'] = 'This project has no brief and no claims yet, so there is nothing for the reviewer to assess. Add a project description or some claims first.';
            return $out;
        }

        $prompt = $this->buildPrompt($project, $brief, $claims, $persona);

        $raw = null;
        try {
            $raw = app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 1100, 'temperature' => 0.4]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] reviewer-twin LLM failed: ' . $e->getMessage());
            $out['message'] = 'The AI reviewer is currently unavailable (the AHG gateway did not respond). Your comment threads are unaffected. Please try the simulation again later.';
            return $out;
        }

        $raw = trim((string) $raw);
        if ($raw === '') {
            $out['message'] = 'The AI reviewer returned no response. The AHG gateway may be busy or offline. Your comment threads are unaffected - please try again shortly.';
            return $out;
        }

        $parsed   = $this->parseFindings($raw);
        $summary  = $parsed['summary'];
        $findings = $parsed['findings'];

        // Persist the run (best-effort; the parsed result is returned either way).
        $runId = null;
        if ($this->runsReady()) {
            try {
                $runId = DB::table('research_review_run')->insertGetId([
                    'project_id' => (int) $project->id,
                    'persona'    => $persona,
                    'model'      => $this->detectModel(),
                    'summary'    => $summary,
                    'findings'   => json_encode($findings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('[ahg-research] reviewer-twin persist failed: ' . $e->getMessage());
            }
        }

        $out['ok'] = true;
        if ($runId) {
            $out['run_id'] = $runId;
        }
        $out['message'] = 'Reviewer simulation complete.';
        return $out;
    }

    /** Delete a stored run, project-scoped. */
    public function deleteRun(int $projectId, int $runId): bool
    {
        if (! $this->runsReady()) {
            return false;
        }
        try {
            return DB::table('research_review_run')
                ->where('id', $runId)->where('project_id', $projectId)->delete() >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Collect the project's claims as plain text lines (read-only).
     *
     * @return array<int,string>
     */
    protected function collectClaimsText(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_assertion')) {
                return [];
            }
            $rows = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->orderBy('updated_at', 'desc')
                ->limit(120)
                ->get(['subject_label', 'object_value', 'object_label', 'status']);

            $lines = [];
            foreach ($rows as $r) {
                $text = trim((string) ($r->object_value ?? $r->subject_label ?? $r->object_label ?? ''));
                if ($text === '') {
                    continue;
                }
                $status = trim((string) ($r->status ?? ''));
                $lines[] = mb_substr($text, 0, 500) . ($status !== '' ? "  [status: {$status}]" : '');
            }
            return $lines;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Build the persona-driven adversarial prompt from read-only project context. */
    protected function buildPrompt(object $project, string $brief, array $claims, string $persona): string
    {
        $personaBrief = self::PERSONA_BRIEFS[$persona] ?? self::PERSONA_BRIEFS['methodologist'];
        $title = trim((string) ($project->title ?? 'Untitled project'));

        $claimBlock = '';
        if (! empty($claims)) {
            foreach ($claims as $i => $c) {
                $claimBlock .= ($i + 1) . '. ' . $c . "\n";
            }
        } else {
            $claimBlock = "(No discrete claims recorded yet.)\n";
        }

        $briefBlock = $brief !== '' ? $brief : '(No project brief provided.)';

        $groups = implode(', ', array_keys(self::FINDING_GROUPS));

        return $personaBrief . "\n\n"
            . "You are simulating an adversarial peer reviewer for the research project below. "
            . "Be specific, critical and constructive. Do NOT invent facts about the project beyond what is given; "
            . "where information is missing, say so and treat the gap itself as a weakness.\n\n"
            . "PROJECT TITLE: {$title}\n\n"
            . "PROJECT BRIEF:\n{$briefBlock}\n\n"
            . "CLAIMS UNDER REVIEW:\n{$claimBlock}\n"
            . "Respond as STRICT JSON only (no prose outside the JSON). Use exactly these keys: "
            . "\"summary\" (a string), and arrays of short string bullet points for: {$groups}. "
            . "For \"strongest_contribution\" and \"weakest_section\" a single-item array is fine. "
            . "Keep each bullet to one or two sentences.";
    }

    /**
     * Parse the AI reply into a summary + grouped findings.
     *
     * Tries strict JSON first (the prompt requests JSON), then falls back to a
     * tolerant heuristic so a non-JSON reply still yields something usable rather
     * than an empty run. Always returns every group key (possibly empty).
     *
     * @return array{summary:string, findings:array<string,array<int,string>>}
     */
    protected function parseFindings(string $raw): array
    {
        $findings = [];
        foreach (array_keys(self::FINDING_GROUPS) as $k) {
            $findings[$k] = [];
        }
        $summary = '';

        // 1. Strict JSON (possibly wrapped in a ```json fence).
        $json = $this->extractJson($raw);
        if (is_array($json)) {
            $summary = trim((string) ($json['summary'] ?? ''));
            foreach (array_keys(self::FINDING_GROUPS) as $k) {
                $findings[$k] = $this->normaliseBullets($json[$k] ?? null);
            }
            if ($summary !== '' || $this->hasAnyFinding($findings)) {
                return ['summary' => $summary, 'findings' => $findings];
            }
        }

        // 2. Heuristic fallback: keep the whole reply as the summary so nothing
        //    is lost, and try to bucket lines under recognised headings.
        $summary = $raw;
        $current = null;
        $labelToKey = [];
        foreach (self::FINDING_GROUPS as $key => $label) {
            $labelToKey[strtolower($label)] = $key;
            $labelToKey[strtolower(str_replace('_', ' ', $key))] = $key;
        }
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $headingProbe = strtolower(rtrim(preg_replace('/[#*:\-]/', '', $trimmed)));
            $headingProbe = trim($headingProbe);
            if (isset($labelToKey[$headingProbe]) && mb_strlen($trimmed) < 60) {
                $current = $labelToKey[$headingProbe];
                continue;
            }
            if ($current !== null) {
                $bullet = ltrim($trimmed, "-*0123456789. \t");
                if ($bullet !== '') {
                    $findings[$current][] = $bullet;
                }
            }
        }

        return ['summary' => $summary, 'findings' => $findings];
    }

    /** Extract the first JSON object from a possibly fenced / chatty reply. */
    protected function extractJson(string $raw): ?array
    {
        // Strip ```json ... ``` fences if present.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $raw, $m)) {
            $candidate = $m[1];
        } elseif (preg_match('/(\{.*\})/s', $raw, $m)) {
            $candidate = $m[1];
        } else {
            return null;
        }
        $decoded = json_decode($candidate, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Normalise a findings value into an array of clean string bullets.
     *
     * @return array<int,string>
     */
    protected function normaliseBullets($value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? [] : [$value];
        }
        if (! is_array($value)) {
            return [trim((string) $value)];
        }
        $out = [];
        foreach ($value as $v) {
            if (is_array($v)) {
                $v = implode(' ', array_map('strval', $v));
            }
            $v = trim((string) $v);
            if ($v !== '') {
                $out[] = $v;
            }
        }
        return $out;
    }

    /** Whether any finding bucket has content. */
    protected function hasAnyFinding(array $findings): bool
    {
        foreach ($findings as $list) {
            if (! empty($list)) {
                return true;
            }
        }
        return false;
    }

    /** Decode a stored findings JSON column into the full group map. */
    public function decodeFindings($findings): array
    {
        $out = [];
        foreach (array_keys(self::FINDING_GROUPS) as $k) {
            $out[$k] = [];
        }
        if (is_string($findings) && trim($findings) !== '') {
            $decoded = json_decode($findings, true);
            if (is_array($decoded)) {
                foreach (array_keys(self::FINDING_GROUPS) as $k) {
                    $out[$k] = $this->normaliseBullets($decoded[$k] ?? null);
                }
            }
        } elseif (is_array($findings)) {
            foreach (array_keys(self::FINDING_GROUPS) as $k) {
                $out[$k] = $this->normaliseBullets($findings[$k] ?? null);
            }
        }
        return $out;
    }

    /**
     * Best-effort note of which gateway model answered, for the run record.
     * Read-only; returns null if it cannot be determined (the label still tells
     * the user it was the gateway, not a human).
     */
    protected function detectModel(): ?string
    {
        try {
            $svc = app(\AhgAiServices\Services\LlmService::class);
            if (method_exists($svc, 'getDefaultConfig')) {
                $cfg = $svc->getDefaultConfig();
                if ($cfg && ! empty($cfg->model)) {
                    return mb_substr((string) $cfg->model, 0, 120);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }
}
