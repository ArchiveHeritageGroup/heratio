<?php

namespace AhgPdfTools\Providers;

use AhgPdfTools\Services\PdfTextExtractService;
use AhgPdfTools\Services\TiffPdfMergeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgPdfToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PdfTextExtractService::class, function () {
            return new PdfTextExtractService;
        });

        $this->app->singleton(TiffPdfMergeService::class, function () {
            return new TiffPdfMergeService;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgPdfTools\Console\CombineFolderCommand::class,
                \AhgPdfTools\Console\PurgeCombineTrashCommand::class,
            ]);

            // #1177: daily purge of quarantined combine source files past retention.
            $this->app->booted(function () {
                try {
                    $this->app->make(\Illuminate\Console\Scheduling\Schedule::class)
                        ->command('ahg:purge-combine-trash')
                        ->dailyAt('03:30')
                        ->withoutOverlapping(30);
                } catch (\Throwable $e) {
                    // scheduler not available in this context - ignore
                }
            });
        }
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-pdf-tools');

        // #1177: seed the retention-window setting (days to keep quarantined combine
        // source files before purge) so it is configurable via ahg_settings. INSERT
        // IGNORE-style: only create it if absent, never overwrite an operator value.
        try {
            if (Schema::hasTable('ahg_settings')
                && ! DB::table('ahg_settings')->where('setting_key', 'pdf_combine_trash_days')->exists()) {
                DB::table('ahg_settings')->insert([
                    'setting_key' => 'pdf_combine_trash_days',
                    'setting_value' => '7',
                    'setting_type' => 'integer',
                    'setting_group' => 'pdf',
                    'description' => 'Days to keep combined source files in quarantine before the scheduled purge removes them.',
                    'is_sensitive' => 0,
                    'is_locked' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // best-effort seed; the purge command falls back to a 7-day default
        }
    }
}
