<?php

/**
 * ListsFusekiGraphs - shared Fuseki graph-listing for RiC maintenance commands.
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

namespace AhgRic\Console\Concerns;

/**
 * SPARQL a Fuseki endpoint for distinct subjects whose IRI starts with a
 * prefix. Was byte-identical in FusekiIntegrityCheckCommand and
 * FusekiOrphanCleanupCommand.
 */
trait ListsFusekiGraphs
{
    private function listFusekiGraphsByPrefix(string $prefix): array
    {
        $sparql = 'SELECT DISTINCT ?s WHERE { { ?s ?p ?o } UNION { GRAPH ?g { ?s ?p ?o } } FILTER(STRSTARTS(STR(?s), "' . $prefix . '")) }';
        $endpoint = config('heratio.fuseki_endpoint', 'http://localhost:3030/heratio') . '/sparql';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['query' => $sparql, 'format' => 'json']),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new \RuntimeException("Fuseki SELECT failed: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        $graphs = [];
        foreach (($data['results']['bindings'] ?? []) as $row) {
            if (isset($row['s']['value'])) {
                $graphs[] = (string) $row['s']['value'];
            }
        }
        return $graphs;
    }
}
