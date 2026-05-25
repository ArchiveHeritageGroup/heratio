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

use Illuminate\Support\Facades\DB;

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

        // Origin info — #662 Phase 2 adds publisher (actor preferred over
        // repository fallback), dateIssued (event type 114), dateCreated
        // (event type 111), and placeOfPublication (relation type 162).
        $publisherFreeText = $this->loadModsPublisherFreeText((int) $io->id, $culture);
        $placesOfPublication = $this->loadPlacesOfPublication((int) $io->id, $culture);

        $hasOrigin = false;
        $originXml = "  <originInfo>\n";

        // Publisher: prefer publication-event actor, then mods:publisher
        // free-text property, then repository as last resort.
        $publisherEmitted = false;
        foreach ($events as $event) {
            if ((int) ($event->type_id ?? 0) === 114 && ! empty($event->actor_id)) {
                $publisher = DB::table('actor_i18n')
                    ->where('id', $event->actor_id)
                    ->where('culture', $culture)
                    ->value('authorized_form_of_name');
                if ($publisher) {
                    $originXml .= '    <publisher>'.$this->escXml((string) $publisher)."</publisher>\n";
                    $publisherEmitted = true;
                    $hasOrigin = true;
                    break;
                }
            }
        }
        if (! $publisherEmitted && $publisherFreeText !== '') {
            $originXml .= '    <publisher>'.$this->escXml($publisherFreeText)."</publisher>\n";
            $publisherEmitted = true;
            $hasOrigin = true;
        }
        if (! $publisherEmitted && $repository) {
            $originXml .= '    <publisher>'.$this->escXml($repository->name)."</publisher>\n";
            $hasOrigin = true;
        }

        foreach ($events as $event) {
            $dateVal = $event->date_display ?: ($event->start_date ?? '');
            if (! $dateVal) {
                continue;
            }
            $typeId = (int) ($event->type_id ?? 0);
            $iso = $event->start_date ?? null;
            if ($typeId === 114) {
                if ($iso) {
                    $originXml .= '    <dateIssued encoding="iso8601">'.$this->escXml((string) $iso)."</dateIssued>\n";
                }
                $originXml .= '    <dateIssued>'.$this->escXml($dateVal)."</dateIssued>\n";
            } elseif ($typeId === 111) {
                if ($iso) {
                    $originXml .= '    <dateCreated encoding="iso8601">'.$this->escXml((string) $iso)."</dateCreated>\n";
                }
                $originXml .= '    <dateCreated>'.$this->escXml($dateVal)."</dateCreated>\n";
            } else {
                $originXml .= '    <dateCreated>'.$this->escXml($dateVal)."</dateCreated>\n";
            }
            $hasOrigin = true;
        }

        foreach ($placesOfPublication as $pop) {
            $originXml .= '    <placeOfPublication><placeTerm type="text">'.$this->escXml((string) ($pop->name ?? ''))."</placeTerm></placeOfPublication>\n";
            $hasOrigin = true;
        }

        $originXml .= "  </originInfo>\n";
        if ($hasOrigin) {
            $xml .= $originXml;
        }

        // mods:note — #662 Phase 2 general note
        $modsNote = $this->loadModsNote((int) $io->id, $culture);
        if ($modsNote !== '') {
            $xml .= '  <note type="general">'.$this->escXml($modsNote)."</note>\n";
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

    /**
     * Load the free-text "mods:publisher" property (serialized PHP array),
     * returning the first scalar value or '' when nothing is recorded.
     */
    private function loadModsPublisherFreeText(int $ioId, string $culture): string
    {
        return $this->loadFirstScalarProperty($ioId, 'mods:publisher', $culture);
    }

    /**
     * Load the free-text mods:note property and concatenate any list
     * elements into one newline-separated string.
     */
    private function loadModsNote(int $ioId, string $culture): string
    {
        return $this->loadFirstScalarProperty($ioId, 'mods:note', $culture);
    }

    /**
     * Fetch place-of-publication terms (taxonomy 42) keyed to the IO via
     * relation rows of type_id = 162. Returns a collection of {name}.
     */
    private function loadPlacesOfPublication(int $ioId, string $culture)
    {
        return DB::table('relation')
            ->join('term_i18n', 'relation.object_id', '=', 'term_i18n.id')
            ->where('relation.subject_id', $ioId)
            ->where('relation.type_id', 162)
            ->where('term_i18n.culture', $culture)
            ->select('relation.object_id as place_id', 'term_i18n.name')
            ->get();
    }

    private function loadFirstScalarProperty(int $ioId, string $name, string $culture): string
    {
        $raw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $ioId)
            ->where('property.name', $name)
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
        if (! $raw) {
            return '';
        }
        $decoded = @unserialize($raw);
        if (is_string($decoded)) {
            return $decoded;
        }
        if (is_array($decoded)) {
            return implode("\n\n", array_filter($decoded));
        }
        return (string) $raw;
    }
}
