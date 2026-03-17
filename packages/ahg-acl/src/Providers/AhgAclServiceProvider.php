<?php

namespace AhgAcl\Providers;

use AhgAcl\Services\AclService;
use Illuminate\Support\ServiceProvider;

class AhgAclServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AclService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-acl');
    }
}
