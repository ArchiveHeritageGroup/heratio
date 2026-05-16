<?php

/**
 * CitationService - Service for Heratio
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

use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

/**
 * CitationService
 *
 * Per-record citation export to the formats academic researchers paste into
 * Zotero / Mendeley / EndNote / etc. The bibliography-level multi-record
 * exporters live in BibliographyService; this one targets the single-record
 * "Copy in citation manager format" picker on cite.blade.php.
 *
 * Formats:
 *   ris      - tagged RIS (paste into Zotero, Mendeley, EndNote)
 *   bibtex   - BibTeX (paste into LaTeX, JabRef, Mendeley)
 *   endnote  - EndNote tagged XML (alternative to RIS for EndNote)
 *   apa      - APA 7 archival format (plain text)
 *   mla      - MLA 9 archival format (plain text)
 *   chicago  - Chicago Manual of Style 17 Notes-Bibliography (plain text)
 */
class CitationService
{
    public const FORMATS = [
        'ris'     => 'RIS (Zotero / Mendeley / EndNote)',
        'bibtex'  => 'BibTeX (LaTeX, JabRef)',
        'endnote' => 'EndNote XML',
        'apa'     => 'APA 7',
        'mla'     => 'MLA 9',
        'chicago' => 'Chicago 17 (Notes-Bibliography)',
    ];

    public function export(int $objectId, string $format): array
    {
        $record = $this->loadRecord($objectId);
        if (!$record) {
            return ['error' => 'Record not found'];
        }

        $body = match (strtolower($format)) {
            'ris'     => $this->toRis($record),
            'bibtex'  => $this->toBibTeX($record),
            'endnote' => $this->toEndNoteXml($record),
            'apa'     => $this->toApa($record),
            'mla'     => $this->toMla($record),
            'chicago' => $this->toChicago($record),
            default   => null,
        };

        if ($body === null) {
            return ['error' => 'Unsupported format'];
        }

        return [
            'format'   => $format,
            'label'    => self::FORMATS[$format] ?? $format,
            'body'     => $body,
            'filename' => $this->filenameFor($record, $format),
            'mime'     => $this->mimeFor($format),
        ];
    }

    private function loadRecord(int $objectId): ?object
    {
        $row = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', app()->getLocale());
            })
            ->leftJoin('actor_i18n as repo', function ($j) {
                $j->on('io.repository_id', '=', 'repo.id')->where('repo.culture', '=', app()->getLocale());
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $objectId)
            ->select(
                'io.id',
                'io.identifier',
                'ioi.title',
                'ioi.scope_and_content',
                'ioi.extent_and_medium',
                'repo.authorized_form_of_name as repository_name',
                'slug.slug'
            )
            ->first();

        if (!$row) return null;

        $creators = DB::table('event as e')
            ->join('actor_i18n as ai', function ($j) {
                $j->on('e.actor_id', '=', 'ai.id')->where('ai.culture', '=', app()->getLocale());
            })
            ->where('e.object_id', $objectId)
            ->where('e.type_id', 111) // creation - see TermId::EVENT_TYPE_CREATION
            ->pluck('ai.authorized_form_of_name')
            ->toArray();

        $dateRow = DB::table('event as e')
            ->leftJoin('event_i18n as ei', function ($j) {
                $j->on('e.id', '=', 'ei.id')->where('ei.culture', '=', app()->getLocale());
            })
            ->where('e.object_id', $objectId)
            ->select('e.start_date', 'ei.date as date_display')
            ->first();

        $row->creators     = $creators;
        $row->date_display = $dateRow->date_display ?? null;
        $row->start_date   = $dateRow->start_date ?? null;
        $row->year         = $this->extractYear($row->date_display ?? null) ?? $this->extractYear($row->start_date ?? null);
        $row->url          = rtrim(config('app.url', ''), '/') . '/' . ($row->slug ?? $row->id);
        $row->accessed_iso = date('Y-m-d');
        $row->accessed_long = date('j F Y');

        return $row;
    }

    private function extractYear(?string $s): ?string
    {
        if (!$s) return null;
        return preg_match('/(\d{4})/', $s, $m) ? $m[1] : null;
    }

    private function authorsListed(object $r): string
    {
        if (!empty($r->creators)) {
            return implode('; ', $r->creators);
        }
        return $r->repository_name ?: 'Anon';
    }

    private function authorsApa(object $r): string
    {
        if (empty($r->creators)) {
            return $r->repository_name ?: 'Anon.';
        }
        $parts = [];
        foreach ($r->creators as $c) {
            $bits = explode(',', $c, 2);
            if (count($bits) === 2) {
                $parts[] = trim($bits[0]) . ', ' . substr(trim($bits[1]), 0, 1) . '.';
            } else {
                $parts[] = trim($c);
            }
        }
        return implode(', ', $parts);
    }

    private function authorsMla(object $r): string
    {
        if (empty($r->creators)) {
            return $r->repository_name ?: 'Anon.';
        }
        return $r->creators[0];
    }

    // ─── Formats ────────────────────────────────────────────────────────

    private function toRis(object $r): string
    {
        $lines = ['TY  - UNPB'];
        if ($r->title) $lines[] = 'TI  - ' . $r->title;
        foreach (($r->creators ?? []) as $a) {
            $lines[] = 'AU  - ' . trim($a);
        }
        if ($r->year)              $lines[] = 'PY  - ' . $r->year;
        if ($r->date_display)      $lines[] = 'DA  - ' . $r->date_display;
        if ($r->repository_name)   $lines[] = 'PB  - ' . $r->repository_name;
        if ($r->identifier)        $lines[] = 'N1  - Reference code: ' . $r->identifier;
        if ($r->extent_and_medium) $lines[] = 'N1  - Extent: ' . $r->extent_and_medium;
        if ($r->url)               $lines[] = 'UR  - ' . $r->url;
        $lines[] = 'Y2  - ' . $r->accessed_iso;
        $lines[] = 'ER  - ';
        return implode("\r\n", $lines);
    }

    private function toBibTeX(object $r): string
    {
        $key = 'archive' . $r->id;
        $fields = [];
        if ($r->title)            $fields[] = '  title = {' . $this->escBib($r->title) . '}';
        if (!empty($r->creators)) $fields[] = '  author = {' . $this->escBib(implode(' and ', $r->creators)) . '}';
        if ($r->year)             $fields[] = '  year = {' . $r->year . '}';
        if ($r->repository_name)  $fields[] = '  howpublished = {' . $this->escBib($r->repository_name) . '}';
        if ($r->identifier)       $fields[] = '  note = {Reference code: ' . $this->escBib($r->identifier) . '}';
        if ($r->url)              $fields[] = '  url = {' . $r->url . '}';
        $fields[] = '  urldate = {' . $r->accessed_iso . '}';

        return "@misc{{$key},\n" . implode(",\n", $fields) . "\n}";
    }

    private function escBib(string $s): string
    {
        return str_replace(['&', '%', '$', '#', '_', '{', '}'], ['\&', '\%', '\$', '\#', '\_', '\{', '\}'], $s);
    }

    private function toEndNoteXml(object $r): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xml><records><record/></records></xml>');
        $rec = $xml->records->record;
        $rec->addChild('ref-type', 'Archival Material');
        $contributors = $rec->addChild('contributors');
        $authors = $contributors->addChild('authors');
        if (!empty($r->creators)) {
            foreach ($r->creators as $c) {
                $authors->addChild('author', htmlspecialchars($c));
            }
        } elseif ($r->repository_name) {
            $authors->addChild('author', htmlspecialchars($r->repository_name));
        }
        $titles = $rec->addChild('titles');
        $titles->addChild('title', htmlspecialchars((string) $r->title));
        if ($r->repository_name) $rec->addChild('publisher', htmlspecialchars($r->repository_name));
        if ($r->year)            $rec->addChild('year', $r->year);
        if ($r->identifier)      $rec->addChild('call-num', htmlspecialchars($r->identifier));
        if ($r->url) {
            $urls = $rec->addChild('urls');
            $relurls = $urls->addChild('related-urls');
            $relurls->addChild('url', htmlspecialchars($r->url));
        }
        $rec->addChild('access-date', $r->accessed_iso);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return (string) $dom->saveXML();
    }

    private function toApa(object $r): string
    {
        $parts = [];
        $parts[] = $this->authorsApa($r);
        $parts[] = '(' . ($r->year ?? 'n.d.') . ').';
        if ($r->title) $parts[] = $r->title . '.';
        if ($r->identifier) $parts[] = '[' . $r->identifier . '].';
        if ($r->repository_name) $parts[] = $r->repository_name . '.';
        $parts[] = 'Retrieved ' . $r->accessed_long . ', from ' . $r->url;
        return implode(' ', array_filter($parts));
    }

    private function toMla(object $r): string
    {
        $parts = [];
        $parts[] = $this->authorsMla($r) . '.';
        if ($r->title) $parts[] = '"' . $r->title . '."';
        if ($r->date_display) $parts[] = $r->date_display . ',';
        elseif ($r->year) $parts[] = $r->year . ',';
        if ($r->identifier) $parts[] = $r->identifier . ',';
        if ($r->repository_name) $parts[] = $r->repository_name . ',';
        $parts[] = $r->url . '.';
        $parts[] = 'Accessed ' . $r->accessed_long . '.';
        return implode(' ', array_filter($parts));
    }

    private function toChicago(object $r): string
    {
        $parts = [];
        $parts[] = $this->authorsListed($r) . ',';
        if ($r->title) $parts[] = '"' . $r->title . ',"';
        if ($r->date_display) $parts[] = $r->date_display . ',';
        elseif ($r->year) $parts[] = $r->year . ',';
        if ($r->identifier) $parts[] = $r->identifier . ',';
        if ($r->repository_name) $parts[] = $r->repository_name . ',';
        $parts[] = $r->url . ' (accessed ' . $r->accessed_long . ').';
        return implode(' ', array_filter($parts));
    }

    private function filenameFor(object $r, string $format): string
    {
        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($r->slug ?: ('record-' . $r->id)));
        $ext  = match ($format) {
            'ris'     => 'ris',
            'bibtex'  => 'bib',
            'endnote' => 'xml',
            'apa', 'mla', 'chicago' => 'txt',
            default => 'txt',
        };
        return trim($slug, '-') . '.' . $ext;
    }

    private function mimeFor(string $format): string
    {
        return match ($format) {
            'ris'     => 'application/x-research-info-systems',
            'bibtex'  => 'application/x-bibtex',
            'endnote' => 'application/xml',
            default   => 'text/plain',
        };
    }
}
