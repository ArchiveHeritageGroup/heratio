<?php

/**
 * ReplicationPackService - Heratio ahg-research
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

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * heratio#1238 - Research OS #16 (moonshot 22): Replication Pack.
 *
 * Per-project. One click produces everything needed to replicate a study,
 * assembled READ-ONLY from existing Research OS slices:
 *
 *   - Method Protocol           (research_method_protocol,        #1231)
 *   - Analysis Results          (research_analysis_result +
 *                                research_analysis_result_claim,  #1234)
 *   - Decision Log              (research_decision_log,           #1224)
 *   - Claims + Evidence         (research_assertion +
 *                                research_assertion_evidence,     #1223)
 *
 * Nothing is computed or re-derived: each section is read straight from the
 * slice that owns it. Every read is Schema::hasTable-guarded and wrapped in
 * try/catch so a partial install never 500s - a missing slice simply becomes an
 * OMITTED entry in the manifest. No existing table is ever altered and no live
 * write happens beyond the optional research_replication_log audit line.
 *
 * The bundle is written as a ZIP under config('heratio.storage_path') (NEVER a
 * hardcoded path), streamed to the browser by the controller, and the temp file
 * is removed afterwards. The README/manifest spells out what was included and
 * what was withheld - in particular, restricted/embargoed data and on-disk
 * artifacts are described by reference (path + provenance) but their bytes are
 * NOT bundled, so an ethics or embargo restriction is honoured by default.
 */
class ReplicationPackService
{
    /** Sub-directory (relative to config('heratio.storage_path')) for built packs. */
    public const PACK_SUBDIR = 'research-replication';

    /** Optional audit table - the feature works fully without it. */
    public const LOG_TABLE = 'research_replication_log';

    // =====================================================================
    // PREVIEW / SUMMARY (drives the page; never throws)
    // =====================================================================

    /**
     * A lightweight summary of what a replication pack WOULD contain for a
     * project, used to render the page and its empty-states. Each section
     * reports whether its slice is installed and how many rows it holds.
     *
     * @return array<string,mixed>
     */
    public function summary(int $projectId): array
    {
        return [
            'method'     => $this->methodSummary($projectId),
            'analysis'   => $this->analysisSummary($projectId),
            'decisions'  => $this->decisionSummary($projectId),
            'claims'     => $this->claimSummary($projectId),
            'artifacts'  => $this->artifactSummary($projectId),
        ];
    }

    private function methodSummary(int $projectId): array
    {
        $available = $this->hasTable('research_method_protocol');
        $count = 0;
        if ($available) {
            $count = $this->safeCount('research_method_protocol', $projectId);
        }

        return [
            'label'     => 'Method Protocol',
            'available' => $available,
            'count'     => $count,
        ];
    }

    private function analysisSummary(int $projectId): array
    {
        $available = $this->hasTable('research_analysis_result');
        $count = 0;
        $links = 0;
        if ($available) {
            $count = $this->safeCount('research_analysis_result', $projectId);
        }
        if ($count > 0 && $this->hasTable('research_analysis_result_claim')) {
            try {
                $links = (int) DB::table('research_analysis_result_claim as l')
                    ->join('research_analysis_result as r', 'r.id', '=', 'l.result_id')
                    ->where('r.project_id', $projectId)
                    ->count();
            } catch (\Throwable $e) {
                $links = 0;
            }
        }

        return [
            'label'     => 'Analysis results + provenance',
            'available' => $available,
            'count'     => $count,
            'links'     => $links,
        ];
    }

    private function decisionSummary(int $projectId): array
    {
        $available = $this->hasTable('research_decision_log');

        return [
            'label'     => 'Decision Log',
            'available' => $available,
            'count'     => $available ? $this->safeCount('research_decision_log', $projectId) : 0,
        ];
    }

    private function claimSummary(int $projectId): array
    {
        $available = $this->hasTable('research_assertion');
        $count = 0;
        $evidence = 0;
        if ($available) {
            $count = $this->safeCount('research_assertion', $projectId);
        }
        if ($count > 0 && $this->hasTable('research_assertion_evidence')) {
            try {
                $evidence = (int) DB::table('research_assertion_evidence as e')
                    ->join('research_assertion as a', 'a.id', '=', 'e.assertion_id')
                    ->where('a.project_id', $projectId)
                    ->count();
            } catch (\Throwable $e) {
                $evidence = 0;
            }
        }

        return [
            'label'     => 'Claims + evidence',
            'available' => $available,
            'count'     => $count,
            'evidence'  => $evidence,
        ];
    }

    private function artifactSummary(int $projectId): array
    {
        // Analysis artifacts are referenced (path + provenance) in the pack but
        // their bytes are NOT bundled - this keeps restricted/embargoed files
        // out of an exportable archive by default.
        $count = 0;
        if ($this->hasTable('research_analysis_result')) {
            try {
                $count = (int) DB::table('research_analysis_result')
                    ->where('project_id', $projectId)
                    ->whereNotNull('artifact_path')
                    ->where('artifact_path', '<>', '')
                    ->count();
            } catch (\Throwable $e) {
                $count = 0;
            }
        }

        return [
            'label'     => 'Data / code artifacts (referenced, not bundled)',
            'available' => $count > 0,
            'count'     => $count,
        ];
    }

    // =====================================================================
    // BUILD (assemble the bundle as files-in-memory, then ZIP)
    // =====================================================================

    /**
     * Assemble the complete replication pack as an in-memory map of
     * relative-path => string-content, plus a structured manifest describing
     * what was included and what was omitted (with reasons). Nothing is written
     * to disk here - zipFiles() turns this into a ZIP.
     *
     * @return array{files: array<string,string>, manifest: array<string,mixed>}
     */
    public function assemble(object $project): array
    {
        $projectId = (int) ($project->id ?? 0);

        $files    = [];
        $included = [];
        $omitted  = [];

        // --- Method Protocol (#1231) -------------------------------------
        if ($this->hasTable('research_method_protocol')) {
            $rows = $this->safeRows('research_method_protocol', $projectId, 'id');
            if (! empty($rows)) {
                $files['method-protocol.json'] = $this->jsonBlock($rows);
                $included[] = 'Method Protocol (' . count($rows) . ') - method-protocol.json';
            } else {
                $omitted[] = 'Method Protocol - no protocol has been recorded for this project.';
            }
        } else {
            $omitted[] = 'Method Protocol - the Method Studio slice is not installed.';
        }

        // --- Analysis results + provenance + claim links (#1234) ---------
        if ($this->hasTable('research_analysis_result')) {
            $results = $this->safeRows('research_analysis_result', $projectId, 'id');
            if (! empty($results)) {
                $links = $this->analysisLinks($projectId);
                $payload = [];
                foreach ($results as $r) {
                    $arr = (array) $r;
                    $arr['linked_claims'] = $links[(int) $r->id] ?? [];
                    $payload[] = $arr;
                }
                $files['analysis-results.json'] = $this->jsonBlock($payload);
                $included[] = 'Analysis results + provenance + linked claims (' . count($results) . ') - analysis-results.json';
            } else {
                $omitted[] = 'Analysis results - none registered for this project.';
            }
        } else {
            $omitted[] = 'Analysis results - the Analysis Bridge slice is not installed.';
        }

        // --- Decision Log (#1224) ----------------------------------------
        if ($this->hasTable('research_decision_log')) {
            $rows = $this->safeRows('research_decision_log', $projectId, 'decided_at');
            if (! empty($rows)) {
                $files['decision-log.json'] = $this->jsonBlock($rows);
                $files['decision-log.csv']  = $this->decisionCsv($rows);
                $included[] = 'Decision Log (' . count($rows) . ') - decision-log.json, decision-log.csv';
            } else {
                $omitted[] = 'Decision Log - no decisions have been recorded for this project.';
            }
        } else {
            $omitted[] = 'Decision Log - the Decision Log slice is not installed.';
        }

        // --- Claims + evidence (#1223) -----------------------------------
        if ($this->hasTable('research_assertion')) {
            $claims = $this->safeRows('research_assertion', $projectId, 'id');
            if (! empty($claims)) {
                $evidence = $this->claimEvidence($projectId);
                $payload = [];
                foreach ($claims as $c) {
                    $arr = (array) $c;
                    $arr['evidence'] = $evidence[(int) $c->id] ?? [];
                    $payload[] = $arr;
                }
                $files['claims-and-evidence.json'] = $this->jsonBlock($payload);
                $included[] = 'Claims + evidence (' . count($claims) . ') - claims-and-evidence.json';
            } else {
                $omitted[] = 'Claims + evidence - the Claim Ledger holds no claims for this project.';
            }
        } else {
            $omitted[] = 'Claims + evidence - the Claim Ledger slice is not installed.';
        }

        // --- Data / code references (bytes withheld by ethics default) ---
        $references = $this->dataCodeReferences($projectId);
        if (! empty($references)) {
            $files['data-code-references.json'] = $this->jsonBlock($references);
            $included[] = 'Data + code references (' . count($references) . ') - data-code-references.json (paths/repos only; files NOT bundled)';
            $omitted[]  = 'Underlying data files and code bytes - referenced by path/repository only. Restricted, embargoed or consent-limited material is intentionally NOT included; request it through the channel named in the reference where ethics permit.';
        }

        // --- Manifest + README -------------------------------------------
        $manifest = $this->manifest($project, $included, $omitted);
        $files['manifest.json'] = $this->jsonBlock($manifest);
        $files['README.md']     = $this->readme($project, $included, $omitted);

        return ['files' => $files, 'manifest' => $manifest];
    }

    /**
     * Build the pack and write it to a ZIP under config('heratio.storage_path').
     * Returns [absolutePath, downloadFilename] or null on failure. The caller is
     * responsible for streaming the file and deleting it afterwards.
     *
     * @return array{0:string,1:string}|null
     */
    public function build(object $project): ?array
    {
        try {
            $bundle = $this->assemble($project);
            $root = $this->storageRoot();
            if ($root === null) {
                return null;
            }

            $dir = $root . '/' . self::PACK_SUBDIR . '/' . (int) ($project->id ?? 0);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (! is_dir($dir) || ! is_writable($dir)) {
                return null;
            }

            $slug = Str::slug((string) ($project->title ?? 'project')) ?: 'project';
            $abs  = $dir . '/replication-pack-' . $slug . '-' . Str::random(8) . '.zip';

            if (! $this->zipFiles($bundle['files'], $abs)) {
                return null;
            }

            $download = 'replication-pack-' . $slug . '-' . date('Ymd') . '.zip';

            return [$abs, $download];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Write an in-memory file map to a ZIP at $absPath using PHP's ZipArchive.
     * Returns true on success. Never throws.
     *
     * @param array<string,string> $files
     */
    private function zipFiles(array $files, string $absPath): bool
    {
        if (! class_exists(\ZipArchive::class)) {
            return false;
        }
        try {
            $zip = new \ZipArchive();
            if ($zip->open($absPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return false;
            }
            foreach ($files as $name => $content) {
                $zip->addFromString($this->safeEntryName($name), (string) $content);
            }
            $zip->close();

            return is_file($absPath);
        } catch (\Throwable $e) {
            if (is_file($absPath)) {
                @unlink($absPath);
            }
            return false;
        }
    }

    // =====================================================================
    // OPTIONAL AUDIT LINE
    // =====================================================================

    /** Record who built a pack and when. No-op (returns false) if the table is absent. */
    public function logBuild(int $projectId, ?int $researcherId): bool
    {
        if (! $this->hasTable(self::LOG_TABLE)) {
            return false;
        }
        try {
            DB::table(self::LOG_TABLE)->insert([
                'project_id' => $projectId,
                'built_by'   => $researcherId,
                'built_at'   => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Recent build history for a project (for the page). Empty if unavailable.
     *
     * @return array<int,object>
     */
    public function recentBuilds(int $projectId, int $limit = 10): array
    {
        if (! $this->hasTable(self::LOG_TABLE)) {
            return [];
        }
        try {
            return DB::table(self::LOG_TABLE)
                ->where('project_id', $projectId)
                ->orderByDesc('id')
                ->limit($limit)
                ->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =====================================================================
    // SECTION READERS (read-only; each guarded + try/catch)
    // =====================================================================

    /**
     * Claim links per analysis result, resolved to a readable claim label.
     *
     * @return array<int,array<int,array<string,mixed>>>  result_id => [link, ...]
     */
    private function analysisLinks(int $projectId): array
    {
        $out = [];
        if (! $this->hasTable('research_analysis_result_claim')) {
            return $out;
        }
        try {
            $links = DB::table('research_analysis_result_claim as l')
                ->join('research_analysis_result as r', 'r.id', '=', 'l.result_id')
                ->where('r.project_id', $projectId)
                ->select('l.*')
                ->orderBy('l.result_id')
                ->get();

            if ($links->isEmpty()) {
                return $out;
            }

            $labels = [];
            if ($this->hasTable('research_assertion')) {
                $ids = $links->pluck('assertion_id')->unique()->all();
                $labels = DB::table('research_assertion')
                    ->whereIn('id', $ids)
                    ->get()
                    ->mapWithKeys(fn ($a) => [
                        (int) $a->id => (string) ($a->object_value ?: $a->subject_label ?: ('Claim #' . $a->id)),
                    ])->all();
            }

            foreach ($links as $l) {
                $out[(int) $l->result_id][] = [
                    'assertion_id' => (int) $l->assertion_id,
                    'claim'        => $labels[(int) $l->assertion_id] ?? ('Claim #' . $l->assertion_id),
                    'relationship' => (string) ($l->relationship ?? ''),
                    'note'         => $l->note ?? null,
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $out;
    }

    /**
     * Evidence rows per claim.
     *
     * @return array<int,array<int,array<string,mixed>>>  assertion_id => [evidence, ...]
     */
    private function claimEvidence(int $projectId): array
    {
        $out = [];
        if (! $this->hasTable('research_assertion_evidence') || ! $this->hasTable('research_assertion')) {
            return $out;
        }
        try {
            $rows = DB::table('research_assertion_evidence as e')
                ->join('research_assertion as a', 'a.id', '=', 'e.assertion_id')
                ->where('a.project_id', $projectId)
                ->select('e.*')
                ->orderBy('e.assertion_id')
                ->get();

            foreach ($rows as $e) {
                $out[(int) $e->assertion_id][] = [
                    'id'           => (int) $e->id,
                    'source_type'  => (string) ($e->source_type ?? ''),
                    'source_id'    => (int) ($e->source_id ?? 0),
                    'relationship' => (string) ($e->relationship ?? ''),
                    'selector'     => $this->decodeJson($e->selector_json ?? null),
                    'note'         => $e->note ?? null,
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $out;
    }

    /**
     * Data + code reference rows drawn from the analysis-result provenance.
     * Paths/repos only - no bytes. This is what an external replicator needs in
     * order to request or locate the underlying material.
     *
     * @return array<int,array<string,mixed>>
     */
    private function dataCodeReferences(int $projectId): array
    {
        if (! $this->hasTable('research_analysis_result')) {
            return [];
        }
        try {
            $rows = DB::table('research_analysis_result')
                ->where('project_id', $projectId)
                ->where(function ($w) {
                    $w->whereNotNull('source_data_ref')
                      ->orWhereNotNull('code_ref')
                      ->orWhereNotNull('artifact_path');
                })
                ->orderBy('id')
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'result_id'           => (int) $r->id,
                    'result_title'        => (string) ($r->title ?? ''),
                    'source_data_ref'     => $r->source_data_ref ?? null,
                    'source_data_version' => $r->source_data_version ?? null,
                    'code_ref'            => $r->code_ref ?? null,
                    'artifact_path'       => $r->artifact_path ?? null,
                    'bytes_included'      => false,
                    'access_note'         => 'File bytes are withheld from this pack. Request access via the data/code reference above where ethics and consent permit.',
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =====================================================================
    // MANIFEST + README
    // =====================================================================

    /**
     * @param array<int,string> $included
     * @param array<int,string> $omitted
     * @return array<string,mixed>
     */
    private function manifest(object $project, array $included, array $omitted): array
    {
        return [
            'pack'         => 'Replication Pack',
            'standard'     => 'Heratio Research OS - Replication Pack (moonshot 22, heratio#1238)',
            'generated_at' => now()->toIso8601String(),
            'project'      => [
                'id'              => (int) ($project->id ?? 0),
                'title'           => (string) ($project->title ?? ''),
                'project_type'    => $project->project_type ?? null,
                'institution'     => $project->institution ?? null,
                'supervisor'      => $project->supervisor ?? null,
                'funding_source'  => $project->funding_source ?? null,
                'grant_number'    => $project->grant_number ?? null,
                'ethics_approval' => $project->ethics_approval ?? null,
            ],
            'included'     => array_values($included),
            'omitted'      => array_values($omitted),
            'ethics_note'  => $this->ethicsNote(),
            'read_only'    => true,
        ];
    }

    private function ethicsNote(): string
    {
        return 'Ethics and access: this pack assembles metadata, provenance and the reasoning '
            . 'trail of the study. It does NOT bundle the underlying data files or code bytes; '
            . 'those are referenced by path or repository only. Restricted, embargoed, personal '
            . 'or consent-limited material must be requested through the channel named in each '
            . 'data/code reference, and shared only where the project ethics approval and the '
            . 'data subjects\' consent permit. Any item that could not be lawfully or ethically '
            . 'included is listed under "omitted" with the reason.';
    }

    /**
     * @param array<int,string> $included
     * @param array<int,string> $omitted
     */
    private function readme(object $project, array $included, array $omitted): string
    {
        $title = (string) ($project->title ?? 'Untitled project');
        $when  = now()->toDayDateTimeString();

        $lines = [];
        $lines[] = '# Replication Pack';
        $lines[] = '';
        $lines[] = 'Project: ' . $title;
        $lines[] = 'Generated: ' . $when;
        $lines[] = 'Produced by: Heratio Research OS - Replication Pack (moonshot 22, heratio#1238)';
        $lines[] = '';
        $lines[] = 'This pack assembles, read-only, everything needed to understand and replicate';
        $lines[] = 'the study: the method, the analysis results and their provenance, the decision';
        $lines[] = 'trail, and the claims with their supporting and refuting evidence.';
        $lines[] = '';
        $lines[] = '## What is included';
        $lines[] = '';
        if (empty($included)) {
            $lines[] = '- (nothing yet - this project has not recorded any of the source slices)';
        } else {
            foreach ($included as $item) {
                $lines[] = '- ' . $item;
            }
        }
        $lines[] = '';
        $lines[] = '## What is omitted';
        $lines[] = '';
        if (empty($omitted)) {
            $lines[] = '- (nothing omitted)';
        } else {
            foreach ($omitted as $item) {
                $lines[] = '- ' . $item;
            }
        }
        $lines[] = '';
        $lines[] = '## Ethics and access';
        $lines[] = '';
        $lines[] = $this->ethicsNote();
        $lines[] = '';
        $lines[] = '## Files';
        $lines[] = '';
        $lines[] = '- manifest.json            - machine-readable index of this pack (included / omitted / ethics).';
        $lines[] = '- method-protocol.json     - the recorded research method protocol(s).';
        $lines[] = '- analysis-results.json    - registered results with full provenance + linked claims.';
        $lines[] = '- decision-log.json / .csv - the project decision trail (the "why").';
        $lines[] = '- claims-and-evidence.json - the claims with their supporting / refuting evidence.';
        $lines[] = '- data-code-references.json - pointers to data and code (paths/repos only; bytes withheld).';
        $lines[] = '';
        $lines[] = 'Heratio is jurisdiction-neutral. Apply your own institution\'s ethics, data-';
        $lines[] = 'protection and consent regime before redistributing any referenced material.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    // =====================================================================
    // INTERNAL HELPERS
    // =====================================================================

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function safeCount(string $table, int $projectId): int
    {
        try {
            return (int) DB::table($table)->where('project_id', $projectId)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Read all rows for a project, ordered, as plain stdClass rows. Empty on any
     * failure so the caller degrades to an "omitted" manifest entry.
     *
     * @return array<int,object>
     */
    private function safeRows(string $table, int $projectId, string $orderBy = 'id'): array
    {
        try {
            $q = DB::table($table)->where('project_id', $projectId);
            // Order by the requested column when present, else fall back to id.
            try {
                if (Schema::hasColumn($table, $orderBy)) {
                    $q->orderBy($orderBy);
                } else {
                    $q->orderBy('id');
                }
            } catch (\Throwable $e) {
                $q->orderBy('id');
            }

            return $q->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Pretty JSON for a pack entry. Never throws; returns '[]' on failure. */
    private function jsonBlock(array $data): string
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return is_string($json) ? $json : '[]';
    }

    /** Decode a JSON column to an array/scalar, or return it unchanged when not JSON. */
    private function decodeJson($value)
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * Decision Log as CSV (the most replication-friendly form of the "why" trail).
     *
     * @param array<int,object> $rows
     */
    private function decisionCsv(array $rows): string
    {
        $cols = ['id', 'decision_type', 'summary', 'reason', 'related_ref', 'decided_by', 'decided_at'];
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        fputcsv($fh, $cols);
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $c) {
                $line[] = (string) ($r->{$c} ?? '');
            }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);

        return is_string($out) ? $out : '';
    }

    /** Storage root from config (never hardcoded), or null if unset. */
    private function storageRoot(): ?string
    {
        $root = rtrim((string) config('heratio.storage_path'), '/');

        return $root !== '' ? $root : null;
    }

    /** Confine a ZIP entry name to a safe relative path (no traversal, no leading slash). */
    private function safeEntryName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = str_replace('..', '', $name);

        return ltrim($name, '/') ?: 'file.txt';
    }

    /** Current researcher id (from ResearchService) for the audit line, or null. */
    public function currentResearcherId(): ?int
    {
        try {
            if (! Auth::check()) {
                return null;
            }
            $researcher = app(ResearchService::class)->getResearcherByUserId(Auth::id());

            return $researcher ? (int) $researcher->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
