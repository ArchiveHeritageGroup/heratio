<?php

namespace AhgMuseum;

use Illuminate\Support\ServiceProvider;

class AhgMuseumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ahg-museum');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgMuseum\Console\Commands\ImportMuseumCsvCommand::class,
            ]);
        }

        // One-shot seed: when museum_metadata_i18n exists but is empty AND
        // museum_metadata has rows, mirror them into the en culture so the
        // fallback-aware read path has data to fall back to. Idempotent — runs
        // once on first request after upgrade and is a no-op thereafter.
        $this->app->booted(function () {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable('museum_metadata_i18n')
                    || !\Illuminate\Support\Facades\Schema::hasTable('museum_metadata')) {
                    return;
                }
                $i18nCount = \Illuminate\Support\Facades\DB::table('museum_metadata_i18n')->count();
                if ($i18nCount > 0) return;
                $parentCount = \Illuminate\Support\Facades\DB::table('museum_metadata')->count();
                if ($parentCount === 0) return;
                \Illuminate\Support\Facades\DB::statement(file_get_contents(__DIR__ . '/../../database/seed_museum_metadata_i18n.sql'));
            } catch (\Throwable $e) {
                // Boot-time seed is best-effort; log nothing — operators can
                // run mysql -u root heratio < .../seed_museum_metadata_i18n.sql by hand.
            }
        });
    }
}
