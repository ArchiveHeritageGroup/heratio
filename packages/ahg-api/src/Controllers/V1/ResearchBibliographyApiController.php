<?php

/**
 * ResearchBibliographyApiController - #1255
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed under AGPL-3.0.
 *
 * REST API v1 for research bibliographies (research_bibliography) and their
 * entries (research_bibliography_entry). Research data is not part of the
 * public catalogue, so every endpoint is gated behind an authenticated key:
 * reads require the `read` scope, writes `write`, deletes `delete` (mirrors
 * the v2 read-auth posture, not the public catalogue reads). Response
 * envelope, pagination, sort and 404 shape mirror the v1 DonorApiController /
 * AccessionApiController / ResearchProjectApiController exactly. Writes go
 * through BibliographyService where a method exists.
 */

namespace AhgApi\Controllers\V1;

use AhgResearch\Services\BibliographyService;
use AhgResearch\Services\ResearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResearchBibliographyApiController extends Controller
{
    protected BibliographyService $service;

    protected ResearchService $research;

    public function __construct(BibliographyService $service, ResearchService $research)
    {
        $this->service = $service;
        $this->research = $research;
    }

    /**
     * GET /api/v1/research-bibliographies
     * Paginated list with optional `q` (name/description), `project`
     * (project id) and `researcher` (researcher id) filters.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $sort = $request->get('sort', 'updated');
        $sortDir = strtolower($request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('research_bibliography as b');

        if (($q = trim((string) $request->get('q', ''))) !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('b.name', 'like', '%' . $q . '%')
                    ->orWhere('b.description', 'like', '%' . $q . '%');
            });
        }

        if (($project = $request->get('project')) !== null && $project !== '') {
            $query->where('b.project_id', (int) $project);
        }

        if (($researcher = $request->get('researcher')) !== null && $researcher !== '') {
            $query->where('b.researcher_id', (int) $researcher);
        }

        $orderCol = match ($sort) {
            'alphabetic', 'name' => 'b.name',
            'created' => 'b.created_at',
            default => 'b.updated_at',
        };

        $total = $query->count();
        $results = $query
            ->select(
                'b.id', 'b.researcher_id', 'b.project_id', 'b.name',
                'b.description', 'b.citation_style', 'b.is_public',
                'b.created_at', 'b.updated_at'
            )
            ->orderBy($orderCol, $sortDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        // Annotate each row with its entry count (cheap, matches the service).
        foreach ($results as $bib) {
            $bib->entry_count = DB::table('research_bibliography_entry')
                ->where('bibliography_id', $bib->id)
                ->count();
        }

        return response()->json([
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'results' => $results,
        ]);
    }

    /**
     * GET /api/v1/research-bibliographies/{id}
     * Returns the bibliography with its entries (via BibliographyService).
     */
    public function show(int $id): JsonResponse
    {
        $bibliography = $this->service->getBibliography($id);

        if (! $bibliography) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($bibliography);
    }

    /**
     * POST /api/v1/research-bibliographies
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'citation_style' => 'nullable|string|max:50',
                'is_public' => 'nullable|boolean',
                'project_id' => 'nullable|integer',
                'researcher_id' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        }

        // Resolve the owning researcher. Prefer an explicit researcher_id;
        // otherwise derive it from the authenticated user via the
        // research_researcher map.
        $researcherId = isset($data['researcher_id']) ? (int) $data['researcher_id'] : null;
        if (! $researcherId) {
            $apiUserId = (int) $request->attributes->get('api_user_id');
            if ($apiUserId) {
                $researcher = $this->research->getResearcherByUserId($apiUserId);
                $researcherId = $researcher->id ?? null;
            }
        }

        if (! $researcherId) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => ['researcher_id' => ['Could not resolve an owning researcher; supply researcher_id.']],
            ], 422);
        }

        $id = $this->service->createBibliography($researcherId, [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'citation_style' => $data['citation_style'] ?? 'chicago',
            'is_public' => isset($data['is_public']) ? (int) $data['is_public'] : 0,
            'project_id' => $data['project_id'] ?? null,
        ]);

        $bibliography = $this->service->getBibliography($id);

        return response()->json($bibliography, 201);
    }

    /**
     * PUT /api/v1/research-bibliographies/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $bibliography = DB::table('research_bibliography')->where('id', $id)->first();

        if (! $bibliography) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $data = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'citation_style' => 'nullable|string|max:50',
                'is_public' => 'nullable|boolean',
                'project_id' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        }

        if (isset($data['is_public'])) {
            $data['is_public'] = (int) $data['is_public'];
        }

        // updateBibliography whitelists name/description/citation_style/is_public/project_id.
        $this->service->updateBibliography($id, $data);

        return response()->json($this->service->getBibliography($id));
    }

    /**
     * DELETE /api/v1/research-bibliographies/{id}
     * Cascades to entries (handled inside the service).
     */
    public function destroy(int $id): JsonResponse
    {
        $bibliography = DB::table('research_bibliography')->where('id', $id)->first();

        if (! $bibliography) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $this->service->deleteBibliography($id);

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    // =========================================================================
    // NESTED ENTRY CRUD
    // =========================================================================

    /**
     * GET /api/v1/research-bibliographies/{id}/entries
     */
    public function entries(int $id): JsonResponse
    {
        $bibliography = DB::table('research_bibliography')->where('id', $id)->first();

        if (! $bibliography) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $entries = $this->service->getEntries($id);

        return response()->json([
            'total' => count($entries),
            'results' => $entries,
        ]);
    }

    /**
     * POST /api/v1/research-bibliographies/{id}/entries
     */
    public function storeEntry(Request $request, int $id): JsonResponse
    {
        $bibliography = DB::table('research_bibliography')->where('id', $id)->first();

        if (! $bibliography) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $data = $request->validate([
                'object_id' => 'nullable|integer',
                'entry_type' => 'nullable|string|max:60',
                'title' => 'nullable|string|max:500',
                'authors' => 'nullable|string',
                'date' => 'nullable|string|max:50',
                'publisher' => 'nullable|string|max:255',
                'container_title' => 'nullable|string|max:500',
                'volume' => 'nullable|string|max:50',
                'issue' => 'nullable|string|max:50',
                'pages' => 'nullable|string|max:50',
                'doi' => 'nullable|string|max:255',
                'url' => 'nullable|string|max:1000',
                'accessed_date' => 'nullable|date',
                'archive_name' => 'nullable|string|max:255',
                'archive_location' => 'nullable|string|max:255',
                'collection_title' => 'nullable|string|max:500',
                'box' => 'nullable|string|max:50',
                'folder' => 'nullable|string|max:50',
                'notes' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        }

        // An entry must have at least a title or a linked object.
        if (empty($data['title']) && empty($data['object_id'])) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => ['title' => ['An entry requires a title or an object_id.']],
            ], 422);
        }

        $entryId = $this->service->addEntry($id, $data);

        $entry = DB::table('research_bibliography_entry')->where('id', $entryId)->first();

        return response()->json($entry, 201);
    }

    /**
     * PUT /api/v1/research-bibliographies/{id}/entries/{entryId}
     */
    public function updateEntry(Request $request, int $id, int $entryId): JsonResponse
    {
        $entry = DB::table('research_bibliography_entry')
            ->where('id', $entryId)
            ->where('bibliography_id', $id)
            ->first();

        if (! $entry) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $data = $request->validate([
                'entry_type' => 'nullable|string|max:60',
                'title' => 'nullable|string|max:500',
                'authors' => 'nullable|string',
                'date' => 'nullable|string|max:50',
                'publisher' => 'nullable|string|max:255',
                'container_title' => 'nullable|string|max:500',
                'volume' => 'nullable|string|max:50',
                'issue' => 'nullable|string|max:50',
                'pages' => 'nullable|string|max:50',
                'doi' => 'nullable|string|max:255',
                'url' => 'nullable|string|max:1000',
                'accessed_date' => 'nullable|date',
                'archive_name' => 'nullable|string|max:255',
                'archive_location' => 'nullable|string|max:255',
                'collection_title' => 'nullable|string|max:500',
                'box' => 'nullable|string|max:50',
                'folder' => 'nullable|string|max:50',
                'notes' => 'nullable|string',
                'sort_order' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        }

        $this->service->updateEntry($entryId, $data);

        $entry = DB::table('research_bibliography_entry')->where('id', $entryId)->first();

        return response()->json($entry);
    }

    /**
     * DELETE /api/v1/research-bibliographies/{id}/entries/{entryId}
     */
    public function destroyEntry(int $id, int $entryId): JsonResponse
    {
        $entry = DB::table('research_bibliography_entry')
            ->where('id', $entryId)
            ->where('bibliography_id', $id)
            ->first();

        if (! $entry) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $this->service->removeEntry($entryId);

        return response()->json(['deleted' => true, 'id' => $entryId]);
    }
}
