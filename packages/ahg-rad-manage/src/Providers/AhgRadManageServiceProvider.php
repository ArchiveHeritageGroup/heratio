<?php

namespace AhgRadManage\Providers;

use Illuminate\Support\ServiceProvider;

class AhgRadManageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'rad-manage');
    }
}
