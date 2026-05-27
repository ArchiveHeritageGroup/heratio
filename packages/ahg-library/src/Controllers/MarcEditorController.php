<?php

/**
 * MarcEditorController - MARC editor for library records.
 *
 * Handles:
 *   - Index (landing page)
 *   - Import (batch MARCXML upload)
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

    public function __construct()
    {
        $this->marcEdit = new MarcEditService();
        $this->libraryService = new LibraryService(app()->getLocale());
        $this->importer = new MarcXmlImporter();
        $this->encoder = new Marc21BinaryEncoder();
    }

    /**
     * Landing page for the MARC editor module.
     */
    public function index()
    {
        return view('ahg-library::marc-editor.index');
    }

    /**
     * Batch import page.
     */
    public function import()
    {
        return view('ahg-library::marc-editor.import');
    }

    /**
     * Preview a MARCXML file before committing the import.
     * Validates the file upload, parses the first record, and returns
     * a preview table showing extracted fields grouped by MARC section.
     */
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

            // Group for display
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

    /**
     * Commit a MARCXML file import, creating library items via LibraryService.
     */
    public function formImportCommit(Request $request)
    {
        $request->validate([
            'marc_file' => 'required|file|max:20480|mimes:xml,marcxml,text/xml',
        ]);

        $file = $request->file('marc_file');
        $raw = file_get_contents($file->getRealPath());

        try {
            $culture = app()->getLocale();
            $records = $this->importer->parseRecords($raw);
            $created = 0;
            $skipped = 0;

            foreach ($records as $recordNode) {
                $desc = $this->importer->describeRecord($recordNode, withMatch: false);

                if (empty($desc['title'])) {
                    $skipped++;
                    continue;
                }

                // Create library item via LibraryService
                $ioId = $this->libraryService->create([
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

    /**
     * Open the MARC editor for a specific library item.
     */
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

    /**
     * Save edits made in the MARC editor form.
     */
    public function update(Request $request, int $libraryItemId)
    {
        $validated = $request->validate([
            'info_object_id' => 'required|integer|exists:information_object,id',
        ]);

        $formData = $request->except(['_token', '_method']);

        $ok = $this->marcEdit->applyEdits($libraryItemId, $formData);

        if (! $ok) {
            return redirect()->back()
                ->with('error', 'Failed to save MARC edits.');
        }

        // Redirect to the library item detail page
        $ioId = DB::table('library_item')->where('id', $libraryItemId)->value('information_object_id');
        $slug = DB::table('slug')->where('object_id', $ioId)->value('slug');

        return redirect()->route('library.show', $slug)
            ->with('success', 'MARC record updated successfully.');
    }

    /**
     * Download a library item as MARCXML.
     */
    public function download(int $libraryItemId)
    {
        $marcxml = $this->marcEdit->exportLibraryItem($libraryItemId);

        if ($marcxml === '') {
            abort(404, 'No MARC record found for this library item.');
        }

        $ioId = DB::table('library_item')
            ->where('id', $libraryItemId)
            ->value('information_object_id');
        $title = DB::table('information_object_i18n')
            ->where('id', $ioId)
            ->value('title') ?? 'record';

        $safeTitle = preg_replace('/[^a-z0-9\-]/i', '_', (string) $title);
        $filename = 'MARCXML_' . $safeTitle . '.xml';

        return response($marcxml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Download a library item as MARC21 binary.
     */
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
            abort(500, 'Failed to encode MARC binary: ' . $e->getMessage());
        }

        $ioId = DB::table('library_item')
            ->where('id', $libraryItemId)
            ->value('information_object_id');
        $title = DB::table('information_object_i18n')
            ->where('id', $ioId)
            ->value('title') ?? 'record';

        $safeTitle = preg_replace('/[^a-z0-9\-]/i', '_', (string) $title);
        $filename = 'MARC21_' . $safeTitle . '.mrc';

        return response($binary, 200, [
            'Content-Type' => 'application/marc',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Group parsed MARC fields into display sections for the import preview.
     *
     * @param array $parsed  Output of parseMarcxml().
     * @return array         Grouped array of sections with human-readable labels.
     */
    private function groupParsedFields(array $parsed): array
    {
        $control = $parsed['control'] ?? [];
        $data = $parsed['data'] ?? [];

        $sections = [];

        // Leader
        if (! empty($parsed['leader'])) {
            $sections['leader'] = [
                'label' => 'Leader',
                'fields' => [['tag' => '000', 'value' => $parsed['leader']]],
            ];
        }

        // Control fields
        if (! empty($control)) {
            $cfList = [];
            foreach ($control as $tag => $value) {
                $cfList[] = ['tag' => $tag, 'value' => $value];
            }
            $sections['control_fields'] = [
                'label' => 'Control Fields (00X)',
                'fields' => $cfList,
            ];
        }

        // Title statement
        $titleFields = array_values(array_filter($data, fn($f) => $f['tag'] === '245'));
        if ($titleFields) {
            $sections['title_statement'] = [
                'label' => 'Title Statement (245)',
                'fields' => $titleFields,
            ];
        }

        // Main entry
        $mainEntryFields = array_values(array_filter($data, fn($f) => in_array($f['tag'], ['100', '110', '111'])));
        if ($mainEntryFields) {
            $sections['main_entry'] = [
                'label' => 'Main Entry (1XX)',
                'fields' => $mainEntryFields,
            ];
        }

        // Added entries
        $addedFields = array_values(array_filter($data, fn($f) => in_array($f['tag'], ['700', '710', '711'])));
        if ($addedFields) {
            $sections['added_entries'] = [
                'label' => 'Added Entries (7XX)',
                'fields' => $addedFields,
            ];
        }

        // Publication info
        $pubFields = array_values(array_filter($data, fn($f) => in_array($f['tag'], ['260', '264'])));
        if ($pubFields) {
            $sections['publication_info'] = [
                'label' => 'Publication (260/264)',
                'fields' => $pubFields,
            ];
        }

        // Physical description
        $physFields = array_values(array_filter($data, fn($f) => in_array($f['tag'], ['300', '007'])));
        if ($physFields) {
            $sections['physical_description'] = [
                'label' => 'Physical Description (300/007)',
                'fields' => $physFields,
            ];
        }

        // Subject access
        $subjFields = array_values(array_filter($data, fn($f) => preg_match('/^6\d{2}$/', $f['tag'])));
        if ($subjFields) {
            $sections['subject_access'] = [
                'label' => 'Subject Access (6XX)',
                'fields' => $subjFields,
            ];
        }

        // Notes
        $noteFields = array_values(array_filter($data, fn($f) => preg_match('/^5\d{2}$/', $f['tag'])));
        if ($noteFields) {
            $sections['notes'] = [
                'label' => 'Notes (5XX)',
                'fields' => $noteFields,
            ];
        }

        // Electronic access
        $eaFields = array_values(array_filter($data, fn($f) => $f['tag'] === '856'));
        if ($eaFields) {
            $sections['electronic_access'] = [
                'label' => 'Electronic Access (856)',
                'fields' => $eaFields,
            ];
        }

        // All remaining data fields
        $remainingTags = array_diff(
            array_unique(array_column($data, 'tag')),
            ['245', '100', '110', '111', '700', '710', '711', '260', '264', '300', '007', '856']
        );
        // Also exclude anything matching 5XX, 6XX (already captured)
        $remainingTags = array_filter($remainingTags, fn($t) => !preg_match('/^[56]\d{2}$/', $t));

        if (! empty($remainingTags)) {
            $otherFields = array_values(array_filter($data, fn($f) => in_array($f['tag'], $remainingTags)));
            $sections['other'] = [
                'label' => 'Other Fields',
                'fields' => $otherFields,
            ];
        }

        return $sections;
    }
}