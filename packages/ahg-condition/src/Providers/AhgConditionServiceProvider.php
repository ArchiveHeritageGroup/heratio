<?php

namespace AhgCondition\Providers;

use Illuminate\Support\ServiceProvider;

class AhgConditionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-condition');

        // Replaces the DELIMITER //CREATE PROCEDURE
        // ahg_condition_seed_ai_dropdowns block that PDO couldn't parse (#105).
        try {
            \AhgCondition\Services\AiDropdownSeeder::seed();
        } catch (\Throwable $e) {
            // No DB or partial schema — seeder retries on next boot.
        }
    }
}
