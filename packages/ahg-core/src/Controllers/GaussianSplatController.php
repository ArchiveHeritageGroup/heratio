<?php

/**
 * GaussianSplatController - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\GaussianSplatService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * heratio#1193 - Gaussian-splat manager + standalone photoreal viewer. Upload a trained splat
 * scene (.ply/.splat/.ksplat) and explore it in the browser. Pairs with the point-cloud viewer
 * (#1183) under Admin -> Media.
 */
class GaussianSplatController extends Controller
{
    public function __construct(private GaussianSplatService $service) {}

    public function index()
    {
        return view('ahg-core::splat-manage', ['splats' => $this->service->list()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:200',
            'splat' => 'required|file',
            'record' => 'nullable|string|max:255',
        ]);

        $ioId = $this->service->resolveObjectId($request->input('record'));
        if ($request->filled('record') && ! $ioId) {
            return back()->with('splat_error', 'No catalogue record matched "'.$request->input('record').'" (use its slug or numeric id).');
        }

        $r = $this->service->store((string) $request->input('title', ''), $request->file('splat'), Auth::id(), $ioId);
        if (! $r['ok']) {
            return back()->with('splat_error', $r['error'] ?? 'Upload failed.');
        }

        return back()->with('splat_success', 'Uploaded.'.($ioId ? ' Attached to the record - it now shows in that record\'s 3D viewer.' : ' Open it to view the capture.'));
    }

    /** Attach/detach an existing splat to a catalogue record (by slug or numeric id). */
    public function attach(Request $request, int $id)
    {
        $request->validate(['record' => 'nullable|string|max:255']);
        $ioId = $this->service->resolveObjectId($request->input('record'));
        if ($request->filled('record') && ! $ioId) {
            return back()->with('splat_error', 'No catalogue record matched "'.$request->input('record').'".');
        }
        $this->service->setObject($id, $ioId);

        return back()->with('splat_success', $ioId ? 'Attached to the record.' : 'Detached from any record.');
    }

    /** Public photoreal viewer for a ready splat; staff can preview failed ones. */
    public function show(string $slug)
    {
        $splat = $this->service->getBySlug($slug);
        if (! $splat || ! $splat->file_name) {
            abort(404);
        }
        if ($splat->status !== 'ready' && ! Auth::check()) {
            abort(404);
        }

        return view('ahg-core::splat-viewer', [
            'splat' => $splat,
            'fileUrl' => $this->service->fileUrl($splat),
        ]);
    }
}
