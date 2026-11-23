<?php

/**
 * RicGraphJoin - adds node titles and RiC types to explorer rows (heratio#1518).
 *
 * The explorer queries used OPTIONAL { ?x a ?type . FILTER(STRSTARTS(...)) }
 * per neighbour. They now fetch the edges first and read titles and types for
 * those nodes in one VALUES query. The rows handed back have the shape the
 * single query produced, including one row per title and type combination.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
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

namespace AhgRic\Support;

class RicGraphJoin
{
    private const RICO = 'https://www.ica.org/standards/RiC/ontology#';

    /**
     * @param  array|null  $result  SPARQL JSON result holding the edge rows
     * @param  callable(string): ?array  $run  executes a query, null on failure
     * @param  callable(array): ?array  $slot  [node var, label var, type var] for a row
     * @return array|null null when the metadata query fails, as the single query did
     */
    public static function enrich(?array $result, callable $run, callable $slot, int $limit): ?array
    {
        $rows = $result['results']['bindings'] ?? [];
        if ($rows === []) {
            return $result;
        }

        $uris = [];
        foreach ($rows as $row) {
            $vars = $slot($row);
            $uri = $vars ? ($row[$vars[0]]['value'] ?? null) : null;
            if ($uri !== null && preg_match('/^[^\x00-\x20<>"{}|\\\\^`]+$/D', $uri)) {
                $uris[$uri] = true;
            }
        }

        $index = [];
        if ($uris !== []) {
            $metadata = $run(self::metadataQuery(array_keys($uris)));
            if ($metadata === null) {
                return null;
            }
            $index = self::index($metadata['results']['bindings'] ?? []);
        }

        $result['results']['bindings'] = self::expand($rows, $index, $slot, $limit);

        return $result;
    }

    /** @param  string[]  $uris */
    public static function metadataQuery(array $uris): string
    {
        $values = '<'.implode('> <', $uris).'>';

        return <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT DISTINCT ?n ?title ?type WHERE {
  VALUES ?n { {$values} }
  { ?n rico:title ?title } UNION { ?n a ?type }
}
SPARQL;
    }

    private static function index(array $bindings): array
    {
        $index = [];
        foreach ($bindings as $row) {
            $uri = $row['n']['value'];
            if (isset($row['title'])) {
                $index[$uri]['titles'][] = $row['title'];
            }
            if (isset($row['type']) && str_starts_with($row['type']['value'], self::RICO)) {
                $index[$uri]['types'][] = $row['type'];
            }
        }

        return $index;
    }

    private static function expand(array $rows, array $index, callable $slot, int $limit): array
    {
        $out = [];
        foreach ($rows as $row) {
            $vars = $slot($row);
            $uri = $vars ? ($row[$vars[0]]['value'] ?? null) : null;
            if ($uri === null) {
                $out[] = $row;
            } else {
                [, $labelVar, $typeVar] = $vars;
                foreach ($index[$uri]['titles'] ?? [null] as $title) {
                    foreach ($index[$uri]['types'] ?? [null] as $type) {
                        $joined = $row;
                        if ($title !== null) {
                            $joined[$labelVar] = $title;
                        }
                        if ($type !== null) {
                            $joined[$typeVar] = $type;
                        }
                        $out[] = $joined;
                    }
                }
            }
            if (count($out) >= $limit) {
                return array_slice($out, 0, $limit);
            }
        }

        return $out;
    }
}
