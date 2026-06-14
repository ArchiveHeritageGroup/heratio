<?php

/**
 * ResearchExportsController - Controller for Heratio
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
 * ResearchExportsController - Researcher notes + collection finding-aid exports.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). All three endpoints are auth-gated and operate on the current
 * researcher's own annotations and collections (research_annotation,
 * research_collection_item -> information_object). No cross-calls to other
 * ResearchController methods existed - the methods used only the injected
 * ResearchService (getResearcherByUserId + getCollection) and the exclusive
 * private helper getCollectionFindingAidData(), which moves verbatim with them.
 */
class ResearchExportsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function exportNotes(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $format = $request->input('format', 'pdf');
        $ids = $request->input('ids') ? explode(',', $request->input('ids')) : [];
        $id = $request->input('id');
        if ($id) $ids = [(int) $id];

        $query = DB::table('research_annotation')
            ->where('researcher_id', $researcher->id);
        if (!empty($ids)) {
            $query->whereIn('id', array_map('intval', $ids));
        }
        $notes = $query->orderBy('created_at', 'desc')->get();

        if ($format === 'csv') {
            $filename = 'research-notes.csv';
            return response()->stream(function () use ($notes) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Title', 'Content', 'Tags', 'Visibility', 'Created']);
                foreach ($notes as $n) {
                    fputcsv($out, [$n->title, strip_tags($n->content ?? ''), $n->tags, $n->visibility, $n->created_at]);
                }
                fclose($out);
            }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
        }

        // PDF = printable HTML
        return view('research::research.export-notes-pdf', compact('notes', 'researcher'));
    }

    public function exportFindingAid(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collectionId = (int) $request->input('id');
        $format = $request->input('format', 'pdf');

        $collection = $this->service->getCollection($collectionId);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Collection not found');
        }

        $data = $this->getCollectionFindingAidData($collectionId);

        if ($format === 'pdf') {
            // Render as printable HTML — user can print to PDF
            return view('research::research.finding-aid', [
                'collection' => $collection,
                'items' => $data,
                'researcher' => $researcher,
                'format' => 'pdf',
            ]);
        }

        // CSV fallback for DOCX (basic export)
        $filename = ($collection->name ?? 'finding-aid') . '.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        return response()->stream(function () use ($data, $collection) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Finding Aid: ' . $collection->name]);
            fputcsv($out, []);
            fputcsv($out, ['Identifier', 'Title', 'Level', 'Repository', 'Scope & Content', 'Extent', 'Access Conditions', 'Notes']);
            foreach ($data as $item) {
                fputcsv($out, [
                    $item->identifier, $item->title, $item->level_of_description,
                    $item->repository_name, $item->scope_and_content, $item->extent_and_medium,
                    $item->access_conditions, $item->researcher_notes,
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Generate HTML finding aid (viewable in browser).
     */
    public function generateFindingAid(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collectionId = (int) $request->input('id');
        $collection = $this->service->getCollection($collectionId);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Collection not found');
        }

        $data = $this->getCollectionFindingAidData($collectionId);

        return view('research::research.finding-aid', [
            'collection' => $collection,
            'items' => $data,
            'researcher' => $researcher,
            'format' => 'html',
        ]);
    }

    /**
     * Get enriched collection items for finding aid export.
     */
    private function getCollectionFindingAidData(int $collectionId): array
    {
        $culture = app()->getLocale();

        return DB::table('research_collection_item as ci')
            ->join('information_object as io', 'ci.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as lod', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'lod.id')->where('lod.culture', '=', $culture);
            })
            ->leftJoin('actor_i18n as repo', function ($j) use ($culture) {
                $j->on('io.repository_id', '=', 'repo.id')->where('repo.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->where('ci.collection_id', $collectionId)
            ->select(
                'io.id', 'io.identifier', 's.slug',
                'ioi.title', 'ioi.scope_and_content', 'ioi.extent_and_medium',
                'ioi.archival_history', 'ioi.arrangement', 'ioi.access_conditions',
                'ioi.reproduction_conditions', 'ioi.physical_characteristics',
                'lod.name as level_of_description',
                'repo.authorized_form_of_name as repository_name',
                'ci.notes as researcher_notes', 'ci.created_at'
            )
            ->orderBy('ci.created_at')
            ->get()->toArray();
    }
}
