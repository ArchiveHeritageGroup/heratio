<?php

/**
 * ResearchMilestoneService - Heratio ahg-research
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

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1222 - Research OS: Research Milestones & Deliverables tracker.
 *
 * The PLAN register for a research project: the milestones and deliverables the
 * project intends to reach, each with a due date, a status and a progress
 * percentage, so a project plan is documented alongside its DMP, outputs, ethics,
 * funding and team. A milestone is a planned point in the work (a decision point,
 * a review, a dissemination event) and a deliverable is a tangible output the
 * plan commits to producing; both share a single type taxonomy. This tracks the
 * intended schedule of the work and is DISTINCT from the Research Outputs
 * register, which records outputs that have actually been produced.
 *
 * Mirrors ResearchTeamService exactly: scoped to a project, dropdown-backed
 * taxonomies (never ENUM), a machine-readable JSON export, and a per-project
 * summary. Every read is Schema::hasTable-guarded and try/catch-wrapped so a
 * partial install degrades cleanly rather than 500ing. No live writes outside
 * the one NEW research_milestone table; no ALTER of any existing table.
 *
 * International and jurisdiction-neutral: NO country, institution or funding
 * regime is assumed or defaulted. Dates are plain calendar dates; the title and
 * deliverable are free-text DATA.
 */
class ResearchMilestoneService
{
    public const TYPE_TAXONOMY   = 'milestone_type';
    public const STATUS_TAXONOMY = 'milestone_status';

    /** Statuses that close a milestone, so it is no longer overdue or due-soon. */
    public const CLOSED_STATUSES = ['completed', 'cancelled'];

    /** Days ahead within which an open milestone is flagged "due soon". */
    public const DUE_SOON_DAYS = 30;

    // ---------------------------------------------------------------------
    // Milestones (CRUD)
    // ---------------------------------------------------------------------

    /**
     * Milestones on a project, ordered by due date (soonest first), with undated
     * items last, then by id. Each row carries the derived overdue / due-soon
     * flags and a resolved type+status label.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listMilestones(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_milestone')) {
                return [];
            }

            $rows = DB::table('research_milestone')
                ->where('project_id', $projectId)
                // Undated milestones sort to the end; dated ones soonest-first.
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->get();

            return $rows->map(fn ($r) => $this->rowToArray($r))->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A single milestone as an array, scoped to its project, or null. */
    public function getMilestone(int $id, ?int $projectId = null): ?array
    {
        try {
            if (! Schema::hasTable('research_milestone')) {
                return null;
            }
            $q = DB::table('research_milestone')->where('id', $id);
            if ($projectId !== null) {
                $q->where('project_id', $projectId);
            }
            $row = $q->first();

            return $row ? $this->rowToArray($row) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create a milestone for a project. Returns the new id, or null on failure.
     *
     * @param  array<string,mixed>  $data
     */
    public function createMilestone(int $projectId, ?int $researcherId, array $data): ?int
    {
        try {
            if (! Schema::hasTable('research_milestone')) {
                return null;
            }

            $now = now();
            $row = array_merge($this->normalise($data), [
                'project_id' => $projectId,
                'owner_id'   => $researcherId,
                'created_by' => $researcherId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return (int) DB::table('research_milestone')->insertGetId($row);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] milestone createMilestone failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Update a milestone, scoped to its project.
     *
     * @param  array<string,mixed>  $data
     */
    public function updateMilestone(int $id, int $projectId, array $data): bool
    {
        try {
            if (! Schema::hasTable('research_milestone')) {
                return false;
            }
            $owns = DB::table('research_milestone')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }

            $row = array_merge($this->normalise($data), ['updated_at' => now()]);
            DB::table('research_milestone')->where('id', $id)->where('project_id', $projectId)->update($row);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] milestone updateMilestone failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Delete a milestone, scoped to its project. */
    public function deleteMilestone(int $id, int $projectId): bool
    {
        try {
            if (! Schema::hasTable('research_milestone')) {
                return false;
            }
            $owns = DB::table('research_milestone')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }
            DB::table('research_milestone')->where('id', $id)->where('project_id', $projectId)->delete();

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] milestone deleteMilestone failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Coerce validated request data into a writeable column map. Dropdown-backed
     * values (milestone_type, status) are constrained to their known option codes;
     * free-text is trimmed and length-capped; progress is clamped to 0-100. When
     * the status is "completed" and no completed_date was supplied, today's date
     * is recorded so the plan stays coherent.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function normalise(array $data): array
    {
        $type = (string) ($data['milestone_type'] ?? 'milestone');
        if (! array_key_exists($type, $this->typeOptions())) {
            $type = 'milestone';
        }

        $status = (string) ($data['status'] ?? 'planned');
        if (! array_key_exists($status, $this->statusOptions())) {
            $status = 'planned';
        }

        $progress = (int) ($data['progress_pct'] ?? 0);
        $progress = max(0, min(100, $progress));
        // A completed milestone is, by definition, fully progressed.
        if ($status === 'completed') {
            $progress = 100;
        }

        $completed = $this->dateOrNull($data['completed_date'] ?? null);
        if ($status === 'completed' && $completed === null) {
            $completed = now()->toDateString();
        }

        return [
            'title'          => mb_substr(trim((string) ($data['title'] ?? '')), 0, 512),
            'milestone_type' => $type,
            'description'    => isset($data['description']) && trim((string) $data['description']) !== ''
                ? mb_substr((string) $data['description'], 0, 65000) : null,
            'due_date'       => $this->dateOrNull($data['due_date'] ?? null),
            'completed_date' => $completed,
            'status'         => $status,
            'progress_pct'   => $progress,
            'deliverable'    => $this->trimOrNull($data['deliverable'] ?? null, 512),
        ];
    }

    private function trimOrNull(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---------------------------------------------------------------------
    // Schedule flags (overdue / due soon) - derived, never stored
    // ---------------------------------------------------------------------

    /**
     * Is this milestone overdue? True when it has a due date in the past AND its
     * status is not one that closes it (completed / cancelled). Derived purely
     * from the row, never persisted, so it is always current.
     *
     * @param  array<string,mixed>  $m
     */
    public function isOverdue(array $m): bool
    {
        $due    = (string) ($m['due_date'] ?? '');
        $status = (string) ($m['status'] ?? '');
        if ($due === '' || in_array($status, self::CLOSED_STATUSES, true)) {
            return false;
        }
        try {
            return Carbon::parse($due)->startOfDay()->isPast()
                && ! Carbon::parse($due)->isToday();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Is this milestone "due soon"? True when it has a due date that is today or
     * within DUE_SOON_DAYS in the future, it is not already overdue, and its
     * status is still open (not completed / cancelled).
     *
     * @param  array<string,mixed>  $m
     */
    public function isDueSoon(array $m): bool
    {
        $due    = (string) ($m['due_date'] ?? '');
        $status = (string) ($m['status'] ?? '');
        if ($due === '' || in_array($status, self::CLOSED_STATUSES, true)) {
            return false;
        }
        if ($this->isOverdue($m)) {
            return false;
        }
        try {
            $date  = Carbon::parse($due)->startOfDay();
            $today = Carbon::today();

            return $date->gte($today) && $date->lte($today->copy()->addDays(self::DUE_SOON_DAYS));
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Per-project summary (counts by status; progress; overdue; next upcoming)
    // ---------------------------------------------------------------------

    /**
     * Summary of a project's plan: total milestone count, counts by status and by
     * type, the overall progress percentage, the overdue count (a warning when
     * non-zero), the due-soon count, the number completed, and the next upcoming
     * milestone (the soonest-due open item). Computed from the in-memory list so
     * it does not re-query per metric.
     *
     * @return array{total:int,completed:int,overdue:int,due_soon:int,progress_pct:int,by_status:array<int,array{code:string,label:string,count:int}>,by_type:array<int,array{code:string,label:string,count:int}>,next:?array<string,mixed>}
     */
    public function summary(int $projectId): array
    {
        $empty = [
            'total' => 0, 'completed' => 0, 'overdue' => 0, 'due_soon' => 0,
            'progress_pct' => 0, 'by_status' => [], 'by_type' => [], 'next' => null,
        ];
        try {
            if (! Schema::hasTable('research_milestone')) {
                return $empty;
            }

            return $this->summaryFromMilestones($this->listMilestones($projectId));
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * Per-project summary from an in-memory milestone list (used by both summary()
     * and the export so neither re-queries).
     *
     * Overall progress is the mean of every milestone's progress_pct, rounded to a
     * whole percent. Completed milestones already read 100 (enforced in
     * normalise()), so a project that is all-done reads 100%.
     *
     * The next upcoming milestone is the soonest-due item that is still open (not
     * completed / cancelled) and has a due date; the list is already due-date
     * ordered, so it is the first such row.
     *
     * @param  array<int,array<string,mixed>>  $milestones
     * @return array{total:int,completed:int,overdue:int,due_soon:int,progress_pct:int,by_status:array<int,array{code:string,label:string,count:int}>,by_type:array<int,array{code:string,label:string,count:int}>,next:?array<string,mixed>}
     */
    private function summaryFromMilestones(array $milestones): array
    {
        $statusLabels = $this->statusOptions();
        $typeLabels   = $this->typeOptions();

        $statusCounts = [];
        $typeCounts   = [];
        $completed    = 0;
        $overdue      = 0;
        $dueSoon      = 0;
        $progressSum  = 0;
        $next         = null;

        foreach ($milestones as $m) {
            $status = (string) ($m['status'] ?? '');
            $type   = (string) ($m['milestone_type'] ?? '');
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $typeCounts[$type]     = ($typeCounts[$type] ?? 0) + 1;

            $progressSum += (int) ($m['progress_pct'] ?? 0);

            if ($status === 'completed') {
                $completed++;
            }
            if (! empty($m['is_overdue'])) {
                $overdue++;
            }
            if (! empty($m['is_due_soon'])) {
                $dueSoon++;
            }

            // First open, dated milestone in due-date order is the next upcoming.
            if ($next === null
                && (string) ($m['due_date'] ?? '') !== ''
                && ! in_array($status, self::CLOSED_STATUSES, true)) {
                $next = [
                    'id'           => (int) ($m['id'] ?? 0),
                    'title'        => (string) ($m['title'] ?? ''),
                    'due_date'     => (string) ($m['due_date'] ?? ''),
                    'status'       => $status,
                    'status_label' => $statusLabels[$status] ?? $status,
                    'is_overdue'   => ! empty($m['is_overdue']),
                    'is_due_soon'  => ! empty($m['is_due_soon']),
                ];
            }
        }

        $total = count($milestones);

        return [
            'total'        => $total,
            'completed'    => $completed,
            'overdue'      => $overdue,
            'due_soon'     => $dueSoon,
            'progress_pct' => $total > 0 ? (int) round($progressSum / $total) : 0,
            'by_status'    => $this->labelCounts($statusCounts, $statusLabels),
            'by_type'      => $this->labelCounts($typeCounts, $typeLabels),
            'next'         => $next,
        ];
    }

    /**
     * Order a code=>count map by a label taxonomy, attaching labels. Any orphan
     * code not in the taxonomy still surfaces with a humanised label.
     *
     * @param  array<string,int>  $counts
     * @param  array<string,string>  $labels
     * @return array<int,array{code:string,label:string,count:int}>
     */
    private function labelCounts(array $counts, array $labels): array
    {
        $out = [];
        foreach ($labels as $code => $label) {
            if (isset($counts[$code])) {
                $out[] = ['code' => (string) $code, 'label' => (string) $label, 'count' => (int) $counts[$code]];
            }
        }
        foreach ($counts as $code => $c) {
            if (! isset($labels[$code])) {
                $out[] = ['code' => (string) $code, 'label' => ucfirst(str_replace('_', ' ', (string) $code)), 'count' => (int) $c];
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // Machine-readable export
    // ---------------------------------------------------------------------

    /**
     * Build a machine-readable export of a project's milestones. Each entry
     * carries the title, type (code + human label), description, the due and
     * completed dates, the status (code + label), the progress percentage, the
     * deliverable, and the derived overdue / due-soon flags. The shape is a
     * top-level object with a "project" block, a generated_at timestamp, a
     * "count", a "summary" block (counts by status/type, overall progress, overdue
     * and due-soon counts, the next upcoming milestone) and a "milestones" array.
     *
     * @param  array<int,array<string,mixed>>  $milestones
     * @return array<string,mixed>
     */
    public function buildExport(array $milestones, ?object $project = null): array
    {
        $typeLabels   = $this->typeOptions();
        $statusLabels = $this->statusOptions();

        $items = [];
        foreach ($milestones as $m) {
            $type   = (string) ($m['milestone_type'] ?? '');
            $status = (string) ($m['status'] ?? '');
            $items[] = [
                'id'             => (int) ($m['id'] ?? 0),
                'title'          => (string) ($m['title'] ?? ''),
                'milestone_type' => $type,
                'type_label'     => $typeLabels[$type] ?? $type,
                'description'    => (string) ($m['description'] ?? ''),
                'due_date'       => ($m['due_date'] ?? '') !== '' ? (string) $m['due_date'] : null,
                'completed_date' => ($m['completed_date'] ?? '') !== '' ? (string) $m['completed_date'] : null,
                'status'         => $status,
                'status_label'   => $statusLabels[$status] ?? $status,
                'progress_pct'   => (int) ($m['progress_pct'] ?? 0),
                'deliverable'    => ($m['deliverable'] ?? '') !== '' ? (string) $m['deliverable'] : null,
                'is_overdue'     => ! empty($m['is_overdue']),
                'is_due_soon'    => ! empty($m['is_due_soon']),
            ];
        }

        $summary = $this->summaryFromMilestones($milestones);

        return [
            'project' => [
                'id'    => isset($project->id) ? (int) $project->id : null,
                'title' => isset($project->title) ? (string) $project->title : '',
            ],
            'generated_at' => now()->toIso8601String(),
            'count'        => count($items),
            'summary'      => [
                'total'        => $summary['total'],
                'completed'    => $summary['completed'],
                'overdue'      => $summary['overdue'],
                'due_soon'     => $summary['due_soon'],
                'progress_pct' => $summary['progress_pct'],
                'by_status'    => $summary['by_status'],
                'by_type'      => $summary['by_type'],
                'next'         => $summary['next'],
            ],
            'milestones' => $items,
        ];
    }

    // ---------------------------------------------------------------------
    // Dropdown-backed taxonomies (Dropdown Manager - never ENUM)
    // ---------------------------------------------------------------------

    /**
     * Milestone-type options [code => label], with a safe fallback. A milestone is
     * a planned point in the work; a deliverable is a tangible output the plan
     * commits to; the remaining codes are common project-plan event types - never
     * a hardcoded <option> list in a view.
     *
     * @return array<string,string>
     */
    public function typeOptions(): array
    {
        return $this->dropdownOptions(self::TYPE_TAXONOMY, [
            'milestone'      => 'Milestone',
            'deliverable'    => 'Deliverable',
            'decision_point' => 'Decision point',
            'review'         => 'Review',
            'dissemination'  => 'Dissemination',
            'other'          => 'Other',
        ]);
    }

    /**
     * Status options [code => label], with a safe fallback.
     *
     * @return array<string,string>
     */
    public function statusOptions(): array
    {
        return $this->dropdownOptions(self::STATUS_TAXONOMY, [
            'planned'     => 'Planned',
            'in_progress' => 'In progress',
            'completed'   => 'Completed',
            'delayed'     => 'Delayed',
            'cancelled'   => 'Cancelled',
        ]);
    }

    /** Generic dropdown reader [code => label] with a fallback map. */
    private function dropdownOptions(string $taxonomy, array $fallback): array
    {
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return $fallback;
            }
            $rows = DB::table('ahg_dropdown')
                ->where('taxonomy', $taxonomy)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get(['code', 'label']);

            if ($rows->isEmpty()) {
                return $fallback;
            }

            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r->code] = (string) $r->label;
            }

            return $out;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Map a DB row to an array, attaching the derived overdue / due-soon flags so
     * the list, summary and export all share one source of truth for them.
     *
     * @return array<string,mixed>
     */
    private function rowToArray(object $r): array
    {
        $m = [
            'id'             => (int) $r->id,
            'project_id'     => (int) $r->project_id,
            'title'          => (string) $r->title,
            'milestone_type' => (string) $r->milestone_type,
            'description'    => $r->description !== null ? (string) $r->description : '',
            'due_date'       => $r->due_date !== null ? (string) $r->due_date : '',
            'completed_date' => $r->completed_date !== null ? (string) $r->completed_date : '',
            'status'         => (string) $r->status,
            'progress_pct'   => (int) $r->progress_pct,
            'deliverable'    => $r->deliverable !== null ? (string) $r->deliverable : '',
            'owner_id'       => $r->owner_id !== null ? (int) $r->owner_id : null,
            'created_by'     => $r->created_by !== null ? (int) $r->created_by : null,
            'created_at'     => (string) ($r->created_at ?? ''),
            'updated_at'     => (string) ($r->updated_at ?? ''),
        ];

        $m['is_overdue']  = $this->isOverdue($m);
        $m['is_due_soon'] = $this->isDueSoon($m);

        return $m;
    }
}
