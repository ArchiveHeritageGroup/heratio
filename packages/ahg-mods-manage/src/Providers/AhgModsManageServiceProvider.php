<?php

namespace AhgModsManage\Providers;

use Illuminate\Support\ServiceProvider;

class AhgModsManageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'mods-manage');
    }
}
