<?php

/**
 * PointCloudSetupCommand - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Console\Commands;

use Illuminate\Console\Command;

/**
 * heratio#1183 - one-time host setup for the point-cloud viewer: publish the Potree viewer
 * libs and the octree directory as public symlinks (kept out of git, provisioned per host
 * alongside the PotreeConverter install). Idempotent.
 */
class PointCloudSetupCommand extends Command
{
    protected $signature = 'ahg:pointcloud-setup';

    protected $description = 'Publish Potree viewer libs + octree storage as public symlinks (#1183).';

    public function handle(): int
    {
        $public = public_path();
        $bin = (string) config('heratio.pointcloud_bin');
        $libs = (string) config('heratio.pointcloud_libs');
        $clouds = rtrim((string) config('heratio.pointclouds_path'), '/');

        if (! is_file($bin)) {
            $this->warn("PotreeConverter not found at {$bin} - install it first (docs/pointcloud-setup.md).");
        } else {
            $this->info("PotreeConverter: {$bin}");
        }

        // Potree viewer libs -> /vendor/potree/libs
        $vendorLink = $public.'/vendor/potree';
        if (! is_dir(dirname($vendorLink))) {
            @mkdir(dirname($vendorLink), 0775, true);
        }
        $this->linkInto($vendorLink, dirname($libs), 'Potree viewer libs');

        // Octree storage -> /pointclouds
        if (! is_dir($clouds)) {
            @mkdir($clouds, 0775, true);
        }
        $this->linkInto($public.'/pointclouds', $clouds, 'Octree storage');

        $this->info('Point-cloud setup complete.');

        return self::SUCCESS;
    }

    private function linkInto(string $link, string $target, string $label): void
    {
        if (is_link($link)) {
            @unlink($link);
        }
        if (file_exists($link)) {
            $this->warn("{$label}: {$link} exists and is not a symlink - left as-is.");

            return;
        }
        if (@symlink($target, $link)) {
            $this->info("{$label}: {$link} -> {$target}");
        } else {
            $this->error("{$label}: could not link {$link} -> {$target}");
        }
    }
}
