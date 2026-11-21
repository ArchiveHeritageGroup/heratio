<?php

/**
 * Live events (heratio#1743, GCIS 5.1.1).
 *
 * Staff (update permission on the record): schedule an event on a record, see
 * the ingest URL and stream key for the encoder, start and stop the ingest
 * worker, and list every event. Viewers: the live playlist and its segments,
 * and the event status, each behind the record's media access rules.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgMediaStreaming\Controllers;

use AhgMediaStreaming\Services\LiveStreamService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LiveEventController extends Controller
{
    public function __construct(private LiveStreamService $service)
    {
    }

    private function record(int $id): object
    {
        $io = DB::table('information_object')->where('id', $id)->first(['id']);
        abort_unless($io && $id > 1, 404);
        $io->slug = DB::table('slug')->where('object_id', $id)->value('slug');
        $io->title = DB::table('information_object_i18n')->where('id', $id)->orderByRaw("culture = 'en' DESC")->value('title');

        return $io;
    }

    /** An event route names an event, not a record: check the record's own rules too. */
    private function managed(Request $request, int $eventId): object
    {
        $e = $this->service->find($eventId);
        abort_unless($e, 404);
        abort_unless($this->service->mayManage($e, $request->user()?->id), 403);

        return $e;
    }

    /** GET /informationobject/{id}/live/create */
    public function create(int $id)
    {
        return view('ahg-media-streaming::live.form', ['io' => $this->record($id)]);
    }

    /** POST /informationobject/{id}/live */
    public function store(Request $request, int $id): RedirectResponse
    {
        $this->record($id);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'scheduled_at' => 'required|date',
            'description' => 'nullable|string|max:5000',
        ]);
        $eventId = $this->service->create($id, trim($data['title']), date('Y-m-d H:i:s', strtotime($data['scheduled_at'])),
            $data['description'] ?? null, $request->user()?->id);

        return redirect()->route('media-streaming.live.show', $eventId)->with('success', __('Live event scheduled. Paste the server and stream key into the encoder.'));
    }

    /** GET /media-streaming/live/{eventId} - the staff event page. */
    public function show(Request $request, int $eventId)
    {
        $this->service->reap();
        $e = $this->managed($request, $eventId);

        return view('ahg-media-streaming::live.show', [
            'event' => $e,
            'io' => $this->record((int) $e->information_object_id),
            'ingest' => $this->service->ingest($e),
            'workerActive' => $this->service->workerActive($e),
            'leftover' => $this->service->leftover($e),
            'statusLabels' => $this->service->statusLabels(),
        ]);
    }

    /** POST /media-streaming/live/{eventId}/start - queue the ingest worker. */
    public function start(Request $request, int $eventId): RedirectResponse
    {
        $e = $this->managed($request, $eventId);
        if ($why = $this->service->requestStart($e, $request->user()?->id)) {
            return back()->withErrors(['start' => $why]);
        }

        return back()->with('success', __('The ingest worker is starting. Start streaming from the encoder.'));
    }

    /** POST /media-streaming/live/{eventId}/stop */
    public function stop(Request $request, int $eventId): RedirectResponse
    {
        $e = $this->managed($request, $eventId);
        $this->service->requestStop($e);

        return back()->with('success', __('Stop requested. The recording is added to the record when the worker finishes.'));
    }

    /** GET /admin/live-events - every event with its status. */
    public function index(Request $request)
    {
        $labels = $this->service->statusLabels();
        $status = $request->validate(['status' => ['nullable', Rule::in(array_keys($labels))]])['status'] ?? null;

        return view('ahg-media-streaming::live.index', [
            'events' => $this->service->list(null, $status),
            'statusLabels' => $labels,
            'status' => $status,
        ]);
    }

    /** GET /media-streaming/live/{eventId}/hls/{file} - the live playlist and segments, access-checked. */
    public function hls(int $eventId, string $file)
    {
        $e = $this->service->find($eventId);
        abort_unless($e, 404);
        abort_unless($this->service->mayView($e), auth()->check() ? 403 : 401);
        $path = $this->service->hlsPath($e, $file);
        abort_unless($path, 404);
        $playlist = str_ends_with($file, '.m3u8');

        return response()->file($path, [
            'Content-Type' => $playlist ? 'application/vnd.apple.mpegurl' : 'video/mp2t',
            'Cache-Control' => $playlist ? 'no-cache, no-store, private' : 'private, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** GET /media-streaming/live/{eventId}/status - for the record page to notice the start and the end. */
    public function status(int $eventId)
    {
        $e = $this->service->find($eventId);
        abort_unless($e, 404);
        abort_unless($this->service->mayView($e), auth()->check() ? 403 : 401);

        return response()->json(['status' => $e->status, 'playlist' => (bool) $this->service->hlsPath($e, 'index.m3u8')])
            ->header('Cache-Control', 'no-store');
    }
}
