<?php

/**
 * ImageArController — Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * AGPL-3.0-or-later. See LICENSE.
 */

namespace AhgImageAr\Controllers;

use AhgImageAr\Services\AnimationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImageArController extends Controller
{
    public function __construct(private AnimationService $ai)
    {
    }

    /**
     * POST /image-ar/generate/{ioId}
     * Send the IO's master image to the AI video server, save the MP4.
     * Atomic replace of any prior generation.
     */
    public function userGenerate(Request $request, int $ioId)
    {
        if (!$this->ai->isEnabled()) {
            session()->flash('error', __('Image animation is currently disabled by the administrator.'));
            return redirect()->back();
        }

        $io = DB::table('information_object')->where('id', $ioId)->first(['id']);
        if (!$io) {
            abort(404);
        }

        [$sourcePath, $sourceDo] = $this->resolveMasterImage($ioId);
        if (!$sourcePath) {
            session()->flash('error', __('No image found for this object. Upload an image first.'));
            return redirect()->back();
        }

        // Per-call overrides from the form (prompt, motion_bucket_id, seed).
        $opts = $this->ai->defaults();
        foreach (['prompt', 'motion_bucket_id', 'num_frames', 'fps', 'seed', 'model'] as $k) {
            if ($request->filled($k)) {
                $opts[$k] = $request->input($k);
            }
        }

        $storage = rtrim((string) config('heratio.storage_path', ''), '/');
        $stem = pathinfo($sourceDo->name, PATHINFO_FILENAME);
        $stamp = substr((string) time(), -6);
        $modelTag = preg_replace('/[^a-z0-9]+/i', '', (string) $opts['model']) ?: 'ai';
        $mp4Filename = $modelTag . '_' . $stem . '_' . $stamp . '.mp4';
        $mp4Abs = $storage . '/uploads/ar/' . $ioId . '/' . $mp4Filename;
        $mp4Web = '/uploads/ar/' . $ioId . '/' . $mp4Filename;

        try {
            $stats = $this->ai->generate($sourcePath, $mp4Abs, $opts);
        } catch (\Throwable $e) {
            Log::error('[image-ar] generation failed', ['io' => $ioId, 'err' => $e->getMessage()]);
            session()->flash('error', __('Animation failed: ') . $e->getMessage());
            return redirect()->back();
        }

        // Replace any existing row + delete its old MP4.
        $existing = DB::table('object_image_ar')->where('object_id', $ioId)->first();
        if ($existing) {
            $this->unlinkWeb($existing->mp4_path, $storage, $mp4Abs);
            $this->unlinkWeb($existing->mind_path ?? null, $storage); // legacy
            DB::table('object_image_ar')->where('id', $existing->id)->delete();
        }

        $duration = $stats['fps'] > 0 ? round($stats['frames'] / $stats['fps'], 2) : null;
        DB::table('object_image_ar')->insert([
            'object_id' => $ioId,
            'digital_object_id' => $sourceDo->id,
            'mp4_filename' => $mp4Filename,
            'mp4_path' => $mp4Web,
            'mp4_size' => $stats['size'],
            'mp4_duration_secs' => $duration,
            'mp4_fps' => $stats['fps'],
            'mp4_motion' => 'ai-' . $stats['model'],
            'ai_model' => $stats['model'],
            'ai_prompt' => $stats['prompt'],
            'ai_seed' => $stats['seed'],
            'ai_motion_bucket_id' => $stats['motion_bucket_id'],
            'generation_secs' => $stats['generation_secs'],
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session()->flash('notice', sprintf(
            __('Animation generated — %d KB in %.0f s (model %s).'),
            (int) ($stats['size'] / 1024),
            $stats['generation_secs'],
            $stats['model']
        ));
        return redirect()->back();
    }

    public function delete(Request $request, int $id)
    {
        $row = DB::table('object_image_ar')->where('id', $id)->first();
        if (!$row) {
            session()->flash('error', __('Animation not found.'));
            return redirect()->back();
        }
        $storage = rtrim((string) config('heratio.storage_path', ''), '/');
        $this->unlinkWeb($row->mp4_path, $storage);
        $this->unlinkWeb($row->mind_path ?? null, $storage);
        DB::table('object_image_ar')->where('id', $id)->delete();
        session()->flash('notice', __('Animation removed.'));
        return redirect()->back();
    }

    /**
     * GET/POST /admin/image-ar/settings
     */
    public function settings(Request $request)
    {
        if ($request->isMethod('post')) {
            $allowed = [
                'ar_enabled', 'ar_user_button',
                'ar_server_url', 'ar_model',
                'ar_num_frames', 'ar_fps', 'ar_motion_bucket_id',
                'ar_default_prompt', 'ar_seed', 'ar_request_timeout',
            ];
            foreach ($allowed as $key) {
                $value = (string) ($request->input($key) ?? '0');
                DB::table('image_ar_settings')->updateOrInsert(
                    ['setting_key' => $key],
                    ['setting_value' => $value, 'updated_at' => now()]
                );
            }
            session()->flash('success', __('Settings saved.'));
            return redirect()->route('admin.image-ar.settings');
        }

        $settings = DB::table('image_ar_settings')->get()->keyBy('setting_key');
        $health = $this->ai->health();
        $stats = [
            'total' => DB::table('object_image_ar')->count(),
            'recent' => DB::table('object_image_ar')->orderByDesc('id')->limit(5)
                ->get(['id', 'object_id', 'ai_model', 'ai_prompt', 'mp4_size', 'generation_secs', 'created_at']),
        ];
        return view('ahg-image-ar::settings', compact('settings', 'stats', 'health'));
    }

    /**
     * @return array{0:?string,1:?\stdClass}
     */
    protected function resolveMasterImage(int $ioId): array
    {
        $do = DB::table('digital_object')
            ->where('object_id', $ioId)
            ->where('mime_type', 'like', 'image/%')
            ->whereNull('parent_id')
            ->orderByRaw("FIELD(usage_id, 140, 141, 142)")
            ->first(['id', 'name', 'path']);
        if (!$do) {
            return [null, null];
        }
        $base = rtrim((string) config('heratio.uploads_path', ''), '/');
        $rel = preg_replace('#^/uploads/r?/?#', '', (string) $do->path);
        $abs = $base . '/' . trim($rel, '/') . '/' . $do->name;
        if (!is_file($abs)) {
            $abs = $base . '/' . $ioId . '/' . $do->name;
        }
        return [is_file($abs) ? $abs : null, $do];
    }

    protected function unlinkWeb(?string $webPath, string $storage, ?string $skipAbs = null): void
    {
        if (!$webPath) {
            return;
        }
        $abs = $storage . '/' . ltrim(preg_replace('#^/uploads/#', 'uploads/', $webPath), '/');
        if ($abs === $skipAbs) {
            return;
        }
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
