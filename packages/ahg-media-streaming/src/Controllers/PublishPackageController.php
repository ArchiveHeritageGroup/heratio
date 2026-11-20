<?php

/**
 * PublishPackageController - "Publish to platforms" for an approved record
 * (heratio#1761, GCIS 6.8.2). Staff with update permission only; every route
 * carries acl:update.
 *
 * Packages are made for posting by hand. Direct posting through the platform
 * APIs is roadmap - see PublishPackageService.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgMediaStreaming\Controllers;

use AhgCore\Services\AclService;
use AhgMediaStreaming\Jobs\BuildPublishPackageJob;
use AhgMediaStreaming\Services\PublishPackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PublishPackageController extends Controller
{
    public function __construct(private PublishPackageService $service) {}

    private function record(int $id): object
    {
        $io = DB::table('information_object')->where('id', $id)->first(['id']);
        abort_unless($io && $id > 1, 404);
        $io->slug = DB::table('slug')->where('object_id', $id)->value('slug');
        $io->title = DB::table('information_object_i18n')->where('id', $id)->orderByRaw("culture = 'en' DESC")->value('title');

        return $io;
    }

    /** A package route names a package, not a record: check the record's own rules (repository-scoped accounts). */
    private function package(Request $request, int $packageId): object
    {
        $row = $this->service->find($packageId);
        abort_unless($row, 404);
        abort_unless(AclService::hasPermission($request->user()?->id, 'update', (int) $row->information_object_id), 403);

        return $row;
    }

    /** GET /informationobject/{id}/publish - choose platforms. */
    public function form(int $id)
    {
        $io = $this->record($id);
        $src = $this->service->source($id);

        return view('ahg-media-streaming::publish.form', [
            'io' => $io,
            'refusals' => $this->service->refusals($id),
            'source' => $src,
            'duration' => $src && $src['type'] !== 'image' ? $this->service->duration($src['file']) : null,
            'presets' => $this->service->presets(),
            'log' => $this->service->log($id),
            'statusLabels' => $this->service->statusLabels(),
        ]);
    }

    /** POST /informationobject/{id}/publish - make (or queue) one package per platform. */
    public function store(Request $request, int $id): RedirectResponse
    {
        $this->record($id);
        $data = $request->validate([
            'platforms' => 'required|array|min:1',
            'platforms.*' => ['string', Rule::in(array_keys($this->service->presets()))],
            'trim' => 'nullable|boolean',
        ]);
        if ($why = $this->service->refusals($id)) {
            return back()->withErrors(['platforms' => implode(' ', $why)]);
        }

        $ids = $this->service->request($id, $data['platforms'], (bool) ($data['trim'] ?? false), $request->user()?->id);
        $src = $this->service->source($id);
        $long = $src['type'] !== 'image' && ($this->service->duration($src['file']) ?? 0) > PublishPackageService::queueSeconds();
        foreach ($ids as $pid) {
            $long ? BuildPublishPackageJob::dispatch($pid, $request->user()?->id) : $this->service->build($pid);
        }

        return redirect()->route('media-streaming.publish.form', $id)->with('success', $long
            ? __('The packages are queued; this page shows them as they are made.')
            : __('The packages are made. Download each one, post it on the platform, then record where it was posted.'));
    }

    /** GET /media-streaming/publish/{packageId}/download */
    public function download(Request $request, int $packageId): Response
    {
        $row = $this->package($request, $packageId);
        abort_unless($row->package_path && is_file($row->package_path)
            && str_starts_with(realpath($row->package_path) ?: '', realpath(PublishPackageService::packagesDir()) ?: "\0"), 404);
        $this->service->markDownloaded($packageId, $request->user()?->id);

        return response()->download($row->package_path, basename($row->package_path), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** POST /media-streaming/publish/{packageId}/posted - staff record where they posted it. */
    public function posted(Request $request, int $packageId): RedirectResponse
    {
        $this->package($request, $packageId);
        $data = $request->validate([
            'posted_url' => 'required|url:http,https|max:2048',
            'posted_at' => 'required|date|before_or_equal:today',
        ]);
        try {
            $this->service->markPosted($packageId, $data['posted_url'], $data['posted_at'], $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['posted_url' => __('Only a made package can be marked posted.')]);
        }

        return back()->with('success', __('Recorded as posted.'));
    }

    /** GET /admin/publish-log - every package, all records. */
    public function index(Request $request)
    {
        $labels = $this->service->statusLabels();
        $status = $request->query('status');
        $status = is_string($status) && isset($labels[$status]) ? $status : null;

        return view('ahg-media-streaming::publish.index', [
            'log' => $this->service->log(null, 500, $status),
            'status' => $status,
            'statusLabels' => $labels,
            'presets' => $this->service->presets(),
        ]);
    }
}
