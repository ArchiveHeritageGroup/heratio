<?php

/**
 * AhgArchaeologyServiceProvider - archaeology collections management.
 *
 * Registered explicitly in bootstrap/providers.php with a PSR-4 autoload entry
 * in the root composer.json, following ahg-articles and ahg-marketing. That
 * avoids a composer require and so leaves composer.lock untouched.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgArchaeology\Providers;

use Illuminate\Support\ServiceProvider;

class AhgArchaeologyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ahg-archaeology.php', 'ahg-archaeology');

        // `/archaeology` is a single top-level segment, so it must be registered
        // before the locked `/{slug}` catch-all or the dashboard 404s while its
        // own child routes resolve normally. Loading the route file from boot()
        // is too late. Same approach as ahg-articles; see
        // memory/reference_slug_catchall_route_precedence.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware(['web', 'auth'])->prefix('archaeology')->group(function () use ($router) {
                $router->get('/', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'index'])
                    ->name('archaeology.index');

                $router->get('/sites', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'sites'])
                    ->name('archaeology.sites');
                $router->get('/site/{id}', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'site'])
                    ->whereNumber('id')->name('archaeology.site');

                $router->get('/objects', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'objects'])
                    ->name('archaeology.objects');
                $router->get('/object/{id}', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'object'])
                    ->whereNumber('id')->name('archaeology.object');

                // Site + find data-entry (the module's missing CRUD) - #1428 Phase 4.
                // {id} is whereNumber, so 'create' is never captured as an id.
                $router->get('/site/create', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'siteCreate'])
                    ->name('archaeology.site.create');
                $router->post('/site', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'siteSave'])
                    ->name('archaeology.site.store');
                $router->get('/site/{id}/edit', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'siteEdit'])
                    ->whereNumber('id')->name('archaeology.site.edit');
                $router->post('/site/{id}', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'siteSave'])
                    ->whereNumber('id')->name('archaeology.site.update');

                $router->get('/object/create', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'findCreate'])
                    ->name('archaeology.object.create');
                $router->post('/object', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'findSave'])
                    ->name('archaeology.object.store');
                $router->get('/object/{id}/edit', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'findEdit'])
                    ->whereNumber('id')->name('archaeology.object.edit');
                $router->post('/object/{id}', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'findSave'])
                    ->whereNumber('id')->name('archaeology.object.update');

                // Stratigraphic contexts (layers) - #1428 Phase 1. 'create' is
                // declared before '{id}' so it is not captured as an id.
                $router->get('/site/{siteId}/contexts', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contexts'])
                    ->whereNumber('siteId')->name('archaeology.contexts');
                // Contexts of a site as JSON, for the find form's context picker - #1428 Phase 4b.
                $router->get('/site/{siteId}/contexts.json', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contextsJson'])
                    ->whereNumber('siteId')->name('archaeology.contexts.json');

                // CSV import of contexts + relationships - #1428 Phase 4b.
                $router->get('/site/{siteId}/contexts/import', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contextImportForm'])
                    ->whereNumber('siteId')->name('archaeology.contexts.import');
                $router->get('/site/{siteId}/contexts/import/template', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contextImportTemplate'])
                    ->whereNumber('siteId')->name('archaeology.contexts.import.template');
                $router->post('/site/{siteId}/contexts/import', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contextImport'])
                    ->whereNumber('siteId')->name('archaeology.contexts.import.run');

                $router->get('/context/create', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contextCreate'])
                    ->name('archaeology.context.create');
                $router->post('/context', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contextSave'])
                    ->name('archaeology.context.store');
                $router->get('/context/{id}', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'context'])
                    ->whereNumber('id')->name('archaeology.context');
                $router->get('/context/{id}/edit', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contextEdit'])
                    ->whereNumber('id')->name('archaeology.context.edit');
                // Context recording sheet PDF - #1428 Phase 4b.
                $router->get('/context/{id}/pdf', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contextPdf'])
                    ->whereNumber('id')->name('archaeology.context.pdf');
                $router->post('/context/{id}', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'contextSave'])
                    ->whereNumber('id')->name('archaeology.context.update');

                // Stratigraphic relationships (Harris Matrix) - #1428 Phase 2.
                $router->post('/context/{id}/relationship', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'relationshipStore'])
                    ->whereNumber('id')->name('archaeology.context.relationship.store');
                $router->post('/context/{id}/relationship/{relId}/delete', [\AhgArchaeology\Controllers\ArchaeologyController::class, 'relationshipDelete'])
                    ->whereNumber('id')->whereNumber('relId')->name('archaeology.context.relationship.delete');
            });
        });
    }

    public function boot(): void
    {
        // Migrations must be loaded here or they silently never run and prod
        // drifts behind dev without any error.
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-archaeology');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgArchaeology\Console\Commands\ArchaeologySeedVocabulariesCommand::class,
            ]);
        }
    }
}
