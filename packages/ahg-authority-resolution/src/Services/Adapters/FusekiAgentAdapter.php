<?php

/**
 * FusekiAgentAdapter - Service for Heratio
 *
 * Candidate adapter for PERSON / ORG candidates sourced from the live
 * Heratio Fuseki dataset (the OpenRiC model dataset). Queries the
 * triplestore for RiC-O agents - rico:Agent / rico:Person /
 * rico:CorporateBody / rico:Family - whose name literal contains the
 * mention's entity_value.
 *
 * These candidates are Fuseki-native: they have no MySQL authority row,
 * so authority_id is null and fuseki_uri carries the subject URI. The
 * engine schema (ahg_mention_candidate.candidate_fuseki_uri) already
 * supports null authority_id + non-null fuseki_uri.
 *
 * SPARQL is run through AhgRic\Services\SparqlQueryService - the shared
 * Fuseki client - and never a bespoke HTTP client. Any failure (Fuseki
 * down, dataset empty, malformed result) is swallowed and yields an
 * empty list so candidate generation is never interrupted.
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

namespace AhgAuthorityResolution\Services\Adapters;

use AhgRic\Services\SparqlQueryService;

class FusekiAgentAdapter implements CandidateAdapterInterface
{
    public function __construct(private SparqlQueryService $sparql)
    {
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['PERSON', 'ORG'], true);
    }

    public function search(string $query, string $entityType, int $limit): array
    {
        if (!$this->supports($entityType)) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, $limit);

        try {
            $sparql = $this->buildSparql($query, $limit);
            $result = $this->sparql->executeQuery($sparql);

            // SparqlQueryService normalises to ['bindings' => [...], 'head' => [...]].
            $bindings = $result['bindings'] ?? ($result['results']['bindings'] ?? []);
            if (!is_array($bindings) || $bindings === []) {
                return [];
            }

            $seen = [];
            $out = [];
            foreach ($bindings as $row) {
                $uri = $row['s']['value'] ?? null;
                $name = $row['name']['value'] ?? null;
                if (!is_string($uri) || $uri === '') {
                    continue;
                }
                if (!is_string($name)) {
                    continue;
                }
                $name = trim($name);
                if ($name === '' || isset($seen[$uri])) {
                    continue;
                }
                $seen[$uri] = true;

                $out[] = [
                    'source' => 'fuseki_agent',
                    'authority_id' => null,
                    'fuseki_uri' => $uri,
                    'display_name' => $name,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            // Fuseki unreachable, dataset empty, or malformed response:
            // never let it bubble into candidate generation.
            return [];
        }
    }

    /**
     * Build the SPARQL SELECT for agent candidates.
     *
     * Matches any RiC-O agent class (Agent / Person / CorporateBody /
     * Family). The display name is read from whichever name predicate is
     * present - rico:name, rico:hasOrHadName -> rico:textualValue,
     * rdfs:label or skos:prefLabel - so the query works regardless of
     * which serialisation the dataset uses for instance names.
     */
    private function buildSparql(string $query, int $limit): string
    {
        $needle = $this->escapeSparqlString(mb_strtolower($query));

        return <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>

SELECT DISTINCT ?s ?name
WHERE {
    ?s a ?agentType .
    VALUES ?agentType { rico:Agent rico:Person rico:CorporateBody rico:Family }
    {
        ?s rico:name ?name .
    } UNION {
        ?s rico:hasOrHadName ?nameObj .
        ?nameObj rico:textualValue ?name .
    } UNION {
        ?s rdfs:label ?name .
    } UNION {
        ?s skos:prefLabel ?name .
    }
    FILTER(CONTAINS(LCASE(STR(?name)), "{$needle}"))
}
LIMIT {$limit}
SPARQL;
    }

    /**
     * Escape a user string for safe embedding inside a SPARQL double-quoted
     * literal. Backslashes and double quotes are escaped; control chars
     * that would terminate the literal are stripped.
     */
    private function escapeSparqlString(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\\"', $value);

        return $value;
    }
}
