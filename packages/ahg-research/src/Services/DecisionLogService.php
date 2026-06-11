<?php

/**
 * DecisionLogService - Heratio ahg-research
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
 * heratio#1224 - Research OS Stage 9: the per-project Decision Log.
 *
 * The Decision Log is the recorded memory of every loop in a research project:
 * every scope change, exclusion, hypothesis revision, method pivot, question
 * reformulation, and supervisor instruction acted on - each with its reason.
 * It is distinct from research_activity_log (system audit of WHAT happened);
 * this records WHY. It answers an examiner's "why did you exclude X" with
 * receipts and feeds the limitations section.
 *
 * Every query is Schema::hasTable-guarded and wrapped in try/catch so the
 * feature degrades to an empty list rather than ever throwing a 500.
 */
class DecisionLogService
{
    public const TABLE = 'research_decision_log';

    /**
     * Canonical decision_type fallback list. decision_type is a VARCHAR holding
     * one of these codes - never a MySQL ENUM. The authoritative source is the
     * ahg_dropdown taxonomy 'decision_type' (see seed_decision_log_dropdowns.sql);
     * this constant is the resilient fallback used when the dropdown is absent.
     *
     * @var array<string,array{label:string,color:string,icon:string}>
     */
    public const FALLBACK_TYPES = [
        'scope_change'           => ['label' => 'Scope change',           'color' => '#0d6efd', 'icon' => 'crop'],
        'exclusion'              => ['label' => 'Exclusion',              'color' => '#dc3545', 'icon' => 'ban'],
        'hypothesis_revision'    => ['label' => 'Hypothesis revision',    'color' => '#fd7e14', 'icon' => 'lightbulb'],
        'method_pivot'           => ['label' => 'Method pivot',           'color' => '#6610f2', 'icon' => 'route'],
        'question_reformulation' => ['label' => 'Question reformulation', 'color' => '#20c997', 'icon' => 'question-circle'],
        'supervisor_instruction' => ['label' => 'Supervisor instruction', 'color' => '#198754', 'icon' => 'user-graduate'],
        'other'                  => ['label' => 'Other',                  'color' => '#6c757d', 'icon' => 'circle-dot'],
    ];

    /**
     * The decision_type options as {code => [label,color,icon]}, sourced from
     * the ahg_dropdown taxonomy 'decision_type' when present, otherwise from the
     * canonical fallback list. Never throws.
     *
     * @return array<string,array{label:string,color:string,icon:string}>
     */
    public function types(): array
    {
        try {
            if (Schema::hasTable('ahg_dropdown')) {
                $rows = DB::table('ahg_dropdown')
                    ->where('taxonomy', 'decision_type')
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

        return self::FALLBACK_TYPES;
    }

    /** Valid decision_type codes for validation. @return array<int,string> */
    public function typeCodes(): array
    {
        return array_keys($this->types());
    }

    /**
     * Resolve a decision_type code to its display meta (label/color/icon),
     * tolerating legacy or unknown codes.
     *
     * @return array{label:string,color:string,icon:string}
     */
    public function typeMeta(?string $code): array
    {
        $types = $this->types();
        if ($code !== null && isset($types[$code])) {
            return $types[$code];
        }

        return [
            'label' => $code ? ucfirst(str_replace('_', ' ', $code)) : 'Other',
            'color' => '#6c757d',
            'icon'  => 'circle-dot',
        ];
    }

    /**
     * List the decision-log entries for a project, newest first. Optional filter
     * by decision_type. Returns an empty array on any failure.
     *
     * @return array<int,object>
     */
    public function listForProject(int $projectId, ?string $type = null): array
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return [];
            }

            $q = DB::table(self::TABLE)->where('project_id', $projectId);

            if ($type !== null && $type !== '' && in_array($type, $this->typeCodes(), true)) {
                $q->where('decision_type', $type);
            }

            return $q->orderByRaw('COALESCE(decided_at, created_at) DESC')
                ->orderBy('id', 'desc')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Fetch a single entry scoped to its project, or null. */
    public function find(int $projectId, int $id): ?object
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return null;
            }

            return DB::table(self::TABLE)
                ->where('project_id', $projectId)
                ->where('id', $id)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Per-type counts for a project (for the timeline summary chips).
     *
     * @return array<string,int>
     */
    public function countsByType(int $projectId): array
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return [];
            }

            return DB::table(self::TABLE)
                ->where('project_id', $projectId)
                ->groupBy('decision_type')
                ->selectRaw('decision_type, COUNT(*) AS n')
                ->pluck('n', 'decision_type')
                ->map(fn ($n) => (int) $n)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Create a decision-log entry. Returns the new id, or null on failure.
     *
     * @param array{decision_type?:string,summary?:string,reason?:string,related_ref?:string,decided_by?:string,decided_at?:string} $data
     */
    public function create(int $projectId, array $data): ?int
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return null;
            }

            $type = $data['decision_type'] ?? 'other';
            if (! in_array($type, $this->typeCodes(), true)) {
                $type = 'other';
            }

            return (int) DB::table(self::TABLE)->insertGetId([
                'project_id'    => $projectId,
                'decision_type' => $type,
                'summary'       => mb_substr((string) ($data['summary'] ?? ''), 0, 500),
                'reason'        => $data['reason'] ?? null,
                'related_ref'   => isset($data['related_ref']) ? mb_substr((string) $data['related_ref'], 0, 500) : null,
                'decided_by'    => isset($data['decided_by']) ? mb_substr((string) $data['decided_by'], 0, 255) : null,
                'decided_at'    => $this->normaliseDate($data['decided_at'] ?? null) ?? now(),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Update an existing entry scoped to its project. Returns true on success.
     *
     * @param array{decision_type?:string,summary?:string,reason?:string,related_ref?:string,decided_by?:string,decided_at?:string} $data
     */
    public function update(int $projectId, int $id, array $data): bool
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return false;
            }

            $update = [];

            if (array_key_exists('decision_type', $data)) {
                $type = $data['decision_type'] ?? 'other';
                $update['decision_type'] = in_array($type, $this->typeCodes(), true) ? $type : 'other';
            }
            if (array_key_exists('summary', $data)) {
                $update['summary'] = mb_substr((string) $data['summary'], 0, 500);
            }
            if (array_key_exists('reason', $data)) {
                $update['reason'] = $data['reason'] ?: null;
            }
            if (array_key_exists('related_ref', $data)) {
                $update['related_ref'] = $data['related_ref'] ? mb_substr((string) $data['related_ref'], 0, 500) : null;
            }
            if (array_key_exists('decided_by', $data)) {
                $update['decided_by'] = $data['decided_by'] ? mb_substr((string) $data['decided_by'], 0, 255) : null;
            }
            if (array_key_exists('decided_at', $data)) {
                $update['decided_at'] = $this->normaliseDate($data['decided_at'] ?? null);
            }

            if ($update === []) {
                return false;
            }

            return DB::table(self::TABLE)
                ->where('project_id', $projectId)
                ->where('id', $id)
                ->update($update) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Delete an entry scoped to its project. Returns true on success. */
    public function delete(int $projectId, int $id): bool
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return false;
            }

            return DB::table(self::TABLE)
                ->where('project_id', $projectId)
                ->where('id', $id)
                ->delete() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Coerce a free-form date string to Y-m-d H:i:s, or null if unparseable. */
    private function normaliseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
