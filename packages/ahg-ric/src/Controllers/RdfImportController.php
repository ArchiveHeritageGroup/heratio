<?php

/**
 * RdfImportController - Service for Heratio
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

namespace AhgRic\Controllers;

use AhgRic\Services\RdfImportService;
use AhgRic\Services\SparqlQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RdfImportController extends Controller
{
    public function form()
    {
        return view('ahg-ric::import', [
            'result'       => null,
            'committed'    => null,
            'sparqlEnabled'=> (bool) config('ahg-ric.fuseki_endpoint'),
        ]);
    }

    public function run(Request $request)
    {
        $request->validate([
            'format'  => 'required|in:turtle,jsonld,rdfxml',
            'payload' => 'nullable|string',
            'file'    => 'nullable|file|max:10240',
            'commit'  => 'nullable|in:0,1',
        ]);

        $payload = $request->input('payload') ?: '';
        if ($request->hasFile('file')) {
            $payload = (string) file_get_contents($request->file('file')->getRealPath());
        }

        if (trim($payload) === '') {
            return back()->with('error', 'Provide a file upload or paste an RDF document.')->withInput();
        }

        $svc = new RdfImportService();

        try {
            $result = $svc->dryRun($payload, $request->input('format'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Parse error: ' . $e->getMessage())->withInput();
        }

        $committed = null;
        if ($request->input('commit') === '1') {
            try {
                $committed = $svc->commit($payload, $request->input('format'), app()->getLocale());
            } catch (\Throwable $e) {
                return back()
                    ->with('error', 'Commit failed: ' . $e->getMessage())
                    ->with('result', $result)
                    ->withInput();
            }
        }

        return view('ahg-ric::import', [
            'result'        => $result,
            'committed'     => $committed,
            'sparqlEnabled' => (bool) config('ahg-ric.fuseki_endpoint'),
        ]);
    }

    /**
     * GET /api/sparql — read-only SPARQL proxy to the configured Fuseki endpoint.
     * Blocks UPDATE / INSERT / DELETE / LOAD / DROP / CLEAR / CREATE keywords.
     */
    public function sparqlProxy(Request $request)
    {
        $query = (string) ($request->input('query') ?? $request->getContent());
        if (trim($query) === '') {
            return response()->json(['error' => 'Missing query parameter or body.'], 400);
        }

        // Defence in depth — only SELECT / ASK / CONSTRUCT / DESCRIBE allowed
        if (preg_match('/\b(INSERT|DELETE|LOAD|DROP|CLEAR|CREATE|MOVE|COPY|ADD)\b/i', $query)) {
            return response()->json(['error' => 'Update operations are not permitted on this endpoint.'], 403);
        }
        if (!preg_match('/\b(SELECT|ASK|CONSTRUCT|DESCRIBE)\b/i', $query)) {
            return response()->json(['error' => 'Only SELECT / ASK / CONSTRUCT / DESCRIBE queries are accepted.'], 400);
        }

        try {
            $svc = new SparqlQueryService();
            $bindings = $svc->executeQuery($query);
            return response()->json($bindings);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Query failed: ' . $e->getMessage()], 502);
        }
    }
}
