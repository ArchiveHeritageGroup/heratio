<?php

/**
 * CitationController - "Cite this record" bibliographic export.
 *
 * A small, self-contained citation surface for ONE published archival record,
 * so a researcher can drop a reference straight into a reference manager
 * (Zotero, Mendeley, EndNote, a LaTeX bibliography, a CSL-aware tool) without
 * copying fields by hand:
 *
 *   GET /cite/{idOrSlug}        - an HTML "Cite this" page: a formatted
 *                                 reference plus copy buttons and download links
 *                                 for each machine format (themed, Bootstrap 5).
 *   GET /cite/{idOrSlug}.bib    - BibTeX        (@misc, archival item)
 *   GET /cite/{idOrSlug}.ris    - RIS           (TY  - GEN)
 *   GET /cite/{idOrSlug}.json   - CSL-JSON      ("manuscript")
 *   GET /cite/{idOrSlug}.dc.xml - simple Dublin Core / OAI-DC (oai_dc)
 *
 * Field mapping (honest - a field that is absent is OMITTED, never fabricated):
 *   - title       <- information_object_i18n.title
 *   - author(s)   <- the actors linked through the event table (the SAME creator
 *                    resolution EntityController uses); omitted when there are
 *                    none (no invented "Anon").
 *   - year / date <- the event display date, else a start/end span; omitted when
 *                    absent.
 *   - publisher   <- the holding repository's authorised name (an archival
 *                    citation names the holding institution as publisher).
 *   - identifier  <- information_object.identifier (the archival reference code).
 *   - URL         <- url() to the canonical public record page (never a
 *                    hardcoded host), so a fresh install on its own domain cites
 *                    its own URLs.
 *   - item type   <- archival: BibTeX @misc, RIS TY=GEN, CSL "manuscript",
 *                    DC type "Text" / "Collection" for a fonds/collection/series.
 *
 * Resolution + gate REUSED, not reinvented: resolve() is the same slug ->
 * information_object join + published-only gate as
 * AhgApi\Controllers\EntityController::loadNode()
 * (status.type_id=158, status_id=160 = Published; synthetic root id=1 excluded;
 * a schema variance yields null, not an exception). A numeric token is accepted
 * as the record's information_object id so the surface works before a slug is
 * known. An unknown / unpublished / root record yields a CLEAN 404 in every
 * format (HTML page, or a format-appropriate comment / empty document) - never a
 * 500, never a leak of a draft.
 *
 * Safe + neutral: read-only (SELECT only; no writes, no DDL); the machine
 * formats carry permissive open CORS (any tool may fetch them); every emitted
 * string is escaped for its format (BibTeX brace/dollar/special escaping, RIS
 * CRLF-delimited tags with newlines stripped from values, XML entity-escaped),
 * so a hostile title can never inject into the artifact. Citation formats are
 * international standards (BibTeX / RIS / CSL / Dublin Core); no jurisdiction or
 * locale assumptions.
 *
 * CATCH-ALL SAFETY: every path here is MULTI-SEGMENT ("/cite/..."), and the
 * machine variants are DOTTED (".bib", ".ris", ".json", ".dc.xml"). The
 * single-segment /{slug} archival-record catch-all (ahg-information-object-
 * manage, constraint '[a-z0-9][a-z0-9-]*$' - ONE segment, no slash, no dot) can
 * therefore never capture them. The dotted-format routes are registered BEFORE
 * the bare HTML route, and the {idOrSlug} matcher allows the slug grammar, so
 * the format suffixes bind as formats, never as part of a slug.
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

namespace AhgApi\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CitationController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object id, always excluded. */
    private const ROOT_ID = 1;

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    /**
     * OPTIONS preflight for the citation endpoints (machine formats are
     * CORS-open).
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    // -----------------------------------------------------------------
    // HTML "Cite this" page
    // -----------------------------------------------------------------

    /**
     * GET /cite/{idOrSlug}
     *
     * The human "Cite this" page: a formatted reference, copy buttons, and a
     * download link for each machine format. Themed (Bootstrap 5 central theme).
     * An unknown / unpublished record yields a clean themed 404.
     */
    public function show(Request $request, string $idOrSlug): Response
    {
        $rec = $this->resolve($idOrSlug);
        if ($rec === null) {
            return $this->withCors(
                response()->view('ahg-api::cite.not-found', [], 404)
            );
        }

        return $this->withCors(response()->view('ahg-api::cite.show', [
            'rec' => $rec,
            'plain' => $this->plainCitation($rec),
            'bibtex' => $this->bibtex($rec),
            'ris' => $this->ris($rec),
            'csl' => $this->cslJson($rec, true),
            'dc' => $this->dublinCore($rec),
            'urls' => [
                'record' => $rec['record_url'],
                'bib' => $this->formatUrl($rec['slug'], 'bib'),
                'ris' => $this->formatUrl($rec['slug'], 'ris'),
                'json' => $this->formatUrl($rec['slug'], 'json'),
                'dc' => $this->formatUrl($rec['slug'], 'dc.xml'),
            ],
        ]));
    }

    // -----------------------------------------------------------------
    // Machine formats (each a downloadable artifact, correct Content-Type)
    // -----------------------------------------------------------------

    /** GET /cite/{idOrSlug}.bib - BibTeX. */
    public function bib(Request $request, string $idOrSlug): Response
    {
        $rec = $this->resolve($idOrSlug);
        if ($rec === null) {
            return $this->machineNotFound('bib', $idOrSlug);
        }

        return $this->downloadResponse(
            $this->bibtex($rec),
            'application/x-bibtex; charset=utf-8',
            $this->filename($rec, 'bib')
        );
    }

    /** GET /cite/{idOrSlug}.ris - RIS. */
    public function risExport(Request $request, string $idOrSlug): Response
    {
        $rec = $this->resolve($idOrSlug);
        if ($rec === null) {
            return $this->machineNotFound('ris', $idOrSlug);
        }

        return $this->downloadResponse(
            $this->ris($rec),
            'application/x-research-info-systems; charset=utf-8',
            $this->filename($rec, 'ris')
        );
    }

    /** GET /cite/{idOrSlug}.json - CSL-JSON. */
    public function csl(Request $request, string $idOrSlug): Response
    {
        $rec = $this->resolve($idOrSlug);
        if ($rec === null) {
            return $this->machineNotFound('json', $idOrSlug);
        }

        return $this->downloadResponse(
            $this->cslJson($rec),
            'application/vnd.citationstyles.csl+json; charset=utf-8',
            $this->filename($rec, 'json')
        );
    }

    /** GET /cite/{idOrSlug}.dc.xml - simple Dublin Core (OAI-DC). */
    public function dc(Request $request, string $idOrSlug): Response
    {
        $rec = $this->resolve($idOrSlug);
        if ($rec === null) {
            return $this->machineNotFound('dc.xml', $idOrSlug);
        }

        return $this->downloadResponse(
            $this->dublinCore($rec),
            'application/xml; charset=utf-8',
            $this->filename($rec, 'dc.xml')
        );
    }

    // -----------------------------------------------------------------
    // Resolution + publication-status gate (REUSED from EntityController)
    // -----------------------------------------------------------------

    /**
     * Resolve an id-or-slug to its published record, enforcing the SAME
     * published-only gate as EntityController. A purely numeric token is treated
     * as the information_object id; anything else is a slug. Returns null for an
     * unknown OR unpublished record (never leaks a draft), and never throws (a
     * schema variance yields null).
     *
     * @return array<string,mixed>|null
     */
    protected function resolve(string $idOrSlug): ?array
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('slug')) {
                return null;
            }

            $query = DB::table('information_object as io')
                ->join('slug as s', 's.object_id', '=', 'io.id')
                ->join('information_object_i18n as i18n', function ($j) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
                })
                ->leftJoin('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION);
                })
                ->where('io.id', '!=', self::ROOT_ID);

            // Numeric token -> the information_object id; otherwise a slug.
            if (ctype_digit($idOrSlug)) {
                $query->where('io.id', (int) $idOrSlug);
            } else {
                $query->where('s.slug', $idOrSlug);
            }

            $row = $query->select(
                'io.id',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                's.slug',
                'i18n.title',
                'i18n.scope_and_content',
                'st.status_id'
            )->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row) {
            return null;
        }

        // Published-only gate, matching the rest of the public v1 API.
        if ((int) $row->status_id !== self::STATUS_PUBLISHED) {
            return null;
        }

        $date = $this->primaryDate((int) $row->id);

        return [
            'id' => (int) $row->id,
            'slug' => (string) $row->slug,
            'identifier' => $row->identifier !== null ? (string) $row->identifier : '',
            'title' => ($row->title !== null && $row->title !== '') ? (string) $row->title : '[Untitled]',
            'level' => $this->termName($row->level_of_description_id),
            'creators' => $this->creators((int) $row->id),
            'date' => $date,
            'year' => $this->yearFrom($date),
            'publisher' => $this->publisher($row->repository_id),
            'record_url' => $this->recordPublicUrl((string) $row->slug),
        ];
    }

    /**
     * Creator names (actors linked via the event table) - the SAME resolution as
     * EntityController::creators(). dcterms:creator / author.
     *
     * @return array<int,string>
     */
    protected function creators(int $objectId): array
    {
        try {
            return DB::table('event')
                ->join('actor_i18n', function ($j) {
                    $j->on('event.actor_id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', $this->culture);
                })
                ->where('event.object_id', $objectId)
                ->whereNotNull('event.actor_id')
                ->whereNotNull('actor_i18n.authorized_form_of_name')
                ->distinct()
                ->pluck('actor_i18n.authorized_form_of_name')
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The single most representative display date for the citation (the event
     * display date, else a start/end span). Best-effort; '' when absent.
     */
    protected function primaryDate(int $objectId): string
    {
        try {
            $rows = DB::table('event as e')
                ->leftJoin('event_i18n as ei', function ($j) {
                    $j->on('e.id', '=', 'ei.id')->where('ei.culture', $this->culture);
                })
                ->where('e.object_id', $objectId)
                ->select('ei.date as display_date', 'e.start_date', 'e.end_date')
                ->get();

            foreach ($rows as $r) {
                if (! empty($r->display_date)) {
                    return trim((string) $r->display_date);
                }
            }
            foreach ($rows as $r) {
                if (! empty($r->start_date)) {
                    return $this->trimDate((string) $r->start_date)
                        .(! empty($r->end_date) ? '/'.$this->trimDate((string) $r->end_date) : '');
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
    }

    /**
     * The holding repository's authorised name (the archival "publisher").
     */
    protected function publisher($repositoryId): string
    {
        if (empty($repositoryId)) {
            return '';
        }

        try {
            $name = DB::table('repository as r')
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('r.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->where('r.id', (int) $repositoryId)
                ->value('ai.authorized_form_of_name');

            return $name ? trim((string) $name) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function termName($termId): ?string
    {
        if (empty($termId)) {
            return null;
        }

        try {
            return DB::table('term_i18n')
                ->where('id', (int) $termId)
                ->where('culture', $this->culture)
                ->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Format builders
    // -----------------------------------------------------------------

    /**
     * A plain, human-readable reference for the page header (a neutral archival
     * citation order: Creator(s), Title, [date], identifier, Repository, URL).
     * Honest: every absent field is silently dropped.
     */
    protected function plainCitation(array $rec): string
    {
        $parts = [];

        if (! empty($rec['creators'])) {
            $parts[] = $this->joinNames($rec['creators']).'.';
        }
        $parts[] = $rec['title'].'.';
        if (! empty($rec['date'])) {
            $parts[] = $rec['date'].'.';
        }
        if (! empty($rec['identifier'])) {
            $parts[] = $rec['identifier'].'.';
        }
        if (! empty($rec['publisher'])) {
            $parts[] = $rec['publisher'].'.';
        }
        $parts[] = $rec['record_url'];

        return trim(implode(' ', $parts));
    }

    /**
     * BibTeX. Archival material maps to @misc with a holding note; a
     * fonds/collection/series carries type="Collection". Every value is
     * BibTeX-escaped and wrapped in braces so the title's case and any special
     * character is preserved and cannot break out of the field.
     */
    protected function bibtex(array $rec): string
    {
        $key = $this->citationKey($rec);
        $lines = ['@misc{'.$key.','];
        $field = fn (string $name, string $value) => '  '.$name.' = {'.$this->bibtexEscape($value).'},';

        $lines[] = $field('title', $rec['title']);
        if (! empty($rec['creators'])) {
            // BibTeX author list is " and "-delimited; each name escaped once.
            $authors = implode(' and ', array_map(fn ($n) => $this->bibtexEscape($n), $rec['creators']));
            $lines[] = '  author = {'.$authors.'},';
        }
        if (! empty($rec['year'])) {
            $lines[] = $field('year', $rec['year']);
        }
        if (! empty($rec['date']) && $rec['date'] !== ($rec['year'] ?? '')) {
            $lines[] = $field('note', 'Date: '.$rec['date']);
        }
        if (! empty($rec['publisher'])) {
            $lines[] = $field('publisher', $rec['publisher']);
            $lines[] = $field('howpublished', 'Archival material held by '.$rec['publisher']);
        } else {
            $lines[] = $field('howpublished', 'Archival material');
        }
        if (! empty($rec['identifier'])) {
            $lines[] = $field('number', $rec['identifier']);
        }
        $lines[] = $field('url', $rec['record_url']);
        if ($this->isCollection($rec['level'])) {
            $lines[] = $field('type', 'Collection');
        }

        // Strip the trailing comma off the final field for clean BibTeX.
        $last = array_pop($lines);
        $lines[] = rtrim($last, ',');
        $lines[] = '}';

        return implode("\n", $lines)."\n";
    }

    /**
     * RIS. Archival material -> TY  - GEN (generic; the most widely accepted RIS
     * type for archival items across reference managers). CRLF line endings per
     * the RIS spec; every value has newlines stripped so a tag can never be
     * forged from a title.
     */
    protected function ris(array $rec): string
    {
        $tags = [];
        $tags[] = ['TY', 'GEN'];
        $tags[] = ['TI', $rec['title']];
        foreach ($rec['creators'] as $name) {
            $tags[] = ['AU', $name];
        }
        if (! empty($rec['year'])) {
            $tags[] = ['PY', $rec['year']];
        }
        if (! empty($rec['date'])) {
            $tags[] = ['DA', $rec['date']];
        }
        if (! empty($rec['publisher'])) {
            $tags[] = ['PB', $rec['publisher']];
        }
        if (! empty($rec['identifier'])) {
            $tags[] = ['CN', $rec['identifier']]; // call number / reference code
        }
        $tags[] = ['UR', $rec['record_url']];
        $tags[] = ['ER', ''];

        $out = '';
        foreach ($tags as [$tag, $value]) {
            $out .= $tag.'  - '.$this->risValue((string) $value)."\r\n";
        }

        return $out;
    }

    /**
     * CSL-JSON (the citeproc data model). Archival material -> "manuscript"
     * (a single item) or "collection" for a fonds/collection/series. Authors are
     * emitted as {literal: "..."} so an organisational or unparsed personal name
     * is preserved verbatim (no risky given/family guessing).
     *
     * @return string  pretty-printed when $pretty, else compact (valid JSON either way)
     */
    protected function cslJson(array $rec, bool $pretty = false): string
    {
        $item = [
            'id' => $this->citationKey($rec),
            'type' => $this->isCollection($rec['level']) ? 'collection' : 'manuscript',
            'title' => $rec['title'],
            'URL' => $rec['record_url'],
        ];

        if (! empty($rec['creators'])) {
            $item['author'] = array_map(
                fn ($n) => ['literal' => $n],
                array_values($rec['creators'])
            );
        }
        if (! empty($rec['year'])) {
            $item['issued'] = ['date-parts' => [[(int) $rec['year']]]];
        }
        if (! empty($rec['date'])) {
            $item['issued'] = ($item['issued'] ?? []) + ['raw' => $rec['date']];
        }
        if (! empty($rec['publisher'])) {
            $item['publisher'] = $rec['publisher'];
            $item['archive'] = $rec['publisher'];
        }
        if (! empty($rec['identifier'])) {
            $item['call-number'] = $rec['identifier'];
        }

        // CSL-JSON is an ARRAY of items.
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0);

        return (string) json_encode([$item], $flags);
    }

    /**
     * Simple Dublin Core in the OAI-DC wrapper - the same metadata shape the
     * platform already serves over OAI-PMH, here as a single downloadable file.
     * Every value is XML-entity-escaped, so a title can never inject markup.
     */
    protected function dublinCore(array $rec): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $x = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $x .= '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"'."\n";
        $x .= '           xmlns:dc="http://purl.org/dc/elements/1.1/"'."\n";
        $x .= '           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'."\n";
        $x .= '           xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ '
            .'http://www.openarchives.org/OAI/2.0/oai_dc.xsd">'."\n";

        $x .= '  <dc:title>'.$e($rec['title']).'</dc:title>'."\n";
        foreach ($rec['creators'] as $name) {
            $x .= '  <dc:creator>'.$e($name).'</dc:creator>'."\n";
        }
        if (! empty($rec['date'])) {
            $x .= '  <dc:date>'.$e($rec['date']).'</dc:date>'."\n";
        }
        if (! empty($rec['publisher'])) {
            $x .= '  <dc:publisher>'.$e($rec['publisher']).'</dc:publisher>'."\n";
        }
        if (! empty($rec['identifier'])) {
            $x .= '  <dc:identifier>'.$e($rec['identifier']).'</dc:identifier>'."\n";
        }
        // The dereferenceable URL is also an identifier.
        $x .= '  <dc:identifier>'.$e($rec['record_url']).'</dc:identifier>'."\n";
        $x .= '  <dc:type>'.$e($this->isCollection($rec['level']) ? 'Collection' : 'Text').'</dc:type>'."\n";
        $x .= '  <dc:format>application/xml</dc:format>'."\n";

        $x .= '</oai_dc:dc>'."\n";

        return $x;
    }

    // -----------------------------------------------------------------
    // Escaping helpers (no injection from titles/names)
    // -----------------------------------------------------------------

    /**
     * Escape a value for a BibTeX braced field. The characters that carry
     * meaning in BibTeX/(La)TeX are backslash-escaped; whitespace collapses to a
     * single space. A title can never break out of its field.
     */
    protected function bibtexEscape(string $value): string
    {
        $value = (string) preg_replace('/\s+/u', ' ', trim($value));

        // Backslash first so the escapes we add next are not re-escaped.
        $map = [
            '\\' => '\\textbackslash{}',
            '{' => '\\{',
            '}' => '\\}',
            '$' => '\\$',
            '&' => '\\&',
            '%' => '\\%',
            '#' => '\\#',
            '_' => '\\_',
            '~' => '\\textasciitilde{}',
            '^' => '\\textasciicircum{}',
        ];

        return strtr($value, $map);
    }

    /**
     * A RIS field value: newlines stripped (a tag is line-delimited, so a raw
     * newline would forge a new tag) and trimmed.
     */
    protected function risValue(string $value): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $value));
    }

    // -----------------------------------------------------------------
    // Small helpers
    // -----------------------------------------------------------------

    /**
     * A stable BibTeX/CSL citation key: a slug-derived ASCII token (so it is a
     * valid BibTeX key), suffixed with the year when present.
     */
    protected function citationKey(array $rec): string
    {
        $base = $rec['slug'] !== '' ? $rec['slug'] : ('record-'.$rec['id']);
        $base = (string) preg_replace('/[^A-Za-z0-9]+/', '', $base);
        if ($base === '') {
            $base = 'record'.$rec['id'];
        }
        if (! empty($rec['year'])) {
            $base .= $rec['year'];
        }

        return $base;
    }

    protected function joinNames(array $names): string
    {
        $names = array_values(array_filter(array_map('trim', $names)));
        if (count($names) <= 1) {
            return $names[0] ?? '';
        }
        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }

    protected function isCollection(?string $level): bool
    {
        $l = strtolower((string) $level);

        return str_contains($l, 'fonds') || str_contains($l, 'collection') || str_contains($l, 'series');
    }

    /**
     * Pull a 4-digit year out of a display/ISO date string, else null.
     */
    protected function yearFrom(string $date): ?string
    {
        if ($date === '') {
            return null;
        }
        if (preg_match('/(\d{4})/', $date, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Trim AtoM-style "-00" month/day placeholders so "1923-00-00" reads "1923".
     */
    protected function trimDate(string $value): string
    {
        $value = trim($value);
        $value = (string) preg_replace('/-00(-00)?$/', '', $value);

        return (string) preg_replace('/-00$/', '', $value);
    }

    protected function recordPublicUrl(string $slug): string
    {
        return rtrim((string) url('/'), '/').'/'.ltrim($slug, '/');
    }

    /**
     * The citation download URL for a given format suffix (bib|ris|json|dc.xml),
     * built from url() so no host is hardcoded.
     */
    protected function formatUrl(string $slug, string $suffix): string
    {
        return rtrim((string) url('/'), '/').'/cite/'.ltrim($slug, '/').'.'.$suffix;
    }

    /**
     * A safe download filename: the slug (or id) plus the format extension.
     */
    protected function filename(array $rec, string $ext): string
    {
        $base = $rec['slug'] !== '' ? $rec['slug'] : ('record-'.$rec['id']);
        $base = (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base);
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'citation';
        }

        return $base.'.'.$ext;
    }

    // -----------------------------------------------------------------
    // Responses + CORS
    // -----------------------------------------------------------------

    /**
     * A downloadable machine artifact with the right Content-Type, an inline
     * Content-Disposition (so a browser shows it but a tool can save it), and
     * permissive open CORS.
     */
    protected function downloadResponse(string $body, string $contentType, string $filename): Response
    {
        return $this->withCors(response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]));
    }

    /**
     * A clean 404 for a machine format: a format-appropriate empty/comment
     * document with the correct Content-Type, never a 500, never a draft leak.
     */
    protected function machineNotFound(string $suffix, string $idOrSlug): Response
    {
        $safe = trim((string) preg_replace('/[\r\n]+/', ' ', $idOrSlug));

        if ($suffix === 'bib') {
            return $this->withCors(response(
                '% Not Found: '.$safe.' is not a published record.'."\n",
                404,
                ['Content-Type' => 'application/x-bibtex; charset=utf-8']
            ));
        }
        if ($suffix === 'ris') {
            return $this->withCors(response(
                "TY  - GEN\r\nER  - \r\n",
                404,
                ['Content-Type' => 'application/x-research-info-systems; charset=utf-8']
            ));
        }
        if ($suffix === 'json') {
            return $this->withCors(response(
                (string) json_encode([
                    'error' => 'Not Found',
                    'message' => 'No published record for '.$safe.'.',
                ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                404,
                ['Content-Type' => 'application/json; charset=utf-8']
            ));
        }

        // dc.xml
        $e = htmlspecialchars($safe, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $this->withCors(response(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<!-- Not Found: '.$e.' is not a published record. -->'."\n"
            .'<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"'
            .' xmlns:dc="http://purl.org/dc/elements/1.1/"></oai_dc:dc>'."\n",
            404,
            ['Content-Type' => 'application/xml; charset=utf-8']
        ));
    }

    /**
     * Apply permissive open CORS headers (the machine formats are meant to be
     * fetched by any reference-manager / tool from any origin).
     */
    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('Vary', 'Accept');
        $response->headers->set('X-Open-Data', 'true');

        return $response;
    }
}
