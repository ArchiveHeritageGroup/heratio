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
     * Call a `/api/ric/v1/*` endpoint. Two auth modes:
     *
     * - **In-process mode** (RIC_API_URL unset or matches app.url): the call
     *   goes to the same host; the caller's admin session cookie is forwarded
     *   so the inner request's `api.auth:write` middleware accepts it.
     *
     * - **External-service mode** (RIC_API_URL set, different host): the call
     *   goes to the configured RiC service; the admin session cookie doesn't
     *   cross origins, so we inject the `X-API-Key` header from
     *   RIC_SERVICE_API_KEY instead.
     *
     * Returns decoded JSON on 2xx, or null on any non-2xx / transport failure
     * (callers treat null as "fall back to direct service call").
     */
    protected function callRicApi(string $method, string $path, array $data = [], ?Request $origRequest = null): ?array
    {
        $origRequest ??= request();

        $ricApiUrl = config('ric.api_url');
        $appUrl = rtrim(config('app.url'), '/');
        $appHost = parse_url($appUrl, PHP_URL_HOST);

        // Pick base + auth mode.
        $external = false;
        if ($ricApiUrl) {
            $ricHost = parse_url($ricApiUrl, PHP_URL_HOST);
            $external = $ricHost && $ricHost !== $appHost;
        }

        if ($external) {
            // Strip the /api/ric/v1 suffix if present in RIC_API_URL so we can
            // join with $path which starts with /api/ric/v1/...
            $base = rtrim($ricApiUrl, '/');
            if (str_ends_with($base, '/api/ric/v1')) {
                $base = substr($base, 0, -strlen('/api/ric/v1'));
            }
            $url = $base . $path;
            $serviceKey = config('ric.service_key');
            $timeout = (int) config('ric.http_timeout', 5);
        } else {
            $url = $appUrl . $path;
            $serviceKey = null;
            $timeout = 5;
        }

        try {
            $client = \Illuminate\Support\Facades\Http::timeout($timeout);
            $client = $client->acceptJson()->asJson();

            if ($external) {
                if (!$serviceKey) {
                    \Illuminate\Support\Facades\Log::error('[callRicApi] RIC_API_URL set but RIC_SERVICE_API_KEY missing — cannot authenticate external call.');
                    return null;
                }
                $client = $client->withHeaders(['X-API-Key' => $serviceKey]);
            } else {
                // In-process: forward session cookie for admin auth.
                $sessionCookieName = config('session.cookie');
                if ($sessionCookieName && $origRequest->cookies->has($sessionCookieName)) {
                    $client = $client->withCookies(
                        [$sessionCookieName => $origRequest->cookies->get($sessionCookieName)],
                        $appHost ?: 'localhost',
                    );
                }
            }

            $response = match (strtolower($method)) {
                'post' => $client->post($url, $data),
                'put', 'patch' => $client->{strtolower($method)}($url, $data),
                'delete' => $client->delete($url, $data),
                default => $client->get($url, $data),
            };
            if ($response->successful()) {
                return $response->json();
            }
            \Illuminate\Support\Facades\Log::info("[callRicApi] {$method} {$path} returned {$response->status()}" . ($external ? ' (external)' : ''));
            return null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[callRicApi] {$method} {$path} failed: " . $e->getMessage());
            return null;
        }
    }

    // ================================================================
    // RECORD-LEVEL AJAX ENDPOINTS (called from IO/Actor show pages)
    // ================================================================

    // ================================================================
    // AJAX ADMIN WRAPPERS — REMOVED 2026-04-18.
    //
    // Every Blade view now calls /api/ric/v1/* directly; the admin
    // /entity-api/* routes + their controller methods are gone. The
    // form-based browse/show/edit/create handlers below remain because
    // they perform server-side redirects after submit (distinct from
    // the pure-JSON public API endpoints).
    // ================================================================

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

    // dropdownChoices AJAX method removed 2026-04-18 — callers now use
    // GET /api/ric/v1/vocabulary/{taxonomy} on the public API.
}
