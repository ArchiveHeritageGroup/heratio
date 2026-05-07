<?php

namespace AhgRic\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

/**
 * FusekiSyncJob
 *
 * Performs a SPARQL UPDATE POST to the configured Fuseki endpoint. This job
 * is used when fuseki_queue_enabled is true to offload synchronous updates.
 */
class FusekiSyncJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $updateEndpoint;
    public ?string $username;
    public ?string $password;
    public int $timeoutSeconds;
    public string $sparqlUpdate;

    public function __construct(string $updateEndpoint, ?string $username, ?string $password, int $timeoutSeconds, string $sparqlUpdate)
    {
        $this->updateEndpoint = $updateEndpoint;
        $this->username = $username;
        $this->password = $password;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->sparqlUpdate = $sparqlUpdate;
    }

    public function handle()
    {
        $ch = curl_init($this->updateEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/sparql-update; charset=utf-8',
                'Accept: */*',
            ],
            CURLOPT_POSTFIELDS => $this->sparqlUpdate,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        if ($this->username !== null && $this->password !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $errstr = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            Log::warning('[ahg-ric][fuseki-job] sparql update curl failed', ['errno' => $errno, 'err' => $errstr]);
            return;
        }
        if ($status < 200 || $status >= 300) {
            Log::warning('[ahg-ric][fuseki-job] sparql update non-2xx', ['status' => $status, 'body' => substr((string) $body, 0, 500)]);
            return;
        }

        // Success
        Log::info('[ahg-ric][fuseki-job] sparql update succeeded', ['status' => $status]);
    }
}
