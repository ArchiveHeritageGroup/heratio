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
     * Catalogue reader, shared with the BIBFRAME package (#1417).
     *
     * Resolved at call time behind a guard so this package carries no composer
     * dependency on ahg-biblio-bf; the surfaces degrade to empty rather than
     * fatal if it is absent.
     */
    protected function works(): ?object
    {
        return class_exists(\AhgBiblioBf\Services\BiblioWorkRepository::class)
            ? app(\AhgBiblioBf\Services\BiblioWorkRepository::class)
            : null;
    }

    /**
     * FRBR integration dashboard — overview + quick links.
     */
    public function index(): Response
    {
        // Counts come from the live catalogue, mapped onto the FRBR hierarchy:
        // a Work is a library_item work_key cluster, an Expression is each
        // library_item, an Item is each library_copy (#1417).
        $works = $this->works();
        $stats = [
            'frbr_works'       => $works?->countWorks() ?? 0,
            'frbr_expressions' => $works?->countInstances() ?? 0,
            'frbr_items'       => $works?->countItems() ?? 0,
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
        return response()->view('ahg-biblio-frbr::export', [
            'works' => $this->works()?->listWorks(200) ?? collect(),
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
        // Agents are the catalogue's contributors (library_item_creator),
        // de-duplicated by name (#1417). The repository returns a collection and
        // this blade expects a paginator ($agents->total()/->links()), so page
        // it here rather than pushing pagination into the shared repository.
        $all = $this->works()?->listAgents() ?? collect();

        $perPage = 50;
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();

        $agents = new \Illuminate\Pagination\LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );

        return response()->view('ahg-biblio-frbr::agent', [
            'agents' => $agents,
        ]);
    }
}
