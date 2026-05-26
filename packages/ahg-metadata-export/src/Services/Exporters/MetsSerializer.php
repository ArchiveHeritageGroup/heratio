<?php

/**
 * MetsSerializer - per-information-object METS 1.12 serializer.
 *
 * Phase 1 of #658 (METS + PROV-O audit) shipped the AIP shape; Phase 4
 * adds SIP / AIP / DIP profile separation matching the OAIS reference
 * model (also Archivematica convention).
 *
 *   SIP - submission package: minimal Dublin Core dmdSec, rightsMD + sourceMD
 *         amdSec only, original digital_object only in fileSec, structMap.
 *   AIP - archival package (default, back-compat with Phase 1): full DC
 *         dmdSec, full amdSec (PREMIS digiprovMD), all file groups (master
 *         + preservation + access) in fileSec, structMap.
 *   DIP - dissemination package: full DC dmdSec, rightsMD only amdSec (no
 *         PREMIS digiprovMD - keep PII / forensic trace off the public
 *         surface), access copy only fileSec, structMap.
 *
 * The PROFILE attribute on <mets> changes per profile.
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

    public const PROFILE_AIP = 'https://heratio.theahg.co.za/profiles/mets/io-aip-v1';

    public const PROFILE_SIP = 'https://heratio.theahg.co.za/profiles/mets/io-sip-v1';

    public const PROFILE_DIP = 'https://heratio.theahg.co.za/profiles/mets/io-dip-v1';

    /**
     * Back-compat alias - Phase 1 callers / tests referenced
     * MetsSerializer::PROFILE before profile separation. Maps to the AIP
     * profile because that matches the Phase 1 emit shape.
     */
    public const PROFILE = self::PROFILE_AIP;

    public const NS_METS = 'http://www.loc.gov/METS/';

    public const NS_XLINK = 'http://www.w3.org/1999/xlink';

    public const NS_PREMIS = 'http://www.loc.gov/premis/v3';

    public const NS_DC = 'http://purl.org/dc/elements/1.1/';

    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public const PROFILES = ['SIP', 'AIP', 'DIP'];

    public function getFormat(): string
    {
        return 'mets';
    }

    /**
     * Normalise a free-form profile string to one of SIP / AIP / DIP.
     * Anything unrecognised falls back to AIP (back-compat default).
     */
    public static function normaliseProfile(?string $profile): string
    {
        $p = strtoupper(trim((string) $profile));
        return in_array($p, self::PROFILES, true) ? $p : 'AIP';
    }

    /**
     * Map an OAIS profile code to its full PROFILE URI.
     */
    public static function profileUri(string $profile): string
    {
        return match (self::normaliseProfile($profile)) {
            'SIP' => self::PROFILE_SIP,
            'DIP' => self::PROFILE_DIP,
            default => self::PROFILE_AIP,
        };
    }

    /**
     * Render a METS XML document for the given information object.
     *
     * @param  string  $profile  one of SIP / AIP / DIP (case-insensitive); default AIP
     */
    public function serializeRecord(int $ioId, string $culture = 'en', string $profile = 'AIP'): string
    {
        $profile = self::normaliseProfile($profile);

        $io = $this->fetchIo($ioId, $culture);
        if (! $io) {
            return '';
        }

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        // <mets> root with namespaces + per-profile PROFILE URI
        $w->startElementNs(null, 'mets', self::NS_METS);
        $w->writeAttributeNs('xmlns', 'xlink', null, self::NS_XLINK);
        $w->writeAttributeNs('xmlns', 'premis', null, self::NS_PREMIS);
        $w->writeAttributeNs('xmlns', 'dc', null, self::NS_DC);
        $w->writeAttributeNs('xmlns', 'xsi', null, self::NS_XSI);
        $w->writeAttribute('OBJID', 'ahg:io/'.((int) $io->id));
        $w->writeAttribute('TYPE', 'ArchivalDescription');
        $w->writeAttribute('LABEL', (string) ($io->title ?? ''));
        $w->writeAttribute('PROFILE', self::profileUri($profile));

        $this->writeHeader($w, $io, $profile);
        $this->writeDmdSec($w, $io, $culture, $profile);
        $this->writeAmdSec($w, (int) $io->id, $profile);
        $this->writeFileSec($w, (int) $io->id, $profile);
        $this->writeStructMap($w, $io, $culture);

        $w->endElement(); // mets
        $w->endDocument();

        return $w->outputMemory();
    }

    private function writeHeader(XMLWriter $w, $io, string $profile): void
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
        $w->writeElement('name', (string) config('app.name', 'Heratio').' METS exporter ('.$profile.')');
        $w->endElement(); // agent

        $w->endElement(); // metsHdr
    }

    /**
     * Descriptive metadata. Profile shapes:
     *   SIP - minimal Dublin Core (title + identifier + date + source)
     *   AIP / DIP - full Dublin Core (Phase 1 behaviour)
     */
    private function writeDmdSec(XMLWriter $w, $io, string $culture, string $profile): void
    {
        $w->startElement('dmdSec');
        $w->writeAttribute('ID', 'dmd-'.((int) $io->id));
        $w->startElement('mdWrap');
        $w->writeAttribute('MDTYPE', 'DC');
        $w->startElement('xmlData');

        if ($profile === 'SIP') {
            // Minimal capture metadata for a submission package
            $w->writeElementNs('dc', 'title', null, (string) ($io->title ?? ''));
            if (! empty($io->identifier)) {
                $w->writeElementNs('dc', 'identifier', null, (string) $io->identifier);
            }
            $events = $this->fetchEvents($io, $culture);
            foreach ($events as $event) {
                $dateVal = $event->date_display ?: ($event->start_date ?? '');
                if ($dateVal) {
                    $w->writeElementNs('dc', 'date', null, (string) $dateVal);
                    break; // SIP keeps it minimal: first date only
                }
            }
            if (! empty($io->slug)) {
                $w->writeElementNs('dc', 'source', null, url('/'.$io->slug));
            }
        } else {
            // AIP + DIP both get the full Dublin Core record
            $repository = $this->fetchRepository($io, $culture);
            $events = $this->fetchEvents($io, $culture);
            $creators = $this->fetchCreators($io, $culture);
            $subjects = $this->fetchAccessPoints($io, 35, $culture);
            $places = $this->fetchAccessPoints($io, 42, $culture);
            $languages = $this->fetchLanguages($io, $culture);
            $levelName = $this->fetchLevelName($io, $culture);

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
        }

        $w->endElement(); // xmlData
        $w->endElement(); // mdWrap
        $w->endElement(); // dmdSec
    }

    /**
     * Administrative metadata. Profile shapes:
     *   SIP - rightsMD (access_conditions) + sourceMD (slug source URL).
     *         No PREMIS digiprovMD (no forensic trace at submission time).
     *   AIP - rightsMD + sourceMD + full PREMIS digiprovMD (per-event).
     *   DIP - rightsMD only. PREMIS deliberately suppressed so the
     *         dissemination copy doesn't leak forensic / agent metadata.
     */
    private function writeAmdSec(XMLWriter $w, int $ioId, string $profile): void
    {
        $w->startElement('amdSec');
        $w->writeAttribute('ID', 'amd-'.$ioId);

        // rightsMD - always present (cheap and useful for downstream consumers)
        $this->writeRightsMd($w, $ioId);

        // sourceMD - SIP + AIP only
        if ($profile !== 'DIP') {
            $this->writeSourceMd($w, $ioId);
        }

        // digiprovMD - AIP only (full PREMIS event chain)
        if ($profile === 'AIP') {
            (new PremisInMetsBuilder())->appendDigiprovMd($w, $ioId);
        }

        $w->endElement(); // amdSec
    }

    /**
     * Minimal rightsMD wrap pulling access_conditions / reproduction_conditions
     * off the information_object. Kept profile-agnostic so SIP / AIP / DIP
     * share the same rights expression.
     */
    private function writeRightsMd(XMLWriter $w, int $ioId): void
    {
        $row = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id');
            })
            ->where('io.id', $ioId)
            ->select('i18n.access_conditions', 'i18n.reproduction_conditions')
            ->first();

        $access = trim((string) ($row->access_conditions ?? ''));
        $reproduction = trim((string) ($row->reproduction_conditions ?? ''));

        $w->startElement('rightsMD');
        $w->writeAttribute('ID', 'rights-'.$ioId);
        $w->startElement('mdWrap');
        $w->writeAttribute('MDTYPE', 'OTHER');
        $w->writeAttribute('OTHERMDTYPE', 'RIGHTSSTATEMENT');
        $w->startElement('xmlData');
        $w->startElement('rights');
        if ($access !== '') {
            $w->writeElement('accessConditions', $access);
        }
        if ($reproduction !== '') {
            $w->writeElement('reproductionConditions', $reproduction);
        }
        $w->endElement(); // rights
        $w->endElement(); // xmlData
        $w->endElement(); // mdWrap
        $w->endElement(); // rightsMD
    }

    /**
     * sourceMD wrap pointing back at the canonical Heratio URL for the IO
     * so the SIP / AIP can be re-resolved at any point.
     */
    private function writeSourceMd(XMLWriter $w, int $ioId): void
    {
        $slug = DB::table('slug')->where('object_id', $ioId)->value('slug');

        $w->startElement('sourceMD');
        $w->writeAttribute('ID', 'source-'.$ioId);
        $w->startElement('mdWrap');
        $w->writeAttribute('MDTYPE', 'OTHER');
        $w->writeAttribute('OTHERMDTYPE', 'HERATIO');
        $w->startElement('xmlData');
        $w->startElement('source');
        $w->writeElement('system', (string) config('app.name', 'Heratio'));
        if (! empty($slug)) {
            $w->writeElement('canonicalUrl', url('/'.$slug));
        }
        $w->writeElement('informationObjectId', (string) $ioId);
        $w->endElement(); // source
        $w->endElement(); // xmlData
        $w->endElement(); // mdWrap
        $w->endElement(); // sourceMD
    }

    /**
     * <fileSec> grouping digital_object rows by usage_id. Profile shapes:
     *   SIP - original / master only (USAGE_MASTER 140)
     *   AIP - master + preservation + access (Phase 1 behaviour)
     *   DIP - access copy only (USAGE_REFERENCE 141 + USAGE_THUMBNAIL 142)
     */
    private function writeFileSec(XMLWriter $w, int $ioId, string $profile): void
    {
        $w->startElement('fileSec');

        if (Schema::hasTable('digital_object')) {
            $rows = DB::table('digital_object')
                ->where('object_id', $ioId)
                ->orderBy('id')
                ->select('id', 'name', 'path', 'usage_id', 'mime_type', 'byte_size', 'checksum')
                ->get();

            $allowedUses = match ($profile) {
                'SIP' => ['master'],
                'DIP' => ['access'],
                default => ['master', 'preservation', 'access', 'other'],
            };

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
                if (in_array($use, $allowedUses, true)) {
                    $groups[$use][] = $r;
                }
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
        // hierarchy can be added later - Phase 1 keeps the map flat).
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
