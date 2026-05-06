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
}
