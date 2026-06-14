<?php

/**
 * ResearchEvidenceController - Controller for Heratio
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
 * ResearchEvidenceController - Evidence viewer + AJAX item search.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Both endpoints are auth-gated. No cross-calls to other
 * ResearchController methods existed - the methods used only DB queries,
 * view()/response() helpers and the auth()/app() facades, so the move is a
 * verbatim lift. No exclusive private helpers were required.
 */
class ResearchEvidenceController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function evidenceViewer(Request $request)
    {
        $objectId = (int) $request->input('object_id');
        $culture = app()->getLocale();

        // Get object info
        $source = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $objectId)
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->first();

        if (!$source) {
            abort(404);
        }

        // Get source assessment for type info
        $assessment = DB::table('research_source_assessment')
            ->where('object_id', $objectId)
            ->orderByDesc('assessed_at')
            ->first();

        $source->source_type = $assessment->source_type ?? null;
        $source->date = $assessment->assessed_at ?? null;

        // Get repository name
        $repo = DB::table('information_object as io2')
            ->join('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('ai.id', '=', 'io2.repository_id')->where('ai.culture', $culture);
            })
            ->where('io2.id', $objectId)
            ->select('ai.authorized_form_of_name as name')
            ->first();
        $source->repository = $repo->name ?? null;

        // Get digital object image
        $imageUrl = null;
        $do = DB::table('digital_object')->where('object_id', $objectId)->orderBy('usage_id')->first();
        if ($do) {
            if (str_starts_with($do->path ?? '', 'http')) {
                $imageUrl = $do->path;
            } else {
                $ref = DB::table('digital_object')->where('parent_id', $do->id)->where('usage_id', 141)->first();
                if ($ref) {
                    $imageUrl = rtrim($ref->path, '/') . '/' . $ref->name;
                } elseif (str_starts_with($do->mime_type ?? '', 'image/')) {
                    $imageUrl = rtrim($do->path, '/') . '/' . $do->name;
                }
            }
        }

        // Get annotations as notes
        $notes = '';
        $researcher = DB::table('research_researcher')->where('user_id', auth()->id())->first();
        if ($researcher) {
            $ann = DB::table('research_annotation')
                ->where('object_id', $objectId)
                ->where('researcher_id', $researcher->id)
                ->orderByDesc('created_at')
                ->first();
            $notes = $ann->content ?? '';
        }

        // Save notes via POST
        if ($request->isMethod('post') && $researcher) {
            DB::table('research_annotation')->updateOrInsert(
                ['object_id' => $objectId, 'researcher_id' => $researcher->id, 'entity_type' => 'information_object'],
                ['content' => $request->input('notes') ?: null, 'created_at' => now()]
            );
            return redirect()->route('research.evidence-viewer', ['object_id' => $objectId])->with('success', 'Notes saved.');
        }

        // Get tags from annotations
        $tags = DB::table('research_annotation')
            ->where('object_id', $objectId)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn($t) => explode(',', $t))
            ->map(fn($t) => trim($t))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return view('research::research.evidence-viewer', compact('source', 'imageUrl', 'notes', 'tags'));
    }

    // =========================================================================
    // AJAX: SEARCH ITEMS
    // =========================================================================

    public function searchItems(Request $request)
    {
        $query = trim($request->input('q', ''));
        if (strlen($query) < 2) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->where('io.id', '!=', 1)
            ->where(function ($q) use ($query) {
                $q->where('ioi.title', 'LIKE', '%' . $query . '%')
                  ->orWhere('io.identifier', 'LIKE', '%' . $query . '%');
            })
            ->select('io.id', 'io.identifier', 'ioi.title', 's.slug')
            ->orderBy('ioi.title')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                $hasChildren = DB::table('information_object')
                    ->where('parent_id', $item->id)->exists();
                return [
                    'id' => $item->id,
                    'title' => $item->title ?: 'Untitled [' . $item->id . ']',
                    'identifier' => $item->identifier,
                    'slug' => $item->slug,
                    'has_children' => $hasChildren,
                ];
            })
            ->toArray();

        return response()->json(['items' => $items]);
    }
}
