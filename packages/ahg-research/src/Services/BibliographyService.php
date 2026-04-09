<?php

/**
 * BibliographyService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

use AhgCore\Constants\TermId;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use DOMDocument;
use RuntimeException;

/**
 * BibliographyService - Bibliography Management Service
 *
 * Handles bibliographies, entries, and export to various formats
 * (RIS, BibTeX, Zotero RDF, Mendeley JSON, CSL-JSON).
 *
 * Migrated from AtoM: ahgResearchPlugin/lib/Services/BibliographyService.php
 */
class BibliographyService
{
    // =========================================================================
    // BIBLIOGRAPHY MANAGEMENT
    // =========================================================================

    /**
     * Create a new bibliography.
     *
     * @param int $researcherId The researcher ID
     * @param array $data Bibliography data
     * @return int The bibliography ID
     */
    public function createBibliography(int $researcherId, array $data): int
    {
        return DB::table('research_bibliography')->insertGetId([
            'researcher_id' => $researcherId,
            'project_id' => $data['project_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'citation_style' => $data['citation_style'] ?? 'chicago',
            'is_public' => $data['is_public'] ?? 0,
            'share_token' => bin2hex(random_bytes(32)),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get a bibliography by ID.
     *
     * @param int $id The bibliography ID
     * @return object|null The bibliography or null
     */
    public function getBibliography(int $id): ?object
    {
        $bibliography = DB::table('research_bibliography as b')
            ->leftJoin('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->leftJoin('research_project as p', 'b.project_id', '=', 'p.id')
            ->where('b.id', $id)
            ->select(
                'b.*',
                'r.first_name as researcher_first_name',
                'r.last_name as researcher_last_name',
                'p.title as project_title'
            )
            ->first();

        if ($bibliography) {
            $bibliography->entries = $this->getEntries($id);
            $bibliography->entry_count = count($bibliography->entries);
        }

        return $bibliography;
    }

    /**
     * Get bibliographies for a researcher.
     *
     * @param int $researcherId The researcher ID
     * @param int|null $projectId Optional project filter
     * @return array List of bibliographies
     */
    public function getBibliographies(int $researcherId, ?int $projectId = null): array
    {
        $query = DB::table('research_bibliography')
            ->where('researcher_id', $researcherId);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $bibliographies = $query->orderBy('updated_at', 'desc')->get()->toArray();

        foreach ($bibliographies as &$bib) {
            $bib->entry_count = DB::table('research_bibliography_entry')
                ->where('bibliography_id', $bib->id)
                ->count();
        }

        return $bibliographies;
    }

    /**
     * Update a bibliography.
     *
     * @param int $id The bibliography ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public function updateBibliography(int $id, array $data): bool
    {
        $allowed = ['name', 'description', 'citation_style', 'is_public', 'project_id'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('research_bibliography')
            ->where('id', $id)
            ->update($updateData) >= 0;
    }

    /**
     * Delete a bibliography.
     *
     * @param int $id The bibliography ID
     * @return bool Success status
     */
    public function deleteBibliography(int $id): bool
    {
        DB::table('research_bibliography_entry')->where('bibliography_id', $id)->delete();
        return DB::table('research_bibliography')->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // ENTRY MANAGEMENT
    // =========================================================================

    /**
     * Add an entry to a bibliography.
     *
     * @param int $bibliographyId The bibliography ID
     * @param array $data Entry data
     * @return int The entry ID
     */
    public function addEntry(int $bibliographyId, array $data): int
    {
        $maxOrder = DB::table('research_bibliography_entry')
            ->where('bibliography_id', $bibliographyId)
            ->max('sort_order') ?? 0;

        return DB::table('research_bibliography_entry')->insertGetId([
            'bibliography_id' => $bibliographyId,
            'object_id' => $data['object_id'] ?? null,
            'entry_type' => $data['entry_type'] ?? 'archival',
            'csl_data' => isset($data['csl_data']) ? json_encode($data['csl_data']) : null,
            'title' => $data['title'] ?? null,
            'authors' => $data['authors'] ?? null,
            'date' => $data['date'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'container_title' => $data['container_title'] ?? null,
            'volume' => $data['volume'] ?? null,
            'issue' => $data['issue'] ?? null,
            'pages' => $data['pages'] ?? null,
            'doi' => $data['doi'] ?? null,
            'url' => $data['url'] ?? null,
            'accessed_date' => $data['accessed_date'] ?? date('Y-m-d'),
            'archive_name' => $data['archive_name'] ?? null,
            'archive_location' => $data['archive_location'] ?? null,
            'collection_title' => $data['collection_title'] ?? null,
            'box' => $data['box'] ?? null,
            'folder' => $data['folder'] ?? null,
            'notes' => $data['notes'] ?? null,
            'sort_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Add an entry from an information object.
     *
     * @param int $bibliographyId The bibliography ID
     * @param int $objectId The information object ID
     * @return int The entry ID
     */
    public function addEntryFromObject(int $bibliographyId, int $objectId): int
    {
        // Get object data
        $object = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('actor_i18n as repo', function ($join) {
                $join->on('io.repository_id', '=', 'repo.id')->where('repo.culture', '=', 'en');
            })
            ->where('io.id', $objectId)
            ->select('io.*', 'ioi.title', 'ioi.scope_and_content', 'slug.slug', 'repo.authorized_form_of_name as repository_name')
            ->first();

        if (!$object) {
            throw new RuntimeException('Object not found');
        }

        // Get creators
        $creators = DB::table('event as e')
            ->join('actor_i18n as ai', function ($join) {
                $join->on('e.actor_id', '=', 'ai.id')->where('ai.culture', '=', 'en');
            })
            ->where('e.object_id', $objectId)
            ->where('e.type_id', TermId::EVENT_TYPE_CREATION)
            ->pluck('ai.authorized_form_of_name')
            ->toArray();

        // Get dates
        $dates = DB::table('event as e')
            ->join('event_i18n as ei', function ($join) {
                $join->on('e.id', '=', 'ei.id')->where('ei.culture', '=', 'en');
            })
            ->where('e.object_id', $objectId)
            ->select('e.start_date', 'ei.date as date_display')
            ->first();

        $dateStr = $dates->date_display ?? ($dates && $dates->start_date ? date('Y', strtotime($dates->start_date)) : null);

        // Get collection hierarchy
        $ancestors = $this->getObjectAncestors($objectId);
        $collectionTitle = !empty($ancestors) ? $ancestors[0]->title : null;

        // Generate URL
        $siteUrl = config('app.url', '');
        $url = rtrim($siteUrl, '/') . '/' . ($object->slug ?? $objectId);

        return $this->addEntry($bibliographyId, [
            'object_id' => $objectId,
            'entry_type' => 'archival',
            'title' => $object->title,
            'authors' => implode('; ', $creators),
            'date' => $dateStr,
            'url' => $url,
            'accessed_date' => date('Y-m-d'),
            'archive_name' => $object->repository_name,
            'collection_title' => $collectionTitle,
        ]);
    }

    /**
     * Get object ancestors for hierarchy.
     */
    private function getObjectAncestors(int $objectId): array
    {
        $object = DB::table('information_object')
            ->where('id', $objectId)
            ->first();

        if (!$object) {
            return [];
        }

        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->where('io.lft', '<', $object->lft)
            ->where('io.rgt', '>', $object->rgt)
            ->where('io.id', '!=', 1) // Exclude root
            ->orderBy('io.lft')
            ->select('io.id', 'ioi.title')
            ->get()
            ->toArray();
    }

    /**
     * Update an entry.
     *
     * @param int $entryId The entry ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public function updateEntry(int $entryId, array $data): bool
    {
        $allowed = [
            'entry_type', 'csl_data', 'title', 'authors', 'date', 'publisher',
            'container_title', 'volume', 'issue', 'pages', 'doi', 'url',
            'accessed_date', 'archive_name', 'archive_location', 'collection_title',
            'box', 'folder', 'notes', 'sort_order',
        ];

        $updateData = array_intersect_key($data, array_flip($allowed));
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        if (isset($updateData['csl_data']) && is_array($updateData['csl_data'])) {
            $updateData['csl_data'] = json_encode($updateData['csl_data']);
        }

        return DB::table('research_bibliography_entry')
            ->where('id', $entryId)
            ->update($updateData) >= 0;
    }

    /**
     * Remove an entry.
     *
     * @param int $entryId The entry ID
     * @return bool Success status
     */
    public function removeEntry(int $entryId): bool
    {
        return DB::table('research_bibliography_entry')
            ->where('id', $entryId)
            ->delete() > 0;
    }

    /**
     * Get entries for a bibliography.
     *
     * @param int $bibliographyId The bibliography ID
     * @return array List of entries
     */
    public function getEntries(int $bibliographyId): array
    {
        return DB::table('research_bibliography_entry as e')
            ->leftJoin('slug', 'e.object_id', '=', 'slug.object_id')
            ->where('e.bibliography_id', $bibliographyId)
            ->select('e.*', 'slug.slug')
            ->orderBy('e.sort_order')
            ->get()
            ->toArray();
    }

    // =========================================================================
    // EXPORT FORMATS
    // =========================================================================

    /**
     * Export bibliography as RIS format.
     *
     * @param int $bibliographyId The bibliography ID
     * @return string RIS formatted string
     */
    public function exportRIS(int $bibliographyId): string
    {
        $entries = $this->getEntries($bibliographyId);
        $output = '';

        foreach ($entries as $entry) {
            $output .= $this->entryToRIS($entry) . "\n";
        }

        return $output;
    }

    /**
     * Convert entry to RIS format.
     */
    private function entryToRIS(object $entry): string
    {
        $typeMap = [
            'archival' => 'UNPB',
            'book' => 'BOOK',
            'article' => 'JOUR',
            'chapter' => 'CHAP',
            'thesis' => 'THES',
            'website' => 'ELEC',
            'other' => 'GEN',
        ];

        $type = $typeMap[$entry->entry_type] ?? 'GEN';
        $lines = [];

        $lines[] = 'TY  - ' . $type;
        if ($entry->title) {
            $lines[] = 'TI  - ' . $entry->title;
        }
        if ($entry->authors) {
            foreach (explode(';', $entry->authors) as $author) {
                $lines[] = 'AU  - ' . trim($author);
            }
        }
        if ($entry->date) {
            $lines[] = 'PY  - ' . $entry->date;
        }
        if ($entry->publisher) {
            $lines[] = 'PB  - ' . $entry->publisher;
        }
        if ($entry->container_title) {
            $lines[] = 'T2  - ' . $entry->container_title;
        }
        if ($entry->volume) {
            $lines[] = 'VL  - ' . $entry->volume;
        }
        if ($entry->issue) {
            $lines[] = 'IS  - ' . $entry->issue;
        }
        if ($entry->pages) {
            $lines[] = 'SP  - ' . $entry->pages;
        }
        if ($entry->doi) {
            $lines[] = 'DO  - ' . $entry->doi;
        }
        if ($entry->url) {
            $lines[] = 'UR  - ' . $entry->url;
        }
        if ($entry->archive_name) {
            $lines[] = 'AN  - ' . $entry->archive_name;
        }
        if ($entry->collection_title) {
            $lines[] = 'N1  - Collection: ' . $entry->collection_title;
        }
        if ($entry->box) {
            $lines[] = 'N1  - Box: ' . $entry->box;
        }
        if ($entry->folder) {
            $lines[] = 'N1  - Folder: ' . $entry->folder;
        }
        if ($entry->notes) {
            $lines[] = 'N1  - ' . $entry->notes;
        }
        if ($entry->accessed_date) {
            $lines[] = 'Y2  - ' . $entry->accessed_date;
        }
        $lines[] = 'ER  - ';

        return implode("\n", $lines);
    }

    /**
     * Export bibliography as BibTeX format.
     *
     * @param int $bibliographyId The bibliography ID
     * @return string BibTeX formatted string
     */
    public function exportBibTeX(int $bibliographyId): string
    {
        $entries = $this->getEntries($bibliographyId);
        $output = '';

        foreach ($entries as $entry) {
            $output .= $this->entryToBibTeX($entry) . "\n\n";
        }

        return $output;
    }

    /**
     * Convert entry to BibTeX format.
     */
    private function entryToBibTeX(object $entry): string
    {
        $typeMap = [
            'archival' => 'misc',
            'book' => 'book',
            'article' => 'article',
            'chapter' => 'incollection',
            'thesis' => 'phdthesis',
            'website' => 'online',
            'other' => 'misc',
        ];

        $type = $typeMap[$entry->entry_type] ?? 'misc';
        $key = 'entry' . $entry->id;

        $fields = [];
        if ($entry->title) {
            $fields[] = '  title = {' . $this->escapeBibTeX($entry->title) . '}';
        }
        if ($entry->authors) {
            $fields[] = '  author = {' . $this->escapeBibTeX($entry->authors) . '}';
        }
        if ($entry->date) {
            $year = preg_match('/\d{4}/', $entry->date, $m) ? $m[0] : $entry->date;
            $fields[] = '  year = {' . $year . '}';
        }
        if ($entry->publisher) {
            $fields[] = '  publisher = {' . $this->escapeBibTeX($entry->publisher) . '}';
        }
        if ($entry->container_title) {
            $fields[] = '  booktitle = {' . $this->escapeBibTeX($entry->container_title) . '}';
        }
        if ($entry->volume) {
            $fields[] = '  volume = {' . $entry->volume . '}';
        }
        if ($entry->issue) {
            $fields[] = '  number = {' . $entry->issue . '}';
        }
        if ($entry->pages) {
            $fields[] = '  pages = {' . $entry->pages . '}';
        }
        if ($entry->doi) {
            $fields[] = '  doi = {' . $entry->doi . '}';
        }
        if ($entry->url) {
            $fields[] = '  url = {' . $entry->url . '}';
        }
        if ($entry->archive_name) {
            $fields[] = '  howpublished = {' . $this->escapeBibTeX($entry->archive_name) . '}';
        }
        if ($entry->notes) {
            $fields[] = '  note = {' . $this->escapeBibTeX($entry->notes) . '}';
        }

        return "@{$type}{{$key},\n" . implode(",\n", $fields) . "\n}";
    }

    /**
     * Escape special BibTeX characters.
     */
    private function escapeBibTeX(string $text): string
    {
        $replacements = [
            '&' => '\&',
            '%' => '\%',
            '$' => '\$',
            '#' => '\#',
            '_' => '\_',
            '{' => '\{',
            '}' => '\}',
            '~' => '\textasciitilde{}',
            '^' => '\textasciicircum{}',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Export bibliography as Zotero RDF.
     *
     * @param int $bibliographyId The bibliography ID
     * @return string RDF/XML string
     */
    public function exportZoteroRDF(int $bibliographyId): string
    {
        $bibliography = $this->getBibliography($bibliographyId);
        $entries = $bibliography->entries ?? [];

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:z="http://www.zotero.org/namespaces/export#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:bib="http://purl.org/net/biblio#" xmlns:dcterms="http://purl.org/dc/terms/"/>');

        foreach ($entries as $entry) {
            $item = $xml->addChild('bib:Document');
            $item->addAttribute('rdf:about', '#item_' . $entry->id, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

            if ($entry->title) {
                $item->addChild('dc:title', htmlspecialchars($entry->title), 'http://purl.org/dc/elements/1.1/');
            }
            if ($entry->authors) {
                foreach (explode(';', $entry->authors) as $author) {
                    $item->addChild('dc:creator', htmlspecialchars(trim($author)), 'http://purl.org/dc/elements/1.1/');
                }
            }
            if ($entry->date) {
                $item->addChild('dc:date', $entry->date, 'http://purl.org/dc/elements/1.1/');
            }
            if ($entry->url) {
                $item->addChild('dc:identifier', $entry->url, 'http://purl.org/dc/elements/1.1/');
            }
            if ($entry->archive_name) {
                $item->addChild('dc:source', htmlspecialchars($entry->archive_name), 'http://purl.org/dc/elements/1.1/');
            }
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    /**
     * Export bibliography as Mendeley JSON.
     *
     * @param int $bibliographyId The bibliography ID
     * @return string JSON string
     */
    public function exportMendeleyJSON(int $bibliographyId): string
    {
        $entries = $this->getEntries($bibliographyId);
        $documents = [];

        foreach ($entries as $entry) {
            $doc = [
                'type' => $this->mapTypeToMendeley($entry->entry_type),
                'title' => $entry->title,
            ];

            if ($entry->authors) {
                $doc['authors'] = [];
                foreach (explode(';', $entry->authors) as $author) {
                    $parts = explode(',', trim($author));
                    $doc['authors'][] = [
                        'last_name' => trim($parts[0] ?? $author),
                        'first_name' => trim($parts[1] ?? ''),
                    ];
                }
            }

            if ($entry->date) {
                $doc['year'] = preg_match('/\d{4}/', $entry->date, $m) ? (int) $m[0] : null;
            }

            if ($entry->url) {
                $doc['websites'] = [$entry->url];
            }
            if ($entry->doi) {
                $doc['identifiers'] = ['doi' => $entry->doi];
            }
            if ($entry->archive_name) {
                $doc['source'] = $entry->archive_name;
            }
            if ($entry->notes) {
                $doc['notes'] = $entry->notes;
            }

            $documents[] = $doc;
        }

        return json_encode($documents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Map entry type to Mendeley type.
     */
    private function mapTypeToMendeley(string $type): string
    {
        $map = [
            'archival' => 'generic',
            'book' => 'book',
            'article' => 'journal',
            'chapter' => 'book_section',
            'thesis' => 'thesis',
            'website' => 'web_page',
            'other' => 'generic',
        ];

        return $map[$type] ?? 'generic';
    }

    /**
     * Export bibliography as CSL-JSON.
     *
     * @param int $bibliographyId The bibliography ID
     * @return string JSON string
     */
    public function exportCSLJSON(int $bibliographyId): string
    {
        $entries = $this->getEntries($bibliographyId);
        $items = [];

        foreach ($entries as $entry) {
            $item = [
                'id' => 'entry_' . $entry->id,
                'type' => $this->mapTypeToCSL($entry->entry_type),
            ];

            if ($entry->title) {
                $item['title'] = $entry->title;
            }

            if ($entry->authors) {
                $item['author'] = [];
                foreach (explode(';', $entry->authors) as $author) {
                    $parts = preg_split('/,\s*/', trim($author), 2);
                    if (count($parts) === 2) {
                        $item['author'][] = [
                            'family' => $parts[0],
                            'given' => $parts[1],
                        ];
                    } else {
                        $item['author'][] = ['literal' => trim($author)];
                    }
                }
            }

            if ($entry->date) {
                if (preg_match('/(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?/', $entry->date, $m)) {
                    $dateParts = [(int) $m[1]];
                    if (isset($m[2])) {
                        $dateParts[] = (int) $m[2];
                    }
                    if (isset($m[3])) {
                        $dateParts[] = (int) $m[3];
                    }
                    $item['issued'] = ['date-parts' => [$dateParts]];
                }
            }

            if ($entry->publisher) {
                $item['publisher'] = $entry->publisher;
            }
            if ($entry->container_title) {
                $item['container-title'] = $entry->container_title;
            }
            if ($entry->volume) {
                $item['volume'] = $entry->volume;
            }
            if ($entry->issue) {
                $item['issue'] = $entry->issue;
            }
            if ($entry->pages) {
                $item['page'] = $entry->pages;
            }
            if ($entry->doi) {
                $item['DOI'] = $entry->doi;
            }
            if ($entry->url) {
                $item['URL'] = $entry->url;
            }
            if ($entry->archive_name) {
                $item['archive'] = $entry->archive_name;
            }
            if ($entry->archive_location) {
                $item['archive_location'] = $entry->archive_location;
            }
            if ($entry->accessed_date) {
                if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $entry->accessed_date, $m)) {
                    $item['accessed'] = ['date-parts' => [[(int) $m[1], (int) $m[2], (int) $m[3]]]];
                }
            }
            if ($entry->notes) {
                $item['note'] = $entry->notes;
            }

            $items[] = $item;
        }

        return json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Map entry type to CSL type.
     */
    private function mapTypeToCSL(string $type): string
    {
        $map = [
            'archival' => 'manuscript',
            'book' => 'book',
            'article' => 'article-journal',
            'chapter' => 'chapter',
            'thesis' => 'thesis',
            'website' => 'webpage',
            'other' => 'document',
        ];

        return $map[$type] ?? 'document';
    }

    /**
     * Import from citation text (attempt to parse).
     *
     * @param int $bibliographyId The bibliography ID
     * @param string $citationText The citation text
     * @return int|null The entry ID or null on failure
     */
    public function importFromCitation(int $bibliographyId, string $citationText): ?int
    {
        // Simple heuristic parsing - this is basic and won't handle all formats
        $entry = [
            'entry_type' => 'other',
            'title' => null,
            'authors' => null,
            'date' => null,
            'url' => null,
            'notes' => 'Imported from citation: ' . substr($citationText, 0, 200),
        ];

        // Try to extract URL
        if (preg_match('/(https?:\/\/[^\s<>"]+)/i', $citationText, $m)) {
            $entry['url'] = $m[1];
        }

        // Try to extract year
        if (preg_match('/\b(1[89]\d{2}|20[0-2]\d)\b/', $citationText, $m)) {
            $entry['date'] = $m[1];
        }

        // Try to extract title (text in quotes or italics markers)
        if (preg_match('/"([^"]+)"/', $citationText, $m)) {
            $entry['title'] = $m[1];
        } elseif (preg_match('/<em>([^<]+)<\/em>/i', $citationText, $m)) {
            $entry['title'] = $m[1];
        } elseif (preg_match('/<i>([^<]+)<\/i>/i', $citationText, $m)) {
            $entry['title'] = $m[1];
        }

        // If we couldn't extract a title, use the first part of the citation
        if (!$entry['title']) {
            $entry['title'] = substr(trim($citationText), 0, 200);
        }

        return $this->addEntry($bibliographyId, $entry);
    }

    // =========================================================================
    // ISSUE 149: BIBTEX / RIS IMPORT
    // =========================================================================

    /**
     * Import entries from BibTeX format.
     *
     * @param int $bibliographyId Target bibliography
     * @param string $bibtex Raw BibTeX content
     * @return array Result with imported count and errors
     */
    public function importBibTeX(int $bibliographyId, string $bibtex): array
    {
        $entries = $this->parseBibTeX($bibtex);
        $imported = 0;
        $errors = [];

        foreach ($entries as $entry) {
            try {
                $this->addEntry($bibliographyId, $entry);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = ($entry['title'] ?? 'Unknown') . ': ' . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors, 'total' => count($entries)];
    }

    /**
     * Import entries from RIS format.
     *
     * @param int $bibliographyId Target bibliography
     * @param string $ris Raw RIS content
     * @return array Result with imported count and errors
     */
    public function importRIS(int $bibliographyId, string $ris): array
    {
        $entries = $this->parseRIS($ris);
        $imported = 0;
        $errors = [];

        foreach ($entries as $entry) {
            try {
                $this->addEntry($bibliographyId, $entry);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = ($entry['title'] ?? 'Unknown') . ': ' . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors, 'total' => count($entries)];
    }

    /**
     * Parse BibTeX string into array of entry data.
     */
    public function parseBibTeX(string $bibtex): array
    {
        $entries = [];
        // Match @type{key, ... }
        preg_match_all('/@(\w+)\s*\{([^,]*),\s*((?:[^{}]|\{[^{}]*\})*)\}/s', $bibtex, $matches, PREG_SET_ORDER);

        $typeMap = [
            'article' => 'article', 'book' => 'book', 'inbook' => 'chapter',
            'incollection' => 'chapter', 'inproceedings' => 'article',
            'mastersthesis' => 'thesis', 'phdthesis' => 'thesis',
            'misc' => 'other', 'techreport' => 'other', 'unpublished' => 'other',
        ];

        foreach ($matches as $match) {
            $type = strtolower($match[1]);
            $fields = $this->parseBibTeXFields($match[3]);

            $entry = [
                'entry_type' => $typeMap[$type] ?? 'other',
                'title' => $this->cleanBibTeXValue($fields['title'] ?? ''),
                'authors' => $this->cleanBibTeXValue($fields['author'] ?? ''),
                'date' => $this->cleanBibTeXValue($fields['year'] ?? ''),
                'publisher' => $this->cleanBibTeXValue($fields['publisher'] ?? ''),
                'container_title' => $this->cleanBibTeXValue($fields['journal'] ?? $fields['booktitle'] ?? ''),
                'volume' => $this->cleanBibTeXValue($fields['volume'] ?? ''),
                'issue' => $this->cleanBibTeXValue($fields['number'] ?? ''),
                'pages' => $this->cleanBibTeXValue($fields['pages'] ?? ''),
                'doi' => $this->cleanBibTeXValue($fields['doi'] ?? ''),
                'url' => $this->cleanBibTeXValue($fields['url'] ?? ''),
                'notes' => $this->cleanBibTeXValue($fields['note'] ?? ''),
            ];

            if (!empty($entry['title'])) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Parse BibTeX field block into key-value pairs.
     */
    protected function parseBibTeXFields(string $block): array
    {
        $fields = [];
        // Match field = {value} or field = "value" or field = number
        preg_match_all('/(\w+)\s*=\s*(?:\{((?:[^{}]|\{[^{}]*\})*)\}|"([^"]*)"|(\d+))/s', $block, $fieldMatches, PREG_SET_ORDER);

        foreach ($fieldMatches as $fm) {
            $key = strtolower($fm[1]);
            $fields[$key] = $fm[2] ?? $fm[3] ?? $fm[4] ?? '';
        }

        return $fields;
    }

    /**
     * Clean BibTeX value (remove braces, trim).
     */
    protected function cleanBibTeXValue(string $value): string
    {
        $value = str_replace(['{', '}'], '', $value);
        return trim($value);
    }

    /**
     * Parse RIS string into array of entry data.
     */
    public function parseRIS(string $ris): array
    {
        $entries = [];
        $current = null;
        $authors = [];

        $typeMap = [
            'JOUR' => 'article', 'BOOK' => 'book', 'CHAP' => 'chapter',
            'THES' => 'thesis', 'RPRT' => 'other', 'CONF' => 'article',
            'ELEC' => 'website', 'GEN' => 'other', 'MGZN' => 'article',
        ];

        $lines = preg_split('/\r?\n/', $ris);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Match RIS tag format: XX  - Value
            if (preg_match('/^([A-Z][A-Z0-9])\s+-\s+(.*)$/', $line, $m)) {
                $tag = $m[1];
                $value = trim($m[2]);

                if ($tag === 'TY') {
                    $current = [
                        'entry_type' => $typeMap[$value] ?? 'other',
                        'title' => '', 'authors' => '', 'date' => '',
                        'publisher' => '', 'container_title' => '',
                        'volume' => '', 'issue' => '', 'pages' => '',
                        'doi' => '', 'url' => '', 'notes' => '',
                    ];
                    $authors = [];
                } elseif ($tag === 'ER') {
                    if ($current && !empty($current['title'])) {
                        $current['authors'] = implode('; ', $authors);
                        $entries[] = $current;
                    }
                    $current = null;
                    $authors = [];
                } elseif ($current !== null) {
                    switch ($tag) {
                        case 'TI': case 'T1': $current['title'] = $value; break;
                        case 'AU': case 'A1': $authors[] = $value; break;
                        case 'PY': case 'Y1': case 'DA': $current['date'] = $value; break;
                        case 'PB': $current['publisher'] = $value; break;
                        case 'JO': case 'JF': case 'T2': $current['container_title'] = $value; break;
                        case 'VL': $current['volume'] = $value; break;
                        case 'IS': $current['issue'] = $value; break;
                        case 'SP': $current['pages'] = $value; break;
                        case 'EP': $current['pages'] .= '-' . $value; break;
                        case 'DO': $current['doi'] = $value; break;
                        case 'UR': case 'L1': $current['url'] = $value; break;
                        case 'N1': $current['notes'] = $value; break;
                    }
                }
            }
        }

        // Handle case where file doesn't end with ER
        if ($current && !empty($current['title'])) {
            $current['authors'] = implode('; ', $authors);
            $entries[] = $current;
        }

        return $entries;
    }

    // =========================================================================
    // CITATION AUTO-GENERATION
    // =========================================================================

    /**
     * Auto-generate a citation from a record.
     */
    public function generateCitationFromRecord(int $objectId, string $style = 'chicago'): ?string
    {
        $record = DB::table('information_object_i18n as ioi')
            ->leftJoin('information_object as io', 'ioi.id', '=', 'io.id')
            ->leftJoin('repository_i18n as ri', function ($join) {
                $join->on('io.repository_id', '=', 'ri.id')->where('ri.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('ioi.id', $objectId)
            ->where('ioi.culture', 'en')
            ->select('ioi.title', 'io.repository_id', 'ri.authorized_form_of_name as repository_name',
                     'ioi.date as date_display', 'ioi.extent_and_medium', 'ioi.identifier as ref_code',
                     'slug.slug')
            ->first();

        if (!$record) {
            return null;
        }

        $formatters = [
            'chicago' => [$this, 'formatChicago'],
            'harvard' => [$this, 'formatHarvard'],
            'turabian' => [$this, 'formatTurabian'],
            'isadg' => [$this, 'formatIsadg'],
        ];

        $formatter = $formatters[$style] ?? $formatters['chicago'];
        return call_user_func($formatter, $record);
    }

    /**
     * Generate citations for multiple records.
     */
    public function generateCitationsFromRecords(array $objectIds, string $style = 'chicago'): array
    {
        $citations = [];
        foreach ($objectIds as $id) {
            $citation = $this->generateCitationFromRecord($id, $style);
            if ($citation) {
                $citations[$id] = $citation;
            }
        }
        return $citations;
    }

    /**
     * Get available citation styles.
     */
    public function getCitationStyles(): array
    {
        return [
            'chicago' => 'Chicago Manual of Style (17th ed.)',
            'harvard' => 'Harvard Referencing',
            'turabian' => 'Turabian / Chicago Notes-Bibliography',
            'isadg' => 'ISAD(G) Reference',
        ];
    }

    protected function formatChicago(object $record): string
    {
        // Chicago Manual of Style format for archival sources
        $parts = [];
        if ($record->title) $parts[] = '"' . $record->title . '"';
        if ($record->date_display) $parts[] = $record->date_display;
        if ($record->ref_code) $parts[] = $record->ref_code;
        if ($record->repository_name) $parts[] = $record->repository_name;
        return implode(', ', $parts) . '.';
    }

    protected function formatHarvard(object $record): string
    {
        // Harvard format for archival sources
        $parts = [];
        if ($record->repository_name) $parts[] = $record->repository_name;
        $year = $record->date_display ? preg_replace('/[^0-9\-]/', '', substr($record->date_display, 0, 4)) : 'n.d.';
        $parts[] = '(' . $year . ')';
        if ($record->title) $parts[] = '\'' . $record->title . '\'';
        if ($record->ref_code) $parts[] = '[' . $record->ref_code . ']';
        return implode(' ', $parts) . '.';
    }

    protected function formatTurabian(object $record): string
    {
        // Turabian / Chicago Notes-Bibliography variant
        $parts = [];
        if ($record->title) $parts[] = '"' . $record->title . ',"';
        if ($record->date_display) $parts[] = $record->date_display . ',';
        if ($record->ref_code) $parts[] = $record->ref_code . ',';
        if ($record->repository_name) $parts[] = $record->repository_name;
        return implode(' ', $parts) . '.';
    }

    protected function formatIsadg(object $record): string
    {
        // ISAD(G) standard reference format
        $parts = [];
        if ($record->repository_name) $parts[] = $record->repository_name;
        if ($record->ref_code) $parts[] = $record->ref_code;
        if ($record->title) $parts[] = $record->title;
        if ($record->date_display) $parts[] = $record->date_display;
        if ($record->extent_and_medium) $parts[] = $record->extent_and_medium;
        return implode('. ', $parts) . '.';
    }
}
