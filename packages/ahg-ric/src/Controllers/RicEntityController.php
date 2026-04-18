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

    /**
     * API-3 helper: call a public `/api/ric/v1/*` endpoint in-process,
     * forwarding the caller's session cookie so write endpoints (`api.auth:write`)
     * accept the admin's session. Returns the JSON decoded body, or null on any
     * non-2xx / transport failure (signal to fall back to direct service call).
     */
    protected function callRicApi(string $method, string $path, array $data = [], ?Request $origRequest = null): ?array
    {
        $origRequest ??= request();
        $appUrl = rtrim(config('app.url'), '/');
        $url = $appUrl . $path;
        $host = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';

        $cookies = [];
        $sessionCookieName = config('session.cookie');
        if ($sessionCookieName && $origRequest->cookies->has($sessionCookieName)) {
            // Forward raw encrypted cookie value — the inner request's
            // EncryptCookies middleware will decrypt it and restore the session.
            $cookies[$sessionCookieName] = $origRequest->cookies->get($sessionCookieName);
        }

        try {
            $client = \Illuminate\Support\Facades\Http::timeout(5);
            if ($cookies) $client = $client->withCookies($cookies, $host);
            $response = match (strtolower($method)) {
                'post' => $client->post($url, $data),
                'put', 'patch' => $client->{strtolower($method)}($url, $data),
                'delete' => $client->delete($url, $data),
                default => $client->get($url, $data),
            };
            if ($response->successful()) {
                return $response->json();
            }
            \Illuminate\Support\Facades\Log::info("[callRicApi] {$method} {$path} returned {$response->status()}");
            return null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[callRicApi] {$method} {$path} failed: " . $e->getMessage());
            return null;
        }
    }

    // ================================================================
    // RECORD-LEVEL AJAX ENDPOINTS (called from IO/Actor show pages)
    // ================================================================

    /**
     * Get all RiC entities linked to a record (for the panel).
     */
    public function entitiesForRecord(int $id): JsonResponse
    {
        // API-3 migration: try the public API first, fall back to direct service.
        $apiUrl = rtrim(config('app.url'), '/') . "/api/ric/v1/records/{$id}/entities";
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($apiUrl);
            if ($response->successful()) {
                return response()->json($response->json());
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[entitiesForRecord] API fallback: ' . $e->getMessage());
        }
        return response()->json($this->service->getEntitiesForRecord($id));
    }

    /**
     * Create a RiC entity via AJAX (from record-level modal).
     *
     * API-3 migration: try the public API first (forwards admin session),
     * fall back to direct service call if the HTTP hop is unreachable.
     */
    public function storeEntity(Request $request): JsonResponse
    {
        $type = $request->input('entity_type');
        $data = $request->all();

        if (!in_array($type, ['place', 'rule', 'activity', 'instantiation'], true)) {
            return response()->json(['success' => false, 'error' => "Unknown entity type: {$type}"], 422);
        }

        // Try HTTP API first.
        $apiResult = $this->callRicApi('POST', "/api/ric/v1/{$type}s", $data, $request);
        $id = null;
        if ($apiResult && isset($apiResult['id'])) {
            $id = (int) $apiResult['id'];
        }

        // Fallback: direct service call.
        if (!$id) {
            try {
                $id = match ($type) {
                    'place' => $this->service->createPlace($data),
                    'rule' => $this->service->createRule($data),
                    'activity' => $this->service->createActivity($data),
                    'instantiation' => $this->service->createInstantiation($data),
                };
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
        }

        // Auto-link to record if requested. (Kept service-side because this
        // is a secondary action and already works.)
        if (!empty($data['link_to_record_id']) && !empty($data['link_relation_type'])) {
            try {
                $this->service->createRelation(
                    (int) $data['link_to_record_id'],
                    $id,
                    $data['link_relation_type']
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('auto-link relation failed: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'id' => $id]);
    }

    /**
     * Update a RiC entity via AJAX.
     *
     * API-3 migration: try the public API first, fall back to service.
     */
    public function updateEntity(Request $request, int $id): JsonResponse
    {
        $type = $request->input('entity_type');
        $data = $request->all();

        if (!in_array($type, ['place', 'rule', 'activity', 'instantiation'], true)) {
            return response()->json(['success' => false, 'error' => "Unknown entity type: {$type}"], 422);
        }

        $apiResult = $this->callRicApi('PATCH', "/api/ric/v1/{$type}s/{$id}", $data, $request);
        if ($apiResult && ($apiResult['success'] ?? false)) {
            return response()->json(['success' => true]);
        }

        try {
            match ($type) {
                'place' => $this->service->updatePlace($id, $data),
                'rule' => $this->service->updateRule($id, $data),
                'activity' => $this->service->updateActivity($id, $data),
                'instantiation' => $this->service->updateInstantiation($id, $data),
            };
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete a RiC entity via AJAX.
     *
     * API-3 migration: try the public API first, fall back to service.
     */
    public function destroyEntity(int $id): JsonResponse
    {
        $className = \Illuminate\Support\Facades\DB::table('object')->where('id', $id)->value('class_name');
        $typeMap = [
            'RicPlace' => 'places', 'RicRule' => 'rules',
            'RicActivity' => 'activities', 'RicInstantiation' => 'instantiations',
        ];
        $typePlural = $typeMap[$className] ?? null;
        if (!$typePlural) {
            return response()->json(['success' => false, 'error' => "Cannot delete entity type: {$className}"], 422);
        }

        $apiResult = $this->callRicApi('DELETE', "/api/ric/v1/{$typePlural}/{$id}");
        if ($apiResult && ($apiResult['success'] ?? false)) {
            return response()->json(['success' => true]);
        }

        try {
            match ($className) {
                'RicPlace' => $this->service->deletePlace($id),
                'RicRule' => $this->service->deleteRule($id),
                'RicActivity' => $this->service->deleteActivity($id),
                'RicInstantiation' => $this->service->deleteInstantiation($id),
            };
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Autocomplete search across all entity types.
     *
     * API-3 migration: delegates to the public `/api/ric/v1/autocomplete` endpoint.
     * The admin route remains for back-compat with existing UI JS (`_relation-editor`,
     * `_fk-autocomplete`, etc.) — can be deleted once all JS consumers reference
     * the public URL directly.
     */
    public function autocompleteEntities(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $apiUrl = rtrim(config('app.url'), '/') . '/api/ric/v1/autocomplete';
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($apiUrl, [
                'q' => $query,
                'types' => $request->input('types'),
                'limit' => (int) $request->input('limit', 20),
            ]);
            if ($response->successful()) {
                return response()->json($response->json());
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[autocomplete] API call failed, falling back to service: ' . $e->getMessage());
        }

        // Fallback: direct service call.
        $results = $this->service->autocompleteEntities($query, $request->input('types'), (int) $request->input('limit', 20));
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
        // API-3 migration: try the public API first, fall back to direct service.
        // Public endpoint returns grouped {outgoing, incoming}; admin JS expects a
        // flat array with per-row `direction`, so we merge back.
        $apiUrl = rtrim(config('app.url'), '/') . "/api/ric/v1/relations-for/{$id}";
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($apiUrl);
            if ($response->successful()) {
                $payload = $response->json();
                $flat = array_merge($payload['outgoing'] ?? [], $payload['incoming'] ?? []);
                return response()->json($flat);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[relationsForRecord] API fallback: ' . $e->getMessage());
        }
        return response()->json($this->service->getRelationsForEntity($id));
    }

    /**
     * Update an existing RiC relation (G9 in-place edit).
     *
     * API-3 migration: try the public API first, fall back to service.
     */
    public function updateRelationAjax(Request $request, int $id): JsonResponse
    {
        $apiResult = $this->callRicApi('PATCH', "/api/ric/v1/relations/{$id}", $request->all(), $request);
        if ($apiResult && ($apiResult['success'] ?? false)) {
            return response()->json(['success' => true]);
        }

        try {
            $this->service->updateRelation(
                $id,
                $request->only(['start_date', 'end_date', 'certainty', 'evidence', 'relation_type'])
            );
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
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

        // API-3 migration: try public API first.
        $apiResult = $this->callRicApi('POST', '/api/ric/v1/relations', $request->all(), $request);
        if ($apiResult && isset($apiResult['id'])) {
            return response()->json(['success' => true, 'id' => $apiResult['id']]);
        }

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
     *
     * API-3 migration: try the public API first, fall back to service.
     */
    public function destroyRelation(int $id): JsonResponse
    {
        $apiResult = $this->callRicApi('DELETE', "/api/ric/v1/relations/{$id}");
        if ($apiResult && ($apiResult['success'] ?? false)) {
            return response()->json(['success' => true]);
        }

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
        $hierarchy = $this->service->getHierarchy($entity->id);

        return view("ahg-ric::entities.{$type}.show", [
            'entity' => $entity,
            'hierarchy' => $hierarchy,
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

        $viewData = [
            'entity' => $entity,
            'typeChoices' => $choices,
            'entityType' => $singularType,
        ];
        if ($type === 'places') {
            $viewData['parentChoices'] = $this->service->listPlacesForPicker($entity->id ?? null);
        }
        if ($type === 'activities') {
            $viewData['placeChoices'] = $this->service->listPlacesForPicker();
        }
        if ($type === 'instantiations' && $entity) {
            $viewData['currentRecordLabel'] = $entity->record_id
                ? \Illuminate\Support\Facades\DB::table('information_object_i18n')
                    ->where('id', $entity->record_id)->where('culture', 'en')->value('title')
                : null;
            $viewData['currentDigitalObjectLabel'] = $entity->digital_object_id
                ? \Illuminate\Support\Facades\DB::table('digital_object')
                    ->where('id', $entity->digital_object_id)->value('name')
                : null;
        }

        return view("ahg-ric::entities.{$type}.edit", $viewData);
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

        // API-3 migration: try the public API first.
        $apiResult = $this->callRicApi('PATCH', "/api/ric/v1/{$type}/{$entity->id}", $data, $request);

        if (!$apiResult || !($apiResult['success'] ?? false)) {
            // Fallback to direct service call.
            match ($type) {
                'places' => $this->service->updatePlace($entity->id, $data),
                'rules' => $this->service->updateRule($entity->id, $data),
                'activities' => $this->service->updateActivity($entity->id, $data),
                'instantiations' => $this->service->updateInstantiation($entity->id, $data),
            };
        }

        return redirect()->route('ric.entities.show', [$type, $slug])
            ->with('success', ucfirst(rtrim($type, 's')) . ' updated successfully.');
    }

    /**
     * Render an empty create form for a RiC entity type (non-AJAX).
     */
    public function createEntityForm(string $type)
    {
        if (!in_array($type, ['places', 'rules', 'activities', 'instantiations'])) {
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

        $viewData = [
            'entity' => null,
            'typeChoices' => $choices,
            'entityType' => $singularType,
        ];
        if ($type === 'places') {
            $viewData['parentChoices'] = $this->service->listPlacesForPicker();
        }
        if ($type === 'activities') {
            $viewData['placeChoices'] = $this->service->listPlacesForPicker();
        }
        if ($type === 'instantiations') {
            $viewData['currentRecordLabel'] = null;
            $viewData['currentDigitalObjectLabel'] = null;
        }

        return view("ahg-ric::entities.{$type}.edit", $viewData);
    }

    /**
     * Persist a newly-created RiC entity from the create form.
     */
    public function storeEntityForm(Request $request, string $type)
    {
        if (!in_array($type, ['places', 'rules', 'activities', 'instantiations'], true)) {
            abort(404);
        }

        $data = $request->all();
        $data['entity_type'] = rtrim($type, 's');

        // API-3 migration: try the public API first.
        $apiResult = $this->callRicApi('POST', "/api/ric/v1/{$type}", $data, $request);
        $id = $apiResult['id'] ?? null;

        if (!$id) {
            try {
                $id = match ($type) {
                    'places' => $this->service->createPlace($data),
                    'rules' => $this->service->createRule($data),
                    'activities' => $this->service->createActivity($data),
                    'instantiations' => $this->service->createInstantiation($data),
                };
            } catch (\Exception $e) {
                return redirect()->back()->withInput()->withErrors(['create' => $e->getMessage()]);
            }
        }

        $slug = \Illuminate\Support\Facades\DB::table('slug')->where('object_id', $id)->value('slug');

        return redirect()->route('ric.entities.show', [$type, $slug])
            ->with('success', ucfirst(rtrim($type, 's')) . ' created successfully.');
    }

    /**
     * Capture Studio — a production-facing landing page focused on RiC-native
     * data entry. Lists each entity type with live counts, quick-create
     * buttons, a recent-additions feed, and a global search.
     */
    public function captureStudio(Request $request)
    {
        $types = [
            [
                'key' => 'places', 'singular' => 'place',
                'title' => 'Place', 'icon' => 'fa-map-marker-alt',
                'description' => 'Geographic or topographical entities. Cities, regions, countries, archaeological sites.',
                'table' => 'ric_place', 'i18n_table' => 'ric_place_i18n', 'label_col' => 'name',
            ],
            [
                'key' => 'rules', 'singular' => 'rule',
                'title' => 'Rule / Mandate', 'icon' => 'fa-gavel',
                'description' => 'Laws, policies, retention schedules, authorising instruments.',
                'table' => 'ric_rule', 'i18n_table' => 'ric_rule_i18n', 'label_col' => 'title',
            ],
            [
                'key' => 'activities', 'singular' => 'activity',
                'title' => 'Activity', 'icon' => 'fa-running',
                'description' => 'Production / accumulation / custody events that shape records over time.',
                'table' => 'ric_activity', 'i18n_table' => 'ric_activity_i18n', 'label_col' => 'name',
            ],
            [
                'key' => 'instantiations', 'singular' => 'instantiation',
                'title' => 'Instantiation', 'icon' => 'fa-file-alt',
                'description' => 'Digital or physical manifestations of a record (TIFF, PDF, microfilm, paper).',
                'table' => 'ric_instantiation', 'i18n_table' => 'ric_instantiation_i18n', 'label_col' => 'title',
            ],
        ];

        foreach ($types as &$t) {
            $t['count'] = (int) \Illuminate\Support\Facades\DB::table($t['table'])->count();
        }
        unset($t);

        $relationCount = (int) \Illuminate\Support\Facades\DB::table('ric_relation_meta')->count();

        // Recent additions — last 10 rows across the 4 RiC-native tables.
        $recent = collect();
        foreach ($types as $t) {
            $rows = \Illuminate\Support\Facades\DB::table($t['table'] . ' as e')
                ->join('object as o', 'e.id', '=', 'o.id')
                ->leftJoin($t['i18n_table'] . ' as i18n', function ($j) use ($t) {
                    $j->on('e.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
                })
                ->leftJoin('slug as s', 'e.id', '=', 's.object_id')
                ->select([
                    'e.id', 's.slug', 'o.created_at',
                    \Illuminate\Support\Facades\DB::raw("'{$t['key']}' as type_key"),
                    \Illuminate\Support\Facades\DB::raw("'{$t['title']}' as type_title"),
                    \Illuminate\Support\Facades\DB::raw("'{$t['icon']}' as type_icon"),
                    "i18n.{$t['label_col']} as label",
                ])
                ->orderBy('o.created_at', 'desc')
                ->limit(5)
                ->get();
            $recent = $recent->merge($rows);
        }
        $recent = $recent->sortByDesc('created_at')->take(10)->values();

        return view('ahg-ric::capture-studio', [
            'types' => $types,
            'relationCount' => $relationCount,
            'recent' => $recent,
        ]);
    }

    /**
     * Global browse of every relation (G8 — standalone relations page).
     */
    public function browseRelations(Request $request)
    {
        // API-3 migration: this admin page is now a pure consumer of the public
        // RiC API. Falls back to the direct DB query if the API call fails for
        // any reason (keeps the page working during infra/deploy transitions).
        $q = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;

        $apiUrl = rtrim(config('app.url'), '/') . '/api/ric/v1/relations';
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get($apiUrl, ['q' => $q, 'page' => $page, 'per_page' => $perPage]);
            if ($response->successful()) {
                $payload = $response->json();
                $rows = collect($payload['data'] ?? [])->map(fn ($r) => (object) $r);
                $total = (int) ($payload['pagination']['total'] ?? 0);
                return view('ahg-ric::relations.browse', [
                    'rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'q' => $q,
                    'sourceBanner' => 'Served via /api/ric/v1/relations (API-3 migration)',
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[browseRelations] API call failed, falling back to direct DB: ' . $e->getMessage());
        }

        // Fallback: direct DB query (legacy path)
        $query = \Illuminate\Support\Facades\DB::table('relation as r')
            ->join('ric_relation_meta as m', 'r.id', '=', 'm.relation_id')
            ->leftJoin('object as subj_o', 'r.subject_id', '=', 'subj_o.id')
            ->leftJoin('object as obj_o', 'r.object_id', '=', 'obj_o.id')
            ->select([
                'r.id', 'r.subject_id', 'r.object_id', 'r.start_date', 'r.end_date',
                'm.rico_predicate', 'm.dropdown_code', 'm.certainty', 'm.evidence',
                'subj_o.class_name as subject_class', 'obj_o.class_name as object_class',
            ])
            ->orderBy('r.id', 'desc');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('m.rico_predicate', 'like', "%{$q}%")
                    ->orWhere('m.evidence', 'like', "%{$q}%")
                    ->orWhere('m.dropdown_code', 'like', "%{$q}%");
            });
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();

        return view('ahg-ric::relations.browse', [
            'rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'q' => $q,
            'sourceBanner' => 'Served via direct DB fallback (API unreachable)',
        ]);
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

        // API-3 migration: try the public API first.
        $apiResult = $this->callRicApi('DELETE', "/api/ric/v1/{$type}/{$entity->id}");
        if ($apiResult && ($apiResult['success'] ?? false)) {
            return redirect()->route("ric.{$type}.browse")
                ->with('success', ucfirst(rtrim($type, 's')) . ' deleted successfully.');
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
