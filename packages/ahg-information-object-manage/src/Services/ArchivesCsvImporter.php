<?php

/**
 * ArchivesCsvImporter - Archives (ISAD(G)) CSV importer for Heratio.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgInformationObjectManage\Services;

use AhgCore\Services\Import\SectorCsvImporter;

/**
 * Archives sector CSV importer following ISAD(G) descriptive standard.
 *
 * Migrated from AtoM's ArchivesCsvImportCommand in ahgDataMigrationPlugin.
 * Maps CSV columns to ISAD(G) fields and creates information_object records
 * with events for creation dates and creator actors.
 */
class ArchivesCsvImporter extends SectorCsvImporter
{
    /**
     * {@inheritdoc}
     */
    public function getSectorName(): string
    {
        return 'archive';
    }

    /**
     * {@inheritdoc}
     */
    public function getStandard(): string
    {
        return 'ISAD(G)';
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnMap(): array
    {
        return [
            'legacy_id'                => 'legacyId',
            'parent_id'                => 'parentId',
            'reference_code'           => 'identifier',
            'level_of_description'     => 'levelOfDescription',
            'level'                    => 'levelOfDescription',
            'title'                    => 'title',
            'extent_and_medium'        => 'extentAndMedium',
            'extent'                   => 'extentAndMedium',
            'date_range'               => 'dateRange',
            'dates'                    => 'dateRange',
            'date_start'               => 'dateStart',
            'start_date'               => 'dateStart',
            'date_end'                 => 'dateEnd',
            'end_date'                 => 'dateEnd',
            'creator'                  => 'creators',
            'admin_bio_history'        => 'adminBioHistory',
            'biography'                => 'adminBioHistory',
            'archival_history'         => 'archivalHistory',
            'custodial_history'        => 'archivalHistory',
            'immediate_source'         => 'acquisition',
            'scope_and_content'        => 'scopeAndContent',
            'description'              => 'scopeAndContent',
            'arrangement'              => 'arrangement',
            'access_conditions'        => 'accessConditions',
            'reproduction_conditions'  => 'reproductionConditions',
            'physical_characteristics' => 'physicalCharacteristics',
            'condition'                => 'physicalCharacteristics',
            'finding_aids'             => 'findingAids',
            'location_of_originals'    => 'locationOfOriginals',
            'location_of_copies'       => 'locationOfCopies',
            'related_units'            => 'relatedUnitsOfDescription',
            'publication_note'         => 'publicationNote',
            'general_note'             => 'notes',
            'archivist_note'           => 'archivistNote',
            'rules'                    => 'rules',
            'sources'                  => 'sources',
            'revision_history'         => 'revisionHistory',
            'date_of_description'      => 'dateOfDescription',
            'subjects'                 => 'subjectAccessPoints',
            'subject_access_points'    => 'subjectAccessPoints',
            'places'                   => 'placeAccessPoints',
            'place_access_points'      => 'placeAccessPoints',
            'names'                    => 'nameAccessPoints',
            'name_access_points'       => 'nameAccessPoints',
            'genres'                   => 'genreAccessPoints',
            'genre_access_points'      => 'genreAccessPoints',
            'digital_object_path'      => 'digitalObjectPath',
            'digital_object_uri'       => 'digitalObjectURI',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredColumns(): array
    {
        return ['identifier', 'title'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getI18nFieldMap(array $data): array
    {
        return array_filter([
            'title'                          => $data['title'] ?? null,
            'extent_and_medium'              => $this->formatExtent($data),
            'scope_and_content'              => $data['scopeAndContent'] ?? null,
            'archival_history'               => $data['archivalHistory'] ?? null,
            'acquisition'                    => $data['acquisition'] ?? null,
            'access_conditions'              => $data['accessConditions'] ?? null,
            'reproduction_conditions'        => $data['reproductionConditions'] ?? null,
            'physical_characteristics'       => $data['physicalCharacteristics'] ?? null,
            'finding_aids'                   => $data['findingAids'] ?? null,
            'arrangement'                    => $data['arrangement'] ?? null,
            'location_of_originals'          => $data['locationOfOriginals'] ?? null,
            'location_of_copies'             => $data['locationOfCopies'] ?? null,
            'related_units_of_description'   => $data['relatedUnitsOfDescription'] ?? null,
            'rules'                          => $data['rules'] ?? null,
            'sources'                        => $data['sources'] ?? null,
            'revision_history'               => $data['revisionHistory'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
    }

    /**
     * {@inheritdoc}
     */
    protected function formatExtent(array $data): ?string
    {
        $extent = $data['extentAndMedium'] ?? null;

        return (!empty($extent)) ? $extent : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function createEvents(int $objectId, array $data): void
    {
        $dateRange = $data['dateRange'] ?? null;
        $dateStart = $data['dateStart'] ?? null;
        $dateEnd = $data['dateEnd'] ?? null;
        $creators = $data['creators'] ?? null;

        if (empty($dateRange) && empty($dateStart) && empty($creators)) {
            return;
        }

        $actorId = null;
        if (!empty($creators)) {
            // Support pipe-delimited multiple creators; first creator gets the event
            $creatorList = array_filter(array_map('trim', explode('|', $creators)));
            if (!empty($creatorList)) {
                $actorId = $this->findOrCreateActor($creatorList[0]);

                // Additional creators get their own creation events without dates
                for ($i = 1; $i < count($creatorList); $i++) {
                    $additionalActorId = $this->findOrCreateActor($creatorList[$i]);
                    $this->createEvent(
                        $objectId,
                        self::EVENT_TYPE_CREATION,
                        null,
                        null,
                        null,
                        $additionalActorId
                    );
                }
            }
        }

        $this->createEvent(
            $objectId,
            self::EVENT_TYPE_CREATION,
            $dateRange,
            $dateStart,
            $dateEnd,
            $actorId
        );
    }

    /**
     * {@inheritdoc}
     *
     * Archives has no sector-specific metadata table; all data is stored
     * in information_object and information_object_i18n.
     */
    protected function saveSectorMetadata(int $objectId, array $data): void
    {
        // No-op: Archives stores all metadata in information_object_i18n.
    }
}
