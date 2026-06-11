<?php

/**
 * ProjectExportService - Heratio ahg-research
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
 * heratio#1237 - Research OS #15: Open-format project export.
 *
 * Founding principle: "no lock-in / the exit door is always open." This service
 * assembles a faithful, full-fidelity export bundle of a single research
 * project's intellectual work into open, non-proprietary formats:
 *
 *   - a Markdown document (human-readable narrative of the whole project);
 *   - a machine-readable JSON of the same data;
 *   - the project's sources as BibTeX, RIS and CSL-JSON.
 *
 * Everything is READ-ONLY over the existing research tables. Every section is
 * Schema::hasTable-guarded and wrapped in try/catch: a missing slice simply
 * omits its section and notes the omission in the bundle manifest. The service
 * never writes to, or alters, any existing table.
 *
 * The ZIP is built with PHP's ZipArchive into a temp file under
 * config('heratio.storage_path').'/research-export/' - never a hardcoded path -
 * and the caller is responsible for streaming it and deleting it afterwards.
 */
class ProjectExportService
{
    /**
     * Tables this export reads from, paired with the manifest section they feed.
     * Used to build the manifest's "included" / "omitted" lists.
     *
     * @var array<string,string>
     */
    private const SECTION_TABLES = [
        'research_project'                 => 'project',
        'research_question_brief'          => 'question_brief',
        'research_question_brief_version'  => 'question_brief',
        'research_assertion'               => 'claims',
        'research_assertion_evidence'      => 'claims',
        'research_claim_meta'              => 'claims',
        'research_decision_log'            => 'decision_log',
        'research_argument'                => 'argument',
        'research_argument_step'           => 'argument',
        'research_method_protocol'         => 'method_protocol',
        'research_memory_item'             => 'research_memory',
        'research_bibliography'            => 'bibliography',
        'research_bibliography_entry'      => 'bibliography',
    ];

    /**
     * Assemble the full project context as a nested structure. Each top-level key
     * maps to a bundle section; any section whose table is missing is set to null
     * (omitted) and recorded in $manifest['omitted'].
     *
     * @return array<string,mixed>
     */
    public function assemble(int $projectId): array
    {
        $project = $this->safeFirst('research_project', fn () =>
            DB::table('research_project')->where('id', $projectId)->first()
        );

        $bundle = [
            'project'        => $project ? (array) $project : null,
            'questionBrief'  => $this->loadQuestionBrief($projectId),
            'claims'         => $this->loadClaims($projectId),
            'decisionLog'    => $this->loadDecisionLog($projectId),
            'argument'       => $this->loadArgument($projectId),
            'methodProtocol' => $this->loadMethodProtocols($projectId),
            'researchMemory' => $this->loadResearchMemory($projectId, $project),
            'sources'        => $this->loadSources($projectId),
        ];

        $bundle['manifest'] = $this->buildManifest($projectId, $bundle);

        return $bundle;
    }

    // =========================================================================
    // Section loaders (each guarded + resilient)
    // =========================================================================

    /** Question Design Brief plus every immutable version, oldest first. */
    private function loadQuestionBrief(int $projectId): ?array
    {
        if (! $this->has('research_question_brief')) {
            return null;
        }
        try {
            $brief = DB::table('research_question_brief')
                ->where('project_id', $projectId)->first();
            if (! $brief) {
                return null;
            }

            $versions = [];
            if ($this->has('research_question_brief_version')) {
                $versions = DB::table('research_question_brief_version')
                    ->where('brief_id', $brief->id)
                    ->orderBy('version_no')
                    ->get()->map(fn ($v) => (array) $v)->all();
            }

            return [
                'brief'    => (array) $brief,
                'versions' => $versions,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Claims (research_assertion) with their evidence (research_assertion_evidence)
     * and the Claim Ledger meta (research_claim_meta). Full fidelity.
     */
    private function loadClaims(int $projectId): ?array
    {
        if (! $this->has('research_assertion')) {
            return null;
        }
        try {
            $assertions = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->orderBy('created_at')
                ->get();

            $hasEvidence = $this->has('research_assertion_evidence');
            $hasMeta     = $this->has('research_claim_meta');

            $claims = [];
            foreach ($assertions as $a) {
                $row = (array) $a;

                $row['evidence'] = [];
                if ($hasEvidence) {
                    $row['evidence'] = DB::table('research_assertion_evidence')
                        ->where('assertion_id', $a->id)
                        ->orderBy('id')
                        ->get()->map(fn ($e) => (array) $e)->all();
                }

                $row['meta'] = null;
                if ($hasMeta) {
                    $meta = DB::table('research_claim_meta')
                        ->where('assertion_id', $a->id)->first();
                    $row['meta'] = $meta ? (array) $meta : null;
                }

                $claims[] = $row;
            }

            return $claims;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Decision Log entries, chronological. */
    private function loadDecisionLog(int $projectId): ?array
    {
        if (! $this->has('research_decision_log')) {
            return null;
        }
        try {
            return DB::table('research_decision_log')
                ->where('project_id', $projectId)
                ->orderByRaw('COALESCE(decided_at, created_at) ASC')
                ->get()->map(fn ($d) => (array) $d)->all();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The argument scaffold(s) with their ordered steps. A step references a
     * claim by assertion_id; we resolve the claim's subject label for readability
     * when research_assertion is present.
     */
    private function loadArgument(int $projectId): ?array
    {
        if (! $this->has('research_argument')) {
            return null;
        }
        try {
            $arguments = DB::table('research_argument')
                ->where('project_id', $projectId)
                ->orderBy('id')
                ->get();

            $hasSteps  = $this->has('research_argument_step');
            $hasClaims = $this->has('research_assertion');

            $out = [];
            foreach ($arguments as $arg) {
                $row = (array) $arg;
                $row['steps'] = [];

                if ($hasSteps) {
                    $steps = DB::table('research_argument_step')
                        ->where('argument_id', $arg->id)
                        ->orderBy('sort_order')->orderBy('id')
                        ->get();

                    foreach ($steps as $s) {
                        $stepRow = (array) $s;
                        $stepRow['claim_label'] = null;
                        if ($hasClaims && ! empty($s->assertion_id)) {
                            $claim = DB::table('research_assertion')
                                ->where('id', $s->assertion_id)->first();
                            if ($claim) {
                                $stepRow['claim_label'] = $this->claimLabel((array) $claim);
                            }
                        }
                        $row['steps'][] = $stepRow;
                    }
                }

                $out[] = $row;
            }

            return $out;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Method Design Studio protocols for the project. */
    private function loadMethodProtocols(int $projectId): ?array
    {
        if (! $this->has('research_method_protocol')) {
            return null;
        }
        try {
            return DB::table('research_method_protocol')
                ->where('project_id', $projectId)
                ->orderBy('id')
                ->get()->map(fn ($m) => (array) $m)->all();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Research Memory items tied to this project. The table is keyed by
     * researcher_id with a nullable project_id, so we slice by project_id only
     * (the project the export is for).
     */
    private function loadResearchMemory(int $projectId, ?object $project): ?array
    {
        if (! $this->has('research_memory_item')) {
            return null;
        }
        try {
            return DB::table('research_memory_item')
                ->where('project_id', $projectId)
                ->orderBy('id')
                ->get()->map(fn ($m) => (array) $m)->all();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Bibliographies for the project plus their entries. The entry's csl_data
     * JSON (when present) is decoded so the citation exporters get full fidelity;
     * the flat columns are kept as a fallback.
     */
    private function loadSources(int $projectId): ?array
    {
        if (! $this->has('research_bibliography')) {
            return null;
        }
        try {
            $bibs = DB::table('research_bibliography')
                ->where('project_id', $projectId)
                ->orderBy('id')
                ->get();

            $hasEntries = $this->has('research_bibliography_entry');

            $out = [];
            foreach ($bibs as $bib) {
                $row = (array) $bib;
                $row['entries'] = [];

                if ($hasEntries) {
                    $entries = DB::table('research_bibliography_entry')
                        ->where('bibliography_id', $bib->id)
                        ->orderBy('sort_order')->orderBy('id')
                        ->get();

                    foreach ($entries as $e) {
                        $entry = (array) $e;
                        $entry['csl'] = $this->decodeJson($e->csl_data ?? null);
                        $row['entries'][] = $entry;
                    }
                }

                $out[] = $row;
            }

            return $out;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =========================================================================
    // Manifest
    // =========================================================================

    /**
     * Build the manifest: which sections are included vs omitted (with the reason
     * a section was dropped: missing table or empty), plus counts.
     *
     * @param array<string,mixed> $bundle
     * @return array<string,mixed>
     */
    private function buildManifest(int $projectId, array $bundle): array
    {
        $included = [];
        $omitted  = [];

        // Map each logical section to its presence + count.
        $sections = [
            'project'         => ['data' => $bundle['project'],        'count' => $bundle['project'] ? 1 : 0],
            'question_brief'  => ['data' => $bundle['questionBrief'],  'count' => isset($bundle['questionBrief']['versions']) ? count($bundle['questionBrief']['versions']) : 0],
            'claims'          => ['data' => $bundle['claims'],         'count' => is_array($bundle['claims']) ? count($bundle['claims']) : 0],
            'decision_log'    => ['data' => $bundle['decisionLog'],    'count' => is_array($bundle['decisionLog']) ? count($bundle['decisionLog']) : 0],
            'argument'        => ['data' => $bundle['argument'],       'count' => is_array($bundle['argument']) ? count($bundle['argument']) : 0],
            'method_protocol' => ['data' => $bundle['methodProtocol'], 'count' => is_array($bundle['methodProtocol']) ? count($bundle['methodProtocol']) : 0],
            'research_memory' => ['data' => $bundle['researchMemory'], 'count' => is_array($bundle['researchMemory']) ? count($bundle['researchMemory']) : 0],
            'bibliography'    => ['data' => $bundle['sources'],        'count' => is_array($bundle['sources']) ? array_sum(array_map(fn ($b) => count($b['entries'] ?? []), $bundle['sources'])) : 0],
        ];

        foreach ($sections as $key => $info) {
            if ($info['data'] === null) {
                $omitted[$key] = 'table not present in this installation';
            } else {
                $included[$key] = $info['count'];
            }
        }

        return [
            'schema'      => 'heratio.research.project-export/1',
            'project_id'  => $projectId,
            'generated_at' => date('c'),
            'generator'   => 'Heratio Research OS - Open-format project export (heratio#1237)',
            'principle'   => 'No lock-in. The exit door is always open. This bundle is a faithful, full-fidelity copy of your work in open formats.',
            'formats'     => ['markdown', 'json', 'bibtex', 'ris', 'csl-json'],
            'included'    => $included,
            'omitted'     => $omitted,
        ];
    }

    // =========================================================================
    // Markdown rendering
    // =========================================================================

    /**
     * Render the whole bundle as a single Markdown document. Sections that were
     * omitted are shown as a short "(not available in this installation)" note so
     * the document is self-describing.
     *
     * @param array<string,mixed> $bundle
     */
    public function toMarkdown(array $bundle): string
    {
        $p = $bundle['project'] ?? null;
        $title = $p['title'] ?? 'Untitled project';

        $md = [];
        $md[] = '# ' . $this->mdInline($title);
        $md[] = '';
        $md[] = '_Open-format export generated by Heratio Research OS on ' . date('Y-m-d H:i') . '._';
        $md[] = '';
        $md[] = '> No lock-in. The exit door is always open. This document is a faithful, full-fidelity copy of your project in an open format.';
        $md[] = '';

        // Project overview
        $md[] = '## Project';
        if ($p) {
            $md = array_merge($md, $this->mdKeyValues([
                'Title'            => $p['title'] ?? '',
                'Type'             => $p['project_type'] ?? '',
                'Status'           => $p['status'] ?? '',
                'Institution'      => $p['institution'] ?? '',
                'Supervisor'       => $p['supervisor'] ?? '',
                'Funding source'   => $p['funding_source'] ?? '',
                'Grant number'     => $p['grant_number'] ?? '',
                'Ethics approval'  => $p['ethics_approval'] ?? '',
                'Start date'       => $p['start_date'] ?? '',
                'Expected end'     => $p['expected_end_date'] ?? '',
                'Actual end'       => $p['actual_end_date'] ?? '',
            ]));
            if (! empty($p['description'])) {
                $md[] = '';
                $md[] = $this->mdBlock($p['description']);
            }
        } else {
            $md[] = '_Project record not available._';
        }
        $md[] = '';

        // Question brief
        $md[] = '## Research Design Brief';
        $qb = $bundle['questionBrief'] ?? null;
        if ($qb === null) {
            $md[] = $this->mdOmitted();
        } elseif (empty($qb['versions'])) {
            $md[] = '_No versions recorded yet._';
        } else {
            foreach ($qb['versions'] as $v) {
                $md[] = '### Version ' . ($v['version_no'] ?? '?');
                if (! empty($v['change_reason'])) {
                    $md[] = '_Change reason: ' . $this->mdInline($v['change_reason']) . '_';
                }
                $md[] = '';
                $md = array_merge($md, $this->mdLabelledBlocks([
                    'Broad topic'         => $v['broad_topic'] ?? '',
                    'Problem statement'   => $v['problem_statement'] ?? '',
                    'Research gap'        => $v['research_gap'] ?? '',
                    'Primary question'    => $v['primary_question'] ?? '',
                    'Secondary questions' => $v['secondary_questions'] ?? '',
                    'Hypothesis'          => $v['hypothesis'] ?? '',
                    'Scope boundaries'    => $v['scope_boundaries'] ?? '',
                    'Key definitions'     => $v['key_definitions'] ?? '',
                    'Assumptions'         => $v['assumptions'] ?? '',
                    'Bias risks'          => $v['bias_risks'] ?? '',
                ]));
                $md[] = '';
            }
        }
        $md[] = '';

        // Claims
        $md[] = '## Claims and Evidence';
        $claims = $bundle['claims'] ?? null;
        if ($claims === null) {
            $md[] = $this->mdOmitted();
        } elseif (count($claims) === 0) {
            $md[] = '_No claims recorded yet._';
        } else {
            foreach ($claims as $i => $c) {
                $md[] = '### Claim ' . ($i + 1) . ': ' . $this->mdInline($this->claimLabel($c));
                $md = array_merge($md, $this->mdKeyValues([
                    'Type'       => $c['assertion_type'] ?? '',
                    'Status'     => $c['status'] ?? '',
                    'Confidence' => isset($c['confidence']) && $c['confidence'] !== null ? (string) $c['confidence'] : '',
                ]));
                if (! empty($c['object_value'])) {
                    $md[] = '';
                    $md[] = $this->mdBlock($c['object_value']);
                }

                $meta = $c['meta'] ?? null;
                if (is_array($meta)) {
                    $md = array_merge($md, $this->mdLabelledBlocks([
                        'Evidence type'         => $meta['evidence_type'] ?? '',
                        'Confidence level'      => $meta['confidence_level'] ?? '',
                        'Provenance'            => $meta['provenance_kind'] ?? '',
                        'Supporting sources'    => $meta['supporting_sources'] ?? '',
                        'Opposing sources'      => $meta['opposing_sources'] ?? '',
                        'Quotations'            => $meta['quotations'] ?? '',
                        'Method / theory link'  => $meta['method_theory_link'] ?? '',
                        'Researcher notes'      => $meta['researcher_notes'] ?? '',
                        'Unresolved weaknesses' => $meta['unresolved_weaknesses'] ?? '',
                        'Ethical concerns'      => $meta['ethical_concerns'] ?? '',
                    ]));
                }

                $evidence = $c['evidence'] ?? [];
                if (! empty($evidence)) {
                    $md[] = '';
                    $md[] = '**Evidence:**';
                    foreach ($evidence as $e) {
                        $rel = $e['relationship'] ?? 'related';
                        $src = trim(($e['source_type'] ?? '') . ' #' . ($e['source_id'] ?? ''));
                        $note = ! empty($e['note']) ? ' - ' . $this->mdInline($e['note']) : '';
                        $md[] = '- (' . $this->mdInline($rel) . ') ' . $this->mdInline($src) . $note;
                    }
                }
                $md[] = '';
            }
        }
        $md[] = '';

        // Decision log
        $md[] = '## Decision Log';
        $log = $bundle['decisionLog'] ?? null;
        if ($log === null) {
            $md[] = $this->mdOmitted();
        } elseif (count($log) === 0) {
            $md[] = '_No decisions recorded yet._';
        } else {
            foreach ($log as $d) {
                $when = $d['decided_at'] ?? $d['created_at'] ?? '';
                $md[] = '### ' . $this->mdInline($d['summary'] ?? '(no summary)');
                $md = array_merge($md, $this->mdKeyValues([
                    'Type'       => $d['decision_type'] ?? '',
                    'Decided by' => $d['decided_by'] ?? '',
                    'When'       => $when,
                    'Reference'  => $d['related_ref'] ?? '',
                ]));
                if (! empty($d['reason'])) {
                    $md[] = '';
                    $md[] = $this->mdBlock($d['reason']);
                }
                $md[] = '';
            }
        }
        $md[] = '';

        // Argument
        $md[] = '## Argument';
        $args = $bundle['argument'] ?? null;
        if ($args === null) {
            $md[] = $this->mdOmitted();
        } elseif (count($args) === 0) {
            $md[] = '_No argument scaffold built yet._';
        } else {
            foreach ($args as $arg) {
                $md[] = '### ' . $this->mdInline($arg['title'] ?? 'Argument');
                if (! empty($arg['central_thesis'])) {
                    $md[] = '**Central thesis:**';
                    $md[] = $this->mdBlock($arg['central_thesis']);
                }
                if (! empty($arg['steps'])) {
                    $md[] = '';
                    foreach ($arg['steps'] as $n => $s) {
                        $slot = $s['slot'] ?? 'step';
                        $label = ! empty($s['claim_label']) ? $this->mdInline($s['claim_label']) : '_(empty slot)_';
                        $md[] = ($n + 1) . '. **' . $this->mdInline($slot) . '** - ' . $label;
                        if (! empty($s['note'])) {
                            $md[] = '   - ' . $this->mdInline($s['note']);
                        }
                    }
                }
                $md[] = '';
            }
        }
        $md[] = '';

        // Method protocol
        $md[] = '## Method Protocol';
        $methods = $bundle['methodProtocol'] ?? null;
        if ($methods === null) {
            $md[] = $this->mdOmitted();
        } elseif (count($methods) === 0) {
            $md[] = '_No method protocol recorded yet._';
        } else {
            foreach ($methods as $m) {
                $md[] = '### ' . $this->mdInline($m['title'] ?? 'Protocol');
                $md = array_merge($md, $this->mdKeyValues([
                    'Template' => $m['template_code'] ?? '',
                    'Status'   => $m['status'] ?? '',
                ]));
                $fields = $this->decodeJson($m['fields'] ?? null);
                if (is_array($fields) && $fields !== []) {
                    $md[] = '';
                    foreach ($fields as $area => $answer) {
                        if ($answer === null || $answer === '') {
                            continue;
                        }
                        $md[] = '**' . $this->mdInline((string) $area) . '**';
                        $md[] = $this->mdBlock(is_scalar($answer) ? (string) $answer : json_encode($answer));
                        $md[] = '';
                    }
                }
                $md[] = '';
            }
        }
        $md[] = '';

        // Research memory
        $md[] = '## Research Memory';
        $memory = $bundle['researchMemory'] ?? null;
        if ($memory === null) {
            $md[] = $this->mdOmitted();
        } elseif (count($memory) === 0) {
            $md[] = '_No research-memory items recorded yet._';
        } else {
            foreach ($memory as $item) {
                $md[] = '### ' . $this->mdInline($item['title'] ?? '(untitled)');
                $md = array_merge($md, $this->mdKeyValues([
                    'Kind'   => $item['kind'] ?? '',
                    'Status' => $item['status'] ?? '',
                    'Source' => $item['source_ref'] ?? '',
                ]));
                if (! empty($item['body'])) {
                    $md[] = '';
                    $md[] = $this->mdBlock($item['body']);
                }
                $md[] = '';
            }
        }
        $md[] = '';

        // Sources summary (full citation files ship separately)
        $md[] = '## Sources';
        $sources = $bundle['sources'] ?? null;
        if ($sources === null) {
            $md[] = $this->mdOmitted();
        } else {
            $total = array_sum(array_map(fn ($b) => count($b['entries'] ?? []), $sources));
            if ($total === 0) {
                $md[] = '_No sources recorded yet._';
            } else {
                $md[] = 'The full source list is provided as BibTeX (`sources.bib`), RIS (`sources.ris`) and CSL-JSON (`sources.json`). Summary:';
                $md[] = '';
                foreach ($sources as $bib) {
                    $md[] = '### ' . $this->mdInline($bib['name'] ?? 'Bibliography');
                    foreach (($bib['entries'] ?? []) as $entry) {
                        $md[] = '- ' . $this->mdInline($this->humanCitation($entry));
                    }
                    $md[] = '';
                }
            }
        }
        $md[] = '';

        return implode("\n", $md) . "\n";
    }

    // =========================================================================
    // Citation exporters (BibTeX / RIS / CSL-JSON)
    // =========================================================================

    /**
     * Flatten the project's bibliography entries into a single list of normalised
     * citation records used by all three citation exporters.
     *
     * @param array<string,mixed> $bundle
     * @return array<int,array<string,mixed>>
     */
    public function flattenSources(array $bundle): array
    {
        $out = [];
        $sources = $bundle['sources'] ?? [];
        if (! is_array($sources)) {
            return $out;
        }
        foreach ($sources as $bib) {
            foreach (($bib['entries'] ?? []) as $entry) {
                $out[] = $this->normaliseEntry($entry, $bib);
            }
        }
        return $out;
    }

    /**
     * Normalise a raw bibliography_entry row (preferring its csl_data JSON, then
     * the flat columns) into a CSL-item-shaped array.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $bib
     * @return array<string,mixed>
     */
    private function normaliseEntry(array $entry, array $bib): array
    {
        $csl = is_array($entry['csl'] ?? null) ? $entry['csl'] : [];

        $id = 'ref-' . ($entry['id'] ?? uniqid());

        $title     = $csl['title']           ?? $entry['title']           ?? '';
        $container = $csl['container-title']  ?? $entry['container_title'] ?? '';
        $publisher = $csl['publisher']        ?? $entry['publisher']       ?? '';
        $volume    = $csl['volume']           ?? $entry['volume']          ?? '';
        $issue     = $csl['issue']            ?? $entry['issue']           ?? '';
        $pages     = $csl['page']             ?? $entry['pages']           ?? '';
        $doi       = $csl['DOI']              ?? $entry['doi']             ?? '';
        $url       = $csl['URL']              ?? $entry['url']             ?? '';
        $entryType = $entry['entry_type']     ?? ($csl['type'] ?? 'other');

        // Authors: CSL author array preferred; else the flat "authors" string.
        $authors = [];
        if (! empty($csl['author']) && is_array($csl['author'])) {
            foreach ($csl['author'] as $a) {
                if (! is_array($a)) {
                    continue;
                }
                $family = $a['family'] ?? '';
                $given  = $a['given']  ?? '';
                $literal = $a['literal'] ?? '';
                if ($family === '' && $given === '' && $literal !== '') {
                    $authors[] = ['literal' => $literal];
                } else {
                    $authors[] = array_filter(['family' => $family, 'given' => $given], fn ($v) => $v !== '');
                }
            }
        } elseif (! empty($entry['authors'])) {
            foreach ($this->splitAuthors((string) $entry['authors']) as $name) {
                $authors[] = $this->parseAuthorName($name);
            }
        }

        // Issued date: CSL date-parts preferred; else the flat "date" string year.
        $year = '';
        if (! empty($csl['issued']['date-parts'][0][0])) {
            $year = (string) $csl['issued']['date-parts'][0][0];
        } elseif (! empty($entry['date'])) {
            if (preg_match('/\b(\d{4})\b/', (string) $entry['date'], $m)) {
                $year = $m[1];
            }
        }

        return [
            'id'              => $id,
            'type'            => $this->cslType($entryType),
            'entry_type'      => $entryType,
            'title'           => (string) $title,
            'authors'         => $authors,
            'year'            => $year,
            'container-title' => (string) $container,
            'publisher'       => (string) $publisher,
            'volume'          => (string) $volume,
            'issue'           => (string) $issue,
            'page'            => (string) $pages,
            'DOI'             => (string) $doi,
            'URL'             => (string) $url,
            'archive'         => (string) ($entry['archive_name'] ?? ''),
            'archive_location' => (string) ($entry['archive_location'] ?? ''),
            'collection-title' => (string) ($entry['collection_title'] ?? ''),
            'note'            => (string) ($entry['notes'] ?? ''),
            'accessed'        => (string) ($entry['accessed_date'] ?? ''),
        ];
    }

    /**
     * Build a valid BibTeX document from the normalised source records.
     *
     * @param array<int,array<string,mixed>> $records
     */
    public function toBibtex(array $records): string
    {
        if ($records === []) {
            return "% No sources recorded for this project.\n";
        }

        $out = [];
        $usedKeys = [];
        foreach ($records as $r) {
            $type = $this->bibtexType($r['type']);
            $key  = $this->bibtexKey($r, $usedKeys);

            $fields = [];
            $this->bibField($fields, 'title', $r['title']);
            $names = $this->bibtexAuthors($r['authors']);
            if ($names !== '') {
                $fields[] = '  author = {' . $names . '}';
            }
            $this->bibField($fields, 'year', $r['year']);
            $this->bibField($fields, 'journal', $r['container-title']);
            $this->bibField($fields, 'publisher', $r['publisher']);
            $this->bibField($fields, 'volume', $r['volume']);
            $this->bibField($fields, 'number', $r['issue']);
            $this->bibField($fields, 'pages', $r['page']);
            $this->bibField($fields, 'doi', $r['DOI']);
            $this->bibField($fields, 'url', $r['URL']);
            $this->bibField($fields, 'note', $r['note']);

            $out[] = '@' . $type . '{' . $key . ",\n" . implode(",\n", $fields) . "\n}";
        }

        return implode("\n\n", $out) . "\n";
    }

    /**
     * Build a valid RIS document from the normalised source records.
     *
     * @param array<int,array<string,mixed>> $records
     */
    public function toRis(array $records): string
    {
        if ($records === []) {
            return "TY  - GEN\nTI  - No sources recorded for this project.\nER  - \n";
        }

        $out = [];
        foreach ($records as $r) {
            $lines = [];
            $lines[] = 'TY  - ' . $this->risType($r['type']);
            foreach ($r['authors'] as $a) {
                $lines[] = 'AU  - ' . $this->risAuthor($a);
            }
            $this->risLine($lines, 'TI', $r['title']);
            $this->risLine($lines, 'PY', $r['year']);
            $this->risLine($lines, 'JO', $r['container-title']);
            $this->risLine($lines, 'PB', $r['publisher']);
            $this->risLine($lines, 'VL', $r['volume']);
            $this->risLine($lines, 'IS', $r['issue']);
            $this->risLine($lines, 'SP', $r['page']);
            $this->risLine($lines, 'DO', $r['DOI']);
            $this->risLine($lines, 'UR', $r['URL']);
            $this->risLine($lines, 'AN', $r['archive']);
            $this->risLine($lines, 'N1', $r['note']);
            $lines[] = 'ER  - ';
            $out[] = implode("\n", $lines);
        }

        return implode("\n\n", $out) . "\n";
    }

    /**
     * Build a valid CSL-JSON document (array of CSL items) from the normalised
     * records.
     *
     * @param array<int,array<string,mixed>> $records
     */
    public function toCslJson(array $records): string
    {
        $items = [];
        foreach ($records as $r) {
            $item = [
                'id'   => $r['id'],
                'type' => $r['type'],
            ];
            if ($r['title'] !== '')           $item['title'] = $r['title'];
            if ($r['authors'] !== [])         $item['author'] = $r['authors'];
            if ($r['year'] !== '')            $item['issued'] = ['date-parts' => [[(int) $r['year']]]];
            if ($r['container-title'] !== '') $item['container-title'] = $r['container-title'];
            if ($r['publisher'] !== '')       $item['publisher'] = $r['publisher'];
            if ($r['volume'] !== '')          $item['volume'] = $r['volume'];
            if ($r['issue'] !== '')           $item['issue'] = $r['issue'];
            if ($r['page'] !== '')            $item['page'] = $r['page'];
            if ($r['DOI'] !== '')             $item['DOI'] = $r['DOI'];
            if ($r['URL'] !== '')             $item['URL'] = $r['URL'];
            if ($r['archive'] !== '')         $item['archive'] = $r['archive'];
            if ($r['archive_location'] !== '') $item['archive_location'] = $r['archive_location'];
            if ($r['collection-title'] !== '') $item['collection-title'] = $r['collection-title'];
            if ($r['note'] !== '')            $item['note'] = $r['note'];
            $items[] = $item;
        }

        return json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Machine JSON of the whole bundle (everything the Markdown shows, structured).
     *
     * @param array<string,mixed> $bundle
     */
    public function toJson(array $bundle): string
    {
        return json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    // =========================================================================
    // ZIP assembly
    // =========================================================================

    /**
     * Build the full ZIP bundle into a temp file under
     * config('heratio.storage_path').'/research-export/' and return its absolute
     * path. The caller streams it then deletes it. Returns null if ZipArchive is
     * unavailable or the directory cannot be created.
     *
     * @param array<string,mixed> $bundle
     */
    public function buildZip(int $projectId, array $bundle): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }

        $dir = $this->exportDir();
        if ($dir === null) {
            return null;
        }

        $slug = $this->slug($bundle['project']['title'] ?? ('project-' . $projectId));
        $stamp = date('Ymd-His');
        $path = $dir . DIRECTORY_SEPARATOR . 'heratio-research-' . $slug . '-' . $stamp . '-' . bin2hex(random_bytes(4)) . '.zip';

        $records = $this->flattenSources($bundle);

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $base = 'heratio-research-' . $slug . '/';
        $zip->addFromString($base . 'README.md', $this->readme($bundle));
        $zip->addFromString($base . 'manifest.json', json_encode($bundle['manifest'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        $zip->addFromString($base . 'project.md', $this->toMarkdown($bundle));
        $zip->addFromString($base . 'project.json', $this->toJson($bundle));
        $zip->addFromString($base . 'sources.bib', $this->toBibtex($records));
        $zip->addFromString($base . 'sources.ris', $this->toRis($records));
        $zip->addFromString($base . 'sources.json', $this->toCslJson($records));
        $zip->close();

        return is_file($path) ? $path : null;
    }

    /**
     * Ensure and return the export temp directory under the configured storage
     * path. Never a hardcoded path. Returns null on failure.
     */
    public function exportDir(): ?string
    {
        try {
            $storage = (string) config('heratio.storage_path');
            if ($storage === '') {
                $storage = function_exists('storage_path') ? storage_path('app') : sys_get_temp_dir();
            }
            $dir = rtrim($storage, '/\\') . DIRECTORY_SEPARATOR . 'research-export';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            return is_dir($dir) && is_writable($dir) ? $dir : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Filename suffix for a single-format download. */
    public function formatFilename(array $bundle, string $format, int $projectId): string
    {
        $slug = $this->slug($bundle['project']['title'] ?? ('project-' . $projectId));
        return match ($format) {
            'markdown' => 'heratio-research-' . $slug . '.md',
            'json'     => 'heratio-research-' . $slug . '.json',
            'bibtex'   => 'heratio-research-' . $slug . '.bib',
            'ris'      => 'heratio-research-' . $slug . '.ris',
            'csl'      => 'heratio-research-' . $slug . '-csl.json',
            default    => 'heratio-research-' . $slug . '.txt',
        };
    }

    // =========================================================================
    // Optional audit log (the only write this slice performs)
    // =========================================================================

    /** Record an export in research_export_log if the table exists. Never throws. */
    public function logExport(int $projectId, string $format, ?string $by): void
    {
        try {
            if (! Schema::hasTable('research_export_log')) {
                return;
            }
            DB::table('research_export_log')->insert([
                'project_id'  => $projectId,
                'format'      => substr($format, 0, 32),
                'exported_by' => $by !== null ? substr($by, 0, 255) : null,
                'exported_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging is best-effort; never block the download.
        }
    }

    // =========================================================================
    // Small helpers
    // =========================================================================

    private function has(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function safeFirst(string $table, callable $query)
    {
        if (! $this->has($table)) {
            return null;
        }
        try {
            return $query();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Decode a JSON column to an array, tolerating already-decoded values. */
    private function decodeJson($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /** Best readable label for a claim row. */
    private function claimLabel(array $c): string
    {
        $subject = trim((string) ($c['subject_label'] ?? ''));
        $pred    = trim((string) ($c['predicate'] ?? ''));
        $object  = trim((string) ($c['object_label'] ?? $c['object_value'] ?? ''));
        $parts = array_filter([$subject, $pred, $object], fn ($v) => $v !== '');
        $label = trim(implode(' ', $parts));
        return $label !== '' ? $label : ('Claim #' . ($c['id'] ?? '?'));
    }

    /** A human one-line citation for the Markdown sources summary. */
    private function humanCitation(array $entry): string
    {
        $csl = is_array($entry['csl'] ?? null) ? $entry['csl'] : [];
        $title = $csl['title'] ?? $entry['title'] ?? '(untitled source)';
        $authors = $entry['authors'] ?? '';
        if ($authors === '' && ! empty($csl['author']) && is_array($csl['author'])) {
            $names = [];
            foreach ($csl['author'] as $a) {
                $names[] = trim(($a['family'] ?? '') . (isset($a['given']) ? ', ' . $a['given'] : '')) ?: ($a['literal'] ?? '');
            }
            $authors = implode('; ', array_filter($names));
        }
        $year = '';
        if (! empty($csl['issued']['date-parts'][0][0])) {
            $year = (string) $csl['issued']['date-parts'][0][0];
        } elseif (! empty($entry['date'])) {
            $year = (string) $entry['date'];
        }
        $bits = array_filter([$authors, $year !== '' ? '(' . $year . ')' : '', $title]);
        return trim(implode(' ', $bits));
    }

    // ---- Markdown primitives -------------------------------------------------

    private function mdOmitted(): string
    {
        return '_This section is not available in this installation (its table is not present)._';
    }

    /** Escape Markdown-significant characters for inline text. */
    private function mdInline($text): string
    {
        $text = (string) $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\n", ' ', $text);
        return preg_replace('/([\\\\`*_\[\]<>])/', '\\\\$1', $text);
    }

    /** A blockquoted multi-line text block. */
    private function mdBlock($text): string
    {
        $text = (string) $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        return implode("\n", array_map(fn ($l) => '> ' . $l, $lines));
    }

    /**
     * Render a key/value table, skipping empty values.
     *
     * @param array<string,mixed> $pairs
     * @return array<int,string>
     */
    private function mdKeyValues(array $pairs): array
    {
        $rows = [];
        foreach ($pairs as $k => $v) {
            $v = (string) $v;
            if (trim($v) === '') {
                continue;
            }
            $rows[] = '- **' . $this->mdInline($k) . ':** ' . $this->mdInline($v);
        }
        return $rows;
    }

    /**
     * Render labelled multi-line blocks, skipping empty values.
     *
     * @param array<string,mixed> $pairs
     * @return array<int,string>
     */
    private function mdLabelledBlocks(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $k => $v) {
            $v = (string) $v;
            if (trim($v) === '') {
                continue;
            }
            $out[] = '**' . $this->mdInline($k) . '**';
            $out[] = $this->mdBlock($v);
            $out[] = '';
        }
        return $out;
    }

    private function readme(array $bundle): string
    {
        $title = $bundle['project']['title'] ?? 'research project';
        $lines = [];
        $lines[] = '# Heratio Research OS - project export';
        $lines[] = '';
        $lines[] = 'This bundle is a faithful, full-fidelity, open-format copy of "' . $this->mdInline($title) . '".';
        $lines[] = '';
        $lines[] = 'No lock-in. The exit door is always open.';
        $lines[] = '';
        $lines[] = '## Contents';
        $lines[] = '';
        $lines[] = '- `project.md` - human-readable narrative of the whole project (Markdown).';
        $lines[] = '- `project.json` - the same data, machine-readable (JSON).';
        $lines[] = '- `sources.bib` - all sources as BibTeX.';
        $lines[] = '- `sources.ris` - all sources as RIS.';
        $lines[] = '- `sources.json` - all sources as CSL-JSON.';
        $lines[] = '- `manifest.json` - what was included, what was omitted, and counts.';
        $lines[] = '';
        return implode("\n", $lines) . "\n";
    }

    // ---- Citation helpers ----------------------------------------------------

    /** Map an entry_type to a CSL type. */
    private function cslType(string $entryType): string
    {
        return match (strtolower($entryType)) {
            'book'                 => 'book',
            'chapter'              => 'chapter',
            'article', 'journal'   => 'article-journal',
            'thesis'               => 'thesis',
            'website', 'webpage'   => 'webpage',
            'archival'             => 'manuscript',
            default                => 'document',
        };
    }

    private function bibtexType(string $cslType): string
    {
        return match ($cslType) {
            'book'            => 'book',
            'chapter'         => 'incollection',
            'article-journal' => 'article',
            'thesis'          => 'phdthesis',
            'webpage'         => 'misc',
            'manuscript'      => 'misc',
            default           => 'misc',
        };
    }

    private function risType(string $cslType): string
    {
        return match ($cslType) {
            'book'            => 'BOOK',
            'chapter'         => 'CHAP',
            'article-journal' => 'JOUR',
            'thesis'          => 'THES',
            'webpage'         => 'ELEC',
            'manuscript'      => 'MANSCPT',
            default           => 'GEN',
        };
    }

    /**
     * @param array<int,string> $fields
     */
    private function bibField(array &$fields, string $name, $value): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }
        $fields[] = '  ' . $name . ' = {' . $this->bibtexEscape($value) . '}';
    }

    private function bibtexEscape(string $v): string
    {
        // Neutralise the BibTeX-significant braces/backslash; collapse newlines.
        $v = str_replace(["\r\n", "\r", "\n"], ' ', $v);
        $v = str_replace('\\', '\\textbackslash{}', $v);
        $v = str_replace(['{', '}'], ['\\{', '\\}'], $v);
        return $v;
    }

    /**
     * @param array<int,array<string,mixed>> $authors
     */
    private function bibtexAuthors(array $authors): string
    {
        $parts = [];
        foreach ($authors as $a) {
            if (isset($a['literal'])) {
                $parts[] = '{' . $this->bibtexEscape((string) $a['literal']) . '}';
                continue;
            }
            $family = $this->bibtexEscape((string) ($a['family'] ?? ''));
            $given  = $this->bibtexEscape((string) ($a['given'] ?? ''));
            if ($family !== '' && $given !== '') {
                $parts[] = $family . ', ' . $given;
            } elseif ($family !== '') {
                $parts[] = $family;
            } elseif ($given !== '') {
                $parts[] = $given;
            }
        }
        return implode(' and ', $parts);
    }

    /**
     * @param array<string,mixed> $record
     * @param array<string,bool> $used
     */
    private function bibtexKey(array $record, array &$used): string
    {
        $first = $record['authors'][0] ?? null;
        $surname = '';
        if (is_array($first)) {
            $surname = $first['family'] ?? $first['literal'] ?? '';
        }
        $surname = preg_replace('/[^A-Za-z0-9]/', '', (string) $surname);
        if ($surname === '') {
            $surname = 'Source';
        }
        $year = preg_replace('/[^0-9]/', '', (string) ($record['year'] ?? '')) ?: 'n.d.';
        $base = $surname . $year;

        $key = $base;
        $i = 0;
        while (isset($used[$key])) {
            $key = $base . chr(ord('a') + $i);
            $i++;
        }
        $used[$key] = true;
        return $key;
    }

    /**
     * @param array<int,string> $lines
     */
    private function risLine(array &$lines, string $tag, $value): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $lines[] = $tag . '  - ' . $value;
    }

    /**
     * @param array<string,mixed> $author
     */
    private function risAuthor(array $author): string
    {
        if (isset($author['literal'])) {
            return (string) $author['literal'];
        }
        $family = (string) ($author['family'] ?? '');
        $given  = (string) ($author['given'] ?? '');
        if ($family !== '' && $given !== '') {
            return $family . ', ' . $given;
        }
        return trim($family . $given);
    }

    /** Split a free-text author string into individual names. */
    private function splitAuthors(string $authors): array
    {
        $authors = str_replace([' and ', ' & '], ';', $authors);
        $parts = preg_split('/\s*;\s*/', $authors);
        return array_values(array_filter(array_map('trim', $parts ?: []), fn ($v) => $v !== ''));
    }

    /**
     * Parse a single author string into a CSL name part. "Family, Given" is
     * honoured; otherwise the last whitespace-token is treated as the family name.
     *
     * @return array<string,string>
     */
    private function parseAuthorName(string $name): array
    {
        $name = trim($name);
        if (strpos($name, ',') !== false) {
            [$family, $given] = array_map('trim', explode(',', $name, 2));
            return array_filter(['family' => $family, 'given' => $given], fn ($v) => $v !== '');
        }
        $tokens = preg_split('/\s+/', $name);
        if (count($tokens) > 1) {
            $family = array_pop($tokens);
            return ['family' => $family, 'given' => implode(' ', $tokens)];
        }
        return ['literal' => $name];
    }

    /** URL/file-safe slug. */
    private function slug(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');
        return $text !== '' ? substr($text, 0, 60) : 'project';
    }
}
