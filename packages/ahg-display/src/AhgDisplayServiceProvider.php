<?php

/**
 * AhgDisplayServiceProvider
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgDisplay;

use AhgDisplay\Commands\PopulateIoFacetDenormCommand;
use AhgDisplay\Commands\RebuildTitleSortCommand;
use AhgDisplay\Services\TitleSortService;
use Illuminate\Console\Scheduling\Schedule;
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
                RebuildTitleSortCommand::class,
            ]);

            // Hourly refresh keeps the sort sidecar self-healing. ~27 code
            // paths write information_object_i18n titles; rather than making
            // every one of them know about this table, the projection is
            // recomputed in bulk (~3s for 454k rows on atom.theahg.co.za).
            // Edits made through a path that calls TitleSortService::refreshFor()
            // are ordered correctly immediately; everything else lands here.
            $this->app->booted(function () {
                try {
                    $this->app->make(Schedule::class)
                        ->command('ahg:display-rebuild-title-sort')
                        ->hourly()
                        ->withoutOverlapping()
                        ->runInBackground();
                } catch (Throwable $e) {
                    // Never block boot on scheduler wiring.
                }
            });
        }

        $this->ensureFacetDenormTable();
        $this->ensureTitleSortTable();
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
     * Idempotent first-boot creation of the browse title-sort sidecar.
     * See database/install-title-sort.sql for why browse cannot sort off the
     * base column directly. Creating the table does NOT populate it -
     * TitleSortService::available() stays false until the rebuild command (or
     * the hourly schedule) has run, and browse keeps using the old ORDER BY
     * until then, so a fresh install is slow-but-correct rather than wrong.
     */
    protected function ensureTitleSortTable(): void
    {
        try {
            if (Schema::hasTable(TitleSortService::TABLE)) {
                // Already present - but an instance created before a sort column
                // was added still needs it. Cheap hasColumn no-op thereafter.
                (new TitleSortService())->ensureColumns();

                return;
            }
            $sql = file_get_contents(__DIR__ . '/../database/install-title-sort.sql');
            if ($sql !== false && trim($sql) !== '') {
                DB::unprepared($sql);
                // The DDL cannot know this install's collation; match it now so
                // the sidecar orders identically to the column it stands in for.
                (new TitleSortService())->alignCollation();
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
