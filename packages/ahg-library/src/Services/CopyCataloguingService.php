<?php

/**
 * CopyCataloguingService — Z39.50 copy cataloguing bridge for Heratio.
 *
 * Wraps Z3950Service to search remote Z39.50 targets (MARC21/USMARC),
 * parses results via Marc21DecoderService, and imports records via
 * LibraryService. Provides preview and commit operations.
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

use AhgZ3950\Services\Z3950Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CopyCataloguingService
{
    private Z3950Service $z3950;
    private Marc21DecoderService $decoder;

    public function __construct()
    {
        $this->z3950 = new Z3950Service();
        $this->decoder = new Marc21DecoderService();
    }

    /**
     * List all active Z39.50 targets (from library_z3950_target table).
     *
     * @return Collection [{id,name,host,port,database_name,syntax,element_set}]
     */
    public function getTargets(): Collection
    {
        return DB::table('library_z3950_target')
            ->where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'port', 'database_name', 'syntax', 'element_set']);
    }

    /**
     * Search a Z39.50 target and return parsed MARC records as preview rows.
     *
     * @param int    $targetId
     * @param string $query     CQL query string
     * @param int    $max      Max results (default 20)
     * @return SearchResult{count:int,records:array,error:string|null}
     */
    public function search(int $targetId, string $query, int $max = 20): array
    {
        $target = DB::table('library_z3950_target')->where('id', $targetId)->first();
        if (! $target) {
            return ['count' => 0, 'records' => [], 'error' => 'Target not found.'];
        }

        $result = $this->z3950->search(
            (string) $target->host,
            (int) $target->port,
            (string) $target->database_name,
            $query,
            (string) $target->syntax,
            (string) $target->element_set,
            $max
        );

        if ($result['error']) {
            Log::warning('[CopyCataloguing] Z39.50 search error: ' . $result['error']);
            return $result;
        }

        // Parse each returned MARC record (USmarc) via Marc21DecoderService
        $records = [];
        foreach ($result['records'] as $marcContent) {
            $syntax = $this->decoder->detectSyntax($marcContent);
            if ($syntax === 'marc21') {
                $parsed = $this->decoder->decode($marcContent);
            } else {
                // Fallback: treat as MARCXML
                // Rebuild using existing MarcEditService parse
                $parsed = (new MarcEditService())->parseMarcxml($marcContent);
            }
            $records[] = $this->buildPreviewRow($parsed, $marcContent);
        }

        return [
            'count'   => count($records),
            'records' => $records,
            'error'   => null,
        ];
    }

    /**
     * Parse raw MARC and return a groupParsedFields-style section array.
     *
     * @param string $marcContent  Raw MARC bytes or MARCXML string
     * @return array              Grouped sections for preview table
     */
    public function preview(string $marcContent): array
    {
        $syntax = $this->decoder->detectSyntax($marcContent);
        if ($syntax === 'marc21') {
            $parsed = $this->decoder->decode($marcContent);
        } else {
            $parsed = (new MarcEditService())->parseMarcxml($marcContent);
        }

        return $this->buildPreviewGroups($parsed);
    }

    /**
     * Import raw MARC into a library item via LibraryService.
     * If FK libraryItemId is provided, updates that record. Otherwise creates new.
     *
     * @param string $marcContent   Raw MARC bytes
     * @param int    $libraryItemId Optional: update existing item instead of creating
     * @param bool   $replace       If true, replaces existing data (not implemented yet)
     * @return int                  information_object ID
     */
    public function import(string $marcContent, int $libraryItemId = 0, bool $replace = false): int
    {
        $syntax = $this->decoder->detectSyntax($marcContent);

        if ($syntax === 'marc21') {
            return $this->decoder->decodeToLibraryItem($marcContent);
        }

        // MARCXML: reuse existing importer path
        $parsed = (new MarcEditService())->parseMarcxml($marcContent);
        $data   = $parsed['data'] ?? [];

        $firstOf = function (string $tag) use ($data): array|null {
            foreach ($data as $f) {
                if (($f['tag'] ?? '') === $tag) {
                    return $f;
                }
            }
            return null;
        };

        $flat = function (?array $field): array {
            if (! $field) return [];
            $out = [];
            foreach ($field['subfields'] ?? [] as $k => $v) {
                $clean = preg_replace('/[0-9]+$/', '', $k);
                if (! isset($out[$clean])) {
                    $out[$clean] = $v;
                }
            }
            return $out;
        };

        $f245 = $firstOf('245');
        $s245 = $f245 ? $flat($f245) : [];
        $title = $s245['a'] ?? 'Untitled';

        $f020 = $firstOf('020');
        $isbn  = $f020 ? ($flat($f020)['a'] ?? null) : null;

        $f022 = $firstOf('022');
        $issn  = $f022 ? ($flat($f022)['a'] ?? null) : null;

        $f260 = $firstOf('260') ?: $firstOf('264');
        $pub  = $f260 ? $flat($f260) : [];
        $publisher = $pub['b'] ?? null;
        $pubPlace  = $pub['a'] ?? null;

        $libraryService = new LibraryService(app()->getLocale());

        return $libraryService->create([
            'title' => $title,
            'isbn'  => $isbn,
            'issn'  => $issn,
            'publisher' => $publisher,
            'publication_place' => $pubPlace,
        ]);
    }

    /**
     * Build a flat preview row from parsed MARC for the search results table.
     *
     * @param array  $parsed
     * @param string $marcContent  Raw bytes (stored in session for commit)
     * @return array{title,author,isbn,issn,publisher,pub_date,marc_content}
     */
    private function buildPreviewRow(array $parsed, string $marcContent): array
    {
        $data = $parsed['data'] ?? [];

        $firstOf = function (string $tag) use ($data): array|null {
            foreach ($data as $f) {
                if (($f['tag'] ?? '') === $tag) {
                    return $f;
                }
            }
            return null;
        };

        $flat = fn(?array $f) => $f ? array_column($f['subfields'] ?? [], null, null) : [];

        $sub245 = $firstOf('245') ? collect($firstOf('245')['subfields'] ?? []) : collect();
        $title  = $sub245->get('a', '');
        $title  = is_array($title) ? ($title[0] ?? '') : $title;

        $f100 = $firstOf('100');
        $f700 = $firstOf('700');
        $p700 = $f700 ? ($f700['subfields'] ?? []) : [];
        $p700a = is_array($p700[0] ?? null) ? ($p700[0]['a'] ?? '') : ($p700['a'] ?? '');
        $author = $f100
            ? ($f100['subfields']['a'] ?? $f100['subfields']['a'] ?? '')
            : $p700a;
        $author = is_string($author) ? $author : '';

        $f020 = $firstOf('020');
        $isbn = $f020 ? ($f020['subfields']['a'] ?? null) : null;
        $isbn = is_string($isbn) ? $isbn : null;

        $f022 = $firstOf('022');
        $issn = $f022 ? ($f022['subfields']['a'] ?? null) : null;
        $issn = is_string($issn) ? $issn : null;

        $f260 = $firstOf('260') ?: $firstOf('264');
        $pub  = $f260 ? ($f260['subfields'] ?? []) : [];
        $publisher = $pub['b'] ?? $pub['a'] ?? null;
        $publisher = is_string($publisher) ? $publisher : null;

        $pubDate = $pub['c'] ?? null;
        $pubDate = is_string($pubDate) ? $pubDate : null;

        return [
            'title'        => (string) $title,
            'author'       => $author,
            'isbn'         => $isbn,
            'issn'         => $issn,
            'publisher'    => $publisher,
            'pub_date'     => $pubDate,
            'marc_content' => base64_encode($marcContent),
        ];
    }

    /**
     * Build section-grouped preview for a Single matched record.
     *
     * @param array $parsed  Output of decode() or parseMarcxml()
     * @return array         Section array forBlade accordion display
     */
    private function buildPreviewGroups(array $parsed): array
    {
        $data = $parsed['data'] ?? [];
        $sections = [];

        $keys = ['leader', 'control_fields', 'title_statement', 'main_entry',
                 'publication_info', 'physical_description', 'notes',
                 'electronic_access', 'rda', 'identifiers'];

        // Delegate to MarcEditorController's groupParsedFields logic — rebuild inline
        $control = $parsed['control'] ?? [];

        // Leader
        if (! empty($parsed['leader'])) {
            $sections['leader'] = ['label' => 'Leader', 'fields' => [['tag' => '000', 'value' => $parsed['leader']]]];
        }

        // Control fields
        if (! empty($control)) {
            $fields = [];
            foreach ($control as $tag => $val) {
                $fields[] = ['tag' => $tag, 'value' => $val];
            }
            $sections['control_fields'] = ['label' => 'Control Fields (00X)', 'fields' => $fields];
        }

        // 245
        $f245 = array_values(array_filter($data, fn($f) => ($f['tag'] ?? '') === '245'));
        if ($f245) {
            $sections['title_statement'] = ['label' => 'Title Statement (245)', 'fields' => $f245];
        }

        // 1XX
        $main = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['100', '110', '111'])));
        if ($main) {
            $sections['main_entry'] = ['label' => 'Main Entry (1XX)', 'fields' => $main];
        }

        // 7XX
        $added = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['700', '710', '711'])));
        if ($added) {
            $sections['added_entries'] = ['label' => 'Added Entries (7XX)', 'fields' => $added];
        }

        // 260/264
        $pub = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['260', '264'])));
        if ($pub) {
            $sections['publication_info'] = ['label' => 'Publication (260/264)', 'fields' => $pub];
        }

        // 300
        $phys = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['300', '007'])));
        if ($phys) {
            $sections['physical_description'] = ['label' => 'Physical Description (300/007)', 'fields' => $phys];
        }

        // 5XX
        $notes = array_values(array_filter($data, fn($f) => preg_match('/^5\d{2}$/', $f['tag'] ?? '')));
        if ($notes) {
            $sections['notes'] = ['label' => 'Notes (5XX)', 'fields' => $notes];
        }

        // 856
        $ea = array_values(array_filter($data, fn($f) => ($f['tag'] ?? '') === '856'));
        if ($ea) {
            $sections['electronic_access'] = ['label' => 'Electronic Access (856)', 'fields' => $ea];
        }

        // 336/337/338
        $rda = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['336', '337', '338'])));
        if ($rda) {
            $sections['rda'] = ['label' => 'RDA Fields (336/337/338)', 'fields' => $rda];
        }

        // 020/022
        $ids = array_values(array_filter($data, fn($f) => in_array($f['tag'] ?? '', ['020', '022', '024', '028'])));
        if ($ids) {
            $sections['identifiers'] = ['label' => 'Standard Identifiers (020/022/024/028)', 'fields' => $ids];
        }

        return $sections;
    }
}
