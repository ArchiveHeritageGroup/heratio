<?php

/**
 * PointCloudController - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Jobs\ProcessPointCloud;
use AhgCore\Services\PointCloudConverterService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * heratio#1183 - point-cloud manager + public Potree viewer. Upload a .las/.laz/.ply scan
 * (e.g. a rock-art panel), convert it to a streaming octree off the request, and view it in
 * the browser. Large scans are better converted via `ahg:pointcloud-convert` on the server.
 */
class PointCloudController extends Controller
{
    public function __construct(private PointCloudConverterService $service) {}

    /** Admin manager: list + upload. */
    public function index()
    {
        return view('ahg-core::pointcloud-manage', ['clouds' => $this->service->list()]);
    }

    /** Accept an upload, queue conversion. */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:200',
            'cloud' => 'required|file',
        ]);

        $file = $request->file('cloud');
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, PointCloudConverterService::NEEDS_TOOLING, true)) {
            return back()->with('pc_error', strtoupper($ext).' is not supported yet (needs PDAL). Export to .las/.laz and re-upload.');
        }
        if (! in_array($ext, PointCloudConverterService::SUPPORTED, true)) {
            return back()->with('pc_error', 'Upload a .las, .laz or .ply point cloud.');
        }

        $row = $this->service->createPending((string) $request->input('title', ''), $file->getClientOriginalName(), Auth::id());

        $incoming = rtrim((string) config('heratio.pointclouds_path'), '/').'/_incoming';
        if (! is_dir($incoming)) {
            @mkdir($incoming, 0775, true);
        }
        $stored = $incoming.'/'.$row['id'].'.'.$ext;
        $file->move($incoming, $row['id'].'.'.$ext);

        ProcessPointCloud::dispatch($row['id'], $stored);

        return back()->with('pc_success', 'Uploaded - conversion is running. It will appear as Ready when done.');
    }

    /** Public Potree viewer for a ready cloud; staff can preview pending/failed. */
    public function show(string $slug)
    {
        $cloud = $this->service->getBySlug($slug);
        if (! $cloud) {
            abort(404);
        }
        if ($cloud->status !== 'ready' && ! Auth::check()) {
            abort(404);
        }

        return view('ahg-core::pointcloud-viewer', [
            'cloud' => $cloud,
            'octreeUrl' => '/pointclouds/'.$cloud->octree_dir.'/metadata.json',
        ]);
    }

    /** JSON status for polling while a cloud converts. */
    public function status(string $slug)
    {
        $cloud = $this->service->getBySlug($slug);
        if (! $cloud) {
            return response()->json(['ok' => false], 404);
        }

        return response()->json([
            'ok' => true, 'status' => $cloud->status,
            'point_count' => $cloud->point_count, 'error' => $cloud->error,
        ]);
    }
}
