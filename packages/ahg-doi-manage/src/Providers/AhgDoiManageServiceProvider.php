<?php

/**
 * AhgDoiManageServiceProvider
 *
 * Registers routes, views, console commands, and (Phase 3 of #654) the
 * DataCite Events API hooks: domain events (DoiViewed / DoiDownload /
 * DoiCitation) routed through RegisterDoiEventsListener, plus the
 * RecordDoiView global middleware. Also auto-installs the ahg_io_funding
 * (Phase 2) and ahg_datacite_event (Phase 3) sidecar tables on first boot
 * if either is missing.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Providers;

use AhgDoiManage\Console\DoiEventsFlushCommand;
use AhgDoiManage\Console\DoiFundingImportCommand;
use AhgDoiManage\Console\MetricsBackfillCommand;
use AhgDoiManage\Events\DoiCitation;
use AhgDoiManage\Events\DoiDownload;
use AhgDoiManage\Events\DoiViewed;
use AhgDoiManage\Http\Middleware\RecordDoiView;
use AhgDoiManage\Listeners\RegisterDoiEventsListener;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgDoiManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-doi-manage');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DoiFundingImportCommand::class,
                DoiEventsFlushCommand::class,
                MetricsBackfillCommand::class,
            ]);
        }

        $this->ensureSchema();
        $this->registerEventListeners();
        $this->registerHttpMiddleware();
    }

    /**
     * Per reference_ci_schema_hastable.md the probe + install is wrapped in
     * one outer try/catch so a missing DB connection during CI bootstrap
     * cannot block boot. Idempotent on subsequent runs (CREATE TABLE IF
     * NOT EXISTS in the SQL file).
     */
    protected function ensureSchema(): void
    {
        try {
            $needFunding = ! Schema::hasTable('ahg_io_funding');
            $needEvents  = ! Schema::hasTable('ahg_datacite_event');
            if (! $needFunding && ! $needEvents) {
                return;
            }
            $sql = @file_get_contents(__DIR__.'/../../database/install.sql');
            if (! $sql) {
                return;
            }
            foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                // Strip any leading -- comment lines so the actual statement
                // (CREATE TABLE / INSERT / etc.) is what reaches DB::statement.
                $lines = preg_split("/\r?\n/", trim($stmt));
                while ($lines && (trim($lines[0]) === '' || str_starts_with(ltrim($lines[0]), '--'))) {
                    array_shift($lines);
                }
                $stmt = trim(implode("\n", $lines));
                if ($stmt === '') {
                    continue;
                }
                DB::statement($stmt);
            }
        } catch (Throwable $e) {
            // Never block boot - the artisan commands surface schema problems at run time.
        }
    }

    protected function registerEventListeners(): void
    {
        Event::listen(DoiViewed::class, [RegisterDoiEventsListener::class, 'handleDoiViewed']);
        Event::listen(DoiDownload::class, [RegisterDoiEventsListener::class, 'handleDoiDownload']);
        Event::listen(DoiCitation::class, [RegisterDoiEventsListener::class, 'handleDoiCitation']);
    }

    /**
     * Push RecordDoiView onto the global stack so an IO show page (resolved
     * via the slug catch-all in the locked ahg-information-object-manage
     * package) fires a DoiViewed event without us having to edit that
     * package's routes file.
     */
    protected function registerHttpMiddleware(): void
    {
        try {
            $kernel = $this->app->make(HttpKernel::class);
            if (method_exists($kernel, 'pushMiddleware')) {
                $kernel->pushMiddleware(RecordDoiView::class);
            }
        } catch (Throwable $e) {
            // Bootstrap-time only; never fatal.
        }
    }
}
