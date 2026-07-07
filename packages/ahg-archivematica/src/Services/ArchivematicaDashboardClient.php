<?php

/**
 * ArchivematicaDashboardClient - Archivematica Dashboard API client for Heratio.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgArchivematica\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin, config-driven wrapper over the Archivematica Dashboard API. The
 * Dashboard drives *processing*: it starts and approves transfers and reports
 * per-microservice status for both the transfer and the downstream ingest
 * (SIP -> AIP) stages.
 *
 * Endpoints (Dashboard API):
 *   POST {url}/api/transfer/start_transfer/   start a transfer in the watched dir
 *   POST {url}/api/transfer/approve/          approve the started transfer
 *   GET  {url}/api/transfer/status/{uuid}/    transfer microservice status
 *   GET  {url}/api/ingest/status/{uuid}/      ingest (SIP/AIP) microservice status
 *
 * Auth: header  Authorization: ApiKey {username}:{api_key}
 *
 * All configuration is read from config('archivematica.*') (which is fed from
 * ahg_settings by the package config file). No credentials live in code.
 *
 * Every method throws RuntimeException on a transport error or a non-2xx
 * response so callers (TransferService, PollArchivematicaJobs) can record a
 * clear failure reason on the am_job row instead of silently swallowing it.
 */
class ArchivematicaDashboardClient
{
    /** Default request timeout (seconds). Overridable via config. */
    private const DEFAULT_TIMEOUT = 30;

    private string $baseUrl;

    private string $username;

    private string $apiKey;

    private int $timeout;

    /**
     * @param array<string,mixed>|null $overrides optional explicit config
     *        (base_url/username/api_key/timeout) - mainly for tests. When
     *        null every value is resolved from config('archivematica.*').
     */
    public function __construct(?array $overrides = null)
    {
        $this->baseUrl = rtrim((string) ($overrides['base_url']
            ?? config('archivematica.am_dashboard_url', '')), '/');
        $this->username = (string) ($overrides['username']
            ?? config('archivematica.am_dashboard_username', ''));
        $this->apiKey = (string) ($overrides['api_key']
            ?? config('archivematica.am_dashboard_api_key', ''));
        $this->timeout = (int) ($overrides['timeout']
            ?? config('archivematica.am_dashboard_timeout', self::DEFAULT_TIMEOUT));
    }

    /**
     * True when a Dashboard URL + credentials are configured. Callers should
     * check this before attempting a transfer so we fail with a friendly
     * message rather than an opaque connection error.
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->username !== '' && $this->apiKey !== '';
    }

    /**
     * Start a transfer. Archivematica copies the payload into the transfer
     * source directory and returns the resulting watched-dir path. Note: the
     * transfer UUID is NOT known at this point - it is assigned when the
     * transfer is approved (see approveTransfer()).
     *
     * @param string        $name  human-readable transfer name
     * @param string        $type  transfer type (standard|zipfile|dspace|...)
     * @param array<string> $paths one or more source paths. Each is sent as
     *                             base64("{pipeline_uuid}:{path}") per the
     *                             Dashboard API contract.
     * @param string|null   $pipelineUuid pipeline the paths belong to;
     *                             defaults to am_default_pipeline_uuid.
     * @param string|null   $accession optional accession number.
     *
     * @return array<string,mixed> decoded JSON body, e.g. {message, path}
     */
    public function startTransfer(
        string $name,
        string $type,
        array $paths,
        ?string $pipelineUuid = null,
        ?string $accession = null
    ): array {
        $pipelineUuid = $pipelineUuid ?: (string) config('archivematica.am_default_pipeline_uuid', '');

        $encodedPaths = [];
        foreach ($paths as $p) {
            $encodedPaths[] = base64_encode($pipelineUuid . ':' . $p);
        }

        $payload = [
            'name'     => $name,
            'type'     => $type,
            'paths[]'  => $encodedPaths,
            'row_ids[]' => [''],
        ];
        if ($accession !== null && $accession !== '') {
            $payload['accession'] = $accession;
        }

        return $this->request('POST', '/api/transfer/start_transfer/', $payload);
    }

    /**
     * Approve a started transfer. Returns the assigned transfer UUID.
     *
     * @param string $type      transfer type (must match start_transfer)
     * @param string $directory the watched-dir directory name returned by
     *                          start_transfer (basename of its `path`).
     *
     * @return array<string,mixed> decoded JSON body, e.g. {message, uuid}
     */
    public function approveTransfer(string $type, string $directory): array
    {
        return $this->request('POST', '/api/transfer/approve/', [
            'type'      => $type,
            'directory' => $directory,
        ]);
    }

    /**
     * Poll transfer-stage status for a transfer UUID. When the transfer has
     * moved into ingest the response carries `sip_uuid`, the handle used to
     * poll ingestStatus().
     *
     * @return array<string,mixed> e.g. {status, name, microservice, uuid, sip_uuid}
     */
    public function transferStatus(string $uuid): array
    {
        return $this->request('GET', '/api/transfer/status/' . rawurlencode($uuid) . '/');
    }

    /**
     * Poll ingest-stage status for a SIP/AIP UUID.
     *
     * @return array<string,mixed> e.g. {status, name, microservice, uuid, aip_uuid}
     */
    public function ingestStatus(string $uuid): array
    {
        return $this->request('GET', '/api/ingest/status/' . rawurlencode($uuid) . '/');
    }

    /**
     * Issue a single authenticated request and decode the JSON body. Throws
     * RuntimeException on transport failure or non-2xx so the caller can
     * capture a precise error string.
     *
     * @param array<string,mixed> $data query (GET) or form body (POST)
     *
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, array $data = []): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('Archivematica Dashboard URL is not configured.');
        }

        $url = $this->baseUrl . $path;

        try {
            $response = $this->http()->send($method, $url, [
                strtoupper($method) === 'GET' ? 'query' : 'form_params' => $data,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Archivematica Dashboard request to {$path} failed: " . $e->getMessage(),
                0,
                $e
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "Archivematica Dashboard {$method} {$path} returned HTTP "
                . $response->status() . ': ' . $response->body()
            );
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build a pre-configured PendingRequest carrying the ApiKey auth header,
     * accept-json, and the configured timeout.
     */
    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'ApiKey ' . $this->username . ':' . $this->apiKey,
            'Accept'        => 'application/json',
        ])->timeout($this->timeout);
    }
}
