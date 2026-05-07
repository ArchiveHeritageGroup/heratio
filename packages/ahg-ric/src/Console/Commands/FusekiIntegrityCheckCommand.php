<?php

/**
 * FusekiIntegrityCheckCommand - Heratio
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

namespace AhgRic\Console\Commands;

use AhgRic\Services\FusekiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Compares the ric_* relational tables to the GRAPH list in Fuseki and
 * reports drift. Runs on the cron schedule from fuseki_integrity_schedule.
 *
 * Reports three counts to stdout + Log::info:
 *   - matched     entity rows whose graph is present in Fuseki
 *   - missing_in_fuseki entity rows whose graph never made it to Fuseki
 *   - orphaned_in_fuseki graphs that have no corresponding entity row
 *
 * Does NOT delete anything. Operators run FusekiOrphanCleanupCommand for
 * the actual purge. Closes #77 phase 2 (fuseki_integrity_schedule).
 */
class FusekiIntegrityCheckCommand extends Command
{
    protected $signature = 'ahg:fuseki-integrity-check {--quiet-success : Suppress output when there are no discrepancies}';
    protected $description = 'Compare Fuseki graph state to ric_* relational tables; report drift.';

    private const GRAPH_PREFIX = 'urn:ahg:ric:';
    private const TYPE_TABLES = [
        'place'         => 'ric_place',
        'rule'          => 'ric_rule',
        'activity'      => 'ric_activity',
        'instantiation' => 'ric_instantiation',
        'relation'      => 'relation',
    ];

    public function handle(): int
    {
        $sync = app(FusekiSyncService::class);

        // The schedule itself is read in AhgRicServiceProvider::boot to drive
        // the cron registration. Within the command we only check the master
        // toggles - if the operator is invoking the command manually we honor
        // that even when fuseki_integrity_schedule is empty (manual override).
        $matched = 0;
        $missingInFuseki = [];
        $orphanedInFuseki = [];

        try {
            $fusekiGraphs = $this->listFusekiGraphsByPrefix(self::GRAPH_PREFIX);
        } catch (\Throwable $e) {
            $this->error('[fuseki-integrity-check] Could not query Fuseki: ' . $e->getMessage());
            return self::FAILURE;
        }

        $relationalIds = [];
        foreach (self::TYPE_TABLES as $entityType => $table) {
            $ids = DB::table($table)->pluck('id')->all();
            $relationalIds[$entityType] = array_flip(array_map('intval', $ids));
        }

        // Pass 1: every relational entity should have a Fuseki graph.
        foreach ($relationalIds as $entityType => $idSet) {
            foreach (array_keys($idSet) as $id) {
                $expected = self::GRAPH_PREFIX . $entityType . ':' . $id;
                if (in_array($expected, $fusekiGraphs, true)) {
                    $matched++;
                } else {
                    $missingInFuseki[] = $expected;
                }
            }
        }

        // Pass 2: every Fuseki graph should have a relational entity.
        foreach ($fusekiGraphs as $graphUri) {
            $parsed = $this->parseGraphUri($graphUri);
            if ($parsed === null) {
                continue; // not in our urn:ahg:ric:* scheme
            }
            [$entityType, $id] = $parsed;
            if (!isset($relationalIds[$entityType][$id])) {
                $orphanedInFuseki[] = $graphUri;
            }
        }

        $missingCount = count($missingInFuseki);
        $orphanedCount = count($orphanedInFuseki);

        // Persist last-run summary so an operator can read it from the
        // settings page or a dashboard widget without tailing logs.
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_key' => 'fuseki_integrity_last_run_at'],
            ['setting_value' => now()->toIso8601String(), 'setting_group' => 'fuseki']
        );
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_key' => 'fuseki_integrity_last_run_summary'],
            ['setting_value' => json_encode([
                'matched' => $matched,
                'missing_in_fuseki' => $missingCount,
                'orphaned_in_fuseki' => $orphanedCount,
            ]), 'setting_group' => 'fuseki']
        );

        if ($this->option('quiet-success') && $missingCount === 0 && $orphanedCount === 0) {
            return self::SUCCESS;
        }

        $this->line('[fuseki-integrity-check] matched=' . $matched
            . ' missing_in_fuseki=' . $missingCount
            . ' orphaned_in_fuseki=' . $orphanedCount);

        Log::info('[fuseki-integrity-check] result', [
            'matched' => $matched,
            'missing_in_fuseki' => $missingCount,
            'orphaned_in_fuseki' => $orphanedCount,
            'missing_examples' => array_slice($missingInFuseki, 0, 10),
            'orphan_examples' => array_slice($orphanedInFuseki, 0, 10),
        ]);

        return self::SUCCESS;
    }

    /**
     * SELECT DISTINCT ?g WHERE { GRAPH ?g {} } FILTER STRSTARTS(STR(?g), $prefix)
     * Returns the list of graph URIs in Fuseki that start with our prefix.
     */
    private function listFusekiGraphsByPrefix(string $prefix): array
    {
        $sparql = 'SELECT DISTINCT ?g WHERE { GRAPH ?g { ?s ?p ?o } FILTER(STRSTARTS(STR(?g), "' . $prefix . '")) }';
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
            if (isset($row['g']['value'])) {
                $graphs[] = (string) $row['g']['value'];
            }
        }
        return $graphs;
    }

    /**
     * Parse `urn:ahg:ric:{type}:{id}` into [type, id]; null if not our scheme.
     */
    private function parseGraphUri(string $uri): ?array
    {
        if (!str_starts_with($uri, self::GRAPH_PREFIX)) {
            return null;
        }
        $tail = substr($uri, strlen(self::GRAPH_PREFIX));
        $parts = explode(':', $tail);
        if (count($parts) !== 2) {
            return null;
        }
        if (!isset(self::TYPE_TABLES[$parts[0]]) || !ctype_digit($parts[1])) {
            return null;
        }
        return [$parts[0], (int) $parts[1]];
    }
}
