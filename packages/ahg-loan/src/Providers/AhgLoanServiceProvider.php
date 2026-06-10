<?php

namespace AhgLoan\Providers;

use AhgLoan\Services\LoanService;
use AhgLoan\Services\TourSchedulingService;
use Illuminate\Support\ServiceProvider;

class AhgLoanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoanService::class, function ($app) {
            return new LoanService;
        });

        $this->app->singleton(TourSchedulingService::class, function ($app) {
            return new TourSchedulingService;
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-loan');

        $this->ensureTourTable();
    }

    /**
     * Idempotent first-boot install for the touring-exhibition scheduling
     * table (#1190). Cheap Schema::hasTable() guard + CREATE TABLE IF NOT
     * EXISTS, matching the ahg-io-manage convention. Single try/catch so a
     * load failure never blocks application boot.
     */
    private function ensureTourTable(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_loan_tour_booking')) {
                return;
            }

            $sql = file_get_contents(__DIR__.'/../../database/install_tour.sql');
            if (! is_string($sql) || trim($sql) === '') {
                return;
            }

            \Illuminate\Support\Facades\DB::unprepared($sql);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ahg-loan] ensureTourTable failed: '.$e->getMessage());
        }
    }
}
