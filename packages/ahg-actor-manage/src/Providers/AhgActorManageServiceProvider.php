<?php

namespace AhgActorManage\Providers;

use Illuminate\Support\ServiceProvider;

class AhgActorManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-actor-manage');

        // #1355 - actor controlled vocabularies live in ahg_dropdown so site
        // admins manage them via the Dropdown Manager (skips itself once
        // seeded; ActorService falls back to hardcoded lists until then).
        try {
            \AhgActorManage\Services\ActorDropdownSeeder::seed();
        } catch (\Throwable $e) {
            // No DB or partial schema - seeder retries on next boot.
        }
    }
}
