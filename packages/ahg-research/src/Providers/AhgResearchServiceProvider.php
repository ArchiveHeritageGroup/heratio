<?php

namespace AhgResearch\Providers;

use AhgResearch\Console\Commands\OrcidSyncCommand;
use AhgResearch\Services\BibliographyService;
use AhgResearch\Services\CitationService;
use AhgResearch\Services\CollaborationRealtimeService;
use AhgResearch\Services\CrossFondsQueryService;
use AhgResearch\Services\OdrlService;
use AhgResearch\Services\OrcidService;
use AhgResearch\Services\ResearchAnalyticsService;
use AhgResearch\Services\ResearchStudioService;
use AhgResearch\Services\NotebookService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AhgResearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OdrlService::class);
        $this->app->singleton(BibliographyService::class);
        $this->app->singleton(CitationService::class);
        $this->app->singleton(ResearchStudioService::class);
        $this->app->singleton(NotebookService::class);
        $this->app->singleton(CrossFondsQueryService::class);
        $this->app->singleton(ResearchAnalyticsService::class);
        $this->app->singleton(CollaborationRealtimeService::class);
        $this->app->singleton(OrcidService::class);
    }

    public function boot(): void
    {
        // Register ODRL policy middleware alias
        $router = $this->app['router'];
        $router->aliasMiddleware('odrl', \AhgResearch\Middleware\OdrlPolicyMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgResearch\Commands\SeedDropdownsCommand::class,
                \AhgResearch\Commands\SeedTargetJournalsCommand::class,
                OrcidSyncCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);

                // #755: ORCID sync — pull Works for all approved researchers who
                // have linked their ORCID iD. Runs at 01:30, after KBART refresh,
                // so feed metadata is current before staff arrive. Self-gates on
                // ORCID_CLIENT_ID being set so the schedule fires harmlessly when
                // ORCID is not yet configured.
                $schedule->command('ahg:orcid-sync --limit=200')
                    ->dailyAt('01:30')
                    ->withoutOverlapping(60)
                    ->runInBackground()
                    ->onOneServer()
                    ->skip(function () {
                        try {
                            return ! app(OrcidService::class)->isConfigured();
                        } catch (\Throwable) {
                            return true; // error → skip rather than crash scheduler boot
                        }
                    });
            });
        }

        // #1198 Research Copilot: ensure the saved-answer table exists (idempotent; one outer
        // try around hasTable + unprepared per reference_ci_schema_hastable).
        $this->app->booted(function () {
            try {
                if (! \Illuminate\Support\Facades\Schema::hasTable('research_copilot_answer')) {
                    $sql = file_get_contents(__DIR__.'/../../database/install_copilot_answer.sql');
                    if (is_string($sql) && trim($sql) !== '') {
                        \Illuminate\Support\Facades\DB::unprepared($sql);
                    }
                }
            } catch (\Throwable $e) {
                // DB not ready / install hiccup - retries next boot.
            }
        });

        // Auto-seed research dropdowns on first boot if missing
        $this->app->booted(function () {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('ahg_dropdown')) {
                    $hasSeatTypes = \Illuminate\Support\Facades\DB::table('ahg_dropdown')
                        ->where('taxonomy', 'seat_type')->exists();
                    if (! $hasSeatTypes) {
                        \Illuminate\Support\Facades\Artisan::call('ahg:seed-research-dropdowns');
                    }
                }
            } catch (\Exception $e) {
                // Silently skip if DB not ready (e.g. during migrations)
            }
        });

        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'research');
    }
}