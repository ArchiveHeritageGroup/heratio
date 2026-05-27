<?php

/**
 * MarcEditService - in-place MARC field editor for library records.
 *
 * Wraps the export (MarcxmlSerializer) and import (MarcXmlImporter) services
 * from ahg-metadata-export so the library package can:
 *   1. Export any library_item to MARCXML.
 *   2. Parse MARCXML into a flat array for Blade forms.
 *   3. Group parsed fields into MARC sections for the edit UI.
 *   4. Apply form-field edits back to the library_item and information_object
 *      columns without a full MARCXML round-trip.
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

namespace AhgLibrary\Services;

use AhgMetadataExport\Services\Exporters\MarcxmlSerializer;
use AhgMetadataExport\Services\Exporters\Marc21BinaryEncoder;
use AhgMetadataExport\Services\Importers\MarcXmlImporter;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarcEditService
{
    private MarcxmlSerializer $serializer;
    private MarcXmlImporter $importer;
    private Marc21BinaryEncoder $encoder;

    public function __construct()
    {
        $this->serializer = new MarcxmlSerializer();
        $this->importer = new MarcXmlImporter();
        $this->encoder = new Marc21BinaryEncoder();
    }

    /**
     * Export a library item to MARCXML string.
     *
     * @param int $libraryItemId  Primary key of the library_item row.
     * @param string $culture    Culture code for i18n lookups (default 'en').
     * @return string            MARCXML record string, or empty string on error.
     */
    public function exportLibraryItem(int $libraryItemId, string $culture = 'en'): string
    {
        try {
            $ioId = DB::table('library_item')
                ->where('id', $libraryItemId)
                ->value('information_object_id');

            if (! $ioId) {
                return '';
            }

            return $this->serializer->serializeRecord((int) $ioId, $culture);
        } catch (Throwable $e) {
            Log::error('MarcEditService::exportLibraryItem error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Parse a MARCXML string into a flat structured array.
     *
     * @param string $marcxml  Raw MARCXML document or <record> element string.
     * @return array           [
     *                            'leader' => string (24 chars),
     *                            'control' => ['001' => text, '003' => text, ...],
     *                            'data' => [
     *                              ['tag' => '245', 'ind1' => '0', 'ind2' => '1',
     *                               'subfields' => ['a' => 'Title Text', 'b' => 'Remainder', ...]],
     *                              ...
     *                            ]
     *                          ]
     */
    public function parseMarcxml(string $marcxml): array
    {
        $dom = new DOMDocument();
        if (! @$dom->loadXML($marcxml)) {
            return ['leader' => '', 'control' => [], 'data' => []];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        // Leader
        $leaderNodes = $xpath->query('//marc:leader');
        $leader = $leaderNodes && $leaderNodes->length > 0
            ? trim($leaderNodes->item(0)->textContent)
            : '';

        // Control fields (001, 003, 005, 006, 007, 008, ...)
        $control = [];
        $cfNodes = $xpath->query('//marc:controlfield');
        if ($cfNodes !== false) {
            foreach ($cfNodes as $node) {
                $tag = $node->getAttribute('tag');
                if ($tag !== '') {
                    $control[$tag] = trim($node->textContent);
                }
            }
        }

        // Data fields
        $data = [];
        $dfNodes = $xpath->query('//marc:datafield');
        if ($dfNodes !== false) {
            foreach ($dfNodes as $node) {
                $tag = $node->getAttribute('tag');
                $ind1 = $node->getAttribute('ind1');
                $ind2 = $node->getAttribute('ind2');

                $subfields = [];
                $sfNodes = $xpath->query('marc:subfield', $node);
                if ($sfNodes !== false) {
                    foreach ($sfNodes as $sf) {
                        $code = $sf->getAttribute('code');
                        $value = trim($sf->textContent);
                        if ($code !== '' && $value !== '') {
                            // Handle repeatable subfields with sequential keys: a, a2, a3...
                            if (isset($subfields[$code])) {
                                $seq = 2;
                                while (isset($subfields[$code . $seq])) {
                                    $seq++;
                                }
                                $subfields[$code . $seq] = $value;
                            } else {
                                $subfields[$code] = $value;
                            }
                        }
                    }
                }

                $data[] = [
                    'tag' => $tag,
                    'ind1' => $ind1,
                    'ind2' => $ind2,
                    'subfields' => $subfields,
                ];
            }
        }

        return [
            'leader' => $leader,
            'control' => $control,
            'data' => $data,
        ];
    }

    /**
     * Group parsed MARC fields into sections for the edit form UI.
     *
     * @param int $libraryItemId  Primary key of the library_item row.
     * @return array              Grouped array ready for Blade iteration:
     *                              'info_object_id' => int,
     *                              'library_item_id' => int,
     *                              'leader' => string,
     *                              'control_fields' => ['001' => val, ...],
     *                              'title_statement' => [...],
     *                              'author_entry' => [...],
     *                              'publication_info' => [...],
     *                              'physical_description' => [...],
     *                              'subject_access' => [...],
     *                              'series_info' => [...],
     *                              'notes' => [...],
     *                              'electronic_access' => [...],
     *                              'raw_data' => parsed array
     */
    public function buildEditFormData(int $libraryItemId): array
    {
        $marcxml = $this->exportLibraryItem($libraryItemId);
        $parsed = $this->parseMarcxml($marcxml);

        $ioId = DB::table('library_item')
            ->where('id', $libraryItemId)
            ->value('information_object_id');

        $control = $parsed['control'] ?? [];
        $data = $parsed['data'] ?? [];

        // Helper: pull first datafield matching tag
        $firstOf = function (string $tag) use ($data): ?array {
            foreach ($data as $f) {
                if ($f['tag'] === $tag) {
                    return $f;
                }
            }
            return null;
        };

        // Helper: pull all datafields matching tag
        $allOf = function (string $tag) use ($data): array {
            return array_values(array_filter($data, fn($f) => $f['tag'] === $tag));
        };

        // Helper: flatten subfields for a field to key->value (first occurrence)
        $flatSubfields = function (?array $field): array {
            if (! $field) return [];
            $out = [];
            foreach ($field['subfields'] ?? [] as $k => $v) {
                // Strip numeric suffix on repeat keys so form field names stay stable
                $clean = preg_replace('/[0-9]+$/', '', $k);
                if (! isset($out[$clean])) {
                    $out[$clean] = $v;
                }
            }
            return $out;
        };

        // Title statement: 245, 240, 246
        $titleStatement = [];
        $f245 = $firstOf('245');
        if ($f245) {
            $titleStatement['245'] = $flatSubfields($f245);
            $titleStatement['245']['_ind1'] = $f245['ind1'];
            $titleStatement['245']['_ind2'] = $f245['ind2'];
        }
        $f240 = $firstOf('240');
        if ($f240) {
            $titleStatement['240'] = $flatSubfields($f240);
        }
        foreach ($allOf('246') as $f) {
            $titleStatement['246'][] = array_merge(['_ind1' => $f['ind1'], '_ind2' => $f['ind2']], $flatSubfields($f));
        }

        // Author entry: 100, 110, 111, 700, 710, 711
        $authorEntry = [];
        foreach ([100, 110, 111, 700, 710, 711] as $tag) {
            foreach ($allOf((string) $tag) as $f) {
                $authorEntry[] = array_merge(
                    ['tag' => (string) $tag, '_ind1' => $f['ind1'], '_ind2' => $f['ind2']],
                    $flatSubfields($f)
                );
            }
        }

        // Publication info: 264 (and 008 date chars pos 07-10)
        $pubInfo = [];
        $f264 = $firstOf('264');
        if ($f264) {
            $pubInfo['264'] = $flatSubfields($f264);
            $pubInfo['264']['_ind1'] = $f264['ind1'];
            $pubInfo['264']['_ind2'] = $f264['ind2'];
        }
        // Extract 008 date from control fields
        if (isset($control['008']) && strlen($control['008']) >= 11) {
            $pubInfo['date_1'] = substr($control['008'], 7, 4);
        }

        // Physical description: 300, 007
        $physDesc = [];
        $f300 = $firstOf('300');
        if ($f300) {
            $physDesc['300'] = $flatSubfields($f300);
        }
        if (isset($control['007'])) {
            $physDesc['007'] = $control['007'];
        }

        // Subject access: 6XX tags
        $subjectAccess = [];
        foreach ($data as $f) {
            if (in_array($f['tag'][0], ['6'])) {
                $subjectAccess[] = array_merge(
                    ['tag' => $f['tag'], '_ind1' => $f['ind1'], '_ind2' => $f['ind2']],
                    $flatSubfields($f)
                );
            }
        }

        // Series info: 4XX tags
        $seriesInfo = [];
        foreach ($data as $f) {
            if (in_array($f['tag'][0], ['4'])) {
                $seriesInfo[] = array_merge(
                    ['tag' => $f['tag'], '_ind1' => $f['ind1'], '_ind2' => $f['ind2']],
                    $flatSubfields($f)
                );
            }
        }

        // Notes: 5XX tags
        $notes = [];
        foreach ($data as $f) {
            if (in_array($f['tag'][0], ['5'])) {
                $notes[] = array_merge(
                    ['tag' => $f['tag'], '_ind1' => $f['ind1'], '_ind2' => $f['ind2']],
                    $flatSubfields($f)
                );
            }
        }

        // Electronic access: 856
        $electronicAccess = [];
        foreach ($allOf('856') as $f) {
            $electronicAccess[] = array_merge(
                ['_ind1' => $f['ind1'], '_ind2' => $f['ind2']],
                $flatSubfields($f)
            );
        }

        return [
            'info_object_id' => (int) $ioId,
            'library_item_id' => $libraryItemId,
            'leader' => $parsed['leader'],
            'control_fields' => $control,
            'title_statement' => $titleStatement,
            'author_entry' => $authorEntry,
            'publication_info' => $pubInfo,
            'physical_description' => $physDesc,
            'subject_access' => $subjectAccess,
            'series_info' => $seriesInfo,
            'notes' => $notes,
            'electronic_access' => $electronicAccess,
            'raw_data' => $parsed,
        ];
    }

    /**
     * Apply form edits from the MARC editor Blade form back to the
     * information_object, information_object_i18n, and library_item columns.
     *
     * Only columns that map directly from the form are updated.
     * No MARCXML round-trip is performed.
     *
     * @param int $libraryItemId   Primary key of the library_item row.
     * @param array $formData      Form data from the Blade view (section keyed).
     * @return bool                True on success, false on error.
     */
    public function applyEdits(int $libraryItemId, array $formData): bool
    {
        try {
            $ioId = DB::table('library_item')
                ->where('id', $libraryItemId)
                ->value('information_object_id');

            if (! $ioId) {
                return false;
            }

            $now = date('Y-m-d H:i:s');

            // Update information_object_i18n: title from title_statement 245$a
            $title = $formData['title_statement']['245']['a'] ?? null;
            if ($title !== null) {
                DB::table('information_object_i18n')
                    ->updateOrInsert(
                        ['id' => $ioId, 'culture' => app()->getLocale()],
                        [
                            'title' => $title,
                            'scope_and_content' => $formData['notes']['520']['a'] ?? null,
                        ]
                    );
            }

            // Update library_item columns from form data
            $libraryUpdates = [];

            $isbnMap = [
                'isbn_10' => 'isbn',
                'isbn_13' => 'isbn',
            ];
            $directMap = [
                'isbn' => 'isbn',
                'issn' => 'issn',
                'publisher' => 'publisher',
                'publication_date' => 'publication_date',
                'pagination' => 'pagination',
                'physical_details' => 'physical_details',
                'edition' => 'edition',
                'edition_statement' => 'edition_statement',
                'barcode' => 'barcode',
                'shelf_location' => 'shelf_location',
                'call_number' => 'call_number',
                'classification_scheme' => 'classification_scheme',
            ];

            // Publication info → library_item
            $pub = $formData['publication_info'] ?? [];
            if (! empty($pub['264']['a'])) {
                $libraryUpdates['publication_place'] = $pub['264']['a'];
            }
            if (! empty($pub['264']['b'])) {
                $libraryUpdates['publisher'] = $pub['264']['b'];
            }
            if (! empty($pub['date_1'])) {
                $libraryUpdates['publication_date'] = $pub['date_1'];
            }

            // Physical description → library_item
            $phys = $formData['physical_description'] ?? [];
            if (! empty($phys['300']['a'])) {
                $libraryUpdates['pagination'] = $phys['300']['a'];
            }

            // Notes → library_item summary / general_note
            $notesData = $formData['notes'] ?? [];
            foreach ($notesData as $note) {
                if (($note['tag'] ?? '') === '500' && ! empty($note['a'])) {
                    $libraryUpdates['general_note'] = trim(($libraryUpdates['general_note'] ?? '') . ' ' . $note['a']);
                }
            }

            if (! empty($libraryUpdates)) {
                $libraryUpdates['updated_at'] = $now;
                DB::table('library_item')
                    ->where('id', $libraryItemId)
                    ->update($libraryUpdates);
            }

            // Touch object updated_at
            DB::table('object')
                ->where('id', $ioId)
                ->update(['updated_at' => $now]);

            return true;
        } catch (Throwable $e) {
            Log::error('MarcEditService::applyEdits error: ' . $e->getMessage());
            return false;
        }
    }
}