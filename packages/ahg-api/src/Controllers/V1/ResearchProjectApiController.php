<?php

/**
 * ResearchProjectApiController - #1255
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed under AGPL-3.0.
 *
 * REST API v1 for research projects (research_project). Research data is not
 * part of the public catalogue, so every endpoint is gated behind an
 * authenticated key: reads require the `read` scope, writes `write`, deletes
 * `delete` (mirrors the v2 read-auth posture, not the public catalogue reads).
 * Response envelope, pagination, sort and 404 shape mirror the v1
 * DonorApiController / AccessionApiController exactly.
 */

namespace AhgApi\Controllers\V1;

use AhgResearch\Events\ProjectClosed;
use AhgResearch\Events\ProjectCreated;
use AhgResearch\Events\ProjectUpdated;
use AhgResearch\Services\ResearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResearchProjectApiController extends Controller
{
    /**
     * Project status codes that represent a closed / completed lifecycle state.
     * Drawn from the project status set used across the research portal
     * (planning, active, on_hold, completed, archived - see
     * research/edit-project.blade.php). Transitioning into either of these
     * fires a ProjectClosed lifecycle event in addition to ProjectUpdated.
     */
    private const CLOSED_STATUSES = ['completed', 'archived'];

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/v1/research-projects
     * Paginated list with optional `q` (title/description) and `owner`
     * (researcher id) filters.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $sort = $request->get('sort', 'updated');
        $sortDir = strtolower($request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('research_project');

        if (($q = trim((string) $request->get('q', ''))) !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', '%' . $q . '%')
                    ->orWhere('description', 'like', '%' . $q . '%');
            });
        }

        if (($owner = $request->get('owner')) !== null && $owner !== '') {
            $query->where('owner_id', (int) $owner);
        }

        $orderCol = match ($sort) {
            'alphabetic', 'title' => 'title',
            'status' => 'status',
            'created' => 'created_at',
            default => 'updated_at',
        };

        $total = $query->count();
        $results = $query
            ->select(
                'id', 'owner_id', 'title', 'description', 'project_type',
                'institution', 'status', 'visibility',
                'start_date', 'expected_end_date', 'actual_end_date',
                'created_at', 'updated_at'
            )
            ->orderBy($orderCol, $sortDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json([
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'results' => $results,
        ]);
    }

    /**
     * GET /api/v1/research-projects/{id}
     */
    public function show(int $id): JsonResponse
    {
        $project = DB::table('research_project')->where('id', $id)->first();

        if (! $project) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Owner researcher summary (owner_id is research_researcher.id).
        $project->owner = DB::table('research_researcher')
            ->where('id', $project->owner_id)
            ->select('id', 'user_id', 'first_name', 'last_name', 'email', 'institution')
            ->first();

        return response()->json($project);
    }

    /**
     * POST /api/v1/research-projects
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'title' => 'required|string|max:500',
                'description' => 'nullable|string',
                'project_type' => 'nullable|string|max:103',
                'institution' => 'nullable|string|max:255',
                'supervisor' => 'nullable|string|max:255',
                'funding_source' => 'nullable|string|max:255',
                'grant_number' => 'nullable|string|max:100',
                'ethics_approval' => 'nullable|string|max:100',
                'start_date' => 'nullable|date',
                'expected_end_date' => 'nullable|date',
                'actual_end_date' => 'nullable|date',
                'status' => 'nullable|string|max:52',
                'visibility' => 'nullable|string|max:38',
                'owner_id' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        }

        // Resolve the owning researcher. Prefer an explicit owner_id; otherwise
        // derive it from the authenticated user via the research_researcher map.
        $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : null;
        if (! $ownerId) {
            $apiUserId = (int) $request->attributes->get('api_user_id');
            if ($apiUserId) {
                $researcher = $this->service->getResearcherByUserId($apiUserId);
                $ownerId = $researcher->id ?? null;
            }
        }

        if (! $ownerId) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => ['owner_id' => ['Could not resolve an owning researcher; supply owner_id.']],
            ], 422);
        }

        $now = date('Y-m-d H:i:s');
        $id = DB::table('research_project')->insertGetId([
            'owner_id' => $ownerId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'project_type' => $data['project_type'] ?? 'personal',
            'institution' => $data['institution'] ?? null,
            'supervisor' => $data['supervisor'] ?? null,
            'funding_source' => $data['funding_source'] ?? null,
            'grant_number' => $data['grant_number'] ?? null,
            'ethics_approval' => $data['ethics_approval'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'expected_end_date' => $data['expected_end_date'] ?? null,
            'actual_end_date' => $data['actual_end_date'] ?? null,
            'status' => $data['status'] ?? 'planning',
            'visibility' => $data['visibility'] ?? 'private',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Mirror the web flow: register the owner as an accepted collaborator.
        DB::table('research_project_collaborator')->insert([
            'project_id' => $id,
            'researcher_id' => $ownerId,
            'role' => 'owner',
            'status' => 'accepted',
            'invited_at' => $now,
            'accepted_at' => $now,
        ]);

        $project = DB::table('research_project')->where('id', $id)->first();

        // #1254 - research lifecycle event. NOTE: the legacy web ResearchController
        // project create/update path is LOCKED (packages/ahg-research/) and is a
        // separate follow-up; emit from this (unlocked) API chokepoint for now.
        event(new ProjectCreated((int) $id, $ownerId));

        return response()->json($project, 201);
    }

    /**
     * PUT /api/v1/research-projects/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $project = DB::table('research_project')->where('id', $id)->first();

        if (! $project) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $data = $request->validate([
                'title' => 'sometimes|required|string|max:500',
                'description' => 'nullable|string',
                'project_type' => 'nullable|string|max:103',
                'institution' => 'nullable|string|max:255',
                'supervisor' => 'nullable|string|max:255',
                'funding_source' => 'nullable|string|max:255',
                'grant_number' => 'nullable|string|max:100',
                'ethics_approval' => 'nullable|string|max:100',
                'start_date' => 'nullable|date',
                'expected_end_date' => 'nullable|date',
                'actual_end_date' => 'nullable|date',
                'status' => 'nullable|string|max:52',
                'visibility' => 'nullable|string|max:38',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        }

        $allowed = [
            'title', 'description', 'project_type', 'institution', 'supervisor',
            'funding_source', 'grant_number', 'ethics_approval', 'start_date',
            'expected_end_date', 'actual_end_date', 'status', 'visibility',
        ];
        $update = array_intersect_key($data, array_flip($allowed));

        $previousStatus = (string) ($project->status ?? '');
        $ownerId = isset($project->owner_id) ? (int) $project->owner_id : null;

        if (! empty($update)) {
            $update['updated_at'] = date('Y-m-d H:i:s');
            DB::table('research_project')->where('id', $id)->update($update);

            // #1254 - research lifecycle events. NOTE: the legacy web
            // ResearchController project update path is LOCKED
            // (packages/ahg-research/) and is a separate follow-up; emit from
            // this (unlocked) API chokepoint for now.
            event(new ProjectUpdated((int) $id, $ownerId));

            // If this update moved the project into a closed/completed state
            // (and it was not already there), also fire ProjectClosed.
            $newStatus = array_key_exists('status', $update) ? (string) $update['status'] : $previousStatus;
            if (in_array($newStatus, self::CLOSED_STATUSES, true)
                && ! in_array($previousStatus, self::CLOSED_STATUSES, true)) {
                event(new ProjectClosed((int) $id, $ownerId));
            }
        }

        $project = DB::table('research_project')->where('id', $id)->first();

        return response()->json($project);
    }

    /**
     * DELETE /api/v1/research-projects/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $project = DB::table('research_project')->where('id', $id)->first();

        if (! $project) {
            return response()->json(['error' => 'Not found'], 404);
        }

        DB::table('research_project_collaborator')->where('project_id', $id)->delete();
        DB::table('research_project')->where('id', $id)->delete();

        return response()->json(['deleted' => true, 'id' => $id]);
    }
}
