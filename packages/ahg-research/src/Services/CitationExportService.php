<?php

/**
 * CitationExportService - Service for Heratio
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

/**
 * CitationExportService
 *
 * Serialises an already-loaded bibliography (and its entries) into the three
 * interchange formats every reference manager imports:
 *
 *   bibtex  - BibTeX (.bib)  for LaTeX / JabRef / Zotero / Mendeley
 *   ris     - RIS (.ris)     for EndNote / Zotero / Mendeley
 *   csljson - CSL-JSON (.json) for Zotero / Pandoc / citeproc tooling
 *
 * The class is a pure serializer. It does NOT compute or load bibliographies:
 * the caller passes the entry rows produced by
 * BibliographyService::getEntries() (or the equivalent ownership-gated query in
 * the controller). Each entry row carries the columns of
 * research_bibliography_entry: entry_type, title, authors, date, publisher,
 * container_title, volume, issue, pages, doi, url, accessed_date, archive_name,
 * archive_location, collection_title, box, folder, notes, id.
 *
 * Every method is read-only over the supplied data and returns a string; no DB
 * writes occur here. Missing fields are skipped gracefully and an empty entry
 * list yields a valid-but-empty document, never an error.
 */
class CitationExportService
{
    /**
     * Supported export formats: machine key => download extension.
     */
    public const FORMATS = [
        'bibtex'  => 'bib',
        'ris'     => 'ris',
        'csljson' => 'json',
    ];

    /**
     * Entry-type -> BibTeX entry type (@book / @article / @misc ...).
     */
    private const BIBTEX_TYPES = [
        'archival' => 'misc',
        'book'     => 'book',
        'article'  => 'article',
        'chapter'  => 'incollection',
        'thesis'   => 'phdthesis',
        'website'  => 'online',
        'other'    => 'misc',
    ];

    /**
     * Entry-type -> RIS reference type (TY).
     */
    private const RIS_TYPES = [
        'archival' => 'UNPB',
        'book'     => 'BOOK',
        'article'  => 'JOUR',
        'chapter'  => 'CHAP',
        'thesis'   => 'THES',
        'website'  => 'ELEC',
        'other'    => 'GEN',
    ];

    /**
     * Entry-type -> CSL item type.
     */
    private const CSL_TYPES = [
        'archival' => 'manuscript',
        'book'     => 'book',
        'article'  => 'article-journal',
        'chapter'  => 'chapter',
        'thesis'   => 'thesis',
        'website'  => 'webpage',
        'other'    => 'document',
    ];

    /**
     * Serialise a list of bibliography entries to the requested format.
     *
     * @param array  $entries Entry rows (objects or arrays) as returned by
     *                        BibliographyService::getEntries().
     * @param string $format  One of: bibtex|ris|csljson.
     * @return string|null    The serialised document, or null for an unknown
     *                        format (caller maps null to a 404).
     */
    public function export(array $entries, string $format): ?string
    {
        $entries = array_map([$this, 'normalise'], array_values($entries));

        return match (strtolower($format)) {
            'bibtex'  => $this->toBibTeX($entries),
            'ris'     => $this->toRIS($entries),
            'csljson' => $this->toCslJson($entries),
            default   => null,
        };
    }

    /**
     * MIME type for a format. Empty string for an unknown format.
     */
    public function mimeFor(string $format): string
    {
        return match (strtolower($format)) {
            'bibtex'  => 'text/x-bibtex',
            'ris'     => 'application/x-research-info-systems',
            'csljson' => 'application/vnd.citationstyles.csl+json',
            default   => '',
        };
    }

    /**
     * Download filename for a bibliography in a given format.
     *
     * @param string|null $name   The bibliography name (may be null).
     * @param int|null    $id     The bibliography id (fallback in the filename).
     * @param string      $format One of: bibtex|ris|csljson.
     */
    public function filenameFor(?string $name, ?int $id, string $format): string
    {
        $ext  = self::FORMATS[strtolower($format)] ?? 'txt';
        $base = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($name ?: ''));
        $base = trim((string) $base, '-');
        if ($base === '') {
            $base = 'bibliography-' . ($id ?? '0');
        }
        return strtolower($base) . '.' . $ext;
    }

    // =====================================================================
    // BibTeX
    // =====================================================================

    private function toBibTeX(array $entries): string
    {
        $blocks  = [];
        $usedKeys = [];

        foreach ($entries as $e) {
            $blocks[] = $this->entryToBibTeX($e, $usedKeys);
        }

        return implode("\n\n", $blocks) . (empty($blocks) ? '' : "\n");
    }

    private function entryToBibTeX(object $e, array &$usedKeys): string
    {
        $type = self::BIBTEX_TYPES[$e->entry_type] ?? 'misc';
        $key  = $this->citeKey($e, $usedKeys);

        $fields = [];
        if ($e->title)           $fields[] = '  title = {' . $this->escapeBibTeX($e->title) . '}';
        if ($e->authors)         $fields[] = '  author = {' . $this->bibtexAuthors($e->authors) . '}';
        if ($year = $this->year($e->date)) {
            $fields[] = '  year = {' . $year . '}';
        }
        if ($e->publisher)       $fields[] = '  publisher = {' . $this->escapeBibTeX($e->publisher) . '}';
        if ($e->container_title) $fields[] = '  booktitle = {' . $this->escapeBibTeX($e->container_title) . '}';
        if ($e->volume)          $fields[] = '  volume = {' . $this->escapeBibTeX($e->volume) . '}';
        if ($e->issue)           $fields[] = '  number = {' . $this->escapeBibTeX($e->issue) . '}';
        if ($e->pages)           $fields[] = '  pages = {' . $this->escapeBibTeX($e->pages) . '}';
        if ($e->doi)             $fields[] = '  doi = {' . $this->escapeBibTeX($e->doi) . '}';
        if ($e->url)             $fields[] = '  url = {' . $this->escapeBibTeX($e->url) . '}';

        $note = $this->bibtexNote($e);
        if ($note !== '') {
            $fields[] = '  note = {' . $this->escapeBibTeX($note) . '}';
        }

        return "@{$type}{{$key},\n" . implode(",\n", $fields) . "\n}";
    }

    /**
     * BibTeX authors join with " and " between names.
     */
    private function bibtexAuthors(string $authors): string
    {
        $names = array_filter(array_map('trim', explode(';', $authors)));
        return $this->escapeBibTeX(implode(' and ', $names));
    }

    /**
     * Build a note from archival fields that have no first-class BibTeX field.
     */
    private function bibtexNote(object $e): string
    {
        $bits = [];
        if ($e->archive_name)     $bits[] = 'Archive: ' . $e->archive_name;
        if ($e->archive_location) $bits[] = 'Location: ' . $e->archive_location;
        if ($e->collection_title) $bits[] = 'Collection: ' . $e->collection_title;
        if ($e->box)              $bits[] = 'Box: ' . $e->box;
        if ($e->folder)           $bits[] = 'Folder: ' . $e->folder;
        if ($e->notes)            $bits[] = $e->notes;
        return implode('; ', $bits);
    }

    /**
     * Author-year-ish cite key, de-duplicated with a/b/c suffixes.
     */
    private function citeKey(object $e, array &$usedKeys): string
    {
        $authorPart = '';
        if ($e->authors) {
            $first = trim((string) (explode(';', $e->authors)[0] ?? ''));
            // "Surname, Given" -> Surname; otherwise last whitespace token.
            $surname = strpos($first, ',') !== false
                ? trim(explode(',', $first)[0])
                : (function ($s) {
                    $parts = preg_split('/\s+/', trim($s)) ?: [];
                    return end($parts) ?: $s;
                })($first);
            $authorPart = $surname;
        }
        if ($authorPart === '') {
            $authorPart = $e->archive_name ?: 'ref';
        }

        $key  = preg_replace('/[^a-z0-9]+/i', '', $authorPart);
        $key  = $key !== '' ? $key : 'ref';
        $key .= $this->year($e->date) ?: '';
        $key  = strtolower($key) ?: 'ref';

        // De-duplicate: ref, refa, refb, ...
        $candidate = $key;
        $suffix    = 'a';
        while (isset($usedKeys[$candidate])) {
            $candidate = $key . $suffix;
            $suffix    = chr(ord($suffix) + 1);
        }
        $usedKeys[$candidate] = true;

        return $candidate;
    }

    private function escapeBibTeX(string $text): string
    {
        $map = [
            '\\' => '\textbackslash{}',
            '&'  => '\&',
            '%'  => '\%',
            '$'  => '\$',
            '#'  => '\#',
            '_'  => '\_',
            '{'  => '\{',
            '}'  => '\}',
            '~'  => '\textasciitilde{}',
            '^'  => '\textasciicircum{}',
        ];
        // Backslash first so we do not double-escape the replacements above.
        $text = str_replace('\\', $map['\\'], $text);
        unset($map['\\']);
        return str_replace(array_keys($map), array_values($map), $text);
    }

    // =====================================================================
    // RIS
    // =====================================================================

    private function toRIS(array $entries): string
    {
        $blocks = [];
        foreach ($entries as $e) {
            $blocks[] = $this->entryToRIS($e);
        }
        // RIS records are CRLF-delimited; a trailing blank line is harmless.
        return implode("\r\n", $blocks) . (empty($blocks) ? '' : "\r\n");
    }

    private function entryToRIS(object $e): string
    {
        $type  = self::RIS_TYPES[$e->entry_type] ?? 'GEN';
        $lines = ['TY  - ' . $type];

        if ($e->title) $lines[] = 'TI  - ' . $this->risValue($e->title);
        if ($e->authors) {
            foreach (array_filter(array_map('trim', explode(';', $e->authors))) as $a) {
                $lines[] = 'AU  - ' . $this->risValue($a);
            }
        }
        if ($year = $this->year($e->date)) {
            $lines[] = 'PY  - ' . $year;
        }
        if ($e->date)            $lines[] = 'DA  - ' . $this->risValue($e->date);
        if ($e->publisher)       $lines[] = 'PB  - ' . $this->risValue($e->publisher);
        if ($e->container_title) $lines[] = 'T2  - ' . $this->risValue($e->container_title);
        if ($e->volume)          $lines[] = 'VL  - ' . $this->risValue($e->volume);
        if ($e->issue)           $lines[] = 'IS  - ' . $this->risValue($e->issue);
        if ($e->pages)           $lines[] = 'SP  - ' . $this->risValue($e->pages);
        if ($e->doi)             $lines[] = 'DO  - ' . $this->risValue($e->doi);
        if ($e->url)             $lines[] = 'UR  - ' . $this->risValue($e->url);
        if ($e->archive_name)    $lines[] = 'AN  - ' . $this->risValue($e->archive_name);
        if ($e->collection_title) $lines[] = 'N1  - Collection: ' . $this->risValue($e->collection_title);
        if ($e->box)             $lines[] = 'N1  - Box: ' . $this->risValue($e->box);
        if ($e->folder)          $lines[] = 'N1  - Folder: ' . $this->risValue($e->folder);
        if ($e->notes)           $lines[] = 'N1  - ' . $this->risValue($e->notes);

        $id = $this->risValue((string) ($e->id ?? ''));
        if ($id !== '')          $lines[] = 'ID  - entry' . $id;
        if ($e->accessed_date)   $lines[] = 'Y2  - ' . $this->risValue($e->accessed_date);

        $lines[] = 'ER  - ';

        return implode("\r\n", $lines);
    }

    /**
     * RIS values are single-line; collapse newlines so a value never breaks
     * the TAG  - VALUE grammar.
     */
    private function risValue(string $value): string
    {
        return trim(preg_replace('/\s*\r?\n\s*/', ' ', $value));
    }

    // =====================================================================
    // CSL-JSON
    // =====================================================================

    private function toCslJson(array $entries): string
    {
        $items = [];
        foreach ($entries as $e) {
            $items[] = $this->entryToCsl($e);
        }
        // [] for an empty bibliography - a valid CSL-JSON document.
        return json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function entryToCsl(object $e): array
    {
        $item = [
            'id'   => 'entry-' . ($e->id ?? uniqid()),
            'type' => self::CSL_TYPES[$e->entry_type] ?? 'document',
        ];

        if ($e->title) $item['title'] = $e->title;

        if ($e->authors) {
            $authors = [];
            foreach (array_filter(array_map('trim', explode(';', $e->authors))) as $a) {
                $authors[] = $this->cslName($a);
            }
            if (!empty($authors)) {
                $item['author'] = $authors;
            }
        }

        $dateParts = $this->dateParts($e->date);
        if (!empty($dateParts)) {
            $item['issued'] = ['date-parts' => [$dateParts]];
        }

        if ($e->publisher)       $item['publisher']       = $e->publisher;
        if ($e->container_title) $item['container-title'] = $e->container_title;
        if ($e->volume)          $item['volume']          = $e->volume;
        if ($e->issue)           $item['issue']           = $e->issue;
        if ($e->pages)           $item['page']            = $e->pages;
        if ($e->doi)             $item['DOI']             = $e->doi;
        if ($e->url)             $item['URL']             = $e->url;
        if ($e->archive_name)    $item['archive']         = $e->archive_name;
        if ($e->archive_location) $item['archive_location'] = $e->archive_location;
        if ($e->collection_title) $item['collection-title'] = $e->collection_title;

        $accessed = $this->dateParts($e->accessed_date);
        if (!empty($accessed)) {
            $item['accessed'] = ['date-parts' => [$accessed]];
        }

        if ($e->notes) $item['note'] = $e->notes;

        return $item;
    }

    /**
     * Best-effort split of "Surname, Given" or "Given Surname" into CSL
     * family/given. Falls back to a literal name when the split is ambiguous.
     */
    private function cslName(string $author): array
    {
        if (strpos($author, ',') !== false) {
            $parts = preg_split('/,\s*/', $author, 2);
            $family = trim($parts[0] ?? '');
            $given  = trim($parts[1] ?? '');
            if ($family !== '' && $given !== '') {
                return ['family' => $family, 'given' => $given];
            }
            return ['literal' => trim($author)];
        }

        $tokens = preg_split('/\s+/', trim($author)) ?: [];
        if (count($tokens) >= 2) {
            $family = array_pop($tokens);
            return ['family' => $family, 'given' => implode(' ', $tokens)];
        }

        return ['literal' => trim($author)];
    }

    /**
     * Parse a date string into CSL date-parts [year, month?, day?].
     * Returns [] when no year can be found.
     */
    private function dateParts(?string $date): array
    {
        if (!$date) {
            return [];
        }
        if (!preg_match('/(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?/', $date, $m)) {
            return [];
        }
        $parts = [(int) $m[1]];
        if (isset($m[2]) && $m[2] !== '') {
            $parts[] = (int) $m[2];
        }
        if (isset($m[3]) && $m[3] !== '') {
            $parts[] = (int) $m[3];
        }
        return $parts;
    }

    // =====================================================================
    // Shared helpers
    // =====================================================================

    /**
     * Extract a 4-digit year from a free-text date string.
     */
    private function year(?string $date): ?string
    {
        if (!$date) {
            return null;
        }
        return preg_match('/(\d{4})/', $date, $m) ? $m[1] : null;
    }

    /**
     * Normalise an entry (array or object) to an object with every field
     * present (null when absent), so serializers never warn on missing keys.
     */
    private function normalise($entry): object
    {
        $row = is_array($entry) ? (object) $entry : clone (object) $entry;

        foreach ([
            'id', 'entry_type', 'title', 'authors', 'date', 'publisher',
            'container_title', 'volume', 'issue', 'pages', 'doi', 'url',
            'accessed_date', 'archive_name', 'archive_location',
            'collection_title', 'box', 'folder', 'notes',
        ] as $field) {
            if (!property_exists($row, $field)) {
                $row->$field = null;
            }
        }

        $row->entry_type = $row->entry_type ?: 'other';

        return $row;
    }
}
