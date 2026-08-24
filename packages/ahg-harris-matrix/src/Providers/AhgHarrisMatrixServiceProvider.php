<?php

/**
 * AhgHarrisMatrixServiceProvider - stratigraphic analysis and interchange.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgHarrisMatrix\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registers under /archaeology/harris - #1483.
 *
 * Everything here is conditional on ahg-archaeology being installed. This
 * plugin adds no tables and owns no entities; it reads the base module's and
 * must be absent, not broken, when that module is not there.
 */
class AhgHarrisMatrixServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The base module owns the /archaeology prefix and registers it from
        // register() to beat the locked /{slug} catch-all. These are CHILD
        // routes of an already-claimed prefix, so boot() is soon enough.
        if (! class_exists(\AhgArchaeology\Services\ArchaeologyService::class)) {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-harris-matrix');

        $this->app->singleton(\AhgHarrisMatrix\Services\HarrisMatrixService::class);

        $this->app['router']->middleware(['web', 'auth'])->prefix('archaeology/harris')->group(function ($router) {
            $router->get('/site/{siteId}/report', [\AhgHarrisMatrix\Controllers\HarrisMatrixController::class, 'report'])
                ->whereNumber('siteId')->name('harris.report');

            $router->get('/site/{siteId}/export.dot', [\AhgHarrisMatrix\Controllers\HarrisMatrixController::class, 'exportDot'])
                ->whereNumber('siteId')->name('harris.export.dot');

            $router->get('/site/{siteId}/datapackage.json', [\AhgHarrisMatrix\Controllers\HarrisMatrixController::class, 'exportDataPackage'])
                ->whereNumber('siteId')->name('harris.export.datapackage');

            $router->match(['get', 'post'], '/site/{siteId}/import-lst', [\AhgHarrisMatrix\Controllers\HarrisMatrixController::class, 'importLst'])
                ->whereNumber('siteId')->name('harris.import.lst')->middleware('acl:update');
        });
    }
}
