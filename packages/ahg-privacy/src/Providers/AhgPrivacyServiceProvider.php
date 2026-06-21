<?php

namespace AhgPrivacy\Providers;

use AhgMetadataExtraction\Events\EmbeddedMetadataExtracted;
use AhgPrivacy\Listeners\ScanEmbeddedMetadataForPii;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgPrivacyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // heratio#1199 fix: wrap the privacy routes in the 'web' group so they get session +
        // CSRF. Without it the `auth` middleware can't see the logged-in session, so every
        // /admin/privacy/* page 302-redirected a logged-in user to login (and on to home).
        \Illuminate\Support\Facades\Route::middleware('web')->group(__DIR__.'/../../routes/web.php');
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

        // Issue #751 - Phase 2 install probe runs alongside Phase 1. The
        // probe + install live inside one outer try block per the
        // reference_ci_schema_hastable rule.
        try {
            if (! Schema::hasTable('ahg_pii_finding_embedded')) {
                $this->installSqlFile(__DIR__.'/../../database/install-phase2.sql');
            }
        } catch (Throwable $e) {
            Log::warning('ahg-privacy: Phase 2 (embedded PII) install probe/install failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Issue #1108 - Phase 3: field-level structured redaction tables.
        try {
            if (! Schema::hasTable('information_object_privacy')
                || ! Schema::hasTable('information_object_privacy_field')
                || ! Schema::hasTable('privacy_reason')) {
                $this->installSqlFile(__DIR__.'/../../database/install-phase3.sql');
            }
        } catch (Throwable $e) {
            Log::warning('ahg-privacy: Phase 3 (field-level redaction) install probe/install failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Issue #1109 - Phase 4: DPIA <-> ROPA linkage columns on the Article 30
        // register + the privacy_dpia_log status-change audit table.
        try {
            if (! Schema::hasTable('privacy_dpia_log')
                || ! Schema::hasColumn('ahg_processing_activity', 'dpia_required')) {
                $this->installSqlFile(__DIR__.'/../../database/install-phase4.sql');
            }
        } catch (Throwable $e) {
            Log::warning('ahg-privacy: Phase 4 (DPIA/ROPA linkage) install probe/install failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Issue #1108 deliverable 5 - DSAR <-> IO scope link table.
        try {
            if (! Schema::hasTable('privacy_dsar_object')) {
                $this->installSqlFile(__DIR__.'/../../database/install-dsar-scope.sql');
            }
        } catch (Throwable $e) {
            Log::warning('ahg-privacy: DSAR scope install probe/install failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Compliance Control Catalog - regime -> obligation -> control mapping
        // (vendor/jurisdiction-agnostic). Probe + install in one outer try per the
        // reference_ci_schema_hastable rule; seed via INSERT IGNORE so it stays
        // idempotent on every boot.
        try {
            if (! Schema::hasTable('ahg_compliance_control')) {
                $this->installSqlFile(__DIR__.'/../../database/install-control-catalog.sql');
            }
        } catch (Throwable $e) {
            Log::warning('ahg-privacy: Compliance Control Catalog install probe/install failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // heratio#1199 - Phase 5: compliance-autopilot retention-schedule
        // proposals. Probe + install live inside one outer try per the
        // reference_ci_schema_hastable rule.
        try {
            if (! Schema::hasTable('ahg_retention_proposal')) {
                $this->installSqlFile(__DIR__.'/../../database/install-phase5.sql');
            }
        } catch (Throwable $e) {
            Log::warning('ahg-privacy: Phase 5 (retention proposals) install probe/install failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // #1108 - alias the field-redaction middleware so IO read routes can
        // opt in (applies field-level redaction for non-privileged viewers).
        $this->app['router']->aliasMiddleware(
            'privacy.redact',
            \AhgPrivacy\Middleware\ApplyRedactionMiddleware::class
        );

        // #1108 deliverable 4 - surface the field-redaction panel on the
        // (hard-locked) IO detail page via response injection, admin-only.
        $this->app['router']->pushMiddlewareToGroup(
            'web',
            \AhgPrivacy\Middleware\InjectFieldRedactionPanel::class
        );

        // Wire the embedded-PII scan listener onto the extraction event.
        // We register unconditionally - the listener short-circuits cleanly
        // when the Phase 2 schema isn't installed yet.
        Event::listen(
            EmbeddedMetadataExtracted::class,
            ScanEmbeddedMetadataForPii::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgPrivacy\Console\Commands\CheckOverdueDsarsCommand::class,
                \AhgPrivacy\Console\Commands\ScanIoCommand::class,
                \AhgPrivacy\Console\Commands\Article30ExportCommand::class,
                \AhgPrivacy\Console\Commands\ScanEmbeddedBackfillCommand::class,
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
        $this->installSqlFile(__DIR__.'/../../database/install-phase1.sql');
    }

    /**
     * Generic idempotent SQL-file installer. Splits the file on top-level ;
     * (no procedure bodies in our install files), runs each statement in
     * isolation, and swallows the well-known "duplicate key name" benign
     * re-run noise. Used by both Phase 1 and Phase 2 (#751) installs.
     */
    private function installSqlFile(string $path): void
    {
        if (! is_readable($path)) {
            return;
        }
        $sql = (string) file_get_contents($path);
        if ($sql === '') {
            return;
        }

        $stripped = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = array_filter(array_map('trim', explode(';', $stripped)), static fn ($s) => $s !== '');
        foreach ($statements as $stmt) {
            try {
                DB::unprepared($stmt.';');
            } catch (Throwable $e) {
                $msg = strtolower($e->getMessage());
                if (str_contains($msg, 'duplicate key name')
                    || str_contains($msg, 'duplicate entry')
                    || str_contains($msg, 'duplicate column name')) {
                    continue;
                }
                Log::warning('ahg-privacy: install statement failed', [
                    'file'  => basename($path),
                    'error' => $e->getMessage(),
                    'sql'   => mb_substr($stmt, 0, 120),
                ]);
            }
        }
    }
}
