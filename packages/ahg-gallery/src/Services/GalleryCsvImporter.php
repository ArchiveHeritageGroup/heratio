<?php

/**
 * GalleryCsvImporter - Gallery (CCO) CSV importer for Heratio.
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

namespace AhgGallery\Services;

use AhgCore\Services\Import\SectorCsvImporter;

/**
 * Gallery sector CSV importer following CCO (Cataloging Cultural Objects) standard.
 *
 * Migrated from AtoM's GalleryCsvImportCommand in ahgDataMigrationPlugin.
 * Maps CSV columns to CCO fields and creates information_object records
 * with events for creation dates and artist/creator actors.
 * Saves gallery-specific metadata to gallery_metadata table.
 */
class GalleryCsvImporter extends SectorCsvImporter
{
    public function getSectorName(): string
    {
        return 'gallery';
    }

    public function getStandard(): string
    {
        return 'CCO';
    }

    public function getColumnMap(): array
    {
        return [
            'legacy_id'                => 'legacyId',
            'parent_id'                => 'parentId',
            'object_number'            => 'objectNumber',
            'accession_number'         => 'objectNumber',
            'work_type'                => 'workType',
            'object_type'              => 'workType',
            'medium'                   => 'workType',
            'title_type'               => 'titleType',
            'artist'                   => 'creator',
            'maker'                    => 'creator',
            'author'                   => 'creator',
            'creator_role'             => 'creatorRole',
            'artist_role'              => 'creatorRole',
            'creation_date'            => 'creationDate',
            'date'                     => 'creationDate',
            'date_made'                => 'creationDate',
            'creation_date_earliest'   => 'creationDateEarliest',
            'date_start'               => 'creationDateEarliest',
            'creation_date_latest'     => 'creationDateLatest',
            'date_end'                 => 'creationDateLatest',
            'creation_place'           => 'creationPlace',
            'place_made'               => 'creationPlace',
            'style_period'             => 'stylePeriod',
            'period'                   => 'stylePeriod',
            'style'                    => 'stylePeriod',
            'cultural_context'         => 'culturalContext',
            'culture'                  => 'culturalContext',
            'material'                 => 'materials',
            'dimensions'               => 'measurements',
            'measurement_type'         => 'measurementType',
            'measurement_unit'         => 'measurementUnit',
            'measurement_value'        => 'measurementValue',
            'description'              => 'subject',
            'inscription'              => 'inscriptions',
            'state_edition'            => 'stateEdition',
            'edition'                  => 'stateEdition',
            'ownership_history'        => 'provenance',
            'exhibition_history'       => 'exhibitionHistory',
            'exhibitions'              => 'exhibitionHistory',
            'bibliographic_references' => 'bibliographicReferences',
            'bibliography'             => 'bibliographicReferences',
            'related_works'            => 'relatedWorks',
            'condition_description'    => 'conditionDescription',
            'condition'                => 'conditionDescription',
            'treatment_history'        => 'treatmentHistory',
            'conservation'             => 'treatmentHistory',
            'credit_line'              => 'creditLine',
            'credit'                   => 'creditLine',
            'copyright'                => 'rights',
            'subjects'                 => 'subjectAccessPoints',
            'places'                   => 'placeAccessPoints',
            'names'                    => 'nameAccessPoints',
            'digital_object_path'      => 'digitalObjectPath',
            'digital_object_uri'       => 'digitalObjectURI',
            'image'                    => 'digitalObjectPath',
        ];
    }

    public function getRequiredColumns(): array
    {
        return ['objectNumber', 'title'];
    }

    protected function getI18nFieldMap(array $data): array
    {
        return array_filter([
            'title'                    => $data['title'] ?? null,
            'extent_and_medium'        => $this->formatExtent($data),
            'scope_and_content'        => $data['subject'] ?? $data['description'] ?? null,
            'archival_history'         => $data['provenance'] ?? null,
            'physical_characteristics' => $data['conditionDescription'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
    }

    protected function formatExtent(array $data): ?string
    {
        $parts = [];

        if (!empty($data['workType'])) {
            $parts[] = $data['workType'];
        }

        if (!empty($data['materials'])) {
            $parts[] = $data['materials'];
        }

        if (!empty($data['technique'])) {
            $parts[] = $data['technique'];
        }

        if (!empty($data['measurements'])) {
            $parts[] = $data['measurements'];
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
        $creator = $data['creator'] ?? $data['artist'] ?? null;
        $dateDisplay = $data['creationDate'] ?? null;
        $dateStart = $data['creationDateEarliest'] ?? null;
        $dateEnd = $data['creationDateLatest'] ?? null;

        if (empty($creator) && empty($dateDisplay) && empty($dateStart)) {
            return;
        }

        $actorId = null;
        if (!empty($creator)) {
            $actorId = $this->findOrCreateActor($creator);
        }

        $this->createEvent(
            $objectId,
            self::EVENT_TYPE_CREATION,
            $dateDisplay,
            $dateStart,
            $dateEnd,
            $actorId
        );
    }

    protected function saveSectorMetadata(int $objectId, array $data): void
    {
        $metadata = array_filter([
            'work_type'          => $data['workType'] ?? null,
            'style_period'       => $data['stylePeriod'] ?? null,
            'cultural_context'   => $data['culturalContext'] ?? null,
            'technique'          => $data['technique'] ?? null,
            'measurements'       => $data['measurements'] ?? null,
            'inscriptions'       => $data['inscriptions'] ?? null,
            'edition_number'     => $data['stateEdition'] ?? null,
            'exhibition_history' => $data['exhibitionHistory'] ?? null,
            'credit_line'        => $data['creditLine'] ?? null,
            'rights'             => $data['rights'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if (!empty($metadata)) {
            $this->upsertMetadata('gallery_metadata', 'information_object_id', $objectId, $metadata);
        }
    }
}
