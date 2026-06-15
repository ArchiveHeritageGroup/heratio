<?php

declare(strict_types=1);

/**
 * SruController - Heratio ahg-library (heratio#1281)
 *
 * HTTP front for the SRU (Search/Retrieve via URL) server. Dispatches the SRU
 * `operation` to SruService (explain | searchRetrieve), returns the XML response
 * CORS-enabled (discovery layers consume it cross-origin), and best-effort logs
 * each searchRetrieve to library_sru_log. Read-only over the public library
 * catalogue, so no auth is required; an X-API-Key, if supplied, is logged only as
 * a SHA-256 hint (never the key itself).
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\SruService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SruController extends Controller
{
    public function handle(Request $request, SruService $sru): Response
    {
        // SRU is GET-based with an `operation` parameter; default is explain.
        $operation = (string) $request->query('operation', 'explain');

        if ($operation === 'searchRetrieve') {
            $start = microtime(true);
            $params = [
                'version' => $request->query('version'),
                'query' => $request->query('query'),
                'startRecord' => $request->query('startRecord'),
                'maximumRecords' => $request->query('maximumRecords'),
                'recordPacking' => $request->query('recordPacking'),
                'recordSchema' => $request->query('recordSchema'),
                'sortKeys' => $request->query('sortKeys'),
            ];
            $xml = $sru->searchRetrieve($params);
            $durationMs = round((microtime(true) - $start) * 1000, 1);

            $this->logSearch($request, $sru, $durationMs);

            return $this->xml($xml);
        }

        // Default / operation=explain: capability document.
        return $this->xml($sru->explain($request->getHost(), (string) $request->getPort()));
    }

    /** Best-effort request log (never breaks the response; table may not exist yet). */
    private function logSearch(Request $request, SruService $sru, float $durationMs): void
    {
        try {
            if (! Schema::hasTable('library_sru_log')) {
                return;
            }
            $apiKey = (string) ($request->header('X-API-Key') ?? $request->query('api_key', ''));
            DB::table('library_sru_log')->insert([
                'query' => mb_substr((string) $request->query('query', ''), 0, 2000),
                'cql_query' => mb_substr($sru->lastCql, 0, 2000),
                'result_count' => max(0, $sru->lastResultCount),
                'duration_ms' => $durationMs,
                'remote_addr' => $request->ip(),
                'api_key_hint' => $apiKey !== '' ? substr(hash('sha256', $apiKey), 0, 16) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('[ahg-library] SRU log insert skipped: ' . $e->getMessage());
        }
    }

    private function xml(string $body): Response
    {
        return response($body, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'X-API-Key, Content-Type');
    }
}
