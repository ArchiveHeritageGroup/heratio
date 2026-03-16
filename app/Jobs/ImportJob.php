<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Import Job — processes XML (EAD) and CSV file imports for information objects.
 *
 * Migrated from arFileImportJob. Handles:
 *  - EAD XML: parse archival descriptions, create information_object + i18n + object + slug
 *  - CSV: parse rows, map columns, create/update/delete records
 *
 * Logs progress to the `job` table (matching the existing schema).
 */
class ImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Job status IDs (term_i18n)
    const STATUS_IN_PROGRESS = 183;
    const STATUS_COMPLETED = 184;
    const STATUS_ERROR = 185;

    protected string $filePath;
    protected string $importType;   // 'xml' or 'csv'
    protected string $objectType;   // e.g. 'informationObject'
    protected string $updateType;   // 'create', 'match-and-update', 'delete-and-replace'
    protected ?string $parentSlug;

    protected int $jobRecordId = 0;
    protected array $errors = [];
    protected int $importedCount = 0;

    public function __construct(
        string $filePath,
        string $importType,
        string $objectType,
        string $updateType,
        ?string $parentSlug = null
    ) {
        $this->filePath = $filePath;
        $this->importType = $importType;
        $this->objectType = $objectType;
        $this->updateType = $updateType;
        $this->parentSlug = $parentSlug;
    }

    public function handle(): void
    {
        $this->createJobRecord();
        $this->log("Starting {$this->importType} import: {$this->objectType} (update: {$this->updateType})");

        try {
            $fullPath = storage_path('app/' . $this->filePath);

            if (!file_exists($fullPath)) {
                $this->logError("Import file not found: {$fullPath}");
                $this->markFailed();
                return;
            }

            $parentId = $this->resolveParentId();

            match ($this->importType) {
                'xml' => $this->importXml($fullPath, $parentId),
                'csv' => $this->importCsv($fullPath, $parentId),
                default => $this->logError("Unknown import type: {$this->importType}"),
            };

            if (empty($this->errors)) {
                $this->log("Import complete. {$this->importedCount} record(s) imported.");
                $this->markCompleted();
            } else {
                $this->log("Import finished with " . count($this->errors) . " error(s). {$this->importedCount} record(s) imported.");
                if ($this->importedCount > 0) {
                    $this->markCompleted();
                } else {
                    $this->markFailed();
                }
            }

            // Clean up temporary file
            @unlink($fullPath);

        } catch (\Throwable $e) {
            $this->logError("Fatal error: {$e->getMessage()}");
            $this->markFailed();
            Log::error('ImportJob failed', ['exception' => $e]);
        }
    }

    // ─── XML (EAD) Import ────────────────────────────────────────────

    protected function importXml(string $path, int $parentId): void
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);

        if ($xml === false) {
            $xmlErrors = libxml_get_errors();
            foreach ($xmlErrors as $err) {
                $this->logError("XML parse error line {$err->line}: " . trim($err->message));
            }
            libxml_clear_errors();
            return;
        }

        // Register EAD namespace (EAD 2002 may or may not have a namespace)
        $namespaces = $xml->getNamespaces(true);

        // Find archdesc element
        $archdesc = $xml->archdesc ?? null;
        if (!$archdesc) {
            $this->logError('No <archdesc> element found in EAD XML.');
            return;
        }

        $culture = app()->getLocale();

        // Import the top-level archival description
        $topId = $this->importEadComponent($archdesc, $parentId, $culture);

        if (!$topId) {
            $this->logError('Failed to import top-level <archdesc> element.');
            return;
        }

        // Import nested <dsc> components
        $dsc = $archdesc->dsc ?? null;
        if ($dsc) {
            $this->importEadChildren($dsc, $topId, $culture);
        }
    }

    protected function importEadComponent(\SimpleXMLElement $node, int $parentId, string $culture): ?int
    {
        $did = $node->did ?? null;

        $title = $did ? (string) ($did->unittitle ?? '') : '';
        $identifier = $did ? (string) ($did->unitid ?? '') : '';
        $extentAndMedium = '';
        if ($did && $did->physdesc) {
            $extentAndMedium = (string) ($did->physdesc->extent ?? $did->physdesc ?? '');
        }

        // Extract dates from unitdate
        $startDate = null;
        $endDate = null;
        $dateDisplay = '';
        if ($did && $did->unitdate) {
            $dateDisplay = (string) $did->unitdate;
            $normal = (string) ($did->unitdate['normal'] ?? '');
            if ($normal) {
                $parts = explode('/', $normal, 2);
                $startDate = $parts[0] ?? null;
                $endDate = $parts[1] ?? null;
            }
        }

        // Level of description
        $level = (string) ($node['level'] ?? 'otherlevel');
        $levelId = $this->resolveLevelId($level, $culture);

        // i18n fields from various EAD elements
        $scopeAndContent = $this->extractText($node, 'scopecontent');
        $arrangement = $this->extractText($node, 'arrangement');
        $archivalHistory = $this->extractText($node, 'custodhist');
        $acquisition = $this->extractText($node, 'acqinfo');
        $appraisal = $this->extractText($node, 'appraisal');
        $accruals = $this->extractText($node, 'accruals');
        $accessConditions = $this->extractText($node, 'accessrestrict');
        $reproductionConditions = $this->extractText($node, 'userestrict');
        $physicalCharacteristics = $this->extractText($node, 'phystech');
        $findingAids = $this->extractText($node, 'otherfindaid');
        $locationOfOriginals = $this->extractText($node, 'originalsloc');
        $locationOfCopies = $this->extractText($node, 'altformavail');
        $relatedUnits = $this->extractText($node, 'relatedmaterial');

        if ($this->updateType === 'match-and-update' && $identifier) {
            $existingId = $this->findByIdentifier($identifier);
            if ($existingId) {
                return $this->updateExisting($existingId, [
                    'title' => $title,
                    'identifier' => $identifier,
                    'level_of_description_id' => $levelId,
                    'extent_and_medium' => $extentAndMedium,
                    'scope_and_content' => $scopeAndContent,
                    'arrangement' => $arrangement,
                    'archival_history' => $archivalHistory,
                    'acquisition' => $acquisition,
                    'appraisal' => $appraisal,
                    'accruals' => $accruals,
                    'access_conditions' => $accessConditions,
                    'reproduction_conditions' => $reproductionConditions,
                    'physical_characteristics' => $physicalCharacteristics,
                    'finding_aids' => $findingAids,
                    'location_of_originals' => $locationOfOriginals,
                    'location_of_copies' => $locationOfCopies,
                    'related_units_of_description' => $relatedUnits,
                ], $culture);
            }
        }

        if ($this->updateType === 'delete-and-replace' && $identifier) {
            $existingId = $this->findByIdentifier($identifier);
            if ($existingId) {
                $this->deleteRecord($existingId);
            }
        }

        return $this->createRecord([
            'parent_id' => $parentId,
            'identifier' => $identifier ?: null,
            'level_of_description_id' => $levelId,
            'title' => $title ?: 'Untitled',
            'extent_and_medium' => $extentAndMedium,
            'scope_and_content' => $scopeAndContent,
            'arrangement' => $arrangement,
            'archival_history' => $archivalHistory,
            'acquisition' => $acquisition,
            'appraisal' => $appraisal,
            'accruals' => $accruals,
            'access_conditions' => $accessConditions,
            'reproduction_conditions' => $reproductionConditions,
            'physical_characteristics' => $physicalCharacteristics,
            'finding_aids' => $findingAids,
            'location_of_originals' => $locationOfOriginals,
            'location_of_copies' => $locationOfCopies,
            'related_units_of_description' => $relatedUnits,
        ], $culture, $startDate, $endDate, $dateDisplay);
    }

    protected function importEadChildren(\SimpleXMLElement $dsc, int $parentId, string $culture): void
    {
        foreach ($dsc->c as $component) {
            $childId = $this->importEadComponent($component, $parentId, $culture);
            if ($childId) {
                // Recursively import nested <c> components
                if (count($component->c) > 0) {
                    // Build a temporary wrapper to iterate nested children
                    foreach ($component->c as $subComponent) {
                        $subId = $this->importEadComponent($subComponent, $childId, $culture);
                        if ($subId && count($subComponent->c) > 0) {
                            $this->importEadChildrenRecursive($subComponent, $subId, $culture);
                        }
                    }
                }
            }
        }
    }

    protected function importEadChildrenRecursive(\SimpleXMLElement $parent, int $parentId, string $culture): void
    {
        foreach ($parent->c as $component) {
            $childId = $this->importEadComponent($component, $parentId, $culture);
            if ($childId && count($component->c) > 0) {
                $this->importEadChildrenRecursive($component, $childId, $culture);
            }
        }
    }

    protected function extractText(\SimpleXMLElement $node, string $element): string
    {
        $el = $node->{$element} ?? null;
        if (!$el) {
            return '';
        }
        // Get text from <p> children or direct content
        $texts = [];
        foreach ($el->p as $p) {
            $texts[] = trim((string) $p);
        }
        if (empty($texts)) {
            $texts[] = trim((string) $el);
        }
        return implode("\n\n", array_filter($texts));
    }

    // ─── CSV Import ──────────────────────────────────────────────────

    protected function importCsv(string $path, int $parentId): void
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->logError("Cannot open CSV file: {$path}");
            return;
        }

        $culture = app()->getLocale();

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->logError('CSV file is empty or has no header row.');
            fclose($handle);
            return;
        }

        // Normalise headers: trim whitespace, lowercase
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        // Map CSV column names to database fields
        $columnMap = [
            'legacyid'                       => '_legacy_id',
            'parentid'                       => '_parent_legacy_id',
            'identifier'                     => 'identifier',
            'title'                          => 'title',
            'levelofdescription'             => '_level_name',
            'extentandmedium'                => 'extent_and_medium',
            'repository'                     => '_repository_name',
            'archivalhistory'                => 'archival_history',
            'acquisition'                    => 'acquisition',
            'scopeandcontent'                => 'scope_and_content',
            'appraisal'                      => 'appraisal',
            'accruals'                       => 'accruals',
            'arrangement'                    => 'arrangement',
            'accessconditions'               => 'access_conditions',
            'reproductionconditions'         => 'reproduction_conditions',
            'physicalcharacteristics'         => 'physical_characteristics',
            'findingaids'                    => 'finding_aids',
            'locationoforiginals'            => 'location_of_originals',
            'locationofcopies'               => 'location_of_copies',
            'relatedunitsofdescription'      => 'related_units_of_description',
            'rules'                          => 'rules',
            'sources'                        => 'sources',
            'revisionhistory'                => 'revision_history',
            'institutionresponsibleidentifier' => 'institution_responsible_identifier',
            'alternatetitle'                 => 'alternate_title',
            'edition'                        => 'edition',
            'eventdates'                     => '_event_dates',
            'eventstarddates'                => '_event_start_dates',
            'eventenddates'                  => '_event_end_dates',
            'culture'                        => '_culture',
            'qubitparentslug'                => '_parent_slug',
        ];

        $rowNum = 1;
        $legacyIdMap = []; // legacy_id -> new object_id

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            if (count($row) !== count($headers)) {
                $this->logError("Row {$rowNum}: column count mismatch (expected " . count($headers) . ", got " . count($row) . "). Skipping.");
                continue;
            }

            try {
                $data = array_combine($headers, $row);
                $mapped = [];

                foreach ($columnMap as $csvCol => $dbField) {
                    if (isset($data[$csvCol])) {
                        $mapped[$dbField] = trim($data[$csvCol]);
                    }
                }

                $rowCulture = !empty($mapped['_culture']) ? $mapped['_culture'] : $culture;
                $legacyId = $mapped['_legacy_id'] ?? null;
                $parentLegacyId = $mapped['_parent_legacy_id'] ?? null;

                // Determine parent
                $rowParentId = $parentId;
                if (!empty($mapped['_parent_slug'])) {
                    $slugRow = DB::table('slug')->where('slug', $mapped['_parent_slug'])->first();
                    if ($slugRow) {
                        $rowParentId = $slugRow->object_id;
                    }
                } elseif ($parentLegacyId && isset($legacyIdMap[$parentLegacyId])) {
                    $rowParentId = $legacyIdMap[$parentLegacyId];
                }

                // Resolve level of description
                $levelId = null;
                if (!empty($mapped['_level_name'])) {
                    $levelId = $this->resolveLevelId($mapped['_level_name'], $rowCulture);
                }

                // Handle update types
                if ($this->updateType === 'match-and-update' && !empty($mapped['identifier'])) {
                    $existingId = $this->findByIdentifier($mapped['identifier']);
                    if ($existingId) {
                        $this->updateExisting($existingId, array_merge(
                            array_filter($mapped, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY),
                            ['level_of_description_id' => $levelId]
                        ), $rowCulture);
                        if ($legacyId) {
                            $legacyIdMap[$legacyId] = $existingId;
                        }
                        continue;
                    }
                }

                if ($this->updateType === 'delete-and-replace' && !empty($mapped['identifier'])) {
                    $existingId = $this->findByIdentifier($mapped['identifier']);
                    if ($existingId) {
                        $this->deleteRecord($existingId);
                    }
                }

                // Create new record
                $newId = $this->createRecord([
                    'parent_id' => $rowParentId,
                    'identifier' => $mapped['identifier'] ?? null,
                    'level_of_description_id' => $levelId,
                    'title' => $mapped['title'] ?? 'Untitled',
                    'extent_and_medium' => $mapped['extent_and_medium'] ?? null,
                    'scope_and_content' => $mapped['scope_and_content'] ?? null,
                    'arrangement' => $mapped['arrangement'] ?? null,
                    'archival_history' => $mapped['archival_history'] ?? null,
                    'acquisition' => $mapped['acquisition'] ?? null,
                    'appraisal' => $mapped['appraisal'] ?? null,
                    'accruals' => $mapped['accruals'] ?? null,
                    'access_conditions' => $mapped['access_conditions'] ?? null,
                    'reproduction_conditions' => $mapped['reproduction_conditions'] ?? null,
                    'physical_characteristics' => $mapped['physical_characteristics'] ?? null,
                    'finding_aids' => $mapped['finding_aids'] ?? null,
                    'location_of_originals' => $mapped['location_of_originals'] ?? null,
                    'location_of_copies' => $mapped['location_of_copies'] ?? null,
                    'related_units_of_description' => $mapped['related_units_of_description'] ?? null,
                    'rules' => $mapped['rules'] ?? null,
                    'sources' => $mapped['sources'] ?? null,
                    'revision_history' => $mapped['revision_history'] ?? null,
                    'institution_responsible_identifier' => $mapped['institution_responsible_identifier'] ?? null,
                    'alternate_title' => $mapped['alternate_title'] ?? null,
                    'edition' => $mapped['edition'] ?? null,
                ], $rowCulture);

                if ($newId && $legacyId) {
                    $legacyIdMap[$legacyId] = $newId;
                }

            } catch (\Throwable $e) {
                $this->logError("Row {$rowNum}: {$e->getMessage()}");
            }
        }

        fclose($handle);
    }

    // ─── Record CRUD helpers ─────────────────────────────────────────

    protected function createRecord(array $data, string $culture, ?string $startDate = null, ?string $endDate = null, ?string $dateDisplay = null): ?int
    {
        try {
            return DB::transaction(function () use ($data, $culture, $startDate, $endDate, $dateDisplay) {
                $parentId = $data['parent_id'] ?? 1;

                // Get parent's rgt for nested set positioning
                $parent = DB::table('information_object')
                    ->where('id', $parentId)
                    ->select('rgt')
                    ->first();

                if (!$parent) {
                    $this->logError("Parent ID {$parentId} not found.");
                    return null;
                }

                $newLft = $parent->rgt;
                $newRgt = $parent->rgt + 1;

                // Shift nested set values
                DB::table('information_object')
                    ->where('rgt', '>=', $parent->rgt)
                    ->increment('rgt', 2);

                DB::table('information_object')
                    ->where('lft', '>', $parent->rgt)
                    ->increment('lft', 2);

                // Create object record
                $objectId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitInformationObject',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Create information_object record
                DB::table('information_object')->insert([
                    'id' => $objectId,
                    'identifier' => $data['identifier'] ?? null,
                    'level_of_description_id' => $data['level_of_description_id'] ?? null,
                    'collection_type_id' => null,
                    'repository_id' => null,
                    'parent_id' => $parentId,
                    'description_status_id' => null,
                    'description_detail_id' => null,
                    'description_identifier' => null,
                    'source_standard' => null,
                    'display_standard_id' => null,
                    'lft' => $newLft,
                    'rgt' => $newRgt,
                    'source_culture' => $culture,
                ]);

                // Separate i18n fields from structural fields
                $i18nFields = [
                    'title', 'alternate_title', 'edition', 'extent_and_medium',
                    'archival_history', 'acquisition', 'scope_and_content',
                    'appraisal', 'accruals', 'arrangement', 'access_conditions',
                    'reproduction_conditions', 'physical_characteristics',
                    'finding_aids', 'location_of_originals', 'location_of_copies',
                    'related_units_of_description', 'institution_responsible_identifier',
                    'rules', 'sources', 'revision_history',
                ];

                $i18nData = ['id' => $objectId, 'culture' => $culture];
                foreach ($i18nFields as $field) {
                    $i18nData[$field] = $data[$field] ?? null;
                }

                DB::table('information_object_i18n')->insert($i18nData);

                // Generate slug
                $baseSlug = Str::slug($data['title'] ?? 'untitled');
                if (empty($baseSlug)) {
                    $baseSlug = 'untitled';
                }
                $slug = $baseSlug;
                $counter = 1;
                while (DB::table('slug')->where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }

                DB::table('slug')->insert([
                    'object_id' => $objectId,
                    'slug' => $slug,
                ]);

                // Create default publication status (Draft = 159)
                DB::table('status')->insert([
                    'object_id' => $objectId,
                    'type_id' => 158,
                    'status_id' => 159, // Draft
                    'serial_number' => 0,
                ]);

                // Create event for dates if provided
                if ($startDate || $endDate || $dateDisplay) {
                    $eventObjectId = DB::table('object')->insertGetId([
                        'class_name' => 'QubitEvent',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('event')->insert([
                        'id' => $eventObjectId,
                        'type_id' => 111, // Creation
                        'object_id' => $objectId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'source_culture' => $culture,
                    ]);

                    DB::table('event_i18n')->insert([
                        'id' => $eventObjectId,
                        'culture' => $culture,
                        'date' => $dateDisplay,
                    ]);
                }

                $this->importedCount++;
                return $objectId;
            });
        } catch (\Throwable $e) {
            $this->logError("Create record failed: {$e->getMessage()}");
            return null;
        }
    }

    protected function updateExisting(int $id, array $data, string $culture): int
    {
        try {
            // Update information_object structural fields
            $structuralFields = ['identifier', 'level_of_description_id', 'repository_id',
                'description_status_id', 'description_detail_id'];
            $ioUpdate = [];
            foreach ($structuralFields as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null) {
                    $ioUpdate[$field] = $data[$field];
                }
            }
            if (!empty($ioUpdate)) {
                DB::table('information_object')->where('id', $id)->update($ioUpdate);
            }

            // Update i18n fields
            $i18nFields = [
                'title', 'alternate_title', 'edition', 'extent_and_medium',
                'archival_history', 'acquisition', 'scope_and_content',
                'appraisal', 'accruals', 'arrangement', 'access_conditions',
                'reproduction_conditions', 'physical_characteristics',
                'finding_aids', 'location_of_originals', 'location_of_copies',
                'related_units_of_description', 'institution_responsible_identifier',
                'rules', 'sources', 'revision_history',
            ];

            $i18nUpdate = [];
            foreach ($i18nFields as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                    $i18nUpdate[$field] = $data[$field];
                }
            }

            if (!empty($i18nUpdate)) {
                $exists = DB::table('information_object_i18n')
                    ->where('id', $id)
                    ->where('culture', $culture)
                    ->exists();

                if ($exists) {
                    DB::table('information_object_i18n')
                        ->where('id', $id)
                        ->where('culture', $culture)
                        ->update($i18nUpdate);
                } else {
                    DB::table('information_object_i18n')->insert(
                        array_merge($i18nUpdate, ['id' => $id, 'culture' => $culture])
                    );
                }
            }

            // Touch the object
            DB::table('object')->where('id', $id)->update(['updated_at' => now()]);

            $this->importedCount++;
            $this->log("Updated existing record ID {$id}.");

        } catch (\Throwable $e) {
            $this->logError("Update record {$id} failed: {$e->getMessage()}");
        }

        return $id;
    }

    protected function deleteRecord(int $id): void
    {
        try {
            $record = DB::table('information_object')
                ->where('id', $id)
                ->select('lft', 'rgt')
                ->first();

            if (!$record) {
                return;
            }

            $width = $record->rgt - $record->lft + 1;

            // Collect descendant IDs
            $descendantIds = DB::table('information_object')
                ->whereBetween('lft', [$record->lft, $record->rgt])
                ->pluck('id')
                ->toArray();

            // Delete related records
            DB::table('information_object_i18n')->whereIn('id', $descendantIds)->delete();
            DB::table('status')->whereIn('object_id', $descendantIds)->delete();
            DB::table('information_object')->whereIn('id', $descendantIds)->delete();
            DB::table('slug')->whereIn('object_id', $descendantIds)->delete();
            DB::table('object')->whereIn('id', $descendantIds)->delete();

            // Close nested set gap
            DB::table('information_object')
                ->where('lft', '>', $record->rgt)
                ->decrement('lft', $width);

            DB::table('information_object')
                ->where('rgt', '>', $record->rgt)
                ->decrement('rgt', $width);

            $this->log("Deleted record ID {$id} and " . (count($descendantIds) - 1) . " descendant(s).");
        } catch (\Throwable $e) {
            $this->logError("Delete record {$id} failed: {$e->getMessage()}");
        }
    }

    // ─── Lookup helpers ──────────────────────────────────────────────

    protected function resolveParentId(): int
    {
        if ($this->parentSlug) {
            $slugRow = DB::table('slug')->where('slug', $this->parentSlug)->first();
            if ($slugRow) {
                return $slugRow->object_id;
            }
        }

        return 1; // Root information object
    }

    protected function resolveLevelId(string $levelName, string $culture): ?int
    {
        $map = [
            'fonds' => 'Fonds', 'subfonds' => 'Sub-fonds', 'collection' => 'Collection',
            'series' => 'Series', 'subseries' => 'Sub-series', 'file' => 'File',
            'item' => 'Item', 'otherlevel' => null,
        ];

        $normalized = $map[strtolower($levelName)] ?? $levelName;
        if (!$normalized) {
            return null;
        }

        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 34) // Levels of description
            ->where('term_i18n.culture', $culture)
            ->where('term_i18n.name', $normalized)
            ->value('term.id');
    }

    protected function findByIdentifier(string $identifier): ?int
    {
        return DB::table('information_object')
            ->where('identifier', $identifier)
            ->value('id');
    }

    // ─── Job record management ───────────────────────────────────────

    protected function createJobRecord(): void
    {
        // Create object row for the job (matches existing schema pattern)
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitJob',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->jobRecordId = DB::table('job')->insertGetId([
            'name' => 'App\\Jobs\\ImportJob',
            'status_id' => self::STATUS_IN_PROGRESS,
            'object_id' => $objectId,
            'user_id' => null,
            'output' => '',
            'completed_at' => null,
        ]);
    }

    protected function log(string $message): void
    {
        if ($this->jobRecordId) {
            $existing = DB::table('job')->where('id', $this->jobRecordId)->value('output') ?? '';
            DB::table('job')->where('id', $this->jobRecordId)->update([
                'output' => $existing . $message . "\n",
            ]);
        }
        Log::info("ImportJob: {$message}");
    }

    protected function logError(string $message): void
    {
        $this->errors[] = $message;
        $this->log("ERROR: {$message}");
    }

    protected function markCompleted(): void
    {
        if ($this->jobRecordId) {
            DB::table('job')->where('id', $this->jobRecordId)->update([
                'status_id' => self::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    protected function markFailed(): void
    {
        if ($this->jobRecordId) {
            DB::table('job')->where('id', $this->jobRecordId)->update([
                'status_id' => self::STATUS_ERROR,
                'completed_at' => now(),
            ]);
        }
    }
}
