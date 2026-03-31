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

        $query = \DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', 'a.id', '=', 'i18n.id')
            ->leftJoin('term as at', 'a.actor_type_id', '=', 'at.id')
            ->select([
                'a.id',
                'a.slug',
                'a.actor_type_id',
                'i18n.authorized_form_of_name as name',
                'at.name as type',
            ]);

        if ($type) {
            $query->where('at.name', $type);
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
            ->where('slug', $slug)
            ->first();

        if (!$actor) {
            return response()->json(['error' => 'Agent not found'], 404);
        }

        $ric = $this->serializer->serializeAgent($actor->id);

        // Add ISCAP compliance
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
            ->select([
                'io.id',
                'io.slug',
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
            ->where('slug', $slug)
            ->first();

        if (!$io) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        $ric = $this->serializer->serializeRecord($io->id);

        // Add ISCAP compliance
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
            ->where('slug', $slug)
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

        $functions = \DB::table('function as f')
            ->leftJoin('function_i18n as fi', 'f.id', '=', 'fi.id')
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
            ->select(['r.id', 'r.slug', 'i18n.authorized_form_of_name as name'])
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
            ->where('slug', $slug)
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
     * Get entity relationships graph
     */
    public function graph(Request $request): JsonResponse
    {
        $uri = $request->get('uri');
        $depth = min($request->get('depth', 2), 5);

        if (!$uri) {
            return response()->json([
                'error' => 'URI parameter required',
                'example' => '/api/ric/v1/graph?uri=' . url('/actor/1'),
            ], 400);
        }

        // Build SPARQL to get relationships
        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?p ?o WHERE { <{$uri}> ?p ?o }
SPARQL;

        $result = $this->executeSparql($sparql);

        return response()->json([
            '@context' => 'https://www.ica.org/standards/RiC/ontology',
            'entity' => $uri,
            'relationships' => $result['bindings'] ?? [],
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
