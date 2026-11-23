<?php

namespace AhgLibrary\Providers;

use AhgLibrary\Services\Ledger\LedgerTaxProvider;
use AhgLibrary\Services\Ledger\TableLedgerTaxProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Invoicing sub-ledger (heratio#1475 item 5). bindIf lets a per-market
 * compliance module supply its own LedgerTaxProvider.
 */
class LibraryLedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(LedgerTaxProvider::class, TableLedgerTaxProvider::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/web-ledger.php');
    }
}
