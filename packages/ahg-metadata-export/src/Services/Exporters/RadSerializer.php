<?php

/**
 * RadSerializer - Rules for Archival Description (RAD) XML serializer.
 *
 * RAD is the Canadian national archival descriptive standard. Heratio's
 * core archival description maps cleanly to RAD when the operator stores
 * RAD-specific overrides in the `ahg_io_rad` sidecar table. This
 * serializer prefers sidecar values; when absent it falls back to the
 * matching ISAD(G) column from `information_object` / `_i18n` so a
 * blank sidecar still produces a complete RAD document.
 *
 * Emits a Heratio-native `<radDescription>` envelope (RAD does not have
 * an officially LoC-hosted XSD; the closest standards are CAIN's
 * unpublished schema and individual Canadian institution profiles).
 * The container is round-trip compatible with `RadXmlImporter`.
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

class RadSerializer
{
    use InformationObjectFetcher;

    public const NAMESPACE_URI = 'https://heratio.theahg.co.za/schemas/rad/1.0';

    /**
     * Map of `<element>` -> [sidecar column, IO/i18n fallback column].
     * The fallback is null when no analogue exists in core. Used by
     * both the serializer and the importer to keep the field set in
     * one place.
     */
    public const FIELD_MAP = [
        'titleProper' => ['title_proper', 'title'],
        'parallelTitle' => ['parallel_title', null],
        'otherTitleInformation' => ['other_title_info', null],
        'statementsOfResponsibility' => ['statements_of_responsibility', null],
        'edition' => ['edition', 'edition'],
        'datesOfCreation' => ['dates_of_creation', null],
        'physicalDescription' => ['physical_description', 'extent_and_medium'],
        'custodialHistory' => ['custodial_history', 'archival_history'],
        'scopeAndContent' => ['scope_and_content_rad', 'scope_and_content'],
        'systemOfArrangement' => ['system_of_arrangement', 'arrangement'],
        'languageOfMaterial' => ['language_of_material', null],
        'findingAids' => ['finding_aids', 'finding_aids'],
        'accruals' => ['accruals', 'accruals'],
        'generalNote' => ['general_note', null],
        'archivistNote' => ['archivist_note', 'revision_history'],
        'rulesOrConventions' => ['rules_or_conventions', 'rules'],
    ];

    public function getFormat(): string
    {
        return 'rad';
    }

    public function getSchemaUrl(): string
    {
        return self::NAMESPACE_URI.'/radDescription.xsd';
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

        $xml = '<radDescription xmlns="'.$this->getNamespace().'"';
        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $xml .= ' xsi:schemaLocation="'.$this->getNamespace().' '.$this->getSchemaUrl().'"';
        $xml .= ' version="1.0">'."\n";

        $idValue = $io->identifier ?: ('heratio-io-'.$io->id);
        $xml .= '  <identifier>'.$this->escXml($idValue)."</identifier>\n";

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

        if ($sidecar && ! empty($sidecar->date_of_descriptions)) {
            $xml .= '  <dateOfDescriptions>'.$this->escXml((string) $sidecar->date_of_descriptions)."</dateOfDescriptions>\n";
        }
        if ($sidecar && ! empty($sidecar->status)) {
            $xml .= '  <descriptionStatus>'.$this->escXml((string) $sidecar->status)."</descriptionStatus>\n";
        }
        if ($sidecar && ! empty($sidecar->level_of_detail)) {
            $xml .= '  <levelOfDetail>'.$this->escXml((string) $sidecar->level_of_detail)."</levelOfDetail>\n";
        }

        $xml .= '</radDescription>';

        return $xml;
    }

    private function loadSidecar(int $objectId)
    {
        try {
            if (! Schema::hasTable('ahg_io_rad')) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return DB::table('ahg_io_rad')->where('information_object_id', $objectId)->first();
    }
}
