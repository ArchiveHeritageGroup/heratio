<?php

/**
 * DataMigrationController - Controller for Heratio
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

namespace AhgDataMigration\Controllers;

use AhgDataMigration\Services\DataMigrationService;
use AtomExtensions\Repositories\DataMigrationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DataMigrationController extends \App\Http\Controllers\Controller
{
    protected DataMigrationRepository $repo;

    protected DataMigrationService $service;

    public function __construct(DataMigrationRepository $repo, DataMigrationService $service)
    {
        $this->repo = $repo;
        $this->service = $service;
    }

    // ── Existing: index ──────────────────────────────────────
    public function index()
    {
        $mappings = $this->repo->getMappings();
        $recentJobs = $this->repo->getRecentJobs(10);
        $stats = $this->repo->getStats();

        return view('ahg-data-migration::index', compact('mappings', 'recentJobs', 'stats'));
    }

    // ── Existing: upload ─────────────────────────────────────
    public function upload(Request $req)
    {
        if ($req->isMethod('get')) {
            $savedMappings = $this->repo->getMappings();
            $repositories = $this->repo->getRepositories();

            return view('ahg-data-migration::upload', compact('savedMappings', 'repositories'));
        }
        $req->validate([
            'file' => 'required|file|max:102400',
            'target_type' => 'required|string',
        ]);
        $path = $req->file('file')->store('data-migration/uploads');
        $fileName = $req->file('file')->getClientOriginalName();
        session(['dm_file' => $path, 'dm_filename' => $fileName,
            'dm_target' => $req->target_type]);

        return redirect()->route('data-migration.map');
    }

    // ── Existing: map ────────────────────────────────────────
    public function map()
    {
        $filePath = session('dm_file');
        $fileName = session('dm_filename', 'unknown');
        $targetType = session('dm_target', 'informationObject');
        if (! $filePath) {
            return redirect()->route('data-migration.upload')
                ->with('error', 'No file uploaded. Please upload a file first.');
        }
        $sourceColumns = $this->repo->getFileColumns($filePath);
        $totalRows = $this->repo->getFileRowCount($filePath);
        $targetFields = $this->repo->getTargetFields($targetType);
        $savedMappings = $this->repo->getMappings();

        return view('ahg-data-migration::map',
            compact('fileName', 'targetType', 'sourceColumns', 'totalRows', 'targetFields', 'savedMappings'));
    }

    // ── Existing: saveMapping ────────────────────────────────
    public function saveMapping(Request $req)
    {
        $req->validate(['name' => 'required|string|max:100',
            'mappings' => 'required|string']);
        $id = $this->repo->saveMapping([
            'name' => $req->name,
            'target_type' => session('dm_target', 'informationObject'),
            'field_mappings' => $req->mappings,
            'category' => $req->category ?? 'Custom',
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    // ── Existing: deleteMapping ──────────────────────────────
    public function deleteMapping(int $id)
    {
        $this->repo->deleteMapping($id);

        return redirect()->route('data-migration.index')
            ->with('success', 'Mapping deleted.');
    }

    // ── Existing: preview ────────────────────────────────────
    public function preview(Request $req)
    {
        $filePath = session('dm_file');
        $targetType = session('dm_target', 'informationObject');
        $mappings = json_decode($req->input('mappings', '{}'), true);
        $preview = $this->repo->previewRecords($filePath, $mappings, 10);

        return view('ahg-data-migration::preview',
            compact('preview', 'targetType', 'mappings'));
    }

    // ── Existing: execute ────────────────────────────────────
    public function execute(Request $req)
    {
        $filePath = session('dm_file');
        $targetType = session('dm_target', 'informationObject');
        $mappings = json_decode($req->input('mappings', '{}'), true);
        $jobId = $this->repo->queueImportJob($filePath, $targetType, $mappings);

        return redirect()->route('data-migration.job', $jobId)
            ->with('success', 'Import job queued. Job ID: '.$jobId);
    }

    // ── Existing: jobs ───────────────────────────────────────
    public function jobs()
    {
        $jobs = $this->repo->getAllJobs();

        return view('ahg-data-migration::jobs', compact('jobs'));
    }

    // ── Existing: jobStatus ──────────────────────────────────
    public function jobStatus(int $id)
    {
        $job = $this->repo->getJob($id);
        if (! $job) {
            abort(404);
        }

        return view('ahg-data-migration::job-status', compact('job'));
    }

    // ── Existing: batchExport ────────────────────────────────
    public function batchExport(Request $req)
    {
        $repositories = $this->repo->getRepositories();

        $counts = [
            'informationObject' => 0,
            'actor' => 0,
            'repository' => 0,
            'accession' => 0,
            'donor' => 0,
            'physicalObject' => 0,
        ];
        try {
            $counts['informationObject'] = \DB::table('information_object')->where('id', '!=', 1)->count();
            $counts['actor'] = \DB::table('actor')->where('id', '!=', 1)->count();
            $counts['repository'] = \DB::table('repository')->count();
            $counts['accession'] = \DB::table('accession')->count();
            if (\Schema::hasTable('donor')) {
                $counts['donor'] = \DB::table('donor')->count();
            }
            if (\Schema::hasTable('physical_object')) {
                $counts['physicalObject'] = \DB::table('physical_object')->count();
            }
        } catch (\Throwable $e) {
            // graceful fallback; counts already zeroed
        }

        $sectors = [
            'archive' => 'Archives (ISAD-G)',
            'museum' => 'Museum (Spectrum)',
            'library' => 'Library (MARC/RDA)',
            'gallery' => 'Gallery (CCO/VRA)',
            'dam' => 'Digital Assets (Dublin Core)',
        ];

        $levels = collect();
        try {
            if (\Schema::hasTable('term')) {
                $levels = \DB::table('term as t')
                    ->leftJoin('term_i18n as ti', function ($j) {
                        $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', app()->getLocale());
                    })
                    ->where('t.taxonomy_id', 34)
                    ->orderBy('ti.name')
                    ->select('t.id', 'ti.name')
                    ->get();
            }
        } catch (\Throwable $e) {
            $levels = collect();
        }

        return view('ahg-data-migration::batch-export', compact('repositories', 'counts', 'sectors', 'levels'));
    }

    // ── Existing: importResults ──────────────────────────────
    public function importResults()
    {
        $results = $this->repo->getImportResults();

        return view('ahg-data-migration::import-results', compact('results'));
    }

    // ════════════════════════════════════════════════════════
    // NEW METHODS — 17 gaps resolved
    // ════════════════════════════════════════════════════════

    /** GET /admin/data-migration/export */
    public function export(Request $req)
    {
        $repositories = $this->repo->getRepositories();
        if ($req->isMethod('post')) {
            $jobId = $this->repo->queueExportJob($req->all());

            return redirect()->route('data-migration.job', $jobId)
                ->with('success', 'Export job queued. Job ID: '.$jobId);
        }

        return view('ahg-data-migration::export', compact('repositories'));
    }

    /** GET|POST /admin/data-migration/import (alias wizard entry) */
    public function import(Request $req)
    {
        return redirect()->route('data-migration.upload');
    }

    /** GET|POST /admin/data-migration/preservica/import */
    public function preservicaImport(Request $req)
    {
        $repositories = $this->repo->getRepositories();
        if ($req->isMethod('post')) {
            $jobId = $this->repo->queuePreservicaImportJob($req->all());

            return redirect()->route('data-migration.job', $jobId)
                ->with('success', 'Preservica import job queued. Job ID: '.$jobId);
        }

        return view('ahg-data-migration::preservica-import', compact('repositories'));
    }

    /** GET|POST /admin/data-migration/preservica/export
     *  GET      /admin/data-migration/preservica/export/{id}  */
    public function preservicaExport(Request $req, ?int $id = null)
    {
        $repositories = $this->repo->getRepositories();
        if ($id) {
            $job = $this->repo->getJob($id);

            return view('ahg-data-migration::preservica-export', compact('repositories', 'job'));
        }
        if ($req->isMethod('post')) {
            $jobId = $this->repo->queuePreservicaExportJob($req->all());

            return redirect()->route('data-migration.job', $jobId)
                ->with('success', 'Preservica export job queued. Job ID: '.$jobId);
        }

        return view('ahg-data-migration::preservica-export', compact('repositories'));
    }

    /** GET /admin/data-migration/download — download a completed export file */
    public function download(Request $req)
    {
        $req->validate(['file' => 'required|string']);
        $path = 'data-migration/exports/'.basename($req->file);
        if (! Storage::exists($path)) {
            abort(404, 'Export file not found.');
        }

        return Storage::download($path);
    }

    /** GET /admin/data-migration/mapping — return mapping JSON for AJAX */
    public function getMapping(Request $req)
    {
        $req->validate(['id' => 'required|integer']);
        $mapping = $this->repo->getMappingById((int) $req->id);
        if (! $mapping) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($mapping);
    }

    /** GET /dataMigration/job/progress — AJAX polling */
    public function jobProgress(Request $req)
    {
        $req->validate(['id' => 'required|integer']);
        $job = $this->repo->getJob((int) $req->id);
        if (! $job) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'id' => $job['id'],
            'status' => $job['status'],
            'progress' => $job['progress'] ?? 0,
            'message' => $job['status_message'] ?? '',
            'errors' => $job['error_count'] ?? 0,
        ]);
    }

    /** POST /dataMigration/queue — queue a migration job via AJAX */
    public function queueJob(Request $req)
    {
        $req->validate(['type' => 'required|string']);
        $jobId = $this->repo->queueGenericJob($req->type, $req->all());

        return response()->json(['success' => true, 'job_id' => $jobId]);
    }

    /** POST /dataMigration/job/cancel */
    public function cancelJob(Request $req)
    {
        $req->validate(['id' => 'required|integer']);
        $this->repo->cancelJob((int) $req->id);
        if ($req->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('data-migration.jobs')
            ->with('success', 'Job cancelled.');
    }

    /** GET /dataMigration/exportCsv — export current result set as CSV */
    public function exportCsv(Request $req)
    {
        $req->validate(['job_id' => 'required|integer']);
        $data = $this->repo->getJobResults((int) $req->job_id);
        $fileName = 'migration-results-'.$req->job_id.'-'.date('Ymd').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];
        $callback = function () use ($data) {
            $handle = fopen('php://output', 'w');
            if (! empty($data)) {
                fputcsv($handle, array_keys($data[0]));
            }
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /** GET /dataMigration/loadMapping — load a saved mapping by id (AJAX) */
    public function loadMapping(Request $req)
    {
        $req->validate(['id' => 'required|integer']);
        $mapping = $this->repo->getMappingById((int) $req->id);
        if (! $mapping) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'id' => $mapping['id'],
            'name' => $mapping['name'],
            'field_mappings' => json_decode($mapping['field_mappings'] ?? '{}', true),
        ]);
    }

    /** POST /dataMigration/previewValidation — validate before committing */
    public function previewValidation(Request $req)
    {
        $filePath = session('dm_file');
        $targetType = session('dm_target', 'informationObject');
        $mappings = json_decode($req->input('mappings', '{}'), true);
        $validation = $this->repo->validateImport($filePath, $targetType, $mappings);

        return response()->json($validation);
    }

    /** GET /dataMigration/exportMapping/{id} — download mapping as JSON file */
    public function exportMapping(int $id)
    {
        $mapping = $this->repo->getMappingById($id);
        if (! $mapping) {
            abort(404);
        }
        $fileName = 'mapping-'.$mapping['name'].'-'.date('Ymd').'.json';

        return response()->json($mapping)
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
    }

    /** POST /dataMigration/importMapping — upload and save a mapping JSON file */
    public function importMapping(Request $req)
    {
        $req->validate(['mapping_file' => 'required|file|mimes:json|max:1024']);
        $content = file_get_contents($req->file('mapping_file')->getRealPath());
        $data = json_decode($content, true);
        if (! $data || ! isset($data['name'])) {
            return redirect()->route('data-migration.index')
                ->with('error', 'Invalid mapping file format.');
        }
        $this->repo->saveMapping([
            'name' => $data['name'].' (imported)',
            'target_type' => $data['target_type'] ?? 'informationObject',
            'field_mappings' => json_encode($data['field_mappings'] ?? []),
            'category' => $data['category'] ?? 'Imported',
        ]);

        return redirect()->route('data-migration.index')
            ->with('success', 'Mapping imported successfully.');
    }

    /** POST /dataMigration/validate — validate file without executing */
    public function validate(Request $req)
    {
        $filePath = session('dm_file');
        $targetType = session('dm_target', 'informationObject');
        if (! $filePath) {
            return response()->json(['valid' => false, 'error' => 'No file in session.']);
        }
        $result = $this->repo->validateImport($filePath, $targetType, []);

        return response()->json($result);
    }

    /** POST /dataMigration/executeAhgImport — execute AHG-specific import */
    public function executeAhgImport(Request $req)
    {
        $filePath = session('dm_file');
        $targetType = session('dm_target', 'informationObject');
        $mappings = json_decode($req->input('mappings', '{}'), true);
        $options = array_merge($req->only(['update_existing', 'skip_errors', 'publish']), [
            'ahg_import' => true,
        ]);
        $jobId = $this->repo->queueImportJob($filePath, $targetType, $mappings, $options);

        return response()->json(['success' => true, 'job_id' => $jobId]);
    }

    /** GET /dataMigration/ahgImportResults — AHG import results view */
    public function ahgImportResults(Request $req)
    {
        $req->validate(['job_id' => 'required|integer']);
        $job = $this->repo->getJob((int) $req->job_id);
        $results = $this->repo->getJobResults((int) $req->job_id);

        return view('ahg-data-migration::import-results', compact('job', 'results'));
    }

    public function previewData(Request $req)
    {
        $filePath = session('dm_file');
        $mappings = json_decode((string) $req->input('mappings', '[]'), true) ?: [];
        $limit = max(1, min(50, (int) $req->input('limit', 10)));

        $rows = collect();
        $headers = [];
        if ($filePath && file_exists($filePath)) {
            $result = $this->service->previewMapped($filePath, $mappings, $limit);
            $rows = collect($result['preview']);
            $headers = $result['headers'];
        }

        return view('ahg-data-migration::preview-data', compact('rows', 'headers'));
    }

    // ════════════════════════════════════════════════════════
    // Issue #740 — Data-migration exports parity (PSIS twin
    // atom-ahg-plugins#86). Six new actions:
    // exportEad, exportAhgCsv, sectorExport, detectSheets,
    // renameMapping, getPreview.
    // ════════════════════════════════════════════════════════

    /**
     * GET /admin/data-migration/export-ead.
     *
     * Streams an EAD 2002 XML download generated from the
     * current session mapping + uploaded file.
     */
    public function exportEad(Request $req)
    {
        $filePath = session('dm_file');
        $fileName = session('dm_filename', 'collection.csv');
        $mappings = $this->resolveMappingsFromRequest($req);

        if (! $filePath || ! file_exists($filePath)) {
            return redirect()->route('data-migration.upload')
                ->with('error', __('Session expired. Please upload a file again.'));
        }

        $parsed = $this->service->parseCSV($filePath, PHP_INT_MAX);
        $records = $this->service->transformWithMapping(
            $this->indexRows($parsed['rows'] ?? [], $parsed['headers'] ?? []),
            $parsed['headers'] ?? [],
            $mappings
        );

        $xml = $this->service->generateEadXml($records, $fileName);
        $download = pathinfo($fileName, PATHINFO_FILENAME).'.ead.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$download.'"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * GET /admin/data-migration/export-ahg-csv.
     *
     * Streams the AHG Extended CSV (ISAD-G columns + AHG sidecar
     * columns for security, rights, provenance, condition).
     */
    public function exportAhgCsv(Request $req)
    {
        $filePath = session('dm_file');
        $fileName = session('dm_filename', 'export.csv');
        $mappings = $this->resolveMappingsFromRequest($req);

        if (! $filePath || ! file_exists($filePath)) {
            return redirect()->route('data-migration.upload')
                ->with('error', __('Session expired. Please upload a file again.'));
        }
        if (empty($mappings)) {
            return redirect()->route('data-migration.map')
                ->with('error', __('No field mapping available. Save a mapping first.'));
        }

        $parsed = $this->service->parseCSV($filePath, PHP_INT_MAX);
        $records = $this->service->transformWithMapping(
            $this->indexRows($parsed['rows'] ?? [], $parsed['headers'] ?? []),
            $parsed['headers'] ?? [],
            $mappings
        );

        if (empty($records)) {
            return redirect()->route('data-migration.map')
                ->with('error', __('No data to export. Check your field mappings.'));
        }

        $csv = $this->service->buildAhgCsv($records);
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $download = $base.'_ahg_extended_'.date('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$download.'"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * GET|POST /admin/data-migration/sector-export/{sector?}.
     *
     * GET  – render the sector picker / column resolver view.
     * POST – stream a CSV using the sector-specific column resolver,
     *        sourced from the current mapping session.
     */
    public function sectorExport(Request $req, ?string $sector = null)
    {
        $sectors = $this->sectorChoices();
        $sector = $sector ?: $req->input('sector', 'archive');
        if (! array_key_exists($sector, $sectors)) {
            $sector = 'archive';
        }

        if ($req->isMethod('post')) {
            $filePath = session('dm_file');
            $fileName = session('dm_filename', 'sector-export.csv');
            $mappings = $this->resolveMappingsFromRequest($req);

            if (! $filePath || ! file_exists($filePath)) {
                return redirect()->route('data-migration.sector-export-new', $sector)
                    ->with('error', __('Session expired. Please upload a file again.'));
            }

            $parsed = $this->service->parseCSV($filePath, PHP_INT_MAX);
            $records = $this->service->transformWithMapping(
                $this->indexRows($parsed['rows'] ?? [], $parsed['headers'] ?? []),
                $parsed['headers'] ?? [],
                $mappings
            );

            $csv = $this->service->sectorExportCsv($sector, $records);
            $base = pathinfo($fileName, PATHINFO_FILENAME);
            $download = $sector.'_'.$base.'_'.date('Ymd_His').'.csv';

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$download.'"',
                'Cache-Control' => 'no-cache, must-revalidate',
            ]);
        }

        $columns = $this->service->sectorColumns($sector);

        return view('ahg-data-migration::sector-export', compact('sector', 'sectors', 'columns'));
    }

    /**
     * POST /admin/data-migration/detect-sheets — JSON.
     *
     * Probes an uploaded spreadsheet (XLS/XLSX/CSV/TSV) and returns
     * sheet metadata + column headers for the UI to render.
     */
    public function detectSheets(Request $req)
    {
        $req->validate(['file' => 'required|file|max:102400']);

        $tmp = $req->file('file')->getRealPath();
        $clientName = $req->file('file')->getClientOriginalName();
        $persistedPath = $tmp;

        // PhpSpreadsheet wants a path with the right extension.
        $ext = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (in_array($ext, ['xls', 'xlsx', 'csv', 'tsv', 'txt'], true)) {
            $persistedPath = tempnam(sys_get_temp_dir(), 'dm_detect_').'.'.$ext;
            copy($tmp, $persistedPath);
        }

        $result = $this->service->detectSpreadsheetSheets($persistedPath);

        if ($persistedPath !== $tmp && file_exists($persistedPath)) {
            @unlink($persistedPath);
        }

        return response()->json($result);
    }

    /**
     * POST /admin/data-migration/rename-mapping — JSON.
     */
    public function renameMapping(Request $req)
    {
        $req->validate([
            'id' => 'required|integer',
            'name' => 'required|string|max:255',
        ]);

        $ok = $this->service->renameMapping((int) $req->input('id'), (string) $req->input('name'));

        return response()->json(['success' => $ok]);
    }

    /**
     * GET /admin/data-migration/get-preview — JSON.
     *
     * Returns the first N rows of the source file projected through
     * the current saved mapping. Used by the live preview pane.
     */
    public function getPreview(Request $req)
    {
        $filePath = session('dm_file');
        if (! $filePath || ! file_exists($filePath)) {
            return response()->json(['success' => false, 'error' => 'No file in session']);
        }

        $mappings = $this->resolveMappingsFromRequest($req);
        $limit = max(1, min(50, (int) $req->input('limit', 10)));

        $result = $this->service->previewMapped($filePath, $mappings, $limit);

        return response()->json([
            'success' => true,
            'headers' => $result['headers'],
            'rows' => $result['preview'],
            'raw' => $result['rows'],
            'count' => count($result['preview']),
        ]);
    }

    /**
     * Pull a mapping definition either from the inline request payload
     * (mappings=json) or from a saved mapping_id, or from the session.
     */
    protected function resolveMappingsFromRequest(Request $req): array
    {
        $payload = $req->input('mappings');
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (is_array($payload)) {
            return $payload;
        }

        $mappingId = (int) $req->input('mapping_id', 0);
        if ($mappingId > 0) {
            $row = $this->service->getMapping($mappingId);
            if ($row) {
                return is_array($row['field_mappings']) ? $row['field_mappings'] : [];
            }
        }

        $sessionMappings = session('dm_mapping', []);

        return is_array($sessionMappings) ? $sessionMappings : [];
    }

    /**
     * Convert assoc-keyed rows from parseCSV() to PSIS-style indexed
     * rows so transformWithMapping can resolve headers by position.
     */
    protected function indexRows(array $rows, array $headers): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (is_array($row) && array_keys($row) === range(0, count($row) - 1)) {
                $indexed[] = $row;

                continue;
            }
            $line = [];
            foreach ($headers as $h) {
                $line[] = is_array($row) ? ($row[$h] ?? '') : '';
            }
            $indexed[] = $line;
        }

        return $indexed;
    }

    /**
     * Sector choices, ahg_dropdown-aware. Falls back to the canonical
     * five sectors when the dropdown table has no rows.
     */
    protected function sectorChoices(): array
    {
        $defaults = [
            'archive' => 'Archives (ISAD-G)',
            'museum' => 'Museum (Spectrum)',
            'library' => 'Library (MARC / RDA)',
            'gallery' => 'Gallery (CCO / VRA)',
            'dam' => 'Digital Assets (Dublin Core)',
        ];

        try {
            if (\Schema::hasTable('ahg_dropdown')) {
                $rows = DB::table('ahg_dropdown')
                    ->where('taxonomy', 'data_migration_sector')
                    ->where('is_active', 1)
                    ->orderBy('sort_order')
                    ->pluck('label', 'code')
                    ->toArray();
                if (! empty($rows)) {
                    return $rows;
                }
            }
        } catch (\Throwable $e) {
            // dropdown lookup is best-effort
        }

        return $defaults;
    }
}
