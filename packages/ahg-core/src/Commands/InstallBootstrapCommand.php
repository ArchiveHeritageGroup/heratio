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

            // Always show per-package status if NOTHING ran — that is always
            // a CI-blocking signal (e.g. a DB connection error in the bootstrap
            // phase that is otherwise invisible because PackageInstaller logs
            // warnings to storage/logs/laravel.log only).
            $printAll = $result['ran'] === 0 || $this->getOutput()->isVerbose();
            if ($printAll) {
                foreach ($result['files'] as $pkg => $status) {
                    $this->line(sprintf('      %-32s %s', $pkg, $status));
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
