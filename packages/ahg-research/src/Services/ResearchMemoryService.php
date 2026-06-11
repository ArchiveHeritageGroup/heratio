<?php

/**
 * ResearchMemoryService - Heratio ahg-research
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
 * heratio#1233 - Research OS Stage 16: Research Memory.
 *
 * Retains the researcher's intellectual memory after a project so the next one
 * starts smarter. A project leaves behind more than findings: unresolved
 * questions, future-article ideas, sources gathered but never used, abandoned
 * hypotheses, reusable datasets, and collaboration / conference / grant leads.
 * This service curates those as memory items (the only writes), suggests
 * candidates read-only from existing artefacts (e.g. the Decision Log), and
 * aggregates open / carried-forward items across all of a researcher's projects
 * so a new project can start from them.
 *
 * Every query is Schema::hasTable-guarded and wrapped in try/catch so the
 * feature degrades to an empty state rather than ever throwing a 500. The only
 * existing table this slice reads from for suggestions is research_decision_log,
 * strictly read-only. No ALTER, no writes to any existing table.
 */
class ResearchMemoryService
{
    public const TABLE = 'research_memory_item';

    /**
     * Canonical kind options. kind is a VARCHAR holding one of these codes -
     * never a MySQL ENUM. The authoritative source may later be an ahg_dropdown
     * taxonomy 'research_memory_kind'; this constant is the resilient fallback
     * used when the dropdown is absent.
     *
     * @var array<string,array{label:string,color:string,icon:string}>
     */
    public const FALLBACK_KINDS = [
        'unresolved_question'   => ['label' => 'Unresolved question',   'color' => '#0d6efd', 'icon' => 'question-circle'],
        'future_article'        => ['label' => 'Future article',        'color' => '#6610f2', 'icon' => 'pen-fancy'],
        'unused_source'         => ['label' => 'Unused source',         'color' => '#fd7e14', 'icon' => 'box-archive'],
        'abandoned_hypothesis'  => ['label' => 'Abandoned hypothesis',  'color' => '#dc3545', 'icon' => 'lightbulb'],
        'reusable_dataset'      => ['label' => 'Reusable dataset',      'color' => '#20c997', 'icon' => 'database'],
        'collaboration'         => ['label' => 'Collaboration lead',    'color' => '#198754', 'icon' => 'people-group'],
        'conference'            => ['label' => 'Conference',            'color' => '#0dcaf0', 'icon' => 'chalkboard-user'],
        'grant'                 => ['label' => 'Grant opportunity',     'color' => '#ffc107', 'icon' => 'sack-dollar'],
        'other'                 => ['label' => 'Other',                'color' => '#6c757d', 'icon' => 'circle-dot'],
    ];

    /**
     * Canonical status options. status is a VARCHAR (never a MySQL ENUM).
     *
     * @var array<string,array{label:string,color:string}>
     */
    public const FALLBACK_STATUSES = [
        'open'            => ['label' => 'Open',            'color' => '#0d6efd'],
        'carried_forward' => ['label' => 'Carried forward', 'color' => '#6610f2'],
        'done'            => ['label' => 'Done',            'color' => '#198754'],
        'dropped'         => ['label' => 'Dropped',         'color' => '#6c757d'],
    ];

    /**
     * The kind options as {code => [label,color,icon]}, sourced from the
     * ahg_dropdown taxonomy 'research_memory_kind' when present, otherwise from
     * the canonical fallback list. Never throws.
     *
     * @return array<string,array{label:string,color:string,icon:string}>
     */
    public function kinds(): array
    {
        try {
            if (Schema::hasTable('ahg_dropdown')) {
                $rows = DB::table('ahg_dropdown')
                    ->where('taxonomy', 'research_memory_kind')
                    ->where('is_active', 1)
                    ->orderBy('sort_order')
                    ->get(['code', 'label', 'color', 'icon']);

                if ($rows->isNotEmpty()) {
                    $out = [];
                    foreach ($rows as $r) {
                        $out[$r->code] = [
                            'label' => $r->label ?: ucfirst(str_replace('_', ' ', (string) $r->code)),
                            'color' => $r->color ?: '#6c757d',
                            'icon'  => $r->icon ?: 'circle-dot',
                        ];
                    }
                    return $out;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to the canonical fallback list.
        }

        return self::FALLBACK_KINDS;
    }

    /** Valid kind codes for validation. @return array<int,string> */
    public function kindCodes(): array
    {
        return array_keys($this->kinds());
    }

    /**
     * Resolve a kind code to its display meta, tolerating unknown codes.
     *
     * @return array{label:string,color:string,icon:string}
     */
    public function kindMeta(?string $code): array
    {
        $kinds = $this->kinds();
        if ($code !== null && isset($kinds[$code])) {
            return $kinds[$code];
        }

        return [
            'label' => $code ? ucfirst(str_replace('_', ' ', $code)) : 'Other',
            'color' => '#6c757d',
            'icon'  => 'circle-dot',
        ];
    }

    /** The status options as {code => [label,color]}. @return array<string,array{label:string,color:string}> */
    public function statuses(): array
    {
        return self::FALLBACK_STATUSES;
    }

    /** Valid status codes for validation. @return array<int,string> */
    public function statusCodes(): array
    {
        return array_keys($this->statuses());
    }

    /**
     * Resolve a status code to its display meta, tolerating unknown codes.
     *
     * @return array{label:string,color:string}
     */
    public function statusMeta(?string $code): array
    {
        $statuses = $this->statuses();
        if ($code !== null && isset($statuses[$code])) {
            return $statuses[$code];
        }

        return [
            'label' => $code ? ucfirst(str_replace('_', ' ', $code)) : 'Open',
            'color' => '#6c757d',
        ];
    }

    // =========================================================================
    // Per-project memory items (curated - the only writes)
    // =========================================================================

    /**
     * List a project's memory items grouped by kind, newest first within each
     * group. Returns {kindCode => array<object>}; empty array on any failure.
     *
     * @return array<string,array<int,object>>
     */
    public function groupedForProject(int $projectId): array
    {
        $items = $this->listForProject($projectId);

        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item->kind][] = $item;
        }

        return $grouped;
    }

    /**
     * Flat list of a project's memory items, newest first. Empty on failure.
     *
     * @return array<int,object>
     */
    public function listForProject(int $projectId): array
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return [];
            }

            return DB::table(self::TABLE)
                ->where('project_id', $projectId)
                ->orderByRaw('COALESCE(updated_at, created_at) DESC')
                ->orderBy('id', 'desc')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Fetch one item scoped to its researcher, or null. */
    public function findForResearcher(int $researcherId, int $id): ?object
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return null;
            }

            return DB::table(self::TABLE)
                ->where('researcher_id', $researcherId)
                ->where('id', $id)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create a memory item. Returns the new id, or null on failure.
     *
     * @param array{kind?:string,title?:string,body?:string,source_ref?:string,status?:string,created_by?:string} $data
     */
    public function create(int $researcherId, ?int $projectId, array $data): ?int
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return null;
            }

            $now = now();

            return (int) DB::table(self::TABLE)->insertGetId([
                'researcher_id' => $researcherId,
                'project_id'    => $projectId,
                'kind'          => $this->normaliseKind($data['kind'] ?? 'other'),
                'title'         => mb_substr((string) ($data['title'] ?? ''), 0, 500),
                'body'          => $data['body'] ?? null,
                'source_ref'    => isset($data['source_ref']) && $data['source_ref'] !== ''
                    ? mb_substr((string) $data['source_ref'], 0, 500) : null,
                'status'        => $this->normaliseStatus($data['status'] ?? 'open'),
                'created_by'    => isset($data['created_by']) ? mb_substr((string) $data['created_by'], 0, 255) : null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Update an item scoped to its researcher. Returns true on success.
     *
     * @param array{kind?:string,title?:string,body?:string,source_ref?:string,status?:string} $data
     */
    public function update(int $researcherId, int $id, array $data): bool
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return false;
            }

            $update = ['updated_at' => now()];

            if (array_key_exists('kind', $data)) {
                $update['kind'] = $this->normaliseKind($data['kind'] ?? 'other');
            }
            if (array_key_exists('title', $data)) {
                $update['title'] = mb_substr((string) $data['title'], 0, 500);
            }
            if (array_key_exists('body', $data)) {
                $update['body'] = $data['body'] ?: null;
            }
            if (array_key_exists('source_ref', $data)) {
                $update['source_ref'] = $data['source_ref'] ? mb_substr((string) $data['source_ref'], 0, 500) : null;
            }
            if (array_key_exists('status', $data)) {
                $update['status'] = $this->normaliseStatus($data['status'] ?? 'open');
            }

            return DB::table(self::TABLE)
                ->where('researcher_id', $researcherId)
                ->where('id', $id)
                ->update($update) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Set just the status of an item scoped to its researcher. Returns true on
     * success. Used by the quick status toggles (carry forward / done / drop).
     */
    public function setStatus(int $researcherId, int $id, string $status): bool
    {
        return $this->update($researcherId, $id, ['status' => $status]);
    }

    /** Delete an item scoped to its researcher. Returns true on success. */
    public function delete(int $researcherId, int $id): bool
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return false;
            }

            return DB::table(self::TABLE)
                ->where('researcher_id', $researcherId)
                ->where('id', $id)
                ->delete() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =========================================================================
    // Suggestions (read-only over existing artefacts)
    // =========================================================================

    /**
     * Suggest candidate memory items for a project, read read-only from existing
     * artefacts. Today the source is the Decision Log: unresolved questions and
     * rejected / abandoned hypotheses are obvious "future work" signals worth
     * carrying forward. Each suggestion carries a stable signature so already
     * accepted ones can be filtered out (accepting = writing one memory item).
     *
     * The shape mirrors a memory item so the view + the accept path can treat
     * both uniformly: {kind, title, body, source_ref, origin, signature}.
     *
     * NEVER writes. Empty array on any failure or missing source table.
     *
     * @return array<int,array{kind:string,title:string,body:?string,source_ref:?string,origin:string,signature:string}>
     */
    public function suggestionsForProject(int $projectId): array
    {
        $suggestions = [];

        // --- Source: research_decision_log (read-only) -----------------------
        try {
            if (Schema::hasTable('research_decision_log')) {
                $rows = DB::table('research_decision_log')
                    ->where('project_id', $projectId)
                    ->whereIn('decision_type', [
                        'question_reformulation',
                        'hypothesis_revision',
                        'exclusion',
                    ])
                    ->orderByRaw('COALESCE(decided_at, created_at) DESC')
                    ->limit(50)
                    ->get();

                foreach ($rows as $r) {
                    // Map a decision type to a future-work memory kind.
                    $kind = match ($r->decision_type) {
                        'hypothesis_revision' => 'abandoned_hypothesis',
                        'exclusion'           => 'unused_source',
                        default               => 'unresolved_question',
                    };

                    $title = trim((string) ($r->summary ?? ''));
                    if ($title === '') {
                        continue;
                    }

                    $suggestions[] = [
                        'kind'       => $kind,
                        'title'      => mb_substr($title, 0, 500),
                        'body'       => $r->reason ?? null,
                        'source_ref' => 'decision-log#' . (int) $r->id
                            . (! empty($r->related_ref) ? ' (' . mb_substr((string) $r->related_ref, 0, 200) . ')' : ''),
                        'origin'     => 'Decision Log',
                        'signature'  => 'decision-log#' . (int) $r->id,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Ignore this source on error.
        }

        // Drop suggestions already accepted into this project's memory (matched
        // on the stable signature stored in source_ref).
        $accepted = $this->acceptedSignatures($projectId);
        if ($accepted !== []) {
            $suggestions = array_values(array_filter(
                $suggestions,
                static fn (array $s) => ! in_array($s['signature'], $accepted, true)
            ));
        }

        return $suggestions;
    }

    /**
     * Look up a single suggestion by its signature so the accept action can
     * persist exactly what the researcher saw. Returns the suggestion shape or
     * null when it is no longer present. Read-only.
     *
     * @return array{kind:string,title:string,body:?string,source_ref:?string,origin:string,signature:string}|null
     */
    public function findSuggestion(int $projectId, string $signature): ?array
    {
        foreach ($this->suggestionsForProject($projectId) as $s) {
            if ($s['signature'] === $signature) {
                return $s;
            }
        }

        return null;
    }

    /**
     * Signatures already accepted into this project's memory, recognised by a
     * "signature" token stored at the head of source_ref on accept.
     *
     * @return array<int,string>
     */
    private function acceptedSignatures(int $projectId): array
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return [];
            }

            $rows = DB::table(self::TABLE)
                ->where('project_id', $projectId)
                ->whereNotNull('source_ref')
                ->pluck('source_ref');

            $out = [];
            foreach ($rows as $ref) {
                $ref = (string) $ref;
                // The accept path stores the bare signature at the start of
                // source_ref (it equals the suggestion's own source_ref head).
                if (preg_match('/^(decision-log#\d+)/', $ref, $m)) {
                    $out[] = $m[1];
                }
            }

            return array_values(array_unique($out));
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // Cross-project carry-forward aggregate
    // =========================================================================

    /**
     * All open / carried-forward memory items across every project owned by this
     * researcher, plus any researcher-level (project-less) items. Joined to a
     * project title for display. Newest first. Empty on failure.
     *
     * This is the pool a new project starts from. Read-only over research_project
     * (a SELECT join only - no write, no ALTER).
     *
     * @return array<int,object>
     */
    public function carryForwardForResearcher(int $researcherId): array
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return [];
            }

            $q = DB::table(self::TABLE . ' as m')
                ->where('m.researcher_id', $researcherId)
                ->whereIn('m.status', ['open', 'carried_forward']);

            if (Schema::hasTable('research_project')) {
                $q->leftJoin('research_project as p', 'p.id', '=', 'm.project_id')
                    ->select('m.*', 'p.title as project_title', 'p.status as project_status');
            } else {
                $q->select('m.*');
            }

            return $q->orderByRaw('COALESCE(m.updated_at, m.created_at) DESC')
                ->orderBy('m.id', 'desc')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Counts of carry-forward items grouped by kind for the aggregate header.
     *
     * @return array<string,int>
     */
    public function carryForwardCountsByKind(int $researcherId): array
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return [];
            }

            return DB::table(self::TABLE)
                ->where('researcher_id', $researcherId)
                ->whereIn('status', ['open', 'carried_forward'])
                ->groupBy('kind')
                ->selectRaw('kind, COUNT(*) AS n')
                ->pluck('n', 'kind')
                ->map(fn ($n) => (int) $n)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Whitelist a kind code, defaulting to 'other'. */
    private function normaliseKind(?string $code): string
    {
        return ($code !== null && in_array($code, $this->kindCodes(), true)) ? $code : 'other';
    }

    /** Whitelist a status code, defaulting to 'open'. */
    private function normaliseStatus(?string $code): string
    {
        return ($code !== null && in_array($code, $this->statusCodes(), true)) ? $code : 'open';
    }
}
