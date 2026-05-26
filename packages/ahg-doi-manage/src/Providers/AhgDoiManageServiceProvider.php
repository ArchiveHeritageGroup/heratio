<?php

/**
 * AhgDoiManageServiceProvider
 *
 * Registers routes, views, and Phase 2 (#654) console command
 * doi:funding-import. Also auto-installs the ahg_io_funding sidecar
 * table on first boot if it is missing.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Providers;

use AhgDoiManage\Console\DoiFundingImportCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgDoiManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-doi-manage');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DoiFundingImportCommand::class,
            ]);
        }

        $this->ensureFundingTable();
    }

    /**
     * Idempotent first-boot install of ahg_io_funding. Wrapped end-to-end
     * in try/catch (per reference_ci_schema_hastable.md) so a missing DB
     * connection during CI bootstrap can never block boot.
     */
    protected function ensureFundingTable(): void
    {
        try {
            if (Schema::hasTable('ahg_io_funding')) {
                return;
            }
            $sql = @file_get_contents(__DIR__.'/../../database/install.sql');
            if (! $sql) {
                return;
            }
            foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }
                DB::statement($stmt);
            }
        } catch (Throwable $e) {
            // Never block boot - the doi:funding-import command will surface
            // schema problems at run time when an operator actually uses it.
        }
    }
}
