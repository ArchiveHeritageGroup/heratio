<?php

/**
 * SplatSetupCommand - Heratio ahg-core
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
 * heratio#1193 - publish the Gaussian-splat storage dir as a public symlink so the viewer can
 * stream splat files statically (kept out of git, provisioned per host). Idempotent.
 */
class SplatSetupCommand extends Command
{
    protected $signature = 'ahg:splat-setup';

    protected $description = 'Publish the Gaussian-splat storage as a public symlink (#1193).';

    public function handle(): int
    {
        $clouds = rtrim((string) config('heratio.splats_path'), '/');
        if (! is_dir($clouds) && ! @mkdir($clouds, 0775, true) && ! is_dir($clouds)) {
            $this->error("Could not create splats storage: {$clouds}");

            return self::FAILURE;
        }
        // If run as root, hand the upload dir to the web user so web uploads can write to it.
        if (function_exists('posix_getuid') && posix_getuid() === 0 && function_exists('chown')) {
            @chown($clouds, 'www-data');
            @chgrp($clouds, 'www-data');
        }
        $link = public_path('splats');
        if (is_link($link)) {
            @unlink($link);
        }
        if (file_exists($link)) {
            $this->warn("{$link} exists and is not a symlink - left as-is.");

            return self::SUCCESS;
        }
        if (@symlink($clouds, $link)) {
            $this->info("Splat storage: {$link} -> {$clouds}");

            return self::SUCCESS;
        }
        $this->error("Could not link {$link} -> {$clouds}");

        return self::FAILURE;
    }
}
