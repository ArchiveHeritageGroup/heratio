<?php

/**
 * FusekiOrphanCleanupCommand - Heratio
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
use AhgRic\Services\SparqlUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purges Fuseki graphs whose entity row no longer exists in the relational
 * tables. Two-pass design lets fuseki_orphan_retention_days act as a "wait
 * this long after orphan detection before deleting" guard so a transient
 * relational delete (e.g. mid-DB-transaction) doesn't immediately drop the
 * graph:
 *
 *   Pass 1 (detection): scans Fuseki for our-prefixed graphs, marks each
 *      orphan in ahg_fuseki_orphan_log with first_seen_at = now().
 *   Pass 2 (purge): drops every logged orphan whose first_seen_at is
 *      older than fuseki_orphan_retention_days.
 *
 * When fuseki_orphan_retention_days = 0 the command is a no-op (auto-purge
 * disabled). Closes #77 phase 2 (fuseki_orphan_retention_days).
 *
 * Implementation note: the ahg_fuseki_orphan_log table is created lazily on
 * first run via Schema::hasTable() + create() so package install is unchanged.
 */
class FusekiOrphanCleanupCommand extends Command
{
    protected $signature = 'ahg:fuseki-orphan-cleanup {--dry-run : Detect orphans + log them but do not DROP}';
    protected $description = 'Detect + purge Fuseki graphs whose entity row no longer exists.';

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
        $retentionDays = $sync->orphanRetentionDays();
        if ($retentionDays === 0) {
            $this->line('[fuseki-orphan-cleanup] retention=0 - auto-purge disabled.');
            return self::SUCCESS;
        }

        $this->ensureLogTable();

        // Pass 1: detect + log new orphans
        try {
            $fusekiGraphs = $this->listFusekiGraphsByPrefix(self::GRAPH_PREFIX);
        } catch (\Throwable $e) {
            $this->error('[fuseki-orphan-cleanup] Could not query Fuseki: ' . $e->getMessage());
            return self::FAILURE;
        }

        $relationalIds = [];
        foreach (self::TYPE_TABLES as $entityType => $table) {
            $ids = DB::table($table)->pluck('id')->all();
            $relationalIds[$entityType] = array_flip(array_map('intval', $ids));
        }

        $newlyDetected = 0;
        foreach ($fusekiGraphs as $graphUri) {
            $parsed = $this->parseGraphUri($graphUri);
            if ($parsed === null) continue;
            [$entityType, $id] = $parsed;
            if (isset($relationalIds[$entityType][$id])) continue; // not orphaned

            // Already logged?
            $existing = DB::table('ahg_fuseki_orphan_log')->where('graph_uri', $graphUri)->first();
            if (!$existing) {
                DB::table('ahg_fuseki_orphan_log')->insert([
                    'graph_uri' => $graphUri,
                    'first_seen_at' => now(),
                ]);
                $newlyDetected++;
            }
        }

        // Pass 2: purge graphs whose first_seen_at is older than retention
        $cutoff = now()->subDays($retentionDays);
        $toPurge = DB::table('ahg_fuseki_orphan_log')
            ->whereNull('purged_at')
            ->where('first_seen_at', '<=', $cutoff)
            ->get();

        $purged = 0;
        $upd = app(SparqlUpdateService::class);
        foreach ($toPurge as $row) {
            // Re-verify the entity is still missing before dropping (might have
            // been re-created under the same id during the retention window).
            $parsed = $this->parseGraphUri($row->graph_uri);
            if ($parsed !== null) {
                [$entityType, $id] = $parsed;
                if (isset($relationalIds[$entityType][$id])) {
                    // No longer orphan - clear the log row so a future deletion
                    // starts the retention clock from scratch.
                    DB::table('ahg_fuseki_orphan_log')->where('graph_uri', $row->graph_uri)->delete();
                    continue;
                }
            }

            if ($this->option('dry-run')) {
                $this->line('[fuseki-orphan-cleanup] DRY-RUN would DROP ' . $row->graph_uri);
                continue;
            }

            try {
                $upd->executeUpdate('DROP SILENT GRAPH <' . $row->graph_uri . '>');
                DB::table('ahg_fuseki_orphan_log')
                    ->where('graph_uri', $row->graph_uri)
                    ->update(['purged_at' => now()]);
                $purged++;
            } catch (\Throwable $e) {
                Log::warning('[fuseki-orphan-cleanup] DROP failed for ' . $row->graph_uri . ': ' . $e->getMessage());
            }
        }

        $this->line('[fuseki-orphan-cleanup] retention_days=' . $retentionDays
            . ' newly_detected=' . $newlyDetected
            . ' purged=' . $purged);

        Log::info('[fuseki-orphan-cleanup] result', [
            'retention_days' => $retentionDays,
            'newly_detected' => $newlyDetected,
            'purged' => $purged,
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        return self::SUCCESS;
    }

    private function ensureLogTable(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('ahg_fuseki_orphan_log')) {
            return;
        }
        \Illuminate\Support\Facades\Schema::create('ahg_fuseki_orphan_log', function ($t) {
            $t->id();
            $t->string('graph_uri', 512)->unique();
            $t->timestamp('first_seen_at');
            $t->timestamp('purged_at')->nullable();
        });
    }

    /**
     * Same SELECT as FusekiIntegrityCheckCommand. Kept duplicated rather than
     * extracted into a shared helper because the two commands evolve at
     * different paces and the SPARQL string is small.
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
