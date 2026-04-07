<?php

/**
 * SectorCsvImporter - Base CSV importer for all GLAM/DAM sectors.
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

namespace AhgCore\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Base class for sector-specific CSV importers.
 *
 * Migrated from AtoM's ahgDataMigrationPlugin sector CSV import commands.
 * Each sector (Archives, Library, Museum, Gallery, DAM) extends this class
 * and overrides: getColumnMap(), getRequiredColumns(), getSectorName(),
 * setI18nFields(), formatExtent(), createEvents(), saveSectorMetadata().
 */
abstract class SectorCsvImporter
{
    // Taxonomy IDs (matching AtoM database)
    const TAXONOMY_LEVEL_OF_DESCRIPTION = 34;
    const TAXONOMY_SUBJECT = 35;
    const TAXONOMY_PLACE = 42;
    const TAXONOMY_GENRE = 78;

    // Event type IDs
    const EVENT_TYPE_CREATION = 111;
    const EVENT_TYPE_PUBLICATION = 114;

    // Status IDs
    const STATUS_TYPE_PUBLICATION = 158;
    const STATUS_DRAFT = 159;

    // Job status IDs
    const JOB_STATUS_IN_PROGRESS = 183;
    const JOB_STATUS_COMPLETED = 184;
    const JOB_STATUS_ERROR = 185;

    protected string $culture = 'en';
    protected ?int $repositoryId = null;
    protected array $customMapping = [];
    protected string $updateMode = 'skip';
    protected string $matchField = 'legacyId';

    protected array $counters = [
        'total' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    protected array $errors = [];
    protected array $legacyIdMap = [];
    protected int $jobRecordId = 0;

    // Callback for progress reporting (used by artisan commands)
    protected ?\Closure $progressCallback = null;

    // ─── Abstract methods (sector-specific) ─────────────────────────

    abstract public function getColumnMap(): array;
    abstract public function getRequiredColumns(): array;
    abstract public function getSectorName(): string;
    abstract public function getStandard(): string;

    /**
     * Return the i18n field mapping: mapped-key => information_object_i18n column.
     */
    abstract protected function getI18nFieldMap(array $data): array;

    /**
     * Format the extent_and_medium field from mapped row data.
     */
    abstract protected function formatExtent(array $data): ?string;

    /**
     * Create events (dates, creators) for a newly created record.
     */
    abstract protected function createEvents(int $objectId, array $data): void;

    /**
     * Save sector-specific metadata (library_item, museum_metadata, etc.).
     * No-op for Archives which has no sector metadata table.
     */
    abstract protected function saveSectorMetadata(int $objectId, array $data): void;

    // ─── Configuration ──────────────────────────────────────────────

    public function setCulture(string $culture): self
    {
        $this->culture = $culture;
        return $this;
    }

    public function setRepositoryId(?int $repositoryId): self
    {
        $this->repositoryId = $repositoryId;
        return $this;
    }

    public function setRepositoryBySlug(string $slug): self
    {
        $slugRecord = DB::table('slug')->where('slug', $slug)->first();
        if ($slugRecord) {
            $this->repositoryId = $slugRecord->object_id;
        }
        return $this;
    }

    public function setUpdateMode(string $mode): self
    {
        $this->updateMode = $mode;
        return $this;
    }

    public function setMatchField(string $field): self
    {
        $this->matchField = $field;
        return $this;
    }

    public function setCustomMapping(array $mapping): self
    {
        $this->customMapping = $mapping;
        return $this;
    }

    public function setProgressCallback(\Closure $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    public function getCounters(): array
    {
        return $this->counters;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    // ─── Validation ─────────────────────────────────────────────────

    /**
     * Validate a CSV file without importing. Returns validation report.
     */
    public function validate(string $filename): array
    {
        $handle = fopen($filename, 'r');
        if (!$handle) {
            return ['valid' => false, 'errors' => ['Cannot open file'], 'warnings' => [], 'total' => 0];
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['valid' => false, 'errors' => ['Empty file or no header row'], 'warnings' => [], 'total' => 0];
        }

        $header = array_map('trim', $header);
        $columnMap = array_merge($this->getColumnMap(), $this->customMapping);
        $required = $this->getRequiredColumns();

        $errors = [];
        $warnings = [];

        // Check required columns exist
        $mappedHeaders = [];
        foreach ($header as $col) {
            $mappedHeaders[] = $columnMap[$col] ?? $col;
        }

        foreach ($required as $req) {
            if (!in_array($req, $mappedHeaders)) {
                $errors[] = "Missing required column: {$req}";
            }
        }

        // Validate rows
        $rowNumber = 1;
        $total = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $total++;

            if (count($row) !== count($header)) {
                $errors[] = "Row {$rowNumber}: column count mismatch (expected " . count($header) . ", got " . count($row) . ")";
                continue;
            }

            $data = [];
            foreach ($header as $i => $col) {
                $targetCol = $columnMap[$col] ?? $col;
                $data[$targetCol] = trim($row[$i] ?? '');
            }

            // Check required fields have values
            foreach ($required as $req) {
                if (empty($data[$req] ?? '')) {
                    $warnings[] = "Row {$rowNumber}: empty required field '{$req}'";
                }
            }
        }

        fclose($handle);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'total' => $total,
        ];
    }

    // ─── Import ─────────────────────────────────────────────────────

    /**
     * Import a CSV file. Returns counters array.
     */
    public function import(string $filename, int $limit = PHP_INT_MAX, int $skip = 0): array
    {
        $handle = fopen($filename, 'r');
        if (!$handle) {
            $this->errors[] = "Cannot open file: {$filename}";
            return $this->counters;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            $this->errors[] = 'Empty file or no header row';
            fclose($handle);
            return $this->counters;
        }

        $header = array_map('trim', $header);
        $columnMap = array_merge($this->getColumnMap(), $this->customMapping);

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false && $this->counters['total'] < $limit) {
            $rowNumber++;

            if ($rowNumber <= $skip + 1) {
                continue;
            }

            $this->counters['total']++;

            try {
                if (count($row) !== count($header)) {
                    $this->errors[] = "Row {$rowNumber}: column count mismatch";
                    $this->counters['errors']++;
                    continue;
                }

                $data = [];
                foreach ($header as $i => $col) {
                    $value = trim($row[$i] ?? '');
                    $targetCol = $columnMap[$col] ?? $col;
                    $data[$targetCol] = $value;
                }

                $result = $this->processRow($data, $rowNumber);

                match ($result) {
                    'created' => $this->counters['imported']++,
                    'updated' => $this->counters['updated']++,
                    default => $this->counters['skipped']++,
                };
            } catch (\Throwable $e) {
                $this->counters['errors']++;
                $this->errors[] = "Row {$rowNumber}: {$e->getMessage()}";
            }

            if ($this->counters['total'] % 100 === 0) {
                $this->progress("Processed {$this->counters['total']} rows...");
            }
        }

        fclose($handle);
        return $this->counters;
    }

    // ─── Row processing ─────────────────────────────────────────────

    protected function processRow(array $data, int $rowNumber): string
    {
        $existingId = $this->findExisting($data);

        if ($existingId !== null) {
            if ($this->updateMode === 'skip') {
                return 'skipped';
            }
            return $this->updateRecord($existingId, $data);
        }

        return $this->createRecord($data);
    }

    protected function findExisting(array $data): ?int
    {
        $matchValue = $data[$this->matchField] ?? null;
        if (empty($matchValue)) {
            return null;
        }

        if ($this->matchField === 'legacyId') {
            return DB::table('keymap')
                ->where('source_name', 'legacyId')
                ->where('source_id', $matchValue)
                ->value('target_id');
        }

        if ($this->matchField === 'identifier') {
            return DB::table('information_object')
                ->where('identifier', $matchValue)
                ->value('id');
        }

        return null;
    }

    protected function createRecord(array $data): string
    {
        return DB::transaction(function () use ($data) {
            $parentId = $this->resolveParentId($data);
            $levelId = $this->resolveLevelId($data);
            $identifier = $this->resolveIdentifier($data);

            // Nested set positioning
            $parent = DB::table('information_object')
                ->where('id', $parentId)
                ->select('rgt')
                ->first();

            if (!$parent) {
                throw new \RuntimeException("Parent ID {$parentId} not found");
            }

            $newLft = $parent->rgt;
            $newRgt = $parent->rgt + 1;

            DB::table('information_object')
                ->where('rgt', '>=', $parent->rgt)
                ->increment('rgt', 2);

            DB::table('information_object')
                ->where('lft', '>', $parent->rgt)
                ->increment('lft', 2);

            // Create object
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitInformationObject',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create information_object
            DB::table('information_object')->insert([
                'id' => $objectId,
                'identifier' => $identifier,
                'level_of_description_id' => $levelId,
                'collection_type_id' => null,
                'repository_id' => $this->repositoryId,
                'parent_id' => $parentId,
                'description_status_id' => null,
                'description_detail_id' => null,
                'description_identifier' => null,
                'source_standard' => null,
                'display_standard_id' => null,
                'lft' => $newLft,
                'rgt' => $newRgt,
                'source_culture' => $this->culture,
            ]);

            // Create i18n record
            $i18nMap = $this->getI18nFieldMap($data);
            $i18nData = ['id' => $objectId, 'culture' => $this->culture];
            foreach ($i18nMap as $value) {
                // getI18nFieldMap returns dbColumn => value pairs
            }
            // Re-build: subclass returns [dbColumn => value]
            $i18nData = array_merge(['id' => $objectId, 'culture' => $this->culture], $i18nMap);
            DB::table('information_object_i18n')->insert($i18nData);

            // Generate slug
            $title = $i18nMap['title'] ?? 'untitled';
            $baseSlug = Str::slug($title) ?: 'untitled';
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            DB::table('slug')->insert(['object_id' => $objectId, 'slug' => $slug]);

            // Draft publication status
            DB::table('status')->insert([
                'object_id' => $objectId,
                'type_id' => self::STATUS_TYPE_PUBLICATION,
                'status_id' => self::STATUS_DRAFT,
                'serial_number' => 0,
            ]);

            // Save keymap for legacy ID tracking
            $legacyId = $data['legacyId'] ?? null;
            if (!empty($legacyId)) {
                $this->saveKeymap($objectId, $legacyId);
                $this->legacyIdMap[$legacyId] = $objectId;
            }

            // Sector-specific: events, access points, metadata
            $this->createEvents($objectId, $data);
            $this->createAccessPoints($objectId, $data);
            $this->saveSectorMetadata($objectId, $data);

            return 'created';
        });
    }

    protected function updateRecord(int $id, array $data): string
    {
        DB::transaction(function () use ($id, $data) {
            $identifier = $this->resolveIdentifier($data);
            $levelId = $this->resolveLevelId($data);

            // Update structural fields
            $ioUpdate = array_filter([
                'identifier' => $identifier,
                'level_of_description_id' => $levelId,
            ], fn($v) => $v !== null);

            if (!empty($ioUpdate)) {
                DB::table('information_object')->where('id', $id)->update($ioUpdate);
            }

            // Update i18n fields
            if ($this->updateMode !== 'merge') {
                $i18nMap = $this->getI18nFieldMap($data);
                $i18nUpdate = array_filter($i18nMap, fn($v) => $v !== null && $v !== '');

                if (!empty($i18nUpdate)) {
                    $exists = DB::table('information_object_i18n')
                        ->where('id', $id)
                        ->where('culture', $this->culture)
                        ->exists();

                    if ($exists) {
                        DB::table('information_object_i18n')
                            ->where('id', $id)
                            ->where('culture', $this->culture)
                            ->update($i18nUpdate);
                    } else {
                        DB::table('information_object_i18n')->insert(
                            array_merge($i18nUpdate, ['id' => $id, 'culture' => $this->culture])
                        );
                    }
                }
            }

            // Touch the object
            DB::table('object')->where('id', $id)->update(['updated_at' => now()]);

            // Update sector metadata
            $this->saveSectorMetadata($id, $data);
        });

        return 'updated';
    }

    // ─── Shared helpers ─────────────────────────────────────────────

    protected function resolveParentId(array $data): int
    {
        $parentId = $data['parentId'] ?? null;

        if (empty($parentId)) {
            return 1; // Root information object
        }

        // Check legacy ID map first (for in-session parent references)
        if (isset($this->legacyIdMap[$parentId])) {
            return $this->legacyIdMap[$parentId];
        }

        // Check keymap table
        $keymap = DB::table('keymap')
            ->where('source_name', 'legacyId')
            ->where('source_id', $parentId)
            ->value('target_id');
        if ($keymap) {
            return (int) $keymap;
        }

        // Check by identifier
        $byIdentifier = DB::table('information_object')
            ->where('identifier', $parentId)
            ->value('id');
        if ($byIdentifier) {
            return (int) $byIdentifier;
        }

        return 1;
    }

    /**
     * Resolve identifier from mapped data. Override in subclasses for sector-specific logic.
     */
    protected function resolveIdentifier(array $data): ?string
    {
        return $data['identifier'] ?? null;
    }

    protected function resolveLevelId(array $data): ?int
    {
        $level = $data['levelOfDescription'] ?? $data['level'] ?? null;
        if (empty($level)) {
            return null;
        }

        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', self::TAXONOMY_LEVEL_OF_DESCRIPTION)
            ->where('term_i18n.culture', $this->culture)
            ->whereRaw('LOWER(term_i18n.name) = ?', [strtolower($level)])
            ->value('term.id');
    }

    protected function saveKeymap(int $targetId, string $legacyId): void
    {
        DB::table('keymap')->insert([
            'source_name' => 'legacyId',
            'source_id' => $legacyId,
            'target_id' => $targetId,
            'target_name' => 'information_object',
        ]);
    }

    // ─── Access points ──────────────────────────────────────────────

    protected function createAccessPoints(int $objectId, array $data): void
    {
        $subjects = $data['subjectAccessPoints'] ?? $data['subjects'] ?? null;
        if ($subjects) {
            $this->createTermRelations($objectId, $subjects, self::TAXONOMY_SUBJECT);
        }

        $places = $data['placeAccessPoints'] ?? $data['places'] ?? null;
        if ($places) {
            $this->createTermRelations($objectId, $places, self::TAXONOMY_PLACE);
        }

        $genres = $data['genreAccessPoints'] ?? $data['genres'] ?? null;
        if ($genres) {
            $this->createTermRelations($objectId, $genres, self::TAXONOMY_GENRE);
        }
    }

    protected function createTermRelations(int $objectId, string $terms, int $taxonomyId): void
    {
        $termList = array_filter(array_map('trim', explode('|', $terms)));

        foreach ($termList as $termName) {
            $termId = $this->findOrCreateTerm($termName, $taxonomyId);
            if ($termId) {
                // Create object_term_relation
                $relObjectId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitObjectTermRelation',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('object_term_relation')->insert([
                    'id' => $relObjectId,
                    'object_id' => $objectId,
                    'term_id' => $termId,
                ]);
            }
        }
    }

    protected function findOrCreateTerm(string $name, int $taxonomyId): ?int
    {
        $existing = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.name', $name)
            ->where('term_i18n.culture', $this->culture)
            ->value('term.id');

        if ($existing) {
            return (int) $existing;
        }

        // Create new term
        $termObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitTerm',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('term')->insert([
            'id' => $termObjectId,
            'taxonomy_id' => $taxonomyId,
            'source_culture' => $this->culture,
        ]);

        DB::table('term_i18n')->insert([
            'id' => $termObjectId,
            'culture' => $this->culture,
            'name' => $name,
        ]);

        return $termObjectId;
    }

    // ─── Actor helper ───────────────────────────────────────────────

    protected function findOrCreateActor(string $name): ?int
    {
        $existing = DB::table('actor_i18n')
            ->where('authorized_form_of_name', $name)
            ->where('culture', $this->culture)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $actorObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitActor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('actor')->insert([
            'id' => $actorObjectId,
            'entity_type_id' => null,
            'description_status_id' => null,
            'description_detail_id' => null,
            'source_culture' => $this->culture,
        ]);

        DB::table('actor_i18n')->insert([
            'id' => $actorObjectId,
            'culture' => $this->culture,
            'authorized_form_of_name' => $name,
        ]);

        // Slug for the actor
        $baseSlug = Str::slug($name) ?: 'actor';
        $slug = $baseSlug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }
        DB::table('slug')->insert(['object_id' => $actorObjectId, 'slug' => $slug]);

        return $actorObjectId;
    }

    // ─── Event helper ───────────────────────────────────────────────

    protected function createEvent(int $objectId, int $typeId, ?string $dateDisplay, ?string $startDate = null, ?string $endDate = null, ?int $actorId = null): void
    {
        if (!$dateDisplay && !$startDate && !$actorId) {
            return;
        }

        $eventObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitEvent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('event')->insert([
            'id' => $eventObjectId,
            'type_id' => $typeId,
            'object_id' => $objectId,
            'actor_id' => $actorId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'source_culture' => $this->culture,
        ]);

        DB::table('event_i18n')->insert([
            'id' => $eventObjectId,
            'culture' => $this->culture,
            'date' => $dateDisplay,
        ]);
    }

    // ─── Upsert sector metadata helper ──────────────────────────────

    protected function upsertMetadata(string $table, string $fkColumn, int $objectId, array $metadata): void
    {
        try {
            $exists = DB::table($table)->where($fkColumn, $objectId)->exists();

            $metadata[$fkColumn] = $objectId;

            if ($exists) {
                DB::table($table)->where($fkColumn, $objectId)->update($metadata);
            } else {
                DB::table($table)->insert($metadata);
            }
        } catch (\Throwable $e) {
            Log::warning("SectorCsvImporter: Could not save metadata to {$table}: {$e->getMessage()}");
        }
    }

    // ─── Progress reporting ─────────────────────────────────────────

    protected function progress(string $message): void
    {
        if ($this->progressCallback) {
            ($this->progressCallback)($message);
        }
        Log::info("SectorCsvImporter [{$this->getSectorName()}]: {$message}");
    }

    // ─── Get available columns documentation ────────────────────────

    /**
     * Return documentation of all accepted CSV columns for this sector.
     */
    public function getColumnDocumentation(): array
    {
        $map = $this->getColumnMap();
        $docs = [];
        foreach ($map as $csvCol => $targetField) {
            $docs[$csvCol] = $targetField;
        }
        return $docs;
    }

    /**
     * Load a custom mapping profile from the database.
     */
    public function loadMappingProfile(int $mappingId): self
    {
        $mapping = DB::table('atom_data_mapping')
            ->where('id', $mappingId)
            ->first();

        if (!$mapping) {
            return $this;
        }

        $fieldMappings = json_decode($mapping->field_mappings ?? '[]', true);
        if (!is_array($fieldMappings)) {
            return $this;
        }

        foreach ($fieldMappings as $field) {
            if (isset($field['source'], $field['target'])) {
                $this->customMapping[$field['source']] = $field['target'];
            }
        }

        return $this;
    }
}
