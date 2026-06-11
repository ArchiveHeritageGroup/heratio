<?php

/**
 * MethodStudioService - Heratio ahg-research
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
 * heratio#1231 - Research OS #9: Method Design Studio (ROS Stage 10).
 *
 * Method support is delivered as DISCIPLINE TEMPLATES rather than an attempt to
 * natively encode every methodology. A template carries structured guidance
 * prompts for each design area (design, sampling, data sources, instruments,
 * coding framework, variables, validity, reliability, ethics, consent, bias
 * control, reproducibility, data management). A researcher starts a per-project
 * Method Protocol from a template, which pre-fills the guidance areas; their
 * answers live in the protocol's `fields` JSON.
 *
 * The protocol is "write once, reuse": getProtocolForReuse() returns it as
 * clean structured data so other features (thesis methodology chapter, grant
 * application, ethics application) can pull it without re-deriving anything.
 *
 * Every read is Schema::hasTable-guarded and try/catch wrapped so a partial
 * install never 500s. No live writes outside the protocol tables; no ALTER.
 */
class MethodStudioService
{
    /** Canonical, ordered guidance areas. Used to render a protocol even when a
     *  template's stored guidance is sparse, and as a jurisdiction-neutral fallback. */
    public const AREAS = [
        'design'           => 'Research design',
        'sampling'         => 'Sampling / selection',
        'data_sources'     => 'Data sources',
        'instruments'      => 'Instruments',
        'coding_framework' => 'Coding / analysis framework',
        'variables'        => 'Variables / constructs',
        'validity'         => 'Validity',
        'reliability'      => 'Reliability',
        'ethics'           => 'Ethics',
        'consent'          => 'Consent',
        'bias_control'     => 'Bias control',
        'reproducibility'  => 'Reproducibility',
        'data_management'  => 'Data management',
    ];

    public const STATUS_TAXONOMY = 'method_protocol_status';

    // ---------------------------------------------------------------------
    // Templates
    // ---------------------------------------------------------------------

    /** Active discipline templates for the picker. Empty array if not installed. */
    public function listTemplates(): array
    {
        try {
            if (! Schema::hasTable('research_method_template')) {
                return [];
            }

            return DB::table('research_method_template')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($t) => $this->decodeTemplate($t))
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** One template by code, or null. */
    public function getTemplate(string $code): ?array
    {
        try {
            if (! Schema::hasTable('research_method_template')) {
                return null;
            }
            $row = DB::table('research_method_template')->where('code', $code)->first();

            return $row ? $this->decodeTemplate($row) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalise a template row into a PHP array with a fully-populated, ordered
     * `areas` map: [ area_key => ['label','prompt','placeholder'] ]. Any area the
     * stored guidance omits is back-filled from the canonical AREAS list so the
     * editor always renders the complete, jurisdiction-neutral set.
     */
    private function decodeTemplate(object $row): array
    {
        $guidance = [];
        if (! empty($row->guidance)) {
            $decoded = json_decode((string) $row->guidance, true);
            if (is_array($decoded)) {
                $guidance = $decoded;
            }
        }

        $areas = [];
        foreach (self::AREAS as $key => $defaultLabel) {
            $g = $guidance[$key] ?? [];
            $areas[$key] = [
                'label'       => (string) ($g['label'] ?? $defaultLabel),
                'prompt'      => (string) ($g['prompt'] ?? ''),
                'placeholder' => (string) ($g['placeholder'] ?? ''),
            ];
        }

        return [
            'code'        => (string) $row->code,
            'name'        => (string) $row->name,
            'discipline'  => (string) ($row->discipline ?? ''),
            'description' => (string) ($row->description ?? ''),
            'areas'       => $areas,
            'is_active'   => (int) ($row->is_active ?? 1),
            'sort_order'  => (int) ($row->sort_order ?? 100),
        ];
    }

    // ---------------------------------------------------------------------
    // Protocols
    // ---------------------------------------------------------------------

    /** Protocols belonging to a project (lightweight list rows). */
    public function listProtocols(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_method_protocol')) {
                return [];
            }

            return DB::table('research_method_protocol')
                ->where('project_id', $projectId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($p) => [
                    'id'            => (int) $p->id,
                    'project_id'    => (int) $p->project_id,
                    'template_code' => (string) $p->template_code,
                    'title'         => (string) $p->title,
                    'status'        => (string) $p->status,
                    'updated_at'    => (string) ($p->updated_at ?? $p->created_at ?? ''),
                ])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Raw protocol row decoded to an array (fields already json-decoded), or null. */
    public function getProtocol(int $id, ?int $projectId = null): ?array
    {
        try {
            if (! Schema::hasTable('research_method_protocol')) {
                return null;
            }
            $q = DB::table('research_method_protocol')->where('id', $id);
            if ($projectId !== null) {
                $q->where('project_id', $projectId);
            }
            $row = $q->first();
            if (! $row) {
                return null;
            }

            $fields = [];
            if (! empty($row->fields)) {
                $decoded = json_decode((string) $row->fields, true);
                if (is_array($decoded)) {
                    $fields = $decoded;
                }
            }

            return [
                'id'            => (int) $row->id,
                'project_id'    => (int) $row->project_id,
                'template_code' => (string) $row->template_code,
                'title'         => (string) $row->title,
                'fields'        => $fields,
                'status'        => (string) $row->status,
                'created_by'    => $row->created_by !== null ? (int) $row->created_by : null,
                'created_at'    => (string) ($row->created_at ?? ''),
                'updated_at'    => (string) ($row->updated_at ?? ''),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create a protocol for a project from a chosen template, pre-filling the
     * guidance areas as empty answer slots so the editor renders every area.
     * Returns the new protocol id, or null on failure.
     */
    public function createFromTemplate(int $projectId, string $templateCode, ?int $researcherId, ?string $title = null): ?int
    {
        try {
            if (! Schema::hasTable('research_method_protocol')) {
                return null;
            }
            $template = $this->getTemplate($templateCode);
            if (! $template) {
                return null;
            }

            // Pre-fill: one empty answer slot per guidance area, in canonical order.
            $fields = [];
            foreach (array_keys($template['areas']) as $areaKey) {
                $fields[$areaKey] = '';
            }

            $now = now();
            $id = DB::table('research_method_protocol')->insertGetId([
                'project_id'    => $projectId,
                'template_code' => $templateCode,
                'title'         => $title !== null && trim($title) !== ''
                    ? mb_substr(trim($title), 0, 255)
                    : ('Method Protocol - ' . $template['name']),
                'fields'        => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'        => 'draft',
                'created_by'    => $researcherId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            return (int) $id;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Save researcher answers + title + status onto an existing protocol.
     * Only the named NEW table is written. Returns true on success.
     *
     * @param array<string,string> $fields  area-key => answer text
     */
    public function saveProtocol(int $id, int $projectId, array $fields, ?string $title = null, ?string $status = null): bool
    {
        try {
            if (! Schema::hasTable('research_method_protocol')) {
                return false;
            }
            $existing = DB::table('research_method_protocol')
                ->where('id', $id)->where('project_id', $projectId)->first();
            if (! $existing) {
                return false;
            }

            // Keep only known area keys; coerce to trimmed strings.
            $clean = [];
            foreach (array_keys(self::AREAS) as $areaKey) {
                if (array_key_exists($areaKey, $fields)) {
                    $clean[$areaKey] = is_string($fields[$areaKey]) ? trim($fields[$areaKey]) : '';
                }
            }

            $update = [
                'fields'     => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];
            if ($title !== null && trim($title) !== '') {
                $update['title'] = mb_substr(trim($title), 0, 255);
            }
            if ($status !== null && in_array($status, $this->statusCodes(), true)) {
                $update['status'] = $status;
            }

            DB::table('research_method_protocol')
                ->where('id', $id)->where('project_id', $projectId)
                ->update($update);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reuse read model: return a protocol as clean, structured data that other
     * features (thesis methodology chapter, grant, ethics application) can pull -
     * each guidance area paired with its label, prompt, and the researcher's
     * answer. Write once here, reference elsewhere. Returns null if not found.
     *
     * @return array{protocol:array,template:?array,areas:array<int,array{key:string,label:string,prompt:string,answer:string}>}|null
     */
    public function getProtocolForReuse(int $id, ?int $projectId = null): ?array
    {
        $protocol = $this->getProtocol($id, $projectId);
        if (! $protocol) {
            return null;
        }
        $template = $this->getTemplate($protocol['template_code']);

        // Prefer the template's ordered areas; fall back to the canonical list.
        $areaDefs = $template['areas'] ?? null;
        if (! is_array($areaDefs)) {
            $areaDefs = [];
            foreach (self::AREAS as $key => $label) {
                $areaDefs[$key] = ['label' => $label, 'prompt' => '', 'placeholder' => ''];
            }
        }

        $areas = [];
        foreach ($areaDefs as $key => $def) {
            $areas[] = [
                'key'    => (string) $key,
                'label'  => (string) ($def['label'] ?? $key),
                'prompt' => (string) ($def['prompt'] ?? ''),
                'answer' => (string) ($protocol['fields'][$key] ?? ''),
            ];
        }

        return [
            'protocol' => [
                'id'            => $protocol['id'],
                'project_id'    => $protocol['project_id'],
                'template_code' => $protocol['template_code'],
                'title'         => $protocol['title'],
                'status'        => $protocol['status'],
                'updated_at'    => $protocol['updated_at'],
            ],
            'template' => $template ? [
                'code'       => $template['code'],
                'name'       => $template['name'],
                'discipline' => $template['discipline'],
            ] : null,
            'areas' => $areas,
        ];
    }

    // ---------------------------------------------------------------------
    // Status taxonomy (Dropdown Manager - never ENUM)
    // ---------------------------------------------------------------------

    /** Status options [code => label] from ahg_dropdown, with a safe fallback. */
    public function statusOptions(): array
    {
        $fallback = ['draft' => 'Draft', 'in_review' => 'In Review', 'final' => 'Final'];
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return $fallback;
            }
            $rows = DB::table('ahg_dropdown')
                ->where('taxonomy', self::STATUS_TAXONOMY)
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

    /** Valid status codes (for whitelist on save). */
    private function statusCodes(): array
    {
        return array_keys($this->statusOptions());
    }
}
