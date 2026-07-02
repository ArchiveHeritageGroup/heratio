<?php

/**
 * SparqlController - SPARQL 1.1 query endpoint over the PROV-O graph
 * for a single information object. Phase 4 of #658.
 *
 * Two ways to call it (per the SPARQL 1.1 Protocol):
 *   GET  /sparql?ioId=NNN&query=URL-ENCODED-SPARQL
 *   POST /sparql        body = SPARQL text   ?ioId=NNN
 *                                                   (also accepts form-encoded
 *                                                   query=... or JSON {query, ioId})
 *
 * Response: application/sparql-results+json per
 *   https://www.w3.org/TR/sparql11-results-json/
 *
 * Auth:
 *   - session (any logged-in user) OR
 *   - Bearer token equal to ahg_setting.sparql_bearer_token
 *
 * Scope:
 *   The ioId parameter pre-filters the graph; full-corpus SPARQL stays
 *   on the Phase 5 backlog. The graph is built on the fly per request
 *   (no caching) - the per-IO triple count is bounded by the row count
 *   in preservation_event so this is intentionally simple.
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
 */

namespace AhgMetadataExport\Controllers;

use AhgMetadataExport\Services\Sparql\ProvOGraphBuilder;
use AhgMetadataExport\Services\Sparql\SimpleSparqlEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SparqlController extends Controller
{
    public function handle(Request $request): Response
    {
        if (! $this->authorised($request)) {
            return response('Unauthorised', 401)->header('WWW-Authenticate', 'Bearer realm="heratio-sparql"');
        }

        $ioId = (int) ($request->input('ioId') ?? $request->query('ioId') ?? 0);
        if ($ioId <= 0) {
            return $this->errorJson('Missing or invalid ioId parameter. Full-corpus SPARQL is Phase 5.', 400);
        }

        // #1384/#1389 — ICIP/TK + ODRL restrictions are absolute; never build a
        // PROV-O graph for a restricted record, even for a bearer-token holder.
        if (in_array($ioId, app(\AhgCore\Services\DisclosureGate::class)->restrictedIds(), true)) {
            return $this->errorJson('Record not available.', 404);
        }

        $query = (string) ($request->input('query') ?? $request->query('query') ?? '');
        if ($query === '' && $request->isMethod('POST')) {
            // Some clients POST the raw SPARQL as the body with
            // Content-Type: application/sparql-query
            $body = (string) $request->getContent();
            if (str_contains(strtolower(trim($body)), 'select')) {
                $query = $body;
            }
        }
        if (trim($query) === '') {
            return $this->errorJson('Missing query parameter.', 400);
        }

        // Only SELECT is supported in Phase 4 (ASK / CONSTRUCT / DESCRIBE
        // would need a different result serialisation).
        if (! preg_match('/(^|\s)SELECT\s/i', $query)) {
            return $this->errorJson('Only SPARQL SELECT queries are supported in Phase 4.', 400);
        }

        $triples = (new ProvOGraphBuilder())->buildTriples($ioId);
        $engine = new SimpleSparqlEngine($triples);

        try {
            $results = $engine->querySelect($query);
        } catch (\InvalidArgumentException $e) {
            return $this->errorJson('Query parse error: '.$e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->errorJson('Query execution error: '.$e->getMessage(), 500);
        }

        $payload = json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response($payload, 200)
            ->header('Content-Type', 'application/sparql-results+json; charset=UTF-8')
            ->header('X-Heratio-Sparql-Profile', 'subset-1');
    }

    /**
     * Either a logged-in web session OR a Bearer token matching the
     * configured sparql_bearer_token grants access.
     */
    private function authorised(Request $request): bool
    {
        if (Auth::check()) {
            return true;
        }

        $auth = (string) $request->header('Authorization', '');
        if (stripos($auth, 'Bearer ') !== 0) {
            return false;
        }
        $token = trim(substr($auth, 7));
        if ($token === '') {
            return false;
        }

        $expected = $this->configuredBearerToken();
        if ($expected === null || $expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * Pull the configured Bearer token from the ahg_setting table. Returns
     * null when the row is absent so a missing token can never accidentally
     * grant access.
     */
    private function configuredBearerToken(): ?string
    {
        if (! Schema::hasTable('ahg_setting')) {
            return null;
        }
        $val = DB::table('ahg_setting')->where('key', 'sparql_bearer_token')->value('value');
        $val = trim((string) ($val ?? ''));
        return $val === '' ? null : $val;
    }

    private function errorJson(string $message, int $status): Response
    {
        $payload = json_encode([
            'head' => ['vars' => []],
            'results' => ['bindings' => []],
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES);
        return response($payload, $status)
            ->header('Content-Type', 'application/sparql-results+json; charset=UTF-8');
    }
}
