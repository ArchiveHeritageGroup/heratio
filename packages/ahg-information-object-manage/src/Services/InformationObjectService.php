<?php

/**
 * InformationObjectService - Service for Heratio
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * InformationObjectService — core CRUD operations for information objects.
 *
 * Migrated from InformationObjectCrudService in the ahgInformationObjectManagePlugin.
 * Provides methods that the ImportJob and other services need without duplicating
 * the inline queries in InformationObjectController.
 */
class InformationObjectService
{
    // Publication status type
    const STATUS_TYPE_PUBLICATION = 158;
    const STATUS_DRAFT = 159;
    const STATUS_PUBLISHED = 160;

    // Event type IDs
    const EVENT_TYPE_CREATION = 111;

    // Name access point relation type
    const RELATION_NAME_ACCESS_POINT = 161;

    // Taxonomy IDs
    const TAXONOMY_LEVELS_OF_DESCRIPTION = 34;
    const TAXONOMY_SUBJECT_ACCESS_POINTS = 35;
    const TAXONOMY_PLACE_ACCESS_POINTS = 42;
    const TAXONOMY_GENRE_ACCESS_POINTS = 78;

    // Root information object
    const ROOT_ID = 1;

    /**
     * i18n field list (snake_case, matching information_object_i18n columns).
     */
    protected static array $i18nFields = [
        'title', 'alternate_title', 'edition', 'extent_and_medium',
        'archival_history', 'acquisition', 'scope_and_content',
        'appraisal', 'accruals', 'arrangement', 'access_conditions',
        'reproduction_conditions', 'physical_characteristics',
        'finding_aids', 'location_of_originals', 'location_of_copies',
        'related_units_of_description', 'institution_responsible_identifier',
        'rules', 'sources', 'revision_history',
    ];

    /**
     * camelCase -> snake_case field map for incoming data with camelCase keys.
     */
    protected static array $camelToSnakeMap = [
        'title' => 'title',
        'alternateTitle' => 'alternate_title',
        'edition' => 'edition',
        'extentAndMedium' => 'extent_and_medium',
        'archivalHistory' => 'archival_history',
        'acquisition' => 'acquisition',
        'scopeAndContent' => 'scope_and_content',
        'appraisal' => 'appraisal',
        'accruals' => 'accruals',
        'arrangement' => 'arrangement',
        'accessConditions' => 'access_conditions',
        'reproductionConditions' => 'reproduction_conditions',
        'physicalCharacteristics' => 'physical_characteristics',
        'findingAids' => 'finding_aids',
        'locationOfOriginals' => 'location_of_originals',
        'locationOfCopies' => 'location_of_copies',
        'relatedUnitsOfDescription' => 'related_units_of_description',
        'institutionResponsibleIdentifier' => 'institution_responsible_identifier',
        'rules' => 'rules',
        'sources' => 'sources',
        'revisionHistory' => 'revision_history',
    ];

    // ─── Read ────────────────────────────────────────────────────────

    /**
     * Get an information object by slug with core data.
     *
     * @return object|null  The IO record with i18n fields, slug, and object timestamps.
     */
    public static function getBySlug(string $slug, ?string $culture = null): ?object
    {
        $culture = $culture ?: app()->getLocale();
        $fallback = config('app.fallback_locale', 'en');

        return DB::table('information_object')
            ->leftJoin('information_object_i18n as ioi_cur', function ($j) use ($culture) {
                $j->on('ioi_cur.id', '=', 'information_object.id')
                    ->where('ioi_cur.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as ioi_fb', function ($j) use ($fallback) {
                $j->on('ioi_fb.id', '=', 'information_object.id')
                    ->where('ioi_fb.culture', '=', $fallback);
            })
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select(self::buildSelectColumns())
            ->first();
    }

    /**
     * Get an information object by ID with core data.
     *
     * @return object|null  The IO record with i18n fields, slug, and object timestamps.
     */
    public static function getById(int $id, ?string $culture = null): ?object
    {
        $culture = $culture ?: app()->getLocale();
        $fallback = config('app.fallback_locale', 'en');

        return DB::table('information_object')
            ->leftJoin('information_object_i18n as ioi_cur', function ($j) use ($culture) {
                $j->on('ioi_cur.id', '=', 'information_object.id')
                    ->where('ioi_cur.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as ioi_fb', function ($j) use ($fallback) {
                $j->on('ioi_fb.id', '=', 'information_object.id')
                    ->where('ioi_fb.culture', '=', $fallback);
            })
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.id', $id)
            ->select(self::buildSelectColumns())
            ->first();
    }

    // ─── Create ──────────────────────────────────────────────────────

    /**
     * Create a new information object.
     *
     * Data keys may be either snake_case (matching DB columns) or camelCase
     * (matching the field map from InformationObjectCrudService).
     *
     * Required: 'title' (or nothing — defaults to 'Untitled')
     * Optional: 'parent_id'/'parentId', 'identifier', 'level_of_description_id'/'levelOfDescriptionId',
     *           'repository_id'/'repositoryId', all i18n fields, etc.
     *
     * @return int  The new object ID.
     */
    public static function create(array $data, ?string $culture = null): int
    {
        $culture = $culture ?: app()->getLocale();
        $data = self::normalizeKeys($data);

        $newId = DB::transaction(function () use ($data, $culture) {
            $parentId = (int) ($data['parent_id'] ?? self::ROOT_ID);

            // 1. Determine nested set position (last child of parent)
            $parent = DB::table('information_object')
                ->where('id', $parentId)
                ->select('lft', 'rgt')
                ->first();

            if (!$parent) {
                throw new \RuntimeException("Parent information object ID {$parentId} not found.");
            }

            $newLft = $parent->rgt;
            $newRgt = $parent->rgt + 1;

            // Shift existing nested set values to make room
            DB::table('information_object')
                ->where('rgt', '>=', $parent->rgt)
                ->increment('rgt', 2);

            DB::table('information_object')
                ->where('lft', '>', $parent->rgt)
                ->increment('lft', 2);

            // 2. Insert object record
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitInformationObject',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Insert information_object record
            $ioInsert = [
                'id' => $objectId,
                'identifier' => $data['identifier'] ?? null,
                'level_of_description_id' => !empty($data['level_of_description_id']) ? (int) $data['level_of_description_id'] : null,
                'collection_type_id' => !empty($data['collection_type_id']) ? (int) $data['collection_type_id'] : null,
                'repository_id' => !empty($data['repository_id']) ? (int) $data['repository_id'] : null,
                'parent_id' => $parentId,
                'description_status_id' => !empty($data['description_status_id']) ? (int) $data['description_status_id'] : null,
                'description_detail_id' => !empty($data['description_detail_id']) ? (int) $data['description_detail_id'] : null,
                'description_identifier' => $data['description_identifier'] ?? null,
                'source_standard' => $data['source_standard'] ?? null,
                'display_standard_id' => !empty($data['display_standard_id']) ? (int) $data['display_standard_id'] : null,
                'lft' => $newLft,
                'rgt' => $newRgt,
                'source_culture' => $culture,
            ];
            if (array_key_exists('icip_sensitivity', $data) && $data['icip_sensitivity'] !== '') {
                $ioInsert['icip_sensitivity'] = $data['icip_sensitivity'];
            }
            DB::table('information_object')->insert($ioInsert);

            // 4. Insert i18n record
            $i18nData = ['id' => $objectId, 'culture' => $culture];
            foreach (self::$i18nFields as $field) {
                $i18nData[$field] = $data[$field] ?? null;
            }
            DB::table('information_object_i18n')->insert($i18nData);

            // 5. Generate slug
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

            // 6. Set default publication status (Draft)
            $pubStatusId = !empty($data['publication_status_id'])
                ? (int) $data['publication_status_id']
                : self::STATUS_DRAFT;

            DB::table('status')->insert([
                'object_id' => $objectId,
                'type_id' => self::STATUS_TYPE_PUBLICATION,
                'status_id' => $pubStatusId,
                'serial_number' => 0,
            ]);

            return $objectId;
        });

        \AhgCore\Support\AuditLog::captureCreate((int) $newId, 'information_object', self::auditSnapshot((int) $newId, $culture));
        return (int) $newId;
    }

    // ─── Update ──────────────────────────────────────────────────────

    /**
     * Update an existing information object.
     *
     * Only the provided keys are updated; missing keys are left unchanged.
     * Data keys may be either snake_case or camelCase.
     */
    /**
     * Flat snapshot of IO-update fields for the security_audit_log
     * before/after diff. Captures the structural columns + the i18n
     * narrative fields most edits touch. See packages/ahg-core/src/Support/AuditLog.php.
     * Public so InformationObjectController::update can reuse it — the
     * controller writes the IO directly rather than calling
     * InformationObjectService::update, so it needs to take its own
     * snapshot to feed AuditLog::captureEdit.
     */
    public static function auditSnapshot(int $id, string $culture): array
    {
        $io = (array) (DB::table('information_object')->where('id', $id)
            ->select('identifier', 'level_of_description_id', 'collection_type_id', 'repository_id',
                'description_status_id', 'description_detail_id', 'description_identifier',
                'source_standard', 'display_standard_id', 'icip_sensitivity', 'parent_id')
            ->first() ?? []);
        $i18n = (array) (DB::table('information_object_i18n')->where('id', $id)
            ->where('culture', $culture)
            ->select('title', 'alternate_title', 'extent_and_medium', 'scope_and_content',
                'archival_history', 'acquisition', 'access_conditions', 'reproduction_conditions',
                'physical_characteristics', 'arrangement', 'appraisal', 'accruals', 'finding_aids',
                'location_of_originals', 'location_of_copies', 'related_units_of_description',
                'rules', 'sources', 'revision_history', 'institution_responsible_identifier')
            ->first() ?? []);

        // Sub-entity counts so the main IO captureEdit also surfaces
        // delete-then-insert changes inside the same update() request:
        // "events: 3 → 5", "subject access points: 12 → 11", etc. Cheap
        // counts (no payload bloat); auditor sees that something changed
        // in each sub-entity bucket without needing per-row diffs.
        $counts = [
            'count_events' => DB::table('event')->where('object_id', $id)->count(),
            'count_notes' => DB::table('note')->where('object_id', $id)->count(),
            'count_subject_access_points' => DB::table('object_term_relation as otr')
                ->join('term', 'term.id', '=', 'otr.term_id')
                ->where('otr.object_id', $id)->where('term.taxonomy_id', 35)->count(),
            'count_place_access_points' => DB::table('object_term_relation as otr')
                ->join('term', 'term.id', '=', 'otr.term_id')
                ->where('otr.object_id', $id)->where('term.taxonomy_id', 42)->count(),
            'count_genre_access_points' => DB::table('object_term_relation as otr')
                ->join('term', 'term.id', '=', 'otr.term_id')
                ->where('otr.object_id', $id)->where('term.taxonomy_id', 78)->count(),
            'count_name_access_points' => DB::table('relation')
                ->where('subject_id', $id)->where('type_id', 161)->count(),
            'count_alternative_identifiers' => DB::table('property')
                ->where('object_id', $id)->where('name', 'alternativeIdentifiers')->count(),
            'count_children' => DB::table('information_object')->where('parent_id', $id)->count(),
        ];

        return array_merge($io, $i18n, $counts);
    }

    public static function update(int $id, array $data, ?string $culture = null): void
    {
        $culture = $culture ?: app()->getLocale();
        $data = self::normalizeKeys($data);

        $auditBefore = self::auditSnapshot($id, $culture);

        DB::transaction(function () use ($id, $data, $culture) {
            // 1. Update structural fields on information_object
            $structuralFields = [
                'identifier', 'level_of_description_id', 'collection_type_id',
                'repository_id', 'description_status_id', 'description_detail_id',
                'description_identifier', 'source_standard', 'display_standard_id',
                'icip_sensitivity',
            ];

            $ioUpdate = [];
            foreach ($structuralFields as $field) {
                if (array_key_exists($field, $data)) {
                    // ICIP cultural-sensitivity URI is preserved as-is when empty → null,
                    // unlike other structural fields which use ?: null.
                    if ($field === 'icip_sensitivity') {
                        $ioUpdate[$field] = ($data[$field] === '' ? null : $data[$field]);
                    } else {
                        $ioUpdate[$field] = $data[$field] ?: null;
                    }
                }
            }

            if (!empty($ioUpdate)) {
                DB::table('information_object')->where('id', $id)->update($ioUpdate);
            }

            // 2. Update i18n fields
            $i18nUpdate = [];
            foreach (self::$i18nFields as $field) {
                if (array_key_exists($field, $data)) {
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

            // 3. Update publication status if provided
            if (array_key_exists('publication_status_id', $data)) {
                DB::table('status')
                    ->where('object_id', $id)
                    ->where('type_id', self::STATUS_TYPE_PUBLICATION)
                    ->delete();

                DB::table('status')->insert([
                    'object_id' => $id,
                    'type_id' => self::STATUS_TYPE_PUBLICATION,
                    'status_id' => (int) ($data['publication_status_id'] ?: self::STATUS_DRAFT),
                    'serial_number' => 0,
                ]);
            }

            // 4. Touch the object
            DB::table('object')->where('id', $id)->update(['updated_at' => now()]);
        });

        $auditAfter = self::auditSnapshot($id, $culture);
        \AhgCore\Support\AuditLog::captureEdit($id, 'information_object', $auditBefore, $auditAfter);
    }

    // ─── Delete ──────────────────────────────────────────────────────

    /**
     * Delete an information object and all its descendants.
     *
     * Handles: nested set cleanup, i18n, slug, object, status, events, notes,
     * term relations, property records.
     */
    public static function delete(int $id): void
    {
        \AhgCore\Support\AuditLog::captureDelete($id, 'information_object', self::auditSnapshot($id, app()->getLocale()));

        DB::transaction(function () use ($id) {
            $record = DB::table('information_object')
                ->where('id', $id)
                ->select('lft', 'rgt')
                ->first();

            if (!$record) {
                return;
            }

            $width = $record->rgt - $record->lft + 1;

            // Collect all descendant IDs (nested set)
            $descendantIds = DB::table('information_object')
                ->whereBetween('lft', [$record->lft, $record->rgt])
                ->pluck('id')
                ->toArray();

            if (empty($descendantIds)) {
                return;
            }

            // Delete events and event_i18n for descendants
            $eventIds = DB::table('event')
                ->whereIn('object_id', $descendantIds)
                ->pluck('id')
                ->toArray();
            if (!empty($eventIds)) {
                DB::table('event_i18n')->whereIn('id', $eventIds)->delete();
                DB::table('event')->whereIn('id', $eventIds)->delete();
                DB::table('object')->whereIn('id', $eventIds)->delete();
            }

            // Delete notes and note_i18n for descendants
            $noteIds = DB::table('note')
                ->whereIn('object_id', $descendantIds)
                ->pluck('id')
                ->toArray();
            if (!empty($noteIds)) {
                DB::table('note_i18n')->whereIn('id', $noteIds)->delete();
                DB::table('note')->whereIn('id', $noteIds)->delete();
                DB::table('object')->whereIn('id', $noteIds)->delete();
            }

            // Delete term relations (access points)
            DB::table('object_term_relation')
                ->whereIn('object_id', $descendantIds)
                ->delete();

            // Delete relations (name access points, rights, etc.)
            DB::table('relation')
                ->where(function ($q) use ($descendantIds) {
                    $q->whereIn('subject_id', $descendantIds)
                        ->orWhereIn('object_id', $descendantIds);
                })
                ->delete();

            // Delete status records
            DB::table('status')
                ->whereIn('object_id', $descendantIds)
                ->delete();

            // Delete property records
            $propertyIds = DB::table('property')
                ->whereIn('object_id', $descendantIds)
                ->pluck('id')
                ->toArray();
            if (!empty($propertyIds)) {
                DB::table('property_i18n')->whereIn('id', $propertyIds)->delete();
                DB::table('property')->whereIn('object_id', $descendantIds)->delete();
            }

            // Delete digital objects
            try {
                $doIds = DB::table('digital_object')
                    ->whereIn('object_id', $descendantIds)
                    ->pluck('id')
                    ->toArray();
                if (!empty($doIds)) {
                    DB::table('digital_object')->whereIn('id', $doIds)->delete();
                    DB::table('object')->whereIn('id', $doIds)->delete();
                }
            } catch (\Exception $e) {
                // digital_object table may not exist in all installs
            }

            // Delete i18n rows
            DB::table('information_object_i18n')
                ->whereIn('id', $descendantIds)
                ->delete();

            // Delete information_object rows
            DB::table('information_object')
                ->whereIn('id', $descendantIds)
                ->delete();

            // Delete slug + object rows
            DB::table('slug')
                ->whereIn('object_id', $descendantIds)
                ->delete();

            DB::table('object')
                ->whereIn('id', $descendantIds)
                ->delete();

            // Close the gap in the nested set
            DB::table('information_object')
                ->where('lft', '>', $record->rgt)
                ->decrement('lft', $width);

            DB::table('information_object')
                ->where('rgt', '>', $record->rgt)
                ->decrement('rgt', $width);
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Normalize data keys from camelCase to snake_case.
     */
    protected static function normalizeKeys(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            // Check if it's a known camelCase key
            if (isset(self::$camelToSnakeMap[$key])) {
                $normalized[self::$camelToSnakeMap[$key]] = $value;
            } else {
                // Convert camelCase structural keys
                $snakeKey = match ($key) {
                    'parentId' => 'parent_id',
                    'levelOfDescriptionId' => 'level_of_description_id',
                    'collectionTypeId' => 'collection_type_id',
                    'repositoryId' => 'repository_id',
                    'descriptionStatusId' => 'description_status_id',
                    'descriptionDetailId' => 'description_detail_id',
                    'descriptionIdentifier' => 'description_identifier',
                    'sourceStandard' => 'source_standard',
                    'displayStandardId' => 'display_standard_id',
                    'publicationStatusId' => 'publication_status_id',
                    default => $key,
                };
                $normalized[$snakeKey] = $value;
            }
        }
        return $normalized;
    }

    /**
     * Build the standard SELECT column list for IO queries.
     */
    protected static function buildSelectColumns(): array
    {
        $columns = [
            'information_object.id',
            'information_object.identifier',
            'information_object.oai_local_identifier',
            'information_object.level_of_description_id',
            'information_object.collection_type_id',
            'information_object.repository_id',
            'information_object.parent_id',
            'information_object.description_status_id',
            'information_object.description_detail_id',
            'information_object.description_identifier',
            'information_object.source_standard',
            'information_object.display_standard_id',
            'information_object.lft',
            'information_object.rgt',
            'information_object.source_culture',
            'information_object.icip_sensitivity',
        ];

        foreach (self::$i18nFields as $field) {
            $columns[] = DB::raw("COALESCE(ioi_cur.{$field}, ioi_fb.{$field}) AS {$field}");
        }

        $columns[] = 'object.created_at';
        $columns[] = 'object.updated_at';
        $columns[] = 'slug.slug';

        return $columns;
    }
}
