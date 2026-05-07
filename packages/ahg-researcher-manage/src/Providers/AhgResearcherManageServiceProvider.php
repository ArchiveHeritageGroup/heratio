<?php

namespace AhgResearcherManage\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AhgResearcherManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-researcher-manage');

        // Replaces the DELIMITER //CREATE PROCEDURE
        // ahg_researcher_seed_workflow block that PDO couldn't parse (#105).
        try {
            \AhgResearcherManage\Services\WorkflowSeeder::seed();
        } catch (\Throwable $e) {
            // No DB or partial schema — seeder retries on next boot.
        }
    }
}
