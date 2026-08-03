<?php

/**
 * ArchaeologyController - collections management UI for archaeological
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
use Illuminate\Support\Facades\DB;
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
            'matrix'   => $this->service->harrisMatrix($siteId),
        ]);
    }

    /** A single context sheet: its fields, drawings link, finds and stratigraphy. */
    public function context(int $id): Response
    {
        $context = $this->service->context($id);
        if (! $context) {
            abort(404);
        }

        return response()->view('ahg-archaeology::context-view', [
            'context'       => $context,
            'relationships' => $this->service->relationshipsForContext($id),
            'otherContexts' => $this->service->contextPickList((int) $context->site_id, $id),
            'relTypes'      => \AhgArchaeology\Services\ArchaeologyService::REL_TYPES,
        ]);
    }

    /** Add a stratigraphic relationship (mirror kept automatically). */
    public function relationshipStore(Request $request, int $contextId)
    {
        $data = $request->validate([
            'related_context_id' => 'required|integer',
            'relationship_type'  => 'required|string|max:20',
            'note'               => 'nullable|string|max:255',
        ]);

        $result = $this->service->addRelationship(
            $contextId,
            (int) $data['related_context_id'],
            $data['relationship_type'],
            $data['note'] ?? null
        );

        return redirect()->to(url('/archaeology/context/'.$contextId))
            ->with($result['ok'] ? 'status' : 'error', $result['ok'] ? __('Relationship added.') : $result['error']);
    }

    /** Remove a stratigraphic relationship (and its mirror). */
    public function relationshipDelete(int $contextId, int $relId)
    {
        $this->service->removeRelationship($relId);

        return redirect()->to(url('/archaeology/context/'.$contextId))
            ->with('status', __('Relationship removed.'));
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

    // ─── Site + find data-entry (the module's missing CRUD) - #1428 Phase 4 ──────

    public function siteCreate(): Response
    {
        return response()->view('ahg-archaeology::site-form', [
            'site'         => null,
            'vocab'        => $this->service->vocabularies(),
            'repositories' => $this->repositories(),
        ]);
    }

    public function siteEdit(int $id): Response
    {
        $site = DB::table('archaeology_site')->where('id', $id)->first();
        if (! $site) {
            abort(404);
        }
        $site->title = $site->information_object_id
            ? DB::table('information_object_i18n')->where('id', $site->information_object_id)->where('culture', 'en')->value('title')
            : '';

        return response()->view('ahg-archaeology::site-form', [
            'site'         => $site,
            'vocab'        => $this->service->vocabularies(),
            'repositories' => $this->repositories(),
        ]);
    }

    public function siteSave(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'title'                  => 'nullable|string|max:255',
            'site_number'            => 'required|string|max:100',
            'national_site_number'   => 'nullable|string|max:100',
            'site_type_id'           => 'nullable|integer',
            'period_id'              => 'nullable|integer',
            'protection_status_id'   => 'nullable|integer',
            'region'                 => 'nullable|string|max:150',
            'locality'               => 'nullable|string|max:255',
            'location_description'   => 'nullable|string',
            'latitude'               => 'nullable|numeric',
            'longitude'              => 'nullable|numeric',
            'elevation_m'            => 'nullable|integer',
            'area_sqm'               => 'nullable|numeric',
            'date_earliest'          => 'nullable|string|max:50',
            'date_latest'            => 'nullable|string|max:50',
            'dating_note'            => 'nullable|string',
            'excavated'              => 'nullable',
            'excavation_years'       => 'nullable|string|max:100',
            'excavator'              => 'nullable|string|max:255',
            'excavation_institution' => 'nullable|string|max:255',
            'permit_number'          => 'nullable|string|max:100',
            'research_potential'     => 'nullable|string|max:30',
            'notes'                  => 'nullable|string',
            'repository_id'          => 'nullable|integer',
        ]);
        $siteId = $this->service->saveSite($data, $id);

        return redirect()->to(url('/archaeology/site/'.$siteId))->with('status', __('Site saved.'));
    }

    public function findCreate(Request $request): Response
    {
        $siteId = (int) $request->query('site_id');

        return response()->view('ahg-archaeology::object-form', [
            'find'     => null,
            'siteId'   => $siteId,
            'vocab'    => $this->service->vocabularies(),
            'sites'    => $this->service->sitePickList(),
            'contexts' => $siteId ? $this->service->contextPickList($siteId) : collect(),
        ]);
    }

    public function findEdit(int $id): Response
    {
        $find = DB::table('archaeology_object')->where('id', $id)->first();
        if (! $find) {
            abort(404);
        }
        $find->title = $find->information_object_id
            ? DB::table('information_object_i18n')->where('id', $find->information_object_id)->where('culture', 'en')->value('title')
            : '';
        $siteId = (int) ($find->site_id ?? 0);

        return response()->view('ahg-archaeology::object-form', [
            'find'     => $find,
            'siteId'   => $siteId,
            'vocab'    => $this->service->vocabularies(),
            'sites'    => $this->service->sitePickList(),
            'contexts' => $siteId ? $this->service->contextPickList($siteId) : collect(),
        ]);
    }

    public function findSave(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'title'                => 'nullable|string|max:255',
            'accession_number'     => 'required|string|max:100',
            'site_id'              => 'nullable|integer',
            'context_id'           => 'nullable|integer',
            'object_type_id'       => 'nullable|integer',
            'material_id'          => 'nullable|integer',
            'technique_id'         => 'nullable|integer',
            'period_id'            => 'nullable|integer',
            'recovery_method_id'   => 'nullable|integer',
            'dating_method_id'     => 'nullable|integer',
            'condition_id'         => 'nullable|integer',
            'context_reference'    => 'nullable|string|max:100',
            'excavation_reference' => 'nullable|string|max:100',
            'find_date'            => 'nullable|date',
            'find_location'        => 'nullable|string|max:255',
            'finder'               => 'nullable|string|max:255',
            'date_earliest'        => 'nullable|string|max:50',
            'date_latest'          => 'nullable|string|max:50',
            'dating_note'          => 'nullable|string',
            'item_count'           => 'nullable|integer',
            'weight_g'             => 'nullable|numeric',
            'storage_location'     => 'nullable|string|max:255',
            'provenance'           => 'nullable|string',
            'notes'                => 'nullable|string',
        ]);
        $findId = $this->service->saveFind($data, $id);

        return redirect()->to(url('/archaeology/object/'.$findId))->with('status', __('Find saved.'));
    }

    /** Repositories for the site-form dropdown. */
    private function repositories()
    {
        return DB::table('repository')
            ->leftJoin('actor_i18n', fn ($j) => $j->on('actor_i18n.id', '=', 'repository.id')->where('actor_i18n.culture', '=', 'en'))
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get(['repository.id', 'actor_i18n.authorized_form_of_name as name']);
    }
}
