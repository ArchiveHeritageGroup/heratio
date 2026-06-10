<?php

/**
 * GaussianSplatService - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1193 - Gaussian-splat photoreal captures. A trained radiance-field scene
 * (.ply / .splat / .ksplat - produced off-platform on a GPU) is uploaded, stored and served to
 * a standalone web viewer. No server-side conversion: the file is the artifact. Built for
 * photoreal capture of fragile heritage (e.g. rock-art panels) alongside LiDAR point clouds.
 */
class GaussianSplatService
{
    public const SUPPORTED = ['ply', 'splat', 'ksplat'];

    /** Store an uploaded splat and record it. Returns the row id + slug. */
    public function store(string $title, UploadedFile $file, ?int $userId): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, self::SUPPORTED, true)) {
            return ['ok' => false, 'id' => 0, 'slug' => '', 'error' => 'Upload a .ply, .splat or .ksplat file.'];
        }

        $title = trim($title) !== '' ? trim($title) : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = $this->uniqueSlug($title);
        $now = now();

        $id = (int) DB::table('ahg_gaussian_splat')->insertGetId([
            'slug' => $slug, 'title' => $title, 'source_filename' => $file->getClientOriginalName(),
            'format' => $ext, 'status' => 'ready', 'created_by' => $userId,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $dir = rtrim((string) config('heratio.splats_path'), '/');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fileName = $id.'.'.$ext;
        try {
            $file->move($dir, $fileName);
        } catch (\Throwable $e) {
            DB::table('ahg_gaussian_splat')->where('id', $id)->update(['status' => 'failed', 'error' => 'Could not store the upload.', 'updated_at' => now()]);

            return ['ok' => false, 'id' => $id, 'slug' => $slug, 'error' => 'Could not store the upload.'];
        }

        DB::table('ahg_gaussian_splat')->where('id', $id)->update([
            'file_name' => $fileName, 'size_bytes' => @filesize($dir.'/'.$fileName) ?: null, 'updated_at' => now(),
        ]);

        return ['ok' => true, 'id' => $id, 'slug' => $slug];
    }

    public function getBySlug(string $slug): ?object
    {
        return DB::table('ahg_gaussian_splat')->where('slug', $slug)->first();
    }

    public function list(int $limit = 100): array
    {
        return DB::table('ahg_gaussian_splat')->orderByDesc('id')->limit($limit)->get()->all();
    }

    /** Public URL for a stored splat file (served via the public/splats symlink). */
    public function fileUrl(object $splat): ?string
    {
        return $splat->file_name ? '/splats/'.$splat->file_name : null;
    }

    private function uniqueSlug(string $title): string
    {
        $base = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($title))), '-');
        $base = $base !== '' ? mb_substr($base, 0, 80) : 'splat';
        $slug = $base;
        $n = 1;
        while (DB::table('ahg_gaussian_splat')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
