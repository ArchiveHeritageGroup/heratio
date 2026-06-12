<?php

/**
 * ResearchEthicsService - Heratio ahg-research
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
 * heratio#1222 - Research OS: Research Ethics & Consent register.
 *
 * A register of a research project's ethics approvals and the consent basis on
 * which its human-subject / sensitive data is held and used - a standard
 * research-governance artifact. Each record carries an approval type, committee
 * and reference number, status, decision and expiry dates, a consent basis and a
 * data-sensitivity classification, and can optionally reference the project's
 * Data Management Plan (the sibling research_dmp slice).
 *
 * Mirrors ResearchOutputService exactly: scoped to a project, dropdown-backed
 * taxonomies (never ENUM), a machine-readable JSON export, and a per-project
 * summary with an expiring-soon flag. Every read is Schema::hasTable-guarded and
 * try/catch-wrapped so a partial install degrades cleanly rather than 500ing. No
 * live writes outside the one NEW research_ethics table; no ALTER of any existing
 * table.
 *
 * International and jurisdiction-neutral: the consent_basis values are generic
 * governance concepts, NOT the lawful-basis terms of any one country's regime.
 */
class ResearchEthicsService
{
    public const APPROVAL_TYPE_TAXONOMY    = 'research_ethics_approval_type';
    public const STATUS_TAXONOMY           = 'research_ethics_status';
    public const CONSENT_BASIS_TAXONOMY    = 'research_consent_basis';
    public const DATA_SENSITIVITY_TAXONOMY = 'research_data_sensitivity';

    /** Window (days) within which a not-yet-expired approval is "expiring soon". */
    public const EXPIRING_SOON_DAYS = 60;

    // ---------------------------------------------------------------------
    // Ethics records (CRUD)
    // ---------------------------------------------------------------------

    /** Ethics records on a project (lightweight list rows, newest first). */
    public function listRecords(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_ethics')) {
                return [];
            }

            return DB::table('research_ethics')
                ->where('project_id', $projectId)
                ->orderByRaw('decision_date IS NULL, decision_date DESC')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($r) => $this->rowToArray($r))
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A single ethics record as an array, scoped to its project, or null. */
    public function getRecord(int $id, ?int $projectId = null): ?array
    {
        try {
            if (! Schema::hasTable('research_ethics')) {
                return null;
            }
            $q = DB::table('research_ethics')->where('id', $id);
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
     * Create an ethics record for a project. Returns the new id, or null on failure.
     *
     * @param  array<string,mixed>  $data
     */
    public function createRecord(int $projectId, ?int $researcherId, array $data): ?int
    {
        try {
            if (! Schema::hasTable('research_ethics')) {
                return null;
            }

            $now = now();
            $row = array_merge($this->normalise($projectId, $data), [
                'project_id' => $projectId,
                'owner_id'   => $researcherId,
                'created_by' => $researcherId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return (int) DB::table('research_ethics')->insertGetId($row);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] ethics createRecord failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Update an ethics record, scoped to its project.
     *
     * @param  array<string,mixed>  $data
     */
    public function updateRecord(int $id, int $projectId, array $data): bool
    {
        try {
            if (! Schema::hasTable('research_ethics')) {
                return false;
            }
            $owns = DB::table('research_ethics')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }

            $row = array_merge($this->normalise($projectId, $data), ['updated_at' => now()]);
            DB::table('research_ethics')->where('id', $id)->where('project_id', $projectId)->update($row);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] ethics updateRecord failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Delete an ethics record, scoped to its project. */
    public function deleteRecord(int $id, int $projectId): bool
    {
        try {
            if (! Schema::hasTable('research_ethics')) {
                return false;
            }
            $owns = DB::table('research_ethics')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }
            DB::table('research_ethics')->where('id', $id)->where('project_id', $projectId)->delete();

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] ethics deleteRecord failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Coerce validated request data into a writeable column map. Dropdown-backed
     * values are constrained to their known option codes; free-text is trimmed
     * and length-capped. The dmp_id is only kept if it points at a plan on the
     * SAME project (FK-by-convention, verified, never assumed).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function normalise(int $projectId, array $data): array
    {
        $type = (string) ($data['approval_type'] ?? 'human_subjects');
        if (! array_key_exists($type, $this->approvalTypeOptions())) {
            $type = 'human_subjects';
        }

        $status = (string) ($data['status'] ?? 'pending');
        if (! array_key_exists($status, $this->statusOptions())) {
            $status = 'pending';
        }

        $consent = (string) ($data['consent_basis'] ?? 'informed_consent');
        if (! array_key_exists($consent, $this->consentBasisOptions())) {
            $consent = 'informed_consent';
        }

        $sensitivity = (string) ($data['data_sensitivity'] ?? 'none');
        if (! array_key_exists($sensitivity, $this->dataSensitivityOptions())) {
            $sensitivity = 'none';
        }

        return [
            'title'            => mb_substr(trim((string) ($data['title'] ?? '')), 0, 512),
            'approval_type'    => $type,
            'reference_number' => $this->trimOrNull($data['reference_number'] ?? null, 128),
            'committee_name'   => $this->trimOrNull($data['committee_name'] ?? null, 512),
            'status'           => $status,
            'decision_date'    => $this->dateOrNull($data['decision_date'] ?? null),
            'expiry_date'      => $this->dateOrNull($data['expiry_date'] ?? null),
            'consent_basis'    => $consent,
            'data_sensitivity' => $sensitivity,
            'notes'            => isset($data['notes']) && trim((string) $data['notes']) !== ''
                ? mb_substr((string) $data['notes'], 0, 65000) : null,
            'dmp_id'           => $this->validDmpId($data['dmp_id'] ?? null, $projectId),
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
            return \Illuminate\Support\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Validate a candidate dmp_id - it must reference a research_dmp row on the
     * SAME project, or it is dropped. Resilient when the sibling slice is not
     * installed (no research_dmp table) - simply returns null.
     */
    private function validDmpId(mixed $value, int $projectId): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }
        try {
            if (! Schema::hasTable('research_dmp')) {
                return null;
            }
            $ok = DB::table('research_dmp')
                ->where('id', (int) $value)
                ->where('project_id', $projectId)
                ->exists();

            return $ok ? (int) $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---------------------------------------------------------------------
    // DMP link options (sibling slice, optional)
    // ---------------------------------------------------------------------

    /**
     * Plans on this project for the optional DMP link [id => label]. Returns an
     * empty array when the sibling slice is absent, so the form degrades to "no
     * plan" cleanly.
     *
     * @return array<int,string>
     */
    public function dmpOptions(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_dmp')) {
                return [];
            }
            $rows = DB::table('research_dmp')
                ->where('project_id', $projectId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['id', 'title']);

            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r->id] = (string) $r->title;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ---------------------------------------------------------------------
    // Expiry helpers (drives the expiring-soon flag)
    // ---------------------------------------------------------------------

    /**
     * Classify a record's expiry relative to today: 'expired' when the expiry
     * date is in the past, 'soon' when it falls inside the expiring-soon window
     * (and the status is not already terminal), or null otherwise. Resilient to a
     * missing / unparseable date.
     *
     * @param  array<string,mixed>  $record
     */
    public function expiryFlag(array $record): ?string
    {
        $raw = trim((string) ($record['expiry_date'] ?? ''));
        if ($raw === '') {
            return null;
        }
        $status = (string) ($record['status'] ?? '');
        // Terminal statuses do not raise an "expiring soon" flag.
        if (in_array($status, ['rejected', 'not_required'], true)) {
            return null;
        }
        try {
            $expiry = \Illuminate\Support\Carbon::parse($raw)->startOfDay();
            $today  = \Illuminate\Support\Carbon::now()->startOfDay();

            if ($expiry->lt($today)) {
                return 'expired';
            }
            if ($expiry->lte($today->copy()->addDays(self::EXPIRING_SOON_DAYS))) {
                return 'soon';
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---------------------------------------------------------------------
    // Per-project summary (counts by status + expiring-soon flag)
    // ---------------------------------------------------------------------

    /**
     * Summary of a project's ethics records: total, counts by status, counts by
     * approval type, and the number of approvals that are expiring soon or have
     * already expired (the governance flag). Only present statuses/types are
     * returned, each carrying its human label.
     *
     * @return array{total:int,by_status:array<int,array{code:string,label:string,count:int}>,by_type:array<int,array{code:string,label:string,count:int}>,expiring_soon:int,expired:int}
     */
    public function summary(int $projectId): array
    {
        $empty = ['total' => 0, 'by_status' => [], 'by_type' => [], 'expiring_soon' => 0, 'expired' => 0];
        try {
            if (! Schema::hasTable('research_ethics')) {
                return $empty;
            }

            $records = $this->listRecords($projectId);
            $total   = count($records);

            $statusCounts = [];
            $typeCounts   = [];
            $expiringSoon = 0;
            $expired      = 0;
            foreach ($records as $r) {
                $s = (string) ($r['status'] ?? '');
                $t = (string) ($r['approval_type'] ?? '');
                $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
                $typeCounts[$t]   = ($typeCounts[$t] ?? 0) + 1;

                $flag = $this->expiryFlag($r);
                if ($flag === 'soon') {
                    $expiringSoon++;
                } elseif ($flag === 'expired') {
                    $expired++;
                }
            }

            $statusLabels = $this->statusOptions();
            $typeLabels   = $this->approvalTypeOptions();

            return [
                'total'         => $total,
                'by_status'     => $this->labelCounts($statusCounts, $statusLabels),
                'by_type'       => $this->labelCounts($typeCounts, $typeLabels),
                'expiring_soon' => $expiringSoon,
                'expired'       => $expired,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
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
     * Build a machine-readable export of a project's ethics records. Each entry
     * carries the approval type, status, committee, reference, dates, consent
     * basis and data-sensitivity (codes + human labels) plus an expiry flag. The
     * shape is a top-level object with a "project" block, a generated_at
     * timestamp, a "count" and an "ethics" array.
     *
     * @param  array<int,array<string,mixed>>  $records
     * @param  object|null  $project
     * @return array<string,mixed>
     */
    public function buildExport(array $records, ?object $project = null): array
    {
        $typeLabels    = $this->approvalTypeOptions();
        $statusLabels  = $this->statusOptions();
        $consentLabels = $this->consentBasisOptions();
        $sensLabels    = $this->dataSensitivityOptions();

        $items = [];
        foreach ($records as $r) {
            $type    = (string) ($r['approval_type'] ?? '');
            $status  = (string) ($r['status'] ?? '');
            $consent = (string) ($r['consent_basis'] ?? '');
            $sens    = (string) ($r['data_sensitivity'] ?? '');
            $items[] = [
                'id'                     => (int) ($r['id'] ?? 0),
                'title'                  => (string) ($r['title'] ?? ''),
                'approval_type'          => $type,
                'approval_type_label'    => $typeLabels[$type] ?? $type,
                'reference_number'       => (string) ($r['reference_number'] ?? ''),
                'committee_name'         => (string) ($r['committee_name'] ?? ''),
                'status'                 => $status,
                'status_label'           => $statusLabels[$status] ?? $status,
                'decision_date'          => (string) ($r['decision_date'] ?? ''),
                'expiry_date'            => (string) ($r['expiry_date'] ?? ''),
                'expiry_flag'            => $this->expiryFlag($r),
                'consent_basis'          => $consent,
                'consent_basis_label'    => $consentLabels[$consent] ?? $consent,
                'data_sensitivity'       => $sens,
                'data_sensitivity_label' => $sensLabels[$sens] ?? $sens,
                'dmp_id'                 => $r['dmp_id'] ?? null,
                'notes'                  => (string) ($r['notes'] ?? ''),
            ];
        }

        return [
            'project' => [
                'id'    => isset($project->id) ? (int) $project->id : null,
                'title' => isset($project->title) ? (string) $project->title : '',
            ],
            'generated_at' => now()->toIso8601String(),
            'count'        => count($items),
            'ethics'       => $items,
        ];
    }

    // ---------------------------------------------------------------------
    // Dropdown-backed taxonomies (Dropdown Manager - never ENUM)
    // ---------------------------------------------------------------------

    /** Approval-type options [code => label], with a safe fallback. */
    public function approvalTypeOptions(): array
    {
        return $this->dropdownOptions(self::APPROVAL_TYPE_TAXONOMY, [
            'human_subjects' => 'Human subjects', 'animal' => 'Animal',
            'data_protection' => 'Data protection', 'biosafety' => 'Biosafety', 'other' => 'Other',
        ]);
    }

    /** Status options [code => label], with a safe fallback. */
    public function statusOptions(): array
    {
        return $this->dropdownOptions(self::STATUS_TAXONOMY, [
            'not_required' => 'Not required', 'pending' => 'Pending', 'approved' => 'Approved',
            'conditions' => 'Approved with conditions', 'expired' => 'Expired', 'rejected' => 'Rejected',
        ]);
    }

    /**
     * Consent-basis options [code => label], with a safe fallback. These are
     * generic, jurisdiction-neutral governance concepts - NOT one law's terms.
     */
    public function consentBasisOptions(): array
    {
        return $this->dropdownOptions(self::CONSENT_BASIS_TAXONOMY, [
            'informed_consent' => 'Informed consent', 'legitimate_interest' => 'Legitimate interest',
            'public_task' => 'Public task', 'anonymised' => 'Anonymised data', 'not_applicable' => 'Not applicable',
        ]);
    }

    /** Data-sensitivity options [code => label], with a safe fallback. */
    public function dataSensitivityOptions(): array
    {
        return $this->dropdownOptions(self::DATA_SENSITIVITY_TAXONOMY, [
            'none' => 'None', 'personal' => 'Personal',
            'special_category' => 'Special category', 'restricted' => 'Restricted',
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

    /** @return array<string,mixed> */
    private function rowToArray(object $r): array
    {
        return [
            'id'               => (int) $r->id,
            'project_id'       => (int) $r->project_id,
            'title'            => (string) $r->title,
            'approval_type'    => (string) $r->approval_type,
            'reference_number' => $r->reference_number !== null ? (string) $r->reference_number : '',
            'committee_name'   => $r->committee_name !== null ? (string) $r->committee_name : '',
            'status'           => (string) $r->status,
            'decision_date'    => $r->decision_date !== null ? (string) $r->decision_date : '',
            'expiry_date'      => $r->expiry_date !== null ? (string) $r->expiry_date : '',
            'consent_basis'    => (string) $r->consent_basis,
            'data_sensitivity' => (string) $r->data_sensitivity,
            'notes'            => $r->notes !== null ? (string) $r->notes : '',
            'dmp_id'           => $r->dmp_id !== null ? (int) $r->dmp_id : null,
            'owner_id'         => $r->owner_id !== null ? (int) $r->owner_id : null,
            'created_by'       => $r->created_by !== null ? (int) $r->created_by : null,
            'created_at'       => (string) ($r->created_at ?? ''),
            'updated_at'       => (string) ($r->updated_at ?? ''),
        ];
    }
}
