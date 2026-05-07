<?php

namespace AhgRic\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Dispatch;

/**
 * FusekiSyncService
 *
 * Central coordinator for deciding whether to write to Fuseki now,
 * enqueue a job, or skip writes based on settings.
 *
 * Closes #77 phase 2 (5 keys): fuseki_sync_on_save, fuseki_sync_on_delete,
 * fuseki_cascade_delete, fuseki_integrity_schedule, fuseki_orphan_retention_days.
 */
class FusekiSyncService
{
    private SparqlUpdateService $upd;

    public function __construct()
    {
        $this->upd = app(SparqlUpdateService::class);
    }

    private function setting(string $key, $default = null)
    {
        try {
            $v = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            return ($v !== null && $v !== '') ? $v : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function enabled(): bool
    {
        // default: preserve existing behavior (attempt writes)
        $v = $this->setting('fuseki_sync_enabled', '1');
        return (bool) intval($v);
    }

    private function queueEnabled(): bool
    {
        $v = $this->setting('fuseki_queue_enabled', '0');
        return (bool) intval($v);
    }

    private function batchSize(): int
    {
        $v = $this->setting('fuseki_batch_size', '100');
        return max(1, (int) $v);
    }

    // ── #77 phase 2 ────────────────────────────────────────────────────

    public function syncOnSaveEnabled(): bool
    {
        return (bool) intval($this->setting('fuseki_sync_on_save', '1'));
    }

    public function syncOnDeleteEnabled(): bool
    {
        return (bool) intval($this->setting('fuseki_sync_on_delete', '1'));
    }

    public function cascadeDeleteEnabled(): bool
    {
        return (bool) intval($this->setting('fuseki_cascade_delete', '0'));
    }

    /**
     * Cron expression for the periodic integrity check. Empty string disables
     * the schedule entirely. Default '0 4 * * *' = 04:00 daily.
     */
    public function integritySchedule(): string
    {
        return (string) $this->setting('fuseki_integrity_schedule', '0 4 * * *');
    }

    /**
     * Days to keep orphan triples (entity row deleted, graph still in Fuseki)
     * before purging. 0 = never auto-purge.
     */
    public function orphanRetentionDays(): int
    {
        return max(0, (int) $this->setting('fuseki_orphan_retention_days', '30'));
    }

    /**
     * Insert RDF-Star into Fuseki, honoring settings (enable/queue).
     */
    public function insertRdfStar(string $graphUri, string $turtleBody): array
    {
        if (!$this->enabled()) {
            Log::info('[ahg-ric] fuseki write skipped: fuseki_sync_enabled=0');
            return ['ok' => false, 'status' => 0, 'error' => 'fuseki_sync_disabled'];
        }

        $update = "INSERT DATA { GRAPH <{$graphUri}> \n{$turtleBody}\n }";

        if ($this->queueEnabled()) {
            // Enqueue a job for asynchronous write. Collect endpoint/creds/timeout
            // from settings (same keys as SparqlUpdateService uses) and dispatch
            // a FusekiSyncJob containing the ready-to-post SPARQL UPDATE text.
            try {
                $base = rtrim($this->setting('fuseki_endpoint', config('ahg-ric.fuseki_endpoint', config('heratio.fuseki_endpoint', 'http://localhost:3030/heratio'))), '/');
                $updateEndpoint = $this->setting('fuseki_update_endpoint', config('heratio.fuseki_update_endpoint', $base . '/update'));
                $username = $this->setting('fuseki_username', config('heratio.fuseki_update_username'));
                $password = $this->setting('fuseki_password', config('heratio.fuseki_update_password'));
                $timeoutSeconds = (int) $this->setting('fuseki_update_timeout', config('heratio.fuseki_update_timeout', 30));

                Dispatch::dispatch(new \AhgRic\Jobs\FusekiSyncJob($updateEndpoint, $username, $password, $timeoutSeconds, $update));
                return ['ok' => true, 'status' => 202, 'error' => null];
            } catch (\Throwable $e) {
                Log::warning('[ahg-ric] failed to enqueue fuseki insert job: ' . $e->getMessage());
                // fallthrough to synchronous attempt
            }
        }

        return $this->upd->insertRdfStar($graphUri, $turtleBody);
    }

    /**
     * Insert plain data (alias to insertRdfStar for now)
     */
    public function insertData(string $graphUri, string $turtleBody): array
    {
        return $this->insertRdfStar($graphUri, $turtleBody);
    }

    // ── #77 phase 2: save/delete dispatch ──────────────────────────────

    /**
     * Dispatch a save-time sync. Gated on fuseki_sync_enabled (master) AND
     * fuseki_sync_on_save. The turtle body is built lazily via the closure
     * so we don't pay for serialization when sync is disabled.
     *
     * Used by RicEntityService::create/update* methods. Failure is logged
     * but does NOT throw - the relational write has already committed and
     * Fuseki failure must not poison the caller. The integrity check
     * schedule (fuseki_integrity_schedule) is the safety net for catching
     * triples that didn't make it.
     */
    public function dispatchSyncOnSave(string $graphUri, callable $turtleBuilder): void
    {
        if (!$this->enabled()) return;
        if (!$this->syncOnSaveEnabled()) return;

        try {
            $turtle = $turtleBuilder();
            if (trim($turtle) === '') return;
            $this->insertRdfStar($graphUri, $turtle);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ric] dispatchSyncOnSave failed: ' . $e->getMessage(), [
                'graph_uri' => $graphUri,
            ]);
        }
    }

    /**
     * Dispatch a delete-time sync. Gated on fuseki_sync_enabled (master) AND
     * fuseki_sync_on_delete. Issues `DROP SILENT GRAPH <uri>` for the entity's
     * own graph; when fuseki_cascade_delete is on AND $relatedGraphUris is
     * non-empty, also drops every related graph (e.g. relation graphs that
     * referenced this entity).
     */
    public function dispatchSyncOnDelete(string $graphUri, array $relatedGraphUris = []): void
    {
        if (!$this->enabled()) return;
        if (!$this->syncOnDeleteEnabled()) return;

        $graphsToDrop = [$graphUri];
        if ($this->cascadeDeleteEnabled() && !empty($relatedGraphUris)) {
            foreach ($relatedGraphUris as $g) {
                if (is_string($g) && $g !== '' && !in_array($g, $graphsToDrop, true)) {
                    $graphsToDrop[] = $g;
                }
            }
        }

        try {
            $update = '';
            foreach ($graphsToDrop as $g) {
                $update .= "DROP SILENT GRAPH <{$g}> ;\n";
            }
            // SparqlUpdateService::executeUpdate is the raw POST path; the
            // queue option for inserts doesn't apply to DROP since it has to
            // run before downstream queries see stale graphs.
            $this->upd->executeUpdate(rtrim($update, " ;\n"));
        } catch (\Throwable $e) {
            Log::warning('[ahg-ric] dispatchSyncOnDelete failed: ' . $e->getMessage(), [
                'graph_uri'      => $graphUri,
                'related_graphs' => $relatedGraphUris,
                'cascade_on'     => $this->cascadeDeleteEnabled(),
            ]);
        }
    }

    // ── #77 phase 2: minimal turtle helpers used by RicEntityService ───
    //
    // These produce a small but legitimate turtle body per entity - enough
    // for Fuseki to know the entity exists, its type, and its label. Full
    // RIC-O serialization (every property + relation expansion) lives in
    // RicSerializationService::serialize{Place,Rule,Activity,Instantiation}
    // which produces JSON-LD; replacing this stub with that path is a
    // future enhancement that needs JSON-LD-to-turtle conversion. The
    // setting wiring (fuseki_sync_on_save) takes effect today via this
    // minimal body.

    public function buildEntityTurtle(string $rdfType, string $entityUri, ?string $label, ?string $identifier = null): string
    {
        $lines = [
            '@prefix rico: <https://www.ica.org/standards/RiC/ontology#> .',
            '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .',
            '@prefix dcterms: <http://purl.org/dc/terms/> .',
            '',
            "<{$entityUri}> a {$rdfType} ;",
        ];
        if ($label !== null && $label !== '') {
            $lines[] = '    rdfs:label ' . self::tplLiteral($label) . ' ;';
        }
        if ($identifier !== null && $identifier !== '') {
            $lines[] = '    dcterms:identifier ' . self::tplLiteral($identifier) . ' ;';
        }
        // Strip the trailing semicolon and replace with a period.
        $last = array_pop($lines);
        $lines[] = rtrim($last, ' ;') . ' .';

        return implode("\n", $lines);
    }

    /**
     * Quote a string as a turtle literal. Escapes backslash + double-quote;
     * other characters pass through. The output is double-quoted so newlines
     * in the input would break parsing - callers should pass single-line
     * label/identifier values.
     */
    private static function tplLiteral(string $val): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $val);
        return '"' . $escaped . '"';
    }
}
