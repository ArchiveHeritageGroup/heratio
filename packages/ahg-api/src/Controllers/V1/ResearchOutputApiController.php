<?php

/**
 * ResearchOutputApiController - Heratio ahg-api (v1)
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

namespace AhgApi\Controllers\V1;

use AhgResearch\Events\OutputPublished;
use AhgResearch\Services\ResearchOutputService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * heratio#1255 - REST API for Research Outputs (the CRIS / RIM register,
 * epic #1222). A research project's scholarly outputs - journal articles,
 * datasets, software, presentations, theses, reports, chapters - each carrying
 * a persistent identifier (DOI / handle / ISBN / URL) resolved to a citable
 * link.
 *
 * Mirrors the v1 catalogue controllers (DonorApiController /
 * AccessionApiController): the same {total,page,limit,results} envelope,
 * page/limit pagination, sort handling and 404 shape. Unlike the catalogue
 * entities a research_output has no slug, so the REST resource is keyed by its
 * numeric id; reads + writes flow through ResearchOutputService (the same
 * service the research portal UI uses) wherever a method exists.
 *
 * Scopes: read endpoints require api.auth:read, writes api.auth:write, delete
 * api.auth:delete (wired in routes/api.php).
 */
class ResearchOutputApiController extends Controller
{
    public function __construct(private ResearchOutputService $outputs)
    {
    }

    /**
     * GET /api/v1/research-outputs
     *
     * Paginated list with optional project_id / output_type / status filters.
     * Uses the same envelope as the catalogue v1 controllers.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $sort = (string) $request->get('sort', 'date');
        $sortDir = strtolower((string) $request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('research_output');

        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->get('project_id'));
        }
        if ($request->filled('output_type')) {
            $query->where('output_type', (string) $request->get('output_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->get('status'));
        }

        $orderCol = match ($sort) {
            'title' => 'title',
            'type' => 'output_type',
            'status' => 'status',
            'created' => 'created_at',
            default => 'output_date',
        };

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy($orderCol, $sortDir)
            ->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $results = $rows->map(fn ($row) => $this->present((array) $row))->all();

        return response()->json([
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'results' => $results,
        ]);
    }

    /**
     * GET /api/v1/research-outputs/{id}
     */
    public function show(int $id): JsonResponse
    {
        $output = $this->outputs->getOutput($id);

        if ($output === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($this->present($output));
    }

    /**
     * POST /api/v1/research-outputs
     *
     * Create an output for a project. output_type / identifier_type / status are
     * validated against their ahg_dropdown taxonomies (never a hardcoded list).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $projectId = (int) $data['project_id'];
        if (! DB::table('research_project')->where('id', $projectId)->exists()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => ['project_id' => ['The selected project does not exist.']],
            ], 422);
        }

        $researcherId = $request->filled('owner_id') ? (int) $request->get('owner_id') : null;

        $id = $this->outputs->createOutput($projectId, $researcherId, $data);

        if ($id === null) {
            return response()->json(['error' => 'Could not create research output'], 500);
        }

        $output = $this->outputs->getOutput($id);

        // #1254 - research lifecycle event. An output created directly in the
        // 'published' state fires OutputPublished. NOTE: the legacy web research
        // output path is LOCKED (packages/ahg-research/) and is a separate
        // follow-up; emit from this (unlocked) API chokepoint for now.
        if ($this->isPublished($output)) {
            event(new OutputPublished((int) $id, $projectId));
        }

        return response()->json($this->present($output ?? []), 201);
    }

    /**
     * PUT/PATCH /api/v1/research-outputs/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $existing = $this->outputs->getOutput($id);
        if ($existing === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Updates are scoped to the row's own project; project_id is not movable
        // via the API (mirrors the service's project-scoped update signature).
        $projectId = (int) $existing['project_id'];
        $wasPublished = $this->isPublished($existing);
        $data = $this->validatePayload($request, $projectId);

        $ok = $this->outputs->updateOutput($id, $projectId, $data);
        if (! $ok) {
            return response()->json(['error' => 'Could not update research output'], 500);
        }

        $output = $this->outputs->getOutput($id);

        // #1254 - research lifecycle event. Fire OutputPublished only on the
        // transition INTO the 'published' state. NOTE: the legacy web research
        // output path is LOCKED (packages/ahg-research/) and is a separate
        // follow-up; emit from this (unlocked) API chokepoint for now.
        if ($this->isPublished($output) && ! $wasPublished) {
            event(new OutputPublished((int) $id, $projectId));
        }

        return response()->json($this->present($output ?? []));
    }

    /**
     * Whether a stored output row sits in the 'published' status.
     *
     * @param  array<string,mixed>|null  $output
     */
    private function isPublished(?array $output): bool
    {
        return $output !== null && (string) ($output['status'] ?? '') === 'published';
    }

    /**
     * DELETE /api/v1/research-outputs/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $existing = $this->outputs->getOutput($id);
        if ($existing === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $ok = $this->outputs->deleteOutput($id, (int) $existing['project_id']);
        if (! $ok) {
            return response()->json(['error' => 'Could not delete research output'], 500);
        }

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Validate the create/update payload. output_type, identifier_type and
     * status are constrained to their live dropdown option codes; the service's
     * normalise() defends a second time, but the API rejects bad values up front
     * with a 422 rather than silently coercing.
     *
     * On update ($scopedProject set) the project_id is not required - the row
     * stays on its own project.
     *
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request, ?int $scopedProject = null): array
    {
        $typeCodes = array_keys($this->outputs->typeOptions());
        $idTypeCodes = array_keys($this->outputs->identifierTypeOptions());
        $statusCodes = array_keys($this->outputs->statusOptions());

        $rules = [
            'title' => 'required|string|max:512',
            'output_type' => ['required', 'string', Rule::in($typeCodes)],
            'authors' => 'nullable|string|max:1024',
            'venue' => 'nullable|string|max:512',
            'identifier_type' => ['nullable', 'string', Rule::in($idTypeCodes)],
            'identifier' => 'nullable|string|max:512',
            'identifier_url' => 'nullable|string|max:1024',
            'output_date' => 'nullable|date',
            'status' => ['nullable', 'string', Rule::in($statusCodes)],
            'notes' => 'nullable|string',
            'dmp_id' => 'nullable|integer',
            'owner_id' => 'nullable|integer',
        ];

        if ($scopedProject === null) {
            $rules['project_id'] = 'required|integer';
        }

        $validated = $request->validate($rules);

        if ($scopedProject !== null) {
            $validated['project_id'] = $scopedProject;
        }

        return $validated;
    }

    /**
     * Decorate a stored output row with the resolved identifier URL plus the
     * human labels for its dropdown-backed codes.
     *
     * @param  array<string,mixed>  $output
     * @return array<string,mixed>
     */
    private function present(array $output): array
    {
        if ($output === []) {
            return $output;
        }

        $typeLabels = $this->outputs->typeOptions();
        $idTypeLabels = $this->outputs->identifierTypeOptions();
        $statusLabels = $this->outputs->statusOptions();

        $type = (string) ($output['output_type'] ?? '');
        $idType = (string) ($output['identifier_type'] ?? '');
        $status = (string) ($output['status'] ?? '');

        $output['type_label'] = $typeLabels[$type] ?? $type;
        $output['identifier_type_label'] = $idType !== '' ? ($idTypeLabels[$idType] ?? $idType) : '';
        $output['status_label'] = $statusLabels[$status] ?? $status;
        $output['url'] = $this->outputs->resolveUrl($output);

        return $output;
    }
}
