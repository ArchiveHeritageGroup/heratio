<?php

namespace AhgIiifCollection\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AhgIiifCollectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-iiif-collection');

        // Auto-seed the workspace-persistence schema (issue #699) on first
        // boot. Wrapped in one outer try/catch per
        // reference_ci_schema_hastable.md - splitting the probe and the
        // install into two try blocks breaks CI on a fresh DB where
        // Schema::hasTable() throws before the table exists.
        try {
            if (!Schema::hasTable('ahg_iiif_workspace')) {
                $sql = @file_get_contents(__DIR__ . '/../../database/install-workspace.sql');
                if ($sql) {
                    DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            // Boot must never fatal because of a transient DB hiccup; the
            // next request retries the probe + install.
        }
    }
}
