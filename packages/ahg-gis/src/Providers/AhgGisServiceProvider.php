<?php

namespace AhgGis\Providers;

use Illuminate\Support\ServiceProvider;

class AhgGisServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-gis');

        // Replaces the DELIMITER //CREATE PROCEDURE
        // ahg_gis_add_index block + its 2 CALL sites that PDO couldn't parse
        // (#105). MySQL 8 has no spatial index on FLOAT and the helper does
        // an existence check before ALTER, so this is idempotent on every
        // boot.
        try {
            \AhgGis\Services\IndexHelper::add('contact_information', 'idx_contact_latitude',  'latitude');
            \AhgGis\Services\IndexHelper::add('contact_information', 'idx_contact_longitude', 'longitude');
        } catch (\Throwable $e) {
            // No DB or contact_information missing — retries on next boot.
        }
    }
}
