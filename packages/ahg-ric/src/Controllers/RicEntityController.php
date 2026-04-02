<?php

/**
 * RicEntityController - CRUD for RiC-native entities
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgRic\Controllers;

use AhgRic\Services\RicEntityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RicEntityController extends Controller
{
    protected RicEntityService $service;

    public function __construct()
    {
        $this->service = new RicEntityService(app()->getLocale());
    }

    // ================================================================
    // RECORD-LEVEL AJAX ENDPOINTS (called from IO/Actor show pages)
    // ================================================================

    /**
     * Get all RiC entities linked to a record (for the panel).
     */
    public function entitiesForRecord(int $id): JsonResponse
    {
        $entities = $this->service->getEntitiesForRecord($id);
        return response()->json($entities);
    }

    /**
     * Create a RiC entity via AJAX (from record-level modal).
     */
    public function storeEntity(Request $request): JsonResponse
    {
        $type = $request->input('entity_type');
        $data = $request->all();

        try {
            $id = match ($type) {
                'place' => $this->service->createPlace($data),
                'rule' => $this->service->createRule($data),
                'activity' => $this->service->createActivity($data),
                'instantiation' => $this->service->createInstantiation($data),
                default => throw new \InvalidArgumentException("Unknown entity type: {$type}"),
            };

            // If a record_id is provided, auto-create a relation
            if (!empty($data['link_to_record_id']) && !empty($data['link_relation_type'])) {
                $this->service->createRelation(
                    (int) $data['link_to_record_id'],
                    $id,
                    $data['link_relation_type']
                );
            }

            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update a RiC entity via AJAX.
     */
    public function updateEntity(Request $request, int $id): JsonResponse
    {
        $type = $request->input('entity_type');
        $data = $request->all();

        try {
            match ($type) {
                'place' => $this->service->updatePlace($id, $data),
                'rule' => $this->service->updateRule($id, $data),
                'activity' => $this->service->updateActivity($id, $data),
                'instantiation' => $this->service->updateInstantiation($id, $data),
                default => throw new \InvalidArgumentException("Unknown entity type: {$type}"),
            };

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete a RiC entity via AJAX.
     */
    public function destroyEntity(int $id): JsonResponse
    {
        $className = \Illuminate\Support\Facades\DB::table('object')->where('id', $id)->value('class_name');

        try {
            match ($className) {
                'RicPlace' => $this->service->deletePlace($id),
                'RicRule' => $this->service->deleteRule($id),
                'RicActivity' => $this->service->deleteActivity($id),
                'RicInstantiation' => $this->service->deleteInstantiation($id),
                default => throw new \InvalidArgumentException("Cannot delete entity type: {$className}"),
            };

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Autocomplete search across all entity types.
     */
    public function autocompleteEntities(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $types = $request->input('types');
        $limit = (int) $request->input('limit', 20);

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = $this->service->autocompleteEntities($query, $types, $limit);
        return response()->json($results);
    }

    /**
     * Get entity info card (for relation editor popovers).
     */
    public function getEntityInfo(int $id): JsonResponse
    {
        $className = \Illuminate\Support\Facades\DB::table('object')->where('id', $id)->value('class_name');
        $entity = null;

        $entity = match ($className) {
            'RicPlace' => $this->service->getPlaceById($id),
            'RicRule' => $this->service->getRuleById($id),
            'RicActivity' => $this->service->getActivityById($id),
            'RicInstantiation' => $this->service->getInstantiationById($id),
            default => null,
        };

        if (!$entity) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($entity);
    }

    // ================================================================
    // RELATION AJAX ENDPOINTS
    // ================================================================

    /**
     * Get all RiC relations for a record.
     */
    public function relationsForRecord(int $id): JsonResponse
    {
        $relations = $this->service->getRelationsForEntity($id);
        return response()->json($relations);
    }

    /**
     * Create a RiC relation.
     */
    public function storeRelation(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|integer',
            'object_id' => 'required|integer',
            'relation_type' => 'required|string',
        ]);

        try {
            $id = $this->service->createRelation(
                (int) $request->input('subject_id'),
                (int) $request->input('object_id'),
                $request->input('relation_type'),
                $request->only(['start_date', 'end_date', 'certainty', 'evidence'])
            );

            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete a RiC relation.
     */
    public function destroyRelation(int $id): JsonResponse
    {
        try {
            $this->service->deleteRelation($id);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get relation types (optionally filtered by domain/range).
     */
    public function getRelationTypes(Request $request): JsonResponse
    {
        $domain = $request->input('domain');
        $range = $request->input('range');
        $types = $this->service->getRelationTypes($domain, $range);
        return response()->json($types);
    }

    // ================================================================
    // STANDALONE BROWSE PAGES
    // ================================================================

    public function browsePlaces(Request $request)
    {
        $result = $this->service->browsePlaces($request->all());
        $choices = $this->service->getDropdownChoices('ric_place_type');
        return view('ahg-ric::entities.places.browse', [
            'result' => $result,
            'typeChoices' => $choices,
            'params' => $request->all(),
        ]);
    }

    public function browseRules(Request $request)
    {
        $result = $this->service->browseRules($request->all());
        $choices = $this->service->getDropdownChoices('ric_rule_type');
        return view('ahg-ric::entities.rules.browse', [
            'result' => $result,
            'typeChoices' => $choices,
            'params' => $request->all(),
        ]);
    }

    public function browseActivities(Request $request)
    {
        $result = $this->service->browseActivities($request->all());
        $choices = $this->service->getDropdownChoices('ric_activity_type');
        return view('ahg-ric::entities.activities.browse', [
            'result' => $result,
            'typeChoices' => $choices,
            'params' => $request->all(),
        ]);
    }

    public function browseInstantiations(Request $request)
    {
        $result = $this->service->browseInstantiations($request->all());
        $choices = $this->service->getDropdownChoices('ric_carrier_type');
        return view('ahg-ric::entities.instantiations.browse', [
            'result' => $result,
            'typeChoices' => $choices,
            'params' => $request->all(),
        ]);
    }

    /**
     * Show a single RiC entity.
     */
    public function showEntity(string $type, string $slug)
    {
        $entity = match ($type) {
            'places' => $this->service->getPlaceBySlug($slug),
            'rules' => $this->service->getRuleBySlug($slug),
            'activities' => $this->service->getActivityBySlug($slug),
            'instantiations' => $this->service->getInstantiationBySlug($slug),
            default => null,
        };

        if (!$entity) {
            abort(404);
        }

        $relations = $this->service->getRelationsForEntity($entity->id);
        $relationTypes = $this->service->getRelationTypes();

        return view("ahg-ric::entities.{$type}.show", [
            'entity' => $entity,
            'relations' => $relations,
            'relationTypes' => $relationTypes,
            'entityType' => rtrim($type, 's'), // places → place
        ]);
    }

    /**
     * Edit form for a single RiC entity.
     */
    public function editEntity(string $type, string $slug)
    {
        $entity = match ($type) {
            'places' => $this->service->getPlaceBySlug($slug),
            'rules' => $this->service->getRuleBySlug($slug),
            'activities' => $this->service->getActivityBySlug($slug),
            'instantiations' => $this->service->getInstantiationBySlug($slug),
            default => null,
        };

        if (!$entity) {
            abort(404);
        }

        $singularType = rtrim($type, 's');
        $taxonomyMap = [
            'place' => 'ric_place_type',
            'rule' => 'ric_rule_type',
            'activity' => 'ric_activity_type',
            'instantiation' => 'ric_carrier_type',
        ];
        $choices = $this->service->getDropdownChoices($taxonomyMap[$singularType] ?? '');

        return view("ahg-ric::entities.{$type}.edit", [
            'entity' => $entity,
            'typeChoices' => $choices,
            'entityType' => $singularType,
        ]);
    }

    /**
     * Update a RiC entity from the edit form (non-AJAX).
     */
    public function updateEntityForm(Request $request, string $type, string $slug)
    {
        $entity = match ($type) {
            'places' => $this->service->getPlaceBySlug($slug),
            'rules' => $this->service->getRuleBySlug($slug),
            'activities' => $this->service->getActivityBySlug($slug),
            'instantiations' => $this->service->getInstantiationBySlug($slug),
            default => null,
        };

        if (!$entity) {
            abort(404);
        }

        $data = $request->all();
        $data['entity_type'] = rtrim($type, 's');

        match ($type) {
            'places' => $this->service->updatePlace($entity->id, $data),
            'rules' => $this->service->updateRule($entity->id, $data),
            'activities' => $this->service->updateActivity($entity->id, $data),
            'instantiations' => $this->service->updateInstantiation($entity->id, $data),
        };

        return redirect()->route('ric.entities.show', [$type, $slug])
            ->with('success', ucfirst(rtrim($type, 's')) . ' updated successfully.');
    }

    /**
     * Delete a RiC entity from the show page (non-AJAX).
     */
    public function destroyEntityForm(string $type, string $slug)
    {
        $entity = match ($type) {
            'places' => $this->service->getPlaceBySlug($slug),
            'rules' => $this->service->getRuleBySlug($slug),
            'activities' => $this->service->getActivityBySlug($slug),
            'instantiations' => $this->service->getInstantiationBySlug($slug),
            default => null,
        };

        if (!$entity) {
            abort(404);
        }

        match ($type) {
            'places' => $this->service->deletePlace($entity->id),
            'rules' => $this->service->deleteRule($entity->id),
            'activities' => $this->service->deleteActivity($entity->id),
            'instantiations' => $this->service->deleteInstantiation($entity->id),
        };

        return redirect()->route("ric.{$type}.browse")
            ->with('success', ucfirst(rtrim($type, 's')) . ' deleted successfully.');
    }

    /**
     * Get dropdown choices for a taxonomy (AJAX).
     */
    public function dropdownChoices(string $taxonomy): JsonResponse
    {
        $choices = $this->service->getDropdownChoices($taxonomy);
        return response()->json($choices);
    }
}
