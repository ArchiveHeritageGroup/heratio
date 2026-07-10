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
     *                             base64("{transfer_source_location_uuid}:{path}")
     *                             — the SS *location* UUID, per the Dashboard API
     *                             contract (the pipeline is implied by the
     *                             authenticated Dashboard).
     * @param string|null   $pipelineUuid retained for signature compatibility;
     *                             no longer used for the path prefix (#1357-follow).
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
        // The path prefix is the Transfer Source LOCATION uuid (from the SS), NOT
        // the pipeline uuid — encoding the pipeline uuid made AM fail to resolve a
        // location and reply "No path provided".
        $locationUuid = (string) config('archivematica.am_transfer_source_location_uuid', '');
        if ($locationUuid === '') {
            throw new RuntimeException(
                'Archivematica transfer source location UUID is not configured '
                . '(am_transfer_source_location_uuid — from the SS Locations page).'
            );
        }

        // Build the body by hand. AM reads request.POST.getlist('paths[]'), so the
        // wire form must carry repeated literal `paths[]=` / `row_ids[]=` keys.
        // Passing an array under key 'paths[]' to form_params serialises it as
        // `paths[][0]=…`, which AM ignores → the other cause of "No path provided".
        $fields = [
            'name=' . rawurlencode($name),
            'type=' . rawurlencode($type),
        ];
        foreach ($paths as $p) {
            $fields[] = 'paths[]=' . rawurlencode(base64_encode($locationUuid . ':' . $p));
            $fields[] = 'row_ids[]=';
        }
        if ($accession !== null && $accession !== '') {
            $fields[] = 'accession=' . rawurlencode($accession);
        }

        return $this->postForm('/api/transfer/start_transfer/', implode('&', $fields));
    }

    /**
     * POST a hand-built application/x-www-form-urlencoded body (for endpoints that
     * need repeated `key[]=` fields Guzzle's form_params can't express), decoding
     * the JSON reply. Mirrors request()'s auth + error handling.
     *
     * @return array<string,mixed>
     */
    private function postForm(string $path, string $body): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('Archivematica Dashboard URL is not configured.');
        }
        $url = $this->baseUrl . $path;

        try {
            $response = $this->http()
                ->withBody($body, 'application/x-www-form-urlencoded')
                ->post($url);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Archivematica Dashboard request to {$path} failed: " . $e->getMessage(),
                0,
                $e
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "Archivematica Dashboard POST {$path} returned HTTP "
                . $response->status() . ': ' . $response->body()
            );
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
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
     * List transfers awaiting approval. start_transfer copies the payload into the
     * watched dir asynchronously, so a transfer only becomes approvable once it
     * appears here — callers poll this before approveTransfer().
     *
     * @return array<int,array<string,mixed>> each e.g. {type, directory, uuid}
     */
    public function unapproved(): array
    {
        $resp = $this->request('GET', '/api/transfer/unapproved/');

        return is_array($resp['results'] ?? null) ? $resp['results'] : [];
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
