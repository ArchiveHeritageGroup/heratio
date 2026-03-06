<?php

namespace AhgInformationObjectManage\Providers;

use Illuminate\Support\ServiceProvider;

class AhgInformationObjectManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-io-manage');
    }
}
