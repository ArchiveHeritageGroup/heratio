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
 * Saves library-specific metadata to library_item, as the library add form does.
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
            'alternate_title'   => $data['parallelTitle'] ?? null,
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

    /**
     * Same table, key and columns as the library add form (LibraryService::create):
     * library_item keyed information_object_id, plus display_object_config
     * marking the record as library. heratio#1778.
     */
    protected function metadataRows(array $data): array
    {
        return [
            'library_item' => ['information_object_id', [
                'call_number'              => $data['callNumber'] ?? null,
                'isbn'                     => $data['isbn'] ?? null,
                'issn'                     => $data['issn'] ?? null,
                'subtitle'                 => $data['otherTitleInfo'] ?? null,
                'responsibility_statement' => $data['statementOfResponsibility'] ?? null,
                'edition_statement'        => $data['editionStatement'] ?? null,
                'publisher'                => $data['publisher'] ?? null,
                'publication_place'        => $data['placeOfPublication'] ?? null,
                'publication_date'         => $data['dateOfPublication'] ?? null,
                'copyright_date'           => $data['copyrightDate'] ?? null,
                'pagination'               => $data['extent'] ?? null,
                'dimensions'               => $data['dimensions'] ?? null,
                'series_title'             => $data['seriesTitle'] ?? null,
                'series_number'            => $data['seriesNumber'] ?? null,
                'general_note'             => $data['generalNote'] ?? $data['note'] ?? null,
                'contents_note'            => $data['tableOfContents'] ?? null,
                'summary'                  => $data['summary'] ?? null,
                'language'                 => $data['language'] ?? null,
                'shelf_location'           => $data['physicalLocation'] ?? null,
            ]],
            'display_object_config' => ['object_id', ['object_type' => 'library']],
        ];
    }
}
