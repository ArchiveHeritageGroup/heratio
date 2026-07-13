<?php

/**
 * KbartService - NISO KBART knowledge-base exchange import/export service
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

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Generator;

/**
 * Implements the NISO KBART (KBART 1.2 / ANSI/NISO Z39.83) standard for
 * library knowledge-base exchange.
 *
 * Reference: https://www.niso.org/standards-committees/kbart
 *
 * All database operations are guarded with Schema::hasTable() so the service
 * degrades gracefully when the optional library tables are not present.
 */
class KbartService
{
    /**
     * NISO KBART 1.2 column names in the order defined by the standard.
     * Used as both the export header row and to parse import TSV by position.
     */
    public const HEADERS = [
        'publication_title',
        'parent_publication_title',
        'preceding_publication_title',
        'type_of_resource',
        'title_url',
        'title_id',
        'first_author',
        'follows',
        'volume',
        'issue',
        'edition',
        'date_monograph_published',
        'date_monograph_published_print',
        'date_monograph_published_electronic',
        'number_of_volumes',
        'author',
        'publisher',
        'pub_type',
        'publication_type',
        'language',
        'isbn',
        'isbn_print',
        'isbn_electronic',
        'isbn_electronic_type',
        'eissn',
        'print_issn',
        'uri',
        'date_last_issued',
        'format',
        'geo_restriction',
        'access_restriction',
        'coverage_depth',
        'coverage_note',
        'embargo_info',
        'data_source',
        'oclc_number',
        'priority',
        'proprietary_id',
        'platform',
        'vendor_id',
        'doi',
        'access_type',
        'rel_membership',
        'lccn',
        'title_notes',
    ];

    /**
     * Return a TSV string containing only the NISO KBART header row.
     * Use this as the template/download for manual import preparation.
     */
    public function getKbartTemplate(): string
    {
        return implode("\t", self::HEADERS) . "\n";
    }

    /**
     * Escape a value for TSV output. Any tab, newline, or double-quote is
     * escaped by wrapping the field in double-quotes and doubling inner quotes.
     */
    private function escape(string $value): string
    {
        if ($value === '' || $value === 'NULL' || $value === null) {
            return '';
        }
        $needsQuoting = str_contains($value, "\t")
            || str_contains($value, "\n")
            || str_contains($value, '"');
        if ($needsQuoting) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    /**
     * Build TSV rows from the library catalogue, yielding one row per call.
     * Optionally filters by date range and limits the total rows.
     *
     * @param string|null $startDate ISO date or null
     * @param string|null $endDate   ISO date or null
     * @param int|null    $limit     Maximum rows to yield (default 50 000)
     * @return Generator<string>      Yields TSV rows
     */
    public function buildKbartRows(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $limit = null
    ): Generator {
        if (!Schema::hasTable('library_item') || !Schema::hasTable('information_object_i18n')) {
            return;
        }

        $limit = $limit ?? 50000;
        $culture = (string) app()->getLocale();

        // library_serial is an optional module (LibrarySerialService guards every
        // access with Schema::hasTable). Only join it when both the table and the
        // library_item.serial_id column exist, otherwise the export hard-fails on
        // installs that never provisioned the serials schema.
        $hasSerial = Schema::hasTable('library_serial')
            && Schema::hasColumn('library_item', 'serial_id');
        // eisbn is likewise not present on every library_item schema; fall back
        // to NULL so the electronic-ISSN column exports blank rather than 500ing.
        $hasEisbn = Schema::hasColumn('library_item', 'eisbn');

        $query = DB::table('library_item')
            ->join('information_object', 'library_item.information_object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($j) use ($culture) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->leftJoin('library_item_creator', 'library_item.id', '=', 'library_item_creator.library_item_id');

        if ($hasSerial) {
            $query->leftJoin('library_serial', 'library_item.serial_id', '=', 'library_serial.id');
        }

        // Rows are grouped by library_item.id, so library_item.* columns are
        // functionally dependent on the group key, but columns pulled through the
        // joins (title, language, first_author, title_url, serial frequency) are
        // not - wrap those in ANY_VALUE() so the export stays valid under MySQL's
        // ONLY_FULL_GROUP_BY sql_mode instead of erroring out.
        $query->select([
                'library_item.id as library_item_id',
                DB::raw('ANY_VALUE(information_object_i18n.title) as publication_title'),
                'library_item.isbn',
                'library_item.issn as print_identifier',
                $hasEisbn
                    ? 'library_item.eisbn as eisbn'
                    : DB::raw('NULL as eisbn'),
                'library_item.publisher',
                'library_item.publication_date as date_monograph_published',
                'library_item.doi',
                'library_item.barcode as proprietary_id',
                'library_item.material_type',
                DB::raw('ANY_VALUE(information_object.source_culture) as language'),
                'library_item.edition',
                'library_item.volume_designation as volume',
                'library_item.responsibility_statement as author',
                DB::raw('ANY_VALUE(library_item_creator.name) as first_author'),
                $hasSerial
                    ? DB::raw('ANY_VALUE(library_serial.frequency) as rel_membership')
                    : DB::raw('NULL as rel_membership'),
                DB::raw('ANY_VALUE(slug.slug) as title_url'),
                'library_item.barcode',
            ]);

        if ($startDate) {
            $query->where('information_object.updated_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('information_object.updated_at', '<=', $endDate . ' 23:59:59');
        }

        // One row per library_item, ordered by id for deterministic output
        $query->groupBy('library_item.id')
            ->orderBy('library_item.id')
            ->limit($limit);

        $rows = $query->get();

        foreach ($rows as $row) {
            $pubType = $this->mapPublicationType($row->material_type ?? '');
            $rowData = [
                'publication_title'               => (string) ($row->publication_title ?? ''),
                'parent_publication_title'        => '',
                'preceding_publication_title'     => '',
                'type_of_resource'                => '',
                'title_url'                       => $row->title_url
                    ? url('/library/' . $row->title_url)
                    : '',
                'title_id'                        => '',
                'first_author'                    => (string) ($row->first_author ?? ''),
                'follows'                         => '',
                'volume'                          => (string) ($row->volume ?? ''),
                'issue'                           => '',
                'edition'                         => (string) ($row->edition ?? ''),
                'date_monograph_published'        => (string) ($row->date_monograph_published ?? ''),
                'date_monograph_published_print'   => '',
                'date_monograph_published_electronic' => '',
                'number_of_volumes'               => '',
                'author'                          => (string) ($row->author ?? ''),
                'publisher'                      => (string) ($row->publisher ?? ''),
                'pub_type'                        => '',
                'publication_type'                => $pubType,
                'language'                        => (string) ($row->language ?? ''),
                'isbn'                            => (string) ($row->isbn ?? ''),
                'isbn_print'                      => '',
                'isbn_electronic'                 => '',
                'isbn_electronic_type'            => '',
                'eissn'                           => (string) ($row->eisbn ?? ''),
                'print_issn'                      => (string) ($row->print_identifier ?? ''),
                'uri'                             => '',
                'date_last_issued'               => '',
                'format'                          => '',
                'geo_restriction'                 => '',
                'access_restriction'              => '',
                'coverage_depth'                  => '',
                'coverage_note'                   => '',
                'embargo_info'                    => '',
                'data_source'                     => 'Heratio',
                'oclc_number'                     => '',
                'priority'                        => '',
                'proprietary_id'                 => (string) ($row->barcode ?? ''),
                'platform'                        => 'Heratio',
                'vendor_id'                       => '',
                'doi'                             => (string) ($row->doi ?? ''),
                'access_type'                     => '',
                'rel_membership'                  => (string) ($row->rel_membership ?? ''),
                'lccn'                            => '',
                'title_notes'                     => '',
            ];

            $values = array_map(fn($k) => $this->escape($rowData[$k] ?? ''), self::HEADERS);
            yield implode("\t", $values) . "\n";
        }
    }

    /**
     * Map Heratio material_type to the NISO KBART publication_type vocabulary.
     */
    private function mapPublicationType(string $materialType): string
    {
        return match ($materialType) {
            'monograph'    => 'Book',
            'ebook'        => 'Book',
            'journal'      => 'Journal',
            'periodical'   => 'Journal',
            'manuscript'   => 'Manuscript',
            'map'          => 'Map',
            'audiovisual'  => 'Audiovisual',
            'microform'    => 'Microform',
            'electronic'   => 'Electronic resource',
            'kit'          => 'Kit',
            default        => 'Other',
        };
    }

    /**
     * Parse a KBART TSV string and yield rows as associative arrays keyed
     * by HEADERS column name. The header row is stripped automatically.
     *
     * @param string $tsv Raw TSV text
     * @return Generator<array<string,string>> Yields row arrays
     */
    public function parseKbartRowsFromString(string $tsv): Generator
    {
        $lines = preg_split('/\r?\n/', $tsv, -1, PREG_SPLIT_NO_EMPTY);
        $isFirst = true;

        foreach ($lines as $line) {
            if ($isFirst) {
                $isFirst = false;
                continue; // skip header row
            }

            $row = $this->parseTsvLine($line);
            if (count($row) === count(self::HEADERS)) {
                yield array_combine(self::HEADERS, $row);
            }
        }
    }

    /**
     * Parse a single TSV line into an array of field values, handling
     * double-quote escaping per RFC 4180.
     *
     * @return array<string>
     */
    private function parseTsvLine(string $line): array
    {
        $fields = [];
        $current = '';
        $inQuotes = false;
        $len = mb_strlen($line);
        $buffer = '';

        // Simple CSV/TSV parser that handles quoted fields
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($line, $i, 1);

            if (!$inQuotes) {
                if ($char === '"') {
                    $inQuotes = true;
                } elseif ($char === "\t") {
                    $fields[] = $buffer;
                    $buffer = '';
                } else {
                    $buffer .= $char;
                }
            } else {
                if ($char === '"') {
                    $next = ($i + 1 < $len) ? mb_substr($line, $i + 1, 1) : '';
                    if ($next === '"') {
                        $buffer .= '"';
                        $i++;
                    } else {
                        $inQuotes = false;
                    }
                } else {
                    $buffer .= $char;
                }
            }
        }
        $fields[] = $buffer;

        return $fields;
    }

    /**
     * Preview the first N rows of an import. Each result includes row number,
     * validity flag, list of error strings, and identifier fields.
     *
     * @param string $tsv    Raw TSV text
     * @param int    $maxRows Maximum rows to preview (default 20)
     * @return array<array{row_number:int, is_valid:bool, errors:string[], publication_title:string, isbn:string, issn:string, eissn:string:string}>
     */
    public function previewImport(string $tsv, int $maxRows = 20): array
    {
        $result = [];
        $rowNumber = 1;
        $parser = $this->parseKbartRowsFromString($tsv);

        foreach ($parser as $row) {
            if ($rowNumber > $maxRows) {
                break;
            }

            $errors = [];
            $publicationTitle = trim($row['publication_title'] ?? '');
            $isbn = trim($row['isbn'] ?? '');
            $issn = trim($row['print_issn'] ?? '');
            $eissn = trim($row['eissn'] ?? '');
            $proprietaryId = trim($row['proprietary_id'] ?? '');

            if ($publicationTitle === '') {
                $errors[] = 'publication_title is required';
            }

            $hasId = $isbn !== '' || $issn !== '' || $eissn !== '' || $proprietaryId !== '';
            if (!$hasId) {
                $errors[] = 'At least one identifier (ISBN, ISSN, eISSN, proprietary_id) is required';
            }

            $result[] = [
                'row_number' => $rowNumber,
                'is_valid' => empty($errors),
                'errors' => $errors,
                'publication_title' => $publicationTitle,
                'isbn' => $isbn,
                'issn' => $issn,
                'eissn' => $eissn,
            ];

            $rowNumber++;
        }

        return $result;
    }

    /**
     * Commit a full KBART import batch. Validates each row and writes
     * records to library_serial (for serials) and library_item (for monographs).
     *
     * @param string $tsv Raw TSV text
     * @return int Number of rows successfully written
     */
    public function writeImportBatch(string $tsv): int
    {
        $count = 0;
        $parser = $this->parseKbartRowsFromString($tsv);

        foreach ($parser as $row) {
            $pubTitle = trim($row['publication_title'] ?? '');
            if ($pubTitle === '') {
                continue;
            }

            $isbn = trim($row['isbn'] ?? '');
            $issn = trim($row['print_issn'] ?? '');
            $eissn = trim($row['eissn'] ?? '');
            $proprietaryId = trim($row['proprietary_id'] ?? '');
            $pubType = trim($row['publication_type'] ?? '');
            $publisher = trim($row['publisher'] ?? '');
            $author = trim($row['author'] ?? '');
            $pubDate = trim($row['date_monograph_published'] ?? '');
            $language = trim($row['language'] ?? '');
            $doi = trim($row['doi'] ?? '');
            $frequency = trim($row['rel_membership'] ?? '');

            // Duplicate detection: skip if same ISBN + title already exists
            if ($isbn !== '') {
                $exists = DB::table('library_item')
                    ->where('isbn', $isbn)
                    ->whereNotNull('isbn')
                    ->exists();
                if ($exists) {
                    continue;
                }
            }

            // Detect serial vs monograph from publication_type field
            $isSerial = $this->looksLikeSerial($pubType, $issn, $eissn);

            if ($isSerial) {
                // Upsert: existing serial by ISSN or title
                $serialId = DB::table('library_serial')
                    ->where('issn', $issn)
                    ->value('id');
                if (!$serialId && $issn !== '') {
                    $serialId = DB::table('library_serial')
                        ->where('title', $pubTitle)
                        ->value('id');
                }

                $now = now();
                $serialData = [
                    'title' => $pubTitle,
                    'issn' => $issn !== '' ? $issn : null,
                    'frequency' => $frequency !== '' ? $frequency : 'monthly',
                    'publisher' => $publisher !== '' ? $publisher : null,
                    'status' => 'active',
                    'updated_at' => $now,
                ];
                if (!$serialId) {
                    $serialData['created_at'] = $now;
                    $serialId = DB::table('library_serial')->insertGetId($serialData);
                } else {
                    DB::table('library_serial')->where('id', $serialId)->update($serialData);
                }
            }

            // Map to library_item fields for monograph-like items
            $itemData = [
                'material_type' => $this->mapKbartToMaterialType($pubType),
                'title' => $pubTitle,
                'isbn' => $isbn !== '' ? $isbn : null,
                'issn' => $issn !== '' ? $issn : null,
                'publisher' => $publisher !== '' ? $publisher : null,
                'publication_date' => $pubDate !== '' ? $pubDate : null,
                'language' => $language !== '' ? $language : null,
                'responsibility_statement' => $author !== '' ? $author : null,
                'doi' => $doi !== '' ? $doi : null,
                'status' => 'available',
            ];

            $service = new LibraryService($language ?: app()->getLocale());

            try {
                $service->create($itemData);
                $count++;
            } catch (\Throwable $e) {
                \Log::warning('KbartService writeImportBatch error: ' . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Heuristic: determine if a row represents a serial based on
     * publication_type, presence of ISSN/eISSN, and frequency field.
     */
    private function looksLikeSerial(string $pubType, string $issn, string $eissn): bool
    {
        if (in_array(mb_strtolower($pubType), ['journal', 'periodical', 'serial', 'newsletter'])) {
            return true;
        }
        if ($issn !== '' || $eissn !== '') {
            return true;
        }
        return false;
    }

    /**
     * Map a KBART publication_type value to a Heratio material_type value.
     */
    private function mapKbartToMaterialType(string $pubType): string
    {
        return match (mb_strtolower($pubType)) {
            'book', 'monograph' => 'monograph',
            'journal', 'periodical', 'serial' => 'journal',
            'manuscript' => 'manuscript',
            'map' => 'map',
            'audiovisual' => 'audiovisual',
            'microform' => 'microform',
            'electronic resource' => 'electronic',
            'kit' => 'kit',
            default => 'monograph',
        };
    }

    /**
     * Build a streamed download response containing the KBART export.
     *
     * @param string|null $startDate ISO date
     * @param string|null $endDate   ISO date
     * @param int|null    $limit     Row limit
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function buildExportResponse(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $limit = null
    ) {
        $filename = 'heratio-kbart-export-' . date('Ymd') . '.tsv';
        $headers = [
            'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $self = $this;

        return response()->stream(
            function () use ($self, $startDate, $endDate, $limit) {
                echo implode("\t", self::HEADERS) . "\n";
                foreach ($self->buildKbartRows($startDate, $endDate, $limit) as $row) {
                    echo $row;
                    flush();
                }
            },
            200,
            $headers
        );
    }
}
