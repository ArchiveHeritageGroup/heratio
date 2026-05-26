<?php

namespace AhgPrivacy\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgPrivacyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'privacy');

        // First-boot install of Phase 1 (#669) sidecar tables. The probe and
        // the install both live inside a single outer try so a non-existent
        // information_schema, a read-only DB, or any other transient error
        // can never break application bootstrap (see CLAUDE.md
        // reference_ci_schema_hastable).
        try {
            if (! Schema::hasTable('ahg_pii_scan_report')
                || ! Schema::hasTable('ahg_processing_activity')
                || ! Schema::hasTable('ahg_dpia')) {
                $this->installPhase1Schema();
            }
        } catch (Throwable $e) {
            Log::warning('ahg-privacy: Phase 1 install probe/install failed', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgPrivacy\Console\Commands\CheckOverdueDsarsCommand::class,
                \AhgPrivacy\Console\Commands\ScanIoCommand::class,
                \AhgPrivacy\Console\Commands\Article30ExportCommand::class,
            ]);

            // Daily 09:00 sweep — the command itself short-circuits when
            // dp_notify_overdue=false or dp_notify_email is empty, so this
            // schedule entry is safe to enable unconditionally.
            $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('privacy:check-overdue-dsars')
                    ->dailyAt('09:00')
                    ->withoutOverlapping();
            });
        }
    }

    /**
     * Run the Phase 1 install SQL idempotently. Each statement is executed
     * individually so a single mid-file error leaves the rest of the install
     * intact and recoverable on the next boot.
     */
    private function installPhase1Schema(): void
    {
        $path = __DIR__.'/../../database/install-phase1.sql';
        if (! is_readable($path)) {
            return;
        }
        $sql = (string) file_get_contents($path);
        if ($sql === '') {
            return;
        }

        // Strip line comments and split on top-level ; - this matches the
        // statement boundary inside the install file (no procedure bodies).
        $stripped = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = array_filter(array_map('trim', explode(';', $stripped)), static fn ($s) => $s !== '');
        foreach ($statements as $stmt) {
            try {
                DB::unprepared($stmt.';');
            } catch (Throwable $e) {
                // Common in re-runs: ADD UNIQUE KEY clashes once the constraint
                // already exists. Treat as benign on the install path.
                if (str_contains(strtolower($e->getMessage()), 'duplicate key name')) {
                    continue;
                }
                Log::warning('ahg-privacy: Phase 1 install statement failed', [
                    'error' => $e->getMessage(),
                    'sql'   => mb_substr($stmt, 0, 120),
                ]);
            }
        }
    }
}
