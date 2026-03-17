<?php

namespace AhgLoan\Providers;

use AhgLoan\Services\LoanService;
use Illuminate\Support\ServiceProvider;

class AhgLoanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoanService::class, function ($app) {
            return new LoanService();
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-loan');
    }
}
