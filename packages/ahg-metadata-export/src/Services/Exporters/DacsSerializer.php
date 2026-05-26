<?php

/**
 * DacsSerializer - Describing Archives: A Content Standard (DACS) XML
 * serializer.
 *
 * DACS 2013 is the United States national archival content standard.
 * Like RAD it has no LoC-hosted XSD; the emitted document uses a
 * Heratio-native `<dacsDescription>` envelope that round-trips with
 * `DacsXmlImporter`.
 *
 * The serializer prefers values from the `ahg_io_dacs` sidecar and
 * falls back to the matching ISAD(G) column when the sidecar is blank,
 * so a fresh install yields a complete DACS document immediately while
 * still allowing DACS-specific overrides where they differ from ISAD(G).
 *
 * Issue #662 Phase 3.
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

class DacsSerializer
{
    use InformationObjectFetcher;

    public const NAMESPACE_URI = 'https://heratio.theahg.co.za/schemas/dacs/1.0';

    /**
     * Map of DACS `<element>` -> [sidecar column, IO/i18n fallback column].
     */
    public const FIELD_MAP = [
        'referenceCode' => ['reference_code', 'identifier'],
        'nameAndLocation' => ['name_and_location', null],
        'title' => ['title_dacs', 'title'],
        'date' => ['date_dacs', null],
        'extent' => ['extent', 'extent_and_medium'],
        'nameOfCreator' => ['name_of_creator', null],
        'scopeAndContent' => ['scope_and_content_dacs', 'scope_and_content'],
        'conditionsGoverningAccess' => ['conditions_governing_access', 'access_conditions'],
        'languagesOfMaterial' => ['languages_of_material', null],
        'biographicalOrHistorical' => ['biographical_or_historical', 'archival_history'],
        'immediateSourceOfAcquisition' => ['immediate_source_of_acquisition', 'acquisition'],
        'systemOfArrangement' => ['system_of_arrangement', 'arrangement'],
        'relatedArchivalMaterials' => ['related_archival_materials', 'related_units_of_description'],
        'publicationNote' => ['publication_note', null],
        'processingInformation' => ['processing_information', null],
        'rules' => ['dacs_rules', 'rules'],
    ];

    public function getFormat(): string
    {
        return 'dacs';
    }

    public function getSchemaUrl(): string
    {
        return self::NAMESPACE_URI.'/dacsDescription.xsd';
    }

    public function getNamespace(): string
    {
        return self::NAMESPACE_URI;
    }

    public function serializeRecord(int $objectId, string $culture = 'en'): string
    {
        $io = $this->fetchIo($objectId, $culture);
        if (! $io) {
            return '';
        }

        $sidecar = $this->loadSidecar($objectId);

        $xml = '<dacsDescription xmlns="'.$this->getNamespace().'"';
        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $xml .= ' xsi:schemaLocation="'.$this->getNamespace().' '.$this->getSchemaUrl().'"';
        $xml .= ' version="1.0">'."\n";

        // Always emit a recordIdentifier for round-trip matching.
        $idValue = $io->identifier ?: ('heratio-io-'.$io->id);
        $xml .= '  <recordIdentifier>'.$this->escXml($idValue)."</recordIdentifier>\n";

        foreach (self::FIELD_MAP as $element => $cols) {
            [$sidecarCol, $fallbackCol] = $cols;
            $value = '';
            if ($sidecar && isset($sidecar->{$sidecarCol}) && $sidecar->{$sidecarCol} !== null && $sidecar->{$sidecarCol} !== '') {
                $value = (string) $sidecar->{$sidecarCol};
            } elseif ($fallbackCol !== null && isset($io->{$fallbackCol}) && $io->{$fallbackCol} !== null && $io->{$fallbackCol} !== '') {
                $value = (string) $io->{$fallbackCol};
            }
            if ($value !== '') {
                $xml .= '  <'.$element.'>'.$this->escXml($value).'</'.$element.">\n";
            }
        }

        $xml .= '</dacsDescription>';

        return $xml;
    }

    private function loadSidecar(int $objectId)
    {
        try {
            if (! Schema::hasTable('ahg_io_dacs')) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return DB::table('ahg_io_dacs')->where('information_object_id', $objectId)->first();
    }
}
