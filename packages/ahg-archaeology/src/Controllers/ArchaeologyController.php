<?php

/**
 * ArchaeologyController — collections management UI for archaeological
 * sites and finds.
 *
 * Read and browse only at this stage. Creating and editing records goes through
 * the normal descriptive-record workflow, because a site or find is an
 * information_object first and an archaeology row second.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgArchaeology\Controllers;

use AhgArchaeology\Services\ArchaeologyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class ArchaeologyController extends Controller
{
    public function __construct(protected ArchaeologyService $service)
    {
    }

    public function index(): Response
    {
        return response()->view('ahg-archaeology::index', [
            'stats'     => $this->service->statistics(),
            'byPeriod'  => $this->service->breakdown('archaeology_object', 'period_id'),
            'byMaterial'=> $this->service->breakdown('archaeology_object', 'material_id'),
            'bySiteType'=> $this->service->breakdown('archaeology_site', 'site_type_id'),
        ]);
    }

    public function sites(Request $request): Response
    {
        $filters = $request->only(['period_id', 'site_type_id', 'region', 'excavated', 'q']);

        return response()->view('ahg-archaeology::sites', [
            'sites'   => $this->service->sites($filters),
            'vocab'   => $this->service->vocabularies(),
            'filters' => $filters,
        ]);
    }

    public function site(int $id): Response
    {
        $site = $this->service->site($id);
        if (! $site) {
            abort(404);
        }

        return response()->view('ahg-archaeology::site-view', [
            'site'       => $site,
            'assemblage' => $this->service->siteAssemblage($id),
            'finds'      => $this->service->objects(['site_id' => $id], 25),
        ]);
    }

    public function objects(Request $request): Response
    {
        $filters = $request->only(['site_id', 'material_id', 'object_type_id', 'period_id', 'q']);

        return response()->view('ahg-archaeology::objects', [
            'objects' => $this->service->objects($filters),
            'vocab'   => $this->service->vocabularies(),
            'filters' => $filters,
        ]);
    }

    public function object(int $id): Response
    {
        $object = $this->service->object($id);
        if (! $object) {
            abort(404);
        }

        return response()->view('ahg-archaeology::object-view', [
            'object' => $object,
        ]);
    }
}
