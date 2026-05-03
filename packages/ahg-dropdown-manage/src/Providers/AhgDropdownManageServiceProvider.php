<?php

namespace AhgDropdownManage\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgDropdownManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-dropdown-manage');

        $this->ensureI18nTable();
    }

    /**
     * Issue #59 Phase 1 — idempotent install of ahg_dropdown_i18n + en seed.
     *
     * Runs at most once per row (the en seed is gated by a NOT-EXISTS check).
     * Failures are swallowed because a fresh install runs before ahg_dropdown
     * itself exists; the next boot picks it up.
     */
    protected function ensureI18nTable(): void
    {
        try {
            if (!Schema::hasTable('ahg_dropdown')) {
                return;
            }
            if (!Schema::hasTable('ahg_dropdown_i18n')) {
                $sql = @file_get_contents(__DIR__ . '/../../database/install_i18n.sql');
                if ($sql) {
                    DB::unprepared($sql);
                }
            }
            // Seed en rows that don't have an i18n row yet — LEFT JOIN ...
            // WHERE i18n.id IS NULL touches only the gaps, so this is a no-op
            // once fully seeded.
            if (Schema::hasTable('ahg_dropdown_i18n')) {
                DB::statement("
                    INSERT IGNORE INTO ahg_dropdown_i18n (id, culture, label)
                    SELECT d.id, 'en', d.label
                    FROM ahg_dropdown d
                    LEFT JOIN ahg_dropdown_i18n di
                      ON di.id = d.id AND di.culture = 'en'
                    WHERE di.id IS NULL
                ");
            }
        } catch (\Throwable $e) {
            // Boot must never throw — missing parent table on a fresh install
            // is the expected case; the next boot after install seeds it.
        }
    }
}
