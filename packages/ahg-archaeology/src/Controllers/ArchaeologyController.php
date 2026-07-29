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

    // ─── Stratigraphic contexts (layers) - #1428 Phase 1 ────────────────────────

    /** Contexts recorded for a site (the "Stratigraphy" list). */
    public function contexts(int $siteId): Response
    {
        $site = $this->service->site($siteId);
        if (! $site) {
            abort(404);
        }

        return response()->view('ahg-archaeology::contexts', [
            'site'     => $site,
            'contexts' => $this->service->contextsForSite($siteId),
        ]);
    }

    /** A single context sheet: its fields, drawings link and finds. */
    public function context(int $id): Response
    {
        $context = $this->service->context($id);
        if (! $context) {
            abort(404);
        }

        return response()->view('ahg-archaeology::context-view', [
            'context' => $context,
        ]);
    }

    /** Create-context form (needs ?site_id=). */
    public function contextCreate(Request $request): Response
    {
        $siteId = (int) $request->query('site_id');
        $site = $this->service->site($siteId);
        if (! $site) {
            abort(404);
        }

        return response()->view('ahg-archaeology::context-form', [
            'site'    => $site,
            'context' => null,
            'types'   => $this->service->vocabulary('context_type'),
            'phases'  => $this->service->vocabulary('context_phase'),
        ]);
    }

    /** Edit-context form. */
    public function contextEdit(int $id): Response
    {
        $context = $this->service->context($id);
        if (! $context) {
            abort(404);
        }

        return response()->view('ahg-archaeology::context-form', [
            'site'    => $context->site,
            'context' => $context,
            'types'   => $this->service->vocabulary('context_type'),
            'phases'  => $this->service->vocabulary('context_phase'),
        ]);
    }

    /** Persist a new or edited context. */
    public function contextSave(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'site_id'              => 'required|integer',
            'context_number'       => 'required|string|max:50',
            'context_type_id'      => 'nullable|integer',
            'phase_id'             => 'nullable|integer',
            'description'          => 'nullable|string',
            'interpretation'       => 'nullable|string',
            'top_elevation_m'      => 'nullable|numeric',
            'bottom_elevation_m'   => 'nullable|numeric',
            'excavation_reference' => 'nullable|string|max:100',
            'excavator'            => 'nullable|string|max:255',
            'excavation_date'      => 'nullable|date',
            'date_earliest'        => 'nullable|string|max:50',
            'date_latest'          => 'nullable|string|max:50',
            'dating_note'          => 'nullable|string',
        ]);

        $contextId = $this->service->saveContext($data, $id);

        return redirect()
            ->to(url('/archaeology/context/'.$contextId))
            ->with('status', __('Context saved.'));
    }
}
