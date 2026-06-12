<?php

/**
 * MetsController - a METS export per record.
 *
 * A self-contained, standards-clean METS (Metadata Encoding and Transmission
 * Standard) XML wrapper for ONE published archival record, so the record can be
 * exchanged with other archives and ingested into preservation / repository
 * systems that speak METS:
 *
 *   GET /mets/{idOrSlug}.xml
 *       - a valid METS 1.12 document (Library of Congress) for a single
 *         PUBLISHED record. application/xml (METS profile). CORS-open.
 *         metsHdr (CREATEDATE + agent) + a dmdSec carrying Dublin Core (oai_dc)
 *         descriptive metadata + a fileSec inventorying the record's digital
 *         objects + a physical structMap referencing the files and the record.
 *
 * METS is the standard archival-interchange wrapper: a descriptive-metadata
 * section, a file inventory, and a structure map, all in one document. A
 * receiving system reads the dmdSec for "what is this", the fileSec for "what
 * files are there" (with checksums for fixity), and the structMap for "how do
 * they fit together".
 *
 * Resolution + gate REUSED, not reinvented: resolve() is the same slug ->
 * information_object join + published-only gate as
 * AhgApi\Controllers\EntityController::loadNode(),
 * AhgApi\Controllers\CitationController::resolve() and
 * AhgApi\Controllers\IiifPresentationController::resolve()
 * (status.type_id=158, status_id=160 = Published; synthetic root id=1 excluded;
 * a numeric token is accepted as the information_object id; a schema variance
 * yields null, not an exception). The descriptive-field gathering (title,
 * identifier, date via event, creators via event+actor_i18n, scope, level,
 * repository-as-publisher) mirrors CitationController; the digital-object
 * gathering + the IIIF Image API identifier construction mirror
 * IiifPresentationController. An unknown / unpublished / root record yields a
 * CLEAN 404 XML - never a 500, never a leak of a draft.
 *
 * Dublin Core REUSED: the dmdSec wraps the SAME simple Dublin Core (oai_dc)
 * shape the cite endpoint's .dc.xml and the OAI-PMH endpoint already serve
 * (dc:title / dc:creator / dc:date / dc:identifier / dc:publisher / dc:type),
 * so the descriptive metadata is consistent across every surface.
 *
 * File URLs DERIVED, never hardcoded: every FLocat xlink:href is built from
 * url('/') (the request host, exactly like the rest of the open-data surfaces),
 * so a fresh install on its own domain emits its own file URLs. The public file
 * URL follows the deployed nginx '/uploads/' alias (digital_object.path +
 * digital_object.name), and a IIIF Image API service URL is added as a second
 * FLocat for any image (the SAME '/' -> '_SL_' Cantaloupe identifier the
 * deployed viewer and IiifPresentationController build).
 *
 * Empty-but-valid: a published record with NO digital objects yields a valid
 * METS with the metsHdr + dmdSec + a minimal (empty) fileSec + a structMap that
 * still references the record div (never a 500), so a receiving system always
 * gets a well-formed document.
 *
 * CATCH-ALL SAFETY: the route is MULTI-SEGMENT and DOTTED
 * ("/mets/{idOrSlug}.xml"). The single-segment /{slug} archival-record catch-all
 * (ahg-information-object-manage, constraint '[a-z0-9][a-z0-9-]*$' - ONE segment,
 * no slash, no dot) can therefore NEVER capture it, so a normal record slug
 * still resolves. The {idOrSlug} matcher allows the slug grammar (including
 * multi-segment slugs with slashes), with the trailing ".xml" pinned as a
 * literal so the record token can never absorb the suffix.
 *
 * Safe + neutral: read-only (SELECT only; no writes, no DDL, no new table);
 * permissive open CORS (any interchange / ingest tool may fetch it); every
 * emitted value is XML-entity-escaped, so a hostile title can never inject
 * markup. METS + Dublin Core + xlink are international standards; no
 * jurisdiction or locale assumptions.
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

class MetsController extends Controller
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
     * OPTIONS preflight for the METS endpoint (CORS-open).
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /mets/{idOrSlug}.xml
     *
     * A valid METS 1.12 document for one published record. An unknown /
     * unpublished / root record yields a clean 404 XML; a record with no digital
     * objects yields a valid METS with an empty fileSec (never a 500).
     */
    public function show(Request $request, string $idOrSlug): Response
    {
        $rec = $this->resolve($idOrSlug);
        if ($rec === null) {
            return $this->notFound($idOrSlug);
        }

        return $this->withCors(response(
            $this->buildMets($rec),
            200,
            ['Content-Type' => 'application/xml; charset=utf-8']
        ));
    }

    // -----------------------------------------------------------------
    // METS assembly (METS 1.12, LoC)
    // -----------------------------------------------------------------

    /**
     * Build the full METS 1.12 document for a resolved record:
     * metsHdr + dmdSec (oai_dc Dublin Core) + fileSec + physical structMap.
     *
     * @param  array<string,mixed>  $rec
     */
    protected function buildMets(array $rec): string
    {
        $files = $this->digitalObjects((int) $rec['id']);

        // Stable ids referenced across fileSec and structMap.
        $dmdId = 'dmd-'.$rec['id'];
        $fileIds = [];
        foreach ($files as $i => $f) {
            $fileIds[$i] = 'file-'.$rec['id'].'-'.($i + 1);
        }

        $objId = htmlspecialchars(
            $rec['identifier'] !== '' ? $rec['identifier'] : ('record-'.$rec['id']),
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );
        $label = htmlspecialchars(
            $rec['title'] !== '' ? $rec['title'] : '[Untitled]',
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );
        $metsUrl = htmlspecialchars($this->metsUrl($rec['slug']), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $x = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $x .= '<mets:mets xmlns:mets="http://www.loc.gov/METS/"'."\n";
        $x .= '           xmlns:xlink="http://www.w3.org/1999/xlink"'."\n";
        $x .= '           xmlns:dc="http://purl.org/dc/elements/1.1/"'."\n";
        $x .= '           xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"'."\n";
        $x .= '           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'."\n";
        $x .= '           xsi:schemaLocation="http://www.loc.gov/METS/ '
            .'http://www.loc.gov/standards/mets/version1121/mets.xsd"'."\n";
        $x .= '           OBJID="'.$objId.'"'."\n";
        $x .= '           LABEL="'.$label.'"'."\n";
        $x .= '           TYPE="'.($this->isCollection($rec['level']) ? 'Collection' : 'Archival Item').'"'."\n";
        $x .= '           PROFILE="'.$metsUrl.'">'."\n";

        $x .= $this->metsHdr($rec);
        $x .= $this->dmdSec($rec, $dmdId);
        $x .= $this->fileSec($files, $fileIds);
        $x .= $this->structMap($rec, $files, $fileIds, $dmdId, $label);

        $x .= '</mets:mets>'."\n";

        return $x;
    }

    /**
     * The METS header: CREATEDATE (now, ISO-8601) and a CREATOR agent naming the
     * holding repository (or the platform when there is no repository).
     *
     * @param  array<string,mixed>  $rec
     */
    protected function metsHdr(array $rec): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $agentName = $rec['publisher'] !== ''
            ? $rec['publisher']
            : (string) config('app.name', 'Heratio');

        $x = '  <mets:metsHdr CREATEDATE="'.$e(gmdate('Y-m-d\TH:i:s\Z')).'" RECORDSTATUS="Published">'."\n";
        $x .= '    <mets:agent ROLE="CREATOR" TYPE="ORGANIZATION">'."\n";
        $x .= '      <mets:name>'.$e($agentName).'</mets:name>'."\n";
        $x .= '    </mets:agent>'."\n";
        $x .= '  </mets:metsHdr>'."\n";

        return $x;
    }

    /**
     * The descriptive-metadata section: an mdWrap (MDTYPE="DC") carrying simple
     * Dublin Core in the oai_dc wrapper - the SAME shape the cite .dc.xml and the
     * OAI-PMH endpoint serve. Every value XML-entity-escaped.
     *
     * @param  array<string,mixed>  $rec
     */
    protected function dmdSec(array $rec, string $dmdId): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $x = '  <mets:dmdSec ID="'.$e($dmdId).'">'."\n";
        $x .= '    <mets:mdWrap MDTYPE="DC" LABEL="Dublin Core descriptive metadata">'."\n";
        $x .= '      <mets:xmlData>'."\n";
        $x .= '        <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"'
            .' xmlns:dc="http://purl.org/dc/elements/1.1/">'."\n";

        $x .= '          <dc:title>'.$e($rec['title'] !== '' ? $rec['title'] : '[Untitled]').'</dc:title>'."\n";
        foreach ($rec['creators'] as $name) {
            $x .= '          <dc:creator>'.$e($name).'</dc:creator>'."\n";
        }
        if ($rec['date'] !== '') {
            $x .= '          <dc:date>'.$e($rec['date']).'</dc:date>'."\n";
        }
        if ($rec['scope'] !== '') {
            $x .= '          <dc:description>'.$e($rec['scope']).'</dc:description>'."\n";
        }
        if ($rec['publisher'] !== '') {
            $x .= '          <dc:publisher>'.$e($rec['publisher']).'</dc:publisher>'."\n";
        }
        if ($rec['identifier'] !== '') {
            $x .= '          <dc:identifier>'.$e($rec['identifier']).'</dc:identifier>'."\n";
        }
        // The dereferenceable record URL is also an identifier.
        $x .= '          <dc:identifier>'.$e($rec['record_url']).'</dc:identifier>'."\n";
        $x .= '          <dc:type>'.$e($this->isCollection($rec['level']) ? 'Collection' : 'Text').'</dc:type>'."\n";

        $x .= '        </oai_dc:dc>'."\n";
        $x .= '      </mets:xmlData>'."\n";
        $x .= '    </mets:mdWrap>'."\n";
        $x .= '  </mets:dmdSec>'."\n";

        return $x;
    }

    /**
     * The file inventory: one mets:fileGrp (USE="master") with a mets:file per
     * digital object. Each file carries MIMETYPE, SIZE (when present), CHECKSUM +
     * CHECKSUMTYPE (when present), and a primary mets:FLocat (xlink:href) to the
     * public file URL; an image also gets a second FLocat to its IIIF Image API
     * service. A record with no digital objects yields an empty fileGrp (still a
     * valid, well-formed fileSec).
     *
     * @param  array<int,object>  $files
     * @param  array<int,string>  $fileIds
     */
    protected function fileSec(array $files, array $fileIds): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $x = '  <mets:fileSec>'."\n";
        $x .= '    <mets:fileGrp USE="master">'."\n";

        foreach ($files as $i => $f) {
            $attrs = 'ID="'.$e($fileIds[$i]).'"';
            if (($f->mime_type ?? '') !== '') {
                $attrs .= ' MIMETYPE="'.$e($f->mime_type).'"';
            }
            $size = (int) ($f->byte_size ?? 0);
            if ($size > 0) {
                $attrs .= ' SIZE="'.$size.'"';
            }
            if (($f->checksum ?? '') !== '') {
                $attrs .= ' CHECKSUM="'.$e($f->checksum).'"';
                $ctype = $this->checksumType((string) ($f->checksum_type ?? ''));
                if ($ctype !== '') {
                    $attrs .= ' CHECKSUMTYPE="'.$e($ctype).'"';
                }
            }

            $x .= '      <mets:file '.$attrs.'>'."\n";

            $href = $this->fileUrl($f);
            if ($href !== '') {
                $x .= '        <mets:FLocat LOCTYPE="URL" xlink:href="'.$e($href).'"'
                    .($f->name !== null && $f->name !== '' ? ' xlink:title="'.$e($f->name).'"' : '')
                    .'/>'."\n";
            }

            // A IIIF Image API service URL as a second locator for any image.
            $iiif = $this->iiifServiceUrl($f);
            if ($iiif !== '') {
                $x .= '        <mets:FLocat LOCTYPE="URL" xlink:href="'.$e($iiif).'"'
                    .' xlink:title="IIIF Image API service"/>'."\n";
            }

            $x .= '      </mets:file>'."\n";
        }

        $x .= '    </mets:fileGrp>'."\n";
        $x .= '  </mets:fileSec>'."\n";

        return $x;
    }

    /**
     * The physical structure map: a single record-level div (DMDID -> the dmdSec)
     * that nests one fptr per file. With no files the record div still stands on
     * its own (a valid, minimal structMap).
     *
     * @param  array<string,mixed>  $rec
     * @param  array<int,object>  $files
     * @param  array<int,string>  $fileIds
     */
    protected function structMap(array $rec, array $files, array $fileIds, string $dmdId, string $label): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $x = '  <mets:structMap TYPE="physical">'."\n";
        $x .= '    <mets:div TYPE="'.($this->isCollection($rec['level']) ? 'collection' : 'item').'"'
            .' LABEL="'.$label.'" DMDID="'.$e($dmdId).'">'."\n";

        foreach ($files as $i => $f) {
            $childLabel = ($f->name !== null && $f->name !== '')
                ? (string) $f->name
                : ('File '.($i + 1));
            $x .= '      <mets:div TYPE="file" ORDER="'.($i + 1).'" LABEL="'.$e($childLabel).'">'."\n";
            $x .= '        <mets:fptr FILEID="'.$e($fileIds[$i]).'"/>'."\n";
            $x .= '      </mets:div>'."\n";
        }

        $x .= '    </mets:div>'."\n";
        $x .= '  </mets:structMap>'."\n";

        return $x;
    }

    // -----------------------------------------------------------------
    // Resolution + publication-status gate (REUSED from EntityController)
    // -----------------------------------------------------------------

    /**
     * Resolve an id-or-slug to its published record, enforcing the SAME
     * published-only gate as EntityController / CitationController /
     * IiifPresentationController. A purely numeric token is treated as the
     * information_object id; anything else is a slug. Returns null for an unknown
     * OR unpublished record (never leaks a draft), and never throws (a schema
     * variance yields null).
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

        return [
            'id' => (int) $row->id,
            'slug' => (string) $row->slug,
            'identifier' => $row->identifier !== null ? (string) $row->identifier : '',
            'title' => ($row->title !== null && $row->title !== '') ? (string) $row->title : '',
            'scope' => $row->scope_and_content !== null ? trim((string) $row->scope_and_content) : '',
            'level' => $this->termName($row->level_of_description_id),
            'creators' => $this->creators((int) $row->id),
            'date' => $this->primaryDate((int) $row->id),
            'publisher' => $this->publisher($row->repository_id),
            'record_url' => $this->recordPublicUrl((string) $row->slug),
        ];
    }

    /**
     * The digital objects for a record, in sequence order. Master rows carry the
     * IO id in object_id (derivatives carry parent_id instead), so this returns
     * one row per uploaded file. Best-effort; [] on any schema variance.
     *
     * @return array<int,object>
     */
    protected function digitalObjects(int $objectId): array
    {
        try {
            if (! Schema::hasTable('digital_object')) {
                return [];
            }

            return DB::table('digital_object as do')
                ->where('do.object_id', $objectId)
                ->orderByRaw('COALESCE(do.sequence, 0)')
                ->orderBy('do.id')
                ->select(
                    'do.id',
                    'do.name',
                    'do.path',
                    'do.mime_type',
                    'do.byte_size',
                    'do.checksum',
                    'do.checksum_type'
                )
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Creator names (actors linked via the event table) - the SAME resolution as
     * CitationController::creators().
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
     * The single most representative display date (the event display date, else
     * a start/end span). Best-effort; '' when absent. Mirrors
     * CitationController::primaryDate().
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
     * Mirrors CitationController::publisher().
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
    // File-URL + checksum helpers
    // -----------------------------------------------------------------

    /**
     * The public, dereferenceable URL of a digital object's master file. The
     * deployed nginx maps '/uploads/' to the storage tree; digital_object.path is
     * stored relative to that mount (with or without a leading '/uploads/'). We
     * normalise to a single '/uploads/...' web path and prefix the request host
     * via url(), so nothing private is hardcoded. '' when there is no path.
     */
    protected function fileUrl(object $do): string
    {
        $path = ltrim((string) ($do->path ?? ''), '/');
        $name = (string) ($do->name ?? '');
        if ($path === '' && $name === '') {
            return '';
        }

        $rel = $path.$name;

        // Ensure the web path is rooted at '/uploads/' exactly once.
        if (! str_starts_with($rel, 'uploads/')) {
            $rel = 'uploads/'.$rel;
        }

        return rtrim((string) url('/'), '/').'/'.$rel;
    }

    /**
     * A IIIF Image API 3.0 service URL for an image digital object - the SAME
     * '/' -> '_SL_' Cantaloupe identifier construction the deployed viewer and
     * IiifPresentationController use, under the '/iiif/3/' prefix. '' for a
     * non-image (audio / video / PDF do not route through the Image API).
     */
    protected function iiifServiceUrl(object $do): string
    {
        if (! $this->isImage((string) ($do->mime_type ?? ''), (string) ($do->name ?? ''))) {
            return '';
        }

        $path = ltrim((string) ($do->path ?? ''), '/');
        $name = (string) ($do->name ?? '');
        if ($path === '' && $name === '') {
            return '';
        }

        // SAME identifier construction as the deployed viewer + the (locked)
        // IiifCollectionService: file path relative to uploads, '/' -> '_SL_',
        // then the filename appended.
        $iiifId = str_replace('/', '_SL_', $path).$name;

        return rtrim((string) url('/'), '/').'/iiif/3/'.$iiifId;
    }

    /**
     * Normalise a stored checksum-type token to a METS CHECKSUMTYPE enumerated
     * value (the controlled vocabulary the METS schema accepts). Anything we
     * cannot map confidently is dropped (the CHECKSUM is still emitted), so we
     * never emit an invalid CHECKSUMTYPE.
     */
    protected function checksumType(string $raw): string
    {
        $t = strtoupper(trim($raw));
        if ($t === '') {
            return '';
        }

        $map = [
            'MD5' => 'MD5',
            'SHA1' => 'SHA-1',
            'SHA-1' => 'SHA-1',
            'SHA256' => 'SHA-256',
            'SHA-256' => 'SHA-256',
            'SHA384' => 'SHA-384',
            'SHA-384' => 'SHA-384',
            'SHA512' => 'SHA-512',
            'SHA-512' => 'SHA-512',
            'CRC32' => 'CRC32',
            'ADLER32' => 'Adler-32',
            'HAVAL' => 'HAVAL',
            'TIGER' => 'TIGER',
            'WHIRLPOOL' => 'WHIRLPOOL',
        ];

        return $map[$t] ?? '';
    }

    // -----------------------------------------------------------------
    // Small helpers
    // -----------------------------------------------------------------

    /**
     * Whether a digital object is an image (so it gets a IIIF Image API locator).
     * MIME-first, with a filename-extension fallback for the formats Cantaloupe
     * serves. Mirrors IiifPresentationController::isImage().
     */
    protected function isImage(string $mime, string $name): bool
    {
        $mime = strtolower(trim($mime));
        if ($mime !== '') {
            return str_starts_with($mime, 'image/');
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|tiff?|jp2|jpx|bmp|webp)$/i', $name);
    }

    protected function isCollection(?string $level): bool
    {
        $l = strtolower((string) $level);

        return str_contains($l, 'fonds') || str_contains($l, 'collection') || str_contains($l, 'series');
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

    /**
     * The canonical public origin (no trailing slash), derived from url() so no
     * host is hardcoded.
     */
    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    protected function metsUrl(string $slug): string
    {
        return $this->base().'/mets/'.ltrim($slug, '/').'.xml';
    }

    protected function recordPublicUrl(string $slug): string
    {
        return $this->base().'/'.ltrim($slug, '/');
    }

    // -----------------------------------------------------------------
    // Responses + CORS
    // -----------------------------------------------------------------

    /**
     * A clean 404 for an unknown / unpublished / root record: a well-formed,
     * minimal METS-namespaced XML document with open CORS, never a 500, never a
     * draft leak.
     */
    protected function notFound(string $idOrSlug): Response
    {
        $safe = trim((string) preg_replace('/[\r\n]+/', ' ', $idOrSlug));
        $e = htmlspecialchars($safe, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $this->withCors(response(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<!-- Not Found: '.$e.' is not a published record. -->'."\n"
            .'<mets:mets xmlns:mets="http://www.loc.gov/METS/"></mets:mets>'."\n",
            404,
            ['Content-Type' => 'application/xml; charset=utf-8']
        ));
    }

    /**
     * Apply permissive open CORS headers (the METS document is meant to be
     * fetched by any interchange / ingest tool from any origin).
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
