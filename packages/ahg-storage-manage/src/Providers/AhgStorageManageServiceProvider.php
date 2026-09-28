<?php

namespace AhgStorageManage\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgStorageManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-storage-manage');
        $this->install();
    }

    /**
     * First-boot self-install of the storage-location tables (heratio#1514) and
     * the location-type dropdown. install.sql is all CREATE TABLE IF NOT EXISTS
     * plus an INSERT IGNORE closure backfill, so re-running it is harmless.
     */
    private function install(): void
    {
        try {
            if (! Schema::hasTable('ahg_storage_location_closure')) {
                DB::unprepared((string) file_get_contents(__DIR__.'/../../database/install.sql'));
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-storage-manage: table install skipped: '.$e->getMessage());
        }
        try {
            if (Schema::hasTable('ahg_dropdown')
                && DB::table('ahg_dropdown')->where('taxonomy', 'storage_location_type')->doesntExist()) {
                DB::unprepared((string) file_get_contents(__DIR__.'/../../database/seed_dropdowns.sql'));
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-storage-manage: dropdown seed skipped: '.$e->getMessage());
        }
    }
}
