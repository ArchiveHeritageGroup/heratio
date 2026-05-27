<?php

/**
 * CaptionTrackController - Admin caption/subtitle track management for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License.
 */

namespace AhgMediaStreaming\Controllers;

use AhgMediaStreaming\Services\CaptionTrackService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CaptionTrackController extends Controller
{
    public function __construct(
        private CaptionTrackService $captionService,
    ) {}

    /**
     * GET /media-streaming/caption-tracks/{digitalObjectId}
     *
     * List all caption tracks for a digital object, grouped by type.
     */
    public function index(int $digitalObjectId)
    {
        $tracks = $this->captionService->getForDigitalObject($digitalObjectId);

        $grouped = [];
        foreach ($tracks as $track) {
            $type = $track->track_type;
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $track;
        }

        $doName = \Illuminate\Support\Facades\DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->value('name') ?? "Digital Object #{$digitalObjectId}";

        return view('ahg-media-streaming::caption-track.index', [
            'digitalObjectId' => $digitalObjectId,
            'doName' => $doName,
            'tracks' => $tracks,
            'grouped' => $grouped,
        ]);
    }

    /**
     * GET /media-streaming/caption-tracks/{digitalObjectId}/create
     */
    public function create(int $digitalObjectId, Request $request)
    {
        return view('ahg-media-streaming::caption-track.form', [
            'digitalObjectId' => $digitalObjectId,
            'track' => null,
            'mode' => 'create',
            'prefillLanguage' => $request->query('language', 'en'),
        ]);
    }

    /**
     * POST /media-streaming/caption-tracks/{digitalObjectId}
     */
    public function store(int $digitalObjectId, Request $request)
    {
        $request->validate([
            'track_type' => 'required|in:caption,subtitle,description,chapters',
            'label' => 'required|string|max:120',
            'language_code' => 'required|string|max:10',
            'is_sdh' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'source_url' => 'nullable|url|max:500',
            'vtt_content' => 'nullable|string',
        ]);

        $data = [
            'digital_object_id' => $digitalObjectId,
            'track_type' => $request->input('track_type'),
            'label' => $request->input('label'),
            'language_code' => $request->input('language_code'),
            'is_sdh' => $request->boolean('is_sdh'),
            'is_default' => $request->boolean('is_default'),
            'source_url' => $request->filled('source_url') ? $request->input('source_url') : null,
            'vtt_content' => $request->filled('vtt_content') ? $request->input('vtt_content') : null,
        ];

        $this->captionService->create($data);

        return redirect()
            ->route('caption-tracks.index', $digitalObjectId)
            ->with('success', 'Caption track added successfully.');
    }

    /**
     * GET /media-streaming/caption-tracks/{digitalObjectId}/{trackId}/edit
     */
    public function edit(int $digitalObjectId, int $trackId)
    {
        $track = \Illuminate\Support\Facades\DB::table('media_caption_track')
            ->where('id', $trackId)
            ->where('digital_object_id', $digitalObjectId)
            ->firstOrFail();

        return view('ahg-media-streaming::caption-track.form', [
            'digitalObjectId' => $digitalObjectId,
            'track' => $track,
            'mode' => 'edit',
            'prefillLanguage' => null,
        ]);
    }

    /**
     * PUT /media-streaming/caption-tracks/{digitalObjectId}/{trackId}
     */
    public function update(int $digitalObjectId, int $trackId, Request $request)
    {
        $request->validate([
            'track_type' => 'required|in:caption,subtitle,description,chapters',
            'label' => 'required|string|max:120',
            'language_code' => 'required|string|max:10',
            'is_sdh' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'source_url' => 'nullable|url|max:500',
            'vtt_content' => 'nullable|string',
        ]);

        $this->captionService->update($trackId, [
            'track_type' => $request->input('track_type'),
            'label' => $request->input('label'),
            'language_code' => $request->input('language_code'),
            'is_sdh' => $request->boolean('is_sdh'),
            'is_default' => $request->boolean('is_default'),
            'source_url' => $request->filled('source_url') ? $request->input('source_url') : null,
            'vtt_content' => $request->filled('vtt_content') ? $request->input('vtt_content') : null,
        ]);

        return redirect()
            ->route('caption-tracks.index', $digitalObjectId)
            ->with('success', 'Caption track updated successfully.');
    }

    /**
     * DELETE /media-streaming/caption-tracks/{digitalObjectId}/{trackId}
     */
    public function destroy(int $digitalObjectId, int $trackId)
    {
        $this->captionService->delete($trackId);

        return redirect()
            ->route('caption-tracks.index', $digitalObjectId)
            ->with('success', 'Caption track deleted.');
    }

    /**
     * POST /media-streaming/caption-tracks/{digitalObjectId}/{trackId}/toggle
     */
    public function toggleActive(int $digitalObjectId, int $trackId)
    {
        $newState = $this->captionService->toggleActive($trackId);
        $stateLabel = $newState ? 'enabled' : 'disabled';

        return redirect()
            ->route('caption-tracks.index', $digitalObjectId)
            ->with('success', "Caption track {$stateLabel}.");
    }

    /**
     * POST /media-streaming/caption-tracks/{digitalObjectId}/{trackId}/fetch
     *
     * Manually fetch remote VTT content and store it inline.
     */
    public function fetchRemote(int $digitalObjectId, int $trackId)
    {
        $vttBlob = $this->captionService->fetchRemoteAndStore($trackId);

        if ($vttBlob === null) {
            return redirect()
                ->route('caption-tracks.index', $digitalObjectId)
                ->with('error', 'Failed to fetch remote VTT content. Check the URL and try again.');
        }

        return redirect()
            ->route('caption-tracks.index', $digitalObjectId)
            ->with('success', 'Remote VTT content fetched and saved.');
    }
}
