<?php

namespace AhgDcManage\Providers;

use Illuminate\Support\ServiceProvider;

class AhgDcManageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'dc-manage');
    }
}
