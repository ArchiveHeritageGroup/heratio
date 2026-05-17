<?php

namespace AhgReports\Providers;

use AhgReports\Services\ReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReportService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-reports');
        $this->autoSeedRecordsManagementTemplates();
    }

    private function autoSeedRecordsManagementTemplates(): void
    {
        try {
            if (!Schema::hasTable('report_template')) {
                return;
            }
            $seeded = DB::table('report_template')
                ->where('category', 'records_management_compliance')
                ->exists();
            if ($seeded) {
                return;
            }
            $seedPath = __DIR__ . '/../../database/seed_records_management_compliance_templates.sql';
            if (!is_file($seedPath)) {
                return;
            }
            $sql = file_get_contents($seedPath);
            if ($sql !== false && $sql !== '') {
                DB::unprepared($sql);
            }
        } catch (\Throwable $e) {
            // best-effort: tables may not exist on a cold install
        }
    }
}
