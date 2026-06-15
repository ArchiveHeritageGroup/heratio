<?php

/**
 * FrbrController — IFLA FRBR conceptual model for Heratio
 *
 * Converts bibliographic catalogue records to/from the FRBR entity model
 * (Work, Expression, Manifestation, Item) via OpenRiC. Supports import,
 * export, and validation of FRBR XML documents.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 * Email: johan@theahg.co.za
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

namespace AhgBiblioFrbr\Controllers;

use AhgBiblioFrbr\Services\FrbrService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FrbrController extends Controller
{
    protected FrbrService $service;

    public function __construct(FrbrService $service)
    {
        $this->service = $service;
    }

    /**
     * FRBR integration dashboard — overview + quick links.
     */
    public function index(): Response
    {
        // The library_biblio_* scaffold is optional; the live FRBR surface is
        // the work-key clustering over library_item. Degrade to zeros when the
        // scaffold tables are absent rather than 500 the dashboard.
        $schema = \Illuminate\Support\Facades\Schema::connection('heratio');
        $stats = [
            'frbr_works'       => $schema->hasTable('library_biblio_work')     ? DB::connection('heratio')->table('library_biblio_work')->count()     : 0,
            'frbr_expressions' => $schema->hasTable('library_biblio_instance')  ? DB::connection('heratio')->table('library_biblio_instance')->count()  : 0,
            'frbr_items'       => $schema->hasTable('library_biblio_item')      ? DB::connection('heratio')->table('library_biblio_item')->count()      : 0,
        ];

        return response()->view('ahg-biblio-frbr::index', [
            'stats' => $stats,
        ]);
    }

    /**
     * Show a single bibliographic work as FRBR JSON.
     */
    public function show(int $workId): Response
    {
        try {
            $graph = $this->service->catalogToFrbr($workId);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        return response()->json($graph);
    }

    /**
     * Export UI — select works and format for FRBR output.
     */
    public function export(): Response
    {
        $works = DB::connection('heratio')
            ->table('library_biblio_work')
            ->select(['id', 'title', 'author', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        return response()->view('ahg-biblio-frbr::export', [
            'works' => $works,
        ]);
    }

    /**
     * Run the FRBR export. Returns RDF/XML or JSON for a single work or batch.
     */
    public function exportRun(Request $request): Response
    {
        $validated = $request->validate([
            'work_id' => 'nullable|integer',
            'format'  => 'nullable|string|in:xml,json,rdf',
        ]);

        $workId = $validated['work_id'] ?? null;
        $format = $validated['format'] ?? 'xml';

        if (! $workId) {
            return redirect()->back()->with('info', 'No work selected.');
        }

        $xml = $this->service->catalogToXml((int) $workId, $format);

        Log::info('[FRBR] Export', ['work_id' => $workId, 'format' => $format]);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => "attachment; filename=work-{$workId}.xml",
        ]);
    }

    /**
     * Import UI — submit an FRBR XML document.
     */
    public function import(): Response
    {
        return response()->view('ahg-biblio-frbr::import');
    }

    /**
     * Run the FRBR import. Parses XML and merges into the catalogue.
     */
    public function importRun(Request $request): Response
    {
        $validated = $request->validate([
            'frbr_file' => 'required|file|mimes:xml|max:10240',
        ]);

        $file = $request->file('frbr_file');
        $content = file_get_contents($file->getRealPath());
        $stats = $this->service->importXml($content);

        Log::info('[FRBR] Import completed', $stats);

        return redirect()->back()->with('success', sprintf(
            'Imported: %d works, %d expressions, %d items. %d warnings.',
            $stats['works'] ?? 0,
            $stats['instances'] ?? 0,
            $stats['items'] ?? 0,
            $stats['warnings'] ?? 0
        ));
    }

    /**
     * Validation UI — paste or upload an FRBR document.
     */
    public function validate(): Response
    {
        return response()->view('ahg-biblio-frbr::validate');
    }

    /**
     * Run validation against the FRBR conceptual model.
     */
    public function validateRun(Request $request): Response
    {
        $validated = $request->validate([
            'frbr_content' => 'nullable|string',
            'frbr_file'    => 'nullable|file|mimes:xml|max:10240',
        ]);

        $content = $validated['frbr_content']
            ?? ($request->hasFile('frbr_file')
                ? file_get_contents($request->file('frbr_file')->getRealPath())
                : '');

        if (empty(trim($content))) {
            return redirect()->back()->with('error', 'No FRBR content provided.');
        }

        $result = $this->service->validateXml($content);
        $passed = empty($result['errors']) && empty($result['fatal']);

        Log::info('[FRBR] Validate ' . ($passed ? 'PASSED' : 'FAILED'), $result);

        return redirect()->back()
            ->withInput(['frbr_content' => $content])
            ->with($passed ? 'success' : 'error', $passed
                ? 'Document is a valid FRBR record.'
                : 'Document has validation errors: ' . implode('; ', array_merge(
                    $result['fatal'] ?? [],
                    $result['errors'] ?? []
                ))
            )
            ->with('validation_result', $result);
    }

    /**
     * Agent management UI — browse FRBR agents.
     */
    public function agent(): Response
    {
        // The library_biblio_agent scaffold is optional; when the bibliographic
        // schema has not been installed the page must still render (empty list)
        // rather than 500. Mirrors the hasTable() guard used elsewhere here.
        // The blade expects a paginator ($agents->total()/->links()); return one
        // even when the optional scaffold table is absent, so the page renders.
        $hasTable = \Illuminate\Support\Facades\Schema::connection('heratio')->hasTable('library_biblio_agent');
        $agents = $hasTable
            ? DB::connection('heratio')
                ->table('library_biblio_agent')
                ->select(['id', 'name', 'type', 'created_at'])
                ->orderBy('name')
                ->paginate(50)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);

        if (! $hasTable) {
            // Flag the unavailable feature in-app rather than a silent empty list.
            session()->now('info', __('The FRBR agent index is not installed on this instance, so no agents can be listed here.'));
        }

        return response()->view('ahg-biblio-frbr::agent', [
            'agents' => $agents,
        ]);
    }
}
