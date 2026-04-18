<?php

/**
 * RicEntityService - CRUD for RiC-native entities
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

namespace AhgRic\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RicEntityService
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    // ================================================================
    // PLACE
    // ================================================================

    public function createPlace(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $id = $this->insertObjectRecord('RicPlace');
            $this->insertSlug($id, $data['name'] ?? 'untitled-place');

            DB::table('ric_place')->insert([
                'id' => $id,
                'type_id' => $data['type_id'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'authority_uri' => $data['authority_uri'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'term_id' => $data['term_id'] ?? null,
                'source_culture' => $this->culture,
            ]);

            DB::table('ric_place_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            return $id;
        });
    }

    public function updatePlace(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $this->updateEntityFields('ric_place', $id, $data, [
                'type_id', 'latitude', 'longitude', 'authority_uri', 'parent_id', 'term_id',
            ]);

            $this->upsertI18n('ric_place_i18n', $id, $data, [
                'name', 'description', 'address',
            ]);

            $this->touchObject($id);
        });
    }

    public function deletePlace(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table('ric_place_i18n')->where('id', $id)->delete();
            DB::table('ric_place')->where('id', $id)->delete();
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    public function getPlaceById(int $id): ?object
    {
        return DB::table('ric_place')
            ->join('object', 'ric_place.id', '=', 'object.id')
            ->join('slug', 'ric_place.id', '=', 'slug.object_id')
            ->leftJoin('ric_place_i18n', function ($j) {
                $j->on('ric_place.id', '=', 'ric_place_i18n.id')
                    ->where('ric_place_i18n.culture', '=', $this->culture);
            })
            ->where('ric_place.id', $id)
            ->select([
                'ric_place.*',
                'ric_place_i18n.name', 'ric_place_i18n.description', 'ric_place_i18n.address',
                'object.created_at', 'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    public function getPlaceBySlug(string $slug): ?object
    {
        $id = DB::table('slug')->where('slug', $slug)->value('object_id');
        return $id ? $this->getPlaceById($id) : null;
    }

    public function browsePlaces(array $params = []): object
    {
        return $this->browseEntities('ric_place', 'ric_place_i18n', 'name', 'RicPlace', $params);
    }

    /**
     * Flat list of every place as {id, name} for use in parent-picker dropdowns.
     * Pass $excludeId to hide a specific place (e.g. the one being edited, to
     * prevent self-loop in hierarchy).
     */
    public function listPlacesForPicker(?int $excludeId = null): array
    {
        $query = DB::table('ric_place as p')
            ->leftJoin('ric_place_i18n as i18n', function ($j) {
                $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
            })
            ->select('p.id', 'i18n.name')
            ->orderBy('i18n.name');
        if ($excludeId !== null) {
            $query->where('p.id', '!=', $excludeId);
        }
        return $query->get()->all();
    }

    // ================================================================
    // RULE
    // ================================================================

    public function createRule(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $id = $this->insertObjectRecord('RicRule');
            $this->insertSlug($id, $data['title'] ?? 'untitled-rule');

            DB::table('ric_rule')->insert([
                'id' => $id,
                'type_id' => $data['type_id'] ?? null,
                'jurisdiction' => $data['jurisdiction'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'authority_uri' => $data['authority_uri'] ?? null,
                'source_culture' => $this->culture,
            ]);

            DB::table('ric_rule_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'legislation' => $data['legislation'] ?? null,
                'sources' => $data['sources'] ?? null,
            ]);

            return $id;
        });
    }

    public function updateRule(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $this->updateEntityFields('ric_rule', $id, $data, [
                'type_id', 'jurisdiction', 'start_date', 'end_date', 'authority_uri',
            ]);

            $this->upsertI18n('ric_rule_i18n', $id, $data, [
                'title', 'description', 'legislation', 'sources',
            ]);

            $this->touchObject($id);
        });
    }

    public function deleteRule(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table('ric_rule_i18n')->where('id', $id)->delete();
            DB::table('ric_rule')->where('id', $id)->delete();
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    public function getRuleById(int $id): ?object
    {
        return DB::table('ric_rule')
            ->join('object', 'ric_rule.id', '=', 'object.id')
            ->join('slug', 'ric_rule.id', '=', 'slug.object_id')
            ->leftJoin('ric_rule_i18n', function ($j) {
                $j->on('ric_rule.id', '=', 'ric_rule_i18n.id')
                    ->where('ric_rule_i18n.culture', '=', $this->culture);
            })
            ->where('ric_rule.id', $id)
            ->select([
                'ric_rule.*',
                'ric_rule_i18n.title', 'ric_rule_i18n.description',
                'ric_rule_i18n.legislation', 'ric_rule_i18n.sources',
                'object.created_at', 'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    public function getRuleBySlug(string $slug): ?object
    {
        $id = DB::table('slug')->where('slug', $slug)->value('object_id');
        return $id ? $this->getRuleById($id) : null;
    }

    public function browseRules(array $params = []): object
    {
        return $this->browseEntities('ric_rule', 'ric_rule_i18n', 'title', 'RicRule', $params);
    }

    // ================================================================
    // ACTIVITY
    // ================================================================

    public function createActivity(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $id = $this->insertObjectRecord('RicActivity');
            $this->insertSlug($id, $data['name'] ?? 'untitled-activity');

            DB::table('ric_activity')->insert([
                'id' => $id,
                'type_id' => $data['type_id'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'place_id' => $data['place_id'] ?? null,
                'source_culture' => $this->culture,
            ]);

            DB::table('ric_activity_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'date_display' => $data['date_display'] ?? null,
            ]);

            return $id;
        });
    }

    public function updateActivity(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $this->updateEntityFields('ric_activity', $id, $data, [
                'type_id', 'start_date', 'end_date', 'place_id',
            ]);

            $this->upsertI18n('ric_activity_i18n', $id, $data, [
                'name', 'description', 'date_display',
            ]);

            $this->touchObject($id);
        });
    }

    public function deleteActivity(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table('ric_activity_i18n')->where('id', $id)->delete();
            DB::table('ric_activity')->where('id', $id)->delete();
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    public function getActivityById(int $id): ?object
    {
        return DB::table('ric_activity')
            ->join('object', 'ric_activity.id', '=', 'object.id')
            ->join('slug', 'ric_activity.id', '=', 'slug.object_id')
            ->leftJoin('ric_activity_i18n', function ($j) {
                $j->on('ric_activity.id', '=', 'ric_activity_i18n.id')
                    ->where('ric_activity_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('ric_place_i18n', function ($j) {
                $j->on('ric_activity.place_id', '=', 'ric_place_i18n.id')
                    ->where('ric_place_i18n.culture', '=', $this->culture);
            })
            ->where('ric_activity.id', $id)
            ->select([
                'ric_activity.*',
                'ric_activity_i18n.name', 'ric_activity_i18n.description', 'ric_activity_i18n.date_display',
                'ric_place_i18n.name as place_name',
                'object.created_at', 'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    public function getActivityBySlug(string $slug): ?object
    {
        $id = DB::table('slug')->where('slug', $slug)->value('object_id');
        return $id ? $this->getActivityById($id) : null;
    }

    public function browseActivities(array $params = []): object
    {
        return $this->browseEntities('ric_activity', 'ric_activity_i18n', 'name', 'RicActivity', $params);
    }

    // ================================================================
    // INSTANTIATION
    // ================================================================

    public function createInstantiation(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $id = $this->insertObjectRecord('RicInstantiation');
            $this->insertSlug($id, $data['title'] ?? 'untitled-instantiation');

            DB::table('ric_instantiation')->insert([
                'id' => $id,
                'record_id' => $data['record_id'] ?? null,
                'carrier_type' => $data['carrier_type'] ?? null,
                'mime_type' => $data['mime_type'] ?? null,
                'extent_value' => $data['extent_value'] ?? null,
                'extent_unit' => $data['extent_unit'] ?? null,
                'digital_object_id' => $data['digital_object_id'] ?? null,
                'source_culture' => $this->culture,
            ]);

            DB::table('ric_instantiation_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'technical_characteristics' => $data['technical_characteristics'] ?? null,
                'production_technical_characteristics' => $data['production_technical_characteristics'] ?? null,
            ]);

            return $id;
        });
    }

    public function updateInstantiation(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $this->updateEntityFields('ric_instantiation', $id, $data, [
                'record_id', 'carrier_type', 'mime_type', 'extent_value', 'extent_unit', 'digital_object_id',
            ]);

            $this->upsertI18n('ric_instantiation_i18n', $id, $data, [
                'title', 'description', 'technical_characteristics', 'production_technical_characteristics',
            ]);

            $this->touchObject($id);
        });
    }

    public function deleteInstantiation(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table('ric_instantiation_i18n')->where('id', $id)->delete();
            DB::table('ric_instantiation')->where('id', $id)->delete();
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    public function getInstantiationById(int $id): ?object
    {
        return DB::table('ric_instantiation')
            ->join('object', 'ric_instantiation.id', '=', 'object.id')
            ->join('slug', 'ric_instantiation.id', '=', 'slug.object_id')
            ->leftJoin('ric_instantiation_i18n', function ($j) {
                $j->on('ric_instantiation.id', '=', 'ric_instantiation_i18n.id')
                    ->where('ric_instantiation_i18n.culture', '=', $this->culture);
            })
            ->where('ric_instantiation.id', $id)
            ->select([
                'ric_instantiation.*',
                'ric_instantiation_i18n.title', 'ric_instantiation_i18n.description',
                'ric_instantiation_i18n.technical_characteristics',
                'ric_instantiation_i18n.production_technical_characteristics',
                'object.created_at', 'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    public function getInstantiationBySlug(string $slug): ?object
    {
        $id = DB::table('slug')->where('slug', $slug)->value('object_id');
        return $id ? $this->getInstantiationById($id) : null;
    }

    public function browseInstantiations(array $params = []): object
    {
        return $this->browseEntities('ric_instantiation', 'ric_instantiation_i18n', 'title', 'RicInstantiation', $params);
    }

    // ================================================================
    // RELATIONS
    // ================================================================

    /**
     * Create a RiC-native relation between two entities.
     */
    public function createRelation(int $subjectId, int $objectId, string $relTypeCode, array $data = []): int
    {
        return DB::transaction(function () use ($subjectId, $objectId, $relTypeCode, $data) {
            // Look up the relation type metadata from ahg_dropdown
            $relType = DB::table('ahg_dropdown')
                ->where('taxonomy', 'ric_relation_type')
                ->where('code', $relTypeCode)
                ->where('is_active', 1)
                ->first();

            $metadata = $relType && $relType->metadata ? json_decode($relType->metadata, true) : [];

            // Create object row for the relation
            $id = $this->insertObjectRecord('QubitRelation');

            // Create relation row (type_id = NULL for RiC-native)
            DB::table('relation')->insert([
                'id' => $id,
                'subject_id' => $subjectId,
                'object_id' => $objectId,
                'type_id' => null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'source_culture' => $this->culture,
            ]);

            // Create RiC relation metadata
            DB::table('ric_relation_meta')->insert([
                'relation_id' => $id,
                'rico_predicate' => $metadata['predicate'] ?? 'rico:isAssociatedWith',
                'inverse_predicate' => $metadata['inverse'] ?? 'rico:isAssociatedWith',
                'domain_class' => $metadata['domain'] ?? null,
                'range_class' => $metadata['range'] ?? null,
                'dropdown_code' => $relTypeCode,
                'certainty' => $data['certainty'] ?? null,
                'evidence' => $data['evidence'] ?? null,
            ]);

            // If symmetric, create inverse relation
            if (!empty($metadata['symmetric'])) {
                $invId = $this->insertObjectRecord('QubitRelation');

                DB::table('relation')->insert([
                    'id' => $invId,
                    'subject_id' => $objectId,
                    'object_id' => $subjectId,
                    'type_id' => null,
                    'start_date' => $data['start_date'] ?? null,
                    'end_date' => $data['end_date'] ?? null,
                    'source_culture' => $this->culture,
                ]);

                DB::table('ric_relation_meta')->insert([
                    'relation_id' => $invId,
                    'rico_predicate' => $metadata['inverse'] ?? $metadata['predicate'] ?? 'rico:isAssociatedWith',
                    'inverse_predicate' => $metadata['predicate'] ?? 'rico:isAssociatedWith',
                    'domain_class' => $metadata['range'] ?? null,
                    'range_class' => $metadata['domain'] ?? null,
                    'dropdown_code' => $relTypeCode,
                    'certainty' => $data['certainty'] ?? null,
                    'evidence' => $data['evidence'] ?? null,
                ]);
            }

            return $id;
        });
    }

    /**
     * Delete a RiC relation.
     */
    public function deleteRelation(int $relationId): void
    {
        DB::transaction(function () use ($relationId) {
            DB::table('ric_relation_meta')->where('relation_id', $relationId)->delete();
            DB::table('relation')->where('id', $relationId)->delete();
            DB::table('object')->where('id', $relationId)->delete();
        });
    }

    /**
     * Get all RiC relations for an entity (both as subject and object).
     */
    public function getRelationsForEntity(int $entityId): Collection
    {
        $outgoing = DB::table('relation')
            ->leftJoin('ric_relation_meta', 'relation.id', '=', 'ric_relation_meta.relation_id')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('relation.type_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('relation.subject_id', $entityId)
            ->select([
                'relation.id',
                'relation.subject_id',
                'relation.object_id as target_id',
                'relation.type_id',
                'relation.start_date',
                'relation.end_date',
                DB::raw("'outgoing' as direction"),
                'ric_relation_meta.rico_predicate',
                'ric_relation_meta.dropdown_code',
                'ric_relation_meta.certainty',
                'term_i18n.name as legacy_type_name',
            ])
            ->get();

        $incoming = DB::table('relation')
            ->leftJoin('ric_relation_meta', 'relation.id', '=', 'ric_relation_meta.relation_id')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('relation.type_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('relation.object_id', $entityId)
            ->select([
                'relation.id',
                'relation.object_id as subject_id',
                'relation.subject_id as target_id',
                'relation.type_id',
                'relation.start_date',
                'relation.end_date',
                DB::raw("'incoming' as direction"),
                'ric_relation_meta.inverse_predicate as rico_predicate',
                'ric_relation_meta.dropdown_code',
                'ric_relation_meta.certainty',
                'term_i18n.name as legacy_type_name',
            ])
            ->get();

        // Resolve target entity names
        $relations = $outgoing->merge($incoming);
        foreach ($relations as $rel) {
            $rel->target_name = $this->resolveEntityName($rel->target_id);
            $rel->target_type = $this->resolveEntityType($rel->target_id);
            $rel->relation_label = $this->resolveRelationLabel($rel);
        }

        return $relations;
    }

    /**
     * Get all RiC entities linked to a specific record (for the record-level panel).
     */
    public function getEntitiesForRecord(int $recordId): array
    {
        // Get all entity IDs linked to this record via relations (as subject or object)
        $relatedIds = DB::table('relation')
            ->where('relation.subject_id', $recordId)
            ->orWhere('relation.object_id', $recordId)
            ->get()
            ->map(function ($r) use ($recordId) {
                return $r->subject_id == $recordId ? $r->object_id : $r->subject_id;
            })
            ->unique()
            ->values()
            ->toArray();

        // Activities linked via relation
        $activities = collect();
        if (!empty($relatedIds)) {
            $activities = DB::table('ric_activity')
                ->leftJoin('ric_activity_i18n', function ($j) {
                    $j->on('ric_activity.id', '=', 'ric_activity_i18n.id')
                        ->where('ric_activity_i18n.culture', '=', $this->culture);
                })
                ->leftJoin('slug', 'ric_activity.id', '=', 'slug.object_id')
                ->whereIn('ric_activity.id', $relatedIds)
                ->select([
                    'ric_activity.id', 'ric_activity.type_id', 'ric_activity.start_date', 'ric_activity.end_date',
                    'ric_activity_i18n.name', 'ric_activity_i18n.description', 'ric_activity_i18n.date_display',
                    'slug.slug',
                ])
                ->get();
        }

        // Instantiations: linked via record_id OR via relation
        $instantiations = DB::table('ric_instantiation')
            ->leftJoin('ric_instantiation_i18n', function ($j) {
                $j->on('ric_instantiation.id', '=', 'ric_instantiation_i18n.id')
                    ->where('ric_instantiation_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'ric_instantiation.id', '=', 'slug.object_id')
            ->where(function ($q) use ($recordId, $relatedIds) {
                $q->where('ric_instantiation.record_id', $recordId);
                if (!empty($relatedIds)) {
                    $q->orWhereIn('ric_instantiation.id', $relatedIds);
                }
            })
            ->select([
                'ric_instantiation.id', 'ric_instantiation.carrier_type', 'ric_instantiation.mime_type',
                'ric_instantiation.extent_value', 'ric_instantiation.extent_unit',
                'ric_instantiation_i18n.title', 'ric_instantiation_i18n.description',
                'slug.slug',
            ])
            ->distinct()
            ->get();

        // Places linked via relation
        $places = collect();
        if (!empty($relatedIds)) {
            $places = DB::table('ric_place')
                ->leftJoin('ric_place_i18n', function ($j) {
                    $j->on('ric_place.id', '=', 'ric_place_i18n.id')
                        ->where('ric_place_i18n.culture', '=', $this->culture);
                })
                ->leftJoin('slug', 'ric_place.id', '=', 'slug.object_id')
                ->whereIn('ric_place.id', $relatedIds)
                ->select([
                    'ric_place.id', 'ric_place.type_id', 'ric_place.latitude', 'ric_place.longitude',
                    'ric_place_i18n.name', 'ric_place_i18n.description',
                    'slug.slug',
                ])
                ->get();
        }

        // Rules linked via relation
        $rules = collect();
        if (!empty($relatedIds)) {
            $rules = DB::table('ric_rule')
                ->leftJoin('ric_rule_i18n', function ($j) {
                    $j->on('ric_rule.id', '=', 'ric_rule_i18n.id')
                        ->where('ric_rule_i18n.culture', '=', $this->culture);
                })
                ->leftJoin('slug', 'ric_rule.id', '=', 'slug.object_id')
                ->whereIn('ric_rule.id', $relatedIds)
                ->select([
                    'ric_rule.id', 'ric_rule.type_id', 'ric_rule.jurisdiction',
                    'ric_rule_i18n.title', 'ric_rule_i18n.description',
                    'slug.slug',
                ])
                ->get();
        }

        return [
            'activities' => $activities,
            'instantiations' => $instantiations,
            'places' => $places,
            'rules' => $rules,
        ];
    }

    // ================================================================
    // HIERARCHY (isPartOf / hasPart)
    // ================================================================

    /**
     * Hierarchical relation codes (child→parent direction).
     */
    private const HIERARCHY_CODES = ['has_part', 'includes', 'is_child_of', 'is_superior_of'];

    /**
     * Get hierarchy info for an entity: parent, children, siblings.
     */
    public function getHierarchy(int $entityId): array
    {
        // Parent = where this entity is the object (target) of has_part/includes,
        //          OR where this entity is the subject of is_child_of
        $parent = null;
        $children = collect();

        // Find "parent has_part/includes this" relations (this entity is object)
        $parentRel = DB::table('relation')
            ->join('ric_relation_meta', 'relation.id', '=', 'ric_relation_meta.relation_id')
            ->where('relation.object_id', $entityId)
            ->whereIn('ric_relation_meta.dropdown_code', ['has_part', 'includes', 'is_superior_of'])
            ->select('relation.subject_id')
            ->first();

        if (!$parentRel) {
            // Also check "this is_child_of parent" (this entity is subject)
            $parentRel = DB::table('relation')
                ->join('ric_relation_meta', 'relation.id', '=', 'ric_relation_meta.relation_id')
                ->where('relation.subject_id', $entityId)
                ->where('ric_relation_meta.dropdown_code', 'is_child_of')
                ->select('relation.object_id as subject_id')
                ->first();
        }

        if ($parentRel) {
            $parentId = $parentRel->subject_id;
            $parent = (object) [
                'id' => $parentId,
                'name' => $this->resolveEntityName($parentId),
                'type' => $this->resolveEntityType($parentId),
                'slug' => DB::table('slug')->where('object_id', $parentId)->value('slug'),
            ];
        }

        // Children = where this entity is the subject of has_part/includes,
        //            OR where this entity is the object of is_child_of
        $childIds = DB::table('relation')
            ->join('ric_relation_meta', 'relation.id', '=', 'ric_relation_meta.relation_id')
            ->where('relation.subject_id', $entityId)
            ->whereIn('ric_relation_meta.dropdown_code', ['has_part', 'includes', 'is_superior_of'])
            ->pluck('relation.object_id')
            ->merge(
                DB::table('relation')
                    ->join('ric_relation_meta', 'relation.id', '=', 'ric_relation_meta.relation_id')
                    ->where('relation.object_id', $entityId)
                    ->where('ric_relation_meta.dropdown_code', 'is_child_of')
                    ->pluck('relation.subject_id')
            )
            ->unique();

        foreach ($childIds as $childId) {
            $children->push((object) [
                'id' => $childId,
                'name' => $this->resolveEntityName($childId),
                'type' => $this->resolveEntityType($childId),
                'slug' => DB::table('slug')->where('object_id', $childId)->value('slug'),
            ]);
        }

        // Siblings = other children of the same parent
        $siblings = collect();
        if ($parent) {
            $siblingIds = DB::table('relation')
                ->join('ric_relation_meta', 'relation.id', '=', 'ric_relation_meta.relation_id')
                ->where('relation.subject_id', $parent->id)
                ->whereIn('ric_relation_meta.dropdown_code', ['has_part', 'includes', 'is_superior_of'])
                ->where('relation.object_id', '!=', $entityId)
                ->pluck('relation.object_id');

            foreach ($siblingIds as $sibId) {
                $siblings->push((object) [
                    'id' => $sibId,
                    'name' => $this->resolveEntityName($sibId),
                    'type' => $this->resolveEntityType($sibId),
                    'slug' => DB::table('slug')->where('object_id', $sibId)->value('slug'),
                ]);
            }
        }

        return [
            'parent' => $parent,
            'children' => $children,
            'siblings' => $siblings,
        ];
    }

    // ================================================================
    // AUTOCOMPLETE (search across all entity types)
    // ================================================================

    public function autocompleteEntities(string $query, ?string $typeFilter = null, int $limit = 20): Collection
    {
        $results = collect();
        $q = '%' . $query . '%';

        $types = $typeFilter ? explode(',', $typeFilter) : ['place', 'rule', 'activity', 'instantiation', 'actor', 'io', 'repository', 'digital_object'];

        if (in_array('place', $types)) {
            $results = $results->merge(
                DB::table('ric_place')
                    ->join('ric_place_i18n', function ($j) {
                        $j->on('ric_place.id', '=', 'ric_place_i18n.id')
                            ->where('ric_place_i18n.culture', '=', $this->culture);
                    })
                    ->where('ric_place_i18n.name', 'like', $q)
                    ->select(['ric_place.id', 'ric_place_i18n.name as label', DB::raw("'Place' as type")])
                    ->limit($limit)
                    ->get()
            );
        }

        if (in_array('rule', $types)) {
            $results = $results->merge(
                DB::table('ric_rule')
                    ->join('ric_rule_i18n', function ($j) {
                        $j->on('ric_rule.id', '=', 'ric_rule_i18n.id')
                            ->where('ric_rule_i18n.culture', '=', $this->culture);
                    })
                    ->where('ric_rule_i18n.title', 'like', $q)
                    ->select(['ric_rule.id', 'ric_rule_i18n.title as label', DB::raw("'Rule' as type")])
                    ->limit($limit)
                    ->get()
            );
        }

        if (in_array('activity', $types)) {
            $results = $results->merge(
                DB::table('ric_activity')
                    ->join('ric_activity_i18n', function ($j) {
                        $j->on('ric_activity.id', '=', 'ric_activity_i18n.id')
                            ->where('ric_activity_i18n.culture', '=', $this->culture);
                    })
                    ->where('ric_activity_i18n.name', 'like', $q)
                    ->select(['ric_activity.id', 'ric_activity_i18n.name as label', DB::raw("'Activity' as type")])
                    ->limit($limit)
                    ->get()
            );
        }

        if (in_array('instantiation', $types)) {
            $results = $results->merge(
                DB::table('ric_instantiation')
                    ->join('ric_instantiation_i18n', function ($j) {
                        $j->on('ric_instantiation.id', '=', 'ric_instantiation_i18n.id')
                            ->where('ric_instantiation_i18n.culture', '=', $this->culture);
                    })
                    ->where('ric_instantiation_i18n.title', 'like', $q)
                    ->select(['ric_instantiation.id', 'ric_instantiation_i18n.title as label', DB::raw("'Instantiation' as type")])
                    ->limit($limit)
                    ->get()
            );
        }

        if (in_array('actor', $types)) {
            $results = $results->merge(
                DB::table('actor')
                    ->join('actor_i18n', function ($j) {
                        $j->on('actor.id', '=', 'actor_i18n.id')
                            ->where('actor_i18n.culture', '=', $this->culture);
                    })
                    ->where('actor_i18n.authorized_form_of_name', 'like', $q)
                    ->select(['actor.id', 'actor_i18n.authorized_form_of_name as label', DB::raw("'Agent' as type")])
                    ->limit($limit)
                    ->get()
            );
        }

        if (in_array('io', $types)) {
            $results = $results->merge(
                DB::table('information_object')
                    ->join('information_object_i18n', function ($j) {
                        $j->on('information_object.id', '=', 'information_object_i18n.id')
                            ->where('information_object_i18n.culture', '=', $this->culture);
                    })
                    ->where('information_object_i18n.title', 'like', $q)
                    ->where('information_object.id', '!=', 1) // skip root
                    ->select(['information_object.id', 'information_object_i18n.title as label', DB::raw("'Record' as type")])
                    ->limit($limit)
                    ->get()
            );
        }

        if (in_array('digital_object', $types)) {
            $results = $results->merge(
                DB::table('digital_object')
                    ->where('digital_object.name', 'like', $q)
                    ->whereNull('digital_object.parent_id') // master file, not derivatives
                    ->select(['digital_object.id', 'digital_object.name as label', DB::raw("'DigitalObject' as type")])
                    ->limit($limit)
                    ->get()
            );
        }

        return $results->take($limit);
    }

    // ================================================================
    // DROPDOWN HELPERS
    // ================================================================

    /**
     * Get dropdown choices for a given taxonomy.
     */
    public function getDropdownChoices(string $taxonomy): Collection
    {
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->select(['code', 'label', 'color', 'icon', 'is_default', 'metadata'])
            ->get();
    }

    /**
     * Get relation types, optionally filtered by domain/range.
     */
    public function getRelationTypes(?string $domain = null, ?string $range = null): Collection
    {
        $query = DB::table('ahg_dropdown')
            ->where('taxonomy', 'ric_relation_type')
            ->where('is_active', 1)
            ->orderBy('sort_order');

        $types = $query->get();

        if ($domain || $range) {
            $types = $types->filter(function ($type) use ($domain, $range) {
                $meta = json_decode($type->metadata ?? '{}', true);
                $domainMatch = !$domain || ($meta['domain'] ?? '*') === '*' || ($meta['domain'] ?? '') === $domain;
                $rangeMatch = !$range || ($meta['range'] ?? '*') === '*' || ($meta['range'] ?? '') === $range;
                return $domainMatch && $rangeMatch;
            });
        }

        return $types->values();
    }

    // ================================================================
    // SHARED PRIVATE METHODS
    // ================================================================

    protected function insertObjectRecord(string $className): int
    {
        return DB::table('object')->insertGetId([
            'class_name' => $className,
            'created_at' => now(),
            'updated_at' => now(),
            'serial_number' => 0,
        ]);
    }

    protected function insertSlug(int $id, string $name): void
    {
        $baseSlug = Str::slug($name) ?: 'untitled';
        $slug = $baseSlug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        DB::table('slug')->insert([
            'object_id' => $id,
            'slug' => $slug,
        ]);
    }

    protected function updateEntityFields(string $table, int $id, array $data, array $fields): void
    {
        $update = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (!empty($update)) {
            DB::table($table)->where('id', $id)->update($update);
        }
    }

    protected function upsertI18n(string $table, int $id, array $data, array $fields): void
    {
        $i18nData = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $i18nData[$field] = $data[$field];
            }
        }
        if (empty($i18nData)) {
            return;
        }

        $exists = DB::table($table)
            ->where('id', $id)
            ->where('culture', $this->culture)
            ->exists();

        if ($exists) {
            DB::table($table)->where('id', $id)->where('culture', $this->culture)->update($i18nData);
        } else {
            DB::table($table)->insert(array_merge(['id' => $id, 'culture' => $this->culture], $i18nData));
        }
    }

    protected function touchObject(int $id): void
    {
        DB::table('object')->where('id', $id)->update([
            'updated_at' => now(),
            'serial_number' => DB::raw('serial_number + 1'),
        ]);
    }

    protected function browseEntities(string $table, string $i18nTable, string $nameColumn, string $className, array $params): object
    {
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 30);
        $sort = $params['sort'] ?? $nameColumn;
        $direction = $params['direction'] ?? 'asc';
        $search = $params['search'] ?? null;

        $query = DB::table($table)
            ->join('object', "$table.id", '=', 'object.id')
            ->join('slug', "$table.id", '=', 'slug.object_id')
            ->leftJoin($i18nTable, function ($j) use ($table, $i18nTable) {
                $j->on("$table.id", '=', "$i18nTable.id")
                    ->where("$i18nTable.culture", '=', $this->culture);
            })
            ->where('object.class_name', $className);

        if ($search) {
            $query->where("$i18nTable.$nameColumn", 'like', "%{$search}%");
        }

        $total = $query->count();

        $items = $query
            ->orderBy($sort === $nameColumn ? "$i18nTable.$nameColumn" : "$table.$sort", $direction)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->select([
                "$table.*",
                "$i18nTable.$nameColumn",
                'object.created_at', 'object.updated_at',
                'slug.slug',
            ])
            ->get();

        return (object) [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    public function resolveEntityName(int $id): string
    {
        $className = DB::table('object')->where('id', $id)->value('class_name');

        // Check based on class_name for efficiency
        switch ($className) {
            case 'RicPlace':
                $row = DB::table('ric_place_i18n')->where('id', $id)->where('culture', $this->culture)->first();
                if ($row && $row->name) return $row->name;
                if ($row && $row->description) return \Illuminate\Support\Str::limit($row->description, 60);
                return 'Place #' . $id;

            case 'RicRule':
                $row = DB::table('ric_rule_i18n')->where('id', $id)->where('culture', $this->culture)->first();
                if ($row && $row->title) return $row->title;
                if ($row && $row->description) return \Illuminate\Support\Str::limit($row->description, 60);
                $type = DB::table('ric_rule')->where('id', $id)->value('type_id');
                return ($type ? ucfirst($type) : 'Rule') . ' #' . $id;

            case 'RicActivity':
                $row = DB::table('ric_activity_i18n')->where('id', $id)->where('culture', $this->culture)->first();
                if ($row && $row->name) return $row->name;
                if ($row && $row->date_display) {
                    $type = DB::table('ric_activity')->where('id', $id)->value('type_id');
                    return ucfirst($type ?? 'Activity') . ' (' . $row->date_display . ')';
                }
                if ($row && $row->description) return \Illuminate\Support\Str::limit($row->description, 60);
                $type = DB::table('ric_activity')->where('id', $id)->value('type_id');
                return ucfirst($type ?? 'Activity') . ' #' . $id;

            case 'RicInstantiation':
                $row = DB::table('ric_instantiation_i18n')->where('id', $id)->where('culture', $this->culture)->first();
                if ($row && $row->title) return $row->title;
                $mime = DB::table('ric_instantiation')->where('id', $id)->value('mime_type');
                if ($mime) return 'Instantiation (' . $mime . ')';
                return 'Instantiation #' . $id;

            case 'QubitInformationObject':
                $name = DB::table('information_object_i18n')->where('id', $id)->where('culture', $this->culture)->value('title');
                if ($name) return $name;
                return 'Record #' . $id;

            case 'QubitActor':
                $name = DB::table('actor_i18n')->where('id', $id)->where('culture', $this->culture)->value('authorized_form_of_name');
                if ($name) return $name;
                return 'Agent #' . $id;

            case 'QubitRepository':
                $name = DB::table('actor_i18n')->where('id', $id)->where('culture', $this->culture)->value('authorized_form_of_name');
                if ($name) return $name;
                return 'Repository #' . $id;

            case 'QubitTerm':
                $name = DB::table('term_i18n')->where('id', $id)->where('culture', $this->culture)->value('name');
                if ($name) return $name;
                return 'Term #' . $id;

            case 'QubitFunctionObject':
                $name = DB::table('function_object_i18n')->where('id', $id)->where('culture', $this->culture)->value('authorized_form_of_name');
                if ($name) return $name;
                return 'Function #' . $id;

            default:
                // Try all i18n tables as fallback
                $name = DB::table('information_object_i18n')->where('id', $id)->where('culture', $this->culture)->value('title');
                if ($name) return $name;
                $name = DB::table('actor_i18n')->where('id', $id)->where('culture', $this->culture)->value('authorized_form_of_name');
                if ($name) return $name;
                $name = DB::table('term_i18n')->where('id', $id)->where('culture', $this->culture)->value('name');
                if ($name) return $name;
                return ($className ?? 'Entity') . ' #' . $id;
        }
    }

    protected function resolveEntityType(int $id): string
    {
        $className = DB::table('object')->where('id', $id)->value('class_name');

        return match ($className) {
            'RicPlace' => 'Place',
            'RicRule' => 'Rule',
            'RicActivity' => 'Activity',
            'RicInstantiation' => 'Instantiation',
            'QubitActor' => 'Agent',
            'QubitInformationObject' => 'Record',
            'QubitRepository' => 'Repository',
            'QubitFunctionObject' => 'Function',
            'QubitTerm' => 'Term',
            default => $className ?? 'Unknown',
        };
    }

    protected function resolveRelationLabel(object $rel): string
    {
        if ($rel->dropdown_code) {
            $label = DB::table('ahg_dropdown')
                ->where('taxonomy', 'ric_relation_type')
                ->where('code', $rel->dropdown_code)
                ->value('label');
            if ($label) return $label;
        }

        if ($rel->rico_predicate) {
            return str_replace('rico:', '', $rel->rico_predicate);
        }

        if ($rel->legacy_type_name) {
            return $rel->legacy_type_name;
        }

        return 'Related';
    }
}
