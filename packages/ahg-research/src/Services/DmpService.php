<?php

/**
 * DmpService - Heratio ahg-research
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
 * heratio#1222 - Research OS: Data Management Plan (DMP) Builder.
 *
 * A researcher-facing DMP builder scoped to a research project. The plan is
 * structured on the RDA / Science Europe machine-actionable DMP (maDMP) common
 * standard - the recognised question set: data description, the four FAIR
 * dimensions (findable, accessible, interoperable, reusable), storage and
 * backup, preservation and retention, sharing and access, costs,
 * responsibilities, and ethics / legal.
 *
 * International and funder-neutral: the funder is recorded as DATA on the plan,
 * never assumed and never defaulted to any one jurisdiction.
 *
 * Resilient: every read is Schema::hasTable-guarded and try/catch-wrapped, so a
 * partial install degrades cleanly rather than 500ing. No live writes outside
 * the two NEW dmp tables; no ALTER of any table.
 */
class DmpService
{
    public const STATUS_TAXONOMY          = 'dmp_status';
    public const FUNDER_TEMPLATE_TAXONOMY = 'dmp_funder_template';

    /**
     * The maDMP common-standard section list (RDA / Science Europe). Stable
     * keys, ordered. The labels and hints are jurisdiction-neutral. A site can
     * relabel or extend per plan via the section rows; this is the canonical
     * seed template applied when a plan is created.
     *
     * @var array<int,array{key:string,label:string,hint:string}>
     */
    public const MADMP_SECTIONS = [
        ['key' => 'data_description', 'label' => 'Data description and collection',
         'hint' => 'What data will you collect, generate or reuse? Types, formats, volumes and how the data is produced.'],
        ['key' => 'documentation',   'label' => 'Documentation and data quality',
         'hint' => 'What metadata, README files and standards describe the data so others can understand it? How is quality assured?'],
        ['key' => 'findable',        'label' => 'FAIR - Findable',
         'hint' => 'Will datasets carry persistent identifiers (e.g. DOIs) and rich metadata, indexed in a searchable resource?'],
        ['key' => 'accessible',      'label' => 'FAIR - Accessible',
         'hint' => 'How and under what conditions will the data be accessed? Open, restricted or closed, and through which protocol or repository?'],
        ['key' => 'interoperable',   'label' => 'FAIR - Interoperable',
         'hint' => 'Which open, standard formats, vocabularies and ontologies make the data combinable with other datasets?'],
        ['key' => 'reusable',        'label' => 'FAIR - Reusable',
         'hint' => 'Which licence and provenance information allow others to reuse the data? How long will it remain reusable?'],
        ['key' => 'storage_backup',  'label' => 'Storage and backup during the project',
         'hint' => 'Where is the data stored and backed up during the project, and how is security and recovery handled?'],
        ['key' => 'preservation',    'label' => 'Preservation and retention',
         'hint' => 'Which data will be preserved long term, in which repository, and for how long (the retention period)?'],
        ['key' => 'sharing_access',  'label' => 'Data sharing and access control',
         'hint' => 'How and when will data be shared? Any embargo, access controls, or reasons some data cannot be shared.'],
        ['key' => 'ethics_legal',    'label' => 'Ethics, legal and privacy',
         'hint' => 'Consent, personal-data protection, sensitive data, intellectual-property and any jurisdictional obligations - jurisdiction-neutral.'],
        ['key' => 'responsibilities','label' => 'Responsibilities and resources',
         'hint' => 'Who is responsible for data management, and what resources (people, skills, infrastructure) are needed?'],
        ['key' => 'costs',           'label' => 'Costs',
         'hint' => 'What are the anticipated data-management costs (storage, curation, repository fees) and how are they covered?'],
    ];

    // ---------------------------------------------------------------------
    // Plans
    // ---------------------------------------------------------------------

    /** DMPs on a project (lightweight list rows). */
    public function listPlans(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_dmp')) {
                return [];
            }

            return DB::table('research_dmp')
                ->where('project_id', $projectId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($d) => $this->rowToArray($d))
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A single plan as an array, scoped to its project, or null. */
    public function getPlan(int $id, ?int $projectId = null): ?array
    {
        try {
            if (! Schema::hasTable('research_dmp')) {
                return null;
            }
            $q = DB::table('research_dmp')->where('id', $id);
            if ($projectId !== null) {
                $q->where('project_id', $projectId);
            }
            $row = $q->first();

            return $row ? $this->rowToArray($row) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Ordered section rows for a plan. */
    public function getSections(int $dmpId): array
    {
        try {
            if (! Schema::hasTable('research_dmp_section')) {
                return [];
            }

            return DB::table('research_dmp_section')
                ->where('dmp_id', $dmpId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($s) => [
                    'id'          => (int) $s->id,
                    'dmp_id'      => (int) $s->dmp_id,
                    'section_key' => (string) $s->section_key,
                    'label'       => (string) $s->label,
                    'body'        => (string) ($s->body ?? ''),
                    'sort_order'  => (int) $s->sort_order,
                ])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Create a new DMP for a project, seeding the maDMP section set. Returns the
     * new plan id, or null on failure.
     *
     * @param  array<string,mixed>  $meta
     */
    public function createPlan(int $projectId, ?int $researcherId, array $meta): ?int
    {
        try {
            if (! Schema::hasTable('research_dmp') || ! Schema::hasTable('research_dmp_section')) {
                return null;
            }

            $title = isset($meta['title']) && trim((string) $meta['title']) !== ''
                ? mb_substr(trim((string) $meta['title']), 0, 255)
                : 'Data Management Plan';

            $now   = now();
            $dmpId = (int) DB::table('research_dmp')->insertGetId([
                'project_id'      => $projectId,
                'title'           => $title,
                'status'          => 'draft',
                'funder'          => isset($meta['funder']) ? mb_substr(trim((string) $meta['funder']), 0, 255) : null,
                'funder_template' => isset($meta['funder_template']) && $meta['funder_template'] !== ''
                    ? mb_substr((string) $meta['funder_template'], 0, 64) : null,
                'language'        => isset($meta['language']) && $meta['language'] !== ''
                    ? mb_substr((string) $meta['language'], 0, 12) : 'en',
                'contact_name'    => isset($meta['contact_name']) ? mb_substr(trim((string) $meta['contact_name']), 0, 255) : null,
                'contact_email'   => isset($meta['contact_email']) ? mb_substr(trim((string) $meta['contact_email']), 0, 255) : null,
                'owner_id'        => $researcherId,
                'created_by'      => $researcherId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            foreach (self::MADMP_SECTIONS as $i => $sec) {
                DB::table('research_dmp_section')->insert([
                    'dmp_id'      => $dmpId,
                    'section_key' => $sec['key'],
                    'label'       => mb_substr($sec['label'], 0, 190),
                    'body'        => null,
                    'sort_order'  => ($i + 1) * 10,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            return $dmpId;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] dmp createPlan failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Save plan meta (title, status, funder, contact, language). Only the NEW
     * plan table is written; the project itself is never touched.
     *
     * @param  array<string,mixed>  $meta
     */
    public function savePlanMeta(int $id, int $projectId, array $meta): bool
    {
        try {
            if (! Schema::hasTable('research_dmp')) {
                return false;
            }
            $existing = DB::table('research_dmp')
                ->where('id', $id)->where('project_id', $projectId)->first();
            if (! $existing) {
                return false;
            }

            $update = ['updated_at' => now()];
            if (array_key_exists('title', $meta) && trim((string) $meta['title']) !== '') {
                $update['title'] = mb_substr(trim((string) $meta['title']), 0, 255);
            }
            if (array_key_exists('status', $meta) && $meta['status'] !== null
                && in_array((string) $meta['status'], array_keys($this->statusOptions()), true)) {
                $update['status'] = (string) $meta['status'];
            }
            if (array_key_exists('funder', $meta)) {
                $update['funder'] = $meta['funder'] !== null && trim((string) $meta['funder']) !== ''
                    ? mb_substr(trim((string) $meta['funder']), 0, 255) : null;
            }
            if (array_key_exists('funder_template', $meta)) {
                $update['funder_template'] = $meta['funder_template'] !== null && $meta['funder_template'] !== ''
                    ? mb_substr((string) $meta['funder_template'], 0, 64) : null;
            }
            if (array_key_exists('language', $meta) && $meta['language'] !== null && $meta['language'] !== '') {
                $update['language'] = mb_substr((string) $meta['language'], 0, 12);
            }
            if (array_key_exists('contact_name', $meta)) {
                $update['contact_name'] = $meta['contact_name'] !== null && trim((string) $meta['contact_name']) !== ''
                    ? mb_substr(trim((string) $meta['contact_name']), 0, 255) : null;
            }
            if (array_key_exists('contact_email', $meta)) {
                $update['contact_email'] = $meta['contact_email'] !== null && trim((string) $meta['contact_email']) !== ''
                    ? mb_substr(trim((string) $meta['contact_email']), 0, 255) : null;
            }

            DB::table('research_dmp')->where('id', $id)->where('project_id', $projectId)->update($update);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] dmp savePlanMeta failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Save per-section bodies. $bodies is [sectionId => text]. Each section is
     * verified to belong to the plan (scoped through the dmp_id) before write.
     *
     * @param  array<int,string>  $bodies
     */
    public function saveSections(int $dmpId, int $projectId, array $bodies): bool
    {
        try {
            if (! Schema::hasTable('research_dmp_section') || ! Schema::hasTable('research_dmp')) {
                return false;
            }
            // Confirm the plan belongs to the project before writing any section.
            $owns = DB::table('research_dmp')
                ->where('id', $dmpId)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }

            $now = now();
            foreach ($bodies as $sectionId => $text) {
                DB::table('research_dmp_section')
                    ->where('id', (int) $sectionId)
                    ->where('dmp_id', $dmpId)
                    ->update([
                        'body'       => $text === '' ? null : mb_substr($text, 0, 65000),
                        'updated_at' => $now,
                    ]);
            }

            DB::table('research_dmp')->where('id', $dmpId)->update(['updated_at' => $now]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] dmp saveSections failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Delete a plan and its sections, scoped to the project. */
    public function deletePlan(int $id, int $projectId): bool
    {
        try {
            if (! Schema::hasTable('research_dmp')) {
                return false;
            }
            $owns = DB::table('research_dmp')
                ->where('id', $id)->where('project_id', $projectId)->exists();
            if (! $owns) {
                return false;
            }
            if (Schema::hasTable('research_dmp_section')) {
                DB::table('research_dmp_section')->where('dmp_id', $id)->delete();
            }
            DB::table('research_dmp')->where('id', $id)->where('project_id', $projectId)->delete();

            return true;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] dmp deletePlan failed: ' . $e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Completeness
    // ---------------------------------------------------------------------

    /**
     * Completeness indicator: how many of the maDMP sections carry an answer.
     *
     * @param  array<int,array<string,mixed>>  $sections
     * @return array{filled:int,total:int,pct:int}
     */
    public function completeness(array $sections): array
    {
        $total  = count($sections);
        $filled = 0;
        foreach ($sections as $s) {
            if (trim((string) ($s['body'] ?? '')) !== '') {
                $filled++;
            }
        }

        return [
            'filled' => $filled,
            'total'  => $total,
            'pct'    => $total > 0 ? (int) round($filled / $total * 100) : 0,
        ];
    }

    // ---------------------------------------------------------------------
    // maDMP machine-readable export (RDA / Science Europe aligned)
    // ---------------------------------------------------------------------

    /**
     * Build an RDA-aligned machine-actionable DMP (maDMP) document for a plan.
     * The shape follows the RDA DMP Common Standard: a top-level "dmp" object
     * carrying title, language, created/modified, contact, a project block and a
     * "dataset" array. Each maDMP section answer is emitted as a dataset
     * description plus a keyword-tagged "heratio:section" extension so the full
     * structured answer set round-trips, without losing fidelity.
     *
     * @param  array<string,mixed>  $plan
     * @param  array<int,array<string,mixed>>  $sections
     * @param  object|null  $project
     * @return array<string,mixed>
     */
    public function buildMadmp(array $plan, array $sections, ?object $project = null): array
    {
        $now      = now()->toIso8601String();
        $created  = $plan['created_at'] ?? '';
        $modified = $plan['updated_at'] ?? '';

        // Index answers by section key for stable emission.
        $byKey = [];
        foreach ($sections as $s) {
            $byKey[(string) ($s['section_key'] ?? '')] = $s;
        }

        $contact = [];
        if (! empty($plan['contact_name']) || ! empty($plan['contact_email'])) {
            $contact = [
                'name'       => (string) ($plan['contact_name'] ?? ''),
                'mbox'       => (string) ($plan['contact_email'] ?? ''),
                'contact_id' => [
                    'identifier' => (string) ($plan['contact_email'] ?? ''),
                    'type'       => 'other',
                ],
            ];
        }

        $projectBlock = [
            'title'       => $project->title ?? (string) ($plan['title'] ?? ''),
            'description' => isset($project->description) ? (string) $project->description : '',
        ];
        if (! empty($plan['funder'])) {
            $projectBlock['funding'] = [[
                'name'             => (string) $plan['funder'],
                'funding_status'   => 'planned',
            ]];
        }

        // One dataset entry carrying the structured maDMP answers as descriptions.
        $datasetDescriptionParts = [];
        $sectionExtension = [];
        foreach (self::MADMP_SECTIONS as $tpl) {
            $key  = $tpl['key'];
            $row  = $byKey[$key] ?? null;
            $body = $row ? (string) ($row['body'] ?? '') : '';
            $sectionExtension[] = [
                'key'    => $key,
                'label'  => $row ? (string) ($row['label'] ?? $tpl['label']) : $tpl['label'],
                'answer' => $body,
            ];
            if (trim($body) !== '') {
                $datasetDescriptionParts[] = ($tpl['label']) . ': ' . $body;
            }
        }

        $dataset = [[
            'title'             => (string) ($plan['title'] ?? 'Dataset'),
            'description'       => implode("\n\n", $datasetDescriptionParts),
            'personal_data'     => 'unknown',
            'sensitive_data'    => 'unknown',
            'dataset_id'        => [
                'identifier' => 'heratio-dmp-' . ($plan['id'] ?? 0),
                'type'       => 'other',
            ],
        ]];

        $dmp = [
            'title'         => (string) ($plan['title'] ?? 'Data Management Plan'),
            'language'      => $this->iso639($plan['language'] ?? 'en'),
            'created'       => $created !== '' ? $created : $now,
            'modified'      => $modified !== '' ? $modified : $now,
            'ethical_issues_exist' => 'unknown',
            'dmp_id'        => [
                'identifier' => 'heratio:dmp:' . ($plan['id'] ?? 0),
                'type'       => 'other',
            ],
            'project'       => [$projectBlock],
            'dataset'       => $dataset,
            // Non-standard, namespaced extension preserving the full section set.
            'extension'     => [[
                'heratio' => [
                    'status'          => (string) ($plan['status'] ?? ''),
                    'funder'          => (string) ($plan['funder'] ?? ''),
                    'funder_template' => (string) ($plan['funder_template'] ?? ''),
                    'sections'        => $sectionExtension,
                ],
            ]],
        ];
        if (! empty($contact)) {
            $dmp['contact'] = $contact;
        }

        return [
            'dmp' => $dmp,
        ];
    }

    /** Normalise a BCP-47 language tag to an ISO 639-3 maDMP value where simple. */
    private function iso639(string $tag): string
    {
        $primary = strtolower(substr($tag, 0, 2));
        $map = ['en' => 'eng', 'af' => 'afr', 'fr' => 'fra', 'de' => 'deu', 'es' => 'spa',
                'pt' => 'por', 'nl' => 'nld', 'it' => 'ita', 'zh' => 'zho', 'ar' => 'ara'];

        return $map[$primary] ?? 'eng';
    }

    // ---------------------------------------------------------------------
    // Dropdown-backed taxonomies (Dropdown Manager - never ENUM)
    // ---------------------------------------------------------------------

    /** Status options [code => label], with a safe fallback. */
    public function statusOptions(): array
    {
        return $this->dropdownOptions(self::STATUS_TAXONOMY, [
            'draft' => 'Draft', 'in_review' => 'In Review', 'approved' => 'Approved',
            'published' => 'Published', 'superseded' => 'Superseded',
        ]);
    }

    /** Optional funder template options [code => label], with a safe fallback. */
    public function funderTemplateOptions(): array
    {
        return $this->dropdownOptions(self::FUNDER_TEMPLATE_TAXONOMY, [
            'generic' => 'Generic (jurisdiction-neutral)', 'horizon_europe' => 'Horizon Europe (example)',
            'nsf' => 'NSF (example)', 'wellcome' => 'Wellcome (example)', 'nrf' => 'NRF (example)',
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
    private function rowToArray(object $d): array
    {
        return [
            'id'              => (int) $d->id,
            'project_id'      => (int) $d->project_id,
            'title'           => (string) $d->title,
            'status'          => (string) $d->status,
            'funder'          => $d->funder !== null ? (string) $d->funder : '',
            'funder_template' => $d->funder_template !== null ? (string) $d->funder_template : '',
            'language'        => (string) ($d->language ?? 'en'),
            'contact_name'    => $d->contact_name !== null ? (string) $d->contact_name : '',
            'contact_email'   => $d->contact_email !== null ? (string) $d->contact_email : '',
            'owner_id'        => $d->owner_id !== null ? (int) $d->owner_id : null,
            'created_by'      => $d->created_by !== null ? (int) $d->created_by : null,
            'created_at'      => (string) ($d->created_at ?? ''),
            'updated_at'      => (string) ($d->updated_at ?? ''),
        ];
    }
}
