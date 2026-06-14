<?php

/**
 * ResearchBibliographiesController - Controller for Heratio
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchBibliographiesController - Researcher bibliographies + reference-manager
 * exports.
 *
 * Extracted from ResearchController as stage 6 of the monolith decomposition
 * (issue #1253 / #1269). All four endpoints are auth-gated and operate on the
 * current researcher's own bibliographies and bibliography entries via the
 * research_bibliography / research_bibliography_entry tables. No cross-calls to
 * other ResearchController methods existed - the methods used only the shared
 * trait helper (getSidebarData), the injected ResearchService
 * (getResearcherByUserId), and the CitationExportService resolved from the
 * container, so the move is a verbatim lift. exportBibliographyEntry is
 * service-coupled to CitationExportService only (NOT method-coupled to
 * ResearchCitationsController) and remains in the auth group as before.
 */
class ResearchBibliographiesController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function bibliographies(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $bibliographies = DB::table('research_bibliography')
            ->where('researcher_id', $researcher->id)
            ->orderBy('name')
            ->get()->toArray();
        foreach ($bibliographies as $bib) {
            $bib->entry_count = DB::table('research_bibliography_entry')
                ->where('bibliography_id', $bib->id)->count();
        }

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $bibliographyId = DB::table('research_bibliography')->insertGetId([
                'researcher_id' => $researcher->id,
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'citation_style' => $request->input('citation_style', 'chicago'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return redirect()->route('research.viewBibliography', $bibliographyId)->with('success', 'Bibliography created');
        }

        return view('research::research.bibliographies', array_merge(
            $this->getSidebarData('bibliographies'),
            compact('researcher', 'bibliographies')
        ));
    }

    public function viewBibliography(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $bibliography = DB::table('research_bibliography')
            ->where('id', $id)
            ->where('researcher_id', $researcher->id)
            ->first();
        if (!$bibliography) abort(404);

        $entries = DB::table('research_bibliography_entry')
            ->where('bibliography_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'add_entry') {
                $title = trim($request->input('title', ''));
                $objectId = (int) $request->input('object_id');

                // If object_id given but no title, resolve from DB
                if ($objectId && !$title) {
                    $obj = DB::table('information_object_i18n')
                        ->where('id', $objectId)->where('culture', 'en')->first();
                    $title = $obj->title ?? 'Untitled';
                }

                if ($title) {
                    $maxOrder = DB::table('research_bibliography_entry')
                        ->where('bibliography_id', $id)->max('sort_order') ?? 0;
                    DB::table('research_bibliography_entry')->insert([
                        'bibliography_id' => $id,
                        'object_id' => $objectId ?: null,
                        'title' => $title,
                        'authors' => $request->input('authors') ?: null,
                        'date' => $request->input('year') ?: null,
                        'container_title' => $request->input('publication') ?: null,
                        'volume' => $request->input('volume') ?: null,
                        'pages' => $request->input('pages') ?: null,
                        'doi' => $request->input('doi') ?: null,
                        'url' => $request->input('url') ?: null,
                        'entry_type' => $request->input('entry_type', 'book'),
                        'notes' => $request->input('notes') ?: null,
                        'sort_order' => $maxOrder + 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    return redirect()->route('research.viewBibliography', $id)->with('success', 'Entry added');
                }
                return redirect()->route('research.viewBibliography', $id)->with('error', 'Title is required');
            }

            if ($action === 'edit_entry') {
                $entryId = (int) $request->input('entry_id');
                DB::table('research_bibliography_entry')
                    ->where('id', $entryId)->where('bibliography_id', $id)
                    ->update([
                        'title' => $request->input('title'),
                        'authors' => $request->input('authors') ?: null,
                        'date' => $request->input('year') ?: null,
                        'container_title' => $request->input('publication') ?: null,
                        'volume' => $request->input('volume') ?: null,
                        'pages' => $request->input('pages') ?: null,
                        'doi' => $request->input('doi') ?: null,
                        'url' => $request->input('url') ?: null,
                        'entry_type' => $request->input('entry_type', 'book'),
                        'notes' => $request->input('notes') ?: null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                return redirect()->route('research.viewBibliography', $id)->with('success', 'Entry updated');
            }

            if ($action === 'update') {
                DB::table('research_bibliography')->where('id', $id)->update([
                    'name' => $request->input('name'),
                    'description' => $request->input('description') ?: null,
                    'citation_style' => $request->input('citation_style', 'chicago'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                return redirect()->route('research.viewBibliography', $id)->with('success', 'Bibliography updated');
            }

            if ($action === 'remove_entry') {
                DB::table('research_bibliography_entry')
                    ->where('id', (int) $request->input('entry_id'))
                    ->where('bibliography_id', $id)
                    ->delete();
                return redirect()->route('research.viewBibliography', $id)->with('success', 'Entry removed');
            }

            if ($action === 'delete') {
                DB::table('research_bibliography_entry')->where('bibliography_id', $id)->delete();
                DB::table('research_bibliography')->where('id', $id)->delete();
                return redirect()->route('research.bibliographies')->with('success', 'Bibliography deleted');
            }
        }

        return view('research::research.view-bibliography', array_merge(
            $this->getSidebarData('bibliographies'),
            compact('researcher', 'bibliography', 'entries')
        ));
    }

    /**
     * Export a researcher's own bibliography as BibTeX / RIS / CSL-JSON.
     *
     * Reuses the exact ownership gating of viewBibliography(): the bibliography
     * must belong to the signed-in researcher, otherwise the same abort(404) is
     * issued. The route constraint limits {format} to bibtex|ris|csljson, but an
     * unknown format is also mapped to 404 defensively. An empty bibliography
     * yields a valid-but-empty download, never a 500.
     */
    public function exportBibliography(Request $request, int $id, string $format)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        // Same ownership gate as viewBibliography().
        $bibliography = DB::table('research_bibliography')
            ->where('id', $id)
            ->where('researcher_id', $researcher->id)
            ->first();
        if (!$bibliography) abort(404);

        $entries = DB::table('research_bibliography_entry')
            ->where('bibliography_id', $id)
            ->orderBy('sort_order')
            ->get()->toArray();

        $exporter = app(\AhgResearch\Services\CitationExportService::class);
        $body     = $exporter->export($entries, $format);
        if ($body === null) {
            abort(404, 'Unsupported export format');
        }

        return response($body, 200, [
            'Content-Type'        => $exporter->mimeFor($format),
            'Content-Disposition' => 'attachment; filename="'
                . $exporter->filenameFor($bibliography->name ?? null, $id, $format) . '"',
        ]);
    }

    /**
     * Export a single bibliography entry (one citation) as BibTeX / RIS /
     * CSL-JSON. The entry must belong to a bibliography owned by the signed-in
     * researcher; otherwise abort(404). Unknown format -> 404.
     */
    public function exportBibliographyEntry(Request $request, int $itemId, string $format)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        // Join through to the owning bibliography so we never expose another
        // researcher's entry.
        $entry = DB::table('research_bibliography_entry as e')
            ->join('research_bibliography as b', 'e.bibliography_id', '=', 'b.id')
            ->where('e.id', $itemId)
            ->where('b.researcher_id', $researcher->id)
            ->select('e.*')
            ->first();
        if (!$entry) abort(404);

        $exporter = app(\AhgResearch\Services\CitationExportService::class);
        $body     = $exporter->export([$entry], $format);
        if ($body === null) {
            abort(404, 'Unsupported export format');
        }

        return response($body, 200, [
            'Content-Type'        => $exporter->mimeFor($format),
            'Content-Disposition' => 'attachment; filename="'
                . $exporter->filenameFor('citation-' . $itemId, $itemId, $format) . '"',
        ]);
    }
}
