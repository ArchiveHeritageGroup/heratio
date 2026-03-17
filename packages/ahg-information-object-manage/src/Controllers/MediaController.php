<?php

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

        $filePath = public_path(($do->path ?? '') . ($do->name ?? ''));
        if (!file_exists($filePath)) {
            $filePath = '/mnt/nas/heratio/archive/' . ($do->path ?? '') . ($do->name ?? '');
        }

        $metadata = [];
        $metadata['digital_object_id'] = $id;
        $metadata['file_size'] = file_exists($filePath) ? filesize($filePath) : ($do->byte_size ?? 0);
        $metadata['media_type'] = str_contains($do->mime_type ?? '', 'audio') ? 'audio' : 'video';
        $metadata['format'] = pathinfo($do->name ?? '', PATHINFO_EXTENSION);

        // Try ffprobe for duration/codec info
        if (file_exists($filePath)) {
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

        $filePath = public_path(($do->path ?? '') . ($do->name ?? ''));
        if (!file_exists($filePath)) {
            $filePath = '/mnt/nas/heratio/archive/' . ($do->path ?? '') . ($do->name ?? '');
        }

        if (!file_exists($filePath)) {
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

        DB::table('media_snippets')->insert([
            'digital_object_id' => $request->input('digital_object_id'),
            'title' => $request->input('title'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'notes' => $request->input('notes', ''),
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
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
