<?php

/**
 * MarcxmlSerializer - MARC21 record serialized as MARCXML.
 *
 * Produces a <record> element body conforming to the MARC21 slim XML schema
 * (https://www.loc.gov/standards/marcxml/). Used for standalone download and
 * for OAI-PMH dissemination (metadataPrefix marcxml).
 *
 * Crosswalk from archival/library information_object fields to MARC21:
 *   001  control number (io.identifier or io.id)
 *   003  control number identifier (config app.name)
 *   005  date of latest transaction (object.updated_at, YYYYMMDDhhmmss.f)
 *   040  cataloguing source
 *   041  language code (from object_term_relation taxonomy 7)
 *   1XX  main entry — first creator (100 personal, 110 corporate, 111 meeting)
 *   245  title statement
 *   264  publication info (repository + dates)
 *   300  physical description (extent_and_medium)
 *   336  content type (RDA)
 *   500  general note
 *   506  restrictions on access
 *   520  summary (scope_and_content)
 *   540  terms governing use (reproduction_conditions)
 *   541  acquisition source (acquisition)
 *   544  related material
 *   561  ownership/custodial history (archival_history)
 *   6XX  subject access (650 topical, 651 geographic, 655 genre)
 *   700  added creator entries (subsequent creators)
 *   852  location (repository)
 *   856  electronic location and access (URL to detail page)
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

namespace AhgMetadataExport\Services\Exporters;

class MarcxmlSerializer
{
    use InformationObjectFetcher;

    public function getFormat(): string
    {
        return 'marcxml';
    }

    public function getSchemaUrl(): string
    {
        return 'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd';
    }

    public function getNamespace(): string
    {
        return 'http://www.loc.gov/MARC21/slim';
    }

    public function serializeRecord(int $objectId, string $culture = 'en'): string
    {
        $io = $this->fetchIo($objectId, $culture);
        if (!$io) {
            return '';
        }

        $repository = $this->fetchRepository($io, $culture);
        $events     = $this->fetchEvents($io, $culture);
        $creators   = $this->fetchCreators($io, $culture);
        $subjects   = $this->fetchAccessPoints($io, 35, $culture);
        $places     = $this->fetchAccessPoints($io, 42, $culture);
        $genres     = $this->fetchAccessPoints($io, 78, $culture);
        $languages  = $this->fetchLanguages($io, $culture);

        $xml  = '<record xmlns="' . $this->getNamespace() . '"';
        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $xml .= ' xsi:schemaLocation="' . $this->getNamespace() . ' ' . $this->getSchemaUrl() . '">' . "\n";

        // Leader: 24 chars
        //   pos 00-04 = record length (placeholder "00000")
        //   pos 05    = record status 'n' = new
        //   pos 06    = record type 'a' = language material
        //   pos 07    = bibliographic level 'c' = collection (for archival records)
        //   pos 08    = type of control ' '
        //   pos 09    = character coding 'a' = UCS/Unicode
        //   pos 10-11 = "22"
        //   pos 12-16 = base address (placeholder)
        //   pos 17    = encoding level 'u' = unknown
        //   pos 18    = descriptive cataloguing form 'i' = ISBD
        //   pos 19    = multipart resource level ' '
        //   pos 20-23 = "4500"
        $xml .= "  <leader>00000nac a2200000ui 4500</leader>\n";

        // 001 control number
        $controlId = $io->identifier ?: (string) $io->id;
        $xml .= '  <controlfield tag="001">' . $this->escXml($controlId) . "</controlfield>\n";

        // 003 control number identifier
        $xml .= '  <controlfield tag="003">' . $this->escXml(config('app.name', 'Heratio')) . "</controlfield>\n";

        // 005 date of latest transaction (YYYYMMDDhhmmss.f)
        $tsSource = $io->updated_at ?: $io->created_at ?: gmdate('Y-m-d H:i:s');
        $ts = strtotime($tsSource);
        $xml .= '  <controlfield tag="005">' . gmdate('YmdHis', $ts) . ".0</controlfield>\n";

        // 008 fixed-length data elements (40 chars; minimally populated)
        //   pos 00-05 = entry date (YYMMDD)
        //   pos 06    = type of date 's' = single known
        //   pos 07-10 = date 1
        //   pos 11-14 = date 2
        //   pos 15-17 = country code (3-letter)
        //   pos 35-37 = language (3-letter)
        //   pos 38    = modified record ' '
        //   pos 39    = cataloguing source ' '
        $entryDate = gmdate('ymd', $ts);
        $year = '';
        foreach ($events as $event) {
            if ($event->start_date) {
                $year = substr($event->start_date, 0, 4);
                break;
            }
        }
        $year = str_pad($year ?: '    ', 4);
        $langCode = $this->iso6392bFromCulture($culture);
        $fixed = $entryDate . 's' . $year . '    ' . 'xx ' . str_repeat(' ', 17) . $langCode . '  ';
        $fixed = str_pad(substr($fixed, 0, 40), 40);
        $xml .= '  <controlfield tag="008">' . $this->escXml($fixed) . "</controlfield>\n";

        // 040 cataloguing source
        $xml .= '  <datafield tag="040" ind1=" " ind2=" ">' . "\n";
        $xml .= '    <subfield code="a">' . $this->escXml(config('app.name', 'Heratio')) . "</subfield>\n";
        $xml .= '    <subfield code="b">' . $this->escXml($langCode) . "</subfield>\n";
        $xml .= '    <subfield code="e">rda</subfield>' . "\n";
        $xml .= "  </datafield>\n";

        // 041 language code(s)
        if ($languages->isNotEmpty()) {
            $xml .= '  <datafield tag="041" ind1="0" ind2=" ">' . "\n";
            foreach ($languages as $lang) {
                $xml .= '    <subfield code="a">' . $this->escXml($this->iso6392bFromCulture($lang->name)) . "</subfield>\n";
            }
            $xml .= "  </datafield>\n";
        }

        // 1XX main entry (first creator only) + 700 added entries (rest)
        $first = true;
        foreach ($creators as $creator) {
            $entityType = (int) ($creator->entity_type_id ?? 0);
            $tag = $first
                ? match ($entityType) {
                    131 => '110',  // corporate
                    130 => '111',  // family (treated as meeting/event for MARC main entry)
                    default => '100', // personal
                }
                : match ($entityType) {
                    131 => '710',
                    130 => '711',
                    default => '700',
                };
            $ind1 = ($entityType === 131 || $entityType === 130) ? '2' : '1';
            $xml .= '  <datafield tag="' . $tag . '" ind1="' . $ind1 . '" ind2=" ">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($creator->name) . "</subfield>\n";
            $xml .= '    <subfield code="e">creator</subfield>' . "\n";
            $xml .= "  </datafield>\n";
            $first = false;
        }

        // 245 title statement
        $xml .= '  <datafield tag="245" ind1="' . ($first ? '0' : '1') . '" ind2="0">' . "\n";
        $xml .= '    <subfield code="a">' . $this->escXml($io->title) . "</subfield>\n";
        $xml .= "  </datafield>\n";

        // 264 production / publication info
        if ($repository || !empty($events)) {
            $xml .= '  <datafield tag="264" ind1=" " ind2="1">' . "\n";
            if ($repository) {
                $xml .= '    <subfield code="b">' . $this->escXml($repository->name) . "</subfield>\n";
            }
            foreach ($events as $event) {
                $dateVal = $event->date_display ?: ($event->start_date ?? '');
                if ($dateVal) {
                    $xml .= '    <subfield code="c">' . $this->escXml($dateVal) . "</subfield>\n";
                    break;
                }
            }
            $xml .= "  </datafield>\n";
        }

        // 300 physical description
        if ($io->extent_and_medium) {
            $xml .= '  <datafield tag="300" ind1=" " ind2=" ">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($io->extent_and_medium) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 336 RDA content type
        $xml .= '  <datafield tag="336" ind1=" " ind2=" ">' . "\n";
        $xml .= '    <subfield code="a">text</subfield>' . "\n";
        $xml .= '    <subfield code="2">rdacontent</subfield>' . "\n";
        $xml .= "  </datafield>\n";

        // 506 restrictions on access
        if ($io->access_conditions) {
            $xml .= '  <datafield tag="506" ind1=" " ind2=" ">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($io->access_conditions) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 520 summary / scope and content
        if ($io->scope_and_content) {
            $xml .= '  <datafield tag="520" ind1=" " ind2=" ">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($io->scope_and_content) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 540 terms governing use
        if ($io->reproduction_conditions) {
            $xml .= '  <datafield tag="540" ind1=" " ind2=" ">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($io->reproduction_conditions) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 541 acquisition source
        if ($io->acquisition) {
            $xml .= '  <datafield tag="541" ind1=" " ind2=" ">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($io->acquisition) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 544 related material
        if ($io->related_units_of_description) {
            $xml .= '  <datafield tag="544" ind1=" " ind2=" ">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($io->related_units_of_description) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 561 ownership and custodial history
        if ($io->archival_history) {
            $xml .= '  <datafield tag="561" ind1=" " ind2=" ">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($io->archival_history) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 650 topical subjects
        foreach ($subjects as $s) {
            $xml .= '  <datafield tag="650" ind1=" " ind2="4">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($s->name) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 651 geographic
        foreach ($places as $p) {
            $xml .= '  <datafield tag="651" ind1=" " ind2="4">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($p->name) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 655 genre/form
        foreach ($genres as $g) {
            $xml .= '  <datafield tag="655" ind1=" " ind2="4">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($g->name) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        // 852 location (repository)
        if ($repository) {
            $xml .= '  <datafield tag="852" ind1=" " ind2=" ">' . "\n";
            $xml .= '    <subfield code="a">' . $this->escXml($repository->name) . "</subfield>\n";
            if ($io->identifier) {
                $xml .= '    <subfield code="j">' . $this->escXml($io->identifier) . "</subfield>\n";
            }
            $xml .= "  </datafield>\n";
        }

        // 856 electronic location
        if ($io->slug) {
            $xml .= '  <datafield tag="856" ind1="4" ind2="0">' . "\n";
            $xml .= '    <subfield code="u">' . $this->escXml(url('/' . $io->slug)) . "</subfield>\n";
            $xml .= '    <subfield code="y">' . $this->escXml($io->title) . "</subfield>\n";
            $xml .= "  </datafield>\n";
        }

        $xml .= "</record>";
        return $xml;
    }

    /**
     * Convert a culture code (en, fr, af, ...) to an ISO 639-2/B 3-letter
     * code suitable for MARC 008/35-37 + 041$a. Passes through 3-letter
     * codes unchanged. Falls back to 'und' (undetermined) when unknown.
     */
    private function iso6392bFromCulture(?string $culture): string
    {
        if (!$culture) {
            return 'und';
        }
        $c = strtolower(trim($culture));
        if (strlen($c) === 3) {
            return $c;
        }
        $map = [
            'en' => 'eng', 'fr' => 'fre', 'de' => 'ger', 'es' => 'spa',
            'pt' => 'por', 'nl' => 'dut', 'it' => 'ita', 'af' => 'afr',
            'zu' => 'zul', 'xh' => 'xho', 'st' => 'sot', 'tn' => 'tsn',
            'ts' => 'tso', 'ss' => 'ssw', 'nr' => 'nbl', 've' => 'ven',
            'sn' => 'sna', 'nd' => 'nde', 'sw' => 'swa', 'ar' => 'ara',
            'zh' => 'chi', 'ja' => 'jpn', 'ko' => 'kor', 'ru' => 'rus',
            'he' => 'heb', 'fa' => 'per', 'hi' => 'hin', 'ur' => 'urd',
            'tr' => 'tur', 'pl' => 'pol', 'cs' => 'cze', 'uk' => 'ukr',
            'el' => 'gre', 'sv' => 'swe', 'no' => 'nor', 'da' => 'dan',
            'fi' => 'fin', 'hu' => 'hun', 'ro' => 'rum', 'th' => 'tha',
            'vi' => 'vie', 'id' => 'ind', 'ms' => 'may',
        ];
        return $map[$c] ?? 'und';
    }
}
