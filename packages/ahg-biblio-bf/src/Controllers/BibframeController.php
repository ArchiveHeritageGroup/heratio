<?php

/**
 * BibframeController - BIBFRAME integration for Heratio
 *
 * Converts bibliographic catalogue records to/from BIBFRAME 2.0 RDF
 * via the OpenRiC RiC-O service layer. Supports import, export, and
 * validation of BIBFRAME XML/RDF records.
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

namespace AhgBiblioBf\Controllers;

use AhgBiblioBf\Services\BibframeService;
use AhgBiblioBf\Services\BiblioWorkRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BibframeController extends Controller
{
    protected BibframeService $service;

    protected BiblioWorkRepository $works;

    public function __construct(BibframeService $service, BiblioWorkRepository $works)
    {
        $this->service = $service;
        $this->works = $works;
    }

    /**
     * BIBFRAME integration dashboard — overview + quick links.
     */
    public function index(): Response
    {
        // Counts come from the live catalogue (library_item / library_copy),
        // mapped onto the BIBFRAME Work -> Instance -> Item hierarchy.
        $stats = [
            'works'     => $this->works->countWorks(),
            'instances' => $this->works->countInstances(),
            'items'     => $this->works->countItems(),
        ];

        return response()->view('ahg-biblio-bf::index', [
            'stats' => $stats,
        ]);
    }

    /**
     * Show a single bibliographic work as BIBFRAME RDF.
     */
    public function show(int $workId): Response
    {
        if (! $this->works->find($workId)['work']) {
            abort(404);
        }

        return response($this->service->catalogToRdf($workId), 200, [
            'Content-Type' => 'application/rdf+xml; charset=utf-8',
        ]);
    }

    /**
     * Export UI — select works and format for BIBFRAME output.
     */
    public function export(): Response
    {
        return response()->view('ahg-biblio-bf::export', [
            'works' => $this->works->listWorks(200),
        ]);
    }

    /**
     * Run the BIBFRAME export. Streams RDF for a single work or batch.
     */
    public function exportRun(Request $request): Response
    {
        $validated = $request->validate([
            'work_id'  => 'nullable|integer',
            'format'   => 'nullable|string|in:xml,rdfxml,turtle,ntriples,json-ld',
            'batch'    => 'nullable|boolean',
        ]);

        $workId = $validated['work_id'] ?? null;
        $format = $validated['format'] ?? 'xml';

        if ($workId) {
            $rdf = $this->service->catalogToRdf($workId, $format);
            return response($rdf, 200, [
                'Content-Type' => 'application/rdf+xml; charset=utf-8',
                'Content-Disposition' => "attachment; filename=work-{$workId}.rdf",
            ]);
        }

        if (! empty($validated['batch'])) {
            $data = $this->works->listWorks(200)->map(fn($w) => [
                'id'    => $w->id,
                'title' => $w->title,
                'rdf'   => $this->service->catalogToRdf((int) $w->id, $format),
            ]);

            return response()->json([
                'count' => $data->count(),
                'works' => $data,
            ]);
        }

        return redirect()->back()->with('info', 'No work selected.');
    }

    /**
     * Import UI — submit a BIBFRAME RDF/XML document.
     */
    public function import(): Response
    {
        return response()->view('ahg-biblio-bf::import');
    }

    /**
     * Run the BIBFRAME import. Parses RDF and merges into the catalogue.
     */
    public function importRun(Request $request): Response
    {
        $validated = $request->validate([
            'rdf_file' => 'required|file|mimes:xml,rdf,ttl|max:10240',
        ]);

        $file = $request->file('rdf_file');
        $content = file_get_contents($file->getRealPath());
        $stats = $this->service->importRdf($content);

        Log::info('[Bibframe] Import completed', (array) $stats);

        return redirect()->back()->with('success', sprintf(
            'Imported: %d works, %d instances, %d items. %d warnings.',
            $stats['works'] ?? 0,
            $stats['instances'] ?? 0,
            $stats['items'] ?? 0,
            $stats['warnings'] ?? 0
        ));
    }

    /**
     * Validation UI — paste or upload a BIBFRAME document for validation.
     */
    public function validate(): Response
    {
        return response()->view('ahg-biblio-bf::validate');
    }

    /**
     * Run validation against the LoC BIBFRAME validator profile.
     */
    public function validateRun(Request $request): Response
    {
        $validated = $request->validate([
            'rdf_content' => 'nullable|string',
            'rdf_file'    => 'nullable|file|mimes:xml,rdf,ttl|max:10240',
        ]);

        $content = $validated['rdf_content']
            ?? ($request->hasFile('rdf_file')
                ? file_get_contents($request->file('rdf_file')->getRealPath())
                : '');

        if (empty(trim($content))) {
            return redirect()->back()->with('error', 'No RDF content provided.');
        }

        $result = $this->service->validateRdf($content);
        $passed = empty($result['errors']) && empty($result['fatal']);

        Log::info('[Bibframe] Validate ' . ($passed ? 'PASSED' : 'FAILED'), $result);

        return redirect()->back()
            ->withInput(['rdf_content' => $content])
            ->with($passed ? 'success' : 'error', $passed
                ? 'Document is valid BIBFRAME 2.0.'
                : 'Document has validation errors: ' . implode('; ', array_merge(
                    $result['fatal'] ?? [],
                    $result['errors'] ?? []
                ))
            )
            ->with('validation_result', $result);
    }

    /**
     * Agent management UI — browse/manage BIBFRAME agents.
     */
    public function agent(): Response
    {
        // Agents are the catalogue's contributors (library_item_creator),
        // de-duplicated by name.
        return response()->view('ahg-biblio-bf::agent', [
            'agents' => $this->works->listAgents(),
        ]);
    }
}
