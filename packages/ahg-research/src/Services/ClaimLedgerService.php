<?php

/**
 * ClaimLedgerService - Service for Heratio
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
 * ClaimLedgerService - Research OS Stage 8 (heratio#1223).
 *
 * Promotes the existing `research_assertion` + `research_assertion_evidence`
 * tables into a per-project Claim Ledger. The core claim (text, status,
 * confidence, project + researcher ownership) lives in `research_assertion`,
 * which is NEVER altered. Extra Claim-Ledger fields live in the sidecar table
 * `research_claim_meta` (1:1 by assertion_id). Evidence links reuse
 * `research_assertion_evidence`.
 *
 * Every read/write is Schema::hasTable-guarded and wrapped in try/catch so the
 * ledger degrades to empty state rather than throwing a 500 when a table is
 * missing during a partial install.
 */
class ClaimLedgerService
{
    /**
     * Canonical claim lifecycle. The status string is stored in
     * research_assertion.status (varchar(46)). These are the values the Claim
     * Ledger surfaces; legacy values (proposed/verified/disputed) still render.
     *
     * @var array<string,string>
     */
    public const STATUSES = [
        'idea'              => 'Idea',
        'working'           => 'Working claim',
        'supported'         => 'Supported',
        'contested'         => 'Contested',
        'weak'              => 'Weak',
        'rejected'          => 'Rejected',
        'needs_evidence'    => 'Needs more evidence',
        'publishable'       => 'Publishable',
    ];

    /** @var array<string,string> Bootstrap badge colour per status. */
    public const STATUS_BADGES = [
        'idea'           => 'secondary',
        'working'        => 'info',
        'supported'      => 'success',
        'contested'      => 'warning',
        'weak'           => 'warning',
        'rejected'       => 'danger',
        'needs_evidence' => 'warning',
        'publishable'    => 'primary',
        // legacy
        'proposed'       => 'secondary',
        'verified'       => 'success',
        'disputed'       => 'danger',
    ];

    /** @var array<string> Provenance / originality of the claim. */
    public const PROVENANCE_KINDS = ['original', 'derived', 'speculative'];

    /** @var array<string> Confidence levels surfaced in the ledger. */
    public const CONFIDENCE_LEVELS = ['high', 'medium', 'low', 'tentative'];

    /** @var array<string> Evidence types a claim can rest on. */
    public const EVIDENCE_TYPES = [
        'primary_source', 'secondary_source', 'archival_record',
        'oral_testimony', 'material_object', 'observation', 'derived_analysis',
    ];

    /** Source types that can be attached as evidence (reuses research_assertion_evidence). */
    public const SOURCE_TYPES = ['bibliography', 'annotation', 'collection_item'];

    protected function tablesReady(): bool
    {
        try {
            return Schema::hasTable('research_assertion');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function metaReady(): bool
    {
        try {
            return Schema::hasTable('research_claim_meta');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // READ
    // =====================================================================

    /**
     * List claims for a project with optional status / search filters.
     * Each row carries evidence_count and any merged sidecar meta.
     *
     * @return array<int,object>
     */
    public function listClaims(int $projectId, array $filters = []): array
    {
        if (! $this->tablesReady()) {
            return [];
        }
        try {
            $q = DB::table('research_assertion as a')
                ->leftJoin('research_assertion_evidence as e', 'a.id', '=', 'e.assertion_id')
                ->where('a.project_id', $projectId)
                ->select('a.*', DB::raw('COUNT(DISTINCT e.id) as evidence_count'))
                ->groupBy('a.id');

            if (! empty($filters['status'])) {
                $q->where('a.status', $filters['status']);
            }
            if (! empty($filters['search'])) {
                $term = '%' . $filters['search'] . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('a.subject_label', 'like', $term)
                      ->orWhere('a.object_value', 'like', $term)
                      ->orWhere('a.object_label', 'like', $term)
                      ->orWhere('a.predicate', 'like', $term);
                });
            }

            $rows = $q->orderBy('a.updated_at', 'desc')->get();

            // Merge sidecar meta in one batched query.
            $meta = [];
            if ($this->metaReady() && $rows->count() > 0) {
                $ids = $rows->pluck('id')->all();
                $meta = DB::table('research_claim_meta')
                    ->whereIn('assertion_id', $ids)
                    ->get()->keyBy('assertion_id');
            }
            foreach ($rows as $r) {
                $r->meta = $meta[$r->id] ?? null;
            }

            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Count claims per status for a project (for the filter pills + summary). */
    public function statusCounts(int $projectId): array
    {
        if (! $this->tablesReady()) {
            return [];
        }
        try {
            $rows = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->select('status', DB::raw('COUNT(*) as n'))
                ->groupBy('status')
                ->pluck('n', 'status');
            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Load one claim (assertion + merged sidecar meta) scoped to a project. */
    public function getClaim(int $projectId, int $claimId): ?object
    {
        if (! $this->tablesReady()) {
            return null;
        }
        try {
            $claim = DB::table('research_assertion')
                ->where('id', $claimId)
                ->where('project_id', $projectId)
                ->first();
            if (! $claim) {
                return null;
            }
            $claim->meta = $this->metaReady()
                ? DB::table('research_claim_meta')->where('assertion_id', $claimId)->first()
                : null;
            return $claim;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Evidence rows attached to a claim, resolved to a human label per source.
     *
     * @return array<int,object>
     */
    public function getEvidence(int $claimId): array
    {
        try {
            if (! Schema::hasTable('research_assertion_evidence')) {
                return [];
            }
            $rows = DB::table('research_assertion_evidence')
                ->where('assertion_id', $claimId)
                ->orderBy('created_at', 'desc')
                ->get();
            foreach ($rows as $r) {
                $r->source_label = $this->resolveSourceLabel($r->source_type, (int) $r->source_id);
            }
            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Selectable evidence candidates from the project's own bibliography,
     * annotations and collection items. Returns a map keyed by source_type.
     *
     * @return array<string,array<int,object>>
     */
    public function availableEvidence(int $projectId): array
    {
        $out = ['bibliography' => [], 'annotation' => [], 'collection_item' => []];
        try {
            if (Schema::hasTable('research_bibliography')) {
                $out['bibliography'] = DB::table('research_bibliography')
                    ->where('project_id', $projectId)
                    ->select('id', 'name as label')
                    ->orderBy('name')->get()->all();
            }
        } catch (\Throwable $e) {
            // skip
        }
        try {
            if (Schema::hasTable('research_annotation')) {
                $out['annotation'] = DB::table('research_annotation')
                    ->where('project_id', $projectId)
                    ->select('id', DB::raw("COALESCE(NULLIF(title,''), LEFT(content,80), 'Annotation') as label"))
                    ->orderBy('id', 'desc')->limit(500)->get()->all();
            }
        } catch (\Throwable $e) {
            // skip
        }
        try {
            if (Schema::hasTable('research_collection_item') && Schema::hasTable('research_collection')) {
                $out['collection_item'] = DB::table('research_collection_item as ci')
                    ->join('research_collection as c', 'ci.collection_id', '=', 'c.id')
                    ->where('c.project_id', $projectId)
                    ->select('ci.id', DB::raw("COALESCE(NULLIF(ci.reference_code,''), CONCAT('Item #', ci.object_id)) as label"))
                    ->orderBy('ci.id', 'desc')->limit(500)->get()->all();
            }
        } catch (\Throwable $e) {
            // skip
        }
        return $out;
    }

    protected function resolveSourceLabel(string $type, int $id): string
    {
        try {
            switch ($type) {
                case 'bibliography':
                    if (Schema::hasTable('research_bibliography')) {
                        $v = DB::table('research_bibliography')->where('id', $id)->value('name');
                        if ($v) return (string) $v;
                    }
                    break;
                case 'annotation':
                    if (Schema::hasTable('research_annotation')) {
                        $r = DB::table('research_annotation')->where('id', $id)->first(['title', 'content']);
                        if ($r) return (string) ($r->title ?: mb_substr((string) $r->content, 0, 80));
                    }
                    break;
                case 'collection_item':
                    if (Schema::hasTable('research_collection_item')) {
                        $r = DB::table('research_collection_item')->where('id', $id)->first(['reference_code', 'object_id']);
                        if ($r) return (string) ($r->reference_code ?: ('Item #' . $r->object_id));
                    }
                    break;
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return ucfirst(str_replace('_', ' ', $type)) . ' #' . $id;
    }

    // =====================================================================
    // FOUNDING-PRINCIPLE SURFACES: no unsupported claim passes silently
    // =====================================================================

    /**
     * Claims with NO evidence attached. The ledger surfaces these so a claim
     * cannot quietly graduate without a citation.
     *
     * @return array<int,object>
     */
    public function claimsWithoutCitation(int $projectId): array
    {
        if (! $this->tablesReady()) {
            return [];
        }
        try {
            return DB::table('research_assertion as a')
                ->leftJoin('research_assertion_evidence as e', 'a.id', '=', 'e.assertion_id')
                ->where('a.project_id', $projectId)
                ->whereNull('e.id')
                ->select('a.*')
                ->orderBy('a.updated_at', 'desc')
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Claims that lean on a single source (>=2 evidence rows but only ONE
     * distinct source). Over-dependence on one source is flagged for review.
     *
     * @return array<int,object>
     */
    public function claimsOverDependent(int $projectId): array
    {
        if (! $this->tablesReady()) {
            return [];
        }
        try {
            return DB::table('research_assertion as a')
                ->join('research_assertion_evidence as e', 'a.id', '=', 'e.assertion_id')
                ->where('a.project_id', $projectId)
                ->select('a.*',
                    DB::raw('COUNT(e.id) as evidence_count'),
                    DB::raw("COUNT(DISTINCT CONCAT(e.source_type,':',e.source_id)) as distinct_sources"))
                ->groupBy('a.id')
                ->havingRaw('COUNT(e.id) >= 2 AND distinct_sources = 1')
                ->orderBy('a.updated_at', 'desc')
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =====================================================================
    // WRITE
    // =====================================================================

    /**
     * Create a claim. Core fields land in research_assertion; extras in the
     * sidecar. Returns the new assertion id, or null on failure.
     */
    public function createClaim(int $projectId, int $researcherId, array $data): ?int
    {
        if (! $this->tablesReady()) {
            return null;
        }
        try {
            $now = now();
            $id = DB::table('research_assertion')->insertGetId([
                'project_id'     => $projectId,
                'researcher_id'  => $researcherId,
                'assertion_type' => $data['assertion_type'] ?? 'claim',
                'subject_type'   => 'text',
                'subject_id'     => 0,
                'subject_label'  => $data['text'] ?? '',
                'predicate'      => $data['predicate'] ?? 'claims',
                'object_value'   => $data['text'] ?? '',
                'object_label'   => mb_substr($data['text'] ?? '', 0, 500),
                'status'         => $this->normaliseStatus($data['status'] ?? 'idea'),
                'confidence'     => $this->confidenceToDecimal($data['confidence_level'] ?? null),
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $this->upsertMeta($id, $data);
            return $id;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Update a claim's text/status and sidecar meta. Returns success. */
    public function updateClaim(int $projectId, int $claimId, array $data): bool
    {
        if (! $this->tablesReady()) {
            return false;
        }
        try {
            $update = ['updated_at' => now()];
            if (array_key_exists('text', $data)) {
                $update['subject_label'] = $data['text'];
                $update['object_value']  = $data['text'];
                $update['object_label']  = mb_substr((string) $data['text'], 0, 500);
            }
            if (array_key_exists('status', $data)) {
                $update['status'] = $this->normaliseStatus($data['status']);
            }
            if (array_key_exists('assertion_type', $data)) {
                $update['assertion_type'] = $data['assertion_type'];
            }
            if (array_key_exists('confidence_level', $data)) {
                $update['confidence'] = $this->confidenceToDecimal($data['confidence_level']);
            }

            $affected = DB::table('research_assertion')
                ->where('id', $claimId)
                ->where('project_id', $projectId)
                ->update($update);

            $this->upsertMeta($claimId, $data);
            return $affected >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Set just the status of a claim (lifecycle transition). */
    public function setStatus(int $projectId, int $claimId, string $status): bool
    {
        if (! $this->tablesReady()) {
            return false;
        }
        try {
            return DB::table('research_assertion')
                ->where('id', $claimId)
                ->where('project_id', $projectId)
                ->update(['status' => $this->normaliseStatus($status), 'updated_at' => now()]) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Delete a claim, its sidecar meta and its evidence links. */
    public function deleteClaim(int $projectId, int $claimId): bool
    {
        if (! $this->tablesReady()) {
            return false;
        }
        try {
            $owned = DB::table('research_assertion')
                ->where('id', $claimId)
                ->where('project_id', $projectId)
                ->exists();
            if (! $owned) {
                return false;
            }
            if (Schema::hasTable('research_assertion_evidence')) {
                DB::table('research_assertion_evidence')->where('assertion_id', $claimId)->delete();
            }
            if ($this->metaReady()) {
                DB::table('research_claim_meta')->where('assertion_id', $claimId)->delete();
            }
            DB::table('research_assertion')
                ->where('id', $claimId)
                ->where('project_id', $projectId)
                ->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Attach an evidence source to a claim (reuses research_assertion_evidence). */
    public function attachEvidence(int $projectId, int $claimId, string $sourceType, int $sourceId, int $userId, string $relationship = 'supports', ?string $note = null): bool
    {
        if (! $this->tablesReady()) {
            return false;
        }
        try {
            $owned = DB::table('research_assertion')
                ->where('id', $claimId)->where('project_id', $projectId)->exists();
            if (! $owned || ! Schema::hasTable('research_assertion_evidence')) {
                return false;
            }
            if (! in_array($sourceType, self::SOURCE_TYPES, true)) {
                return false;
            }
            $exists = DB::table('research_assertion_evidence')
                ->where('assertion_id', $claimId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->exists();
            if ($exists) {
                return true;
            }
            DB::table('research_assertion_evidence')->insert([
                'assertion_id' => $claimId,
                'source_type'  => $sourceType,
                'source_id'    => $sourceId,
                'relationship' => in_array($relationship, ['supports', 'opposes', 'contextualizes'], true) ? $relationship : 'supports',
                'note'         => $note,
                'added_by'     => $userId,
                'created_at'   => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Detach an evidence row from a claim (project-scoped). */
    public function detachEvidence(int $projectId, int $claimId, int $evidenceId): bool
    {
        if (! $this->tablesReady()) {
            return false;
        }
        try {
            $owned = DB::table('research_assertion')
                ->where('id', $claimId)->where('project_id', $projectId)->exists();
            if (! $owned || ! Schema::hasTable('research_assertion_evidence')) {
                return false;
            }
            DB::table('research_assertion_evidence')
                ->where('id', $evidenceId)
                ->where('assertion_id', $claimId)
                ->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // INTERNAL HELPERS
    // =====================================================================

    /** Upsert the sidecar meta row for a claim from the supplied data. */
    protected function upsertMeta(int $claimId, array $data): void
    {
        if (! $this->metaReady()) {
            return;
        }
        $fields = [
            'evidence_type'         => $data['evidence_type'] ?? null,
            'confidence_level'      => $data['confidence_level'] ?? null,
            'provenance_kind'       => in_array(($data['provenance_kind'] ?? null), self::PROVENANCE_KINDS, true) ? $data['provenance_kind'] : ($data['provenance_kind'] ?? 'original'),
            'supporting_sources'    => $data['supporting_sources'] ?? null,
            'opposing_sources'      => $data['opposing_sources'] ?? null,
            'quotations'            => $data['quotations'] ?? null,
            'method_theory_link'    => $data['method_theory_link'] ?? null,
            'researcher_notes'      => $data['researcher_notes'] ?? null,
            'unresolved_weaknesses' => $data['unresolved_weaknesses'] ?? null,
            'ethical_concerns'      => $data['ethical_concerns'] ?? null,
        ];

        // Only touch meta if at least one extra field was supplied.
        $hasAny = false;
        foreach ($fields as $v) {
            if ($v !== null && $v !== '') {
                $hasAny = true;
                break;
            }
        }

        try {
            $existing = DB::table('research_claim_meta')->where('assertion_id', $claimId)->first();
            if ($existing) {
                $fields['updated_at'] = now();
                DB::table('research_claim_meta')->where('assertion_id', $claimId)->update($fields);
            } elseif ($hasAny) {
                $fields['assertion_id'] = $claimId;
                $fields['created_at']   = now();
                $fields['updated_at']   = now();
                DB::table('research_claim_meta')->insert($fields);
            }
        } catch (\Throwable $e) {
            // sidecar write best-effort; core claim already persisted
        }
    }

    /** Keep status within the known set; pass through unknown legacy values. */
    protected function normaliseStatus(?string $status): string
    {
        $status = trim((string) $status);
        if ($status === '') {
            return 'idea';
        }
        return $status;
    }

    /** Map a textual confidence level to the decimal(5,2) confidence column. */
    protected function confidenceToDecimal(?string $level): ?float
    {
        return match ($level) {
            'high'      => 0.90,
            'medium'    => 0.60,
            'low'       => 0.30,
            'tentative' => 0.10,
            default     => null,
        };
    }
}
