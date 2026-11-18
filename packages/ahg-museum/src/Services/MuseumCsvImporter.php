<?php

/**
 * MuseumCsvImporter - Museum (Spectrum 5.0) CSV importer for Heratio.
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

namespace AhgMuseum\Services;

use AhgCore\Services\Import\SectorCsvImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Museum sector CSV importer following Spectrum 5.0 standard.
 *
 * Migrated from AtoM's MuseumCsvImportCommand in ahgDataMigrationPlugin.
 * Maps CSV columns to Spectrum fields and creates information_object records
 * with events for production dates and maker/creator actors.
 * Saves museum-specific metadata where the museum add form keeps it
 * (museum_metadata, display_object_config, provenance_overview).
 */
class MuseumCsvImporter extends SectorCsvImporter
{
    public function getSectorName(): string
    {
        return 'museum';
    }

    public function getStandard(): string
    {
        return 'Spectrum 5.0';
    }

    public function getColumnMap(): array
    {
        return [
            'legacy_id'                       => 'legacyId',
            'parent_id'                       => 'parentId',
            'object_number'                   => 'objectNumber',
            'accession_number'                => 'objectNumber',
            'object_name'                     => 'objectName',
            'name'                            => 'title',
            'number_of_objects'               => 'numberOfObjects',
            'quantity'                        => 'numberOfObjects',
            'brief_description'               => 'briefDescription',
            'description'                     => 'briefDescription',
            'scope_and_content'               => 'briefDescription',
            'notes'                           => 'comments',
            'distinguishing_features'         => 'distinguishingFeatures',
            'object_production_person'        => 'objectProductionPerson',
            'maker'                           => 'objectProductionPerson',
            'creator'                         => 'objectProductionPerson',
            'artist'                          => 'objectProductionPerson',
            'maker_role'                      => 'objectProductionPersonRole',
            'object_production_organisation'  => 'objectProductionOrganisation',
            'manufacturer'                    => 'objectProductionOrganisation',
            'object_production_date'          => 'objectProductionDate',
            'date_made'                       => 'objectProductionDate',
            'production_date'                 => 'objectProductionDate',
            'date'                            => 'objectProductionDate',
            'object_production_place'         => 'objectProductionPlace',
            'place_made'                      => 'objectProductionPlace',
            'production_place'                => 'objectProductionPlace',
            'material'                        => 'materials',
            'medium'                          => 'materials',
            'measurement'                     => 'dimensions',
            'inscription'                     => 'inscriptions',
            'marks'                           => 'inscriptions',
            'history'                         => 'objectHistoryNote',
            'provenance'                      => 'objectHistoryNote',
            'ownership_history'               => 'ownershipHistory',
            'acquisition_method'              => 'acquisitionMethod',
            'acquisition_date'                => 'acquisitionDate',
            'acquisition_source'              => 'acquisitionSource',
            'donor'                           => 'acquisitionSource',
            'acquisition_reason'              => 'acquisitionReason',
            'current_location'                => 'currentLocation',
            'location'                        => 'currentLocation',
            'normal_location'                 => 'normalLocation',
            'condition_note'                  => 'conditionNote',
            'subjects'                        => 'subjectAccessPoints',
            'places'                          => 'placeAccessPoints',
            'names'                           => 'nameAccessPoints',
            'digital_object_path'             => 'digitalObjectPath',
            'digital_object_uri'              => 'digitalObjectURI',
            'image'                           => 'digitalObjectPath',
        ];
    }

    public function getRequiredColumns(): array
    {
        return ['objectNumber', 'objectName'];
    }

    protected function getI18nFieldMap(array $data): array
    {
        return array_filter([
            'title'                    => $data['title'] ?? $data['objectName'] ?? null,
            'extent_and_medium'        => $this->formatExtent($data),
            'scope_and_content'        => $data['briefDescription'] ?? $data['description'] ?? null,
            'archival_history'         => $data['objectHistoryNote'] ?? $data['provenance'] ?? null,
            'physical_characteristics' => $data['condition'] ?? $data['conditionNote'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
    }

    protected function formatExtent(array $data): ?string
    {
        $parts = [];

        if (!empty($data['numberOfObjects'])) {
            $parts[] = $data['numberOfObjects'] . ' object(s)';
        }

        if (!empty($data['materials'])) {
            $parts[] = 'Materials: ' . $data['materials'];
        }

        if (!empty($data['dimensions'])) {
            $parts[] = 'Dimensions: ' . $data['dimensions'];
        }

        return !empty($parts) ? implode('; ', $parts) : null;
    }

    /**
     * Resolve identifier: prefer objectNumber.
     */
    protected function resolveIdentifier(array $data): ?string
    {
        return $data['objectNumber'] ?? $data['identifier'] ?? null;
    }

    protected function createEvents(int $objectId, array $data): void
    {
        $dateRange = $data['objectProductionDate'] ?? $data['dateRange'] ?? $data['date'] ?? null;
        $creators = $data['objectProductionPerson'] ?? $data['creators'] ?? $data['creator'] ?? null;

        if (empty($dateRange) && empty($creators)) {
            return;
        }

        $actorId = null;
        if (!empty($creators)) {
            $actorId = $this->findOrCreateActor($creators);
        }

        $this->createEvent(
            $objectId,
            self::EVENT_TYPE_CREATION,
            $dateRange,
            $data['dateStart'] ?? null,
            $data['dateEnd'] ?? null,
            $actorId
        );
    }

    /**
     * Same table, key and columns as the museum add form (MuseumService::create):
     * museum_metadata keyed object_id, display_object_config marking the record
     * as museum, and acquisition on provenance_overview as the provenance form
     * stores it. heratio#1778.
     */
    protected function metadataRows(array $data): array
    {
        $acquisitionDate = $data['acquisitionDate'] ?? null;

        return [
            'museum_metadata' => ['object_id', [
                'object_type'                => $data['objectName'] ?? null,
                'components_count'           => $data['numberOfObjects'] ?? null,
                'materials'                  => $data['materials'] ?? null,
                'techniques'                 => $data['technique'] ?? $data['techniques'] ?? null,
                'dimensions'                 => $data['dimensions'] ?? null,
                'inscriptions'               => $data['inscriptions'] ?? null,
                'physical_appearance'        => $data['distinguishingFeatures'] ?? null,
                'creator_identity'           => $data['objectProductionPerson'] ?? $data['objectProductionOrganisation'] ?? null,
                'creator_role'               => $data['objectProductionPersonRole'] ?? null,
                'creation_date_display'      => $data['objectProductionDate'] ?? null,
                'creation_place'             => $data['objectProductionPlace'] ?? null,
                'ownership_history'          => $data['ownershipHistory'] ?? null,
                'current_location'           => $data['currentLocation'] ?? null,
                'location_within_repository' => $data['normalLocation'] ?? null,
                'condition_notes'            => $data['conditionNote'] ?? null,
            ]],
            'display_object_config' => ['object_id', ['object_type' => 'museum']],
            'provenance_overview' => ['information_object_id', [
                'acquisition_type'      => $this->acquisitionTypeCode($data['acquisitionMethod'] ?? null),
                'acquisition_date'      => $this->dateOrNull($acquisitionDate),
                'acquisition_date_text' => $acquisitionDate,
            ]],
        ];
    }

    /**
     * Acquisition method as a provenance_acquisition_type dropdown code
     * (matched on code or label); an unmatched value is kept as written.
     */
    protected function acquisitionTypeCode(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $code = DB::table('ahg_dropdown')
            ->where('taxonomy', 'provenance_acquisition_type')
            ->where(fn ($q) => $q->where('code', Str::snake(strtolower($value)))->orWhere('label', $value))
            ->value('code');

        return $code ?? mb_substr($value, 0, 50);
    }
}
