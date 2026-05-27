<?php

/**
 * AhgRequestPublishServiceProvider - registers routes, views, and ensures
 * the ahg_publish_request schema + publish_request_status dropdown rows
 * are present on first boot (Heratio #745).
 *
 * Schema is installed idempotently via DB::statement on CREATE TABLE IF
 * NOT EXISTS plus INSERT IGNORE seeds, so re-runs are safe. The probe +
 * install is wrapped in a single outer try/catch per reference_ci_schema_hastable
 * - we never want CI/bootstrap to abort because the DB connection isn't
 * ready yet.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author:    Johan Pieterse <johan@plainsailingisystems.co.za>
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgRequestPublish\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgRequestPublishServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-request-publish');

        $this->ensureSchema();
    }

    /**
     * Idempotent schema + seed installer. Skips entirely if the new
     * ahg_publish_request table already exists; otherwise runs the
     * install_publish_request.sql + seed_publish_request_status.sql files.
     */
    protected function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('ahg_publish_request')) {
                $this->ensureDropdownSeed();

                return;
            }

            $this->runSqlFile(__DIR__.'/../../database/install_publish_request.sql');
            $this->ensureDropdownSeed();
        } catch (Throwable $e) {
            // Never block boot - operator can run install.sql manually.
        }
    }

    /**
     * Best-effort dropdown seed. INSERT IGNORE keeps it idempotent. We only
     * run it when ahg_dropdown exists to avoid noise on minimal installs.
     */
    protected function ensureDropdownSeed(): void
    {
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return;
            }

            $exists = DB::table('ahg_dropdown')
                ->where('taxonomy', 'publish_request_status')
                ->exists();
            if ($exists) {
                return;
            }

            $this->runSqlFile(__DIR__.'/../../database/seed_publish_request_status.sql');
        } catch (Throwable $e) {
            // Never block boot.
        }
    }

    /**
     * Strip SQL comments + split on semicolons, executing each statement.
     * Cribbed from ahg-doi-manage's pattern - keeps -- comment lines from
     * reaching the driver.
     */
    protected function runSqlFile(string $path): void
    {
        $sql = @file_get_contents($path);
        if (! $sql) {
            return;
        }
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $lines = preg_split("/\r?\n/", trim($stmt));
            while ($lines && (trim($lines[0]) === '' || str_starts_with(ltrim($lines[0]), '--'))) {
                array_shift($lines);
            }
            $stmt = trim(implode("\n", $lines));
            if ($stmt === '') {
                continue;
            }
            DB::statement($stmt);
        }
    }
}
