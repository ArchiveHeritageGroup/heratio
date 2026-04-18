<?php

/**
 * LinkedDataApiController - RIC-O Linked Data REST API endpoints
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgRic\Http\Controllers;

use App\Http\Controllers\Controller;
use AhgRic\Services\RicSerializationService;
use AhgRic\Services\ShaclValidationService;
use AhgRic\Services\RicEntityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * REST API Controller for RIC-O Linked Data publication.
 * 
 * Provides Linked Data endpoints for:
 * - Agents (ISAAR)
 * - Functions (ISDF)
 * - Records (ISAD)
 * - Repositories (ISDIAH)
 * - SPARQL queries
 */
class LinkedDataApiController extends Controller
{
    private RicSerializationService $serializer;
    private ShaclValidationService $validator;
    private RicEntityService $entities;

    public function __construct()
    {
        $this->serializer = new RicSerializationService();
        $this->validator = new ShaclValidationService();
        $this->entities = new RicEntityService();
    }

    /**
     * GET /api/ric/v1/agents
     * List all agents (persons, corporate bodies, families)
     */
    public function listAgents(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = min($request->get('limit', 50), 200);
        $type = $request->get('type'); // person, corporate body, family

        $culture = app()->getLocale() ?: 'en';
        $query = \DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term as at', 'a.entity_type_id', '=', 'at.id')
            ->leftJoin('term_i18n as at_i18n', function ($j) use ($culture) {
                $j->on('at.id', '=', 'at_i18n.id')->where('at_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->select([
                'a.id',
                'slug.slug',
                'a.entity_type_id',
                'i18n.authorized_form_of_name as name',
                'at_i18n.name as type',
            ]);

        if ($type) {
            $query->where('at_i18n.name', $type);
        }

        $total = $query->count();
        $agents = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $items = array_map(fn($a) => [
            '@id' => url('/actor/' . $a->slug),
            '@type' => 'rico:' . ($a->type ?? 'Agent'),
            'rico:name' => $a->name,
        ], $agents->toArray());

        return response()->json([
            '@context' => 'https://www.ica.org/standards/RiC/ontology',
            '@type' => 'rico:AgentList',
            'ric:total' => $total,
            'ric:page' => $page,
            'ric:limit' => $limit,
            'ric:items' => $items,
        ]);
    }

    /**
     * GET /api/ric/v1/agents/{slug}
     * Get single agent as RIC-O JSON-LD
     */
    public function showAgent(string $slug): JsonResponse
    {
        $actor = \DB::table('actor')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('actor.*')
            ->first();

        if (!$actor) {
            return response()->json(['error' => 'Agent not found'], 404);
        }

        $ric = $this->serializer->serializeAgent($actor->id);
        $ric = $this->serializer->addIscapCompliance($ric, $actor->id, 'actor');

        return response()->json($ric, 200, [
            'Content-Type' => 'application/ld+json',
            'Vary' => 'Accept',
        ]);
    }

    /**
     * GET /api/ric/v1/records
     * List all records (information objects)
     */
    public function listRecords(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = min($request->get('limit', 50), 200);
        $level = $request->get('level'); // fonds, series, file, item

        $query = \DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', 'io.id', '=', 'i18n.id')
            ->leftJoin('term as level', 'io.level_of_description_id', '=', 'level.id')
            ->leftJoin('term_i18n as level_i18n', 'level.id', '=', 'level_i18n.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->select([
                'io.id',
                'slug.slug',
                'io.identifier',
                'i18n.title',
                'level_i18n.name as level',
            ]);

        if ($level) {
            $query->where('level_i18n.name', $level);
        }

        $total = $query->count();
        $records = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $items = array_map(fn($r) => [
            '@id' => url('/informationobject/' . $r->slug),
            '@type' => 'rico:' . ($r->level === 'item' ? 'Record' : 'RecordSet'),
            'rico:identifier' => $r->identifier,
            'rico:title' => $r->title,
        ], $records->toArray());

        return response()->json([
            '@context' => 'https://www.ica.org/standards/RiC/ontology',
            '@type' => 'rico:RecordList',
            'ric:total' => $total,
            'ric:page' => $page,
            'ric:items' => $items,
        ]);
    }

    /**
     * GET /api/ric/v1/records/{slug}
     * Get single record as RIC-O JSON-LD
     */
    public function showRecord(string $slug): JsonResponse
    {
        $io = \DB::table('information_object')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('information_object.*')
            ->first();

        if (!$io) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        $ric = $this->serializer->serializeRecord($io->id);
        $ric = $this->serializer->addIscapCompliance($ric, $io->id, 'information_object');

        return response()->json($ric, 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    /**
     * GET /api/ric/v1/records/{slug}/export
     * Export entire record set as JSON-LD graph
     */
    public function exportRecordSet(string $slug): JsonResponse
    {
        $io = \DB::table('information_object')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('information_object.*')
            ->first();

        if (!$io) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        $graph = $this->serializer->exportRecordSet($io->id, ['pretty' => true]);

        return response()->json($graph, 200, [
            'Content-Type' => 'application/ld+json',
            'Content-Disposition' => 'attachment; filename="' . $slug . '-ric.jsonld"',
        ]);
    }

    /**
     * GET /api/ric/v1/functions
     * List all functions
     */
    public function listFunctions(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = min($request->get('limit', 50), 200);

        $functions = \DB::table('function_object as f')
            ->leftJoin('function_object_i18n as fi', 'f.id', '=', 'fi.id')
            ->select(['f.id', 'fi.authorized_form_of_name as name', 'fi.description'])
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $items = array_map(fn($f) => [
            '@id' => url('/function/' . $f->id),
            '@type' => 'rico:Function',
            'rico:name' => $f->name,
        ], $functions->toArray());

        return response()->json([
            '@context' => 'https://www.ica.org/standards/RiC/ontology',
            '@type' => 'rico:FunctionList',
            'ric:items' => $items,
        ]);
    }

    /**
     * GET /api/ric/v1/functions/{id}
     * Get single function as RIC-O JSON-LD
     */
    public function showFunction(int $id): JsonResponse
    {
        $ric = $this->serializer->serializeFunction($id);

        if (isset($ric['error'])) {
            return response()->json($ric, 404);
        }

        return response()->json($ric, 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    /**
     * GET /api/ric/v1/repositories
     * List all repositories
     */
    public function listRepositories(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = min($request->get('limit', 50), 200);

        $repos = \DB::table('repository as r')
            ->leftJoin('actor_i18n as i18n', 'r.id', '=', 'i18n.id')
            ->leftJoin('slug', 'r.id', '=', 'slug.object_id')
            ->select(['r.id', 'slug.slug', 'i18n.authorized_form_of_name as name'])
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $items = array_map(fn($r) => [
            '@id' => url('/repository/' . $r->slug),
            '@type' => 'rico:CorporateBody',
            'rico:name' => $r->name,
        ], $repos->toArray());

        return response()->json([
            '@context' => 'https://www.ica.org/standards/RiC/ontology',
            '@type' => 'rico:RepositoryList',
            'ric:items' => $items,
        ]);
    }

    /**
     * GET /api/ric/v1/repositories/{slug}
     * Get single repository as RIC-O JSON-LD
     */
    public function showRepository(string $slug): JsonResponse
    {
        $repo = \DB::table('repository')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('repository.*')
            ->first();

        if (!$repo) {
            return response()->json(['error' => 'Repository not found'], 404);
        }

        $ric = $this->serializer->serializeRepository($repo->id);

        return response()->json($ric, 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    /**
     * GET /api/ric/v1/places
     * List RiC-native Places (paginated).
     */
    public function listPlaces(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min((int) $request->get('limit', 50), 200);
        $type = $request->get('type');
        $culture = app()->getLocale() ?: 'en';

        $query = \DB::table('ric_place as p')
            ->leftJoin('ric_place_i18n as i18n', function ($j) use ($culture) {
                $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->select(['p.id', 'p.type_id', 'p.latitude', 'p.longitude', 'i18n.name']);

        if ($type) {
            $query->where('p.type_id', $type);
        }

        $total = $query->count();
        $places = $query
            ->orderBy('i18n.name')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $items = array_map(fn($p) => [
            '@id' => url('/place/' . $p->id),
            '@type' => 'rico:Place',
            'rico:name' => $p->name,
            'openric:localType' => $p->type_id,
        ], $places->toArray());

        return response()->json([
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
                'openric' => 'https://openric.org/ns/v1#',
            ],
            '@type' => 'rico:PlaceList',
            'openric:total' => $total,
            'openric:page' => $page,
            'openric:limit' => $limit,
            'openric:items' => $items,
        ]);
    }

    /**
     * GET /api/ric/v1/places/{id}
     * Get single RiC-native Place as RIC-O JSON-LD.
     */
    public function showPlace(int $id): JsonResponse
    {
        $ric = $this->serializer->serializePlace($id);

        if (isset($ric['error'])) {
            return response()->json($ric, 404);
        }

        return response()->json($ric, 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    /**
     * GET /api/ric/v1/rules
     * List RiC-native Rules (mandates, laws, policies) — paginated.
     */
    public function listRules(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min((int) $request->get('limit', 50), 200);
        $type = $request->get('type');
        $culture = app()->getLocale() ?: 'en';

        $query = \DB::table('ric_rule as r')
            ->leftJoin('ric_rule_i18n as i18n', function ($j) use ($culture) {
                $j->on('r.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->select([
                'r.id', 'r.type_id', 'r.jurisdiction',
                'r.start_date', 'r.end_date', 'i18n.title',
            ]);

        if ($type) {
            $query->where('r.type_id', $type);
        }

        $total = $query->count();
        $items = $query
            ->orderBy('r.id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $rows = array_map(fn($r) => [
            '@id' => url('/rule/' . $r->id),
            '@type' => 'rico:Rule',
            'rico:title' => $r->title,
            'rico:ruleType' => $r->type_id,
            'openric:jurisdiction' => $r->jurisdiction,
        ], $items->toArray());

        return response()->json([
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
                'openric' => 'https://openric.org/ns/v1#',
            ],
            '@type' => 'rico:RuleList',
            'openric:total' => $total,
            'openric:page' => $page,
            'openric:limit' => $limit,
            'openric:items' => $rows,
        ]);
    }

    /**
     * GET /api/ric/v1/rules/{id}
     * Get single RiC-native Rule as RIC-O JSON-LD.
     */
    public function showRule(int $id): JsonResponse
    {
        $ric = $this->serializer->serializeRule($id);

        if (isset($ric['error'])) {
            return response()->json($ric, 404);
        }

        return response()->json($ric, 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    /**
     * GET /api/ric/v1/activities
     * List RiC-native Activities (paginated). Emitted class is selected
     * per mapping spec §6.5 based on type_id.
     */
    public function listActivities(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min((int) $request->get('limit', 50), 200);
        $type = $request->get('type');
        $culture = app()->getLocale() ?: 'en';

        $eventTypeToRic = [
            'creation' => 'Production', 'production' => 'Production',
            'contribution' => 'Production', 'accumulation' => 'Accumulation',
            'collection' => 'Accumulation',
        ];

        $query = \DB::table('ric_activity as a')
            ->leftJoin('ric_activity_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->select(['a.id', 'a.type_id', 'a.start_date', 'a.end_date', 'i18n.name']);

        if ($type) {
            $query->where('a.type_id', $type);
        }

        $total = $query->count();
        $items = $query
            ->orderBy('a.id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $rows = array_map(fn($a) => [
            '@id' => url('/activity/' . $a->id),
            '@type' => 'rico:' . ($eventTypeToRic[strtolower($a->type_id ?? '')] ?? 'Activity'),
            'rico:name' => $a->name,
            'openric:localType' => $a->type_id,
        ], $items->toArray());

        return response()->json([
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
                'openric' => 'https://openric.org/ns/v1#',
            ],
            '@type' => 'rico:ActivityList',
            'openric:total' => $total,
            'openric:page' => $page,
            'openric:limit' => $limit,
            'openric:items' => $rows,
        ]);
    }

    /**
     * GET /api/ric/v1/activities/{id}
     * Get single RiC-native Activity as RIC-O JSON-LD.
     */
    public function showActivity(int $id): JsonResponse
    {
        $ric = $this->serializer->serializeActivity($id);

        if (isset($ric['error'])) {
            return response()->json($ric, 404);
        }

        return response()->json($ric, 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    /**
     * GET /api/ric/v1/instantiations
     * List RiC-native Instantiations (paginated).
     */
    public function listInstantiations(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min((int) $request->get('limit', 50), 200);
        $carrier = $request->get('carrier');
        $mime = $request->get('mime');
        $culture = app()->getLocale() ?: 'en';

        $query = \DB::table('ric_instantiation as ri')
            ->leftJoin('ric_instantiation_i18n as i18n', function ($j) use ($culture) {
                $j->on('ri.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->select([
                'ri.id', 'ri.record_id', 'ri.carrier_type', 'ri.mime_type',
                'ri.extent_value', 'ri.extent_unit', 'i18n.title',
            ]);

        if ($carrier) {
            $query->where('ri.carrier_type', $carrier);
        }
        if ($mime) {
            $query->where('ri.mime_type', $mime);
        }

        $total = $query->count();
        $items = $query
            ->orderBy('ri.id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $rows = array_map(fn($i) => [
            '@id' => url('/instantiation/' . $i->id),
            '@type' => 'rico:Instantiation',
            'rico:identifier' => $i->title,
            'rico:hasMimeType' => $i->mime_type,
            'rico:hasCarrierType' => $i->carrier_type,
        ], $items->toArray());

        return response()->json([
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
                'openric' => 'https://openric.org/ns/v1#',
            ],
            '@type' => 'rico:InstantiationList',
            'openric:total' => $total,
            'openric:page' => $page,
            'openric:limit' => $limit,
            'openric:items' => $rows,
        ]);
    }

    /**
     * GET /api/ric/v1/instantiations/{id}
     * Get single RiC-native Instantiation as RIC-O JSON-LD.
     */
    public function showInstantiation(int $id): JsonResponse
    {
        $ric = $this->serializer->serializeInstantiation($id);

        if (isset($ric['error'])) {
            return response()->json($ric, 404);
        }

        return response()->json($ric, 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    /**
     * GET /api/ric/v1/sparql
     * Execute SPARQL query against triplestore
     */
    public function sparql(Request $request): JsonResponse
    {
        $query = $request->get('query');
        
        if (!$query) {
            return response()->json([
                'error' => 'SPARQL query required',
                'example' => 'SELECT ?s ?p ?o WHERE { ?s ?p ?o } LIMIT 10',
            ], 400);
        }

        // Sanitize and execute query
        $result = $this->executeSparql($query);

        return response()->json([
            '@context' => [
                'ric' => 'https://www.ica.org/standards/RiC/ontology#',
                'results' => 'http://www.w3.org/2005/sparql-results#',
            ],
            'sparql' => $query,
            'results' => $result,
        ]);
    }

    /**
     * Execute SPARQL query
     */
    private function executeSparql(string $query): array
    {
        $endpoint = config('heratio.fuseki_endpoint', 'http://localhost:3030/heratio');
        $url = $endpoint . '/sparql';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'query' => $query,
                'format' => 'json',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return ['error' => 'SPARQL query failed', 'http_code' => $httpCode];
        }

        $data = json_decode($response, true);

        return [
            'bindings' => $data['results']['bindings'] ?? [],
            'head' => $data['head']['vars'] ?? [],
        ];
    }

    /**
     * GET /api/ric/v1/graph
     * Returns an OpenRiC Subgraph rooted at the given entity URI, per
     * the OpenRiC Viewing API spec §4.7. Shape matches graph-primitives.md §3:
     *   { @context, @type: "openric:Subgraph", openric:root, openric:depth,
     *     openric:nodes, openric:edges }
     */
    public function graph(Request $request): JsonResponse
    {
        $uri = $request->get('uri');
        $depth = max(1, min((int) $request->get('depth', 1), 3));

        if (!$uri) {
            return response()->json([
                'error' => 'uri parameter required',
                'example' => '/api/ric/v1/graph?uri=' . url('/informationobject/my-fonds'),
            ], 400);
        }

        // Parse URI → entity type + key. URL shape: <base>/<type>/<id-or-slug>.
        $path = rtrim((string) parse_url($uri, PHP_URL_PATH), '/');
        $parts = $path ? array_values(array_filter(explode('/', $path), 'strlen')) : [];
        if (count($parts) < 2) {
            return response()->json(['error' => 'uri must point to a single entity (shape: /<type>/<id-or-slug>)'], 400);
        }
        $lastSegment = $parts[count($parts) - 1];
        $entityType  = $parts[count($parts) - 2];

        $ricController = new \AhgRic\Controllers\RicController();
        $instanceId = \AhgCore\Services\SettingHelper::get('ahg_ric_instance_id', 'heratio');
        $baseUri = config('app.url', url('/'));

        // Dispatch by entity type from the URI.
        $graph = null;
        switch ($entityType) {
            case 'informationobject':
            case 'record':
            case 'recordset':
                $recordId = ctype_digit($lastSegment)
                    ? (int) $lastSegment
                    : (int) \DB::table('slug')->where('slug', $lastSegment)->value('object_id');
                if ($recordId) {
                    $graph = $ricController->buildGraphFromDatabase($recordId, $baseUri, $instanceId);
                }
                break;

            case 'actor':
            case 'person':
            case 'corporatebody':
            case 'family':
                $actorId = ctype_digit($lastSegment)
                    ? (int) $lastSegment
                    : (int) \DB::table('slug')->where('slug', $lastSegment)->value('object_id');
                if ($actorId) {
                    $graph = $this->buildAgentGraph($actorId, $baseUri, $instanceId);
                }
                break;

            case 'repository':
                $repoId = ctype_digit($lastSegment)
                    ? (int) $lastSegment
                    : (int) \DB::table('slug')->where('slug', $lastSegment)->value('object_id');
                if ($repoId) {
                    $graph = $this->buildRepositoryGraph($repoId, $baseUri, $instanceId);
                }
                break;

            case 'place':
                if (ctype_digit($lastSegment)) {
                    $graph = $this->buildPlaceGraph((int) $lastSegment, $baseUri, $instanceId);
                }
                break;

            case 'activity':
                if (ctype_digit($lastSegment)) {
                    $graph = $this->buildActivityGraph((int) $lastSegment, $baseUri, $instanceId);
                }
                break;

            case 'rule':
                if (ctype_digit($lastSegment)) {
                    $graph = $this->buildRuleGraph((int) $lastSegment, $baseUri, $instanceId);
                }
                break;

            case 'instantiation':
                if (ctype_digit($lastSegment)) {
                    $graph = $this->buildInstantiationGraph((int) $lastSegment, $baseUri, $instanceId);
                }
                break;
        }

        if (!$graph || empty($graph['nodes'])) {
            return response()->json(['error' => "no entity found for uri {$uri}"], 404);
        }

        // Per graph-primitives.md §6 invariant 1: openric:root MUST appear in nodes.
        // buildGraphFromDatabase always places the root node first, so its id is
        // the authoritative root URI for the invariant check.
        $nodes = $graph['nodes'] ?? [];
        $rootUri = $nodes[0]['id'] ?? $uri;

        return response()->json([
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
                'openric' => 'https://openric.org/ns/v1#',
            ],
            '@type' => 'openric:Subgraph',
            'openric:root' => $rootUri,
            'openric:depth' => $depth,
            'openric:nodes' => $nodes,
            'openric:edges' => $graph['edges'] ?? [],
        ], 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    /**
     * POST /api/ric/v1/validate
     * Validate RIC-O entity against SHACL shapes
     */
    public function validate(Request $request): JsonResponse
    {
        $entity = $request->input('entity');
        $type = $request->input('type', 'unknown');

        if (!$entity) {
            return response()->json(['error' => 'entity required'], 400);
        }

        $result = $this->validator->validateBeforeSave($entity, $type);

        return response()->json([
            'valid' => $result['valid'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
        ]);
    }

    /**
     * GET /api/ric/v1/vocabulary
     * Get RIC-O vocabulary terms
     */
    public function vocabulary(): JsonResponse
    {
        $vocab = [
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            ],
            '@type' => 'ric:Vocabulary',
            'classes' => [
                ['@id' => 'rico:Agent', 'rdfs:label' => 'Agent'],
                ['@id' => 'rico:Person', 'rdfs:label' => 'Person'],
                ['@id' => 'rico:CorporateBody', 'rdfs:label' => 'Corporate Body'],
                ['@id' => 'rico:Family', 'rdfs:label' => 'Family'],
                ['@id' => 'rico:Function', 'rdfs:label' => 'Function'],
                ['@id' => 'rico:Record', 'rdfs:label' => 'Record'],
                ['@id' => 'rico:RecordSet', 'rdfs:label' => 'Record Set'],
                ['@id' => 'rico:RecordPart', 'rdfs:label' => 'Record Part'],
                ['@id' => 'rico:Instantiation', 'rdfs:label' => 'Instantiation'],
                ['@id' => 'rico:Place', 'rdfs:label' => 'Place'],
                ['@id' => 'rico:Activity', 'rdfs:label' => 'Activity'],
            ],
            'properties' => [
                ['@id' => 'rico:name', 'rdfs:label' => 'Name'],
                ['@id' => 'rico:identifier', 'rdfs:label' => 'Identifier'],
                ['@id' => 'rico:hasDateRangeSet', 'rdfs:label' => 'Has Date Range Set'],
                ['@id' => 'rico:hasCreator', 'rdfs:label' => 'Has Creator'],
                ['@id' => 'rico:heldBy', 'rdfs:label' => 'Held By'],
                ['@id' => 'rico:hasInstantiation', 'rdfs:label' => 'Has Instantiation'],
                ['@id' => 'rico:hasSubject', 'rdfs:label' => 'Has Subject'],
            ],
        ];

        return response()->json($vocab);
    }

    // ========================================================================
    // Subgraph builders for non-record root URIs. Each returns a minimal
    // {nodes: [...], edges: [...]} dict; the root node is always first.
    // ========================================================================

    private function buildAgentGraph(int $actorId, string $baseUri, string $instanceId): array
    {
        $culture = app()->getLocale() ?: 'en';
        $actor = \DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('a.id', $actorId)
            ->select('a.id', 'i18n.authorized_form_of_name as name')
            ->first();
        if (!$actor) return ['nodes' => [], 'edges' => []];

        $rootUri = $baseUri . '/' . $instanceId . '/person/' . $actorId;
        $nodes = [['id' => $rootUri, 'label' => $actor->name ?: 'Agent ' . $actorId, 'type' => 'Person']];
        $edges = [];
        $seen = [$rootUri => true];

        // Records linked via event (creator/accumulator).
        $events = \DB::table('event as e')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('e.object_id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                $j->on('e.type_id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->where('e.actor_id', $actorId)
            ->select('e.object_id', 'ioi.title', 'ti.name as event_type')
            ->limit(25)
            ->get();
        foreach ($events as $ev) {
            if (!$ev->object_id) continue;
            $recUri = $baseUri . '/' . $instanceId . '/recordset/' . $ev->object_id;
            if (!isset($seen[$recUri])) {
                $seen[$recUri] = true;
                $nodes[] = ['id' => $recUri, 'label' => $ev->title ?: 'Record ' . $ev->object_id, 'type' => 'RecordSet'];
            }
            $predicate = match (true) {
                str_contains(strtolower($ev->event_type ?? ''), 'creat'),
                str_contains(strtolower($ev->event_type ?? ''), 'product')
                    => 'rico:hasCreator',
                str_contains(strtolower($ev->event_type ?? ''), 'accumulat')
                    => 'rico:hasAccumulator',
                default => 'rico:isAssociatedWith',
            };
            $edges[] = [
                'source' => $rootUri, 'target' => $recUri,
                'predicate' => $predicate, 'label' => $ev->event_type ?: 'related',
            ];
        }

        // Records linked via relation table (with ric_relation_meta predicates where available).
        $rels = \DB::table('relation as r')
            ->leftJoin('ric_relation_meta as rm', 'r.id', '=', 'rm.relation_id')
            ->leftJoin('information_object_i18n as ioi_o', function ($j) use ($culture) {
                $j->on('r.object_id', '=', 'ioi_o.id')->where('ioi_o.culture', '=', $culture);
            })
            ->leftJoin('object as o_o', 'r.object_id', '=', 'o_o.id')
            ->where('r.subject_id', $actorId)
            ->whereIn('o_o.class_name', ['QubitInformationObject'])
            ->select('r.object_id', 'ioi_o.title', 'rm.rico_predicate')
            ->limit(15)
            ->get();
        foreach ($rels as $rel) {
            $recUri = $baseUri . '/' . $instanceId . '/recordset/' . $rel->object_id;
            if (!isset($seen[$recUri])) {
                $seen[$recUri] = true;
                $nodes[] = ['id' => $recUri, 'label' => $rel->title ?: 'Record ' . $rel->object_id, 'type' => 'RecordSet'];
            }
            $edges[] = [
                'source' => $rootUri, 'target' => $recUri,
                'predicate' => $rel->rico_predicate ?: 'rico:isAssociatedWith',
                'label' => 'associated with',
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function buildRepositoryGraph(int $repoId, string $baseUri, string $instanceId): array
    {
        $culture = app()->getLocale() ?: 'en';
        $repo = \DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('a.id', $repoId)
            ->select('a.id', 'i18n.authorized_form_of_name as name')
            ->first();
        if (!$repo) return ['nodes' => [], 'edges' => []];

        $rootUri = $baseUri . '/' . $instanceId . '/corporatebody/' . $repoId;
        $nodes = [['id' => $rootUri, 'label' => $repo->name ?: 'Repository ' . $repoId, 'type' => 'CorporateBody']];
        $edges = [];
        $seen = [$rootUri => true];

        $holdings = \DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->where('io.repository_id', $repoId)
            ->select('io.id', 'ioi.title')
            ->limit(25)
            ->get();
        foreach ($holdings as $h) {
            $recUri = $baseUri . '/' . $instanceId . '/recordset/' . $h->id;
            if (!isset($seen[$recUri])) {
                $seen[$recUri] = true;
                $nodes[] = ['id' => $recUri, 'label' => $h->title ?: 'Record ' . $h->id, 'type' => 'RecordSet'];
            }
            $edges[] = [
                'source' => $rootUri, 'target' => $recUri,
                'predicate' => 'rico:hasHolding', 'label' => 'has holding',
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function buildPlaceGraph(int $placeId, string $baseUri, string $instanceId): array
    {
        $culture = app()->getLocale() ?: 'en';
        $place = \DB::table('ric_place as p')
            ->leftJoin('ric_place_i18n as i18n', function ($j) use ($culture) {
                $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('p.id', $placeId)
            ->select('p.id', 'p.parent_id', 'i18n.name')
            ->first();
        if (!$place) return ['nodes' => [], 'edges' => []];

        $rootUri = $baseUri . '/' . $instanceId . '/place/' . $placeId;
        $nodes = [['id' => $rootUri, 'label' => $place->name ?: 'Place ' . $placeId, 'type' => 'Place']];
        $edges = [];
        $seen = [$rootUri => true];

        // Parent place
        if ($place->parent_id) {
            $parent = \DB::table('ric_place as p')
                ->leftJoin('ric_place_i18n as i18n', function ($j) use ($culture) {
                    $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->where('p.id', $place->parent_id)
                ->select('p.id', 'i18n.name')->first();
            if ($parent) {
                $parentUri = $baseUri . '/' . $instanceId . '/place/' . $parent->id;
                $nodes[] = ['id' => $parentUri, 'label' => $parent->name ?: 'Place ' . $parent->id, 'type' => 'Place'];
                $seen[$parentUri] = true;
                $edges[] = ['source' => $rootUri, 'target' => $parentUri, 'predicate' => 'rico:isOrWasPartOf', 'label' => 'part of'];
            }
        }

        // Child places
        $children = \DB::table('ric_place as p')
            ->leftJoin('ric_place_i18n as i18n', function ($j) use ($culture) {
                $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('p.parent_id', $placeId)
            ->select('p.id', 'i18n.name')
            ->limit(15)
            ->get();
        foreach ($children as $c) {
            $cUri = $baseUri . '/' . $instanceId . '/place/' . $c->id;
            if (isset($seen[$cUri])) continue;
            $seen[$cUri] = true;
            $nodes[] = ['id' => $cUri, 'label' => $c->name ?: 'Place ' . $c->id, 'type' => 'Place'];
            $edges[] = ['source' => $cUri, 'target' => $rootUri, 'predicate' => 'rico:isOrWasPartOf', 'label' => 'part of'];
        }

        // Activities at this place
        $activities = \DB::table('ric_activity as a')
            ->leftJoin('ric_activity_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('a.place_id', $placeId)
            ->select('a.id', 'a.type_id', 'i18n.name')
            ->limit(15)
            ->get();
        foreach ($activities as $a) {
            $aUri = $baseUri . '/' . $instanceId . '/activity/' . $a->id;
            if (isset($seen[$aUri])) continue;
            $seen[$aUri] = true;
            $nodes[] = ['id' => $aUri, 'label' => $a->name ?: ucfirst($a->type_id ?? 'Activity'), 'type' => 'Activity'];
            $edges[] = ['source' => $aUri, 'target' => $rootUri, 'predicate' => 'rico:hasOrHadLocation', 'label' => 'at'];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function buildActivityGraph(int $activityId, string $baseUri, string $instanceId): array
    {
        $culture = app()->getLocale() ?: 'en';
        $act = \DB::table('ric_activity as a')
            ->leftJoin('ric_activity_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('a.id', $activityId)
            ->select('a.id', 'a.type_id', 'a.place_id', 'i18n.name')->first();
        if (!$act) return ['nodes' => [], 'edges' => []];

        $shortType = match (strtolower($act->type_id ?? '')) {
            'production', 'creation', 'contribution' => 'Production',
            'accumulation', 'collection' => 'Accumulation',
            default => 'Activity',
        };
        $rootUri = $baseUri . '/' . $instanceId . '/activity/' . $activityId;
        $nodes = [['id' => $rootUri, 'label' => $act->name ?: ucfirst($act->type_id ?? 'Activity'), 'type' => $shortType]];
        $edges = [];
        $seen = [$rootUri => true];

        // Linked Place
        if ($act->place_id) {
            $place = \DB::table('ric_place as p')
                ->leftJoin('ric_place_i18n as i18n', function ($j) use ($culture) {
                    $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->where('p.id', $act->place_id)
                ->select('p.id', 'i18n.name')->first();
            if ($place) {
                $pUri = $baseUri . '/' . $instanceId . '/place/' . $place->id;
                $nodes[] = ['id' => $pUri, 'label' => $place->name ?: 'Place ' . $place->id, 'type' => 'Place'];
                $seen[$pUri] = true;
                $edges[] = ['source' => $rootUri, 'target' => $pUri, 'predicate' => 'rico:hasOrHadLocation', 'label' => 'at'];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function buildRuleGraph(int $ruleId, string $baseUri, string $instanceId): array
    {
        $culture = app()->getLocale() ?: 'en';
        $rule = \DB::table('ric_rule as r')
            ->leftJoin('ric_rule_i18n as i18n', function ($j) use ($culture) {
                $j->on('r.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('r.id', $ruleId)
            ->select('r.id', 'i18n.title')->first();
        if (!$rule) return ['nodes' => [], 'edges' => []];

        $rootUri = $baseUri . '/' . $instanceId . '/rule/' . $ruleId;
        return [
            'nodes' => [['id' => $rootUri, 'label' => $rule->title ?: 'Rule ' . $ruleId, 'type' => 'Rule']],
            'edges' => [],
        ];
    }

    private function buildInstantiationGraph(int $instId, string $baseUri, string $instanceId): array
    {
        $culture = app()->getLocale() ?: 'en';
        $inst = \DB::table('ric_instantiation as ri')
            ->leftJoin('ric_instantiation_i18n as i18n', function ($j) use ($culture) {
                $j->on('ri.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as io_i18n', function ($j) use ($culture) {
                $j->on('ri.record_id', '=', 'io_i18n.id')->where('io_i18n.culture', '=', $culture);
            })
            ->where('ri.id', $instId)
            ->select('ri.id', 'ri.record_id', 'ri.mime_type', 'i18n.title', 'io_i18n.title as record_title')
            ->first();
        if (!$inst) return ['nodes' => [], 'edges' => []];

        $rootUri = $baseUri . '/' . $instanceId . '/instantiation/' . $instId;
        $nodes = [[
            'id' => $rootUri,
            'label' => $inst->title ?: ($inst->mime_type ? 'Instantiation (' . $inst->mime_type . ')' : 'Instantiation ' . $instId),
            'type' => 'Instantiation',
        ]];
        $edges = [];
        if ($inst->record_id) {
            $recUri = $baseUri . '/' . $instanceId . '/recordset/' . $inst->record_id;
            $nodes[] = [
                'id' => $recUri,
                'label' => $inst->record_title ?: 'Record ' . $inst->record_id,
                'type' => 'RecordSet',
            ];
            $edges[] = [
                'source' => $recUri, 'target' => $rootUri,
                'predicate' => 'rico:hasInstantiation', 'label' => 'has instantiation',
            ];
        }
        return ['nodes' => $nodes, 'edges' => $edges];
    }

    // ================================================================
    // API-1 READ GAPS — endpoints added per docs/ric-api-read-gaps.md
    // ================================================================

    /**
     * GET /api/ric/v1/relations?q=&page=&per_page=
     * API-R-1: paginated list of every relation with optional search.
     */
    public function listRelations(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(200, max(1, (int) $request->get('per_page', 50)));

        $query = DB::table('relation as r')
            ->join('ric_relation_meta as m', 'r.id', '=', 'm.relation_id')
            ->leftJoin('object as subj_o', 'r.subject_id', '=', 'subj_o.id')
            ->leftJoin('object as obj_o', 'r.object_id', '=', 'obj_o.id')
            ->select([
                'r.id', 'r.subject_id', 'r.object_id', 'r.start_date', 'r.end_date',
                'm.rico_predicate', 'm.inverse_predicate', 'm.dropdown_code',
                'm.certainty', 'm.evidence', 'm.domain_class', 'm.range_class',
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
        $items = $query->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $items,
            'pagination' => [
                'page' => $page, 'per_page' => $perPage, 'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * GET /api/ric/v1/relations-for/{id}
     * API-R-2: incoming + outgoing relations for one entity, grouped.
     */
    public function relationsFor(int $id): JsonResponse
    {
        $relations = $this->entities->getRelationsForEntity($id);
        $grouped = $relations->groupBy('direction');
        return response()->json([
            'entity_id' => $id,
            'outgoing' => $grouped->get('outgoing', collect())->values(),
            'incoming' => $grouped->get('incoming', collect())->values(),
            'total' => $relations->count(),
        ]);
    }

    /**
     * GET /api/ric/v1/hierarchy/{id}?include=parent,children,siblings
     * API-R-3: hierarchical walk. Currently supports ric_place parent/children/siblings.
     */
    public function hierarchy(int $id, Request $request): JsonResponse
    {
        $include = array_filter(explode(',', $request->get('include', 'parent,children,siblings')));

        // Find entity class_name to route the query.
        $className = DB::table('object')->where('id', $id)->value('class_name');

        $result = ['entity_id' => $id, 'class' => $className];

        if ($className === 'RicPlace') {
            $place = DB::table('ric_place')->where('id', $id)->first();
            if (!$place) return response()->json(['error' => 'Not found'], 404);

            if (in_array('parent', $include) && $place->parent_id) {
                $result['parent'] = $this->flatPlace($place->parent_id);
            }
            if (in_array('children', $include)) {
                $result['children'] = DB::table('ric_place as p')
                    ->leftJoin('ric_place_i18n as i18n', function ($j) {
                        $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', 'en');
                    })
                    ->leftJoin('slug as s', 'p.id', '=', 's.object_id')
                    ->where('p.parent_id', $id)
                    ->select('p.id', 'i18n.name', 's.slug', 'p.type_id')
                    ->orderBy('i18n.name')
                    ->get();
            }
            if (in_array('siblings', $include) && $place->parent_id) {
                $result['siblings'] = DB::table('ric_place as p')
                    ->leftJoin('ric_place_i18n as i18n', function ($j) {
                        $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', 'en');
                    })
                    ->leftJoin('slug as s', 'p.id', '=', 's.object_id')
                    ->where('p.parent_id', $place->parent_id)
                    ->where('p.id', '!=', $id)
                    ->select('p.id', 'i18n.name', 's.slug', 'p.type_id')
                    ->orderBy('i18n.name')
                    ->get();
            }
            return response()->json($result);
        }

        // For non-RicPlace entities, fall back to relation-based hierarchy using
        // ric_relation_meta dropdown_code IN (has_part, includes, is_superior_of).
        $hierPredicates = ['has_part', 'includes', 'is_superior_of', 'is_part_of', 'is_included_in'];
        if (in_array('children', $include)) {
            $result['children'] = DB::table('relation as r')
                ->join('ric_relation_meta as m', 'r.id', '=', 'm.relation_id')
                ->leftJoin('object as o', 'r.object_id', '=', 'o.id')
                ->where('r.subject_id', $id)
                ->whereIn('m.dropdown_code', ['has_part', 'includes', 'is_superior_of'])
                ->select('r.object_id as id', 'o.class_name as class', 'm.dropdown_code')
                ->get();
        }
        if (in_array('parent', $include)) {
            $result['parent'] = DB::table('relation as r')
                ->join('ric_relation_meta as m', 'r.id', '=', 'm.relation_id')
                ->leftJoin('object as o', 'r.object_id', '=', 'o.id')
                ->where('r.subject_id', $id)
                ->whereIn('m.dropdown_code', ['is_part_of', 'is_included_in'])
                ->select('r.object_id as id', 'o.class_name as class', 'm.dropdown_code')
                ->first();
        }
        return response()->json($result);
    }

    private function flatPlace(int $id): ?array
    {
        $p = DB::table('ric_place as p')
            ->leftJoin('ric_place_i18n as i18n', function ($j) {
                $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', 'en');
            })
            ->leftJoin('slug as s', 'p.id', '=', 's.object_id')
            ->where('p.id', $id)
            ->select('p.id', 'i18n.name', 's.slug', 'p.type_id')
            ->first();
        return $p ? (array) $p : null;
    }

    /**
     * GET /api/ric/v1/autocomplete?q=&types=&limit=
     * API-R-4: cross-entity autocomplete, public equivalent of admin search.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $types = $request->get('types');
        $limit = min(200, max(1, (int) $request->get('limit', 20)));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = $this->entities->autocompleteEntities($q, $types, $limit);
        return response()->json($results);
    }

    /**
     * GET /api/ric/v1/vocabulary/{taxonomy}
     * API-R-5: dropdown taxonomy by name.
     */
    public function vocabularyByTaxonomy(string $taxonomy): JsonResponse
    {
        $choices = $this->entities->getDropdownChoices($taxonomy);
        if ($choices->isEmpty()) {
            $exists = DB::table('ahg_dropdown')->where('taxonomy', $taxonomy)->exists();
            if (!$exists) {
                return response()->json(['error' => 'Taxonomy not found', 'taxonomy' => $taxonomy], 404);
            }
        }
        return response()->json([
            'taxonomy' => $taxonomy,
            'items' => $choices,
            'count' => $choices->count(),
        ]);
    }

    /**
     * GET /api/ric/v1/records/{id}/entities
     * API-R-6: aggregated linked-RiC data for a record.
     */
    public function entitiesForRecord(int $id, Request $request): JsonResponse
    {
        $types = array_filter(explode(',', $request->get('types', 'place,rule,activity,instantiation')));

        // Find outgoing relations whose object is one of the requested types.
        $result = [];
        if (in_array('place', $types)) {
            $result['places'] = DB::table('relation as r')
                ->join('object as o', 'r.object_id', '=', 'o.id')
                ->where('r.subject_id', $id)
                ->where('o.class_name', 'RicPlace')
                ->leftJoin('ric_place_i18n as i18n', function ($j) {
                    $j->on('r.object_id', '=', 'i18n.id')->where('i18n.culture', 'en');
                })
                ->leftJoin('slug as s', 'r.object_id', '=', 's.object_id')
                ->select('r.object_id as id', 'i18n.name', 's.slug')
                ->distinct()
                ->get();
        }
        foreach (['rule' => 'RicRule', 'activity' => 'RicActivity', 'instantiation' => 'RicInstantiation'] as $type => $class) {
            if (!in_array($type, $types)) continue;
            $i18nTable = "ric_{$type}_i18n";
            $labelCol = $type === 'rule' ? 'title' : ($type === 'instantiation' ? 'title' : 'name');
            $result["{$type}s"] = DB::table('relation as r')
                ->join('object as o', 'r.object_id', '=', 'o.id')
                ->where('r.subject_id', $id)
                ->where('o.class_name', $class)
                ->leftJoin("{$i18nTable} as i18n", function ($j) {
                    $j->on('r.object_id', '=', 'i18n.id')->where('i18n.culture', 'en');
                })
                ->leftJoin('slug as s', 'r.object_id', '=', 's.object_id')
                ->select('r.object_id as id', "i18n.{$labelCol} as name", 's.slug')
                ->distinct()
                ->get();
        }
        return response()->json($result);
    }

    /**
     * GET /api/ric/v1/entities/{id}/info
     * API-R-7: minimal info card for any entity, for popovers and autocomplete details.
     */
    public function entityInfo(int $id): JsonResponse
    {
        $className = DB::table('object')->where('id', $id)->value('class_name');
        if (!$className) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $info = ['id' => $id, 'class' => $className];
        $slug = DB::table('slug')->where('object_id', $id)->value('slug');
        if ($slug) $info['slug'] = $slug;

        match ($className) {
            'RicPlace' => $this->enrichPlaceInfo($info, $id),
            'RicRule' => $this->enrichRuleInfo($info, $id),
            'RicActivity' => $this->enrichActivityInfo($info, $id),
            'RicInstantiation' => $this->enrichInstantiationInfo($info, $id),
            default => $this->enrichGenericInfo($info, $id, $className),
        };

        return response()->json($info);
    }

    private function enrichPlaceInfo(array &$info, int $id): void
    {
        $row = DB::table('ric_place')->join('ric_place_i18n', function ($j) {
            $j->on('ric_place.id', '=', 'ric_place_i18n.id')->where('ric_place_i18n.culture', 'en');
        })->where('ric_place.id', $id)->select('ric_place.type_id', 'ric_place_i18n.name', 'ric_place_i18n.description')->first();
        if ($row) { $info['name'] = $row->name; $info['type'] = $row->type_id; $info['description'] = $row->description; }
    }
    private function enrichRuleInfo(array &$info, int $id): void
    {
        $row = DB::table('ric_rule')->join('ric_rule_i18n', function ($j) {
            $j->on('ric_rule.id', '=', 'ric_rule_i18n.id')->where('ric_rule_i18n.culture', 'en');
        })->where('ric_rule.id', $id)->select('ric_rule.type_id', 'ric_rule_i18n.title', 'ric_rule_i18n.description')->first();
        if ($row) { $info['name'] = $row->title; $info['type'] = $row->type_id; $info['description'] = $row->description; }
    }
    private function enrichActivityInfo(array &$info, int $id): void
    {
        $row = DB::table('ric_activity')->join('ric_activity_i18n', function ($j) {
            $j->on('ric_activity.id', '=', 'ric_activity_i18n.id')->where('ric_activity_i18n.culture', 'en');
        })->where('ric_activity.id', $id)->select('ric_activity.type_id', 'ric_activity_i18n.name', 'ric_activity_i18n.description')->first();
        if ($row) { $info['name'] = $row->name; $info['type'] = $row->type_id; $info['description'] = $row->description; }
    }
    private function enrichInstantiationInfo(array &$info, int $id): void
    {
        $row = DB::table('ric_instantiation')->join('ric_instantiation_i18n', function ($j) {
            $j->on('ric_instantiation.id', '=', 'ric_instantiation_i18n.id')->where('ric_instantiation_i18n.culture', 'en');
        })->where('ric_instantiation.id', $id)->select('ric_instantiation.carrier_type', 'ric_instantiation_i18n.title', 'ric_instantiation_i18n.description')->first();
        if ($row) { $info['name'] = $row->title; $info['type'] = $row->carrier_type; $info['description'] = $row->description; }
    }
    private function enrichGenericInfo(array &$info, int $id, string $className): void
    {
        // Best-effort for actor / information_object etc.
        if ($className === 'QubitActor') {
            $row = DB::table('actor_i18n')->where('id', $id)->where('culture', 'en')->value('authorized_form_of_name');
            if ($row) $info['name'] = $row;
        } elseif ($className === 'QubitInformationObject') {
            $row = DB::table('information_object_i18n')->where('id', $id)->where('culture', 'en')->value('title');
            if ($row) $info['name'] = $row;
        }
    }

    /**
     * GET /api/ric/v1/relation-types?domain=&range=
     * API-R-8: filtered relation-type catalog.
     */
    public function relationTypes(Request $request): JsonResponse
    {
        $types = $this->entities->getRelationTypes($request->get('domain'), $request->get('range'));
        return response()->json([
            'items' => $types->values(),
            'count' => $types->count(),
        ]);
    }

    /**
     * GET /api/ric/v1/places/flat?exclude_id=
     * API-R-9: flat name+id list for parent-picker dropdowns.
     */
    public function placesFlat(Request $request): JsonResponse
    {
        $excludeId = $request->get('exclude_id') !== null ? (int) $request->get('exclude_id') : null;
        $items = $this->entities->listPlacesForPicker($excludeId);
        return response()->json(['items' => $items, 'count' => count($items)]);
    }

    // ================================================================
    // API-2 WRITE SURFACE — gated by api.auth:write middleware on the route.
    // ================================================================

    private function assertEntityTypeAllowed(string $type): void
    {
        if (!in_array($type, ['places', 'rules', 'activities', 'instantiations'])) {
            abort(404, 'Unknown entity type');
        }
    }

    /**
     * POST /api/ric/v1/{type}
     * Create a Place, Rule, Activity, or Instantiation.
     */
    public function createEntity(Request $request, string $type): JsonResponse
    {
        $this->assertEntityTypeAllowed($type);
        $data = $request->all();
        try {
            $id = match ($type) {
                'places' => $this->entities->createPlace($data),
                'rules' => $this->entities->createRule($data),
                'activities' => $this->entities->createActivity($data),
                'instantiations' => $this->entities->createInstantiation($data),
            };
            $slug = DB::table('slug')->where('object_id', $id)->value('slug');
            return response()->json([
                'id' => $id,
                'slug' => $slug,
                'type' => rtrim($type, 's'),
                'href' => "/api/ric/v1/{$type}/" . ($slug ?: $id),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('[RiC API] createEntity failed', ['type' => $type, 'error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * PATCH /api/ric/v1/{type}/{id}
     * Update a Place, Rule, Activity, or Instantiation by ID.
     */
    public function updateEntity(Request $request, string $type, int $id): JsonResponse
    {
        $this->assertEntityTypeAllowed($type);
        $data = $request->all();
        try {
            match ($type) {
                'places' => $this->entities->updatePlace($id, $data),
                'rules' => $this->entities->updateRule($id, $data),
                'activities' => $this->entities->updateActivity($id, $data),
                'instantiations' => $this->entities->updateInstantiation($id, $data),
            };
            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Log::error('[RiC API] updateEntity failed', ['type' => $type, 'id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * DELETE /api/ric/v1/{type}/{id}
     */
    public function deleteEntity(string $type, int $id): JsonResponse
    {
        $this->assertEntityTypeAllowed($type);
        try {
            match ($type) {
                'places' => $this->entities->deletePlace($id),
                'rules' => $this->entities->deleteRule($id),
                'activities' => $this->entities->deleteActivity($id),
                'instantiations' => $this->entities->deleteInstantiation($id),
            };
            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Log::error('[RiC API] deleteEntity failed', ['type' => $type, 'id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/ric/v1/relations
     */
    public function createRelation(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|integer',
            'object_id' => 'required|integer',
            'relation_type' => 'required|string',
        ]);
        try {
            $id = $this->entities->createRelation(
                (int) $request->input('subject_id'),
                (int) $request->input('object_id'),
                $request->input('relation_type'),
                $request->only(['start_date', 'end_date', 'certainty', 'evidence'])
            );
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * PATCH /api/ric/v1/relations/{id}
     */
    public function updateRelation(Request $request, int $id): JsonResponse
    {
        try {
            $this->entities->updateRelation(
                $id,
                $request->only(['start_date', 'end_date', 'certainty', 'evidence', 'relation_type'])
            );
            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * DELETE /api/ric/v1/relations/{id}
     */
    public function deleteRelation(int $id): JsonResponse
    {
        try {
            $this->entities->deleteRelation($id);
            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
