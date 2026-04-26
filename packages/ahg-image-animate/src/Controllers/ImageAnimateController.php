<?php

/**
 * ImageAnimateController — Heratio
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

namespace AhgImageAnimate\Controllers;

use AhgImageAnimate\Services\KenBurnsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImageAnimateController extends Controller
{
    public function __construct(private KenBurnsService $kb)
    {
    }

    /**
     * POST /image-animate/generate/{ioId}
     * User-triggered render. Always replaces any existing animation for the IO.
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

        // Write under {storage_path}/uploads/animations/<ioId>/ — that root is
        // www-data-writable (the archive dirs are root-owned and would 403),
        // and the existing nginx /uploads/ alias maps straight to it.
        $storage = rtrim((string) config('heratio.storage_path', ''), '/');
        $stem = pathinfo($sourceDo->name, PATHINFO_FILENAME);
        $filename = 'kenburns_' . $stem . '_' . $opts['motion'] . '_' . substr((string) time(), -6) . '.mp4';
        $absPath = $storage . '/uploads/animations/' . $ioId . '/' . $filename;
        $webPath = '/uploads/animations/' . $ioId . '/' . $filename;

        try {
            $stats = $this->kb->render($sourcePath, $absPath, $opts);
        } catch (\Throwable $e) {
            Log::error('[image-animate] render failed', ['io' => $ioId, 'err' => $e->getMessage()]);
            session()->flash('error', __('Animation failed: ') . $e->getMessage());
            return redirect()->back();
        }

        // Replace any existing row for this IO (and remove the old MP4).
        $existing = DB::table('object_image_animation')->where('object_id', $ioId)->first(['id', 'file_path']);
        if ($existing) {
            $oldAbs = $base . '/' . ltrim(preg_replace('#^/uploads/#', '', (string) $existing->file_path), '/');
            if ($oldAbs !== $absPath && is_file($oldAbs)) {
                @unlink($oldAbs);
            }
            DB::table('object_image_animation')->where('id', $existing->id)->delete();
        }

        DB::table('object_image_animation')->insert([
            'object_id' => $ioId,
            'digital_object_id' => $sourceDo->id,
            'filename' => $filename,
            'file_path' => $webPath,
            'file_size' => $stats['size'],
            'mime_type' => 'video/mp4',
            'mode' => 'kenburns',
            'motion' => $stats['motion'],
            'duration_secs' => $opts['duration_secs'],
            'fps' => $opts['fps'],
            'width' => $opts['width'],
            'height' => $opts['height'],
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session()->flash('notice', __('Animation generated.'));
        return redirect()->back();
    }

    /**
     * POST /image-animate/{id}/delete
     */
    public function delete(Request $request, int $id)
    {
        $row = DB::table('object_image_animation')->where('id', $id)->first(['id', 'file_path', 'object_id']);
        if (!$row) {
            session()->flash('error', __('Animation not found.'));
            return redirect()->back();
        }
        $base = rtrim((string) config('heratio.uploads_path', ''), '/');
        $abs = $base . '/' . ltrim(preg_replace('#^/uploads/#', '', (string) $row->file_path), '/');
        if (is_file($abs)) {
            @unlink($abs);
        }
        DB::table('object_image_animation')->where('id', $id)->delete();
        session()->flash('notice', __('Animation removed.'));
        return redirect()->back();
    }

    /**
     * GET/POST /admin/image-animate/settings
     */
    public function settings(Request $request)
    {
        if ($request->isMethod('post')) {
            $allowed = [
                'animate_enabled', 'animate_user_button',
                'animate_default_motion', 'animate_duration_secs',
                'animate_fps', 'animate_width', 'animate_height', 'animate_zoom_strength',
            ];
            foreach ($allowed as $key) {
                if (!$request->has($key) && !str_starts_with($key, 'animate_enabled') && !str_starts_with($key, 'animate_user_button')) {
                    continue;
                }
                $value = (string) ($request->input($key) ?? '0');
                DB::table('image_animate_settings')->updateOrInsert(
                    ['setting_key' => $key],
                    ['setting_value' => $value, 'updated_at' => now()]
                );
            }
            session()->flash('success', __('Settings saved.'));
            return redirect()->route('admin.image-animate.settings');
        }

        $settings = DB::table('image_animate_settings')->get()->keyBy('setting_key');
        $stats = [
            'total' => DB::table('object_image_animation')->count(),
            'recent' => DB::table('object_image_animation')->orderByDesc('id')->limit(5)
                ->get(['id', 'object_id', 'motion', 'duration_secs', 'file_size', 'created_at']),
        ];
        return view('ahg-image-animate::settings', compact('settings', 'stats'));
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
}
