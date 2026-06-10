<?php

/**
 * PointCloudConverterService - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * heratio#1183 - convert LiDAR / photogrammetry point clouds (.las/.laz, and .ply) into a
 * Potree octree the browser can stream. Built for documenting fragile heritage surfaces such
 * as rock-art panels and shelters, where a scan doubles as a dated conservation baseline.
 *
 * .e57 (terrestrial-scanner format) needs a PDAL pre-conversion to .laz and is reported as
 * unsupported until PDAL is installed - never converted silently or half-way.
 */
class PointCloudConverterService
{
    /** Input extensions PotreeConverter reads directly. */
    public const SUPPORTED = ['las', 'laz', 'ply'];

    /** Needs a PDAL pre-pass we don't have yet - reported, not attempted. */
    public const NEEDS_TOOLING = ['e57'];

    /** Convert a source cloud into an octree under pointclouds_path/<dir>. Returns a result. */
    public function convert(string $sourcePath, string $octreeDir): array
    {
        $out = ['ok' => false, 'point_count' => null, 'error' => ''];
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if (in_array($ext, self::NEEDS_TOOLING, true)) {
            $out['error'] = strtoupper($ext).' needs server-side tooling (PDAL) that is not installed yet. Export to .las/.laz and re-upload, or ask an administrator to enable .e57 support.';

            return $out;
        }
        if (! in_array($ext, self::SUPPORTED, true)) {
            $out['error'] = 'Unsupported format. Upload a .las, .laz or .ply point cloud.';

            return $out;
        }
        if (! is_readable($sourcePath)) {
            $out['error'] = 'Source file is not readable.';

            return $out;
        }

        $bin = (string) config('heratio.pointcloud_bin');
        if (! is_file($bin) || ! is_executable($bin)) {
            $out['error'] = 'Point-cloud converter is not installed on this server.';
            Log::warning('[ahg-core] PotreeConverter missing at '.$bin);

            return $out;
        }

        $targetDir = rtrim((string) config('heratio.pointclouds_path'), '/').'/'.$octreeDir;
        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            $out['error'] = 'Could not create the output directory.';

            return $out;
        }

        // PotreeConverter <source> -o <outdir>  (octree only; the viewer libs are shared).
        $proc = new Process([$bin, $sourcePath, '-o', $targetDir]);
        $proc->setTimeout(3600);   // big scans take time
        try {
            $proc->run();
        } catch (\Throwable $e) {
            $out['error'] = 'Conversion failed to start: '.$e->getMessage();

            return $out;
        }

        if (! $proc->isSuccessful() || ! is_file($targetDir.'/metadata.json')) {
            Log::warning('[ahg-core] PotreeConverter failed', ['dir' => $octreeDir, 'err' => mb_substr($proc->getErrorOutput(), 0, 500)]);
            $out['error'] = 'Conversion failed. The file may be corrupt or not a supported point cloud.';

            return $out;
        }

        $out['point_count'] = $this->readPointCount($targetDir.'/metadata.json');
        $out['ok'] = true;

        return $out;
    }

    /** Pull the total point count out of the Potree metadata.json. */
    private function readPointCount(string $metadataPath): ?int
    {
        try {
            $meta = json_decode((string) file_get_contents($metadataPath), true);

            return isset($meta['points']) ? (int) $meta['points'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---- Persistence (ahg_point_cloud) ----

    /** Create a pending cloud row; returns [id, slug, octree_dir]. */
    public function createPending(string $title, string $sourceFilename, ?int $userId): array
    {
        $slug = $this->uniqueSlug($title !== '' ? $title : pathinfo($sourceFilename, PATHINFO_FILENAME));
        $now = now();
        $id = (int) DB::table('ahg_point_cloud')->insertGetId([
            'slug' => $slug, 'title' => $title !== '' ? $title : $sourceFilename,
            'source_filename' => $sourceFilename, 'octree_dir' => null, 'status' => 'pending',
            'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return ['id' => $id, 'slug' => $slug, 'octree_dir' => 'cloud-'.$id];
    }

    /** Run conversion for a stored cloud row and update its status. */
    public function process(int $id, string $sourcePath): bool
    {
        $row = DB::table('ahg_point_cloud')->where('id', $id)->first();
        if (! $row) {
            return false;
        }
        // Idempotency / duplicate-dispatch guard (database queue retry_after can re-fire a
        // long conversion): already done, or another worker is mid-conversion -> don't re-run.
        if ($row->status === 'ready') {
            return true;
        }
        if ($row->status === 'processing' && $row->updated_at && now()->diffInHours($row->updated_at) < 6) {
            return false;
        }
        $dir = $row->octree_dir ?: ('cloud-'.$id);
        DB::table('ahg_point_cloud')->where('id', $id)->update(['status' => 'processing', 'octree_dir' => $dir, 'updated_at' => now()]);

        $res = $this->convert($sourcePath, $dir);
        DB::table('ahg_point_cloud')->where('id', $id)->update([
            'status' => $res['ok'] ? 'ready' : 'failed',
            'point_count' => $res['point_count'],
            'error' => $res['ok'] ? null : mb_substr((string) $res['error'], 0, 1000),
            'updated_at' => now(),
        ]);

        return $res['ok'];
    }

    public function getBySlug(string $slug): ?object
    {
        return DB::table('ahg_point_cloud')->where('slug', $slug)->first();
    }

    public function list(int $limit = 100): array
    {
        return DB::table('ahg_point_cloud')->orderByDesc('id')->limit($limit)->get()->all();
    }

    private function uniqueSlug(string $title): string
    {
        $base = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($title))), '-');
        $base = $base !== '' ? mb_substr($base, 0, 80) : 'cloud';
        $slug = $base;
        $n = 1;
        while (DB::table('ahg_point_cloud')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
