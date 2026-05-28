<?php

/**
 * Marc21DecoderService — ISO 2709 | MARC21 binary decoder for Heratio.
 *
 * Provides:
 *   - Syntax detection (binary MARC vs MARCXML)
 *   - Full ISO 2709 parse into the same array shape as MarcEditService::parseMarcxml()
 *   - Direct creation of a library_item from a binary MARC record
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

use App\Http\Controllers\Controller;
use AhgLibrary\Services\LibraryService;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class Marc21DecoderService
{
    /**
     * Detect the MARC syntax of raw bytes and return a string label.
     *
     * @param string $raw  Raw bytes or text.
     * @return string      'marcxml' | 'marc21' | 'unknown'
     */
    public function detectSyntax(string $raw): string
    {
        if (str_contains($raw, '<?xml') || str_contains($raw, '<record')) {
            return 'marcxml';
        }
        // MARC21 binary: byte 20 (position 9 from 0) is the record status
        // and byte 5 is always 'n' in UTF-8 records. A safer marker is the
        // field terminator (0x1D) within the first 30 bytes.
        if (isset($raw[9]) && ord($raw[9]) === 0x1D) {
            return 'marc21';
        }
        // Fallback: if the 24-byte leader looks like a valid MARC leader
        // (base address is in bytes 12-16 as digits), treat as binary.
        if (strlen($raw) >= 24 && ctype_digit(substr($raw, 12, 5))) {
            return 'marc21';
        }
        return 'unknown';
    }

    /**
     * Decode a binary MARC21 record from raw ISO 2709 bytes.
     *
     * Returns the same array shape as MarcEditService::parse_marcxml():
     *   [
     *     'leader'    => string (24 chars),
     *     'control'   => ['001' => text, '003' => text, ...],
     *     'data'      => [
     *       ['tag' => '245', 'ind1' => '0', 'ind2' => '1',
     *        'subfields' => ['a' => 'Title', 'b' => 'subtitle', ...]],
     *       ...
     *     ]
     *   ]
     *
     * ISO 2709 layout:
     *   Bytes 0-23    : Leader (24 bytes)
     *   Bytes 24..N-1: Directory (12 bytes/entry + 0x1E terminator)
     *   Byte N        : Data area begins at the base address (leader bytes 12-16)
     *   Each directory entry: tag(3) + length(4) + offset(5) = 12 bytes
     *   The 'offset' field is the byte offset from the base address.
     *
     * @param string $raw  Raw ISO 2709 bytes.
     * @return array       Parsed record array.
     */
    public function decode(string $raw): array
    {
        if (strlen($raw) < 24) {
            return ['leader' => '', 'control' => [], 'data' => []];
        }

        // ── Leader (bytes 0-23, exactly 24 chars) ────────────────────────
        $leader = substr($raw, 0, 24);

        // Status byte (position 5): a=archival, c=corrected, d=deleted, n=new
        // Type byte (position 6): a=text, e=carto, etc.
        // Bibliographic level (position 7): m=monograph, s=serial
        // Indicator length (pos 10): almost always '2' for bibliographic records
        // Subfield code length (pos 11): almost always '2'
        $indicatorLen = isset($leader[10]) ? (int) $leader[10] : 2;
        $baseAddress  = (int) substr($leader, 12, 5);

        // Directory starts at byte 24 and runs until the 0x1E terminator
        // (which is the byte just before the data area — i.e. byte baseAddress - 1).
        $dirStart = 24;
        $dirEnd   = $baseAddress - 1; // last byte of directory (the 0x1E terminator slot)

        // ── Directory parsing ──────────────────────────────────────────────
        // Each entry: tag(3) + length(4) + offset(5) = 12 bytes.
        // The 'offset' is from the BASE ADDRESS (the start of the data area).
        $directory = [];
        $dpos = $dirStart;

        while ($dpos + 12 <= $dirEnd) {
            $tag   = substr($raw, $dpos, 3);
            $flen  = (int) substr($raw, $dpos + 3, 4);
            $start = (int) substr($raw, $dpos + 7, 5);
            $dpos += 12;

            if (! ctype_digit($tag)) {
                continue;
            }

            // 'start' is the offset from the base address.
            $dataStart = $baseAddress + $start;
            $dataEnd   = $dataStart + $flen - 1; // flen includes the RTF byte

            if ($dataEnd > strlen($raw)) {
                // Record short: ignore this field
                continue;
            }

            // Extract field data (excluding the trailing RTF 0x1E)
            $rawField = substr($raw, $dataStart, $flen - 1);

            if ($tag <= '009') {
                // Control field: no indicators, no subfield delimiter
                $directory[$tag] = $rawField;
            } else {
                // Data field: 2 indicators + subfield values
                $ind1 = isset($rawField[0]) ? $rawField[0] : ' ';
                $ind2 = isset($rawField[1]) ? $rawField[1] : ' ';
                $rawSubfields = substr($rawField, $indicatorLen);

                // Parse subfields: split on 0x1F, code is first byte of each chunk
                $subfields = [];
                foreach (explode("\x1F", $rawSubfields) as $chunk) {
                    if (strlen($chunk) < 1) {
                        continue;
                    }
                    $code = $chunk[0];
                    $val  = substr($chunk, 1);

                    // Handle repeatable subfields: |a Val1 |a Val2 → a, a2, a3 …
                    if (isset($subfields[$code])) {
                        $seq = 2;
                        while (isset($subfields[$code . $seq])) {
                            $seq++;
                        }
                        $subfields[$code . $seq] = $val;
                    } else {
                        $subfields[$code] = $val;
                    }
                }

                $directory[$tag] = [
                    'tag'      => $tag,
                    'ind1'     => $ind1,
                    'ind2'     => $ind2,
                    'subfields' => $subfields,
                ];
            }
        }

        // Split into control (001-009) and data (010+) arrays
        $control = [];
        $data    = [];

        foreach ($directory as $key => $val) {
            if (is_string($val) && ctype_digit($key) && (int) $key <= 9) {
                $control[sprintf('%03d', (int) $key)] = $val;
            } else {
                $data[] = $val;
            }
        }

        return [
            'leader'  => $leader,
            'control' => $control,
            'data'    => $data,
        ];
    }

    /**
     * Parse a binary MARC record and create a library item via LibraryService.
     *
     * Maps fields to Heratio columns:
     *   245$a               → title
     *   100$a               → creators[0]
     *   020$a               → isbn
     *   022$a               → issn
     *   300$a               → pagination
     *   260/264$b           → publisher
     *   008[35-37]          → language
     *   008[7-10]           → publication_date
     *   050$a               → call_number
     *   050$b               → classification_scheme
     *
     * @param string      $raw     Raw ISO 2709 bytes.
     * @param string|null $culture Culture code (default app locale).
     * @return int                  information_object ID of the created record.
     */
    public function decodeToLibraryItem(string $raw, ?string $culture = null): int
    {
        $culture = $culture ?? (string) app()->getLocale();
        $parsed  = $this->decode($raw);
        $data    = $parsed['data'];
        $control = $parsed['control'];

        // Helper: first occurrence of a given tag
        $firstOf = function (string $tag) use ($data): array|null {
            foreach ($data as $f) {
                if (($f['tag'] ?? '') === $tag) {
                    return $f;
                }
            }
            return null;
        };

        // Helper: flatten subfields — strips repeat suffixes (a2 → a)
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

        // Title: 245$a required
        $f245    = $firstOf('245');
        $sub245  = $f245 ? $flat($f245) : [];
        $title   = $sub245['a'] ?? 'Untitled';
        $subtitle = $sub245['b'] ?? null;

        // Main entry author as creator
        $creators = [];
        $f100 = $firstOf('100');
        if ($f100) {
            $creators[] = [
                'name' => $flat($f100)['a'] ?? '',
                'role' => 'author',
            ];
        }
        $f110 = $firstOf('110');
        if ($f110) {
            $creators[] = [
                'name' => $flat($f110)['a'] ?? '',
                'role' => 'creator',
            ];
        }

        // 008 language code (positions 35-37 of the 40-byte control field)
        $langMap = [
            'eng' => 'en', 'afr' => 'af', 'dut' => 'nl', 'fre' => 'fr',
            'ger' => 'de', 'ita' => 'it', 'spa' => 'es', 'por' => 'pt',
            'rus' => 'ru', 'chi' => 'zh', 'jpn' => 'ja', 'kor' => 'ko',
            'ara' => 'ar', 'heb' => 'he',
        ];
        $langCode = 'en';
        $c008 = $control['008'] ?? '';
        if (strlen($c008) >= 38) {
            $rawLang = strtolower(substr($c008, 35, 3));
            $langCode = $langMap[$rawLang] ?? $rawLang;
        }

        // 008 date (positions 7-10) — four-character publication year
        $pubDate = null;
        if (strlen($c008) >= 11) {
            $yearRaw = substr($c008, 7, 4);
            if (ctype_digit($yearRaw)) {
                $pubDate = $yearRaw;
            }
        }

        // Physical description: 300$a
        $f300   = $firstOf('300');
        $sub300 = $f300 ? $flat($f300) : [];
        $pagination = $sub300['a'] ?? null;

        // Publication: 264$b (preferred) falls back to 260$b
        $f260 = $firstOf('260');
        $f264 = $firstOf('264');
        $pubField = $f264 ?? $f260;
        $pubSub   = $pubField ? $flat($pubField) : [];
        $publisher = $pubSub['b'] ?? null;
        $pubPlace  = $pubSub['a'] ?? null;

        // Classification: 050
        $f050   = $firstOf('050');
        $sub050 = $f050 ? $flat($f050) : [];
        $callNumber  = $sub050['a'] ?? null;
        $classScheme = ! empty($sub050['b']) ? 'LC' : null;

        // Identifiers
        $f020 = $firstOf('020');
        $isbn = $f020 ? ($flat($f020)['a'] ?? null) : null;

        $f022 = $firstOf('022');
        $issn = $f022 ? ($flat($f022)['a'] ?? null) : null;

        // Create via LibraryService
        $libraryService = new LibraryService($culture);

        return $libraryService->create([
            'title' => $title,
            'subtitle' => $subtitle,
            'creators' => $creators,
            'isbn' => $isbn,
            'issn' => $issn,
            'pagination' => $pagination,
            'publisher' => $publisher,
            'publication_place' => $pubPlace,
            'publication_date' => $pubDate,
            'language' => $langCode,
            'call_number' => $callNumber,
            'classification_scheme' => $classScheme,
            'material_type' => $this->inferMaterialType($parsed),
        ]);
    }

    /**
     * Infer material type from the MARC21 leader, then fall back to a sensible
     * default ('monograph') if the hint is inconclusive.
     *
     * @param array $parsed  Output of decode().
     * @return string        Material type key.
     */
    public function inferMaterialType(array $parsed): string
    {
        $leader = $parsed['leader'] ?? '';
        if (strlen($leader) < 8) {
            return 'monograph';
        }

        $recType  = $leader[6] ?? ' ';
        $bibLevel = $leader[7] ?? ' ';

        // Visual materials from leader position 18 (undefined in some records)
        return match ($recType) {
            'a', 't' => match ($bibLevel) {
                'm' => 'monograph',
                's' => 'periodical',
                'a' => 'monograph',
                'b' => 'serial',
                'i' => 'serial',
                'j' => 'monograph',
                default => 'monograph',
            },
            'e', 'f' => 'map',
            'c', 'd' => 'manuscript',
            'i', 'j' => 'audiovisual',
            'k' => 'other',
            'm' => 'electronic',
            'p' => 'kit',
            'r' => 'other',
            default => 'monograph',
        };
    }
}