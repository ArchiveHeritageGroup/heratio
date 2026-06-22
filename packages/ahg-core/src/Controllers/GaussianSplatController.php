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
            'bounds' => $this->service->computeBounds($this->service->filePath($splat), $splat->format ?? ''),
        ]);
    }

    /**
     * Render the photoreal viewer for a splat uploaded as a normal DIGITAL OBJECT
     * (.splat / .ksplat / 3DGS .ply) on a record - the "Link digital object" path. No
     * ahg_gaussian_splat row needed; the digital object is the source of truth.
     */
    public function showDigitalObject(int $id)
    {
        $do = \Illuminate\Support\Facades\DB::table('digital_object')->where('id', $id)->first();
        if (! $do || ! $do->name) {
            abort(404);
        }
        $ext = strtolower(pathinfo((string) $do->name, PATHINFO_EXTENSION));
        if (! in_array($ext, ['splat', 'ksplat', 'ply'], true)) {
            abort(404);
        }
        // A mesh .ply is not a splat - it belongs to the standard 3D-model viewer.
        if ($ext === 'ply' && ! $this->service->isGaussianPly($do)) {
            abort(404);
        }

        return view('ahg-core::splat-viewer', [
            'splat' => (object) ['title' => $do->name, 'format' => $ext],
            // Prefer the static nginx URL when the file is reachable there (unchanged for existing
            // splats); otherwise stream it - object-id media is stored a shard deeper than its URL.
            'fileUrl' => $this->service->digitalObjectServedUrl($do) ?? url('/splat/do/'.$do->id.'/raw'),
            'bounds' => $this->service->computeBounds($this->service->digitalObjectPath($do), $ext),
        ]);
    }

    /**
     * Stream the raw splat bytes for a digital object, resolved to its real on-disk location
     * across storage layouts. Used as the viewer source when the public /uploads URL does not
     * resolve (object-id media written to <uploads_path>/r/<id>/). Range-capable via BinaryFile
     * response so large scenes load progressively. Read-only - touches no upload path.
     */
    public function rawDigitalObject(int $id)
    {
        $do = \Illuminate\Support\Facades\DB::table('digital_object')->where('id', $id)->first();
        if (! $do || ! $do->name) {
            abort(404);
        }
        $ext = strtolower(pathinfo((string) $do->name, PATHINFO_EXTENSION));
        if (! in_array($ext, ['splat', 'ksplat', 'ply'], true)) {
            abort(404);
        }
        $fs = $this->service->digitalObjectFsPath($do);
        if ($fs === '' || ! is_readable($fs)) {
            abort(404);
        }

        return response()->file($fs, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
