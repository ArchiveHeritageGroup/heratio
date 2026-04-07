<?php

/**
 * MediaController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MediaController extends Controller
{
    public function transcriptionVtt(int $id)
    {
        $transcription = DB::table('media_transcription')
            ->where('digital_object_id', $id)->first();

        if (!$transcription) {
            abort(404, 'Transcription not found');
        }

        $segments = json_decode($transcription->segments ?? '[]', true) ?: [];
        $vtt = "WEBVTT\n\n";
        foreach ($segments as $i => $seg) {
            $start = $this->formatTimestamp($seg['start'] ?? 0);
            $end = $this->formatTimestamp($seg['end'] ?? 0);
            $text = trim($seg['text'] ?? '');
            $vtt .= ($i + 1) . "\n{$start} --> {$end}\n{$text}\n\n";
        }

        return response($vtt, 200)
            ->header('Content-Type', 'text/vtt; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"transcription-{$id}.vtt\"");
    }

    public function transcriptionSrt(int $id)
    {
        $transcription = DB::table('media_transcription')
            ->where('digital_object_id', $id)->first();

        if (!$transcription) {
            abort(404, 'Transcription not found');
        }

        $segments = json_decode($transcription->segments ?? '[]', true) ?: [];
        $srt = '';
        foreach ($segments as $i => $seg) {
            $start = $this->formatTimestampSrt($seg['start'] ?? 0);
            $end = $this->formatTimestampSrt($seg['end'] ?? 0);
            $text = trim($seg['text'] ?? '');
            $srt .= ($i + 1) . "\n{$start} --> {$end}\n{$text}\n\n";
        }

        return response($srt, 200)
            ->header('Content-Type', 'application/x-subrip; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"transcription-{$id}.srt\"");
    }

    public function extract(Request $request, int $id)
    {
        $do = DB::table('digital_object')->where('id', $id)->first();
        if (!$do) {
            return response()->json(['success' => false, 'error' => 'Digital object not found'], 404);
        }

        $filePath = $this->resolveFilePath($do);

        $metadata = [];
        $metadata['digital_object_id'] = $id;
        $metadata['file_size'] = ($filePath && file_exists($filePath)) ? filesize($filePath) : ($do->byte_size ?? 0);
        $metadata['media_type'] = str_contains($do->mime_type ?? '', 'audio') ? 'audio' : 'video';
        $metadata['format'] = pathinfo($do->name ?? '', PATHINFO_EXTENSION);

        // Try ffprobe for duration/codec info
        if ($filePath && file_exists($filePath)) {
            $ffprobe = shell_exec("ffprobe -v quiet -print_format json -show_format -show_streams " . escapeshellarg($filePath) . " 2>/dev/null");
            if ($ffprobe) {
                $info = json_decode($ffprobe, true);
                $format = $info['format'] ?? [];
                $metadata['duration'] = (float) ($format['duration'] ?? 0);
                $metadata['bitrate'] = (int) ($format['bit_rate'] ?? 0);
                $metadata['title'] = $format['tags']['title'] ?? null;
                $metadata['artist'] = $format['tags']['artist'] ?? null;
                $metadata['album'] = $format['tags']['album'] ?? null;
                $metadata['genre'] = $format['tags']['genre'] ?? null;
                $metadata['year'] = $format['tags']['date'] ?? null;
                $metadata['copyright'] = $format['tags']['copyright'] ?? null;

                foreach (($info['streams'] ?? []) as $stream) {
                    if (($stream['codec_type'] ?? '') === 'audio') {
                        $metadata['audio_codec'] = $stream['codec_name'] ?? null;
                        $metadata['audio_sample_rate'] = (int) ($stream['sample_rate'] ?? 0);
                        $metadata['audio_channels'] = (int) ($stream['channels'] ?? 0);
                        $metadata['audio_bits_per_sample'] = (int) ($stream['bits_per_raw_sample'] ?? 0);
                    }
                    if (($stream['codec_type'] ?? '') === 'video') {
                        $metadata['video_codec'] = $stream['codec_name'] ?? null;
                        $metadata['video_width'] = (int) ($stream['width'] ?? 0);
                        $metadata['video_height'] = (int) ($stream['height'] ?? 0);
                        $metadata['video_frame_rate'] = eval('return ' . ($stream['r_frame_rate'] ?? '0') . ';') ?: 0;
                    }
                }
            }
        }

        $metadata['created_at'] = now();

        DB::table('media_metadata')->updateOrInsert(
            ['digital_object_id' => $id],
            $metadata
        );

        return response()->json(['success' => true]);
    }

    public function transcribe(Request $request, int $id)
    {
        $lang = $request->get('lang', 'en');
        $do = DB::table('digital_object')->where('id', $id)->first();
        if (!$do) {
            return response()->json(['success' => false, 'error' => 'Digital object not found'], 404);
        }

        $filePath = $this->resolveFilePath($do);

        if (!$filePath) {
            return response()->json(['success' => false, 'error' => 'Media file not found on disk']);
        }

        // Try Whisper for transcription
        $whisperCmd = "whisper " . escapeshellarg($filePath) . " --language {$lang} --output_format json --output_dir /tmp 2>/dev/null";
        $output = shell_exec($whisperCmd);

        $baseName = pathinfo($filePath, PATHINFO_FILENAME);
        $jsonPath = "/tmp/{$baseName}.json";

        if (file_exists($jsonPath)) {
            $data = json_decode(file_get_contents($jsonPath), true);
            $fullText = $data['text'] ?? '';
            $segments = $data['segments'] ?? [];
            $duration = end($segments)['end'] ?? 0;

            DB::table('media_transcription')->updateOrInsert(
                ['digital_object_id' => $id],
                [
                    'full_text' => $fullText,
                    'segments' => json_encode($segments),
                    'language' => $lang,
                    'duration' => $duration,
                    'segment_count' => count($segments),
                    'confidence' => collect($segments)->avg('avg_logprob') ? round((1 + collect($segments)->avg('avg_logprob')) * 100) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            @unlink($jsonPath);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'error' => 'Whisper transcription failed. Ensure whisper is installed.']);
    }

    public function snippetStore(Request $request)
    {
        $request->validate([
            'digital_object_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'start_time' => 'required|numeric',
            'end_time' => 'required|numeric',
        ]);

        $id = DB::table('media_snippets')->insertGetId([
            'digital_object_id' => $request->input('digital_object_id'),
            'title' => $request->input('title'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'notes' => $request->input('notes', ''),
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    /**
     * List snippets by digital_object_id query parameter (legacy AtoM URL).
     * GET /media/snippets?digital_object_id=X
     */
    public function snippetsListByQuery(Request $request)
    {
        $doId = (int) $request->query('digital_object_id', $request->query('id', 0));

        if (!$doId) {
            return response()->json(['error' => 'digital_object_id query parameter required'], 400);
        }

        return $this->snippetsList($doId);
    }

    /**
     * List snippets for a digital object (AJAX GET).
     * GET /media/snippets/{id}
     */
    public function snippetsList(int $id)
    {
        try {
            $snippets = DB::table('media_snippets')
                ->where('digital_object_id', $id)
                ->orderBy('start_time')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();

            return response()->json(['snippets' => $snippets]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a media snippet (AJAX DELETE).
     * DELETE /media/snippets/{id}
     */
    public function snippetDelete(int $id)
    {
        try {
            DB::table('media_snippets')->where('id', $id)->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export a snippet as a downloadable clip reference (AJAX).
     * GET /media/export-snippet?id=<snippet_id>
     */
    public function exportSnippet(Request $request)
    {
        $snippetId = (int) $request->query('id');
        $snippet = DB::table('media_snippets')->where('id', $snippetId)->first();

        if (!$snippet) {
            return response()->json(['error' => 'Snippet not found'], 404);
        }

        return response()->json([
            'id' => $snippet->id,
            'digital_object_id' => $snippet->digital_object_id,
            'title' => $snippet->title,
            'start_time' => $snippet->start_time,
            'end_time' => $snippet->end_time,
            'notes' => $snippet->notes ?? '',
            'duration' => round($snippet->end_time - $snippet->start_time, 3),
        ]);
    }

    /**
     * Get transcription data as JSON (AJAX GET).
     * GET /media/transcription/{id}
     */
    public function transcriptionJson(int $id)
    {
        $transcription = DB::table('media_transcription')
            ->where('digital_object_id', $id)
            ->first();

        if (!$transcription) {
            return response()->json(['error' => 'No transcription found'], 404);
        }

        $segments = json_decode($transcription->segments ?? '[]', true);
        $data = json_decode($transcription->transcription_data ?? '{}', true);

        return response()->json([
            'full_text' => $transcription->full_text ?? '',
            'language' => $transcription->language ?? 'en',
            'confidence' => $transcription->confidence ?? null,
            'segments' => !empty($data['segments']) ? $data['segments'] : $segments,
            'segment_count' => $transcription->segment_count ?? count($segments),
            'duration' => $transcription->duration ?? null,
        ]);
    }

    /**
     * Delete transcription data (AJAX DELETE).
     * DELETE /media/transcription/{id}
     */
    public function transcriptionDelete(int $id)
    {
        try {
            DB::table('media_transcription')->where('digital_object_id', $id)->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function resolveFilePath(object $do): ?string
    {
        $relative = ($do->path ?? '') . ($do->name ?? '');
        $candidates = [
            public_path($relative),
            config('heratio.uploads_path') . '/' . $relative,
            '/usr/share/nginx/archive/' . $relative,
            '/usr/share/nginx/archive' . $relative,
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    private function formatTimestamp(float $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds - ($h * 3600) - ($m * 60);
        return sprintf('%02d:%02d:%06.3f', $h, $m, $s);
    }

    private function formatTimestampSrt(float $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds - ($h * 3600) - ($m * 60);
        return sprintf('%02d:%02d:%02d,%03d', $h, $m, floor($s), ($s - floor($s)) * 1000);
    }
}
