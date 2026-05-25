<?php

/**
 * ModsSerializer - MODS 3.5 XML serializer.
 *
 * Produces a <mods> element body for an information object, conforming to
 * the LoC MODS 3.5 schema. Used for standalone download and for OAI-PMH
 * dissemination (metadataPrefix mods).
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

class ModsSerializer
{
    use InformationObjectFetcher;

    public function getFormat(): string
    {
        return 'mods';
    }

    public function getSchemaUrl(): string
    {
        return 'http://www.loc.gov/standards/mods/v3/mods-3-5.xsd';
    }

    public function getNamespace(): string
    {
        return 'http://www.loc.gov/mods/v3';
    }

    public function serializeRecord(int $objectId, string $culture = 'en'): string
    {
        $io = $this->fetchIo($objectId, $culture);
        if (! $io) {
            return '';
        }

        $repository = $this->fetchRepository($io, $culture);
        $events = $this->fetchEvents($io, $culture);
        $creators = $this->fetchCreators($io, $culture);
        $subjects = $this->fetchAccessPoints($io, 35, $culture);
        $places = $this->fetchAccessPoints($io, 42, $culture);
        $genres = $this->fetchAccessPoints($io, 78, $culture);
        $levelName = $this->fetchLevelName($io, $culture);
        $languages = $this->fetchLanguages($io, $culture);

        $xml = '<mods xmlns="'.$this->getNamespace().'"';
        $xml .= ' xmlns:xlink="http://www.w3.org/1999/xlink"';
        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $xml .= ' xsi:schemaLocation="'.$this->getNamespace().' '.$this->getSchemaUrl().'"';
        $xml .= ' version="3.5">'."\n";

        // Title
        $xml .= "  <titleInfo>\n    <title>".$this->escXml($io->title)."</title>\n  </titleInfo>\n";

        // Creators
        foreach ($creators as $creator) {
            $type = match ((int) ($creator->entity_type_id ?? 0)) {
                132 => 'personal',
                130 => 'family',
                131 => 'corporate',
                default => 'personal',
            };
            $xml .= "  <name type=\"{$type}\">\n";
            $xml .= '    <namePart>'.$this->escXml($creator->name)."</namePart>\n";
            $xml .= "    <role><roleTerm type=\"text\" authority=\"marcrelator\">creator</roleTerm></role>\n";
            $xml .= "  </name>\n";
        }

        // typeOfResource
        if ($levelName) {
            $xml .= '  <typeOfResource>'.$this->escXml($levelName)."</typeOfResource>\n";
        }

        // Origin info (dates)
        $hasOrigin = false;
        $originXml = "  <originInfo>\n";
        if ($repository) {
            $originXml .= '    <publisher>'.$this->escXml($repository->name)."</publisher>\n";
            $hasOrigin = true;
        }
        foreach ($events as $event) {
            $dateVal = $event->date_display ?: ($event->start_date ?? '');
            if ($dateVal) {
                $hasOrigin = true;
                $originXml .= '    <dateCreated>'.$this->escXml($dateVal)."</dateCreated>\n";
            }
        }
        $originXml .= "  </originInfo>\n";
        if ($hasOrigin) {
            $xml .= $originXml;
        }

        // Languages
        foreach ($languages as $lang) {
            $xml .= '  <language><languageTerm type="text">'.$this->escXml($lang->name)."</languageTerm></language>\n";
        }

        // Physical description
        if ($io->extent_and_medium) {
            $xml .= "  <physicalDescription>\n    <extent>".$this->escXml($io->extent_and_medium)."</extent>\n  </physicalDescription>\n";
        }

        // Abstract
        if ($io->scope_and_content) {
            $xml .= '  <abstract>'.$this->escXml($io->scope_and_content)."</abstract>\n";
        }

        // Subjects + places + genres
        foreach ($subjects as $s) {
            $xml .= '  <subject><topic>'.$this->escXml($s->name)."</topic></subject>\n";
        }
        foreach ($places as $p) {
            $xml .= '  <subject><geographic>'.$this->escXml($p->name)."</geographic></subject>\n";
        }
        foreach ($genres as $g) {
            $xml .= '  <genre>'.$this->escXml($g->name)."</genre>\n";
        }

        // Identifier
        if ($io->identifier) {
            $xml .= '  <identifier type="local">'.$this->escXml($io->identifier)."</identifier>\n";
        }
        if ($io->slug) {
            $xml .= '  <identifier type="uri">'.$this->escXml(url('/'.$io->slug))."</identifier>\n";
        }

        // Location
        if ($repository) {
            $xml .= "  <location>\n    <physicalLocation>".$this->escXml($repository->name)."</physicalLocation>\n";
            if ($io->slug) {
                $xml .= '    <url usage="primary display">'.$this->escXml(url('/'.$io->slug))."</url>\n";
            }
            $xml .= "  </location>\n";
        }

        // Access conditions
        if ($io->access_conditions) {
            $xml .= '  <accessCondition type="restriction on access">'.$this->escXml($io->access_conditions)."</accessCondition>\n";
        }
        if ($io->reproduction_conditions) {
            $xml .= '  <accessCondition type="use and reproduction">'.$this->escXml($io->reproduction_conditions)."</accessCondition>\n";
        }

        // Record info
        $xml .= "  <recordInfo>\n";
        $xml .= '    <recordContentSource>'.$this->escXml(config('app.name', 'Heratio'))."</recordContentSource>\n";
        $xml .= '    <recordCreationDate encoding="iso8601">'.gmdate('Y-m-d')."</recordCreationDate>\n";
        $xml .= '    <languageOfCataloging><languageTerm authority="iso639-2b">'.$this->escXml($culture)."</languageTerm></languageOfCataloging>\n";
        $xml .= "  </recordInfo>\n";

        $xml .= '</mods>';

        return $xml;
    }
}
