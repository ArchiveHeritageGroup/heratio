<?php

namespace AhgAccessionManage\Providers;

use AhgAccessionManage\Services\CaaisProfileService;
use AhgCore\Services\PackageInstaller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgAccessionManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-accession-manage');
        $this->installCaais();
    }

    /**
     * First-boot self-install of the CAAIS profile tables and vocabularies
     * (heratio#1514). Gated on the last table the file creates, through
     * Schema::hasTable, which the per-release SchemaExistenceCache answers
     * from memory once the table exists - so a normal request costs nothing.
     * The dropdown seed runs in the same pass rather than being probed per
     * request; it is INSERT IGNORE, and heratio:install-bootstrap re-runs it.
     */
    private function installCaais(): void
    {
        try {
            if (Schema::hasTable(CaaisProfileService::SENTINEL_TABLE)
                || ! Schema::hasTable('accession')) {
                return;
            }
            $root = dirname(__DIR__, 2);
            if (! PackageInstaller::autoInstall($root, true, $root.'/database/install_caais.sql')) {
                Log::warning('ahg-accession-manage: CAAIS install skipped: '.PackageInstaller::$lastError);

                return;
            }
            if (Schema::hasTable('ahg_dropdown')) {
                \Illuminate\Support\Facades\DB::unprepared((string) file_get_contents($root.'/database/seed_dropdowns.sql'));
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-accession-manage: CAAIS install skipped: '.$e->getMessage());
        }
    }
}
