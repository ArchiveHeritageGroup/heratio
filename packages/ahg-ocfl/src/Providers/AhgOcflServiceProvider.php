<?php

/**
 * AhgOcflServiceProvider - boot the OCFL v1.1 storage layer.
 *
 * Wires:
 *   - the four artisan commands (init / ingest / verify / export)
 *   - the ahg_ocfl_object_map table (Schema::hasTable probe pattern)
 *   - the package config so env() overrides work
 *   - optional auto-init of the storage root namaste on first boot
 *     (only when config('ocfl.auto_init') is true)
 *
 * The package is a foundation - consumers (ahg-preservation, the admin UI)
 * call ocfl:ingest / ocfl:verify and are tracked in #691 follow-ups.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Providers;

use AhgOcfl\Console\Commands\OcflBackfillEmbeddedMetadataExtensionCommand;
use AhgOcfl\Console\Commands\OcflExportCommand;
use AhgOcfl\Console\Commands\OcflIngestCommand;
use AhgOcfl\Console\Commands\OcflInitCommand;
use AhgOcfl\Console\Commands\OcflVerifyCommand;
use AhgOcfl\Layout\StorageRoot;
use AhgOcfl\Metadata\DbEmbeddedMetadataPiiGate;
use AhgOcfl\Metadata\DbEmbeddedMetadataSource;
use AhgOcfl\Metadata\EmbeddedMetadataPiiGate;
use AhgOcfl\Metadata\EmbeddedMetadataSource;
use AhgOcfl\Storage\OcflStorageAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgOcflServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ocfl.php', 'ocfl');

        // Singleton: one adapter per request. The Filesystem disk it
        // wraps is itself a singleton in Laravel, so reusing it inside a
        // request is the right thing.
        $this->app->singleton(OcflStorageAdapter::class, function (Application $app) {
            return new OcflStorageAdapter(
                disk: (string) $app['config']->get('ocfl.disk', 'ocfl'),
            );
        });

        $this->app->singleton(StorageRoot::class, function (Application $app) {
            $root = new StorageRoot(
                $app->make(OcflStorageAdapter::class),
                (string) $app['config']->get('ocfl.storage_layout', 'flat-id'),
                (string) $app['config']->get('ocfl.digest_algorithm', 'sha512'),
            );
            // Auto-wire the embedded-metadata extension + PII gate so
            // every ocfl:ingest call emits the block when sidecar
            // tables have data. The gate fails open when #751 is
            // absent (see DbEmbeddedMetadataPiiGate docstring).
            try {
                $root->withEmbeddedMetadataSource($app->make(EmbeddedMetadataSource::class));
            } catch (\Throwable) {
                // Container binding absent in degraded boot - skip.
            }
            try {
                $root->withPiiGate($app->make(EmbeddedMetadataPiiGate::class));
            } catch (\Throwable) {
                // Gate absent - inventory still emits the block, just
                // without redaction.
            }
            return $root;
        });

        // Default bindings: concrete DB-backed implementations. Tests
        // override these by re-binding the interface to a stub in the
        // container before resolving StorageRoot.
        $this->app->bind(EmbeddedMetadataSource::class, DbEmbeddedMetadataSource::class);
        $this->app->bind(EmbeddedMetadataPiiGate::class, DbEmbeddedMetadataPiiGate::class);
    }

    public function boot(): void
    {
        // Idempotent install of the IO -> OCFL object map. Wrapped so a
        // brand-new install (no DB yet) doesn't blow up here.
        $this->bootInstallIfNeeded();

        if ($this->app->runningInConsole()) {
            $this->commands([
                OcflInitCommand::class,
                OcflIngestCommand::class,
                OcflVerifyCommand::class,
                OcflExportCommand::class,
                OcflBackfillEmbeddedMetadataExtensionCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../config/ocfl.php' => config_path('ocfl.php'),
            ], 'ocfl-config');
        }

        // Optional auto-init - off by default. Real deployments run
        // `php artisan ocfl:init` explicitly so the operator chooses
        // the storage root path.
        if ($this->app['config']->get('ocfl.auto_init') === true) {
            $this->bootAutoInit();
        }
    }

    /**
     * Auto-create the ahg_ocfl_object_map table on first boot.
     *
     * Mirrors the standard Heratio package install pattern (see
     * ahg-records-manage): wrap probe + install in one outer try.
     */
    protected function bootInstallIfNeeded(): void
    {
        try {
            if (! Schema::hasTable('ahg_ocfl_object_map')) {
                $sql = @file_get_contents(__DIR__.'/../../database/install.sql');
                if ($sql !== false && trim($sql) !== '') {
                    DB::unprepared($sql);
                    Log::info('ahg-ocfl: install.sql applied (first-boot)');
                }
            }
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue.
            Log::warning('ahg-ocfl boot install skipped: '.$e->getMessage());
        }
    }

    /**
     * Best-effort auto-init of the storage root namaste declaration.
     */
    protected function bootAutoInit(): void
    {
        try {
            /** @var StorageRoot $root */
            $root = $this->app->make(StorageRoot::class);
            if (! $root->isInitialized()) {
                $root->initialize();
                Log::info('ahg-ocfl: storage root auto-initialised');
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-ocfl auto-init skipped: '.$e->getMessage());
        }
    }
}
