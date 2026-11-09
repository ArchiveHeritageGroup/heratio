<?php

/**
 * GridEntryController - grid entry (heratio#1581): type or paste many
 * descriptions under one parent in a spreadsheet-style grid. Saving validates
 * the rows, stages them as an ingest session and hands over to the ingest
 * wizard's commit step, so the records are created by the same job as a CSV
 * or Excel ingest. Port of the AHG Extensions for AtoM grid entry (#208).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgIngest\Controllers;

use AhgCore\Services\AclService;
use AhgIngest\Services\GridEntryService;
use AhgIngest\Services\IngestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GridEntryController extends Controller
{
    protected function grid(): GridEntryService
    {
        return new GridEntryService(new IngestService());
    }

    /** The parent description, or null when it does not exist (or is the root). */
    protected function loadParent(int $id, string $culture): ?object
    {
        if ($id <= 1) {
            return null;
        }

        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as cur', fn ($j) => $j->on('cur.id', '=', 'io.id')->where('cur.culture', '=', $culture))
            ->leftJoin('information_object_i18n as src', fn ($j) => $j->on('src.id', '=', 'io.id')->on('src.culture', '=', 'io.source_culture'))
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('io.id', $id)
            ->select('io.id', 'io.identifier', 'slug.slug', DB::raw('COALESCE(cur.title, src.title) AS title'))
            ->first();
    }

    public function index(Request $request)
    {
        $grid = $this->grid();
        $culture = app()->getLocale();
        $parentId = (int) $request->query('parent');
        if (! $parentId && $request->query('slug')) {
            $parentId = (int) DB::table('slug')->where('slug', (string) $request->query('slug'))->value('object_id');
        }
        $parent = $parentId ? $this->loadParent($parentId, $culture) : null;

        return view('ahg-ingest::grid', [
            'parent' => $parent,
            'children' => $parent ? $grid->children((int) $parent->id, $culture) : [],
            'levels' => $grid->levelNames($culture),
            'columns' => GridEntryService::COLUMNS,
            'maxRows' => GridEntryService::MAX_ROWS,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        // JSON only (the route also carries the CSRF check).
        if (! $request->isJson() || ! is_array($request->input('rows'))) {
            return response()->json(['error' => __('Bad request.')], 400);
        }
        $culture = app()->getLocale();
        $parent = $this->loadParent((int) $request->input('parent_id'), $culture);
        if (! $parent) {
            return response()->json(['error' => __('Choose a parent description first.')], 422);
        }
        if (! AclService::check((object) ['id' => (int) $parent->id], 'create')) {
            return response()->json(['error' => __('You may not add descriptions under this parent.')], 403);
        }

        $grid = $this->grid();
        $rows = $grid->normalise($request->input('rows'));
        if (! $rows) {
            return response()->json(['error' => __('The grid is empty.')], 422);
        }
        $errors = $grid->validate($rows, $grid->levelNames($culture));
        if ($errors) {
            return response()->json(['error' => __('Some rows need attention before they can be saved.'), 'errors' => $errors], 422);
        }

        $sessionId = $grid->stage((int) auth()->id(), (int) $parent->id, (string) ($parent->title ?: $parent->slug), $rows);

        return response()->json([
            'ok' => true,
            'session_id' => $sessionId,
            'rows' => count($rows),
            'commit_url' => route('ingest.commit', $sessionId),
        ]);
    }
}
