<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * OpenAPI 3.0 spec for the OpenRiC Viewing + Write API. Consumed by:
 *   - LinkedDataApiController::openapi()  — JSON at /api/ric/v1/openapi.json
 *   - The /api/ric/v1/docs Swagger UI page
 *   - openric.org/api-explorer/ (static copy checked into openric-spec)
 *
 * Keep this file the single source of truth. When an endpoint changes,
 * update this spec; the explorer picks up the change immediately.
 */

namespace AhgRic\Support;

class OpenApiSpec
{
    public static function build(string $baseUrl): array
    {
        $base = rtrim($baseUrl, '/');
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'OpenRiC Reference API',
                'description' => implode("\n\n", [
                    'Reference implementation of the OpenRiC Viewing + Write API.',
                    'The same surface any OpenRiC-conformant server is expected to provide. See the spec at https://openric.org/.',
                    'All read endpoints are public. Write endpoints require `X-API-Key` with the appropriate scope (`write` / `delete`).',
                ]),
                'version' => '1.0.0',
                'license' => ['name' => 'AGPL-3.0-or-later', 'url' => 'https://www.gnu.org/licenses/agpl-3.0.html'],
                'contact' => ['name' => 'OpenRiC', 'url' => 'https://openric.org/'],
            ],
            'servers' => [['url' => $base, 'description' => 'This server']],
            'security' => [[], ['ApiKeyAuth' => []]],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key',
                        'description' => 'Issued by the server admin. Three scopes: `write`, `delete`, implicit `read`.'],
                ],
                'schemas' => self::schemas(),
            ],
            'tags' => [
                ['name' => 'Discovery', 'description' => 'API info, health, vocabulary, OpenAPI spec.'],
                ['name' => 'Agents',    'description' => 'rico:Agent / Person / CorporateBody / Family (ISAAR-CPF).'],
                ['name' => 'Records',   'description' => 'rico:Record / RecordSet (ISAD archival descriptions).'],
                ['name' => 'Places',    'description' => 'rico:Place — geographic / topographical entities.'],
                ['name' => 'Rules',     'description' => 'rico:Rule — mandates, legislation, policies.'],
                ['name' => 'Activities','description' => 'rico:Activity — production / accumulation events.'],
                ['name' => 'Instantiations', 'description' => 'rico:Instantiation — carriers of bitstreams (files, images).'],
                ['name' => 'Repositories', 'description' => 'rico:Repository / Custodian (ISDIAH).'],
                ['name' => 'Functions',    'description' => 'rico:Function (ISDF).'],
                ['name' => 'Relations', 'description' => 'rico:Relation — typed links between entities.'],
                ['name' => 'Graph',     'description' => 'Subgraph walks + full SPARQL.'],
                ['name' => 'Uploads',   'description' => 'Multipart file upload.'],
                ['name' => 'Harvest',   'description' => 'OAI-PMH v2.0 harvester endpoint.'],
                ['name' => 'Keys',      'description' => 'Self-service API key request flow (no auth required).'],
            ],
            'paths' => self::paths(),
        ];
    }

    private static function paths(): array
    {
        $listParams = [
            ['name' => 'page',  'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
            ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 50, 'maximum' => 200]],
        ];
        $okJsonLd = ['200' => ['description' => 'OK', 'content' => ['application/ld+json' => ['schema' => ['$ref' => '#/components/schemas/JsonLd']]]]];
        $idPath   = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
        $slugPath = ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];

        $typeSchemas = [
            'places'         => 'PlaceCreate',
            'rules'          => 'RuleCreate',
            'activities'     => 'ActivityCreate',
            'instantiations' => 'InstantiationCreate',
        ];

        $paths = [
            // -------- Discovery --------
            '/'            => ['get' => self::op('Discovery', 'API root', [], $okJsonLd)],
            '/health'      => ['get' => self::op('Discovery', 'Health probe', [], ['200' => ['description' => '{"status":"ok"}',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Health']]]]])],
            '/openapi.json' => ['get' => self::op('Discovery', 'This document', [], $okJsonLd)],
            '/docs'        => ['get' => self::op('Discovery', 'Swagger UI explorer', [], ['200' => ['description' => 'HTML page']])],
            '/vocabulary'  => ['get' => self::op('Discovery', 'Ontology classes + properties', [], $okJsonLd)],
            '/vocabulary/{taxonomy}' => ['get' => self::op('Discovery', 'Single dropdown taxonomy',
                [['name' => 'taxonomy', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], $okJsonLd)],

            // -------- Agents --------
            '/agents' => [
                'get'  => self::op('Agents', 'List agents', array_merge($listParams, [
                    ['name' => 'type', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['person', 'corporate body', 'family']]],
                ]), $okJsonLd),
                'post' => self::op('Agents', 'Create an Agent', [], ['201' => ['description' => 'Created',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateResponse']]]]],
                    'AgentCreate', true),
            ],
            '/agents/{slug}' => ['get' => self::op('Agents', 'Get agent by slug', [$slugPath], $okJsonLd)],
            '/agents/{id}'   => [
                'patch'  => self::op('Agents', 'Update agent', [$idPath],
                    ['200' => self::successResp()], 'AgentUpdate', true),
                'delete' => self::op('Agents', 'Delete agent', [$idPath],
                    ['200' => self::successResp(), '404' => self::errorResp()], null, true),
            ],

            // -------- Records --------
            '/records' => [
                'get'  => self::op('Records', 'List records', array_merge($listParams, [
                    ['name' => 'level', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['fonds', 'series', 'file', 'item']]],
                ]), $okJsonLd),
                'post' => self::op('Records', 'Create a Record', [],
                    ['201' => ['description' => 'Created',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateResponse']]]]],
                    'RecordCreate', true),
            ],
            '/records/{slug}' => ['get' => self::op('Records', 'Get record by slug', [$slugPath], $okJsonLd)],
            '/records/{slug}/export' => ['get' => self::op('Records', 'Export full record set as JSON-LD',
                [$slugPath], $okJsonLd)],
            '/records/{id}/entities' => ['get' => self::op('Records', 'Linked RiC entities for a record',
                [$idPath, ['name' => 'types', 'in' => 'query', 'schema' => ['type' => 'string',
                    'example' => 'place,rule,activity,instantiation']]], $okJsonLd)],
            '/records/{id}' => [
                'patch'  => self::op('Records', 'Update record', [$idPath],
                    ['200' => self::successResp(), '404' => self::errorResp()], 'RecordUpdate', true),
                'delete' => self::op('Records', 'Delete record (refuses if has descendants)', [$idPath],
                    ['200' => self::successResp(), '404' => self::errorResp(),
                     '409' => ['description' => 'Has descendants — delete/re-parent them first.']], null, true),
            ],
        ];

        // Places / Rules / Activities / Instantiations — similar-shaped CRUD
        foreach ([
            ['Places', 'places', 'Place'],
            ['Rules', 'rules', 'Rule'],
            ['Activities', 'activities', 'Activity'],
            ['Instantiations', 'instantiations', 'Instantiation'],
        ] as [$tag, $slug, $className]) {
            $paths["/{$slug}"] = [
                'get'  => self::op($tag, "List {$tag}", $listParams, $okJsonLd),
                'post' => self::op($tag, "Create a {$className}", [],
                    ['201' => ['description' => 'Created',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateResponse']]]]],
                    "{$className}Create", true),
            ];
            $paths["/{$slug}/{id}"] = [
                'get'    => self::op($tag, "Get {$className} by id", [$idPath], $okJsonLd),
                'patch'  => self::op($tag, "Update {$className}", [$idPath],
                    ['200' => self::successResp()], "{$className}Update", true),
                'delete' => self::op($tag, "Delete {$className}", [$idPath],
                    ['200' => self::successResp(), '404' => self::errorResp()], null, true),
            ];
        }

        $paths['/places/flat'] = ['get' => self::op('Places', 'Flat name+id list for parent-picker',
            [['name' => 'exclude_id', 'in' => 'query', 'schema' => ['type' => 'integer']]], $okJsonLd)];

        // -------- Repositories + Functions --------
        $paths['/repositories'] = [
            'get'  => self::op('Repositories', 'List repositories', $listParams, $okJsonLd),
            'post' => self::op('Repositories', 'Create a Repository', [],
                ['201' => ['description' => 'Created',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateResponse']]]]],
                'RepositoryCreate', true),
        ];
        $paths['/repositories/{slug}'] = ['get' => self::op('Repositories', 'Get repository by slug', [$slugPath], $okJsonLd)];
        $paths['/repositories/{id}'] = [
            'patch'  => self::op('Repositories', 'Update repository', [$idPath],
                ['200' => self::successResp(), '404' => self::errorResp()], 'RepositoryUpdate', true),
            'delete' => self::op('Repositories', 'Delete repository (refuses if owns records)', [$idPath],
                ['200' => self::successResp(), '404' => self::errorResp(),
                 '409' => ['description' => 'Repository owns information_objects — re-assign first.']], null, true),
        ];
        $paths['/functions'] = [
            'get'  => self::op('Functions', 'List functions', $listParams, $okJsonLd),
            'post' => self::op('Functions', 'Create a Function', [],
                ['201' => ['description' => 'Created',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateResponse']]]]],
                'FunctionCreate', true),
        ];
        $paths['/functions/{id}']      = [
            'get'    => self::op('Functions', 'Get function by id', [$idPath], $okJsonLd),
            'patch'  => self::op('Functions', 'Update function', [$idPath],
                ['200' => self::successResp(), '404' => self::errorResp()], 'FunctionUpdate', true),
            'delete' => self::op('Functions', 'Delete function', [$idPath],
                ['200' => self::successResp(), '404' => self::errorResp()], null, true),
        ];

        // -------- Relations --------
        $paths['/relations'] = [
            'get'  => self::op('Relations', 'List relations', array_merge($listParams, [
                ['name' => 'type', 'in' => 'query', 'schema' => ['type' => 'string']],
            ]), $okJsonLd),
            'post' => self::op('Relations', 'Create a relation', [],
                ['201' => ['description' => 'Created',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateResponse']]]]],
                'RelationCreate', true),
        ];
        $paths['/relations/{id}'] = [
            'patch'  => self::op('Relations', 'Update relation', [$idPath],
                ['200' => self::successResp()], 'RelationUpdate', true),
            'delete' => self::op('Relations', 'Delete relation', [$idPath],
                ['200' => self::successResp()], null, true),
        ];
        $paths['/relations-for/{id}'] = ['get' => self::op('Relations', 'Relations for one entity, grouped by direction', [$idPath], $okJsonLd)];
        $paths['/relation-types'] = ['get' => self::op('Relations', 'Relation type catalog',
            [['name' => 'domain', 'in' => 'query', 'schema' => ['type' => 'string']],
             ['name' => 'range',  'in' => 'query', 'schema' => ['type' => 'string']]], $okJsonLd)];

        $paths['/entities/{id}/info'] = ['get' => self::op('Discovery', 'Minimal entity info card', [$idPath], $okJsonLd)];
        $paths['/entities/{id}']      = ['delete' => self::op('Discovery', 'Type-agnostic delete by id',
            [$idPath], ['200' => self::successResp(), '404' => self::errorResp()], null, true)];
        $paths['/hierarchy/{id}'] = ['get' => self::op('Records', 'Hierarchy walk',
            [$idPath, ['name' => 'include', 'in' => 'query',
                'schema' => ['type' => 'string', 'example' => 'parent,children,siblings']]], $okJsonLd)];
        $paths['/autocomplete'] = ['get' => self::op('Discovery', 'Cross-entity label autocomplete',
            [['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
             ['name' => 'types', 'in' => 'query', 'schema' => ['type' => 'string', 'example' => 'place,agent,record']],
             ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 10]]], $okJsonLd)];

        // -------- Graph + SPARQL --------
        $paths['/graph'] = ['get' => self::op('Graph', 'Subgraph walk',
            [['name' => 'uri', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'],
                'description' => 'Seed URI (an @id returned by any list endpoint)'],
             ['name' => 'depth', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1, 'maximum' => 3]]],
            ['200' => ['description' => 'openric:Subgraph',
                'content' => ['application/ld+json' => ['schema' => ['$ref' => '#/components/schemas/Subgraph']]]]])];
        $paths['/sparql'] = ['get' => array_merge(
            self::op('Graph', '⚠ EXPERIMENTAL — SPARQL query endpoint',
                [['name' => 'query', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']]], $okJsonLd),
            ['description' => 'Experimental and optional. The reference implementation currently returns a stub response. Not part of the conformance-required surface. May be removed or replaced with a proper triplestore-backed endpoint in a future release.',
             'deprecated' => false])];

        // -------- Uploads --------
        $paths['/upload'] = ['post' => array_merge(
            self::op('Uploads', 'Upload a file (multipart)', [],
                ['201' => ['description' => 'Created',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UploadResponse']]]],
                 '413' => ['description' => 'File too large']], null, true),
            ['requestBody' => ['required' => true, 'content' => [
                'multipart/form-data' => ['schema' => ['type' => 'object',
                    'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
                    'required' => ['file']]]]]])];

        // -------- OAI-PMH --------
        $paths['/oai'] = ['get' => self::op('Harvest', 'OAI-PMH v2.0 endpoint',
            [['name' => 'verb', 'in' => 'query', 'required' => true,
                'schema' => ['type' => 'string',
                    'enum' => ['Identify', 'ListMetadataFormats', 'ListSets', 'ListIdentifiers', 'ListRecords', 'GetRecord']]],
             ['name' => 'metadataPrefix', 'in' => 'query',
                'schema' => ['type' => 'string', 'enum' => ['oai_dc', 'rico_ld']]],
             ['name' => 'identifier', 'in' => 'query', 'schema' => ['type' => 'string', 'example' => 'oai:ric.theahg.co.za:1']],
             ['name' => 'set', 'in' => 'query', 'schema' => ['type' => 'string', 'example' => 'fonds:1']],
             ['name' => 'from', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
             ['name' => 'until', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
             ['name' => 'resumptionToken', 'in' => 'query', 'schema' => ['type' => 'string']]],
            ['200' => ['description' => 'OAI-PMH XML envelope',
                'content' => ['application/xml' => ['schema' => ['type' => 'string']]]]])];

        // -------- Validation --------
        $paths['/validate'] = ['post' => self::op('Graph', 'SHACL validation',
            [], ['200' => ['description' => 'Validation report',
                'content' => ['application/json' => ['schema' => ['type' => 'object']]]]], 'ValidateRequest')];

        // -------- Bulk import + thumbnails + revisions --------
        $paths['/import'] = ['post' => array_merge(
            self::op('Uploads', 'Bulk-create entities from CSV or JSON',
                [['name' => 'type', 'in' => 'query', 'required' => true,
                  'schema' => ['type' => 'string',
                    'enum' => ['places', 'rules', 'activities', 'instantiations', 'agents', 'records', 'repositories', 'functions']]],
                 ['name' => 'format', 'in' => 'query',
                  'schema' => ['type' => 'string', 'enum' => ['csv', 'json']]],
                 ['name' => 'dry_run', 'in' => 'query',
                  'schema' => ['type' => 'boolean', 'default' => false]]],
                ['201' => ['description' => 'Per-row report',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ImportResponse']]]],
                 '413' => ['description' => 'Too many rows (default 10 000)']], null, true),
            ['requestBody' => ['content' => [
                'multipart/form-data' => ['schema' => ['type' => 'object',
                    'properties' => ['file' => ['type' => 'string', 'format' => 'binary']]]],
                'application/json' => ['schema' => ['oneOf' => [
                    ['type' => 'array', 'items' => ['type' => 'object']],
                    ['type' => 'object', 'properties' => ['rows' => ['type' => 'array', 'items' => ['type' => 'object']]]],
                ]]],
            ]]])];

        $paths['/thumbnail/{id}'] = ['get' => self::op('Uploads', 'Derivative thumbnail for a digital_object id',
            [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
             ['name' => 'w', 'in' => 'query', 'schema' => ['type' => 'integer', 'enum' => [150, 300, 600, 1200], 'default' => 300]],
             ['name' => 'h', 'in' => 'query', 'schema' => ['type' => 'integer', 'enum' => [150, 300, 600, 1200]]]],
            ['302' => ['description' => 'Redirect to the cached thumbnail file URL'],
             '404' => ['description' => 'Digital object or source file not found'],
             '415' => ['description' => 'Source is not an image']])];

        $paths['/{type}/{id}/revisions'] = ['get' => self::op('Discovery', 'Audit-log entries for one entity',
            [['name' => 'type', 'in' => 'path', 'required' => true,
              'schema' => ['type' => 'string', 'enum' => ['places', 'rules', 'activities', 'instantiations', 'agents', 'records', 'repositories', 'functions', 'relations']]],
             ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
             ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 50, 'maximum' => 200]]],
            ['200' => ['description' => 'Revision list',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RevisionList']]]]])];

        // -------- Self-service key request (no auth) --------
        $paths['/keys/request'] = [
            'get'  => self::op('Keys', 'Key request form (HTML)', [],
                ['200' => ['description' => 'HTML form for requesting an API key.']]),
            'post' => self::op('Keys', 'Submit a key request', [],
                ['201' => ['description' => 'Accepted',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/KeyRequestAccepted']]]],
                 '429' => ['description' => 'Rate-limited — too many requests from your IP in the last 24h.']],
                'KeyRequest'),
        ];
        $paths['/keys/request/{id}'] = ['get' => self::op('Keys', 'Check request status (no secret revealed)',
            [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
            ['200' => ['description' => 'Status',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/KeyRequestStatus']]]],
             '404' => ['description' => 'Not found']])];

        ksort($paths);
        return $paths;
    }

    private static function op(string $tag, string $summary, array $params, array $responses,
                               ?string $requestSchema = null, bool $needsAuth = false): array
    {
        $op = ['tags' => [$tag], 'summary' => $summary, 'parameters' => $params, 'responses' => $responses];
        if ($requestSchema !== null) {
            $op['requestBody'] = ['required' => true, 'content' => [
                'application/json' => ['schema' => ['$ref' => "#/components/schemas/{$requestSchema}"]]]];
        }
        if ($needsAuth) {
            $op['security'] = [['ApiKeyAuth' => []]];
        }
        return $op;
    }

    private static function successResp(): array
    {
        return ['description' => 'Success',
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessResponse']]]];
    }

    private static function errorResp(): array
    {
        return ['description' => 'Not found',
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]];
    }

    private static function schemas(): array
    {
        return [
            'Health' => ['type' => 'object', 'properties' => [
                'status' => ['type' => 'string', 'example' => 'ok'],
                'service' => ['type' => 'string'], 'version' => ['type' => 'string']]],
            'Error'  => ['type' => 'object', 'properties' => [
                'error' => ['type' => 'string'], 'id' => ['type' => 'integer']]],
            'SuccessResponse' => ['type' => 'object', 'properties' => [
                'success' => ['type' => 'boolean'], 'id' => ['type' => 'integer']]],
            'CreateResponse' => ['type' => 'object', 'properties' => [
                'id' => ['type' => 'integer'], 'slug' => ['type' => 'string', 'nullable' => true],
                'type' => ['type' => 'string'], 'href' => ['type' => 'string']]],
            'JsonLd' => ['type' => 'object', 'description' => 'JSON-LD document. See https://www.ica.org/standards/RiC/ontology.',
                'additionalProperties' => true],
            'Subgraph' => ['type' => 'object', 'properties' => [
                '@context' => ['type' => 'object'],
                '@type' => ['type' => 'string', 'example' => 'openric:Subgraph'],
                'openric:root' => ['type' => 'string'], 'openric:depth' => ['type' => 'integer'],
                'openric:nodes' => ['type' => 'array', 'items' => ['type' => 'object']],
                'openric:edges' => ['type' => 'array', 'items' => ['type' => 'object']]]],
            'UploadResponse' => ['type' => 'object', 'properties' => [
                'id' => ['type' => 'integer'], 'url' => ['type' => 'string', 'format' => 'uri'],
                'thumbnail_url' => ['type' => 'string', 'format' => 'uri', 'nullable' => true,
                    'description' => 'Non-null when the upload was an image and a default-size thumbnail was generated on write.'],
                'mime' => ['type' => 'string'], 'size' => ['type' => 'integer'],
                'filename' => ['type' => 'string'], 'path' => ['type' => 'string']]],

            'ImportResponse' => ['type' => 'object', 'properties' => [
                'type' => ['type' => 'string'],
                'dry_run' => ['type' => 'boolean'],
                'total' => ['type' => 'integer'],
                'succeeded' => ['type' => 'integer'],
                'failed' => ['type' => 'integer'],
                'duration_ms' => ['type' => 'integer'],
                'created' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'row' => ['type' => 'integer'], 'id' => ['type' => 'integer'],
                    'slug' => ['type' => 'string'], 'label' => ['type' => 'string']]]],
                'errors' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'row' => ['type' => 'integer'], 'error' => ['type' => 'string'], 'label' => ['type' => 'string']]]]]],

            'RevisionList' => ['type' => 'object', 'properties' => [
                '@type' => ['type' => 'string', 'example' => 'openric:RevisionList'],
                'entity' => ['type' => 'object', 'properties' => [
                    'type' => ['type' => 'string'], 'id' => ['type' => 'integer']]],
                'total' => ['type' => 'integer'],
                'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                    'action' => ['type' => 'string', 'enum' => ['create', 'update', 'delete']],
                    'actor' => ['type' => 'string'], 'ip' => ['type' => 'string'],
                    'payload' => ['type' => 'object', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time']]]]]],

            'AgentCreate' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                'name' => ['type' => 'string', 'description' => 'Authorised form of name'],
                'entity_type_id' => ['type' => 'integer', 'description' => 'Term id — Person / CorporateBody / Family'],
                'description_identifier' => ['type' => 'string'],
                'parent_id' => ['type' => 'integer'],
                'dates_of_existence' => ['type' => 'string'], 'history' => ['type' => 'string'],
                'places' => ['type' => 'string'], 'legal_status' => ['type' => 'string'],
                'functions' => ['type' => 'string'], 'mandates' => ['type' => 'string'],
                'general_context' => ['type' => 'string'], 'sources' => ['type' => 'string']]],
            'AgentUpdate' => ['$ref' => '#/components/schemas/AgentCreate'],

            'RecordCreate' => ['type' => 'object', 'required' => ['title'], 'properties' => [
                'title' => ['type' => 'string'], 'identifier' => ['type' => 'string'],
                'level_of_description_id' => ['type' => 'integer'], 'repository_id' => ['type' => 'integer'],
                'parent_id' => ['type' => 'integer'], 'scope_and_content' => ['type' => 'string'],
                'extent_and_medium' => ['type' => 'string'], 'archival_history' => ['type' => 'string'],
                'acquisition' => ['type' => 'string'], 'arrangement' => ['type' => 'string'],
                'access_conditions' => ['type' => 'string'], 'reproduction_conditions' => ['type' => 'string'],
                'physical_characteristics' => ['type' => 'string'], 'finding_aids' => ['type' => 'string']]],
            'RecordUpdate' => ['$ref' => '#/components/schemas/RecordCreate'],

            'PlaceCreate' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                'name' => ['type' => 'string'], 'type_id' => ['type' => 'integer'],
                'latitude' => ['type' => 'number'], 'longitude' => ['type' => 'number'],
                'authority_uri' => ['type' => 'string', 'format' => 'uri'],
                'parent_id' => ['type' => 'integer'], 'address' => ['type' => 'string'],
                'description' => ['type' => 'string']]],
            'PlaceUpdate' => ['$ref' => '#/components/schemas/PlaceCreate'],

            'RuleCreate' => ['type' => 'object', 'required' => ['title'], 'properties' => [
                'title' => ['type' => 'string'], 'type_id' => ['type' => 'integer'],
                'jurisdiction' => ['type' => 'string'], 'start_date' => ['type' => 'string', 'format' => 'date'],
                'end_date' => ['type' => 'string', 'format' => 'date'],
                'authority_uri' => ['type' => 'string', 'format' => 'uri'],
                'description' => ['type' => 'string'], 'legislation' => ['type' => 'string'],
                'sources' => ['type' => 'string']]],
            'RuleUpdate' => ['$ref' => '#/components/schemas/RuleCreate'],

            'ActivityCreate' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                'name' => ['type' => 'string'], 'type_id' => ['type' => 'integer'],
                'date_display' => ['type' => 'string'], 'start_date' => ['type' => 'string', 'format' => 'date'],
                'end_date' => ['type' => 'string', 'format' => 'date'], 'place_id' => ['type' => 'integer'],
                'description' => ['type' => 'string']]],
            'ActivityUpdate' => ['$ref' => '#/components/schemas/ActivityCreate'],

            'InstantiationCreate' => ['type' => 'object', 'required' => ['title'], 'properties' => [
                'title' => ['type' => 'string'], 'carrier_type' => ['type' => 'string'],
                'mime_type' => ['type' => 'string'], 'extent_value' => ['type' => 'number'],
                'extent_unit' => ['type' => 'string'], 'record_id' => ['type' => 'integer'],
                'content_url' => ['type' => 'string', 'format' => 'uri'],
                'description' => ['type' => 'string'],
                'technical_characteristics' => ['type' => 'string']]],
            'InstantiationUpdate' => ['$ref' => '#/components/schemas/InstantiationCreate'],

            'RepositoryCreate' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                'name' => ['type' => 'string', 'description' => 'Authorised form of name'],
                'identifier' => ['type' => 'string'], 'upload_limit' => ['type' => 'number'],
                'history' => ['type' => 'string'], 'geocultural_context' => ['type' => 'string'],
                'collecting_policies' => ['type' => 'string'], 'buildings' => ['type' => 'string'],
                'holdings' => ['type' => 'string'], 'finding_aids' => ['type' => 'string'],
                'opening_times' => ['type' => 'string'], 'access_conditions' => ['type' => 'string'],
                'disabled_access' => ['type' => 'string'], 'research_services' => ['type' => 'string'],
                'reproduction_services' => ['type' => 'string'], 'public_facilities' => ['type' => 'string']]],
            'RepositoryUpdate' => ['$ref' => '#/components/schemas/RepositoryCreate'],

            'FunctionCreate' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                'name' => ['type' => 'string', 'description' => 'Authorised form of name'],
                'type_id' => ['type' => 'integer'],
                'classification' => ['type' => 'string'], 'dates' => ['type' => 'string'],
                'description' => ['type' => 'string'], 'history' => ['type' => 'string'],
                'legislation' => ['type' => 'string'], 'institution_identifier' => ['type' => 'string'],
                'sources' => ['type' => 'string']]],
            'FunctionUpdate' => ['$ref' => '#/components/schemas/FunctionCreate'],

            'RelationCreate' => ['type' => 'object', 'required' => ['subject_id', 'object_id', 'relation_type'],
                'properties' => [
                    'subject_id' => ['type' => 'integer'], 'object_id' => ['type' => 'integer'],
                    'relation_type' => ['type' => 'string', 'example' => 'hasInstantiation'],
                    'start_date' => ['type' => 'string', 'format' => 'date'],
                    'end_date' => ['type' => 'string', 'format' => 'date'],
                    'certainty' => ['type' => 'string', 'enum' => ['certain', 'probable', 'possible', 'uncertain', 'unknown']],
                    'evidence' => ['type' => 'string']]],
            'RelationUpdate' => ['$ref' => '#/components/schemas/RelationCreate'],

            'ValidateRequest' => ['type' => 'object', 'properties' => [
                'graph' => ['type' => 'object', 'description' => 'JSON-LD graph to validate against SHACL shapes'],
                'shapes' => ['type' => 'string', 'description' => 'Optional: named shape set; defaults to openric.shacl.ttl']]],

            'KeyRequest' => ['type' => 'object', 'required' => ['email', 'intended_use'], 'properties' => [
                'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Where the issued key will be emailed.'],
                'organization' => ['type' => 'string', 'description' => 'Institution or project name (optional).'],
                'intended_use' => ['type' => 'string', 'minLength' => 20,
                    'description' => 'What will you use this key for? Read by a human admin.'],
                'scopes' => ['type' => 'string', 'example' => 'read,write,delete',
                    'description' => 'Comma-separated subset of [read, write, delete]. Defaults to "read,write".']]],
            'KeyRequestAccepted' => ['type' => 'object', 'properties' => [
                'success' => ['type' => 'boolean'],
                'request_id' => ['type' => 'integer'],
                'message' => ['type' => 'string'],
                'status_url' => ['type' => 'string', 'format' => 'uri']]],
            'KeyRequestStatus' => ['type' => 'object', 'properties' => [
                'id' => ['type' => 'integer'],
                'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'denied', 'revoked']],
                'email' => ['type' => 'string', 'description' => 'Masked — only prefix visible.'],
                'requested_scopes' => ['type' => 'string'],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                'reviewed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true]]],
        ];
    }
}
