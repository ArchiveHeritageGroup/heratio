<?php

/**
 * CaptionTrackService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License.
 */

namespace AhgMediaStreaming\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaptionTrackService
{
    /**
     * Get all caption tracks for a digital object.
     */
    public function getForDigitalObject(int $digitalObjectId): \Illuminate\Support\Collection
    {
        return DB::table('media_caption_track')
            ->where('digital_object_id', $digitalObjectId)
            ->orderByRaw("FIELD(track_type, 'caption', 'subtitle', 'description', 'chapters')")
            ->orderBy('language_code')
            ->orderBy('label')
            ->get();
    }

    /**
     * Get active tracks for embedding in a video player.
     *
     * Excludes description/chapters; they are shown separately in the UI.
     */
    public function getActiveForPlayer(int $digitalObjectId): \Illuminate\Support\Collection
    {
        return DB::table('media_caption_track')
            ->where('digital_object_id', $digitalObjectId)
            ->where('active', 1)
            ->whereIn('track_type', ['caption', 'subtitle'])
            ->orderBy('is_default', 'desc')
            ->orderBy('label')
            ->get();
    }

    /**
     * Fetch remote VTT/SRT content and store it inline.
     *
     * Returns the fetched VTT content, or null on failure.
     */
    public function fetchRemoteAndStore(int $trackId): ?string
    {
        $track = DB::table('media_caption_track')->find($trackId);
        if (!$track || empty($track->source_url)) {
            return null;
        }

        try {
            $content = Http::timeout(15)->get($track->source_url)->body();

            // Detect SRT and convert to VTT
            if (str_contains(trim(substr($content, 0, 200)), '-->') && !str_starts_with(trim($content), 'WEBVTT')) {
                $content = $this->srtToVtt($content);
            }

            $vttContent = $this->wrapInVtt($content, $track->label);

            DB::table('media_caption_track')
                ->where('id', $trackId)
                ->update(['vtt_content' => $vttContent, 'updated_at' => now()]);

            return $vttContent;
        } catch (\Throwable $e) {
            Log::error('CaptionTrackService: fetch failed', [
                'track_id' => $trackId,
                'url' => $track->source_url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Add a new track.
     *
     * @return int new track ID
     */
    public function create(array $data): int
    {
        return DB::table('media_caption_track')->insertGetId([
            'digital_object_id' => $data['digital_object_id'],
            'track_type' => $data['track_type'] ?? 'subtitle',
            'label' => $data['label'],
            'language_code' => $data['language_code'] ?? 'en',
            'is_sdh' => $data['is_sdh'] ?? false,
            'is_default' => $data['is_default'] ?? false,
            'active' => $data['active'] ?? true,
            'vtt_content' => $data['vtt_content'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update an existing track.
     */
    public function update(int $id, array $data): void
    {
        $fillable = [
            'track_type', 'label', 'language_code', 'is_sdh',
            'is_default', 'active', 'vtt_content', 'source_url',
        ];

        $update = [];
        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (!empty($update)) {
            $update['updated_at'] = now();
            DB::table('media_caption_track')->where('id', $id)->update($update);
        }
    }

    /**
     * Delete a track by ID.
     */
    public function delete(int $id): void
    {
        DB::table('media_caption_track')->where('id', $id)->delete();
    }

    /**
     * Toggle a track active/inactive.
     */
    public function toggleActive(int $id): bool
    {
        $track = DB::table('media_caption_track')->find($id);
        $newState = !$track->active;

        DB::table('media_caption_track')
            ->where('id', $id)
            ->update(['active' => $newState, 'updated_at' => now()]);

        return $newState;
    }

    /**
     * Convert SRT subtitle format to VTT.
     *
     * SRT: sequential numbers, timestamps, text blocks.
     * VTT: WEBVTT header, timestamp format uses commas.
     */
    private function srtToVtt(string $srt): string
    {
        // Convert timestamp separator from dot (.) to comma (,)
        $srt = preg_replace('/(\d{2}):(\d{2}):(\d{2})\.(\d{3})/', '$1:$2:$3,$4', $srt);

        return trim($srt);
    }

    /**
     * Wrap raw WebVTT content with a WEBVTT header and optional cue label.
     */
    private function wrapInVtt(string $content, string $label): string
    {
        $content = trim($content);

        if (str_starts_with($content, 'WEBVTT')) {
            return $content;
        }

        return "WEBVTT\n\n{$label}\n{$content}";
    }

    /**
     * Get the VTT blob for a track, fetching from source_url if needed.
     */
    public function getVttBlob(int $trackId): ?string
    {
        $track = DB::table('media_caption_track')->find($trackId);

        if (!$track) {
            return null;
        }

        // If VTT content exists, return it directly
        if (!empty($track->vtt_content)) {
            return $track->vtt_content;
        }

        // Fetch from remote if configured
        if (!empty($track->source_url)) {
            return $this->fetchRemoteAndStore($trackId);
        }

        return null;
    }
}
