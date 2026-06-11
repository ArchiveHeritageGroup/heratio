<?php

/**
 * ArgumentBuilderService - Service for Heratio
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
use Illuminate\Support\Facades\Schema;

/**
 * ArgumentBuilderService - Research OS Stage 12 (heratio#1229).
 *
 * Per-project argument scaffold. The researcher drags CLAIMS into an ordered
 * nine-step argument sequence and the system warns about weak spots. Claims are
 * NOT rebuilt here - they live in `research_assertion` (the Claim Ledger,
 * heratio#1223) and their evidence in `research_assertion_evidence`. A step
 * references a claim by its assertion id; the Argument Builder only owns the two
 * NEW tables `research_argument` and `research_argument_step`.
 *
 * Every read/write is Schema::hasTable-guarded and wrapped in try/catch so the
 * builder degrades to an empty canvas rather than throwing a 500 when a table is
 * missing during a partial install (reference_ci_schema_hastable).
 *
 * The warnings pass is heuristic PHP computed from the claim + evidence data -
 * no AI is required. An optional AI critique, if ever wired, must route through
 * the AHG gateway via LlmService and be labelled as AI-generated; this service
 * ships no direct node calls.
 */
class ArgumentBuilderService
{
    /**
     * The nine canonical argument slots, in their intended narrative order.
     * Stored in research_argument_step.slot as a plain VARCHAR; never a MySQL
     * ENUM. Each slot maps to a human label + a one-line prompt for the canvas.
     *
     * @var array<string,array{label:string,hint:string}>
     */
    public const SLOTS = [
        'problem'        => ['label' => 'Problem',        'hint' => 'The situation or puzzle the work addresses.'],
        'gap'            => ['label' => 'Gap',            'hint' => 'What is missing or unresolved in current knowledge.'],
        'frame'         => ['label' => 'Frame',          'hint' => 'The theoretical or conceptual lens applied.'],
        'method'         => ['label' => 'Method',         'hint' => 'How the claim is established or tested.'],
        'evidence'       => ['label' => 'Evidence',       'hint' => 'The core supported finding the case rests on.'],
        'analysis'       => ['label' => 'Analysis',       'hint' => 'What the evidence means once interpreted.'],
        'counterargument'=> ['label' => 'Counterargument','hint' => 'The strongest objection and the response to it.'],
        'contribution'   => ['label' => 'Contribution',   'hint' => 'What this adds that did not exist before.'],
        'implication'    => ['label' => 'Implication',    'hint' => 'Why it matters - consequences and next steps.'],
    ];

    /** @var array<string> Statuses that mark a claim as rejected/contested (contradiction signal). */
    public const CONTESTED_STATUSES = ['rejected', 'contested', 'disputed', 'weak'];

    /** @var array<string> Statuses that read as a low-confidence / not-yet-supported claim. */
    public const WEAK_STATUSES = ['idea', 'working', 'weak', 'needs_evidence', 'proposed', 'contested', 'rejected', 'disputed'];

    /** Slots whose conclusion should not outrun the evidence (heuristic target). */
    public const CONCLUSION_SLOTS = ['contribution', 'implication'];

    /** Confidence at/below this decimal counts as low for the over-reach heuristic. */
    public const LOW_CONFIDENCE_CEILING = 0.40;

    /** Warning severity levels surfaced in the panel (VARCHAR-style, not an enum). */
    public const SEVERITY_BADGES = [
        'danger'  => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];

    protected function argumentsReady(): bool
    {
        try {
            return Schema::hasTable('research_argument') && Schema::hasTable('research_argument_step');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function claimsReady(): bool
    {
        try {
            return Schema::hasTable('research_assertion');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // ARGUMENT (header) READ / WRITE
    // =====================================================================

    /** Fetch the single argument for a project, or null if none exists yet. */
    public function getArgument(int $projectId): ?object
    {
        if (! $this->argumentsReady()) {
            return null;
        }
        try {
            return DB::table('research_argument')
                ->where('project_id', $projectId)
                ->orderBy('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fetch the project's argument, creating an empty one on first visit so the
     * canvas always has a home. Returns null only if the tables are unavailable.
     */
    public function getOrCreateArgument(int $projectId, ?int $userId = null): ?object
    {
        $existing = $this->getArgument($projectId);
        if ($existing) {
            return $existing;
        }
        if (! $this->argumentsReady()) {
            return null;
        }
        try {
            $now = now();
            $id = DB::table('research_argument')->insertGetId([
                'project_id'     => $projectId,
                'title'          => null,
                'central_thesis' => null,
                'created_by'     => $userId,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            return DB::table('research_argument')->where('id', $id)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Update the argument title + central thesis. Returns success. */
    public function updateArgument(int $projectId, int $argumentId, array $data): bool
    {
        if (! $this->argumentsReady()) {
            return false;
        }
        try {
            return DB::table('research_argument')
                ->where('id', $argumentId)
                ->where('project_id', $projectId)
                ->update([
                    'title'          => $data['title'] ?? null,
                    'central_thesis' => $data['central_thesis'] ?? null,
                    'updated_at'     => now(),
                ]) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // STEPS READ
    // =====================================================================

    /**
     * All steps for an argument, ordered by sort_order, each enriched with its
     * attached claim (from research_assertion) and that claim's evidence count
     * + distinct-source count (from research_assertion_evidence). No claim data
     * is duplicated; it is read live from the ledger tables.
     *
     * @return array<int,object>
     */
    public function getSteps(int $argumentId): array
    {
        if (! $this->argumentsReady()) {
            return [];
        }
        try {
            $steps = DB::table('research_argument_step')
                ->where('argument_id', $argumentId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($steps->isEmpty()) {
                return [];
            }

            // Batch-load attached claims + their evidence aggregates.
            $assertionIds = $steps->pluck('assertion_id')->filter()->unique()->values()->all();
            $claims = [];
            if (! empty($assertionIds) && $this->claimsReady()) {
                $claims = DB::table('research_assertion')
                    ->whereIn('id', $assertionIds)
                    ->get()
                    ->keyBy('id');

                $evAgg = $this->evidenceAggregates($assertionIds);
                foreach ($claims as $c) {
                    $agg = $evAgg[$c->id] ?? null;
                    $c->evidence_count   = $agg->evidence_count ?? 0;
                    $c->distinct_sources = $agg->distinct_sources ?? 0;
                }
            }

            foreach ($steps as $s) {
                $s->claim = ($s->assertion_id && isset($claims[$s->assertion_id]))
                    ? $claims[$s->assertion_id]
                    : null;
            }

            return $steps->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Evidence aggregates (count + distinct source count) for a set of claims,
     * computed with a single batched GROUP BY over research_assertion_evidence.
     *
     * @param array<int> $assertionIds
     * @return array<int,object> keyed by assertion_id
     */
    protected function evidenceAggregates(array $assertionIds): array
    {
        try {
            if (empty($assertionIds) || ! Schema::hasTable('research_assertion_evidence')) {
                return [];
            }
            return DB::table('research_assertion_evidence')
                ->whereIn('assertion_id', $assertionIds)
                ->select(
                    'assertion_id',
                    DB::raw('COUNT(*) as evidence_count'),
                    DB::raw("COUNT(DISTINCT CONCAT(source_type,':',source_id)) as distinct_sources")
                )
                ->groupBy('assertion_id')
                ->get()
                ->keyBy('assertion_id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Claims available to attach: every claim (assertion) belonging to the
     * project, with a short label + evidence count, so the picker can show which
     * claims are well supported. Reuses research_assertion - claims are NOT
     * rebuilt here.
     *
     * @return array<int,object>
     */
    public function availableClaims(int $projectId): array
    {
        if (! $this->claimsReady()) {
            return [];
        }
        try {
            $rows = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->orderBy('updated_at', 'desc')
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $evAgg = $this->evidenceAggregates($rows->pluck('id')->all());
            foreach ($rows as $r) {
                $r->label = $this->claimLabel($r);
                $agg = $evAgg[$r->id] ?? null;
                $r->evidence_count   = $agg->evidence_count ?? 0;
                $r->distinct_sources = $agg->distinct_sources ?? 0;
            }
            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Human-readable one-line label for a claim/assertion row. */
    public function claimLabel(?object $claim): string
    {
        if (! $claim) {
            return '';
        }
        $text = $claim->object_value ?? $claim->subject_label ?? $claim->object_label ?? '';
        $text = trim((string) $text);
        if ($text === '') {
            $text = 'Claim #' . ($claim->id ?? '?');
        }
        return mb_substr($text, 0, 160);
    }

    // =====================================================================
    // STEPS WRITE
    // =====================================================================

    /**
     * Add a step (slot) to an argument. The slot must be one of the canonical
     * nine; an invalid slot is rejected. A claim (assertion_id) may be attached
     * now or left null. Returns the new step id, or null on failure.
     */
    public function addStep(int $projectId, int $argumentId, string $slot, ?int $assertionId = null, ?string $note = null): ?int
    {
        if (! $this->argumentsReady() || ! $this->ownsArgument($projectId, $argumentId)) {
            return null;
        }
        if (! array_key_exists($slot, self::SLOTS)) {
            return null;
        }
        try {
            $assertionId = $this->validateAssertionForProject($projectId, $assertionId);
            $next = (int) DB::table('research_argument_step')
                ->where('argument_id', $argumentId)
                ->max('sort_order');
            return DB::table('research_argument_step')->insertGetId([
                'argument_id' => $argumentId,
                'slot'        => $slot,
                'assertion_id'=> $assertionId,
                'note'        => $note,
                'sort_order'  => $next + 1,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Attach (or change) the claim on a step, validating it belongs to the project. */
    public function attachClaim(int $projectId, int $argumentId, int $stepId, ?int $assertionId): bool
    {
        if (! $this->argumentsReady() || ! $this->ownsArgument($projectId, $argumentId)) {
            return false;
        }
        try {
            $assertionId = $this->validateAssertionForProject($projectId, $assertionId);
            return DB::table('research_argument_step')
                ->where('id', $stepId)
                ->where('argument_id', $argumentId)
                ->update(['assertion_id' => $assertionId]) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Update a step's free-text note. */
    public function updateStepNote(int $projectId, int $argumentId, int $stepId, ?string $note): bool
    {
        if (! $this->argumentsReady() || ! $this->ownsArgument($projectId, $argumentId)) {
            return false;
        }
        try {
            return DB::table('research_argument_step')
                ->where('id', $stepId)
                ->where('argument_id', $argumentId)
                ->update(['note' => $note]) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Remove a step from the argument. */
    public function removeStep(int $projectId, int $argumentId, int $stepId): bool
    {
        if (! $this->argumentsReady() || ! $this->ownsArgument($projectId, $argumentId)) {
            return false;
        }
        try {
            DB::table('research_argument_step')
                ->where('id', $stepId)
                ->where('argument_id', $argumentId)
                ->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reorder steps from an ordered list of step ids. Each id's position becomes
     * its new sort_order. Ids not belonging to the argument are ignored.
     *
     * @param array<int> $orderedStepIds
     */
    public function reorderSteps(int $projectId, int $argumentId, array $orderedStepIds): bool
    {
        if (! $this->argumentsReady() || ! $this->ownsArgument($projectId, $argumentId)) {
            return false;
        }
        try {
            $valid = DB::table('research_argument_step')
                ->where('argument_id', $argumentId)
                ->pluck('id')
                ->all();
            $valid = array_flip($valid);
            $pos = 0;
            foreach ($orderedStepIds as $stepId) {
                $stepId = (int) $stepId;
                if (! isset($valid[$stepId])) {
                    continue;
                }
                $pos++;
                DB::table('research_argument_step')
                    ->where('id', $stepId)
                    ->where('argument_id', $argumentId)
                    ->update(['sort_order' => $pos]);
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Guard: does this argument belong to this project? */
    protected function ownsArgument(int $projectId, int $argumentId): bool
    {
        try {
            return DB::table('research_argument')
                ->where('id', $argumentId)
                ->where('project_id', $projectId)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Return the assertion id only if it belongs to the project, else null. */
    protected function validateAssertionForProject(int $projectId, ?int $assertionId): ?int
    {
        if ($assertionId === null || $assertionId <= 0 || ! $this->claimsReady()) {
            return null;
        }
        try {
            $ok = DB::table('research_assertion')
                ->where('id', $assertionId)
                ->where('project_id', $projectId)
                ->exists();
            return $ok ? $assertionId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =====================================================================
    // WARNINGS PASS - heuristic PHP over the claim + evidence data.
    //
    // No AI. Each warning is [severity, slot|null, message]. The set:
    //  1. step whose claim has NO evidence (LEFT JOIN research_assertion_evidence)
    //  2. a slot over-citing one author/source (>=2 refs, 1 distinct source)
    //  3. a missing slot in the nine-step chain
    //  4. a claim that contradicts another (rejected/contested used as a signal)
    //  5. conclusion stronger than evidence (contribution/implication slot
    //     referencing weak / low-confidence claims)
    // =====================================================================

    /**
     * Compute every warning for an argument's current steps.
     *
     * @param array<int,object> $steps Output of getSteps() (claims pre-attached).
     * @return array<int,array{severity:string,slot:?string,message:string}>
     */
    public function computeWarnings(array $steps): array
    {
        $warnings = [];

        // 3. Missing slots in the nine-step chain.
        $present = [];
        foreach ($steps as $s) {
            $present[$s->slot] = true;
        }
        $missing = [];
        foreach (self::SLOTS as $slot => $meta) {
            if (empty($present[$slot])) {
                $missing[] = $meta['label'];
            }
        }
        if (! empty($missing)) {
            $warnings[] = [
                'severity' => 'info',
                'slot'     => null,
                'message'  => 'Argument is missing ' . count($missing) . ' of the nine steps: '
                    . implode(', ', $missing) . '.',
            ];
        }

        // Track claims per slot for contradiction detection (4).
        $usedAssertionIds = [];

        foreach ($steps as $s) {
            $slotLabel = self::SLOTS[$s->slot]['label'] ?? ucfirst((string) $s->slot);
            $claim = $s->claim ?? null;

            if ($claim) {
                $usedAssertionIds[] = (int) $claim->id;
            }

            // A populated slot with no claim attached at all.
            if (! $claim) {
                $warnings[] = [
                    'severity' => 'warning',
                    'slot'     => $s->slot,
                    'message'  => "The '{$slotLabel}' step has no claim attached. Drag a claim from the ledger to support it.",
                ];
                continue;
            }

            $evidenceCount   = (int) ($claim->evidence_count ?? 0);
            $distinctSources = (int) ($claim->distinct_sources ?? 0);
            $status          = strtolower(trim((string) ($claim->status ?? '')));
            $confidence      = $claim->confidence;

            // 1. Step whose claim has NO evidence.
            if ($evidenceCount === 0) {
                $warnings[] = [
                    'severity' => 'danger',
                    'slot'     => $s->slot,
                    'message'  => "The '{$slotLabel}' claim has no evidence attached. An uncited claim cannot carry the argument.",
                ];
            }

            // 2. Slot over-citing one author/source.
            if ($evidenceCount >= 2 && $distinctSources === 1) {
                $warnings[] = [
                    'severity' => 'warning',
                    'slot'     => $s->slot,
                    'message'  => "The '{$slotLabel}' claim leans on a single source across {$evidenceCount} citations. Broaden the support.",
                ];
            }

            // 4. A claim that contradicts another (rejected/contested as signal).
            if (in_array($status, self::CONTESTED_STATUSES, true)) {
                $warnings[] = [
                    'severity' => 'danger',
                    'slot'     => $s->slot,
                    'message'  => "The '{$slotLabel}' claim is marked '{$status}', which signals a contradiction or dispute. Resolve it before relying on it.",
                ];
            }

            // 5. Conclusion stronger than evidence.
            if (in_array($s->slot, self::CONCLUSION_SLOTS, true)) {
                $lowConfidence = ($confidence !== null && (float) $confidence <= self::LOW_CONFIDENCE_CEILING);
                $weakStatus    = in_array($status, self::WEAK_STATUSES, true);
                if ($lowConfidence || $weakStatus || $evidenceCount === 0) {
                    $why = [];
                    if ($evidenceCount === 0) { $why[] = 'no evidence'; }
                    if ($lowConfidence)       { $why[] = 'low confidence'; }
                    if ($weakStatus)          { $why[] = "status '{$status}'"; }
                    $warnings[] = [
                        'severity' => 'warning',
                        'slot'     => $s->slot,
                        'message'  => "The '{$slotLabel}' conclusion rests on a weak claim (" . implode(', ', $why)
                            . "). The conclusion may be stronger than the evidence supports.",
                    ];
                }
            }
        }

        // 4 (cont). The same claim contradicting itself across slots is benign,
        // but a claim reused while ANOTHER claim in the argument is rejected/
        // contested is surfaced once at argument level.
        $contested = [];
        foreach ($steps as $s) {
            $claim = $s->claim ?? null;
            if ($claim && in_array(strtolower(trim((string) ($claim->status ?? ''))), self::CONTESTED_STATUSES, true)) {
                $contested[(int) $claim->id] = $this->claimLabel($claim);
            }
        }
        if (count($contested) > 0 && count($usedAssertionIds) > count($contested)) {
            $warnings[] = [
                'severity' => 'info',
                'slot'     => null,
                'message'  => 'This argument mixes ' . count($contested)
                    . ' contested/rejected claim(s) with supported ones. Check the chain does not contradict itself.',
            ];
        }

        return $warnings;
    }
}
