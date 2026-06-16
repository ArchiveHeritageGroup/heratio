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

        // typeOfResource - #662 Phase 3: map Heratio level + media type to
        // the MODS 3.7 controlled vocabulary instead of echoing the raw
        // level term. Falls back to the level term verbatim when no map
        // entry matches so legacy installs keep their behaviour.
        $resourceType = $this->mapTypeOfResource($io, $levelName);
        if ($resourceType !== null) {
            $xml .= '  <typeOfResource>'.$this->escXml($resourceType)."</typeOfResource>\n";
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

        // Languages - #662 Phase 3 also emits ISO 639-2b code when the term
        // resolves to a recognised culture string.
        foreach ($languages as $lang) {
            $xml .= "  <language>\n";
            $xml .= '    <languageTerm type="text">'.$this->escXml($lang->name)."</languageTerm>\n";
            $iso = $this->resolveLanguageCode($lang->name);
            if ($iso !== null) {
                $xml .= '    <languageTerm type="code" authority="iso639-2b">'.$this->escXml($iso)."</languageTerm>\n";
            }
            $xml .= "  </language>\n";
        }

        // Physical description - #662 Phase 3 adds <form> (from media-type
        // taxonomy / RDA carrier) and <digitalOrigin> ("born digital" /
        // "reformatted digital") when a digital_object row exists.
        if ($io->extent_and_medium || $this->hasDigitalObject((int) $io->id)) {
            $xml .= "  <physicalDescription>\n";
            foreach ($this->loadPhysicalForms((int) $io->id, $culture) as $form) {
                $xml .= '    <form authority="marcform">'.$this->escXml($form)."</form>\n";
            }
            if ($io->extent_and_medium) {
                $xml .= '    <extent>'.$this->escXml($io->extent_and_medium)."</extent>\n";
            }
            $digitalOrigin = $this->resolveDigitalOrigin((int) $io->id);
            if ($digitalOrigin !== null) {
                $xml .= '    <digitalOrigin>'.$this->escXml($digitalOrigin)."</digitalOrigin>\n";
            }
            $xml .= "  </physicalDescription>\n";
        }

        // Abstract
        if ($io->scope_and_content) {
            $xml .= '  <abstract>'.$this->escXml($io->scope_and_content)."</abstract>\n";
        }

        // Subjects + places + genres + temporal/cartographics - #662 Phase 3
        foreach ($subjects as $s) {
            $xml .= "  <subject>\n    <topic>".$this->escXml($s->name)."</topic>\n  </subject>\n";
        }
        foreach ($places as $p) {
            $xml .= "  <subject>\n    <geographic>".$this->escXml($p->name)."</geographic>\n  </subject>\n";
        }
        foreach ($this->loadTemporalSubjects((int) $io->id, $culture) as $t) {
            $xml .= "  <subject>\n    <temporal>".$this->escXml($t)."</temporal>\n  </subject>\n";
        }
        foreach ($this->loadCartographics((int) $io->id, $culture) as $coords) {
            $xml .= "  <subject>\n    <cartographics>\n      <coordinates>".$this->escXml($coords)."</coordinates>\n    </cartographics>\n  </subject>\n";
        }
        foreach ($genres as $g) {
            $xml .= '  <genre authority="lcgft">'.$this->escXml($g->name)."</genre>\n";
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

        // Record info - #662 Phase 3 adds descriptionStandard +
        // recordIdentifier, and uses the IO's actual created_at /
        // updated_at instead of "now".
        $xml .= "  <recordInfo>\n";
        $descStandard = $this->resolveDescriptionStandard($io);
        if ($descStandard !== '') {
            $xml .= '    <descriptionStandard>'.$this->escXml($descStandard)."</descriptionStandard>\n";
        }
        $xml .= '    <recordContentSource>'.$this->escXml(config('app.name', 'Heratio'))."</recordContentSource>\n";
        $recordCreation = ! empty($io->created_at)
            ? substr((string) $io->created_at, 0, 10)
            : gmdate('Y-m-d');
        $xml .= '    <recordCreationDate encoding="iso8601">'.$this->escXml($recordCreation)."</recordCreationDate>\n";
        if (! empty($io->updated_at)) {
            $xml .= '    <recordChangeDate encoding="iso8601">'.$this->escXml(substr((string) $io->updated_at, 0, 10))."</recordChangeDate>\n";
        }
        $recordIdValue = $io->identifier ?: ($io->slug ?: ('heratio-io-'.$io->id));
        $xml .= '    <recordIdentifier source="Heratio">'.$this->escXml($recordIdValue)."</recordIdentifier>\n";
        $xml .= '    <languageOfCataloging><languageTerm authority="iso639-2b">'.$this->escXml($this->resolveLanguageCode($culture) ?? $culture)."</languageTerm></languageOfCataloging>\n";
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
        $decoded = @unserialize($raw, ['allowed_classes' => false]);
        if (is_string($decoded)) {
            return $decoded;
        }
        if (is_array($decoded)) {
            return implode("\n\n", array_filter($decoded));
        }
        return (string) $raw;
    }

    /**
     * Map the IO level + (when present) its first digital-object MIME type
     * to one of the MODS 3.7 typeOfResource controlled values. Returns
     * null when no source data is available, the raw level name when a
     * sensible default cannot be deduced.
     *
     * MODS 3.7 typeOfResource vocabulary:
     *   text / cartographic / notated music / sound recording-musical /
     *   sound recording-nonmusical / sound recording / still image /
     *   moving image / three dimensional object / software, multimedia /
     *   mixed material
     */
    private function mapTypeOfResource($io, ?string $levelName): ?string
    {
        try {
            $mime = DB::table('digital_object')
                ->where('information_object_id', $io->id)
                ->orderBy('id')
                ->value('mime_type');
        } catch (\Throwable $e) {
            $mime = null;
        }

        if (is_string($mime) && $mime !== '') {
            $lower = strtolower($mime);
            if (str_starts_with($lower, 'image/')) {
                return 'still image';
            }
            if (str_starts_with($lower, 'video/')) {
                return 'moving image';
            }
            if (str_starts_with($lower, 'audio/')) {
                return 'sound recording';
            }
            if (str_starts_with($lower, 'model/') || str_starts_with($lower, 'application/x-3d')) {
                return 'three dimensional object';
            }
            if ($lower === 'application/pdf' || str_starts_with($lower, 'text/')) {
                return 'text';
            }
            if (str_starts_with($lower, 'application/')) {
                return 'software, multimedia';
            }
        }

        if ($levelName && in_array(strtolower($levelName), ['fonds', 'collection', 'sub-fonds', 'series', 'sub-series'], true)) {
            return 'mixed material';
        }

        return $levelName;
    }

    /**
     * Heratio cultures are short ISO 639-1 codes (e.g. "en", "af"). MODS
     * expects ISO 639-2b. This is a minimal lookup covering the languages
     * Heratio is actually shipped with; unknown inputs return null and
     * the caller falls back to the raw text.
     */
    private function resolveLanguageCode(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $key = strtolower(trim($value));
        $map = [
            'en' => 'eng', 'english' => 'eng',
            'af' => 'afr', 'afrikaans' => 'afr',
            'fr' => 'fre', 'french' => 'fre',
            'de' => 'ger', 'german' => 'ger',
            'pt' => 'por', 'portuguese' => 'por',
            'es' => 'spa', 'spanish' => 'spa',
            'nl' => 'dut', 'dutch' => 'dut',
            'it' => 'ita', 'italian' => 'ita',
            'zu' => 'zul', 'zulu' => 'zul',
            'xh' => 'xho', 'xhosa' => 'xho',
            'st' => 'sot', 'sotho' => 'sot',
            'tn' => 'tsn', 'tswana' => 'tsn',
            'sn' => 'sna', 'shona' => 'sna',
            'sw' => 'swa', 'swahili' => 'swa',
        ];

        return $map[$key] ?? null;
    }

    private function hasDigitalObject(int $ioId): bool
    {
        try {
            return DB::table('digital_object')->where('information_object_id', $ioId)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function loadPhysicalForms(int $ioId, string $culture): array
    {
        try {
            // Use the existing free-text property "mods:form" when an
            // operator has filled it in; otherwise infer from the
            // physical-object link.
            $raw = $this->loadFirstScalarProperty($ioId, 'mods:form', $culture);
            if ($raw !== '') {
                return array_filter(array_map('trim', preg_split('/\r?\n+/', $raw) ?: []));
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    private function resolveDigitalOrigin(int $ioId): ?string
    {
        if (! $this->hasDigitalObject($ioId)) {
            return null;
        }
        try {
            // Heuristic: when the digital_object row has a source filename
            // that matches a non-image / non-text MIME we assume "born digital".
            $do = DB::table('digital_object')
                ->where('information_object_id', $ioId)
                ->orderBy('id')
                ->select('mime_type', 'usage_id')
                ->first();
            if (! $do) {
                return null;
            }
            // usage_id = 1 (master) is typically reformatted from analogue
            // archival material; usage_id = 3 (reference) for born-digital.
            // No reliable signal in core - emit a safe default.
            return 'reformatted digital';
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function loadTemporalSubjects(int $ioId, string $culture): array
    {
        $raw = $this->loadFirstScalarProperty($ioId, 'mods:temporal', $culture);
        if ($raw === '') {
            return [];
        }

        return array_filter(array_map('trim', preg_split('/\r?\n+/', $raw) ?: []));
    }

    private function loadCartographics(int $ioId, string $culture): array
    {
        $raw = $this->loadFirstScalarProperty($ioId, 'mods:cartographics', $culture);
        if ($raw === '') {
            return [];
        }

        return array_filter(array_map('trim', preg_split('/\r?\n+/', $raw) ?: []));
    }

    private function resolveDescriptionStandard($io): string
    {
        if (! empty($io->source_standard)) {
            return (string) $io->source_standard;
        }
        try {
            if (! empty($io->display_standard_id)) {
                $name = DB::table('term_i18n')->where('id', $io->display_standard_id)->value('name');
                if ($name) {
                    return (string) $name;
                }
            }
        } catch (\Throwable $e) {
            // ignore - schema may not have display_standard_id locally
        }

        return 'ISAD(G)';
    }
}
