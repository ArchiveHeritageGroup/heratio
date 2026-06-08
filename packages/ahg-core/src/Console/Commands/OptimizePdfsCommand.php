<?php

/**
 * OptimizePdfsCommand - Console command for Heratio
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

use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\PdfWebOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: generate a web-optimized (downsampled + linearized) PDF derivative
 * for large PDF masters so the document viewer shows page 1 quickly instead of
 * pulling the whole master over the wire. A 50-200MB scan typically becomes a
 * few MB.
 *
 * The MASTER is never modified - the optimized copy is added as a separate
 * reference (usage_id 141, mime application/pdf) digital object alongside it,
 * which the viewer prefers for display while keeping the master for download.
 *
 * Dry-run by default; --commit applies. Idempotent: a master that already has
 * a web-PDF reference is skipped, so this is safe to schedule.
 */
class OptimizePdfsCommand extends Command
{
    protected $signature = 'ahg:optimize-pdfs '
        .'{--commit : Actually generate + register derivatives (otherwise dry-run)} '
        .'{--min-mb=20 : Only PDFs larger than this} '
        .'{--dpi=200 : Target resolution for colour/grey images (mono = dpi*1.5, capped 600)} '
        .'{--max-ratio=0.8 : Only keep the derivative when it is at most this fraction of the master size} '
        .'{--limit=0 : Max PDFs to process (0 = all)} '
        .'{--id= : Restrict to one information_object (object_id)}';

    protected $description = 'Generate web-optimized PDF derivatives so large documents load fast in the viewer';

    public function handle(PdfWebOptimizationService $svc): int
    {
        if (! $svc->toolsAvailable()) {
            $this->error('PDF tools (ghostscript + qpdf) not installed on this host. See docs/pdf-web-optimisation-setup.md.');

            return 1;
        }

        $min = (float) $this->option('min-mb') * 1048576;
        $dpi = (int) $this->option('dpi');
        $maxRatio = (float) $this->option('max-ratio');
        $commit = (bool) $this->option('commit');

        // Master PDFs (no parent) over the threshold that do not yet have a web-PDF reference.
        $q = DB::table('digital_object')
            ->where('mime_type', 'application/pdf')
            ->whereNull('parent_id')
            ->where('byte_size', '>', $min);
        if ($this->option('id')) {
            $q->where('object_id', (int) $this->option('id'));
        }
        $rows = $q->orderByDesc('byte_size')->get()
            ->filter(fn ($r) => ! DigitalObjectService::getWebPdfReference((int) $r->object_id))
            ->values();
        if ((int) $this->option('limit') > 0) {
            $rows = $rows->take((int) $this->option('limit'));
        }

        $this->info(($commit ? 'COMMIT' : 'DRY-RUN').': '.$rows->count().' large PDF(s) over '.$this->option('min-mb').'MB without a web derivative (dpi='.$dpi.')');
        if ($rows->isEmpty()) {
            return 0;
        }

        $done = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            $mb = round(($r->byte_size ?: 0) / 1048576, 1);
            $abs = DigitalObjectService::resolveDiskPath($r);
            if (! $abs) {
                $this->warn("  io#{$r->object_id} {$r->name} {$mb}MB - file missing on disk, skip");
                $skipped++;
                continue;
            }
            if (! $commit) {
                $this->line("  io#{$r->object_id} {$r->name} {$mb}MB -> would optimize");
                continue;
            }

            $out = $svc->optimize($abs, $dpi);
            if (! $out) {
                $this->error("  io#{$r->object_id} {$r->name} - optimize FAILED (see log)");
                $skipped++;
                continue;
            }
            $newBytes = filesize($out);
            // Only worth it if meaningfully smaller (otherwise the master was already lean).
            if ($newBytes > ($r->byte_size * $maxRatio)) {
                $this->line("  io#{$r->object_id} {$r->name} {$mb}MB -> derivative ".round($newBytes / 1048576, 1).'MB not small enough (> '.($maxRatio * 100).'% of master), skip');
                $svc->cleanupDirOf($out);
                $skipped++;
                continue;
            }

            $base = pathinfo((string) $r->name, PATHINFO_FILENAME);
            $newName = 'reference_'.$base.'.web.pdf';
            $destDir = dirname($abs);
            $dest = $destDir.'/'.$newName;
            if (! @copy($out, $dest)) {
                $this->error("  io#{$r->object_id} - could not write {$newName} into {$destDir}");
                $svc->cleanupDirOf($out);
                $skipped++;
                continue;
            }
            $svc->cleanupDirOf($out);
            @chmod($dest, 0664);
            @chown($dest, 'www-data');
            @chgrp($dest, 'www-data');

            $derivBytes = filesize($dest);
            $checksum = md5_file($dest) ?: null;
            $now = now()->format('Y-m-d H:i:s');
            $derivObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitDigitalObject',
                'created_at' => $now,
                'updated_at' => $now,
                'serial_number' => 0,
            ]);
            DB::table('digital_object')->insert([
                'id' => $derivObjectId,
                'object_id' => (int) $r->object_id,
                'usage_id' => DigitalObjectService::USAGE_REFERENCE,
                'mime_type' => 'application/pdf',
                'media_type_id' => DigitalObjectService::MEDIA_TEXT,
                'name' => $newName,
                'path' => $r->path,
                'byte_size' => $derivBytes,
                'checksum' => $checksum,
                'checksum_type' => 'md5',
                'parent_id' => (int) $r->id,
            ]);
            $this->info("  io#{$r->object_id} {$r->name} {$mb}MB -> {$newName} ".round($derivBytes / 1048576, 2).'MB (web reference #'.$derivObjectId.')');
            $done++;
        }

        $this->info("Done. {$done} optimized, {$skipped} skipped. Masters untouched.");

        return 0;
    }
}
