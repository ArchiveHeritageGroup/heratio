<?php

/**
 * AhgDisplayServiceProvider
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgDisplay;

use AhgDisplay\Commands\PopulateIoFacetDenormCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgDisplayServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ahg-display');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PopulateIoFacetDenormCommand::class,
            ]);
        }

        $this->ensureFacetDenormTable();
        $this->seedDefaultSettings();
    }

    public function register(): void
    {
        //
    }

    /**
     * Idempotent first-boot creation of the AHG facet denorm sidecar.
     * See docs/adr/0001-atom-base-schema-readonly-sidecar-pattern.md (Pattern C).
     */
    protected function ensureFacetDenormTable(): void
    {
        try {
            if (Schema::hasTable('ahg_io_facet_denorm')) {
                return;
            }
            $sql = file_get_contents(__DIR__ . '/../database/install.sql');
            if ($sql !== false && trim($sql) !== '') {
                DB::unprepared($sql);
            }
        } catch (Throwable $e) {
            // Never block boot.
        }
    }

    /**
     * Idempotent seed of Display defaults into ahg_settings.
     * Read-path flag — off by default; flipped after populator completes.
     */
    protected function seedDefaultSettings(): void
    {
        try {
            if (! Schema::hasTable('ahg_settings')) {
                return;
            }

            $defaults = [
                'ahg_display_use_facet_denorm' => '0',
            ];

            $existingKeys = DB::table('ahg_settings')
                ->whereIn('setting_key', array_keys($defaults))
                ->pluck('setting_key')
                ->all();
            $missing = array_diff(array_keys($defaults), $existingKeys);
            if (empty($missing)) {
                return;
            }

            $rows = [];
            foreach ($missing as $key) {
                $rows[] = [
                    'setting_key'   => $key,
                    'setting_value' => $defaults[$key],
                ];
            }
            DB::table('ahg_settings')->insertOrIgnore($rows);
        } catch (Throwable $e) {
            // Never block boot.
        }
    }
}
