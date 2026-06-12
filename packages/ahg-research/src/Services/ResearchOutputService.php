<?php

/**
 * ResearchOutputService - Heratio ahg-research
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
 * heratio#1222 - Research OS: Research Outputs register (CRIS / RIM).
 *
 * A register of the scholarly outputs a research project produces - journal
 * articles, datasets, software, presentations, theses, reports, book chapters
 * and more. Each output carries a persistent identifier (DOI, handle, ISBN, URL)
 * resolved to a citable, clickable link, and can optionally reference the
 * project's Data Management Plan (the sibling research_dmp slice).
 *
 * Mirrors DmpService exactly: scoped to a project, dropdown-backed taxonomies
 * (never ENUM), a machine-readable JSON export, and a per-project summary. Every
 * read is Schema::hasTable-guarded and try/catch-wrapped so a partial install
 * degrades cleanly rather than 500ing. No live writes outside the one NEW
 * research_output table; no ALTER of any existing table.
 */
class ResearchOutputService
{
    public const TYPE_TAXONOMY            = 'research_output_type';
    public const IDENTIFIER_TYPE_TAXONOMY = 'research_output_identifier_type';
    public const STATUS_TAXONOMY          = 'research_output_status';

    // ---------------------------------------------------------------------
    // Outputs (CRUD)
    // ---------------------------------------------------------------------

    /** Outputs on a project (lightweight list rows, newest first). */
    public function listOutputs(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_output')) {
                return [];
            }

            return DB::table('research_output')
                ->where('project_id', $projectId)
                ->orderByRaw('output_date IS NULL, output_date DESC')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($o) => $this->rowToArray($o))
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A single output as an array, scoped to its project, or null. */
    public function getOutput(int $id, ?int $projectId = null): ?array
    {
        try {
            if (! Schema::hasTable('research_output')) {
                return null;
            }
            $q = DB::table('research_output')->where('id', $id);
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
     * Create an output for a project. Returns the new id, or null on failure.
     *
     * @param  array<string,mixed>  $data
     */
    public function createOutput(int $projectId, ?int $researcherId, array $data): ?int
    {
        try {
            if (! Schema::hasTable('research_output')) {
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

            return (int) DB::table('research_output')->insertGetId($row);
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] output createOutput failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Update an output, scoped to its project.
     *
     * @param  array<string,mixed>  $data
     */
    public function updateOutput(int $id, int $projectId, array $data): bool
    {
        try {
            if (! Schema::hasTable('research_output')) {
                return false;
            }
            $owns = DB::table('research_output')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }

            $row = array_merge($this->normalise($projectId, $data), ['updated_at' => now()]);
            DB::table('research_output')->where('id', $id)->where('project_id', $projectId)->update($row);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] output updateOutput failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Delete an output, scoped to its project. */
    public function deleteOutput(int $id, int $projectId): bool
    {
        try {
            if (! Schema::hasTable('research_output')) {
                return false;
            }
            $owns = DB::table('research_output')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }
            DB::table('research_output')->where('id', $id)->where('project_id', $projectId)->delete();

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] output deleteOutput failed: ' . $e->getMessage());

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
        $type   = (string) ($data['output_type'] ?? 'journal_article');
        if (! array_key_exists($type, $this->typeOptions())) {
            $type = 'journal_article';
        }

        $status = (string) ($data['status'] ?? 'planned');
        if (! array_key_exists($status, $this->statusOptions())) {
            $status = 'planned';
        }

        $idType = isset($data['identifier_type']) && $data['identifier_type'] !== null
            ? (string) $data['identifier_type'] : null;
        if ($idType !== null && ! array_key_exists($idType, $this->identifierTypeOptions())) {
            $idType = 'other';
        }

        return [
            'output_type'     => $type,
            'title'           => mb_substr(trim((string) ($data['title'] ?? '')), 0, 512),
            'authors'         => $this->trimOrNull($data['authors'] ?? null, 1024),
            'venue'           => $this->trimOrNull($data['venue'] ?? null, 512),
            'identifier_type' => $idType,
            'identifier'      => $this->trimOrNull($data['identifier'] ?? null, 512),
            'identifier_url'  => $this->trimOrNull($data['identifier_url'] ?? null, 1024),
            'output_date'     => $this->dateOrNull($data['output_date'] ?? null),
            'status'          => $status,
            'notes'           => isset($data['notes']) && trim((string) $data['notes']) !== ''
                ? mb_substr((string) $data['notes'], 0, 65000) : null,
            'dmp_id'          => $this->validDmpId($data['dmp_id'] ?? null, $projectId),
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
    // Identifier resolver (DOI / handle / ISBN / URL -> resolvable link)
    // ---------------------------------------------------------------------

    /**
     * Resolve an output's persistent identifier to a clickable URL. This builds
     * a link only - it never performs an external fetch. Precedence: an explicit
     * identifier_url wins; otherwise the type + identifier yields a canonical
     * resolver URL. Returns null when nothing resolvable is present.
     *
     * @param  array<string,mixed>  $output
     */
    public function resolveUrl(array $output): ?string
    {
        $explicit = trim((string) ($output['identifier_url'] ?? ''));
        if ($explicit !== '') {
            return $this->ensureScheme($explicit);
        }

        $type = (string) ($output['identifier_type'] ?? '');
        $id   = trim((string) ($output['identifier'] ?? ''));
        if ($id === '') {
            return null;
        }

        return match ($type) {
            'doi'    => 'https://doi.org/' . $this->stripDoiPrefix($id),
            'handle' => 'https://hdl.handle.net/' . ltrim($id, '/'),
            'isbn'   => 'https://search.worldcat.org/search?q=bn:' . rawurlencode(preg_replace('/[^0-9Xx]/', '', $id) ?: $id),
            'url'    => $this->ensureScheme($id),
            default  => $this->looksLikeUrl($id) ? $this->ensureScheme($id) : null,
        };
    }

    /** Strip a leading doi:, DOI:, or https://doi.org/ prefix from a DOI value. */
    private function stripDoiPrefix(string $doi): string
    {
        $doi = trim($doi);
        $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi) ?? $doi;
        $doi = preg_replace('/^doi:\s*/i', '', $doi) ?? $doi;

        return ltrim($doi, '/');
    }

    private function looksLikeUrl(string $v): bool
    {
        return (bool) preg_match('#^(https?://|www\.)#i', $v);
    }

    private function ensureScheme(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return 'https://' . ltrim($url, '/');
    }

    // ---------------------------------------------------------------------
    // Per-project summary (counts by type)
    // ---------------------------------------------------------------------

    /**
     * Summary of a project's outputs: total, counts by type, counts by status.
     * The by-type list is ordered by the type taxonomy so a stable, labelled
     * breakdown renders even for types with a zero count is NOT included - only
     * present types are returned, each carrying its human label.
     *
     * @return array{total:int,by_type:array<int,array{code:string,label:string,count:int}>,by_status:array<int,array{code:string,label:string,count:int}>}
     */
    public function summary(int $projectId): array
    {
        $empty = ['total' => 0, 'by_type' => [], 'by_status' => []];
        try {
            if (! Schema::hasTable('research_output')) {
                return $empty;
            }

            $total = (int) DB::table('research_output')->where('project_id', $projectId)->count();

            $typeRows = DB::table('research_output')
                ->where('project_id', $projectId)
                ->select('output_type', DB::raw('COUNT(*) as c'))
                ->groupBy('output_type')
                ->pluck('c', 'output_type');

            $statusRows = DB::table('research_output')
                ->where('project_id', $projectId)
                ->select('status', DB::raw('COUNT(*) as c'))
                ->groupBy('status')
                ->pluck('c', 'status');

            $typeLabels   = $this->typeOptions();
            $statusLabels = $this->statusOptions();

            $byType = [];
            foreach ($typeLabels as $code => $label) {
                if (isset($typeRows[$code])) {
                    $byType[] = ['code' => $code, 'label' => $label, 'count' => (int) $typeRows[$code]];
                }
            }
            // Any orphan types not in the taxonomy still surface.
            foreach ($typeRows as $code => $c) {
                if (! isset($typeLabels[$code])) {
                    $byType[] = ['code' => (string) $code, 'label' => ucfirst(str_replace('_', ' ', (string) $code)), 'count' => (int) $c];
                }
            }

            $byStatus = [];
            foreach ($statusLabels as $code => $label) {
                if (isset($statusRows[$code])) {
                    $byStatus[] = ['code' => $code, 'label' => $label, 'count' => (int) $statusRows[$code]];
                }
            }
            foreach ($statusRows as $code => $c) {
                if (! isset($statusLabels[$code])) {
                    $byStatus[] = ['code' => (string) $code, 'label' => ucfirst(str_replace('_', ' ', (string) $code)), 'count' => (int) $c];
                }
            }

            return ['total' => $total, 'by_type' => $byType, 'by_status' => $byStatus];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    // ---------------------------------------------------------------------
    // Machine-readable export
    // ---------------------------------------------------------------------

    /**
     * Build a machine-readable export of a project's outputs. Each entry carries
     * the type, title, identifier, the resolvable URL and the date, plus the
     * full record fields. The shape is a top-level object with a "project" block,
     * a generated_at timestamp, a "count" and an "outputs" array.
     *
     * @param  array<int,array<string,mixed>>  $outputs
     * @param  object|null  $project
     * @return array<string,mixed>
     */
    public function buildExport(array $outputs, ?object $project = null): array
    {
        $typeLabels   = $this->typeOptions();
        $idTypeLabels = $this->identifierTypeOptions();
        $statusLabels = $this->statusOptions();

        $items = [];
        foreach ($outputs as $o) {
            $type   = (string) ($o['output_type'] ?? '');
            $idType = (string) ($o['identifier_type'] ?? '');
            $status = (string) ($o['status'] ?? '');
            $items[] = [
                'id'              => (int) ($o['id'] ?? 0),
                'type'            => $type,
                'type_label'      => $typeLabels[$type] ?? $type,
                'title'           => (string) ($o['title'] ?? ''),
                'authors'         => (string) ($o['authors'] ?? ''),
                'venue'           => (string) ($o['venue'] ?? ''),
                'identifier_type' => $idType,
                'identifier'      => (string) ($o['identifier'] ?? ''),
                'identifier_label'=> $idType !== '' ? ($idTypeLabels[$idType] ?? $idType) : '',
                'url'             => $this->resolveUrl($o),
                'date'            => (string) ($o['output_date'] ?? ''),
                'status'          => $status,
                'status_label'    => $statusLabels[$status] ?? $status,
                'dmp_id'          => $o['dmp_id'] ?? null,
                'notes'           => (string) ($o['notes'] ?? ''),
            ];
        }

        return [
            'project' => [
                'id'    => isset($project->id) ? (int) $project->id : null,
                'title' => isset($project->title) ? (string) $project->title : '',
            ],
            'generated_at' => now()->toIso8601String(),
            'count'        => count($items),
            'outputs'      => $items,
        ];
    }

    // ---------------------------------------------------------------------
    // Dropdown-backed taxonomies (Dropdown Manager - never ENUM)
    // ---------------------------------------------------------------------

    /** Output-type options [code => label], with a safe fallback. */
    public function typeOptions(): array
    {
        return $this->dropdownOptions(self::TYPE_TAXONOMY, [
            'journal_article' => 'Journal article', 'dataset' => 'Dataset', 'software' => 'Software',
            'presentation' => 'Presentation', 'thesis' => 'Thesis', 'report' => 'Report',
            'chapter' => 'Book chapter', 'other' => 'Other',
        ]);
    }

    /** Identifier-type options [code => label], with a safe fallback. */
    public function identifierTypeOptions(): array
    {
        return $this->dropdownOptions(self::IDENTIFIER_TYPE_TAXONOMY, [
            'doi' => 'DOI', 'handle' => 'Handle', 'isbn' => 'ISBN', 'url' => 'URL', 'other' => 'Other',
        ]);
    }

    /** Status options [code => label], with a safe fallback. */
    public function statusOptions(): array
    {
        return $this->dropdownOptions(self::STATUS_TAXONOMY, [
            'planned' => 'Planned', 'in_progress' => 'In progress', 'published' => 'Published',
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
    private function rowToArray(object $o): array
    {
        return [
            'id'              => (int) $o->id,
            'project_id'      => (int) $o->project_id,
            'output_type'     => (string) $o->output_type,
            'title'           => (string) $o->title,
            'authors'         => $o->authors !== null ? (string) $o->authors : '',
            'venue'           => $o->venue !== null ? (string) $o->venue : '',
            'identifier_type' => $o->identifier_type !== null ? (string) $o->identifier_type : '',
            'identifier'      => $o->identifier !== null ? (string) $o->identifier : '',
            'identifier_url'  => $o->identifier_url !== null ? (string) $o->identifier_url : '',
            'output_date'     => $o->output_date !== null ? (string) $o->output_date : '',
            'status'          => (string) $o->status,
            'notes'           => $o->notes !== null ? (string) $o->notes : '',
            'dmp_id'          => $o->dmp_id !== null ? (int) $o->dmp_id : null,
            'owner_id'        => $o->owner_id !== null ? (int) $o->owner_id : null,
            'created_by'      => $o->created_by !== null ? (int) $o->created_by : null,
            'created_at'      => (string) ($o->created_at ?? ''),
            'updated_at'      => (string) ($o->updated_at ?? ''),
        ];
    }
}
