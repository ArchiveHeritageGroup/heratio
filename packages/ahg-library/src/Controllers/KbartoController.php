<?php

/**
 * KbartoController - KBART knowledge-base import/export HTTP layer.
 *
 * Wraps KbartService to expose:
 *   GET  /library-manage/kbart              — landing page
 *   GET  /library-manage/kbart/export       — download TSV
 *   GET  /library-manage/kbart/export-csv   — alias
 *   GET  /library-manage/kbart/import       — import form (retains context after commit error)
 *   POST /library-manage/kbart/preview      — preview TSV before committing
 *   POST /library-manage/kbart/commit       — commit import
 *   GET  /library-manage/kbart/template     — blank KBART template
 *
 * Copyright (C) 2026 Johan Pieterse
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\KbartService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class KbartoController extends Controller
{
    public function __construct(
        private KbartService $kbart
    ) {}

    /**
     * Landing page for the KBART module.
     * Shows a success banner when an import was just committed.
     */
    public function index(): \Illuminate\View\View
    {
        return view('ahg-library::kbart.index')->with([
            'import_success' => session('success'),
            'import_total_rows' => session('import_total_rows'),
            'import_records_created' => session('import_records_created'),
        ]);
    }

    /**
     * Stream a KBART TSV export for the given date range.
     *
     * Query params (all optional):
     *   start_date  YYYY-MM-DD
     *   end_date    YYYY-MM-DD
     *   limit       int (default 50 000)
     */
    public function export(Request $request): Response
    {
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $limit     = (int) $request->query('limit', 50_000);

        // Validate dates
        if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = null;
        }
        if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = null;
        }

        return $this->kbart->buildExportResponse($startDate, $endDate, $limit);
    }

    /**
     * Alias for export() so both /export and /export-csv work.
     */
    public function exportCsv(Request $request): Response
    {
        return $this->export($request);
    }

    /**
     * Show the KBART import form.
     *
     * Preserves context after a failed commit so the user can see their
     * preview and error rather than staring at a blank form. Reads raw_tsv
     * and commit_error from session when present.
     */
    public function import(): \Illuminate\View\View
    {
        // After a commit failure, the session carries everything we need
        // to re-display the preview table and error message.
        $previewData = session('preview_data');
        $rawTsv      = session('raw_tsv');
        $commitError = session('commit_error');

        // Force a fresh array if nothing in session (first load)
        if ($previewData === null) {
            $previewData = [];
            $rawTsv      = null;
            $commitError = null;
        }

        return view('ahg-library::kbart.import', [
            'preview_data'  => $previewData ?: [],
            'record_count'  => count($previewData ?: []),
            'raw_tsv'       => $rawTsv,
            'commit_error'  => $commitError,
            'commit_success' => session('commit_success'),
        ]);
    }

    /**
     * Preview a TSV file before committing the import.
     * Parses the first 20 rows and re-renders the import page with a
     * preview table. The session stores raw_tsv so the user can commit
     * without re-uploading.
     */
    public function preview(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\View\View
    {
        $request->validate([
            'kbart_file' => 'required|file|max:51200|mimes:txt,tsv,csv',
        ]);

        $file = $request->file('kbart_file');

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['txt', 'tsv', 'csv'])) {
            return redirect()->back()
                ->with('error', 'Please upload a .txt, .tsv, or .csv file.');
        }

        $raw = file_get_contents($file->getRealPath());
        $sizeKB = strlen($raw) / 1024;

        if ($sizeKB > 50) {
            return redirect()->back()
                ->with('error', "File is too large ({$sizeKB} KB). Maximum allowed is 50 MB.");
        }

        if (trim($raw) === '') {
            return redirect()->back()
                ->with('error', 'The uploaded file is empty.');
        }

        try {
            $previewData = $this->kbart->previewImport($raw, 20);

            return view('ahg-library::kbart.import')->with([
                'preview_data'  => $previewData,
                'record_count' => count($previewData),
                'raw_tsv'      => $raw,
                'commit_error'  => null,
                'commit_success' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('KbartoController preview error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to parse the file: ' . $e->getMessage());
        }
    }

    /**
     * Commit a KBART TSV import batch.
     *
     * On success: redirects to the KBART landing page with a success banner.
     * On failure: redirects back to the import form re-rendered with the
     * preview table, error message, and original raw_tsv intact so nothing
     * is lost and the user can retry.
     */
    public function commit(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'raw_tsv' => 'required|string',
        ]);

        $raw = $request->input('raw_tsv');

        // Capture preview data while the raw file is still available so we
        // can redisplay the preview table in the error case without forcing
        // the user to re-upload.
        $info = $this->kbart->previewImport($raw, 100);
        $totalRows = count($info);

        try {
            $count = $this->kbart->writeImportBatch($raw);

            if ($count === 0) {
                return redirect()->route('library.kbart-import')->with([
                    'commit_error'  => 'No records were imported. The file may contain only header rows or duplicates.',
                    'preview_data'  => $this->kbart->previewImport($raw, 20),
                    'record_count'  => $totalRows,
                    'raw_tsv'       => $raw,
                ]);
            }

            return redirect()->route('library.kbart')->with([
                'success' => "KBART import complete: {$count} of {$totalRows} record(s) created.",
                'import_total_rows'       => $totalRows,
                'import_records_created' => $count,
            ]);
        } catch (Throwable $e) {
            Log::error('KbartoController commit error: ' . $e->getMessage());

            return redirect()->route('library.kbart-import')->with([
                'commit_error'  => 'Import failed: ' . $e->getMessage(),
                'preview_data'  => $this->kbart->previewImport($raw, 20),
                'record_count'  => $totalRows,
                'raw_tsv'       => $raw,
            ]);
        }
    }

    /**
     * Download a blank KBART template TSV file.
     */
    public function template(): Response
    {
        $content = $this->kbart->getKbartTemplate();

        return response($content, 200, [
            'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="heratio-kbart-template.tsv"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
