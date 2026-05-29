<?php

/**
 * MarcEditorController - MARC editor for library records.
 *
 * Handles:
 *   - Index (landing page)
 *   - Import (batch MARCXML upload)
 *   - Import binary MARC21 (ISO 2709)
 *   - Edit existing library items in MARC format
 *   - Download MARCXML / MARC binary
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryService;
use AhgLibrary\Services\MarcEditService;
use AhgLibrary\Services\Marc21DecoderService;
use AhgLibrary\Services\MarcMergeService;
use AhgLibrary\Services\MarcValidationService;
use AhgMetadataExport\Services\Exporters\Marc21BinaryEncoder;
use AhgMetadataExport\Services\Importers\MarcXmlImporter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarcEditorController extends Controller
{
    private MarcEditService $marcEdit;
    private LibraryService $libraryService;
    private MarcXmlImporter $importer;
    private Marc21BinaryEncoder $encoder;
    private Marc21DecoderService $decoder;

    public function __construct()
    {
        $this->marcEdit = new MarcEditService();
        $this->libraryService = new LibraryService(app()->getLocale());
        $this->importer = new MarcXmlImporter();
        $this->encoder = new Marc21BinaryEncoder();
        $this->decoder = new Marc21DecoderService();
    }

    public function index()
    {
        return view('ahg-library::marc-editor.index');
    }

    public function editRedirect(Request $request)
    {
        $data = $request->validate(['id' => 'required|integer|min:1']);
        return redirect()->route('library.marc-edit', ['id' => (int) $data['id']]);
    }

    public function import()
    {
        return view('ahg-library::marc-editor.import');
    }

    public function formImportPreview(Request $request)
    {
        $request->validate([
            'marc_file' => 'required|file|max:20480|mimes:xml,marcxml,text/xml',
        ]);

        $file = $request->file('marc_file');
        $raw = file_get_contents($file->getRealPath());

        try {
            $parsed = $this->marcEdit->parseMarcxml($raw);

            if (empty($parsed['data']) && empty($parsed['control'])) {
                return redirect()->back()
                    ->with('error', 'No MARC records found in the uploaded file.');
            }

            $previewData = $this->groupParsedFields($parsed);

            return view('ahg-library::marc-editor.import', [
                'preview_data' => $previewData,
                'record_count' => 1,
                'raw_marcxml' => $raw,
            ]);
        } catch (Throwable $e) {
            Log::error('MarcEditor formImportPreview error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to parse MARCXML: ' . $e->getMessage());
        }
    }

    public function formImportCommit(Request $request)
    {
        $request->validate([
            'marc_file' => 'required|file|max:20480|mimes:xml,marcxml,text/xml',
        ]);

        $file = $request->file('marc_file');
        $raw = file_get_contents($file->getRealPath());

        try {
            $records = $this->importer->parseRecords($raw);
            $created = 0;
            $skipped = 0;

            foreach ($records as $recordNode) {
                $desc = $this->importer->describeRecord($recordNode, withMatch: false);

                if (empty($desc['title'])) {
                    $skipped++;
                    continue;
                }

                $this->libraryService->create([
                    'title' => $desc['title'],
                    'scope_and_content' => $desc['scope_and_content'] ?? null,
                    'extent_and_medium' => $desc['extent_and_medium'] ?? null,
                    'identifier' => $desc['control_number'] ?? null,
                    'creators' => [],
                    'subjects' => [],
                ]);
                $created++;
            }

            $msg = "Import complete: {$created} record(s) created";
            if ($skipped > 0) {
                $msg .= ", {$skipped} skipped (no title).";
            }

            return redirect()->route('library.browse')
                ->with('success', $msg);
        } catch (Throwable $e) {
            Log::error('MarcEditor formImportCommit error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function importBinary()
    {
        return view('ahg-library::marc-editor.import-binary');
    }

    /**
     * Validate an uploaded MARCXML file and render the validation report.
     * Server-rendered companion to POST /api/cataloguing/marc/validate.
     */
    public function validateForm(Request $request)
    {
        $request->validate([
            'marc_file' => 'required|file|max:20480',
        ]);

        $raw = file_get_contents($request->file('marc_file')->getRealPath());
        $report = (new MarcValidationService())->validate($raw);

        return view('ahg-library::marc-editor.validation-report', [
            'report'  => $report,
            'marcxml' => $raw,
        ]);
    }

    /**
     * Diff an uploaded (edited) MARCXML record against the Heratio master and
     * render the conflict-review screen. Companion to
     * POST /api/cataloguing/marc/merge.
     */
    public function mergePreview(Request $request)
    {
        $request->validate([
            'marc_file' => 'required|file|max:20480',
        ]);

        $raw = file_get_contents($request->file('marc_file')->getRealPath());
        $report = (new MarcMergeService())->diff($raw, app()->getLocale());

        return view('ahg-library::marc-editor.conflict-review', [
            'report'  => $report,
            'marcxml' => $raw,
        ]);
    }

    public function formBinaryPreview(Request $request)
    {
        $request->validate([
            'marc_file' => 'required|file|max:20480',
        ]);

        $file = $request->file('marc_file');
        $raw = file_get_contents($file->getRealPath());

        try {
            $syntax = $this->decoder->detectSyntax($raw);
            if ($syntax === 'unknown') {
                return redirect()->back()
                    ->with('error', 'Unrecognised syntax. Provide a MARCXML or MARC21 binary file.');
            }

            if ($syntax === 'marcxml') {
                $parsed = $this->marcEdit->parseMarcxml($raw);
            } else {
                $parsed = $this->decoder->decode($raw);
            }

            $previewData = $this->groupParsedFields($parsed);

            return view('ahg-library::marc-editor.import-binary', [
                'preview_data' => $previewData,
                'raw_marc' => $raw,
            ]);
        } catch (Throwable $e) {
            Log::error('MarcEditor formBinaryPreview error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to decode MARC binary: ' . $e->getMessage());
        }
    }

    public function formBinaryCommit(Request $request)
    {
        $request->validate([
            'marc_file' => 'required|string',
        ]);

        try {
            $raw = base64_decode($request->input('marc_file'), true);
            if ($raw === false) {
                return redirect()->back()->with('error', 'Invalid base64-encoded MARC file.');
            }

            $ioId = $this->decoder->decodeToLibraryItem($raw);
            if (! $ioId) {
                return redirect()->back()->with('error', 'Failed to create library item from MARC record.');
            }

            $slug = DB::table('slug')->where('object_id', $ioId)->value('slug');

            return redirect()->route('library.show', $slug)
                ->with('success', 'MARC record created successfully.');
        } catch (Throwable $e) {
            Log::error('MarcEditor formBinaryCommit error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function edit(int $libraryItemId)
    {
        try {
            $formData = $this->marcEdit->buildEditFormData($libraryItemId);

            if (empty($formData['info_object_id'])) {
                abort(404, 'Library item not found.');
            }

            return view('ahg-library::marc-editor.edit', [
                'formData' => $formData,
                'libraryItemId' => $libraryItemId,
            ]);
        } catch (Throwable $e) {
            Log::error('MarcEditor edit error: ' . $e->getMessage());
            abort(500, 'Failed to load MARC record: ' . $e->getMessage());
        }
    }

    public function update(Request $request, int $libraryItemId)
    {
        $request->validate([
            'info_object_id' => 'required|integer|exists:information_object,id',
        ]);

        $formData = $request->except(['_token', '_method']);

        $ok = $this->marcEdit->applyEdits($libraryItemId, $formData);

        if (! $ok) {
            return redirect()->back()->with('error', 'Failed to save MARC edits.');
        }

        $ioId = DB::table('library_item')->where('id', $libraryItemId)->value('information_object_id');
        $slug = DB::table('slug')->where('object_id', $ioId)->value('slug');

        return redirect()->route('library.show', $slug)
            ->with('success', 'MARC record updated successfully.');
    }

    public function download(int $libraryItemId)
    {
        $marcxml = $this->marcEdit->exportLibraryItem($libraryItemId);
        if ($marcxml === '') {
            abort(404, 'No MARC record found for this library item.');
        }

        $ioId = DB::table('library_item')->where('id', $libraryItemId)->value('information_object_id');
        $title = DB::table('information_object_i18n')->where('id', $ioId)->value('title') ?? 'record';
        $safeTitle = preg_replace('/[^a-z0-9\-]/i', '_', (string) $title);

        return response($marcxml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $safeTitle . '.xml"',
        ]);
    }

    public function downloadBinary(int $libraryItemId)
    {
        $marcxml = $this->marcEdit->exportLibraryItem($libraryItemId);
        if ($marcxml === '') {
            abort(404, 'No MARC record found for this library item.');
        }

        try {
            $binary = $this->encoder->encodeFromMarcxml($marcxml);
        } catch (Throwable $e) {
            Log::error('MarcEditor downloadBinary encode error: ' . $e->getMessage());
            abort(500, 'Failed to encode MARC binary.');
        }

        $ioId = DB::table('library_item')->where('id', $libraryItemId)->value('information_object_id');
        $title = DB::table('information_object_i18n')->where('id', $ioId)->value('title') ?? 'record';
        $safeTitle = preg_replace('/[^a-z0-9\-]/i', '_', (string) $title);

        return response($binary, 200, [
            'Content-Type' => 'application/marc',
            'Content-Disposition' => 'attachment; filename="' . $safeTitle . '.mrc"',
        ]);
    }

    private function groupParsedFields(array $parsed): array
    {
        $control = $parsed['control'] ?? [];
        $data = $parsed['data'] ?? [];
        $sections = [];

        if (! empty($parsed['leader'])) {
            $sections['leader'] = [
                'label' => 'Leader',
                'fields' => [['tag' => '000', 'value' => $parsed['leader']]],
            ];
        }

        if (! empty($control)) {
            $cfList = [];
            foreach ($control as $tag => $value) {
                $cfList[] = ['tag' => $tag, 'value' => $value];
            }
            $sections['control_fields'] = ['label' => 'Control Fields (00X)', 'fields' => $cfList];
        }

        $titleFields = array_values(array_filter($data, fn($f) => ($f['tag'] ?? '') === '245'));
        if ($titleFields) {
            $sections['title_statement'] = ['label' => 'Title Statement (245)', 'fields' => $titleFields];
        }

        $mainEntryFields = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['100', '110', '111'])));
        if ($mainEntryFields) {
            $sections['main_entry'] = ['label' => 'Main Entry (1XX)', 'fields' => $mainEntryFields];
        }

        $addedFields = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['700', '710', '711'])));
        if ($addedFields) {
            $sections['added_entries'] = ['label' => 'Added Entries (7XX)', 'fields' => $addedFields];
        }

        $pubFields = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['260', '264'])));
        if ($pubFields) {
            $sections['publication_info'] = ['label' => 'Publication (260/264)', 'fields' => $pubFields];
        }

        $physFields = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['300', '007'])));
        if ($physFields) {
            $sections['physical_description'] = ['label' => 'Physical Description (300/007)', 'fields' => $physFields];
        }

        $subjFields = array_values(array_filter($data, fn($f) => preg_match('/^6\d{2}$/', $f['tag'] ?? '')));
        if ($subjFields) {
            $sections['subject_access'] = ['label' => 'Subject Access (6XX)', 'fields' => $subjFields];
        }

        $noteFields = array_values(array_filter($data, fn($f) => preg_match('/^5\d{2}$/', $f['tag'] ?? '')));
        if ($noteFields) {
            $sections['notes'] = ['label' => 'Notes (5XX)', 'fields' => $noteFields];
        }

        $eaFields = array_values(array_filter($data, fn($f) => ($f['tag'] ?? '') === '856'));
        if ($eaFields) {
            $sections['electronic_access'] = ['label' => 'Electronic Access (856)', 'fields' => $eaFields];
        }

        $rdaFields = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['336', '337', '338'])));
        if ($rdaFields) {
            $sections['rda_fields'] = ['label' => 'RDA Fields (336/337/338)', 'fields' => $rdaFields];
        }

        $idFields = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['020', '022', '024', '028'])));
        if ($idFields) {
            $sections['identifier_fields'] = ['label' => 'Standard Identifiers (020/022/024/028)', 'fields' => $idFields];
        }

        return $sections;
    }
}
