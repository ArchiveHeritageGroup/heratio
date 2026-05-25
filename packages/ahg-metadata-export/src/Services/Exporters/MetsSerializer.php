<?php

/**
 * MetsSerializer - per-information-object METS 1.12 serializer.
 *
 * Phase 1 of #658 (METS + PROV-O audit). Produces a METS document for a
 * single archival description with the four canonical sections:
 *
 *   <metsHdr>   - creation timestamp + agent
 *   <dmdSec>    - Dublin Core descriptive metadata (mirrors ExportController::dc())
 *   <amdSec>    - administrative metadata containing per-event <digiprovMD>
 *                 children built by PremisInMetsBuilder
 *   <fileSec>   - <fileGrp USE="master|preservation|access"> over digital_object rows
 *   <structMap> - logical structure: one <div> per child IO
 *
 * PROFILE: https://heratio.theahg.co.za/profiles/mets/io-v1
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
 */

namespace AhgMetadataExport\Services\Exporters;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use XMLWriter;

class MetsSerializer
{
    use InformationObjectFetcher;

    public const PROFILE = 'https://heratio.theahg.co.za/profiles/mets/io-v1';

    public const NS_METS = 'http://www.loc.gov/METS/';

    public const NS_XLINK = 'http://www.w3.org/1999/xlink';

    public const NS_PREMIS = 'http://www.loc.gov/premis/v3';

    public const NS_DC = 'http://purl.org/dc/elements/1.1/';

    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function getFormat(): string
    {
        return 'mets';
    }

    /**
     * Render a METS XML document for the given information object.
     */
    public function serializeRecord(int $ioId, string $culture = 'en'): string
    {
        $io = $this->fetchIo($ioId, $culture);
        if (! $io) {
            return '';
        }

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        // <mets> root with namespaces + PROFILE
        $w->startElementNs(null, 'mets', self::NS_METS);
        $w->writeAttributeNs('xmlns', 'xlink', null, self::NS_XLINK);
        $w->writeAttributeNs('xmlns', 'premis', null, self::NS_PREMIS);
        $w->writeAttributeNs('xmlns', 'dc', null, self::NS_DC);
        $w->writeAttributeNs('xmlns', 'xsi', null, self::NS_XSI);
        $w->writeAttribute('OBJID', 'ahg:io/'.((int) $io->id));
        $w->writeAttribute('TYPE', 'ArchivalDescription');
        $w->writeAttribute('LABEL', (string) ($io->title ?? ''));
        $w->writeAttribute('PROFILE', self::PROFILE);

        $this->writeHeader($w, $io);
        $this->writeDmdSec($w, $io, $culture);
        $this->writeAmdSec($w, (int) $io->id);
        $this->writeFileSec($w, (int) $io->id);
        $this->writeStructMap($w, $io, $culture);

        $w->endElement(); // mets
        $w->endDocument();

        return $w->outputMemory();
    }

    private function writeHeader(XMLWriter $w, $io): void
    {
        $w->startElement('metsHdr');
        $w->writeAttribute('CREATEDATE', gmdate('Y-m-d\TH:i:s\Z'));
        if (! empty($io->updated_at)) {
            $t = strtotime((string) $io->updated_at);
            if ($t) {
                $w->writeAttribute('LASTMODDATE', gmdate('Y-m-d\TH:i:s\Z', $t));
            }
        }
        $w->writeAttribute('RECORDSTATUS', 'derived');

        $w->startElement('agent');
        $w->writeAttribute('ROLE', 'CREATOR');
        $w->writeAttribute('TYPE', 'OTHER');
        $w->writeAttribute('OTHERTYPE', 'SOFTWARE');
        $w->writeElement('name', (string) config('app.name', 'Heratio').' METS exporter');
        $w->endElement(); // agent

        $w->endElement(); // metsHdr
    }

    /**
     * Inline Dublin Core descriptive metadata. The element list mirrors the
     * dc() method in ExportController so the dmdSec content matches the
     * per-record DC XML download byte-for-byte (at the element level).
     */
    private function writeDmdSec(XMLWriter $w, $io, string $culture): void
    {
        $repository = $this->fetchRepository($io, $culture);
        $events = $this->fetchEvents($io, $culture);
        $creators = $this->fetchCreators($io, $culture);
        $subjects = $this->fetchAccessPoints($io, 35, $culture);
        $places = $this->fetchAccessPoints($io, 42, $culture);
        $languages = $this->fetchLanguages($io, $culture);
        $levelName = $this->fetchLevelName($io, $culture);

        $w->startElement('dmdSec');
        $w->writeAttribute('ID', 'dmd-'.((int) $io->id));
        $w->startElement('mdWrap');
        $w->writeAttribute('MDTYPE', 'DC');
        $w->startElement('xmlData');

        $w->writeElementNs('dc', 'title', null, (string) ($io->title ?? ''));
        foreach ($creators as $c) {
            $w->writeElementNs('dc', 'creator', null, (string) ($c->name ?? ''));
        }
        foreach ($subjects as $s) {
            $w->writeElementNs('dc', 'subject', null, (string) ($s->name ?? ''));
        }
        if (! empty($io->scope_and_content)) {
            $w->writeElementNs('dc', 'description', null, (string) $io->scope_and_content);
        }
        if ($repository) {
            $w->writeElementNs('dc', 'publisher', null, (string) $repository->name);
        }
        foreach ($events as $event) {
            $dateVal = $event->date_display ?: ($event->start_date ?? '');
            if ($dateVal) {
                $w->writeElementNs('dc', 'date', null, (string) $dateVal);
            }
        }
        if ($levelName) {
            $w->writeElementNs('dc', 'type', null, (string) $levelName);
        }
        if (! empty($io->extent_and_medium)) {
            $w->writeElementNs('dc', 'format', null, (string) $io->extent_and_medium);
        }
        if (! empty($io->identifier)) {
            $w->writeElementNs('dc', 'identifier', null, (string) $io->identifier);
        }
        if (! empty($io->slug)) {
            $w->writeElementNs('dc', 'source', null, url('/'.$io->slug));
        }
        foreach ($languages as $lang) {
            $w->writeElementNs('dc', 'language', null, (string) ($lang->name ?? ''));
        }
        foreach ($places as $p) {
            $w->writeElementNs('dc', 'coverage', null, (string) ($p->name ?? ''));
        }
        if (! empty($io->access_conditions)) {
            $w->writeElementNs('dc', 'rights', null, (string) $io->access_conditions);
        }

        $w->endElement(); // xmlData
        $w->endElement(); // mdWrap
        $w->endElement(); // dmdSec
    }

    /**
     * Administrative metadata. We emit one <amdSec> with N <digiprovMD>
     * children, one per preservation_event row, built by PremisInMetsBuilder.
     */
    private function writeAmdSec(XMLWriter $w, int $ioId): void
    {
        $w->startElement('amdSec');
        $w->writeAttribute('ID', 'amd-'.$ioId);
        (new PremisInMetsBuilder())->appendDigiprovMd($w, $ioId);
        $w->endElement(); // amdSec
    }

    /**
     * <fileSec> grouping digital_object rows by usage_id into master /
     * preservation / access file groups. usage_id mapping:
     *   140 (USAGE_MASTER)     -> USE="master"
     *   143 (USAGE_PRESERVATION) -> USE="preservation"
     *   141 (USAGE_REFERENCE)  -> USE="access"
     *   142 (USAGE_THUMBNAIL)  -> USE="access" (treated as access derivative)
     * Other usage_id values fall into USE="other".
     */
    private function writeFileSec(XMLWriter $w, int $ioId): void
    {
        $w->startElement('fileSec');

        if (Schema::hasTable('digital_object')) {
            $rows = DB::table('digital_object')
                ->where('object_id', $ioId)
                ->orderBy('id')
                ->select('id', 'name', 'path', 'usage_id', 'mime_type', 'byte_size', 'checksum')
                ->get();

            $groups = [
                'master' => [],
                'preservation' => [],
                'access' => [],
                'other' => [],
            ];
            foreach ($rows as $r) {
                $use = match ((int) ($r->usage_id ?? 0)) {
                    140 => 'master',
                    143 => 'preservation',
                    141, 142 => 'access',
                    default => 'other',
                };
                $groups[$use][] = $r;
            }

            foreach ($groups as $use => $files) {
                if (empty($files)) {
                    continue;
                }
                $w->startElement('fileGrp');
                $w->writeAttribute('USE', $use);
                foreach ($files as $f) {
                    $w->startElement('file');
                    $w->writeAttribute('ID', 'file-'.((int) $f->id));
                    if (! empty($f->mime_type)) {
                        $w->writeAttribute('MIMETYPE', (string) $f->mime_type);
                    }
                    if (! empty($f->byte_size)) {
                        $w->writeAttribute('SIZE', (string) $f->byte_size);
                    }
                    if (! empty($f->checksum)) {
                        $w->writeAttribute('CHECKSUM', (string) $f->checksum);
                        $w->writeAttribute('CHECKSUMTYPE', 'SHA-256');
                    }
                    $w->startElement('FLocat');
                    $w->writeAttribute('LOCTYPE', 'URL');
                    $href = ((string) ($f->path ?? '')).((string) ($f->name ?? ''));
                    $w->writeAttributeNs('xlink', 'href', null, $href);
                    $w->endElement(); // FLocat
                    $w->endElement(); // file
                }
                $w->endElement(); // fileGrp
            }
        }

        $w->endElement(); // fileSec
    }

    /**
     * Logical structMap with one <div> per child IO.
     */
    private function writeStructMap(XMLWriter $w, $io, string $culture): void
    {
        $w->startElement('structMap');
        $w->writeAttribute('TYPE', 'logical');

        $w->startElement('div');
        $w->writeAttribute('TYPE', 'archivalDescription');
        $w->writeAttribute('LABEL', (string) ($io->title ?? ''));
        $w->writeAttribute('DMDID', 'dmd-'.((int) $io->id));

        // One direct-child <div> per child IO (depth 1 only; deeper
        // hierarchy can be added later — Phase 1 keeps the map flat).
        $children = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.parent_id', $io->id)
            ->orderBy('io.lft')
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->get();

        foreach ($children as $child) {
            $w->startElement('div');
            $w->writeAttribute('TYPE', 'child');
            $w->writeAttribute('LABEL', (string) ($child->title ?? ''));
            if (! empty($child->slug)) {
                $w->startElement('mptr');
                $w->writeAttribute('LOCTYPE', 'URL');
                $w->writeAttributeNs('xlink', 'href', null, url('/'.$child->slug));
                $w->endElement(); // mptr
            }
            $w->endElement(); // div (child)
        }

        $w->endElement(); // div (root)
        $w->endElement(); // structMap
    }
}
