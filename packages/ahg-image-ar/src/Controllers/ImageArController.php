<?php

/**
 * ImageArController — Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgImageAr\Controllers;

use AhgImageAr\Services\KenBurnsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImageArController extends Controller
{
    public function __construct(private KenBurnsService $kb)
    {
    }

    /**
     * POST /image-ar/generate/{ioId}
     * Render a Ken Burns MP4 from the IO's master image. Atomic replace.
     */
    public function userGenerate(Request $request, int $ioId)
    {
        if (!$this->kb->isEnabled()) {
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

        $opts = $this->kb->defaults();
        if ($request->filled('motion')) {
            $opts['motion'] = (string) $request->input('motion');
        }

        $storage = rtrim((string) config('heratio.storage_path', ''), '/');
        $stem = pathinfo($sourceDo->name, PATHINFO_FILENAME);
        $stamp = substr((string) time(), -6);
        $mp4Filename = 'kenburns_' . $stem . '_' . $opts['motion'] . '_' . $stamp . '.mp4';
        $mp4Abs = $storage . '/uploads/ar/' . $ioId . '/' . $mp4Filename;
        $mp4Web = '/uploads/ar/' . $ioId . '/' . $mp4Filename;

        try {
            $kbStats = $this->kb->render($sourcePath, $mp4Abs, $opts);
        } catch (\Throwable $e) {
            Log::error('[image-ar] mp4 render failed', ['io' => $ioId, 'err' => $e->getMessage()]);
            session()->flash('error', __('Animation failed: ') . $e->getMessage());
            return redirect()->back();
        }

        // Replace any existing row + delete its old file.
        $existing = DB::table('object_image_ar')->where('object_id', $ioId)->first();
        if ($existing) {
            $this->unlinkWeb($existing->mp4_path, $storage, $mp4Abs);
            $this->unlinkWeb($existing->mind_path, $storage); // legacy AR tracker, may be present
            DB::table('object_image_ar')->where('id', $existing->id)->delete();
        }

        DB::table('object_image_ar')->insert([
            'object_id' => $ioId,
            'digital_object_id' => $sourceDo->id,
            'mp4_filename' => $mp4Filename,
            'mp4_path' => $mp4Web,
            'mp4_size' => $kbStats['size'],
            'mp4_motion' => $kbStats['motion'],
            'mp4_duration_secs' => $opts['duration_secs'],
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session()->flash('notice', sprintf(
            __('Animation generated — %d KB.'),
            (int) ($kbStats['size'] / 1024)
        ));
        return redirect()->back();
    }

    /**
     * POST /image-ar/{id}/delete
     */
    public function delete(Request $request, int $id)
    {
        $row = DB::table('object_image_ar')->where('id', $id)->first();
        if (!$row) {
            session()->flash('error', __('Animation not found.'));
            return redirect()->back();
        }
        $storage = rtrim((string) config('heratio.storage_path', ''), '/');
        $this->unlinkWeb($row->mp4_path, $storage);
        $this->unlinkWeb($row->mind_path, $storage);
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
                'ar_default_motion', 'ar_duration_secs',
                'ar_fps', 'ar_width', 'ar_height', 'ar_zoom_strength',
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
        $stats = [
            'total' => DB::table('object_image_ar')->count(),
            'recent' => DB::table('object_image_ar')->orderByDesc('id')->limit(5)
                ->get(['id', 'object_id', 'mp4_motion', 'mp4_size', 'mp4_duration_secs', 'created_at']),
        ];
        return view('ahg-image-ar::settings', compact('settings', 'stats'));
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
