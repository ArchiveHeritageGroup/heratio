<?php

/**
 * ArchivesCsvImporter - Archives (ISAD(G)) CSV importer for Heratio.
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

use AhgCore\Constants\TermId;
use AhgCore\Services\Import\SectorCsvImporter;
use Illuminate\Support\Facades\DB;

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
            'appraisal'                => 'appraisal',
            'accruals'                 => 'accruals',
            'publication_note'         => 'publicationNote',
            'general_note'             => 'generalNote',
            'archivist_note'           => 'archivistNote',
            'language_note'            => 'languageNote',
            'languages'                => 'language',
            'scripts'                  => 'script',
            'language_of_description'  => 'languageOfDescription',
            'script_of_description'    => 'scriptOfDescription',
            'publication_status'       => 'publicationStatus',
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
            'appraisal'                      => $data['appraisal'] ?? null,
            'accruals'                       => $data['accruals'] ?? null,
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

    /** CSV column => note type, as the archival CSV export writes them. */
    private const NOTE_COLUMNS = [
        'generalNote'     => TermId::NOTE_GENERAL,
        'publicationNote' => TermId::NOTE_PUBLICATION,
        'archivistNote'   => TermId::NOTE_ARCHIVIST,
        'languageNote'    => TermId::NOTE_LANGUAGE,
    ];

    /** CSV column => property name; values are |-separated codes, stored serialized. */
    private const LANGUAGE_COLUMNS = ['language', 'script', 'languageOfDescription', 'scriptOfDescription'];

    /** True while the row being saved is a new record (merge mode keeps what an existing one has). */
    private bool $creating = false;

    protected function createRecord(array $data): string
    {
        $this->creating = true;
        try {
            return parent::createRecord($data);
        } finally {
            $this->creating = false;
        }
    }

    /**
     * Archives has no sector table; this writes the parts of a description
     * that live outside information_object_i18n: notes, name access points,
     * languages and scripts, and publication status. Each is written the way
     * the description edit form writes it. A column given a value replaces
     * what the record had for it; an empty column leaves it alone, and in
     * merge mode an existing record keeps any value it already has.
     */
    protected function saveSectorMetadata(int $objectId, array $data): void
    {
        foreach (self::NOTE_COLUMNS as $column => $typeId) {
            $values = $this->listValue($data[$column] ?? '');
            if ($values && $this->mayReplace(DB::table('note')->where('object_id', $objectId)->where('type_id', $typeId))) {
                $this->replaceNotes($objectId, $typeId, $values);
            }
        }

        foreach (self::LANGUAGE_COLUMNS as $name) {
            $values = $this->listValue($data[$name] ?? '');
            if ($values && $this->mayReplace(DB::table('property')->where('object_id', $objectId)->where('name', $name))) {
                $this->replaceSerializedProperty($objectId, $name, $values);
            }
        }

        $names = $this->listValue($data['nameAccessPoints'] ?? '');
        if ($names && $this->mayReplace($this->nameRelations($objectId))) {
            $this->replaceNameAccessPoints($objectId, $names);
        }

        $status = trim((string) ($data['publicationStatus'] ?? ''));
        if ($status !== '' && ($this->creating || $this->updateMode !== 'merge')) {
            $this->setPublicationStatus($objectId, $status);
        }
    }

    /** |-separated cell to a list of non-empty values. */
    private function listValue(string $cell): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $cell)), fn ($v) => $v !== ''));
    }

    private function mayReplace($existing): bool
    {
        return $this->creating || $this->updateMode !== 'merge' || ! $existing->exists();
    }

    private function newObject(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Remove rows of $table (and their i18n rows and object rows) by id. */
    private function dropRows(string $table, ?string $i18nTable, array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        if ($i18nTable) {
            DB::table($i18nTable)->whereIn('id', $ids)->delete();
        }
        DB::table($table)->whereIn('id', $ids)->delete();
        DB::table('object')->whereIn('id', $ids)->delete();
    }

    private function replaceNotes(int $objectId, int $typeId, array $contents): void
    {
        $this->dropRows('note', 'note_i18n', DB::table('note')->where('object_id', $objectId)->where('type_id', $typeId)->pluck('id')->all());

        foreach ($contents as $content) {
            $noteId = $this->newObject('QubitNote');
            DB::table('note')->insert(['id' => $noteId, 'object_id' => $objectId, 'type_id' => $typeId, 'source_culture' => $this->culture]);
            DB::table('note_i18n')->insert(['id' => $noteId, 'culture' => $this->culture, 'content' => $content]);
        }
    }

    /**
     * ponytail: codes are stored as given (ISO 639 / ISO 15924, as the export
     * writes them); language names are not translated to codes. Add a lookup
     * if files with names turn up.
     */
    private function replaceSerializedProperty(int $objectId, string $name, array $values): void
    {
        $this->dropRows('property', 'property_i18n', DB::table('property')->where('object_id', $objectId)->where('name', $name)->pluck('id')->all());

        $propId = $this->newObject('QubitProperty');
        DB::table('property')->insert(['id' => $propId, 'object_id' => $objectId, 'name' => $name, 'source_culture' => $this->culture]);
        DB::table('property_i18n')->insert(['id' => $propId, 'culture' => $this->culture, 'value' => serialize(array_values(array_unique($values)))]);
    }

    private function nameRelations(int $objectId)
    {
        return DB::table('relation')->where('subject_id', $objectId)->where('type_id', TermId::RELATION_NAME_ACCESS_POINT);
    }

    private function replaceNameAccessPoints(int $objectId, array $names): void
    {
        $this->dropRows('relation', null, $this->nameRelations($objectId)->pluck('id')->all());

        $actorIds = [];
        foreach ($names as $name) {
            if ($actorId = $this->findOrCreateActor($name)) {
                $actorIds[$actorId] = true;
            }
        }
        foreach (array_keys($actorIds) as $actorId) {
            DB::table('relation')->insert([
                'id' => $this->newObject('QubitRelation'),
                'subject_id' => $objectId,
                'object_id' => $actorId,
                'type_id' => TermId::RELATION_NAME_ACCESS_POINT,
                'source_culture' => $this->culture,
            ]);
        }
    }

    /**
     * Publication status by its term name in the import culture or in
     * English ("Published", "Draft"), or by term id. An unknown value leaves
     * the status as it was and is reported in the import errors.
     */
    private function setPublicationStatus(int $objectId, string $value): void
    {
        $allowed = [TermId::PUBLICATION_STATUS_DRAFT, TermId::PUBLICATION_STATUS_PUBLISHED];
        $statusId = ctype_digit($value) && in_array((int) $value, $allowed, true)
            ? (int) $value
            : DB::table('term_i18n')->whereIn('id', $allowed)->whereIn('culture', array_unique([$this->culture, 'en']))
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->value('id');

        if (! $statusId) {
            $prefix = $this->currentRow > 0 ? "Row {$this->currentRow}: " : '';
            $this->errors[] = "{$prefix}unknown publication status '{$value}'; the status was left unchanged";

            return;
        }

        \AhgCore\Support\StatusRow::set($objectId, TermId::STATUS_TYPE_PUBLICATION, (int) $statusId, ['serial_number' => 0]);
    }
}
