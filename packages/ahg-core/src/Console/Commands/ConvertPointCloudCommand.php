<?php

/**
 * ConvertPointCloudCommand - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\PointCloudConverterService;
use Illuminate\Console\Command;

/**
 * heratio#1183 - convert a point cloud file on disk into a Potree octree and record it.
 * Synchronous; for admin/cron use (the web upload path dispatches a queued job instead).
 */
class ConvertPointCloudCommand extends Command
{
    protected $signature = 'ahg:pointcloud-convert {source : Path to a .las/.laz/.ply file} {--title= : Display title}';

    protected $description = 'Convert a point cloud (.las/.laz/.ply) into a Potree octree (#1183).';

    public function handle(PointCloudConverterService $svc): int
    {
        $source = (string) $this->argument('source');
        if (! is_readable($source)) {
            $this->error("Cannot read: {$source}");

            return self::FAILURE;
        }

        $title = (string) ($this->option('title') ?: pathinfo($source, PATHINFO_FILENAME));
        $row = $svc->createPending($title, basename($source), null);
        $this->info("Created cloud #{$row['id']} ({$row['slug']}) - converting…");

        if ($svc->process($row['id'], $source)) {
            $this->info("Done. View at /pointcloud/{$row['slug']}");

            return self::SUCCESS;
        }
        $this->error('Conversion failed - see ahg_point_cloud.error.');

        return self::FAILURE;
    }
}
