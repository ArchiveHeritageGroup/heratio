<?php

namespace AhgAuditTrail\Providers;

use Illuminate\Support\ServiceProvider;

class AhgAuditTrailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-audit-trail');
    }
}
