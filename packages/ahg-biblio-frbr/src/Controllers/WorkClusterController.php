<?php

/**
 * WorkClusterController - public "View all editions" expander for a FRBR Work.
 *
 * Lists every library_item row sharing a work_key with the requested work.
 * Linked from GLAM browse hit-list once the cluster-renderer integration in
 * locked ahg-display is applied (see docs/reference/frbr-cluster-renderer-integration.md).
 *
 * Issue: heratio#763
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgBiblioFrbr\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class WorkClusterController extends Controller
{
    public function show(string $workKey)
    {
        if (!preg_match('/^[a-z0-9:_-]+$/i', $workKey)) {
            abort(404);
        }

        $items = DB::table('library_item')
            ->where('library_item.work_key', $workKey)
            ->join('information_object', 'information_object.id', '=', 'library_item.information_object_id')
            // #1356 — guests see published editions only (status type_id=158,
            // status_id=160); an authenticated editor still sees drafts. Mirrors
            // the #1353 ahg-display gate.
            ->when(! auth()->check(), function ($q) {
                $q->where('information_object.id', '!=', 1)
                  ->whereExists(function ($sub) {
                      $sub->select(DB::raw(1))
                          ->from('status')
                          ->whereColumn('status.object_id', 'information_object.id')
                          ->where('status.type_id', 158)
                          ->where('status.status_id', 160);
                  });
            })
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object_i18n.id', '=', 'information_object.id')
                  ->where('information_object_i18n.culture', '=', app()->getLocale());
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'information_object.id')
            ->select(
                'library_item.id as library_item_id',
                'library_item.isbn',
                'library_item.issn',
                'library_item.publication_date',
                'library_item.publisher',
                'library_item.publication_place',
                'library_item.edition',
                'library_item.edition_statement',
                'library_item.language',
                'library_item.material_type',
                'information_object_i18n.title',
                'slug.slug'
            )
            ->orderBy('library_item.publication_date')
            ->get();

        if ($items->isEmpty()) {
            abort(404);
        }

        $representative = $items->first();

        return view('ahg-biblio-frbr::work-cluster', [
            'workKey'        => $workKey,
            'items'          => $items,
            'representative' => $representative,
            'editionCount'   => $items->count(),
        ]);
    }
}
