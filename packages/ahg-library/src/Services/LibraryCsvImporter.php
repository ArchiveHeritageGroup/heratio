<?php

/**
 * LibraryCsvImporter - Library (MARC/RDA) CSV importer for Heratio.
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

namespace AhgLibrary\Services;

use AhgCore\Services\Import\SectorCsvImporter;

/**
 * Library sector CSV importer following MARC/RDA descriptive standard.
 *
 * Migrated from AtoM's LibraryCsvImportCommand in ahgDataMigrationPlugin.
 * Maps CSV columns to MARC/RDA fields and creates information_object records
 * with events for publication dates and author/publisher actors.
 * Saves library-specific metadata to library_metadata table.
 */
class LibraryCsvImporter extends SectorCsvImporter
{
    public function getSectorName(): string
    {
        return 'library';
    }

    public function getStandard(): string
    {
        return 'MARC/RDA';
    }

    public function getColumnMap(): array
    {
        return [
            'legacy_id'                    => 'legacyId',
            'parent_id'                    => 'parentId',
            'barcode'                      => 'identifier',
            'call_number'                  => 'callNumber',
            'classification'               => 'callNumber',
            'title_proper'                 => 'titleProper',
            'main_title'                   => 'titleProper',
            'parallel_title'               => 'parallelTitle',
            'other_title_info'             => 'otherTitleInfo',
            'subtitle'                     => 'otherTitleInfo',
            'statement_of_responsibility'  => 'statementOfResponsibility',
            'author'                       => 'statementOfResponsibility',
            'creator'                      => 'statementOfResponsibility',
            'edition_statement'            => 'editionStatement',
            'edition'                      => 'editionStatement',
            'place_of_publication'         => 'placeOfPublication',
            'publication_place'            => 'placeOfPublication',
            'date_of_publication'          => 'dateOfPublication',
            'publication_date'             => 'dateOfPublication',
            'date'                         => 'dateOfPublication',
            'year'                         => 'dateOfPublication',
            'copyright_date'               => 'copyrightDate',
            'pages'                        => 'extent',
            'pagination'                   => 'extent',
            'physical_description'         => 'extent',
            'size'                         => 'dimensions',
            'series_title'                 => 'seriesTitle',
            'series'                       => 'seriesTitle',
            'series_number'                => 'seriesNumber',
            'volume'                       => 'seriesNumber',
            'notes'                        => 'note',
            'general_note'                 => 'generalNote',
            'table_of_contents'            => 'tableOfContents',
            'contents'                     => 'tableOfContents',
            'abstract'                     => 'summary',
            'description'                  => 'summary',
            'scope_and_content'            => 'summary',
            'subjects'                     => 'subjectAccessPoints',
            'subject'                      => 'subjectAccessPoints',
            'places'                       => 'placeAccessPoints',
            'names'                        => 'nameAccessPoints',
            'genres'                       => 'genreAccessPoints',
            'genre'                        => 'genreAccessPoints',
            'digital_object_path'          => 'digitalObjectPath',
            'digital_object_uri'           => 'digitalObjectURI',
            'library'                      => 'repository',
            'physical_location'            => 'physicalLocation',
            'shelf_location'               => 'physicalLocation',
            'location'                     => 'physicalLocation',
        ];
    }

    public function getRequiredColumns(): array
    {
        return ['identifier', 'title'];
    }

    protected function getI18nFieldMap(array $data): array
    {
        return array_filter([
            'title'             => $data['title'] ?? $data['titleProper'] ?? null,
            'extent_and_medium' => $this->formatExtent($data),
            'scope_and_content' => $data['summary'] ?? $data['description'] ?? null,
            'finding_aids'      => $data['tableOfContents'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
    }

    protected function formatExtent(array $data): ?string
    {
        $parts = [];

        if (!empty($data['extent'])) {
            $parts[] = $data['extent'];
        }

        if (!empty($data['dimensions'])) {
            $parts[] = $data['dimensions'];
        }

        return !empty($parts) ? implode('; ', $parts) : null;
    }

    protected function createEvents(int $objectId, array $data): void
    {
        $pubDate = $data['dateOfPublication'] ?? $data['date'] ?? null;
        $publisher = $data['publisher'] ?? null;

        if ($pubDate || $publisher) {
            $actorId = null;
            if ($publisher) {
                $actorId = $this->findOrCreateActor($publisher);
            }

            $this->createEvent(
                $objectId,
                self::EVENT_TYPE_PUBLICATION,
                $pubDate,
                null,
                null,
                $actorId
            );
        }

        // Author event if different from publisher
        $author = $data['statementOfResponsibility'] ?? $data['author'] ?? null;
        if ($author && $author !== $publisher) {
            $authorActorId = $this->findOrCreateActor($author);

            $this->createEvent(
                $objectId,
                self::EVENT_TYPE_CREATION,
                $pubDate,
                null,
                null,
                $authorActorId
            );
        }
    }

    /**
     * Resolve identifier: prefer barcode/identifier, fallback to callNumber.
     */
    protected function resolveIdentifier(array $data): ?string
    {
        return $data['identifier'] ?? $data['callNumber'] ?? null;
    }

    protected function saveSectorMetadata(int $objectId, array $data): void
    {
        $metadata = array_filter([
            'isbn'                 => $data['isbn'] ?? null,
            'issn'                 => $data['issn'] ?? null,
            'call_number'          => $data['callNumber'] ?? null,
            'publisher'            => $data['publisher'] ?? null,
            'place_of_publication' => $data['placeOfPublication'] ?? null,
            'edition'              => $data['editionStatement'] ?? $data['edition'] ?? null,
            'series'               => $data['seriesTitle'] ?? null,
            'language'             => $data['language'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if (!empty($metadata)) {
            $this->upsertMetadata('library_metadata', 'information_object_id', $objectId, $metadata);
        }
    }
}
