<?php

/**
 * ResearchAnnotationsController - Controller for Heratio
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
 * ResearchAnnotationsController - Researcher notes / annotations for the research portal.
 *
 * Extracted from ResearchController as stage 2 of the monolith decomposition
 * (issue #1253), mirroring stage 1 (ResearchReproductionsController). Handles
 * the researcher-facing notes list/search/create/update/delete plus the
 * dedicated REST-ish store/update/destroy endpoints.
 */
class ResearchAnnotationsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function annotations(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post')) {
            $action = $request->input('do');

            if ($action === 'delete') {
                $this->service->deleteAnnotation((int) $request->input('id'), $researcher->id);
                return redirect()->route('research.annotations')->with('success', 'Note deleted');
            }

            if ($action === 'create') {
                $content = trim($request->input('content'));
                $validEntityTypes = ['information_object', 'actor', 'repository', 'accession', 'term'];
                $entityType = $request->input('entity_type', 'information_object');
                $visibility = $request->input('visibility', 'private');
                $contentFormat = $request->input('content_format', 'text');

                if ($content) {
                    DB::table('research_annotation')->insert([
                        'researcher_id' => $researcher->id,
                        'object_id' => ((int) $request->input('object_id')) ?: null,
                        'entity_type' => in_array($entityType, $validEntityTypes) ? $entityType : 'information_object',
                        'collection_id' => ((int) $request->input('collection_id')) ?: null,
                        'title' => trim($request->input('title')),
                        'content' => $content,
                        'tags' => trim($request->input('tags', '')) ?: null,
                        'content_format' => in_array($contentFormat, ['text', 'html']) ? $contentFormat : 'text',
                        'visibility' => in_array($visibility, ['private', 'shared', 'public']) ? $visibility : 'private',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    return redirect()->route('research.annotations')->with('success', 'Note created');
                }
            }

            if ($action === 'update') {
                $id = (int) $request->input('id');
                $content = trim($request->input('content'));
                $validEntityTypes = ['information_object', 'actor', 'repository', 'accession', 'term'];
                $entityType = $request->input('entity_type', 'information_object');
                $visibility = $request->input('visibility', 'private');
                $contentFormat = $request->input('content_format', 'text');

                if ($content) {
                    DB::table('research_annotation')
                        ->where('id', $id)
                        ->where('researcher_id', $researcher->id)
                        ->update([
                            'title' => trim($request->input('title')),
                            'content' => $content,
                            'object_id' => ((int) $request->input('object_id')) ?: null,
                            'entity_type' => in_array($entityType, $validEntityTypes) ? $entityType : 'information_object',
                            'collection_id' => ((int) $request->input('collection_id')) ?: null,
                            'tags' => trim($request->input('tags', '')) ?: null,
                            'content_format' => in_array($contentFormat, ['text', 'html']) ? $contentFormat : 'text',
                            'visibility' => in_array($visibility, ['private', 'shared', 'public']) ? $visibility : 'private',
                        ]);
                    return redirect()->route('research.annotations')->with('success', 'Note updated');
                }
            }
        }

        $q = $request->input('q');
        $visibility = $request->input('visibility');
        $tag = $request->input('tag');

        $annotations = $q
            ? $this->service->searchAnnotations($researcher->id, $q)
            : $this->service->getAnnotations($researcher->id);

        if ($visibility) {
            $annotations = array_filter($annotations, fn($a) => ($a->visibility ?? 'private') === $visibility);
        }
        if ($tag) {
            $annotations = array_filter($annotations, function ($a) use ($tag) {
                if (empty($a->tags)) return false;
                $tags = array_map('trim', explode(',', $a->tags));
                return in_array($tag, $tags);
            });
        }
        $annotations = array_values($annotations);

        $researchCollections = DB::table('research_collection')
            ->where('researcher_id', $researcher->id)
            ->orderBy('name')->get();

        return view('research::research.annotations', array_merge(
            $this->getSidebarData('annotations'),
            compact('researcher', 'annotations', 'researchCollections')
        ));
    }

    public function storeAnnotation(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $content = trim($request->input('content'));
        $validEntityTypes = ['information_object', 'actor', 'repository', 'accession', 'term'];
        $entityType = $request->input('entity_type', 'information_object');
        $visibility = $request->input('visibility', 'private');
        $contentFormat = $request->input('content_format', 'text');

        if ($content) {
            DB::table('research_annotation')->insert([
                'researcher_id' => $researcher->id,
                'object_id' => ((int) $request->input('object_id')) ?: null,
                'entity_type' => in_array($entityType, $validEntityTypes) ? $entityType : 'information_object',
                'collection_id' => ((int) $request->input('collection_id')) ?: null,
                'title' => trim($request->input('title')),
                'content' => $content,
                'tags' => trim($request->input('tags', '')) ?: null,
                'content_format' => in_array($contentFormat, ['text', 'html']) ? $contentFormat : 'text',
                'visibility' => in_array($visibility, ['private', 'shared', 'public']) ? $visibility : 'private',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return redirect()->route('research.annotations')->with('success', 'Note created');
        }

        return redirect()->route('research.annotations')->with('error', 'Content is required');
    }

    public function updateAnnotation(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $content = trim($request->input('content'));
        $validEntityTypes = ['information_object', 'actor', 'repository', 'accession', 'term'];
        $entityType = $request->input('entity_type', 'information_object');
        $visibility = $request->input('visibility', 'private');
        $contentFormat = $request->input('content_format', 'text');

        if ($content) {
            DB::table('research_annotation')
                ->where('id', $id)
                ->where('researcher_id', $researcher->id)
                ->update([
                    'title' => trim($request->input('title')),
                    'content' => $content,
                    'object_id' => ((int) $request->input('object_id')) ?: null,
                    'entity_type' => in_array($entityType, $validEntityTypes) ? $entityType : 'information_object',
                    'collection_id' => ((int) $request->input('collection_id')) ?: null,
                    'tags' => trim($request->input('tags', '')) ?: null,
                    'content_format' => in_array($contentFormat, ['text', 'html']) ? $contentFormat : 'text',
                    'visibility' => in_array($visibility, ['private', 'shared', 'public']) ? $visibility : 'private',
                ]);
            return redirect()->route('research.annotations')->with('success', 'Note updated');
        }

        return redirect()->route('research.annotations')->with('error', 'Content is required');
    }

    public function destroyAnnotation(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $this->service->deleteAnnotation($id, $researcher->id);

        return redirect()->route('research.annotations')->with('success', 'Note deleted');
    }
}
