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

    public function __construct(?string $endpoint = null)
    {
        // Default /openric-model on purpose: its callers - the authority-
        // resolution candidate adapters and KM grounding (#1320) - work on
        // Heratio's own agents and places loaded there as urn:ahg:ric:* by
        // ahg:ric:fuseki-load (#139). The archival /ric store has its own
        // client (RelationshipService, RicController). Pass $endpoint to
        // query another dataset (heratio#1516).
        $this->fusekiEndpoint = rtrim($endpoint ?? config('heratio.fuseki_endpoint', 'http://localhost:3030/openric-model'), '/');
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

        // #1516 - two steps: find the page of entities, then label only that
        // page. Labelling inside the match query joins every name node of
        // every hit before grouping, and agents carry thousands of them.
        $result = $this->executeQuery($this->buildSearchQuery($query, $type, $limit, $offset));

        return $this->withLabels($result, 'label', 'description');
    }

    /**
     * Build SPARQL search query
     *
     * #1516 - matches the name shapes both writers produce: rico:name (the
     * #139 instance load), rico:title for records, rico:textualValue on the
     * name node for agents, places and terms (rico:hasAgentName / hasOrHadName
     * / hasPlaceName, the RiC extractor), and rdfs:label from the live sync.
     *
     * The default CONTAINS path suits the small /openric-model dataset. On the
     * archival /ric store (17.9M triples) it scans every name and takes
     * minutes; there set RIC_TEXT_INDEX=true to use its jena-text Lucene
     * index (#18), which answers in well under a second.
     */
    private function buildSearchQuery(string $term, ?string $type, int $limit, int $offset): string
    {
        $typeFilter = '';
        if ($type) {
            $typeUri = $this->getTypeUri($type);
            $typeFilter = "FILTER(?t = <{$typeUri}>)";
        }

        if (config('heratio.ric_text_index', false)) {
            $lucene = $this->escapeSparqlLiteral($this->luceneQuery($term)); // #1394 - prevent SPARQL injection
            // The index returns name nodes and entities alike; cap it well
            // above the page so dedup to owning entities still fills it.
            $hitCap = max(100, ($limit + $offset) * 10);
            $match = <<<SPARQL
    (?hit ?score) text:query ("{$lucene}" {$hitCap}) .
    OPTIONAL { ?owner rico:hasAgentName|rico:hasOrHadName|rico:hasPlaceName ?hit }
    BIND(COALESCE(?owner, ?hit) AS ?entity)
    FILTER(isIRI(?entity))
SPARQL;
            $order = 'ORDER BY DESC(MAX(?score))';
        } else {
            $needle = $this->escapeSparqlLiteral(mb_strtolower($term)); // #1394 - prevent SPARQL injection
            $match = <<<SPARQL
    { ?entity rico:name|rico:title|rdfs:label ?text }
    UNION
    { ?entity rico:hasAgentName|rico:hasOrHadName|rico:hasPlaceName ?n . ?n rico:textualValue ?text }
    FILTER(isIRI(?entity) && CONTAINS(LCASE(STR(?text)), "{$needle}"))
SPARQL;
            $order = '';
        }

        return <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX text: <http://jena.apache.org/text#>

SELECT ?entity (SAMPLE(?t) AS ?type)
WHERE {
{$match}
    ?entity a ?t .
    {$typeFilter}
}
GROUP BY ?entity
{$order}
LIMIT {$limit}
OFFSET {$offset}
SPARQL;
    }

    /**
     * #1516 - add a display label and a description to each ?entity row,
     * read from the predicates either writer uses (rico:name or title, then
     * the name node's textualValue, then rdfs:label). One query for the page.
     */
    private function withLabels(array $result, string $labelVar, string $descriptionVar): array
    {
        $uris = [];
        foreach ($result['bindings'] ?? [] as $row) {
            if (($row['entity']['type'] ?? '') === 'uri') {
                $uris[] = '<' . $this->escapeSparqlIri($row['entity']['value']) . '>';
            }
        }
        if ($uris === []) {
            return $result;
        }

        $values = implode(' ', array_unique($uris));
        $labels = $this->executeQuery(<<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

SELECT ?entity (SAMPLE(?title) AS ?t) (SAMPLE(?name) AS ?n) (SAMPLE(?rdfsLabel) AS ?r) (SAMPLE(?desc) AS ?d)
WHERE {
    VALUES ?entity { {$values} }
    OPTIONAL { ?entity rico:name|rico:title ?title }
    OPTIONAL { ?entity (rico:hasAgentName|rico:hasOrHadName|rico:hasPlaceName)/rico:textualValue ?name }
    OPTIONAL { ?entity rdfs:label ?rdfsLabel }
    OPTIONAL { ?entity rico:scopeAndContent|rico:descriptiveNote|rico:history ?desc }
}
GROUP BY ?entity
SPARQL);

        $byUri = [];
        foreach ($labels['bindings'] ?? [] as $row) {
            $byUri[$row['entity']['value']] = [
                $labelVar => $row['t'] ?? $row['n'] ?? $row['r'] ?? null,
                $descriptionVar => $row['d'] ?? null,
            ];
        }
        foreach ($result['bindings'] as $i => $row) {
            foreach ($byUri[$row['entity']['value'] ?? ''] ?? [] as $var => $binding) {
                if ($binding !== null) {
                    $result['bindings'][$i][$var] = $binding;
                }
            }
        }
        $result['head'] = array_values(array_unique(array_merge($result['head'] ?? [], [$labelVar, $descriptionVar])));

        return $result;
    }

    /**
     * #1516 - turn free text into a Lucene query: every word required,
     * Lucene operators escaped so user input cannot change the query shape.
     */
    private function luceneQuery(string $term): string
    {
        // Lowercased so a bare AND / OR / NOT is a word, not an operator.
        $words = preg_split('/\s+/u', mb_strtolower(trim($term)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_map(
            fn ($w) => preg_replace('#([+\-!(){}\[\]^"~*?:\\\\/&|])#', '\\\\$1', $w),
            $words
        );

        return implode(' AND ', $words);
    }

    /**
     * Get entity by URI
     */
    public function getEntity(string $uri): ?array
    {
        $uri = $this->escapeSparqlIri($uri); // #1394 - prevent SPARQL/IRI injection
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
        $uri = $this->escapeSparqlIri($uri); // #1394 - prevent SPARQL/IRI injection
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
        $uri = $this->escapeSparqlIri($uri); // #1394 - prevent SPARQL/IRI injection
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

        // #1516 - page of entities first, then name and description from the
        // predicates the data carries (see withLabels).
        $sparql = <<<SPARQL
SELECT ?entity
WHERE {
    ?entity a <{$typeUri}> .
}
LIMIT {$limit}
OFFSET {$offset}
SPARQL;

        return $this->withLabels($this->executeQuery($sparql), 'name', 'description');
    }

    /**
     * Get temporal data (dates)
     */
    public function getTemporalData(string $uri): array
    {
        $uri = $this->escapeSparqlIri($uri); // #1394 - prevent SPARQL/IRI injection
        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

SELECT ?dateRange ?startDate ?endDate ?expressedDate ?dateType
WHERE {
    # #1516 - dates hang off rico:isOrWasAssociatedWithDate, on the entity
    # itself or on the Production activity that resulted in it.
    { <{$uri}> rico:isOrWasAssociatedWithDate ?dateRange }
    UNION
    { ?activity rico:resultsOrResultedIn <{$uri}> ; rico:isOrWasAssociatedWithDate ?dateRange }
    OPTIONAL { ?dateRange rico:beginningDate ?startDate }
    OPTIONAL { ?dateRange rico:endDate ?endDate }
    OPTIONAL { ?dateRange rico:expressedDate ?expressedDate }
    OPTIONAL { ?dateRange a ?dateType }
}
SPARQL;

        return $this->executeQuery($sparql);
    }

    /**
     * Get hierarchical relationships
     */
    public function getHierarchy(string $uri): array
    {
        $uri = $this->escapeSparqlIri($uri); // #1394 - prevent SPARQL/IRI injection
        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?parent ?child ?hierarchyType
WHERE {
    # #1516 - the extractor writes the hierarchy one way only: parent
    # rico:includes child. The inverse is not in the data.
    {
        ?parent rico:includes <{$uri}> .
        BIND(rico:includes AS ?hierarchyType)
    }
    UNION
    {
        <{$uri}> rico:includes ?child .
        BIND(rico:includes AS ?hierarchyType)
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

        // Direct HTTP to the Fuseki SPARQL endpoint is the canonical,
        // dependency-free path and the default. ric_semantic_search.py is a
        // Flask HTTP server, not a CLI query tool, so the Python branch is
        // opt-in only (config heratio.ric_sparql_via_python). See heratio#138.
        if (config('heratio.ric_sparql_via_python', false) && file_exists($this->pythonScript)) {
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
    /**
     * #1394 - escape a value for safe inclusion in a SPARQL string literal
     * ("..."): backslash, double-quote and the control chars that would close
     * the literal or inject a newline. Mirrors FusekiSyncService's escaping.
     */
    private function escapeSparqlLiteral(string $s): string
    {
        $s = str_replace(['\\', '"'], ['\\\\', '\\"'], $s);

        return strtr($s, ["\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
    }

    /**
     * #1394 - sanitise a value used inside a SPARQL IRI ref (<...>). IRI refs
     * may not contain < > " { } | ^ ` \\, spaces or control chars; strip them so
     * a caller cannot break out of the angle brackets to inject triple patterns
     * or a SERVICE clause (SSRF).
     */
    private function escapeSparqlIri(string $uri): string
    {
        return preg_replace('/[\x00-\x20<>"{}|\^`\\\\]/', '', $uri);
    }

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
