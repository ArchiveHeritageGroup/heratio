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
use AhgRic\Support\RicGraphManifest;
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

    // Entity -> source-table -> IRI mapping comes from the shared
    // RicGraphManifest (ADR-0003) so this command, the integrity check, and the
    // loader stay in lockstep.

    public function handle(): int
    {
        $sync = app(FusekiSyncService::class);
        $retentionDays = $sync->orphanRetentionDays();

        $this->ensureLogTable();

        // Pass 1: detect + log new orphans. Detection runs REGARDLESS of the
        // retention setting - retention only gates the PURGE (pass 2) below.
        // Previously a retention of 0 returned early, so with auto-purge off the
        // orphan detector never even looked, and the dashboard - which reads
        // ric_orphan_tracking - had nothing to show (#1421 bug 2a).
        try {
            $fusekiGraphs = $this->listFusekiGraphsByPrefix(RicGraphManifest::URN_PREFIX);
        } catch (\Throwable $e) {
            $this->error('[fuseki-orphan-cleanup] Could not query Fuseki: ' . $e->getMessage());
            return self::FAILURE;
        }

        $relationalIds = [];
        foreach (RicGraphManifest::TYPES as $entityType => $cfg) {
            $ids = DB::table($cfg['table'])->pluck($cfg['id'])->all();
            $relationalIds[$entityType] = array_flip(array_map('intval', $ids));
        }
        // Flat union of every relational id, for HTTP-scheme resolution below.
        $allRelationalIds = [];
        foreach ($relationalIds as $set) {
            $allRelationalIds += $set;
        }

        $newlyDetected = 0;

        // Record one orphan graph URI (idempotent) into BOTH the retention log
        // (ahg_fuseki_orphan_log, the pass-2 purge clock) AND ric_orphan_tracking
        // (the table the /admin/ric dashboard actually reads - nothing populated
        // it before, hence the perpetual "Orphaned Triples: 0").
        $record = function (string $graphUri, ?string $ricType) use (&$newlyDetected) {
            $existing = DB::table('ahg_fuseki_orphan_log')->where('graph_uri', $graphUri)->first();
            if (! $existing) {
                DB::table('ahg_fuseki_orphan_log')->insert([
                    'graph_uri' => $graphUri,
                    'first_seen_at' => now(),
                ]);
                $newlyDetected++;
            }
            $this->trackOrphan($graphUri, $ricType);
        };

        // Pass 1a: URN-scheme entities (urn:ahg:ric:<type>:<id>).
        foreach ($fusekiGraphs as $graphUri) {
            $parsed = $this->parseGraphUri($graphUri);
            if ($parsed === null) {
                continue;
            }
            [$entityType, $id] = $parsed;
            if (isset($relationalIds[$entityType][$id])) {
                continue; // resolves to a relational entity - not orphaned
            }
            $record($graphUri, $entityType);
        }

        // Pass 1b (#1421 bug 2a): HTTP base-URI-scheme entities
        // (<base>/<instance>/<type>/<localid>) written by the shell/PSIS sync.
        // The URN sweep above never saw these, so context entities (Rule,
        // Mandate, Mechanism) written under the HTTP scheme went uncounted - the
        // exact case reported (…/rule/text_900328). A subject is an orphan when
        // its local id does not resolve to any relational entity (a non-numeric
        // id like "text_900328" never matches a numeric relational id).
        $httpPrefix = $this->httpSchemePrefix();
        if ($httpPrefix !== null) {
            try {
                foreach ($this->listFusekiGraphsByPrefix($httpPrefix) as $subject) {
                    $path = trim((string) parse_url($subject, PHP_URL_PATH), '/');
                    if ($path === '') {
                        continue;
                    }
                    $slash = strrpos($path, '/');
                    $localId = $slash !== false ? substr($path, $slash + 1) : $path;
                    $isKnown = ctype_digit($localId) && isset($allRelationalIds[(int) $localId]);
                    if (! $isKnown) {
                        $record($subject, $this->ricTypeFromHttp($subject));
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('[fuseki-orphan-cleanup] HTTP-scheme sweep skipped: ' . $e->getMessage());
            }
        }

        // Pass 2: purge graphs whose first_seen_at is older than retention.
        // Skipped entirely when retention=0 (detection-only mode).
        if ($retentionDays === 0) {
            $this->line('[fuseki-orphan-cleanup] retention=0 - detection only (auto-purge disabled); newly_detected=' . $newlyDetected);
            Log::info('[fuseki-orphan-cleanup] detection-only', ['newly_detected' => $newlyDetected]);
            return self::SUCCESS;
        }

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
                    $this->resolveOrphanTracking($row->graph_uri, 'resolved');
                    continue;
                }
            }

            if ($this->option('dry-run')) {
                $this->line('[fuseki-orphan-cleanup] DRY-RUN would DROP ' . $row->graph_uri);
                continue;
            }

            try {
                // Entities live as SUBJECTS in the default graph (fuseki-load),
                // so delete the subject's triples; also DROP any legacy
                // same-named named graph for backward compatibility.
                $upd->executeUpdate(
                    'DELETE WHERE { <' . $row->graph_uri . '> ?p ?o } ; '
                    . 'DROP SILENT GRAPH <' . $row->graph_uri . '>'
                );
                DB::table('ahg_fuseki_orphan_log')
                    ->where('graph_uri', $row->graph_uri)
                    ->update(['purged_at' => now()]);
                $this->resolveOrphanTracking($row->graph_uri, 'cleaned');
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

    /**
     * The HTTP base-URI prefix this instance's RiC entities are published under
     * (<base_uri>/<instance>/), or null if no base URI is configured. Used to
     * sweep the alternate URI scheme the shell/PSIS sync writes (#1421 bug 2a).
     */
    private function httpSchemePrefix(): ?string
    {
        $base = rtrim((string) config('ric.base_uri', ''), '/');
        if ($base === '') {
            return null;
        }
        $instance = trim((string) config('ahg-ric.instance_id', ''), '/');

        return $instance !== '' ? $base . '/' . $instance . '/' : $base . '/';
    }

    /**
     * The type word from an HTTP-scheme RiC URI (<base>/<instance>/<type>/<id>).
     */
    private function ricTypeFromHttp(string $uri): ?string
    {
        $parts = explode('/', trim((string) parse_url($uri, PHP_URL_PATH), '/'));

        return count($parts) >= 2 ? $parts[count($parts) - 2] : null;
    }

    /**
     * Record a detected orphan into ric_orphan_tracking - the table the
     * /admin/ric dashboard counts and lists. Idempotent per URI while status is
     * 'detected'. Nothing populated this table before #1421, which is why the
     * dashboard's orphan count was structurally always 0.
     */
    private function trackOrphan(string $uri, ?string $ricType): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ric_orphan_tracking')) {
            return;
        }
        $exists = DB::table('ric_orphan_tracking')
            ->where('ric_uri', $uri)
            ->where('status', 'detected')
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('ric_orphan_tracking')->insert([
            'ric_uri' => mb_substr($uri, 0, 500),
            'ric_type' => $ricType ? mb_substr($ricType, 0, 100) : null,
            'detected_at' => now(),
            'detection_method' => 'fuseki-sweep',
            'status' => 'detected',
        ]);
    }

    /**
     * Mark a tracked orphan resolved ('cleaned' after a purge, or 'resolved'
     * when its relational entity reappeared).
     */
    private function resolveOrphanTracking(string $uri, string $status): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ric_orphan_tracking')) {
            return;
        }
        DB::table('ric_orphan_tracking')
            ->where('ric_uri', $uri)
            ->where('status', 'detected')
            ->update([
                'status' => $status,
                'resolved_at' => now(),
                'cleaned_at' => $status === 'cleaned' ? now() : null,
            ]);
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
        // Match entity SUBJECTS in the default graph or any named graph (entities
        // are written to the default graph as subjects, not per-entity named
        // graphs) - mirrors the fixed FusekiIntegrityCheckCommand.
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

    private function parseGraphUri(string $uri): ?array
    {
        return RicGraphManifest::parse($uri);
    }
}
