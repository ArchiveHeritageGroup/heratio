<?php

namespace AhgDacsManage\Providers;

use Illuminate\Support\ServiceProvider;

class AhgDacsManageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'dacs-manage');
    }
}
