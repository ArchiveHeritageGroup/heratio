<?php

/**
 * ThreeDDerivativesCommand — generate Blender thumbnails for 3D digital objects.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Ahg3dModel\Commands;

use Ahg3dModel\Services\ThreeDThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ThreeDDerivativesCommand extends Command
{
    protected $signature = 'ahg:3d-derivatives {--id=} {--force} {--dry-run}';

    protected $description = 'Generate 3D model thumbnails via Blender';

    public function handle(ThreeDThumbnailService $thumbs): int
    {
        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');

        if ($id = $this->option('id')) {
            $obj = DB::table('digital_object')->where('id', (int) $id)->first();
            if (! $obj) { $this->error("digital_object #{$id} not found"); return self::FAILURE; }
            if (! $thumbs->is3DModel($obj->name ?? '')) {
                $this->error("digital_object #{$id} is not a 3D model ({$obj->name})");
                return self::FAILURE;
            }
            if ($dry) { $this->info("would generate derivatives for #{$id}"); return self::SUCCESS; }
            $ok = $thumbs->createDerivatives((int) $id);
            $this->info($ok ? "generated derivatives for #{$id}" : "failed: #{$id}");
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        if ($dry) {
            $this->info('dry-run: scanning for 3D models without thumbnails');
            return self::SUCCESS;
        }

        $r = $thumbs->batchProcessExisting();
        $this->info(sprintf('processed=%d success=%d failed=%d',
            $r['processed'] ?? 0, $r['success'] ?? 0, $r['failed'] ?? 0));
        return ($r['failed'] ?? 0) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
