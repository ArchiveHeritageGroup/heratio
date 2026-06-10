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

    /**
     * Store an uploaded splat and record it. Returns the row id + slug.
     * Optionally attach it to a catalogue record so it renders inline on that
     * record's page (the "3D" viewer mode) - #1193.
     */
    public function store(string $title, UploadedFile $file, ?int $userId, ?int $informationObjectId = null): array
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
            'information_object_id' => $informationObjectId,
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

    /** Splats (newest first), each with the attached record's title + slug when linked. */
    public function list(int $limit = 100): array
    {
        return DB::table('ahg_gaussian_splat as s')
            ->leftJoin('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 's.information_object_id')->where('i.culture', '=', 'en'); })
            ->leftJoin('slug as sl', 'sl.object_id', '=', 's.information_object_id')
            ->orderByDesc('s.id')->limit($limit)
            ->get(['s.*', 'i.title as object_title', 'sl.slug as object_slug'])->all();
    }

    /** Resolve a catalogue record from a numeric id or a slug; null if blank/unknown. */
    public function resolveObjectId(?string $slugOrId): ?int
    {
        $v = trim((string) $slugOrId);
        if ($v === '') {
            return null;
        }
        if (ctype_digit($v)) {
            return DB::table('information_object')->where('id', (int) $v)->exists() ? (int) $v : null;
        }

        return DB::table('slug')->where('slug', $v)->value('object_id') ?: null;
    }

    /** Attach (or detach, with null) a splat to a catalogue record. */
    public function setObject(int $splatId, ?int $informationObjectId): bool
    {
        return DB::table('ahg_gaussian_splat')->where('id', $splatId)
            ->update(['information_object_id' => $informationObjectId, 'updated_at' => now()]) > 0;
    }

    /** Public URL for a stored splat file (served via the public/splats symlink). */
    public function fileUrl(object $splat): ?string
    {
        return $splat->file_name ? '/splats/'.$splat->file_name : null;
    }

    /**
     * Is this .ply a 3D Gaussian-splat export (vs a normal mesh)? Reads the PLY header for the
     * 3DGS signature (spherical-harmonic + scale/rotation vertex properties, no face element).
     * Mesh .ply is left to the standard 3D-model viewer; only 3DGS .ply goes to the gs3d viewer.
     */
    public function isGaussianPly(object $do): bool
    {
        $url = $this->digitalObjectUrl($do);
        // /uploads is served from <storage_path>/uploads (nginx alias), so the file is there.
        $fs = rtrim((string) config('heratio.storage_path'), '/').$url;
        if (! is_readable($fs)) {
            return false;
        }
        $head = @file_get_contents($fs, false, null, 0, 4096) ?: '';

        return (str_contains($head, 'f_dc_0') || (str_contains($head, 'scale_0') && str_contains($head, 'rot_0')))
            && ! preg_match('/element\s+face/i', $head);
    }

    /** Public URL for a splat uploaded as a digital object (path is the AtoM /uploads layout). */
    public function digitalObjectUrl(object $do): string
    {
        $path = trim((string) ($do->path ?? ''));
        $name = (string) ($do->name ?? '');
        // AtoM stores path as a directory (e.g. /uploads/r/<io>/) + a separate name.
        $hasExt = pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION) !== '';
        if (! $hasExt && $name !== '') {
            $path = rtrim($path, '/').'/'.ltrim($name, '/');
        }
        if (! str_starts_with($path, '/') && ! str_starts_with($path, 'http')) {
            $path = '/'.ltrim($path, '/');
        }

        return $path;
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
