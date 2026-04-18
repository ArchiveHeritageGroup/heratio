<?php

/**
 * DataMigrationService - Service for Heratio
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



namespace AhgDataMigration\Services;

use AhgCore\Constants\TermId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataMigrationService
{
    /**
     * Get all saved field mappings ordered by most recently updated.
     */
    public function getSavedMappings(): array
    {
        return DB::table('atom_data_mapping')
            ->select('id', 'name', 'target_type', 'category', 'description', 'field_mappings', 'source_template', 'is_default', 'created_at', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Get a single mapping with JSON-decoded field_mappings.
     */
    public function getMapping(int $id): ?array
    {
        $row = DB::table('atom_data_mapping')->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        $data = (array) $row;
        $data['field_mappings'] = json_decode($data['field_mappings'], true) ?: [];
        return $data;
    }

    /**
     * Insert or update a mapping in atom_data_mapping.
     */
    public function saveMapping(array $data): int
    {
        $fieldMappings = $data['field_mappings'] ?? [];
        if (is_array($fieldMappings)) {
            $fieldMappings = json_encode($fieldMappings);
        }

        $record = [
            'name'           => $data['name'],
            'target_type'    => $data['target_type'],
            'category'       => $data['category'] ?? 'Custom',
            'description'    => $data['description'] ?? null,
            'field_mappings' => $fieldMappings,
            'source_template'=> $data['source_template'] ?? null,
            'is_default'     => $data['is_default'] ?? 0,
            'created_by'     => $data['created_by'] ?? auth()->id(),
        ];

        if (!empty($data['id'])) {
            DB::table('atom_data_mapping')->where('id', $data['id'])->update($record);
            return (int) $data['id'];
        }

        return (int) DB::table('atom_data_mapping')->insertGetId($record);
    }

    /**
     * Delete a mapping by ID.
     */
    public function deleteMapping(int $id): bool
    {
        return DB::table('atom_data_mapping')->where('id', $id)->delete() > 0;
    }

    /**
     * Get migration jobs ordered by most recent.
     */
    public function getJobs(int $limit = 50): array
    {
        return DB::table('atom_migration_job')
            ->select(
                'id', 'name', 'target_type', 'source_file', 'source_format',
                'status', 'total_records', 'processed_records', 'imported_records',
                'updated_records', 'skipped_records', 'error_count',
                'progress_message', 'started_at', 'completed_at', 'created_at'
            )
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Get a single migration job.
     */
    public function getJob(int $id): ?array
    {
        $row = DB::table('atom_migration_job')->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        $data = (array) $row;
        $data['error_log'] = json_decode($data['error_log'] ?? '[]', true) ?: [];
        $data['mapping_snapshot'] = json_decode($data['mapping_snapshot'] ?? '{}', true) ?: [];
        $data['import_options'] = json_decode($data['import_options'] ?? '{}', true) ?: [];
        return $data;
    }

    /**
     * Create a new migration job with status pending.
     */
    public function createJob(array $data): int
    {
        $mappingSnapshot = $data['mapping_snapshot'] ?? [];
        if (is_array($mappingSnapshot)) {
            $mappingSnapshot = json_encode($mappingSnapshot);
        }
        $importOptions = $data['import_options'] ?? [];
        if (is_array($importOptions)) {
            $importOptions = json_encode($importOptions);
        }

        return (int) DB::table('atom_migration_job')->insertGetId([
            'name'             => $data['name'] ?? 'Import ' . now()->format('Y-m-d H:i:s'),
            'target_type'      => $data['target_type'],
            'source_file'      => $data['source_file'] ?? null,
            'source_format'    => $data['source_format'] ?? 'csv',
            'mapping_id'       => $data['mapping_id'] ?? null,
            'mapping_snapshot' => $mappingSnapshot,
            'import_options'   => $importOptions,
            'status'           => 'pending',
            'total_records'    => $data['total_records'] ?? 0,
            'processed_records'=> 0,
            'imported_records' => 0,
            'updated_records'  => 0,
            'skipped_records'  => 0,
            'error_count'      => 0,
            'error_log'        => json_encode([]),
            'created_by'       => $data['created_by'] ?? auth()->id(),
            'created_at'       => now(),
        ]);
    }

    /**
     * Update job progress fields.
     */
    public function updateJobProgress(int $id, array $data): void
    {
        $update = [];
        $allowed = [
            'status', 'processed_records', 'imported_records', 'updated_records',
            'skipped_records', 'error_count', 'progress_message', 'total_records',
            'started_at', 'completed_at', 'output_file',
        ];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (array_key_exists('error_log', $data)) {
            $update['error_log'] = is_array($data['error_log']) ? json_encode($data['error_log']) : $data['error_log'];
        }
        if (!empty($update)) {
            DB::table('atom_migration_job')->where('id', $id)->update($update);
        }
    }

    /**
     * Parse a CSV file and return headers + preview rows.
     */
    public function parseCSV(string $path, int $previewRows = 10): array
    {
        $result = ['headers' => [], 'rows' => [], 'totalRows' => 0];

        if (!file_exists($path)) {
            return $result;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $result;
        }

        // Detect BOM and skip if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return $result;
        }

        // Trim whitespace from headers
        $headers = array_map('trim', $headers);
        $result['headers'] = $headers;

        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            if ($rowCount <= $previewRows) {
                // Pad row to match header count
                while (count($row) < count($headers)) {
                    $row[] = '';
                }
                $result['rows'][] = array_combine($headers, array_slice($row, 0, count($headers)));
            }
        }
        $result['totalRows'] = $rowCount;

        fclose($handle);
        return $result;
    }

    /**
     * Return available target fields for a given entity type.
     */
    public function getTargetFields(string $targetType): array
    {
        $fields = match ($targetType) {
            'informationObject' => [
                'identifier'                        => 'Identifier',
                'title'                             => 'Title',
                'alternate_title'                   => 'Alternate title',
                'edition'                           => 'Edition',
                'extent_and_medium'                 => 'Extent and medium',
                'archival_history'                  => 'Archival history',
                'acquisition'                       => 'Immediate source of acquisition',
                'scope_and_content'                 => 'Scope and content',
                'appraisal'                         => 'Appraisal',
                'accruals'                          => 'Accruals',
                'arrangement'                       => 'Arrangement',
                'access_conditions'                 => 'Conditions governing access',
                'reproduction_conditions'           => 'Conditions governing reproduction',
                'physical_characteristics'          => 'Physical characteristics',
                'finding_aids'                      => 'Finding aids',
                'location_of_originals'             => 'Location of originals',
                'location_of_copies'                => 'Location of copies',
                'related_units_of_description'      => 'Related units of description',
                'institution_responsible_identifier' => 'Institution responsible identifier',
                'rules'                             => 'Rules or conventions',
                'sources'                           => 'Sources',
                'revision_history'                  => 'Revision history',
                'level_of_description_id'           => 'Level of description',
                'repository_id'                     => 'Repository',
                'parent_id'                         => 'Parent ID',
                'description_identifier'            => 'Description identifier',
                'source_standard'                   => 'Source standard',
                'legacyId'                          => 'Legacy ID',
                'parentId'                          => 'Parent legacy ID',
                'culture'                           => 'Culture',
            ],
            'actor' => [
                'authorized_form_of_name' => 'Authorized form of name',
                'dates_of_existence'      => 'Dates of existence',
                'history'                 => 'History',
                'places'                  => 'Places',
                'legal_status'            => 'Legal status',
                'functions'               => 'Functions',
                'mandates'                => 'Mandates',
                'internal_structures'     => 'Internal structures',
                'general_context'         => 'General context',
                'institution_responsible_identifier' => 'Institution responsible identifier',
                'rules'                   => 'Rules or conventions',
                'sources'                 => 'Sources',
                'revision_history'        => 'Revision history',
                'entity_type_id'          => 'Entity type',
                'description_identifier'  => 'Description identifier',
                'legacyId'                => 'Legacy ID',
                'culture'                 => 'Culture',
            ],
            'accession' => [
                'identifier'              => 'Identifier',
                'title'                   => 'Title',
                'date'                    => 'Date',
                'appraisal'               => 'Appraisal',
                'archival_history'        => 'Archival history',
                'location_information'    => 'Location information',
                'physical_characteristics' => 'Physical characteristics',
                'processing_notes'        => 'Processing notes',
                'received_extent_units'   => 'Received extent units',
                'scope_and_content'       => 'Scope and content',
                'source_of_acquisition'   => 'Source of acquisition',
                'acquisition_type_id'     => 'Acquisition type',
                'processing_priority_id'  => 'Processing priority',
                'processing_status_id'    => 'Processing status',
                'resource_type_id'        => 'Resource type',
                'legacyId'                => 'Legacy ID',
                'culture'                 => 'Culture',
            ],
            'repository' => [
                'authorized_form_of_name' => 'Authorized form of name',
                'dates_of_existence'      => 'Dates of existence',
                'history'                 => 'History',
                'places'                  => 'Places',
                'legal_status'            => 'Legal status',
                'functions'               => 'Functions',
                'mandates'                => 'Mandates',
                'internal_structures'     => 'Internal structures',
                'general_context'         => 'General context',
                'institution_responsible_identifier' => 'Institution responsible identifier',
                'rules'                   => 'Rules or conventions',
                'sources'                 => 'Sources',
                'revision_history'        => 'Revision history',
                'geocultural_context'     => 'Geocultural context',
                'collecting_policies'     => 'Collecting policies',
                'buildings'               => 'Buildings',
                'holdings'                => 'Holdings',
                'finding_aids'            => 'Finding aids',
                'opening_times'           => 'Opening times',
                'access_conditions'       => 'Access conditions',
                'disabled_access'         => 'Disabled access',
                'research_services'       => 'Research services',
                'reproduction_services'   => 'Reproduction services',
                'public_facilities'       => 'Public facilities',
                'desc_institution_identifier' => 'Description institution identifier',
                'desc_rules'              => 'Description rules',
                'desc_sources'            => 'Description sources',
                'desc_revision_history'   => 'Description revision history',
                'legacyId'                => 'Legacy ID',
                'culture'                 => 'Culture',
            ],
            default => [],
        };

        return $fields;
    }

    /**
     * Execute an import from a CSV file using the job's mapping and options.
     */
    public function executeImport(int $jobId): array
    {
        $job = $this->getJob($jobId);
        if (!$job) {
            return ['success' => false, 'message' => 'Job not found'];
        }

        $this->updateJobProgress($jobId, [
            'status'     => 'processing',
            'started_at' => now(),
            'progress_message' => 'Starting import...',
        ]);

        $sourcePath = $job['source_file'];
        if (!$sourcePath || !file_exists($sourcePath)) {
            $this->updateJobProgress($jobId, [
                'status'           => 'failed',
                'completed_at'     => now(),
                'progress_message' => 'Source file not found: ' . ($sourcePath ?: '(empty)'),
            ]);
            return ['success' => false, 'message' => 'Source file not found'];
        }

        $mapping = $job['mapping_snapshot'];
        $options = $job['import_options'];
        $targetType = $job['target_type'];
        $importType = $options['import_type'] ?? 'create';
        $culture = $options['culture'] ?? 'en';

        $csvData = $this->parseCSV($sourcePath, PHP_INT_MAX);
        $headers = $csvData['headers'];
        $totalRows = $csvData['totalRows'];

        $this->updateJobProgress($jobId, [
            'total_records'    => $totalRows,
            'progress_message' => "Parsed {$totalRows} rows from CSV",
        ]);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $legacyIdMap = [];

        // Re-open the file to process all rows
        $handle = fopen($sourcePath, 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        fgetcsv($handle); // skip headers

        $rowNum = 0;
        while (($csvRow = fgetcsv($handle)) !== false) {
            $rowNum++;
            try {
                // Pad row to match header count
                while (count($csvRow) < count($headers)) {
                    $csvRow[] = '';
                }
                $row = array_combine($headers, array_slice($csvRow, 0, count($headers)));

                // Apply field mapping: source column => target field
                $mapped = [];
                foreach ($mapping as $sourceCol => $targetField) {
                    if (!empty($targetField) && isset($row[$sourceCol])) {
                        $mapped[$targetField] = $row[$sourceCol];
                    }
                }

                if (empty($mapped)) {
                    $skipped++;
                    continue;
                }

                DB::beginTransaction();

                $recordId = $this->importRecord($targetType, $mapped, $importType, $culture, $legacyIdMap);

                if ($recordId > 0) {
                    // Track legacy ID for parent-child resolution
                    if (!empty($mapped['legacyId'])) {
                        $legacyIdMap[$mapped['legacyId']] = $recordId;
                    }
                    if ($importType === 'create') {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } else {
                    $skipped++;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = [
                    'row'     => $rowNum,
                    'message' => $e->getMessage(),
                ];
            }

            // Update progress every 25 rows
            if ($rowNum % 25 === 0) {
                $this->updateJobProgress($jobId, [
                    'processed_records' => $rowNum,
                    'imported_records'  => $imported,
                    'updated_records'   => $updated,
                    'skipped_records'   => $skipped,
                    'error_count'       => count($errors),
                    'progress_message'  => "Processing row {$rowNum} of {$totalRows}...",
                ]);
            }
        }
        fclose($handle);

        $finalStatus = count($errors) > 0 && $imported === 0 && $updated === 0 ? 'failed' : 'completed';

        $this->updateJobProgress($jobId, [
            'status'            => $finalStatus,
            'processed_records' => $rowNum,
            'imported_records'  => $imported,
            'updated_records'   => $updated,
            'skipped_records'   => $skipped,
            'error_count'       => count($errors),
            'error_log'         => $errors,
            'completed_at'      => now(),
            'progress_message'  => "Completed: {$imported} imported, {$updated} updated, {$skipped} skipped, " . count($errors) . " errors",
        ]);

        return [
            'success'  => true,
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => count($errors),
        ];
    }

    /**
     * Import a single record based on target type.
     */
    private function importRecord(string $targetType, array $mapped, string $importType, string $culture, array &$legacyIdMap): int
    {
        return match ($targetType) {
            'informationObject' => $this->importInformationObject($mapped, $importType, $culture, $legacyIdMap),
            'actor'             => $this->importActor($mapped, $importType, $culture),
            'accession'         => $this->importAccession($mapped, $importType, $culture),
            'repository'        => $this->importRepository($mapped, $importType, $culture, $legacyIdMap),
            default             => 0,
        };
    }

    /**
     * Import a single information object record.
     */
    private function importInformationObject(array $mapped, string $importType, string $culture, array &$legacyIdMap): int
    {
        $i18nFields = [
            'title', 'alternate_title', 'edition', 'extent_and_medium', 'archival_history',
            'acquisition', 'scope_and_content', 'appraisal', 'accruals', 'arrangement',
            'access_conditions', 'reproduction_conditions', 'physical_characteristics',
            'finding_aids', 'location_of_originals', 'location_of_copies',
            'related_units_of_description', 'institution_responsible_identifier',
            'rules', 'sources', 'revision_history',
        ];

        $ioFields = [
            'identifier', 'level_of_description_id', 'repository_id', 'parent_id',
            'description_identifier', 'source_standard',
        ];

        // Resolve parent from legacy ID
        if (!empty($mapped['parentId']) && empty($mapped['parent_id'])) {
            $parentLegacy = $mapped['parentId'];
            if (isset($legacyIdMap[$parentLegacy])) {
                $mapped['parent_id'] = $legacyIdMap[$parentLegacy];
            }
        }

        // Check for existing record if update mode
        $existingId = null;
        if ($importType === 'update' && !empty($mapped['identifier'])) {
            $existingId = DB::table('information_object')
                ->where('identifier', $mapped['identifier'])
                ->value('id');
        }

        if ($existingId && $importType === 'update') {
            // Update existing record
            $ioData = [];
            foreach ($ioFields as $f) {
                if (array_key_exists($f, $mapped) && $mapped[$f] !== '') {
                    $ioData[$f] = $mapped[$f];
                }
            }
            if (!empty($ioData)) {
                DB::table('information_object')->where('id', $existingId)->update($ioData);
            }

            $i18nData = [];
            foreach ($i18nFields as $f) {
                if (array_key_exists($f, $mapped)) {
                    $i18nData[$f] = $mapped[$f];
                }
            }
            if (!empty($i18nData)) {
                $exists = DB::table('information_object_i18n')
                    ->where('id', $existingId)
                    ->where('culture', $culture)
                    ->exists();
                if ($exists) {
                    DB::table('information_object_i18n')
                        ->where('id', $existingId)
                        ->where('culture', $culture)
                        ->update($i18nData);
                } else {
                    $i18nData['id'] = $existingId;
                    $i18nData['culture'] = $culture;
                    DB::table('information_object_i18n')->insert($i18nData);
                }
            }

            DB::table('object')->where('id', $existingId)->update(['updated_at' => now()]);
            return $existingId;
        }

        // Create new record
        $objectId = DB::table('object')->insertGetId([
            'class_name'  => 'QubitInformationObject',
            'created_at'  => now(),
            'updated_at'  => now(),
            'serial_number' => 0,
        ]);

        $ioData = ['id' => $objectId, 'source_culture' => $culture];
        foreach ($ioFields as $f) {
            if (array_key_exists($f, $mapped) && $mapped[$f] !== '') {
                $ioData[$f] = $mapped[$f];
            }
        }
        // Set default parent if not specified (root IO = 1)
        if (empty($ioData['parent_id'])) {
            $ioData['parent_id'] = 1;
        }
        // Calculate lft/rgt based on parent
        $parentRgt = DB::table('information_object')->where('id', $ioData['parent_id'])->value('rgt') ?? 2;
        DB::table('information_object')
            ->where('lft', '>=', $parentRgt)
            ->increment('lft', 2);
        DB::table('information_object')
            ->where('rgt', '>=', $parentRgt)
            ->increment('rgt', 2);
        $ioData['lft'] = $parentRgt;
        $ioData['rgt'] = $parentRgt + 1;

        DB::table('information_object')->insert($ioData);

        // Insert i18n
        $i18nData = ['id' => $objectId, 'culture' => $culture];
        foreach ($i18nFields as $f) {
            if (array_key_exists($f, $mapped)) {
                $i18nData[$f] = $mapped[$f];
            }
        }
        DB::table('information_object_i18n')->insert($i18nData);

        // Create slug
        $slugBase = Str::slug($mapped['title'] ?? $mapped['identifier'] ?? 'untitled');
        $slug = $this->generateUniqueSlug($slugBase);
        DB::table('slug')->insert([
            'object_id'     => $objectId,
            'slug'          => $slug,
            'serial_number' => 0,
        ]);

        // Create default publication status (draft)
        DB::table('status')->insert([
            'object_id'      => $objectId,
            'type_id'        => TermId::STATUS_TYPE_PUBLICATION,
            'status_id'      => TermId::PUBLICATION_STATUS_DRAFT,
            'serial_number'  => 0,
        ]);

        return $objectId;
    }

    /**
     * Import a single actor record.
     */
    private function importActor(array $mapped, string $importType, string $culture): int
    {
        $i18nFields = [
            'authorized_form_of_name', 'dates_of_existence', 'history', 'places',
            'legal_status', 'functions', 'mandates', 'internal_structures',
            'general_context', 'institution_responsible_identifier', 'rules',
            'sources', 'revision_history',
        ];

        $actorFields = ['entity_type_id', 'description_identifier'];

        // Check for existing record if update mode
        $existingId = null;
        if ($importType === 'update' && !empty($mapped['authorized_form_of_name'])) {
            $existingId = DB::table('actor_i18n')
                ->where('authorized_form_of_name', $mapped['authorized_form_of_name'])
                ->where('culture', $culture)
                ->value('id');
        }

        if ($existingId && $importType === 'update') {
            $i18nData = [];
            foreach ($i18nFields as $f) {
                if (array_key_exists($f, $mapped)) {
                    $i18nData[$f] = $mapped[$f];
                }
            }
            if (!empty($i18nData)) {
                DB::table('actor_i18n')
                    ->where('id', $existingId)
                    ->where('culture', $culture)
                    ->update($i18nData);
            }
            DB::table('object')->where('id', $existingId)->update(['updated_at' => now()]);
            return $existingId;
        }

        // Create new
        $objectId = DB::table('object')->insertGetId([
            'class_name'  => 'QubitActor',
            'created_at'  => now(),
            'updated_at'  => now(),
            'serial_number' => 0,
        ]);

        $actorData = ['id' => $objectId, 'source_culture' => $culture];
        foreach ($actorFields as $f) {
            if (array_key_exists($f, $mapped) && $mapped[$f] !== '') {
                $actorData[$f] = $mapped[$f];
            }
        }
        DB::table('actor')->insert($actorData);

        $i18nData = ['id' => $objectId, 'culture' => $culture];
        foreach ($i18nFields as $f) {
            if (array_key_exists($f, $mapped)) {
                $i18nData[$f] = $mapped[$f];
            }
        }
        DB::table('actor_i18n')->insert($i18nData);

        $slugBase = Str::slug($mapped['authorized_form_of_name'] ?? 'actor');
        $slug = $this->generateUniqueSlug($slugBase);
        DB::table('slug')->insert([
            'object_id'     => $objectId,
            'slug'          => $slug,
            'serial_number' => 0,
        ]);

        return $objectId;
    }

    /**
     * Import a single accession record.
     */
    private function importAccession(array $mapped, string $importType, string $culture): int
    {
        $i18nFields = [
            'title', 'appraisal', 'archival_history', 'location_information',
            'physical_characteristics', 'processing_notes', 'received_extent_units',
            'scope_and_content', 'source_of_acquisition',
        ];

        $accFields = [
            'identifier', 'date', 'acquisition_type_id', 'processing_priority_id',
            'processing_status_id', 'resource_type_id',
        ];

        // Check for existing record if update mode
        $existingId = null;
        if ($importType === 'update' && !empty($mapped['identifier'])) {
            $existingId = DB::table('accession')
                ->where('identifier', $mapped['identifier'])
                ->value('id');
        }

        if ($existingId && $importType === 'update') {
            $accData = [];
            foreach ($accFields as $f) {
                if (array_key_exists($f, $mapped) && $mapped[$f] !== '' && $f !== 'identifier') {
                    $accData[$f] = $mapped[$f];
                }
            }
            $accData['updated_at'] = now();
            if (!empty($accData)) {
                DB::table('accession')->where('id', $existingId)->update($accData);
            }

            $i18nData = [];
            foreach ($i18nFields as $f) {
                if (array_key_exists($f, $mapped)) {
                    $i18nData[$f] = $mapped[$f];
                }
            }
            if (!empty($i18nData)) {
                $exists = DB::table('accession_i18n')
                    ->where('id', $existingId)
                    ->where('culture', $culture)
                    ->exists();
                if ($exists) {
                    DB::table('accession_i18n')
                        ->where('id', $existingId)
                        ->where('culture', $culture)
                        ->update($i18nData);
                } else {
                    $i18nData['id'] = $existingId;
                    $i18nData['culture'] = $culture;
                    DB::table('accession_i18n')->insert($i18nData);
                }
            }
            return $existingId;
        }

        // Create new - accession uses object table for ID
        $objectId = DB::table('object')->insertGetId([
            'class_name'  => 'QubitAccession',
            'created_at'  => now(),
            'updated_at'  => now(),
            'serial_number' => 0,
        ]);

        $accData = [
            'id'             => $objectId,
            'source_culture' => $culture,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];
        foreach ($accFields as $f) {
            if (array_key_exists($f, $mapped) && $mapped[$f] !== '') {
                $accData[$f] = $mapped[$f];
            }
        }
        DB::table('accession')->insert($accData);

        $i18nData = ['id' => $objectId, 'culture' => $culture];
        foreach ($i18nFields as $f) {
            if (array_key_exists($f, $mapped)) {
                $i18nData[$f] = $mapped[$f];
            }
        }
        DB::table('accession_i18n')->insert($i18nData);

        $slugBase = Str::slug($mapped['identifier'] ?? $mapped['title'] ?? 'accession');
        $slug = $this->generateUniqueSlug($slugBase);
        DB::table('slug')->insert([
            'object_id'     => $objectId,
            'slug'          => $slug,
            'serial_number' => 0,
        ]);

        return $objectId;
    }

    /**
     * Import a single repository record (repositories are actors with class_name for repository).
     */
    private function importRepository(array $mapped, string $importType, string $culture, array &$legacyIdMap): int
    {
        $actorI18nFields = [
            'authorized_form_of_name', 'dates_of_existence', 'history', 'places',
            'legal_status', 'functions', 'mandates', 'internal_structures',
            'general_context', 'institution_responsible_identifier', 'rules',
            'sources', 'revision_history',
        ];

        $repoI18nFields = [
            'geocultural_context', 'collecting_policies', 'buildings', 'holdings',
            'finding_aids', 'opening_times', 'access_conditions', 'disabled_access',
            'research_services', 'reproduction_services', 'public_facilities',
            'desc_institution_identifier', 'desc_rules', 'desc_sources', 'desc_revision_history',
        ];

        // Check for existing record if update mode
        $existingId = null;
        if ($importType === 'update' && !empty($mapped['authorized_form_of_name'])) {
            $existingId = DB::table('actor_i18n')
                ->join('object', 'actor_i18n.id', '=', 'object.id')
                ->where('object.class_name', 'QubitRepository')
                ->where('actor_i18n.authorized_form_of_name', $mapped['authorized_form_of_name'])
                ->where('actor_i18n.culture', $culture)
                ->value('actor_i18n.id');
        }

        if ($existingId && $importType === 'update') {
            $i18nData = [];
            foreach ($actorI18nFields as $f) {
                if (array_key_exists($f, $mapped)) {
                    $i18nData[$f] = $mapped[$f];
                }
            }
            if (!empty($i18nData)) {
                DB::table('actor_i18n')
                    ->where('id', $existingId)
                    ->where('culture', $culture)
                    ->update($i18nData);
            }

            $repoData = [];
            foreach ($repoI18nFields as $f) {
                if (array_key_exists($f, $mapped)) {
                    $repoData[$f] = $mapped[$f];
                }
            }
            if (!empty($repoData)) {
                $exists = DB::table('repository_i18n')
                    ->where('id', $existingId)
                    ->where('culture', $culture)
                    ->exists();
                if ($exists) {
                    DB::table('repository_i18n')
                        ->where('id', $existingId)
                        ->where('culture', $culture)
                        ->update($repoData);
                } else {
                    $repoData['id'] = $existingId;
                    $repoData['culture'] = $culture;
                    DB::table('repository_i18n')->insert($repoData);
                }
            }

            DB::table('object')->where('id', $existingId)->update(['updated_at' => now()]);
            return $existingId;
        }

        // Create new
        $objectId = DB::table('object')->insertGetId([
            'class_name'  => 'QubitRepository',
            'created_at'  => now(),
            'updated_at'  => now(),
            'serial_number' => 0,
        ]);

        DB::table('actor')->insert([
            'id'             => $objectId,
            'source_culture' => $culture,
        ]);

        DB::table('repository')->insert([
            'id'             => $objectId,
            'source_culture' => $culture,
        ]);

        $actorI18n = ['id' => $objectId, 'culture' => $culture];
        foreach ($actorI18nFields as $f) {
            if (array_key_exists($f, $mapped)) {
                $actorI18n[$f] = $mapped[$f];
            }
        }
        DB::table('actor_i18n')->insert($actorI18n);

        $repoI18n = ['id' => $objectId, 'culture' => $culture];
        foreach ($repoI18nFields as $f) {
            if (array_key_exists($f, $mapped)) {
                $repoI18n[$f] = $mapped[$f];
            }
        }
        DB::table('repository_i18n')->insert($repoI18n);

        $slugBase = Str::slug($mapped['authorized_form_of_name'] ?? 'repository');
        $slug = $this->generateUniqueSlug($slugBase);
        DB::table('slug')->insert([
            'object_id'     => $objectId,
            'slug'          => $slug,
            'serial_number' => 0,
        ]);

        return $objectId;
    }

    /**
     * Generate a unique slug, appending a suffix if needed.
     */
    private function generateUniqueSlug(string $base): string
    {
        $slug = $base ?: 'untitled';
        $slug = mb_substr($slug, 0, 245);
        $original = $slug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $original . '-' . $counter;
            $counter++;
        }
        return $slug;
    }

    /**
     * Export entity records as a CSV streamed response.
     */
    public function batchExportCsv(string $entityType, array $filters = []): StreamedResponse
    {
        $query = $this->buildExportQuery($entityType, $filters);
        $columns = $this->getExportColumns($entityType);

        return new StreamedResponse(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');

            // Write BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Write header row
            fputcsv($handle, array_values($columns));

            // Stream rows in chunks
            $query->orderBy('object.created_at', 'desc')
                ->chunk(500, function ($rows) use ($handle, $columns) {
                    foreach ($rows as $row) {
                        $rowData = [];
                        foreach (array_keys($columns) as $col) {
                            $rowData[] = $row->$col ?? '';
                        }
                        fputcsv($handle, $rowData);
                    }
                });

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $entityType . '_export_' . date('Y-m-d_His') . '.csv"',
            'Cache-Control'       => 'no-cache',
        ]);
    }

    /**
     * Build the export query for a given entity type.
     */
    private function buildExportQuery(string $entityType, array $filters)
    {
        $culture = app()->getLocale();

        $query = match ($entityType) {
            'informationObject' => DB::table('information_object')
                ->join('object', 'information_object.id', '=', 'object.id')
                ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                    $join->on('information_object.id', '=', 'information_object_i18n.id')
                        ->where('information_object_i18n.culture', '=', $culture);
                })
                ->select(
                    'information_object.id',
                    'information_object.identifier',
                    'information_object_i18n.title',
                    'information_object_i18n.scope_and_content',
                    'information_object_i18n.archival_history',
                    'information_object_i18n.acquisition',
                    'information_object_i18n.extent_and_medium',
                    'information_object_i18n.appraisal',
                    'information_object_i18n.accruals',
                    'information_object_i18n.arrangement',
                    'information_object_i18n.access_conditions',
                    'information_object_i18n.reproduction_conditions',
                    'information_object_i18n.physical_characteristics',
                    'information_object_i18n.finding_aids',
                    'information_object_i18n.location_of_originals',
                    'information_object_i18n.location_of_copies',
                    'information_object_i18n.related_units_of_description',
                    'information_object_i18n.rules',
                    'information_object_i18n.sources',
                    'information_object_i18n.revision_history',
                    'object.created_at',
                    'object.updated_at'
                ),
            'actor' => DB::table('actor')
                ->join('object', 'actor.id', '=', 'object.id')
                ->leftJoin('actor_i18n', function ($join) use ($culture) {
                    $join->on('actor.id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', '=', $culture);
                })
                ->where('object.class_name', 'QubitActor')
                ->select(
                    'actor.id',
                    'actor_i18n.authorized_form_of_name',
                    'actor_i18n.dates_of_existence',
                    'actor_i18n.history',
                    'actor_i18n.places',
                    'actor_i18n.legal_status',
                    'actor_i18n.functions',
                    'actor_i18n.mandates',
                    'actor_i18n.internal_structures',
                    'actor_i18n.general_context',
                    'actor_i18n.rules',
                    'actor_i18n.sources',
                    'actor_i18n.revision_history',
                    'object.created_at',
                    'object.updated_at'
                ),
            'repository' => DB::table('repository')
                ->join('object', 'repository.id', '=', 'object.id')
                ->join('actor', 'repository.id', '=', 'actor.id')
                ->leftJoin('actor_i18n', function ($join) use ($culture) {
                    $join->on('actor.id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', '=', $culture);
                })
                ->leftJoin('repository_i18n', function ($join) use ($culture) {
                    $join->on('repository.id', '=', 'repository_i18n.id')
                        ->where('repository_i18n.culture', '=', $culture);
                })
                ->select(
                    'repository.id',
                    'actor_i18n.authorized_form_of_name',
                    'actor_i18n.history',
                    'repository_i18n.geocultural_context',
                    'repository_i18n.collecting_policies',
                    'repository_i18n.buildings',
                    'repository_i18n.holdings',
                    'repository_i18n.finding_aids',
                    'repository_i18n.opening_times',
                    'repository_i18n.access_conditions',
                    'object.created_at',
                    'object.updated_at'
                ),
            'accession' => DB::table('accession')
                ->join('object', 'accession.id', '=', 'object.id')
                ->leftJoin('accession_i18n', function ($join) use ($culture) {
                    $join->on('accession.id', '=', 'accession_i18n.id')
                        ->where('accession_i18n.culture', '=', $culture);
                })
                ->select(
                    'accession.id',
                    'accession.identifier',
                    'accession.date',
                    'accession_i18n.title',
                    'accession_i18n.scope_and_content',
                    'accession_i18n.archival_history',
                    'accession_i18n.appraisal',
                    'accession_i18n.source_of_acquisition',
                    'accession_i18n.location_information',
                    'accession_i18n.physical_characteristics',
                    'accession_i18n.processing_notes',
                    'accession_i18n.received_extent_units',
                    'object.created_at',
                    'object.updated_at'
                ),
            'donor' => DB::table('donor')
                ->join('object', 'donor.id', '=', 'object.id')
                ->join('actor', 'donor.id', '=', 'actor.id')
                ->leftJoin('actor_i18n', function ($join) use ($culture) {
                    $join->on('actor.id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', '=', $culture);
                })
                ->select(
                    'donor.id',
                    'actor_i18n.authorized_form_of_name',
                    'actor_i18n.history',
                    'actor_i18n.places',
                    'object.created_at',
                    'object.updated_at'
                ),
            'physicalObject' => DB::table('physical_object')
                ->join('object', 'physical_object.id', '=', 'object.id')
                ->leftJoin('physical_object_i18n', function ($join) use ($culture) {
                    $join->on('physical_object.id', '=', 'physical_object_i18n.id')
                        ->where('physical_object_i18n.culture', '=', $culture);
                })
                ->select(
                    'physical_object.id',
                    'physical_object_i18n.name',
                    'physical_object_i18n.description',
                    'physical_object_i18n.location',
                    'object.created_at',
                    'object.updated_at'
                ),
            default => DB::table('object')->whereRaw('1=0'),
        };

        // Apply date filters
        if (!empty($filters['date_from'])) {
            $query->where('object.created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('object.created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        return $query;
    }

    /**
     * Get column name => header label map for export.
     */
    private function getExportColumns(string $entityType): array
    {
        return match ($entityType) {
            'informationObject' => [
                'id' => 'ID', 'identifier' => 'Identifier', 'title' => 'Title',
                'scope_and_content' => 'Scope and content', 'archival_history' => 'Archival history',
                'acquisition' => 'Acquisition', 'extent_and_medium' => 'Extent and medium',
                'appraisal' => 'Appraisal', 'accruals' => 'Accruals', 'arrangement' => 'Arrangement',
                'access_conditions' => 'Access conditions', 'reproduction_conditions' => 'Reproduction conditions',
                'physical_characteristics' => 'Physical characteristics', 'finding_aids' => 'Finding aids',
                'location_of_originals' => 'Location of originals', 'location_of_copies' => 'Location of copies',
                'related_units_of_description' => 'Related units', 'rules' => 'Rules', 'sources' => 'Sources',
                'revision_history' => 'Revision history', 'created_at' => 'Created', 'updated_at' => 'Updated',
            ],
            'actor' => [
                'id' => 'ID', 'authorized_form_of_name' => 'Name', 'dates_of_existence' => 'Dates of existence',
                'history' => 'History', 'places' => 'Places', 'legal_status' => 'Legal status',
                'functions' => 'Functions', 'mandates' => 'Mandates', 'internal_structures' => 'Internal structures',
                'general_context' => 'General context', 'rules' => 'Rules', 'sources' => 'Sources',
                'revision_history' => 'Revision history', 'created_at' => 'Created', 'updated_at' => 'Updated',
            ],
            'repository' => [
                'id' => 'ID', 'authorized_form_of_name' => 'Name', 'history' => 'History',
                'geocultural_context' => 'Geocultural context', 'collecting_policies' => 'Collecting policies',
                'buildings' => 'Buildings', 'holdings' => 'Holdings', 'finding_aids' => 'Finding aids',
                'opening_times' => 'Opening times', 'access_conditions' => 'Access conditions',
                'created_at' => 'Created', 'updated_at' => 'Updated',
            ],
            'accession' => [
                'id' => 'ID', 'identifier' => 'Identifier', 'date' => 'Date', 'title' => 'Title',
                'scope_and_content' => 'Scope and content', 'archival_history' => 'Archival history',
                'appraisal' => 'Appraisal', 'source_of_acquisition' => 'Source of acquisition',
                'location_information' => 'Location', 'physical_characteristics' => 'Physical characteristics',
                'processing_notes' => 'Processing notes', 'received_extent_units' => 'Received extent',
                'created_at' => 'Created', 'updated_at' => 'Updated',
            ],
            'donor' => [
                'id' => 'ID', 'authorized_form_of_name' => 'Name', 'history' => 'History',
                'places' => 'Places', 'created_at' => 'Created', 'updated_at' => 'Updated',
            ],
            'physicalObject' => [
                'id' => 'ID', 'name' => 'Name', 'description' => 'Description',
                'location' => 'Location', 'created_at' => 'Created', 'updated_at' => 'Updated',
            ],
            default => ['id' => 'ID'],
        };
    }
}
