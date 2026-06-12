<?php

/**
 * ResearchTeamService - Heratio ahg-research
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
 * heratio#1222 - Research OS: Research Team & Collaborators register.
 *
 * The PEOPLE register for a research project: who is on the team and in what
 * capacity (name, role, affiliation, email, ORCID), so a project's contributors
 * are documented alongside its DMP, outputs, ethics and funding. This is the
 * broader CONTRIBUTOR list - co-investigators, students, partners, technicians
 * and external collaborators - and is DISTINCT from the project's single
 * owner/researcher concept the portal already carries; it documents everyone
 * else who contributes, it does not replace the owner.
 *
 * Mirrors ResearchFundingService exactly: scoped to a project, dropdown-backed
 * taxonomies (never ENUM), a machine-readable JSON export, and a per-project
 * summary. Every read is Schema::hasTable-guarded and try/catch-wrapped so a
 * partial install degrades cleanly rather than 500ing. No live writes outside
 * the one NEW research_team_member table; no ALTER of any existing table.
 *
 * International and jurisdiction-neutral: NO country, institution or registry is
 * assumed or defaulted. Affiliation is free-text DATA. The ORCID is stored as
 * the bare iD and rendered as a link to https://orcid.org/{orcid} - never
 * fetched. The role taxonomy is informed by the international CRediT contributor-
 * roles taxonomy, but the detailed contribution is kept as free text.
 */
class ResearchTeamService
{
    public const ROLE_TAXONOMY   = 'research_team_role';
    public const STATUS_TAXONOMY = 'research_team_status';

    /** Roles treated as a project lead when is_lead is not explicitly set. */
    public const LEAD_ROLES = ['principal_investigator'];

    // ---------------------------------------------------------------------
    // Team members (CRUD)
    // ---------------------------------------------------------------------

    /** Team members on a project (leads first, then by role, then name). */
    public function listMembers(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_team_member')) {
                return [];
            }

            return DB::table('research_team_member')
                ->where('project_id', $projectId)
                ->orderByDesc('is_lead')
                ->orderBy('person_name')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($r) => $this->rowToArray($r))
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A single team member as an array, scoped to its project, or null. */
    public function getMember(int $id, ?int $projectId = null): ?array
    {
        try {
            if (! Schema::hasTable('research_team_member')) {
                return null;
            }
            $q = DB::table('research_team_member')->where('id', $id);
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
     * Create a team member for a project. Returns the new id, or null on failure.
     *
     * @param  array<string,mixed>  $data
     */
    public function createMember(int $projectId, ?int $researcherId, array $data): ?int
    {
        try {
            if (! Schema::hasTable('research_team_member')) {
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

            return (int) DB::table('research_team_member')->insertGetId($row);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] team createMember failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Update a team member, scoped to its project.
     *
     * @param  array<string,mixed>  $data
     */
    public function updateMember(int $id, int $projectId, array $data): bool
    {
        try {
            if (! Schema::hasTable('research_team_member')) {
                return false;
            }
            $owns = DB::table('research_team_member')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }

            $row = array_merge($this->normalise($data), ['updated_at' => now()]);
            DB::table('research_team_member')->where('id', $id)->where('project_id', $projectId)->update($row);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] team updateMember failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Delete a team member, scoped to its project. */
    public function deleteMember(int $id, int $projectId): bool
    {
        try {
            if (! Schema::hasTable('research_team_member')) {
                return false;
            }
            $owns = DB::table('research_team_member')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }
            DB::table('research_team_member')->where('id', $id)->where('project_id', $projectId)->delete();

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] team deleteMember failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Coerce validated request data into a writeable column map. Dropdown-backed
     * values (role, status) are constrained to their known option codes; free-text
     * is trimmed and length-capped. The ORCID is normalised to the bare canonical
     * form (####-####-####-###X) or dropped if it does not match - never fetched.
     * The is_lead flag is taken from the form, but a principal-investigator role is
     * always treated as a lead.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function normalise(array $data): array
    {
        $role = (string) ($data['role'] ?? 'researcher');
        if (! array_key_exists($role, $this->roleOptions())) {
            $role = 'researcher';
        }

        $status = (string) ($data['status'] ?? 'active');
        if (! array_key_exists($status, $this->statusOptions())) {
            $status = 'active';
        }

        $isLead = ! empty($data['is_lead']) ? 1 : 0;
        if (in_array($role, self::LEAD_ROLES, true)) {
            $isLead = 1;
        }

        return [
            'person_name'       => mb_substr(trim((string) ($data['person_name'] ?? '')), 0, 512),
            'role'              => $role,
            'affiliation'       => $this->trimOrNull($data['affiliation'] ?? null, 512),
            'email'             => $this->trimOrNull($data['email'] ?? null, 255),
            'orcid'             => $this->normaliseOrcid($data['orcid'] ?? null),
            'is_lead'           => $isLead,
            'contribution_note' => isset($data['contribution_note']) && trim((string) $data['contribution_note']) !== ''
                ? mb_substr((string) $data['contribution_note'], 0, 65000) : null,
            'start_date'        => $this->dateOrNull($data['start_date'] ?? null),
            'end_date'          => $this->dateOrNull($data['end_date'] ?? null),
            'status'            => $status,
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

    // ---------------------------------------------------------------------
    // ORCID handling (stored as the bare iD; rendered as a link; never fetched)
    // ---------------------------------------------------------------------

    /**
     * Normalise an ORCID input to the bare canonical form ####-####-####-###X, or
     * null when it is blank or does not match. Accepts a full https://orcid.org/
     * URL, an orcid.org/ path, or a run of 16 digits, and hyphenates them. ORCID
     * iDs are 16 characters: 15 digits plus a final check character that may be a
     * digit or 'X'. No external lookup is ever performed - format only.
     */
    public function normaliseOrcid(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        // Strip a leading URL / host if the user pasted the full link.
        $raw = preg_replace('#^https?://#i', '', $raw) ?? $raw;
        $raw = preg_replace('#^(www\.)?orcid\.org/#i', '', $raw) ?? $raw;

        // Keep digits and a trailing X (uppercased); drop hyphens / spaces.
        $compact = strtoupper(str_replace(['-', ' '], '', $raw));
        if (! preg_match('/^[0-9]{15}[0-9X]$/', $compact)) {
            return null;
        }

        return substr($compact, 0, 4) . '-' . substr($compact, 4, 4) . '-'
            . substr($compact, 8, 4) . '-' . substr($compact, 12, 4);
    }

    /**
     * Is a candidate ORCID string acceptable for storage? Blank is allowed
     * (returns true); a non-blank value is valid only if it normalises to the
     * canonical 16-character form. Used to back form validation without any
     * external fetch.
     */
    public function isValidOrcid(mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return true;
        }

        return $this->normaliseOrcid($value) !== null;
    }

    /** The public ORCID resolver URL for a bare iD, or null when there is none. */
    public function orcidUrl(?string $orcid): ?string
    {
        $orcid = $orcid !== null ? trim($orcid) : '';
        if ($orcid === '') {
            return null;
        }

        return 'https://orcid.org/' . $orcid;
    }

    // ---------------------------------------------------------------------
    // Per-project summary (counts by role; leads highlighted)
    // ---------------------------------------------------------------------

    /**
     * Summary of a project's team: total member count, counts by role, counts by
     * status, the number of active members, and the list of leads (name + role
     * label) so they can be highlighted. Computed from the in-memory member list
     * so it does not re-query per metric.
     *
     * @return array{total:int,active:int,by_role:array<int,array{code:string,label:string,count:int}>,by_status:array<int,array{code:string,label:string,count:int}>,leads:array<int,array{id:int,name:string,role:string,role_label:string}>}
     */
    public function summary(int $projectId): array
    {
        $empty = ['total' => 0, 'active' => 0, 'by_role' => [], 'by_status' => [], 'leads' => []];
        try {
            if (! Schema::hasTable('research_team_member')) {
                return $empty;
            }

            $members = $this->listMembers($projectId);

            return $this->summaryFromMembers($members);
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * Per-project summary from an in-memory member list (used by both summary()
     * and the export so neither re-queries).
     *
     * @param  array<int,array<string,mixed>>  $members
     * @return array{total:int,active:int,by_role:array<int,array{code:string,label:string,count:int}>,by_status:array<int,array{code:string,label:string,count:int}>,leads:array<int,array{id:int,name:string,role:string,role_label:string}>}
     */
    private function summaryFromMembers(array $members): array
    {
        $roleLabels   = $this->roleOptions();
        $statusLabels = $this->statusOptions();

        $roleCounts   = [];
        $statusCounts = [];
        $active       = 0;
        $leads        = [];

        foreach ($members as $m) {
            $role   = (string) ($m['role'] ?? '');
            $status = (string) ($m['status'] ?? '');
            $roleCounts[$role]     = ($roleCounts[$role] ?? 0) + 1;
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if ($status === 'active') {
                $active++;
            }
            if (! empty($m['is_lead'])) {
                $leads[] = [
                    'id'         => (int) ($m['id'] ?? 0),
                    'name'       => (string) ($m['person_name'] ?? ''),
                    'role'       => $role,
                    'role_label' => $roleLabels[$role] ?? ucfirst(str_replace('_', ' ', $role)),
                ];
            }
        }

        return [
            'total'     => count($members),
            'active'    => $active,
            'by_role'   => $this->labelCounts($roleCounts, $roleLabels),
            'by_status' => $this->labelCounts($statusCounts, $statusLabels),
            'leads'     => $leads,
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
     * Build a machine-readable export of a project's team. Each entry carries the
     * person's name, role (code + human label), affiliation, email, the ORCID as
     * both the bare iD and a resolvable URL, the lead flag, the free-text
     * contribution note, the involvement period and status. The shape is a
     * top-level object with a "project" block, a generated_at timestamp, a
     * "count", a "summary" block (counts by role/status, active count, leads) and
     * a "team" array.
     *
     * @param  array<int,array<string,mixed>>  $members
     * @param  object|null  $project
     * @return array<string,mixed>
     */
    public function buildExport(array $members, ?object $project = null): array
    {
        $roleLabels   = $this->roleOptions();
        $statusLabels = $this->statusOptions();

        $items = [];
        foreach ($members as $m) {
            $role   = (string) ($m['role'] ?? '');
            $status = (string) ($m['status'] ?? '');
            $orcid  = (string) ($m['orcid'] ?? '');
            $items[] = [
                'id'                => (int) ($m['id'] ?? 0),
                'person_name'       => (string) ($m['person_name'] ?? ''),
                'role'              => $role,
                'role_label'        => $roleLabels[$role] ?? $role,
                'affiliation'       => (string) ($m['affiliation'] ?? ''),
                'email'             => (string) ($m['email'] ?? ''),
                'orcid'             => $orcid !== '' ? $orcid : null,
                'orcid_url'         => $orcid !== '' ? $this->orcidUrl($orcid) : null,
                'is_lead'           => ! empty($m['is_lead']),
                'contribution_note' => (string) ($m['contribution_note'] ?? ''),
                'start_date'        => (string) ($m['start_date'] ?? ''),
                'end_date'          => (string) ($m['end_date'] ?? ''),
                'status'            => $status,
                'status_label'      => $statusLabels[$status] ?? $status,
            ];
        }

        $summary = $this->summaryFromMembers($members);

        return [
            'project' => [
                'id'    => isset($project->id) ? (int) $project->id : null,
                'title' => isset($project->title) ? (string) $project->title : '',
            ],
            'generated_at' => now()->toIso8601String(),
            'count'        => count($items),
            'summary'      => [
                'total'     => $summary['total'],
                'active'    => $summary['active'],
                'by_role'   => $summary['by_role'],
                'by_status' => $summary['by_status'],
                'leads'     => $summary['leads'],
            ],
            'team' => $items,
        ];
    }

    // ---------------------------------------------------------------------
    // Dropdown-backed taxonomies (Dropdown Manager - never ENUM)
    // ---------------------------------------------------------------------

    /**
     * Role options [code => label], with a safe fallback. The codes are informed
     * by the international CRediT contributor-roles taxonomy and common project
     * team roles - never a hardcoded <option> list in a view.
     *
     * @return array<string,string>
     */
    public function roleOptions(): array
    {
        return $this->dropdownOptions(self::ROLE_TAXONOMY, [
            'principal_investigator' => 'Principal investigator',
            'co_investigator'        => 'Co-investigator',
            'researcher'             => 'Researcher',
            'student'                => 'Student',
            'advisor'                => 'Advisor',
            'partner'                => 'Partner',
            'technician'             => 'Technician',
            'other'                  => 'Other',
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
            'active'   => 'Active',
            'inactive' => 'Inactive',
            'former'   => 'Former',
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
            'id'                => (int) $r->id,
            'project_id'        => (int) $r->project_id,
            'person_name'       => (string) $r->person_name,
            'role'              => (string) $r->role,
            'affiliation'       => $r->affiliation !== null ? (string) $r->affiliation : '',
            'email'             => $r->email !== null ? (string) $r->email : '',
            'orcid'             => $r->orcid !== null ? (string) $r->orcid : '',
            'is_lead'           => (int) $r->is_lead === 1,
            'contribution_note' => $r->contribution_note !== null ? (string) $r->contribution_note : '',
            'start_date'        => $r->start_date !== null ? (string) $r->start_date : '',
            'end_date'          => $r->end_date !== null ? (string) $r->end_date : '',
            'status'            => (string) $r->status,
            'owner_id'          => $r->owner_id !== null ? (int) $r->owner_id : null,
            'created_by'        => $r->created_by !== null ? (int) $r->created_by : null,
            'created_at'        => (string) ($r->created_at ?? ''),
            'updated_at'        => (string) ($r->updated_at ?? ''),
        ];
    }
}
