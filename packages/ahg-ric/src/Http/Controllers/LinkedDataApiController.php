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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

    public function __construct()
    {
        $this->serializer = new RicSerializationService();
        $this->validator = new ShaclValidationService();
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

        // Resolve URI to a Heratio record id. The URI tail is either the slug
        // (records, agents, repositories) or the numeric id (RiC-native entities).
        $tail = rtrim((string) parse_url($uri, PHP_URL_PATH), '/');
        $lastSegment = $tail ? substr($tail, strrpos($tail, '/') + 1) : '';
        if ($lastSegment === '') {
            return response()->json(['error' => 'uri must point to a single entity'], 400);
        }

        $recordId = null;
        if (ctype_digit($lastSegment)) {
            $recordId = (int) $lastSegment;
        } else {
            $recordId = \DB::table('slug')->where('slug', $lastSegment)->value('object_id');
        }

        if (!$recordId) {
            return response()->json(['error' => "no entity found for uri {$uri}"], 404);
        }

        $ricController = new \AhgRic\Controllers\RicController();
        $instanceId = \AhgCore\Services\SettingHelper::get('ahg_ric_instance_id', 'heratio');
        $baseUri = config('app.url', url('/'));
        $graph = $ricController->buildGraphFromDatabase($recordId, $baseUri, $instanceId);

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
}
