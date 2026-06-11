<?php

/**
 * GrantEngineService - Heratio ahg-research
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
 * heratio#1239 - Research OS #17 (moonshot 24): Grant Engine.
 *
 * Assemble funder-specific grant DRAFTS from material that already exists on a
 * project. A funder TEMPLATE (a row in the ahg_dropdown grant_funder_template
 * taxonomy, carrying an ordered section list in its metadata) drives the section
 * set. When a draft is started the sections are pre-filled READ-ONLY from the
 * project's own material - its mission/description, its Method Protocol (#1231),
 * its Question Brief (#1226) and its claims/assertions (#1223) - so the
 * researcher starts from their own words rather than a blank page. Every source
 * read is Schema::hasTable-guarded and try/catch wrapped, so a partial install
 * (a site without the method-studio or claim-ledger slices) degrades to a
 * lighter pre-fill instead of 500ing.
 *
 * Optional AI drafting per section (draftSection) runs ONLY through the AI
 * gateway abstraction (AhgAiServices\Services\LlmService), is clearly labelled
 * as AI-assisted, and never writes anything - the researcher reviews the
 * suggestion and saves it themselves. There is no auto-submit anywhere.
 *
 * No live writes outside the three NEW grant tables; no ALTER of any table.
 */
class GrantEngineService
{
    public const TEMPLATE_TAXONOMY      = 'grant_funder_template';
    public const DRAFT_STATUS_TAXONOMY  = 'grant_draft_status';
    public const CALL_STATUS_TAXONOMY   = 'grant_call_status';

    /** Jurisdiction-neutral fallback section list if a template has no metadata.sections. */
    public const FALLBACK_SECTIONS = [
        ['key' => 'summary',     'label' => 'Project summary / abstract', 'hint' => 'A short, plain-language overview of what you will do and why it matters.'],
        ['key' => 'background',  'label' => 'Background and rationale',    'hint' => 'The problem, the gap in current knowledge, and why now.'],
        ['key' => 'aims',        'label' => 'Aims and objectives',         'hint' => 'The specific, measurable goals of the project.'],
        ['key' => 'methodology', 'label' => 'Methodology / approach',      'hint' => 'How you will do the work.'],
        ['key' => 'outputs',     'label' => 'Expected outputs and outcomes','hint' => 'What the project will produce.'],
        ['key' => 'impact',      'label' => 'Significance and impact',      'hint' => 'Who benefits and how the field is advanced.'],
        ['key' => 'workplan',    'label' => 'Work plan and timeline',      'hint' => 'Phases, milestones and the schedule.'],
        ['key' => 'budget',      'label' => 'Budget justification',        'hint' => 'What you are requesting and why.'],
        ['key' => 'team',        'label' => 'Team and capability',         'hint' => 'Who is involved and why they can deliver.'],
        ['key' => 'ethics',      'label' => 'Ethics and data management',  'hint' => 'Consent, privacy, retention - jurisdiction-neutral.'],
    ];

    // ---------------------------------------------------------------------
    // Funder templates (dropdown-backed)
    // ---------------------------------------------------------------------

    /** Active funder templates for the picker. Empty array if not installed. */
    public function listTemplates(): array
    {
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return [];
            }
            $rows = DB::table('ahg_dropdown')
                ->where('taxonomy', self::TEMPLATE_TAXONOMY)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();

            return $rows->map(fn ($r) => $this->decodeTemplate($r))->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** One funder template by code, or null. */
    public function getTemplate(string $code): ?array
    {
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return null;
            }
            $row = DB::table('ahg_dropdown')
                ->where('taxonomy', self::TEMPLATE_TAXONOMY)
                ->where('code', $code)
                ->first();

            return $row ? $this->decodeTemplate($row) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Normalise a dropdown row into a template array with an ordered section list. */
    private function decodeTemplate(object $row): array
    {
        $sections = [];
        $funder   = '';
        if (! empty($row->metadata)) {
            $meta = json_decode((string) $row->metadata, true);
            if (is_array($meta)) {
                $funder = (string) ($meta['funder'] ?? '');
                if (isset($meta['sections']) && is_array($meta['sections'])) {
                    foreach ($meta['sections'] as $i => $s) {
                        if (! is_array($s)) {
                            continue;
                        }
                        $key = (string) ($s['key'] ?? '');
                        if ($key === '') {
                            continue;
                        }
                        $sections[] = [
                            'key'   => $key,
                            'label' => (string) ($s['label'] ?? $key),
                            'hint'  => (string) ($s['hint'] ?? ''),
                            'order' => (int) ($s['order'] ?? (($i + 1) * 10)),
                        ];
                    }
                }
            }
        }
        if (empty($sections)) {
            foreach (self::FALLBACK_SECTIONS as $i => $s) {
                $sections[] = $s + ['order' => ($i + 1) * 10];
            }
        }

        return [
            'code'     => (string) $row->code,
            'name'     => (string) $row->label,
            'funder'   => $funder,
            'sections' => $sections,
        ];
    }

    // ---------------------------------------------------------------------
    // Drafts
    // ---------------------------------------------------------------------

    /** Grant drafts on a project (lightweight list rows). */
    public function listDrafts(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_grant_draft')) {
                return [];
            }

            return DB::table('research_grant_draft')
                ->where('project_id', $projectId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($d) => [
                    'id'              => (int) $d->id,
                    'project_id'      => (int) $d->project_id,
                    'funder_template' => (string) $d->funder_template,
                    'title'           => (string) $d->title,
                    'status'          => (string) $d->status,
                    'updated_at'      => (string) ($d->updated_at ?? $d->created_at ?? ''),
                ])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A single draft row as an array, scoped to its project, or null. */
    public function getDraft(int $id, ?int $projectId = null): ?array
    {
        try {
            if (! Schema::hasTable('research_grant_draft')) {
                return null;
            }
            $q = DB::table('research_grant_draft')->where('id', $id);
            if ($projectId !== null) {
                $q->where('project_id', $projectId);
            }
            $row = $q->first();
            if (! $row) {
                return null;
            }

            return [
                'id'              => (int) $row->id,
                'project_id'      => (int) $row->project_id,
                'funder_template' => (string) $row->funder_template,
                'title'           => (string) $row->title,
                'status'          => (string) $row->status,
                'created_by'      => $row->created_by !== null ? (int) $row->created_by : null,
                'created_at'      => (string) ($row->created_at ?? ''),
                'updated_at'      => (string) ($row->updated_at ?? ''),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Ordered section rows for a draft. */
    public function getSections(int $draftId): array
    {
        try {
            if (! Schema::hasTable('research_grant_section')) {
                return [];
            }

            return DB::table('research_grant_section')
                ->where('draft_id', $draftId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($s) => [
                    'id'          => (int) $s->id,
                    'draft_id'    => (int) $s->draft_id,
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
     * Start a grant draft for a project from a chosen funder template, pre-filling
     * each section READ-ONLY from the project's own material. Returns the new
     * draft id, or null on failure.
     */
    public function startDraft(int $projectId, string $templateCode, ?int $researcherId, ?string $title = null): ?int
    {
        try {
            if (! Schema::hasTable('research_grant_draft') || ! Schema::hasTable('research_grant_section')) {
                return null;
            }
            $template = $this->getTemplate($templateCode);
            if (! $template) {
                return null;
            }

            $material = $this->gatherProjectMaterial($projectId);
            $project  = $material['project'];

            $now      = now();
            $draftId  = (int) DB::table('research_grant_draft')->insertGetId([
                'project_id'      => $projectId,
                'funder_template' => $templateCode,
                'title'           => $title !== null && trim($title) !== ''
                    ? mb_substr(trim($title), 0, 255)
                    : ('Grant draft - ' . ($project['title'] !== '' ? $project['title'] : $template['name'])),
                'status'          => 'draft',
                'created_by'      => $researcherId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            foreach ($template['sections'] as $i => $sec) {
                DB::table('research_grant_section')->insert([
                    'draft_id'    => $draftId,
                    'section_key' => $sec['key'],
                    'label'       => mb_substr($sec['label'], 0, 190),
                    'body'        => $this->prefillSection($sec['key'], $material),
                    'sort_order'  => (int) ($sec['order'] ?? (($i + 1) * 10)),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            return $draftId;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] grant startDraft failed: ' . $e->getMessage());

            return null;
        }
    }

    /** Save a draft's title + status. Only the NEW table is written. */
    public function saveDraftMeta(int $id, int $projectId, ?string $title = null, ?string $status = null): bool
    {
        try {
            if (! Schema::hasTable('research_grant_draft')) {
                return false;
            }
            $existing = DB::table('research_grant_draft')
                ->where('id', $id)->where('project_id', $projectId)->first();
            if (! $existing) {
                return false;
            }

            $update = ['updated_at' => now()];
            if ($title !== null && trim($title) !== '') {
                $update['title'] = mb_substr(trim($title), 0, 255);
            }
            if ($status !== null && in_array($status, array_keys($this->draftStatusOptions()), true)) {
                $update['status'] = $status;
            }

            DB::table('research_grant_draft')
                ->where('id', $id)->where('project_id', $projectId)
                ->update($update);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Save section bodies. $bodies is [section_id => text]; only rows belonging to
     * the draft are written. Returns true on success.
     *
     * @param array<int,string> $bodies
     */
    public function saveSections(int $draftId, int $projectId, array $bodies): bool
    {
        try {
            if (! Schema::hasTable('research_grant_draft') || ! Schema::hasTable('research_grant_section')) {
                return false;
            }
            // Confirm the draft belongs to the project before touching its sections.
            $draft = DB::table('research_grant_draft')
                ->where('id', $draftId)->where('project_id', $projectId)->first();
            if (! $draft) {
                return false;
            }

            $now = now();
            foreach ($bodies as $sectionId => $text) {
                $sectionId = (int) $sectionId;
                if ($sectionId <= 0) {
                    continue;
                }
                $body = is_string($text) ? trim($text) : '';
                DB::table('research_grant_section')
                    ->where('id', $sectionId)
                    ->where('draft_id', $draftId)
                    ->update(['body' => $body, 'updated_at' => $now]);
            }

            DB::table('research_grant_draft')
                ->where('id', $draftId)->where('project_id', $projectId)
                ->update(['updated_at' => $now]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Pre-fill from existing project material (READ-ONLY)
    // ---------------------------------------------------------------------

    /**
     * Gather a project's own material for pre-fill, every read Schema-guarded.
     *
     * @return array{project:array, method:?array, brief:?array, claims:array}
     */
    public function gatherProjectMaterial(int $projectId): array
    {
        return [
            'project' => $this->readProject($projectId),
            'method'  => $this->readMethodProtocol($projectId),
            'brief'   => $this->readQuestionBrief($projectId),
            'claims'  => $this->readClaims($projectId),
        ];
    }

    /** Project core fields (mission = description), or empty defaults. */
    private function readProject(int $projectId): array
    {
        $out = ['id' => $projectId, 'title' => '', 'description' => '', 'institution' => '', 'funding_source' => ''];
        try {
            if (! Schema::hasTable('research_project')) {
                return $out;
            }
            $row = DB::table('research_project')->where('id', $projectId)->first();
            if ($row) {
                $out['title']          = (string) ($row->title ?? '');
                $out['description']    = (string) ($row->description ?? '');
                $out['institution']    = (string) ($row->institution ?? '');
                $out['funding_source'] = (string) ($row->funding_source ?? '');
            }
        } catch (\Throwable $e) {
            // leave defaults
        }

        return $out;
    }

    /**
     * Latest Method Protocol for the project (#1231), as area-key => answer.
     * Returns null if the slice is not installed or there is no protocol.
     *
     * @return array{title:string, template_code:string, fields:array<string,string>}|null
     */
    private function readMethodProtocol(int $projectId): ?array
    {
        try {
            if (! Schema::hasTable('research_method_protocol')) {
                return null;
            }
            $row = DB::table('research_method_protocol')
                ->where('project_id', $projectId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
            if (! $row) {
                return null;
            }
            $fields = [];
            if (! empty($row->fields)) {
                $decoded = json_decode((string) $row->fields, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $k => $v) {
                        $fields[(string) $k] = is_string($v) ? $v : '';
                    }
                }
            }

            return [
                'title'         => (string) ($row->title ?? ''),
                'template_code' => (string) ($row->template_code ?? ''),
                'fields'        => $fields,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Latest Question Brief version for the project (#1226).
     * Returns null if the slice is not installed or there is no brief.
     *
     * @return array<string,string>|null
     */
    private function readQuestionBrief(int $projectId): ?array
    {
        try {
            if (! Schema::hasTable('research_question_brief') || ! Schema::hasTable('research_question_brief_version')) {
                return null;
            }
            $brief = DB::table('research_question_brief')->where('project_id', $projectId)->first();
            if (! $brief) {
                return null;
            }
            $ver = DB::table('research_question_brief_version')
                ->where('brief_id', $brief->id)
                ->orderByDesc('version_no')
                ->orderByDesc('id')
                ->first();
            if (! $ver) {
                return null;
            }

            return [
                'broad_topic'         => (string) ($ver->broad_topic ?? ''),
                'problem_statement'   => (string) ($ver->problem_statement ?? ''),
                'research_gap'        => (string) ($ver->research_gap ?? ''),
                'primary_question'    => (string) ($ver->primary_question ?? ''),
                'secondary_questions' => (string) ($ver->secondary_questions ?? ''),
                'hypothesis'          => (string) ($ver->hypothesis ?? ''),
                'scope_boundaries'    => (string) ($ver->scope_boundaries ?? ''),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A few of the project's claims/assertions (#1223) as short label strings,
     * to seed the impact/outputs narrative. Empty array if the table is absent.
     *
     * @return array<int,string>
     */
    private function readClaims(int $projectId, int $limit = 8): array
    {
        try {
            if (! Schema::hasTable('research_assertion')) {
                return [];
            }
            $rows = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $subject   = trim((string) ($r->subject_label ?? ''));
                $predicate = trim((string) ($r->predicate ?? ''));
                $object    = trim((string) ($r->object_label ?? $r->object_value ?? ''));
                $parts     = array_filter([$subject, $predicate, $object], fn ($p) => $p !== '');
                $line      = trim(implode(' ', $parts));
                if ($line !== '') {
                    $out[] = mb_substr($line, 0, 300);
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Pre-fill text for one section from the gathered material. Pure string
     * assembly from the researcher's OWN material - never invented content. The
     * researcher then edits freely, or asks the AI to draft (see draftSection).
     *
     * @param array{project:array, method:?array, brief:?array, claims:array} $material
     */
    private function prefillSection(string $key, array $material): string
    {
        $project = $material['project'];
        $method  = $material['method'];
        $brief   = $material['brief'];
        $claims  = $material['claims'];

        $lines = [];

        switch ($key) {
            case 'summary':
            case 'background':
                if (! empty($project['description'])) {
                    $lines[] = $project['description'];
                }
                if ($brief && ! empty($brief['broad_topic'])) {
                    $lines[] = $brief['broad_topic'];
                }
                if ($brief && ! empty($brief['problem_statement'])) {
                    $lines[] = $brief['problem_statement'];
                }
                if ($brief && ! empty($brief['research_gap'])) {
                    $lines[] = 'Gap: ' . $brief['research_gap'];
                }
                break;

            case 'questions':
                if ($brief && ! empty($brief['primary_question'])) {
                    $lines[] = 'Primary question: ' . $brief['primary_question'];
                }
                if ($brief && ! empty($brief['secondary_questions'])) {
                    $lines[] = 'Secondary questions: ' . $brief['secondary_questions'];
                }
                if ($brief && ! empty($brief['hypothesis'])) {
                    $lines[] = 'Hypothesis: ' . $brief['hypothesis'];
                }
                break;

            case 'aims':
            case 'objectives':
            case 'ambition':
            case 'significance':
            case 'innovation':
                if ($brief && ! empty($brief['primary_question'])) {
                    $lines[] = $brief['primary_question'];
                }
                if ($brief && ! empty($brief['research_gap'])) {
                    $lines[] = $brief['research_gap'];
                }
                break;

            case 'methodology':
            case 'approach':
                if ($method && ! empty($method['fields'])) {
                    foreach (['design', 'sampling', 'data_sources', 'instruments', 'coding_framework'] as $area) {
                        if (! empty($method['fields'][$area])) {
                            $lines[] = $method['fields'][$area];
                        }
                    }
                }
                break;

            case 'feasibility':
                if ($method && ! empty($method['fields'])) {
                    foreach (['validity', 'reliability', 'reproducibility'] as $area) {
                        if (! empty($method['fields'][$area])) {
                            $lines[] = $method['fields'][$area];
                        }
                    }
                }
                break;

            case 'outputs':
            case 'impact':
                if (! empty($claims)) {
                    $lines[] = 'Drawing on the project claims:';
                    foreach ($claims as $c) {
                        $lines[] = '- ' . $c;
                    }
                }
                if ($brief && ! empty($brief['scope_boundaries'])) {
                    $lines[] = 'Scope: ' . $brief['scope_boundaries'];
                }
                break;

            case 'ethics':
                if ($method && ! empty($method['fields'])) {
                    foreach (['ethics', 'consent', 'data_management', 'bias_control'] as $area) {
                        if (! empty($method['fields'][$area])) {
                            $lines[] = $method['fields'][$area];
                        }
                    }
                }
                break;

            case 'team':
                if (! empty($project['institution'])) {
                    $lines[] = 'Institution: ' . $project['institution'];
                }
                break;

            case 'budget':
                if (! empty($project['funding_source'])) {
                    $lines[] = 'Existing funding source: ' . $project['funding_source'];
                }
                break;
        }

        return trim(implode("\n\n", array_filter($lines, fn ($l) => trim((string) $l) !== '')));
    }

    // ---------------------------------------------------------------------
    // Optional AI drafting per section (gateway only, labelled, never submits)
    // ---------------------------------------------------------------------

    /**
     * Ask the AI gateway to draft one section, grounded in the project's own
     * material and the section's current text. Returns a SUGGESTION the
     * researcher must review and save - this method writes NOTHING.
     *
     * Routes exclusively through AhgAiServices\Services\LlmService (the gateway
     * abstraction) - never a direct node port. Returns null if AI is unavailable.
     *
     * @return array{ok:bool, text:string, label:string}
     */
    public function draftSection(int $projectId, string $sectionKey, string $sectionLabel, string $currentText, string $funderName = ''): array
    {
        $out = ['ok' => false, 'text' => '', 'label' => 'AI-assisted draft (review required before use)'];

        try {
            $material = $this->gatherProjectMaterial($projectId);
            $context  = $this->materialDigest($material);

            $funderLine = $funderName !== ''
                ? "The target funder is: {$funderName} (an example funder; keep guidance jurisdiction-neutral). "
                : 'Keep the writing jurisdiction-neutral. ';

            $prompt = "You are helping a researcher draft the \"{$sectionLabel}\" section of a grant application. "
                . $funderLine
                . "Write a clear, concise, professional draft for THIS section ONLY, grounded strictly in the project material below. "
                . "Do not invent funding amounts, dates, named people, institutions, or results that are not present in the material. "
                . "If the material is thin for this section, write a short honest scaffold the researcher can complete, and note what is missing in square brackets.\n\n"
                . "PROJECT MATERIAL:\n{$context}\n\n"
                . "CURRENT DRAFT OF THIS SECTION (may be empty):\n" . ($currentText !== '' ? $currentText : '(empty)') . "\n\n"
                . "Return only the section prose.";

            $text = (string) app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 700, 'temperature' => 0.3]);
            $text = trim($text);

            $out['text'] = $text;
            $out['ok']   = $text !== '';
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] grant draftSection LLM failed: ' . $e->getMessage());
        }

        return $out;
    }

    /** Compact digest of the gathered material for the AI prompt. */
    private function materialDigest(array $material): string
    {
        $p = $material['project'];
        $parts = [];
        if (! empty($p['title'])) {
            $parts[] = 'Project title: ' . $p['title'];
        }
        if (! empty($p['description'])) {
            $parts[] = 'Mission / description: ' . $p['description'];
        }
        if (! empty($p['institution'])) {
            $parts[] = 'Institution: ' . $p['institution'];
        }

        if (! empty($material['brief'])) {
            foreach ($material['brief'] as $k => $v) {
                if (trim((string) $v) !== '') {
                    $parts[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $v;
                }
            }
        }

        if (! empty($material['method']['fields'])) {
            foreach ($material['method']['fields'] as $area => $v) {
                if (trim((string) $v) !== '') {
                    $parts[] = 'Method - ' . str_replace('_', ' ', $area) . ': ' . $v;
                }
            }
        }

        if (! empty($material['claims'])) {
            $parts[] = 'Project claims: ' . implode('; ', $material['claims']);
        }

        $digest = implode("\n", $parts);

        // Keep the prompt bounded.
        return mb_substr($digest, 0, 6000);
    }

    // ---------------------------------------------------------------------
    // Tracked calls
    // ---------------------------------------------------------------------

    /**
     * Tracked grant calls for a researcher, optionally narrowed to a project.
     */
    public function listCalls(?int $researcherId, ?int $projectId = null): array
    {
        try {
            if (! Schema::hasTable('research_grant_call')) {
                return [];
            }
            $q = DB::table('research_grant_call');
            if ($researcherId !== null) {
                $q->where('researcher_id', $researcherId);
            }
            if ($projectId !== null) {
                $q->where('project_id', $projectId);
            }

            return $q->orderByRaw('deadline IS NULL, deadline ASC')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($c) => [
                    'id'            => (int) $c->id,
                    'researcher_id' => $c->researcher_id !== null ? (int) $c->researcher_id : null,
                    'project_id'    => $c->project_id !== null ? (int) $c->project_id : null,
                    'funder'        => (string) $c->funder,
                    'title'         => (string) $c->title,
                    'url'           => (string) ($c->url ?? ''),
                    'deadline'      => (string) ($c->deadline ?? ''),
                    'status'        => (string) $c->status,
                    'notes'         => (string) ($c->notes ?? ''),
                ])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Track a new grant call. Returns the new id, or null. */
    public function addCall(?int $researcherId, ?int $projectId, array $data): ?int
    {
        try {
            if (! Schema::hasTable('research_grant_call')) {
                return null;
            }
            $funder = trim((string) ($data['funder'] ?? ''));
            $title  = trim((string) ($data['title'] ?? ''));
            if ($funder === '' || $title === '') {
                return null;
            }
            $status = (string) ($data['status'] ?? 'watching');
            if (! in_array($status, array_keys($this->callStatusOptions()), true)) {
                $status = 'watching';
            }
            $deadline = trim((string) ($data['deadline'] ?? ''));

            $now = now();

            return (int) DB::table('research_grant_call')->insertGetId([
                'researcher_id' => $researcherId,
                'project_id'    => $projectId,
                'funder'        => mb_substr($funder, 0, 255),
                'title'         => mb_substr($title, 0, 255),
                'url'           => $this->cleanUrl((string) ($data['url'] ?? '')),
                'deadline'      => $deadline !== '' ? $deadline : null,
                'status'        => $status,
                'notes'         => trim((string) ($data['notes'] ?? '')) ?: null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Update a tracked call's status (and optionally notes), owner-scoped. */
    public function updateCall(int $id, ?int $researcherId, array $data): bool
    {
        try {
            if (! Schema::hasTable('research_grant_call')) {
                return false;
            }
            $q = DB::table('research_grant_call')->where('id', $id);
            if ($researcherId !== null) {
                $q->where('researcher_id', $researcherId);
            }
            if (! $q->exists()) {
                return false;
            }

            $update = ['updated_at' => now()];
            if (isset($data['status'])) {
                $status = (string) $data['status'];
                if (in_array($status, array_keys($this->callStatusOptions()), true)) {
                    $update['status'] = $status;
                }
            }
            if (array_key_exists('notes', $data)) {
                $update['notes'] = trim((string) $data['notes']) ?: null;
            }

            DB::table('research_grant_call')->where('id', $id)->update($update);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Delete a tracked call, owner-scoped. */
    public function deleteCall(int $id, ?int $researcherId): bool
    {
        try {
            if (! Schema::hasTable('research_grant_call')) {
                return false;
            }
            $q = DB::table('research_grant_call')->where('id', $id);
            if ($researcherId !== null) {
                $q->where('researcher_id', $researcherId);
            }

            return $q->delete() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Light URL hygiene: only keep http(s) URLs, trimmed and bounded. */
    private function cleanUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return mb_substr($url, 0, 500);
    }

    // ---------------------------------------------------------------------
    // Status taxonomies (Dropdown Manager - never ENUM)
    // ---------------------------------------------------------------------

    /** Draft status options [code => label], with a safe fallback. */
    public function draftStatusOptions(): array
    {
        return $this->dropdownOptions(self::DRAFT_STATUS_TAXONOMY, [
            'draft' => 'Draft', 'in_review' => 'In Review', 'ready' => 'Ready', 'submitted' => 'Submitted',
        ]);
    }

    /** Call status options [code => label], with a safe fallback. */
    public function callStatusOptions(): array
    {
        return $this->dropdownOptions(self::CALL_STATUS_TAXONOMY, [
            'watching' => 'Watching', 'preparing' => 'Preparing', 'submitted' => 'Submitted',
            'awarded' => 'Awarded', 'declined' => 'Declined', 'closed' => 'Closed',
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
}
