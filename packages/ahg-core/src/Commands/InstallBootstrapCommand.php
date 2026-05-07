<?php
/*
 * Heratio — heratio:install-bootstrap artisan command
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems
 * Licensed under AGPL-3.0-or-later. See LICENSE.
 */

namespace AhgCore\Commands;

use AhgCore\Services\PackageInstaller;
use Illuminate\Console\Command;

/**
 * Standalone install plan §6 stage 9.
 *
 * Iterates every packages/<name>/database/install.sql and runs it when the
 * package's sentinel table is missing. Idempotent — already-installed
 * packages are skipped. Two-pass option lets cross-plugin INSERTs land
 * after their dependency tables exist.
 *
 *   php artisan heratio:install-bootstrap
 *   php artisan heratio:install-bootstrap --pass=2
 *   php artisan heratio:install-bootstrap --packages-root=/usr/share/nginx/heratio/packages
 */
class InstallBootstrapCommand extends Command
{
    protected $signature = 'heratio:install-bootstrap
        {--pass=1 : 1 = single pass, 2 = two-pass (cross-plugin INSERTs land on second pass)}
        {--packages-root= : Override the packages root (defaults to base_path("packages"))}';

    protected $description = 'Run every package install.sql whose sentinel table is missing (Phase 1 #6 / install plan §6 stage 9).';

    public function handle(): int
    {
        $packagesRoot = $this->option('packages-root') ?: base_path('packages');
        $passes       = (int) $this->option('pass');

        if (!is_dir($packagesRoot)) {
            $this->error("packages-root not a directory: {$packagesRoot}");
            return self::FAILURE;
        }

        for ($pass = 1; $pass <= max(1, $passes); $pass++) {
            $this->info("==> heratio:install-bootstrap pass {$pass} of {$passes}");
            $result = PackageInstaller::installAll($packagesRoot);
            $this->line("    ran={$result['ran']}  skipped={$result['skipped']}");

            // Always print per-file rows for any non-installed status. Skips
            // are otherwise invisible — PackageInstaller logs warnings to
            // storage/logs/laravel.log only, and that file is too noisy to
            // tail meaningfully in CI (a single failing install.sql can
            // produce a kilobyte of SQL inside its exception message). With
            // -vvv every file prints, success or skip; default mode prints
            // only skips, keeping the green-path output quiet.
            $verbose = $this->getOutput()->isVerbose();
            foreach ($result['files'] as $pkg => $status) {
                if ($verbose || $status !== 'installed') {
                    $this->line(sprintf('      %-40s %s', $pkg, $status));
                }
            }

            if ($pass < $passes && $result['ran'] === 0) {
                $this->line('    (no further work to do — exiting early)');
                break;
            }
        }

        return self::SUCCESS;
    }
}
