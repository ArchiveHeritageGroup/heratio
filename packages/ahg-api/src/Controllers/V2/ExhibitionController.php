<?php

/**
 * V2 ExhibitionController - #1280
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed under AGPL-3.0.
 *
 * REST API v2 READ surface for exhibition spaces + placements, using the standard
 * v2 envelope (success / data / meta / links via BaseApiController). Writes live on
 * the v1 resource (ExhibitionApiController); this is the read-auth v2 mirror, like
 * the other v2 read controllers. Thin over AhgExhibition\Services\ExhibitionSpaceService.
 */

namespace AhgApi\Controllers\V2;

use AhgExhibition\Services\ExhibitionSpaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExhibitionController extends BaseApiController
{
    protected ExhibitionSpaceService $service;

    public function __construct(ExhibitionSpaceService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * GET /api/v2/exhibitions
     */
    public function index(Request $request): JsonResponse
    {
        $p = $this->paginationParams($request);

        $query = DB::table('ahg_exhibition_space');
        if (($q = trim((string) $request->get('q', ''))) !== '') {
            $query->where('name', 'like', '%' . $q . '%');
        }
        if (($type = trim((string) $request->get('space_type', ''))) !== '') {
            $query->where('space_type', $type);
        }

        $orderCol = match ($p['sort']) {
            'alphabetic', 'name' => 'name',
            'type', 'space_type' => 'space_type',
            'created' => 'created_at',
            default => 'updated_at',
        };

        $total = $query->count();
        $rows = $query
            ->select(
                'id', 'slug', 'name', 'space_type', 'building', 'floor',
                'capacity_value', 'capacity_unit', 'created_at', 'updated_at'
            )
            ->orderBy($orderCol, $p['sortDir'])
            ->offset(($p['page'] - 1) * $p['limit'])
            ->limit($p['limit'])
            ->get();

        return $this->paginated($rows, $total, $p['page'], $p['limit'], '/api/v2/exhibitions');
    }

    /**
     * GET /api/v2/exhibitions/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return $this->error('not_found', 'Exhibition not found.', 404);
        }
        $space->placements = $this->service->getPlacements((int) $space->id);

        return $this->success($space);
    }
}
