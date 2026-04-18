<?php

/**
 * RelationshipService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgRic\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RelationshipService
{
    protected string $fusekiEndpoint;
    protected string $fusekiUsername;
    protected string $fusekiPassword;
    protected string $baseUri;
    protected string $instanceId;

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $config = [];
        if (Schema::hasTable('setting') && Schema::hasTable('setting_i18n')) {
            $rows = DB::table('setting')
                ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
                ->where('setting.scope', 'ric')
                ->where('setting_i18n.culture', 'en')
                ->pluck('setting_i18n.value', 'setting.name')
                ->toArray();
            $config = $rows;
        }

        $this->fusekiEndpoint = ($config['fuseki_endpoint'] ?? config('ric.fuseki.url', 'http://localhost:3030/ric')) . '/query';
        $this->fusekiUsername = $config['fuseki_username'] ?? config('ric.fuseki.user', '');
        $this->fusekiPassword = $config['fuseki_password'] ?? config('ric.fuseki.password', '');
        $this->baseUri = $config['ric_base_uri'] ?? 'https://archives.theahg.co.za/ric';
        $this->instanceId = $config['ric_instance_id'] ?? 'atom-psis';
    }

    /**
     * Get all related entities for a given entity ID.
     * Returns grouped by relationship type.
     */
    public function getRelatedEntities(int $entityId, ?string $relationType = null): array
    {
        // First try MySQL relation table
        $mysqlRelations = $this->getMysqlRelations($entityId, $relationType);

        // Then try Fuseki SPARQL
        $sparqlRelations = $this->getSparqlRelations($entityId, $relationType);

        // Merge, deduplicate by target ID
        return $this->mergeRelations($mysqlRelations, $sparqlRelations);
    }

    /**
     * Get a graph summary for an entity (nodes + edges).
     */
    public function getGraphSummary(int $entityId): array
    {
        $uri = $this->resolveEntityUri($entityId);
        if (!$uri) {
            return ['nodes' => [], 'edges' => [], 'total_nodes' => 0, 'total_edges' => 0];
        }

        $query = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?s ?p ?o ?sLabel ?oLabel ?sType ?oType WHERE {
  {
    <{$uri}> ?p ?o .
    BIND(<{$uri}> AS ?s)
    FILTER(isURI(?o) && ?p != <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>)
    OPTIONAL { ?o rico:title ?oLabel }
    OPTIONAL { ?o a ?oType . FILTER(STRSTARTS(STR(?oType), "https://www.ica.org/standards/RiC/ontology#")) }
  }
  UNION
  {
    ?s ?p <{$uri}> .
    BIND(<{$uri}> AS ?o)
    FILTER(isURI(?s) && ?p != <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>)
    OPTIONAL { ?s rico:title ?sLabel }
    OPTIONAL { ?s a ?sType . FILTER(STRSTARTS(STR(?sType), "https://www.ica.org/standards/RiC/ontology#")) }
  }
  OPTIONAL { <{$uri}> rico:title ?centerLabel }
} LIMIT 100
SPARQL;

        $result = $this->executeSparql($query);
        $nodes = [];
        $edges = [];
        $nodeIndex = [];

        if ($result && isset($result['results']['bindings'])) {
            // Add center node
            $nodeIndex[$uri] = true;
            $nodes[] = ['id' => $uri, 'label' => $this->resolveEntityName($entityId), 'type' => 'center'];

            foreach ($result['results']['bindings'] as $row) {
                $sUri = $row['s']['value'];
                $oUri = $row['o']['value'];
                $pred = $row['p']['value'];

                // Add source node
                if (!isset($nodeIndex[$sUri])) {
                    $nodeIndex[$sUri] = true;
                    $nodes[] = [
                        'id' => $sUri,
                        'label' => $row['sLabel']['value'] ?? $this->extractLabel($sUri),
                        'type' => isset($row['sType']) ? $this->extractType($row['sType']['value']) : 'Unknown',
                    ];
                }

                // Add target node
                if (!isset($nodeIndex[$oUri])) {
                    $nodeIndex[$oUri] = true;
                    $nodes[] = [
                        'id' => $oUri,
                        'label' => $row['oLabel']['value'] ?? $this->extractLabel($oUri),
                        'type' => isset($row['oType']) ? $this->extractType($row['oType']['value']) : 'Unknown',
                    ];
                }

                $edges[] = [
                    'source' => $sUri,
                    'target' => $oUri,
                    'label' => $this->extractLabel($pred),
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'total_nodes' => count($nodes),
            'total_edges' => count($edges),
        ];
    }

    /**
     * Get timeline context for an entity — dates and events.
     */
    public function getTimelineContext(int $entityId): array
    {
        $events = [];

        // Get events from AtoM event table
        $rows = DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->leftJoin('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->where(function ($q) use ($entityId) {
                $q->where('event.object_id', $entityId)
                  ->orWhere('event.actor_id', $entityId);
            })
            ->where('event_i18n.culture', 'en')
            ->where(function ($q) {
                $q->whereNull('actor_i18n.culture')
                  ->orWhere('actor_i18n.culture', 'en');
            })
            ->select(
                'event.id',
                'event.start_date',
                'event.end_date',
                'event.type_id',
                'event_i18n.date as date_display',
                'event_i18n.name as event_name',
                'event_i18n.description as event_description',
                'actor_i18n.authorized_form_of_name as actor_name'
            )
            ->orderBy('event.start_date')
            ->get();

        foreach ($rows as $row) {
            $events[] = [
                'id' => $row->id,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'date_display' => $row->date_display,
                'name' => $row->event_name,
                'description' => $row->event_description,
                'actor' => $row->actor_name,
                'type_id' => $row->type_id,
            ];
        }

        return $events;
    }

    /**
     * Explain why two entities are related.
     */
    public function explainRelationship(int $sourceId, int $targetId): array
    {
        $explanations = [];

        // Check MySQL relation table
        $relations = DB::table('relation')
            ->leftJoin('term_i18n', function ($join) {
                $join->on('relation.type_id', '=', 'term_i18n.id')
                     ->where('term_i18n.culture', '=', 'en');
            })
            ->where(function ($q) use ($sourceId, $targetId) {
                $q->where(function ($inner) use ($sourceId, $targetId) {
                    $inner->where('relation.subject_id', $sourceId)
                          ->where('relation.object_id', $targetId);
                })->orWhere(function ($inner) use ($sourceId, $targetId) {
                    $inner->where('relation.subject_id', $targetId)
                          ->where('relation.object_id', $sourceId);
                });
            })
            ->select('relation.*', 'term_i18n.name as relation_type_name')
            ->get();

        foreach ($relations as $rel) {
            $explanations[] = [
                'source' => 'relation_table',
                'type' => $rel->relation_type_name ?? 'Related',
                'direction' => $rel->subject_id == $sourceId ? 'forward' : 'reverse',
            ];
        }

        // Check event table (shared creator/subject)
        $sharedEvents = DB::table('event as e1')
            ->join('event as e2', 'e1.actor_id', '=', 'e2.actor_id')
            ->where('e1.object_id', $sourceId)
            ->where('e2.object_id', $targetId)
            ->where('e1.id', '!=', DB::raw('e2.id'))
            ->count();

        if ($sharedEvents > 0) {
            $explanations[] = [
                'source' => 'shared_creator',
                'type' => 'Shared creator/agent',
                'count' => $sharedEvents,
            ];
        }

        return $explanations;
    }

    // ── Internal helpers ──

    protected function getMysqlRelations(int $entityId, ?string $relationType = null): array
    {
        $query = DB::table('relation')
            ->leftJoin('term_i18n', function ($join) {
                $join->on('relation.type_id', '=', 'term_i18n.id')
                     ->where('term_i18n.culture', '=', 'en');
            })
            ->where(function ($q) use ($entityId) {
                $q->where('relation.subject_id', $entityId)
                  ->orWhere('relation.object_id', $entityId);
            })
            ->select('relation.*', 'term_i18n.name as relation_type_name');

        if ($relationType) {
            $query->where('term_i18n.name', $relationType);
        }

        $results = [];
        foreach ($query->limit(50)->get() as $row) {
            $targetId = $row->subject_id == $entityId ? $row->object_id : $row->subject_id;
            $results[] = [
                'source' => 'mysql',
                'target_id' => $targetId,
                'type' => $row->relation_type_name ?? 'Related',
                'direction' => $row->subject_id == $entityId ? 'outgoing' : 'incoming',
            ];
        }

        return $results;
    }

    protected function getSparqlRelations(int $entityId, ?string $relationType = null): array
    {
        $uri = $this->resolveEntityUri($entityId);
        if (!$uri) {
            return [];
        }

        $typeFilter = '';
        if ($relationType) {
            $typeFilter = "FILTER(CONTAINS(STR(?p), \"{$relationType}\"))";
        }

        $query = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?target ?label ?type ?predicate ?direction WHERE {
  {
    <{$uri}> ?predicate ?target .
    BIND("outgoing" AS ?direction)
    FILTER(isURI(?target) && ?predicate != <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>)
    {$typeFilter}
    OPTIONAL { ?target rico:title ?label }
    OPTIONAL { ?target a ?type . FILTER(STRSTARTS(STR(?type), "https://www.ica.org/standards/RiC/ontology#")) }
  }
  UNION
  {
    ?target ?predicate <{$uri}> .
    BIND("incoming" AS ?direction)
    FILTER(isURI(?target) && ?predicate != <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>)
    {$typeFilter}
    OPTIONAL { ?target rico:title ?label }
    OPTIONAL { ?target a ?type . FILTER(STRSTARTS(STR(?type), "https://www.ica.org/standards/RiC/ontology#")) }
  }
} LIMIT 50
SPARQL;

        $result = $this->executeSparql($query);
        $relations = [];

        if ($result && isset($result['results']['bindings'])) {
            foreach ($result['results']['bindings'] as $row) {
                $relations[] = [
                    'source' => 'sparql',
                    'target_uri' => $row['target']['value'],
                    'label' => $row['label']['value'] ?? $this->extractLabel($row['target']['value']),
                    'type' => isset($row['type']) ? $this->extractType($row['type']['value']) : 'Unknown',
                    'predicate' => $this->extractLabel($row['predicate']['value']),
                    'direction' => $row['direction']['value'],
                ];
            }
        }

        return $relations;
    }

    protected function mergeRelations(array $mysql, array $sparql): array
    {
        $grouped = [];

        foreach ($mysql as $rel) {
            $key = $rel['type'] ?? 'Related';
            $grouped[$key][] = $rel;
        }

        foreach ($sparql as $rel) {
            $key = $rel['predicate'] ?? $rel['type'] ?? 'Related';
            $grouped[$key][] = $rel;
        }

        return $grouped;
    }

    protected function resolveEntityUri(int $entityId): ?string
    {
        // Check ric_sync_status for the Fuseki URI
        if (Schema::hasTable('ric_sync_status')) {
            $row = DB::table('ric_sync_status')
                ->where('entity_id', $entityId)
                ->first();
            if ($row && !empty($row->fuseki_uri)) {
                return $row->fuseki_uri;
            }
        }

        // Build URI from convention
        return $this->baseUri . '/' . $this->instanceId . '/entity/' . $entityId;
    }

    protected function resolveEntityName(int $entityId): string
    {
        // Try IO title
        $title = DB::table('information_object_i18n')
            ->where('id', $entityId)
            ->where('culture', 'en')
            ->value('title');
        if ($title) return $title;

        // Try actor name
        $name = DB::table('actor_i18n')
            ->where('id', $entityId)
            ->where('culture', 'en')
            ->value('authorized_form_of_name');
        if ($name) return $name;

        // Try term name
        $name = DB::table('term_i18n')
            ->where('id', $entityId)
            ->where('culture', 'en')
            ->value('name');
        if ($name) return $name;

        return "Entity #{$entityId}";
    }

    protected function executeSparql(string $query): ?array
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $this->fusekiEndpoint,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $query,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/sparql-query',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 3,
        ];
        if ($this->fusekiPassword) {
            $opts[CURLOPT_USERPWD] = "{$this->fusekiUsername}:{$this->fusekiPassword}";
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }

    protected function extractLabel(string $uri): string
    {
        $parts = explode('/', $uri);
        $last = end($parts);
        if (str_contains($last, '#')) {
            $last = substr($last, strrpos($last, '#') + 1);
        }
        // camelCase to words
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $last);
    }

    protected function extractType(string $uri): string
    {
        $label = $this->extractLabel($uri);
        return str_replace('https://www.ica.org/standards/RiC/ontology#', '', $label);
    }
}
