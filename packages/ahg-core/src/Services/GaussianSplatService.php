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

    /** Absolute filesystem path for a splat uploaded as a digital object. */
    public function digitalObjectPath(object $do): string
    {
        return rtrim((string) config('heratio.storage_path'), '/').$this->digitalObjectUrl($do);
    }

    /** Absolute filesystem path for a legacy stored splat file. */
    public function filePath(object $splat): ?string
    {
        return ! empty($splat->file_name)
            ? rtrim((string) config('heratio.splats_path'), '/').'/'.$splat->file_name
            : null;
    }

    /**
     * Bounding box of a splat scene (sampled), so the viewer can frame the camera on the real
     * centre at a fitting distance instead of guessing. Returns ['center'=>[x,y,z],'radius'=>float]
     * or null. Cached in a sidecar <file>.bounds.json so the parse runs once per file. Reads are
     * seek-sampled (a few hundred KB), never the whole multi-MB file.
     */
    public function computeBounds(?string $absPath, ?string $ext): ?array
    {
        if (! $absPath || ! is_file($absPath)) {
            return null;
        }
        $cache = $absPath.'.bounds.json';
        if (is_file($cache) && @filemtime($cache) >= @filemtime($absPath)) {
            $j = json_decode((string) @file_get_contents($cache), true);
            if (is_array($j) && isset($j['center'], $j['radius'])) {
                return $j;
            }
        }
        $ext = strtolower((string) $ext);
        $b = $ext === 'splat' ? $this->boundsFromSplat($absPath)
            : ($ext === 'ply' ? $this->boundsFromPly($absPath) : null);
        if ($b) {
            @file_put_contents($cache, json_encode($b));
        }

        return $b;
    }

    /** Sample a .splat scene (32-byte records, float32 x,y,z first) by seeking. */
    private function boundsFromSplat(string $path): ?array
    {
        $size = @filesize($path);
        if (! $size || $size < 32) {
            return null;
        }
        $n = intdiv($size, 32);
        $step = max(1, intdiv($n, 20000));
        $f = @fopen($path, 'rb');
        if (! $f) {
            return null;
        }
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $cnt = 0;
        for ($i = 0; $i < $n; $i += $step) {
            if (fseek($f, $i * 32) !== 0) {
                break;
            }
            $raw = fread($f, 12);
            if (strlen($raw) < 12) {
                break;
            }
            $v = array_values(unpack('f3', $raw));
            if (! is_finite($v[0]) || ! is_finite($v[1]) || ! is_finite($v[2])) {
                continue;
            }
            for ($a = 0; $a < 3; $a++) {
                $min[$a] = min($min[$a], $v[$a]);
                $max[$a] = max($max[$a], $v[$a]);
            }
            $cnt++;
        }
        fclose($f);

        return $this->boundsResult($min, $max, $cnt);
    }

    /** Sample a binary-little-endian PLY (3DGS export) vertex x,y,z by seeking. */
    private function boundsFromPly(string $path): ?array
    {
        $f = @fopen($path, 'rb');
        if (! $f) {
            return null;
        }
        $header = '';
        while (! feof($f) && strlen($header) < 65536) {
            $line = fgets($f);
            if ($line === false) {
                break;
            }
            $header .= $line;
            if (str_contains($line, 'end_header')) {
                break;
            }
        }
        $dataStart = ftell($f);
        if (! str_contains($header, 'binary_little_endian')) {
            fclose($f);

            return null; // ascii / big-endian not supported - fall back to the default framing
        }
        $sizes = [
            'char' => 1, 'uchar' => 1, 'int8' => 1, 'uint8' => 1,
            'short' => 2, 'ushort' => 2, 'int16' => 2, 'uint16' => 2,
            'int' => 4, 'uint' => 4, 'int32' => 4, 'uint32' => 4, 'float' => 4, 'float32' => 4,
            'double' => 8, 'float64' => 8,
        ];
        $inVertex = false;
        $vertexCount = 0;
        $stride = 0;
        $off = ['x' => null, 'y' => null, 'z' => null];
        foreach (preg_split('/\r\n|\n|\r/', $header) as $ln) {
            $p = preg_split('/\s+/', trim($ln));
            if (($p[0] ?? '') === 'element') {
                $inVertex = (($p[1] ?? '') === 'vertex');
                if ($inVertex) {
                    $vertexCount = (int) ($p[2] ?? 0);
                }

                continue;
            }
            if ($inVertex && ($p[0] ?? '') === 'property') {
                if (($p[1] ?? '') === 'list') {
                    fclose($f);

                    return null;
                }
                $sz = $sizes[$p[1] ?? ''] ?? 0;
                if ($sz === 0) {
                    fclose($f);

                    return null;
                }
                $name = $p[2] ?? '';
                if (array_key_exists($name, $off)) {
                    $off[$name] = $stride;
                }
                $stride += $sz;
            }
        }
        if ($vertexCount < 8 || $stride === 0 || $off['x'] === null || $off['y'] === null || $off['z'] === null) {
            fclose($f);

            return null;
        }
        $step = max(1, intdiv($vertexCount, 20000));
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $cnt = 0;
        for ($i = 0; $i < $vertexCount; $i += $step) {
            $base = $dataStart + $i * $stride;
            $vals = [];
            foreach (['x', 'y', 'z'] as $k) {
                if (fseek($f, $base + $off[$k]) !== 0) {
                    break 2;
                }
                $raw = fread($f, 4);
                if (strlen($raw) < 4) {
                    break 2;
                }
                $u = unpack('f', $raw);
                $vals[] = $u[1];
            }
            if (! is_finite($vals[0]) || ! is_finite($vals[1]) || ! is_finite($vals[2])) {
                continue;
            }
            for ($a = 0; $a < 3; $a++) {
                $min[$a] = min($min[$a], $vals[$a]);
                $max[$a] = max($max[$a], $vals[$a]);
            }
            $cnt++;
        }
        fclose($f);

        return $this->boundsResult($min, $max, $cnt);
    }

    /** Centre + bounding-sphere radius from sampled min/max; null if too few points. */
    private function boundsResult(array $min, array $max, int $cnt): ?array
    {
        if ($cnt < 8) {
            return null;
        }
        $center = [];
        $sumsq = 0.0;
        for ($a = 0; $a < 3; $a++) {
            $center[$a] = ($min[$a] + $max[$a]) / 2;
            $d = $max[$a] - $min[$a];
            $sumsq += $d * $d;
        }
        $radius = sqrt($sumsq) / 2;
        if (! is_finite($radius) || $radius <= 0) {
            return null;
        }

        return ['center' => $center, 'radius' => $radius];
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
