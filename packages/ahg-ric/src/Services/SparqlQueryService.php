<?php

/**
 * SparqlQueryService - SPARQL query builder for RIC-O triplestore
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgRic\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for building and executing SPARQL queries against Fuseki triplestore.
 * 
 * Wraps the Python ric_semantic_search.py tool and provides Laravel-native methods.
 */
class SparqlQueryService
{
    private string $fusekiEndpoint;
    private string $pythonScript;
    private int $cacheMinutes = 15;

    public function __construct()
    {
        $this->fusekiEndpoint = config('heratio.fuseki_endpoint', 'http://localhost:3030/heratio');
        $this->pythonScript = __DIR__ . '/../../tools/ric_semantic_search.py';
    }

    /**
     * Search for entities using SPARQL
     */
    public function search(string $query, array $options = []): array
    {
        $type = $options['type'] ?? null;
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;

        // Build SPARQL query
        $sparql = $this->buildSearchQuery($query, $type, $limit, $offset);

        // Execute query
        return $this->executeQuery($sparql);
    }

    /**
     * Build SPARQL search query
     */
    private function buildSearchQuery(string $term, ?string $type, int $limit, int $offset): string
    {
        $typeFilter = '';
        if ($type) {
            $typeUri = $this->getTypeUri($type);
            $typeFilter = "FILTER(?type = <{$typeUri}>)";
        }

        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>

SELECT DISTINCT ?entity ?type ?label ?description
WHERE {
    ?entity a ?type .
    OPTIONAL { ?entity rico:name ?label }
    OPTIONAL { ?entity rico:description ?description }
    OPTIONAL { ?entity skos:prefLabel ?label }
    
    FILTER(
        CONTAINS(LCASE(COALESCE(?label, "")), LCASE("{$term}")) ||
        CONTAINS(LCASE(COALESCE(?description, "")), LCASE("{$term}"))
    )
    
    {$typeFilter}
}
LIMIT {$limit}
OFFSET {$offset}
SPARQL;

        return $sparql;
    }

    /**
     * Get entity by URI
     */
    public function getEntity(string $uri): ?array
    {
        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

SELECT ?property ?value ?valueType
WHERE {
    <{$uri}> ?property ?value .
    BIND(DATATYPE(?value) AS ?valueType)
}
SPARQL;

        $result = $this->executeQuery($sparql);

        if (empty($result['bindings'])) {
            return null;
        }

        return [
            'uri' => $uri,
            'properties' => $result['bindings'],
        ];
    }

    /**
     * Get relationships for an entity
     */
    public function getRelationships(string $uri, int $depth = 1): array
    {
        $queries = [];

        // Outgoing relationships
        $outgoingSparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?property ?target ?targetType
WHERE {
    <{$uri}> ?property ?target .
    BIND(?target AS ?targetResource)
    OPTIONAL { ?target a ?targetType }
}
SPARQL;
        $queries['outgoing'] = $outgoingSparql;

        // Incoming relationships
        $incomingSparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?property ?source ?sourceType
WHERE {
    ?source ?property <{$uri}> .
    BIND(?source AS ?sourceResource)
    OPTIONAL { ?source a ?sourceType }
}
SPARQL;
        $queries['incoming'] = $incomingSparql;

        $results = [];
        foreach ($queries as $direction => $sparql) {
            $result = $this->executeQuery($sparql);
            $results[$direction] = $result['bindings'] ?? [];
        }

        return $results;
    }

    /**
     * Find related entities (same context)
     */
    public function findRelated(string $uri): array
    {
        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT DISTINCT ?related ?relationship ?relatedType
WHERE {
    {
        <{$uri}> ?prop1 ?intermediate .
        ?intermediate ?prop2 ?related .
        BIND(?prop2 AS ?relationship)
    }
    UNION
    {
        ?related ?prop1 <{$uri}> .
        ?related ?prop2 ?intermediate .
        BIND(?prop1 AS ?relationship)
    }
    FILTER(?related != <{$uri}>)
    OPTIONAL { ?related a ?relatedType }
}
LIMIT 50
SPARQL;

        return $this->executeQuery($sparql);
    }

    /**
     * Get entities by type
     */
    public function getByType(string $type, int $limit = 100, int $offset = 0): array
    {
        $typeUri = $this->getTypeUri($type);

        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?entity ?name ?description
WHERE {
    ?entity a <{$typeUri}> .
    OPTIONAL { ?entity rico:name ?name }
    OPTIONAL { ?entity rico:description ?description }
}
LIMIT {$limit}
OFFSET {$offset}
SPARQL;

        return $this->executeQuery($sparql);
    }

    /**
     * Get temporal data (dates)
     */
    public function getTemporalData(string $uri): array
    {
        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

SELECT ?dateRange ?startDate ?endDate ?dateType
WHERE {
    <{$uri}> rico:hasDateRangeSet ?dateRange .
    OPTIONAL { ?dateRange rico:startDate ?startDate }
    OPTIONAL { ?dateRange rico:endDate ?endDate }
    OPTIONAL { ?dateRange rico:dateType ?dateType }
}
SPARQL;

        return $this->executeQuery($sparql);
    }

    /**
     * Get hierarchical relationships
     */
    public function getHierarchy(string $uri): array
    {
        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?parent ?child ?hierarchyType
WHERE {
    {
        <{$uri}> rico:isPartOf ?parent .
        BIND(rico:isPartOf AS ?hierarchyType)
    }
    UNION
    {
        ?child rico:isPartOf <{$uri}> .
        BIND(?child AS ?child)
        BIND(rico:hasRecordPart AS ?hierarchyType)
    }
}
SPARQL;

        return $this->executeQuery($sparql);
    }

    /**
     * Execute SPARQL query with caching
     */
    public function executeQuery(string $sparql, bool $useCache = true): array
    {
        // Generate cache key
        $cacheKey = 'sparql_' . md5($sparql);

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Check if Python script exists
        if (file_exists($this->pythonScript)) {
            $result = $this->executeViaPython($sparql);
        } else {
            $result = $this->executeViaHttp($sparql);
        }

        // Cache result
        if ($useCache && !empty($result)) {
            Cache::put($cacheKey, $result, now()->addMinutes($this->cacheMinutes));
        }

        return $result;
    }

    /**
     * Execute query via Python script
     */
    private function executeViaPython(string $sparql): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sparql_');
        file_put_contents($tempFile, $sparql);

        $command = sprintf(
            'python3 %s --query-file %s 2>&1',
            escapeshellcmd($this->pythonScript),
            escapeshellarg($tempFile)
        );

        $output = shell_exec($command);
        unlink($tempFile);

        if (!$output) {
            return ['error' => 'Query execution failed'];
        }

        $data = json_decode($output, true);

        return [
            'bindings' => $data['results']['bindings'] ?? [],
            'head' => $data['head']['vars'] ?? [],
        ];
    }

    /**
     * Execute query via HTTP to Fuseki
     */
    private function executeViaHttp(string $sparql): array
    {
        $url = $this->fusekiEndpoint . '/sparql';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'query' => $sparql,
                'format' => 'json',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            Log::error("SPARQL query failed: HTTP {$httpCode}");
            return ['error' => "Query failed with HTTP {$httpCode}"];
        }

        $data = json_decode($response, true);

        return [
            'bindings' => $data['results']['bindings'] ?? [],
            'head' => $data['head']['vars'] ?? [],
        ];
    }

    /**
     * Get RIC-O type URI from type name
     */
    private function getTypeUri(string $type): string
    {
        $types = [
            'agent' => 'https://www.ica.org/standards/RiC/ontology#Agent',
            'person' => 'https://www.ica.org/standards/RiC/ontology#Person',
            'corporatebody' => 'https://www.ica.org/standards/RiC/ontology#CorporateBody',
            'family' => 'https://www.ica.org/standards/RiC/ontology#Family',
            'function' => 'https://www.ica.org/standards/RiC/ontology#Function',
            'record' => 'https://www.ica.org/standards/RiC/ontology#Record',
            'recordset' => 'https://www.ica.org/standards/RiC/ontology#RecordSet',
            'repository' => 'https://www.ica.org/standards/RiC/ontology#CorporateBody',
            'place' => 'https://www.ica.org/standards/RiC/ontology#Place',
            'activity' => 'https://www.ica.org/standards/RiC/ontology#Activity',
        ];

        return $types[strtolower($type)] ?? 'https://www.ica.org/standards/RiC/ontology#Thing';
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        Cache::flush();
    }

    /**
     * Get statistics from triplestore
     */
    public function getStatistics(): array
    {
        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?type (COUNT(DISTINCT ?entity) AS ?count)
WHERE {
    ?entity a ?type .
    FILTER(STRSTARTS(STR(?type), "https://www.ica.org/standards/RiC/"))
}
GROUP BY ?type
ORDER BY DESC(?count)
SPARQL;

        return $this->executeQuery($sparql);
    }
}
