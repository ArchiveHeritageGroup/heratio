<?php

/**
 * ArchivematicaSsClient - Storage Service (SS) API v2 client for Heratio.
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

namespace AhgArchivematica\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin, config-driven wrapper over the Archivematica Storage Service API v2.
 * The SS is where AIPs and DIPs land; this client browses packages, reads a
 * single package's detail, and streams a package download to disk.
 *
 * Endpoints (Storage Service API v2):
 *   GET {url}/api/v2/file/?package_type=DIP   list DIP packages (paged)
 *   GET {url}/api/v2/file/{uuid}/             single package detail
 *   GET {url}/api/v2/file/{uuid}/download/    download the package (stream)
 *
 * Auth: header  Authorization: ApiKey {username}:{api_key}
 *
 * All configuration is read from config('archivematica.*') (fed from
 * ahg_settings by the package config file). No credentials live in code.
 *
 * Every method throws RuntimeException on a transport error or a non-2xx
 * response so callers (DipIngestService, IngestDipsCommand) can record a
 * clear failure reason instead of silently swallowing it.
 */
class ArchivematicaSsClient
{
    /** Default request timeout (seconds) for JSON calls. */
    private const DEFAULT_TIMEOUT = 30;

    /** Default timeout (seconds) for a (potentially large) package download. */
    private const DEFAULT_DOWNLOAD_TIMEOUT = 3600;

    private string $baseUrl;

    private string $username;

    private string $apiKey;

    private int $timeout;

    private int $downloadTimeout;

    /**
     * @param array<string,mixed>|null $overrides optional explicit config
     *        (base_url/username/api_key/timeout/download_timeout) - mainly for
     *        tests. When null every value is resolved from
     *        config('archivematica.*').
     */
    public function __construct(?array $overrides = null)
    {
        $this->baseUrl = rtrim((string) ($overrides['base_url']
            ?? config('archivematica.am_ss_url', '')), '/');
        $this->username = (string) ($overrides['username']
            ?? config('archivematica.am_ss_username', ''));
        $this->apiKey = (string) ($overrides['api_key']
            ?? config('archivematica.am_ss_api_key', ''));
        $this->timeout = (int) ($overrides['timeout']
            ?? config('archivematica.am_ss_timeout', self::DEFAULT_TIMEOUT));
        $this->downloadTimeout = (int) ($overrides['download_timeout']
            ?? config('archivematica.am_ss_download_timeout', self::DEFAULT_DOWNLOAD_TIMEOUT));
    }

    /**
     * True when an SS URL + credentials are configured. Callers should check
     * this before browsing so we fail with a friendly message rather than an
     * opaque connection error.
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->username !== '' && $this->apiKey !== '';
    }

    /**
     * Build the SS ApiKey authorization header value. Pure + static so it can
     * be unit-tested without a container / config.
     */
    public static function buildAuthHeader(string $username, string $apiKey): string
    {
        return 'ApiKey ' . $username . ':' . $apiKey;
    }

    /**
     * List packages of a given type (default DIP). Follows the SS paging
     * contract (limit/offset + meta.next) and returns the flattened list of
     * package objects.
     *
     * @param string              $packageType DIP | AIP | transfer | SIP
     * @param array<string,mixed> $filters     extra query filters, e.g.
     *                                          ['status' => 'UPLOADED']
     * @param int                 $limit        page size
     *
     * @return array<int,array<string,mixed>> package objects
     */
    public function listPackages(string $packageType = 'DIP', array $filters = [], int $limit = 100): array
    {
        $objects = [];
        $offset = 0;

        do {
            $query = array_merge($filters, [
                'package_type' => $packageType,
                'limit'        => $limit,
                'offset'       => $offset,
            ]);

            $body = $this->request('GET', '/api/v2/file/', $query);
            $page = $body['objects'] ?? [];
            if (is_array($page)) {
                foreach ($page as $obj) {
                    $objects[] = $obj;
                }
            }

            $meta = $body['meta'] ?? [];
            $next = $meta['next'] ?? null;
            $offset += $limit;
        } while (! empty($next));

        return $objects;
    }

    /**
     * Convenience wrapper: list DIP packages. Only UPLOADED (available on the
     * SS) packages are of interest to the ingest pipeline; callers can widen
     * with $filters if they need more.
     *
     * @param array<string,mixed> $filters extra query filters
     *
     * @return array<int,array<string,mixed>>
     */
    public function listDipPackages(array $filters = [], int $limit = 100): array
    {
        return $this->listPackages('DIP', $filters, $limit);
    }

    /**
     * Fetch a single package's detail by UUID.
     *
     * @return array<string,mixed> decoded package object
     */
    public function getPackage(string $uuid): array
    {
        return $this->request('GET', '/api/v2/file/' . rawurlencode($uuid) . '/');
    }

    /**
     * Stream a package download to $destPath. Returns the absolute path
     * written. Uses a long timeout because DIPs/AIPs can be large.
     *
     * @throws RuntimeException on transport failure, non-2xx, or write failure.
     */
    public function downloadPackage(string $uuid, string $destPath): string
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('Archivematica Storage Service URL is not configured.');
        }

        $dir = dirname($destPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create download directory: {$dir}");
        }

        $url = $this->baseUrl . '/api/v2/file/' . rawurlencode($uuid) . '/download/';

        try {
            $response = $this->http()
                ->timeout($this->downloadTimeout)
                ->withOptions(['stream' => false])
                ->sink($destPath)
                ->get($url);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Archivematica SS download of package {$uuid} failed: " . $e->getMessage(),
                0,
                $e
            );
        }

        if (! $response->successful()) {
            @unlink($destPath);
            throw new RuntimeException(
                "Archivematica SS download of package {$uuid} returned HTTP " . $response->status()
            );
        }

        if (! is_file($destPath) || filesize($destPath) === 0) {
            throw new RuntimeException("Archivematica SS download wrote no data for package {$uuid}.");
        }

        return $destPath;
    }

    /**
     * Issue a single authenticated JSON request and decode the body. Throws
     * RuntimeException on transport failure or non-2xx.
     *
     * @param array<string,mixed> $data query (GET) or form body (POST)
     *
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, array $data = []): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('Archivematica Storage Service URL is not configured.');
        }

        $url = $this->baseUrl . $path;

        try {
            $response = $this->http()->send($method, $url, [
                strtoupper($method) === 'GET' ? 'query' : 'form_params' => $data,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Archivematica SS request to {$path} failed: " . $e->getMessage(),
                0,
                $e
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "Archivematica SS {$method} {$path} returned HTTP "
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
            'Authorization' => self::buildAuthHeader($this->username, $this->apiKey),
            'Accept'        => 'application/json',
        ])->timeout($this->timeout);
    }
}
