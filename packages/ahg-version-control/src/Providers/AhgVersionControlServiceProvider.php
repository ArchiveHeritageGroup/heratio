<?php

/**
 * AhgVersionControlServiceProvider — service provider for the AHG Version Control package.
 *
 * Phase A — schema only. Migrations are loaded; routes, observers and services
 * register in subsequent build phases.
 *
 * Copyright (C) 2026 The Archive and Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. AGPL-3.0.
 */

namespace AhgVersionControl\Providers;

use AhgCore\Models\Actor;
use AhgCore\Models\InformationObject;
use AhgVersionControl\Console\BackfillCommand;
use AhgVersionControl\Console\CaptureCommand;
use AhgVersionControl\Console\DiffCommand;
use AhgVersionControl\Console\PruneCommand;
use AhgVersionControl\Console\SnapshotSmokeCommand;
use AhgVersionControl\Observers\ActorSnapshotObserver;
use AhgVersionControl\Observers\InformationObjectSnapshotObserver;
use AhgVersionControl\Services\DiffComputer;
use AhgVersionControl\Services\RestoreService;
use AhgVersionControl\Services\SnapshotBuilder;
use AhgVersionControl\Services\VersionWriter;
use Illuminate\Support\ServiceProvider;

class AhgVersionControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SnapshotBuilder::class, fn () => new SnapshotBuilder());
        $this->app->singleton(VersionWriter::class, fn () => new VersionWriter());
        $this->app->singleton(DiffComputer::class, fn () => new DiffComputer());
        $this->app->singleton(RestoreService::class, fn ($app) => new RestoreService(
            $app->make(SnapshotBuilder::class),
            $app->make(VersionWriter::class),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-version-control');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SnapshotSmokeCommand::class,
                CaptureCommand::class,
                DiffCommand::class,
                BackfillCommand::class,
                PruneCommand::class,
            ]);
        }

        // Phase D — observers: auto-capture a version on every save.
        InformationObject::observe(InformationObjectSnapshotObserver::class);
        Actor::observe(ActorSnapshotObserver::class);
    }
}
