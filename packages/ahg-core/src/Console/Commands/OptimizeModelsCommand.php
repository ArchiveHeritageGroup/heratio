<?php

/**
 * OptimizeModelsCommand - Console command for Heratio
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
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\ModelCompressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: convert + Draco-compress existing oversized 3D model masters
 * (OBJ/GLB/GLTF) so they load in the exhibition walkthrough instead of
 * showing a placeholder. The original master file is kept on disk; only the
 * digital_object row is re-pointed at the compressed .glb.
 *
 * Dry-run by default. Use --commit to apply. Original DB rows are backed up
 * to storage/app/ first (a restore .sql is written) so the change is reversible.
 */
class OptimizeModelsCommand extends Command
{
    protected $signature = 'ahg:optimize-models {--commit : Actually compress + update (otherwise dry-run)} {--min-mb=20 : Only models larger than this} {--limit=0 : Max models to process (0 = all)} {--id= : Restrict to one information_object id}';

    protected $description = 'Convert + Draco-compress oversized 3D models so the walkthrough can load them';

    public function handle(ModelCompressionService $svc): int
    {
        if (! $svc->toolsAvailable()) {
            $this->error('Model tools not installed on this host. See docs/model-optimisation-setup.md.');

            return 1;
        }

        $min = (float) $this->option('min-mb') * 1048576;
        $q = DB::table('digital_object')->where('usage_id', 140)->where('byte_size', '>', $min);
        if ($this->option('id')) {
            $q->where('object_id', (int) $this->option('id'));
        }
        $rows = $q->orderByDesc('byte_size')->get()
            ->filter(fn ($r) => in_array(strtolower(pathinfo((string) $r->name, PATHINFO_EXTENSION)), ModelCompressionService::SUPPORTED, true))
            // Skip our own optimised outputs (the "-opt.glb" suffix) so the hourly schedule does not
            // re-compress a model that is still over the threshold (e.g. a texture-heavy glb).
            ->filter(fn ($r) => ! str_ends_with(strtolower((string) $r->name), '-opt.glb'))
            ->values();
        if ((int) $this->option('limit') > 0) {
            $rows = $rows->take((int) $this->option('limit'));
        }

        $commit = (bool) $this->option('commit');
        $this->info(($commit ? 'COMMIT' : 'DRY-RUN').': '.$rows->count().' oversized 3D model(s) over '.$this->option('min-mb').'MB');
        if ($rows->isEmpty()) {
            return 0;
        }

        if ($commit) {
            $this->backup($rows);
        }

        $storage = rtrim((string) config('heratio.storage_path'), '/');
        foreach ($rows as $r) {
            $ext = strtolower(pathinfo((string) $r->name, PATHINFO_EXTENSION));
            $abs = $storage.$r->path.$r->name;
            $mb = round($r->byte_size / 1048576, 1);
            if (! is_file($abs)) {
                $this->warn("  io#{$r->object_id} {$r->name} {$mb}MB - file missing, skip");
                continue;
            }
            if (! $commit) {
                $this->line("  io#{$r->object_id} {$r->name} {$mb}MB -> would compress");
                continue;
            }

            $out = $svc->compressToGlb($abs, $ext);
            if (! $out) {
                $this->error("  io#{$r->object_id} {$r->name} - compress FAILED (see log)");
                continue;
            }
            $newName = pathinfo((string) $r->name, PATHINFO_FILENAME).'.glb';
            if ($newName === $r->name) {
                $newName = pathinfo((string) $r->name, PATHINFO_FILENAME).'-opt.glb';
            }
            $dest = dirname($abs).'/'.$newName;
            @copy($out, $dest);
            @unlink($out);
            @rmdir(dirname($out));
            @chmod($dest, 0664);
            @chown($dest, 'www-data');
            @chgrp($dest, 'www-data');
            if (! is_file($dest)) {
                $this->error("  io#{$r->object_id} - could not write {$newName}");
                continue;
            }
            $newBytes = filesize($dest);
            DB::table('digital_object')->where('id', $r->id)->update([
                'name' => $newName,
                'mime_type' => 'model/gltf-binary',
                'byte_size' => $newBytes,
            ]);
            $this->info("  io#{$r->object_id} {$r->name} {$mb}MB -> {$newName} ".round($newBytes / 1048576, 2).'MB');
        }

        $this->info('Done. Originals kept on disk; restore SQL in storage/app if needed.');

        return 0;
    }

    /** Write a restore .sql of the original digital_object rows before mutating them. */
    private function backup($rows): void
    {
        $lines = ['-- Restore original digital_object rows (ahg:optimize-models backup)'];
        foreach ($rows as $r) {
            $lines[] = sprintf(
                "UPDATE digital_object SET name=%s, mime_type=%s, byte_size=%d WHERE id=%d;",
                DB::getPdo()->quote((string) $r->name),
                DB::getPdo()->quote((string) $r->mime_type),
                (int) $r->byte_size,
                (int) $r->id
            );
        }
        $file = storage_path('app/optimize-models-restore-'.date('Ymd-His').'.sql');
        @file_put_contents($file, implode("\n", $lines)."\n");
        $this->line('Backup restore SQL: '.$file);
    }
}
