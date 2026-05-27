<?php

/**
 * WorkOverrideController - admin UI for FRBR force-group / force-split
 * overrides. Lets cataloguers pin items together or pull them apart when
 * the algorithmic work-key gets it wrong.
 *
 * Routes:
 *   GET  /admin/frbr/overrides
 *   GET  /admin/frbr/overrides/create
 *   POST /admin/frbr/overrides
 *   DELETE /admin/frbr/overrides/{id}
 *   POST /admin/frbr/cluster (preview: show siblings of an item)
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgBiblioFrbr\Controllers;

use AhgBiblioFrbr\Services\WorkKeyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkOverrideController extends Controller
{
    public function __construct(private WorkKeyService $svc)
    {
    }

    public function index(Request $request)
    {
        $rows = DB::table('library_work_override')
            ->leftJoin('library_item', 'library_item.id', '=', 'library_work_override.library_item_id')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object_i18n.id', '=', 'library_item.information_object_id')
                  ->where('information_object_i18n.culture', '=', 'en');
            })
            ->select(
                'library_work_override.id',
                'library_work_override.library_item_id',
                'library_work_override.mode',
                'library_work_override.override_key',
                'library_work_override.reason',
                'library_work_override.created_at',
                'information_object_i18n.title'
            )
            ->orderByDesc('library_work_override.created_at')
            ->paginate(50);

        return view('ahg-biblio-frbr::overrides.index', compact('rows'));
    }

    public function create()
    {
        return view('ahg-biblio-frbr::overrides.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'library_item_id' => 'required|integer|exists:library_item,id',
            'mode' => 'required|in:force_group,force_split',
            'override_key' => 'required|string|max:64',
            'reason' => 'nullable|string|max:500',
        ]);

        $this->svc->setOverride(
            (int) $data['library_item_id'],
            $data['mode'],
            $data['override_key'],
            $data['reason'] ?? null,
            Auth::id()
        );

        return redirect()->route('admin.frbr.overrides.index')
            ->with('success', __('Override saved and work-key recomputed.'));
    }

    public function destroy(Request $request, int $id)
    {
        $row = DB::table('library_work_override')->where('id', $id)->first();
        if (!$row) {
            return redirect()->route('admin.frbr.overrides.index')
                ->with('error', __('Override not found.'));
        }
        $this->svc->clearOverride((int) $row->library_item_id, (string) $row->mode);
        return redirect()->route('admin.frbr.overrides.index')
            ->with('success', __('Override cleared.'));
    }

    /**
     * Preview: show all library_item rows sharing a work-key with the requested item.
     */
    public function cluster(Request $request)
    {
        $itemId = (int) $request->input('library_item_id', 0);
        if ($itemId <= 0) {
            return response()->json(['error' => 'library_item_id required'], 422);
        }
        $siblings = $this->svc->siblingsOf($itemId);
        $key = DB::table('library_item')->where('id', $itemId)->value('work_key');
        return response()->json([
            'library_item_id' => $itemId,
            'work_key' => $key,
            'siblings' => $siblings,
        ]);
    }
}
