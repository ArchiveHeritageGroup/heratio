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
        $this->app->singleton(\AhgResearch\Services\DmpService::class);
        $this->app->singleton(\AhgResearch\Services\ResearchOutputService::class);
        // Bind the UserProvisionerInterface to the default Eloquent implementation
        $this->app->singleton(\AhgResearch\Contracts\UserProvisionerInterface::class, \AhgResearch\Services\UserProvisioner\EloquentUserProvisioner::class);
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
                \AhgResearch\Console\Commands\FieldAlertsCommand::class, // #1235 Living Field Alerts
                \AhgResearch\Console\Commands\ImpactRefreshCommand::class, // #1241 Impact Tracking
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

                // #1235 Living Field Alerts - poll Crossref/OpenAlex for
                // retractions + updates on watched works. Resilient: a failed
                // fetch just yields no new alerts.
                $schedule->command('ahg:research-field-alerts')
                    ->dailyAt('02:10')
                    ->withoutOverlapping(60)
                    ->runInBackground()
                    ->onOneServer();

                // #1241 Impact Tracking - poll OpenAlex/Crossref for citations +
                // mentions of published outputs. Same resilient pattern.
                $schedule->command('ahg:research-impact-refresh')
                    ->dailyAt('02:25')
                    ->withoutOverlapping(60)
                    ->runInBackground()
                    ->onOneServer();
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

        // Self-declared research mode (experience_level) on the researcher row.
        // research_researcher is this package's own sidecar table, not an AtoM
        // base table, so adding a column is safe. Added idempotently here
        // because this package provisions schema at boot, not via artisan
        // migrate (the matching migration file covers the Docker migrate path).
        $this->app->booted(function () {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('research_researcher')
                    && ! \Illuminate\Support\Facades\Schema::hasColumn('research_researcher', 'experience_level')) {
                    \Illuminate\Support\Facades\DB::statement(
                        "ALTER TABLE `research_researcher` ADD COLUMN `experience_level` VARCHAR(20) NOT NULL DEFAULT 'intermediate'"
                    );
                }
            } catch (\Throwable $e) {
                // DB not ready / column already present - retries next boot.
            }
        });

        // Research OS (epic #1222) - auto-install per-slice sidecar/new tables.
        // Idempotent; one outer try per reference_ci_schema_hastable. Existing
        // tables are NEVER altered. Extend this map as ROS slices land.
        $this->app->booted(function () {
            // [table, install sql, optional dropdown-seed sql].
            $installs = [
                ['research_claim_meta', 'install_claim_ledger.sql', null],                       // #1223 Claim Ledger
                ['research_decision_log', 'install_decision_log.sql', 'seed_decision_log_dropdowns.sql'], // #1224 Decision Log
                ['research_source_triage', 'install_source_triage.sql', null],                   // #1227 Source Triage
                ['research_question_brief', 'install_question_builder.sql', null],               // #1226 Question Builder
                ['research_inbox_item', 'install_inbox.sql', 'seed_inbox_dropdowns.sql'],        // #1228 Quick Capture Inbox
                ['research_argument', 'install_argument_builder.sql', null],                     // #1229 Argument Builder
                ['research_review_comment', 'install_review_studio.sql', null],                  // #1230 Review Studio
                ['research_method_template', 'install_method_studio.sql', 'seed_method_templates.sql'], // #1231 Method Studio
                ['research_submission', 'install_publication_studio.sql', null],                 // #1232 Publication Studio
                ['research_memory_item', 'install_research_memory.sql', null],                   // #1233 Research Memory
                ['research_analysis_result', 'install_analysis_bridge.sql', null],              // #1234 Analysis Bridge
                ['research_field_watch', 'install_field_alerts.sql', null],                      // #1235 Living Field Alerts
                ['research_contradiction', 'install_contradiction_engine.sql', null],            // #1236 Contradiction Engine
                ['research_replication_log', 'install_replication_pack.sql', null],              // #1238 Replication Pack
                ['research_export_log', 'install_project_export.sql', null],                     // #1237 Open-format Export
                ['research_ai_disclosure_log', 'install_ai_disclosure.sql', null],              // #1242 AI Disclosure
                ['research_impact_signal', 'install_impact_tracking.sql', null],                 // #1241 Impact Tracking
                ['research_grant_draft', 'install_grant_engine.sql', 'seed_grant_templates.sql'], // #1239 Grant Engine
                ['research_writing_doc', 'install_writing_studio.sql', null],                    // #1222 Stage 13 Writing Studio
                ['research_dmp', 'install_dmp_builder.sql', 'seed_dmp_dropdowns.sql'],            // #1222 DMP Builder
                ['research_output', 'install_research_outputs.sql', 'seed_research_outputs_dropdowns.sql'], // #1222 Research Outputs register (CRIS/RIM)
                ['research_ethics', 'install_research_ethics.sql', 'seed_research_ethics_dropdowns.sql'], // #1222 Research Ethics & Consent register
                ['research_funding', 'install_research_funding.sql', 'seed_research_funding_dropdowns.sql'], // #1222 Research Funding tracker (awarded-funding ledger)
                ['research_team_member', 'install_research_team.sql', 'seed_research_team_dropdowns.sql'], // #1222 Research Team & Collaborators register
                ['research_milestone', 'install_research_milestones.sql', 'seed_research_milestones_dropdowns.sql'], // #1222 Research Milestones & Deliverables tracker
                // #1240 Time Machine - read-only reconstruction, no table.
            ];
            foreach ($installs as [$table, $file, $seed]) {
                try {
                    if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                        $sql = @file_get_contents(__DIR__.'/../../database/'.$file);
                        if (is_string($sql) && trim($sql) !== '') {
                            \Illuminate\Support\Facades\DB::unprepared($sql);
                        }
                        if ($seed !== null && \Illuminate\Support\Facades\Schema::hasTable('ahg_dropdown')) {
                            $seedSql = @file_get_contents(__DIR__.'/../../database/'.$seed);
                            if (is_string($seedSql) && trim($seedSql) !== '') {
                                \Illuminate\Support\Facades\DB::unprepared($seedSql);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // DB not ready / install hiccup - retries next boot.
                }
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
        // Research OS (epic #1222) routes - one file per slice so the shared
        // routes/web.php stays untouched. Each file self-contains its own
        // prefix('research')->name('research.')->middleware(['web','auth'])
        // group, so load it plainly. Extend this list as ROS slices land.
        foreach ([
            'claim-ledger', 'decision-log', 'source-triage', 'question-builder', 'inbox',
            'argument-builder', 'review-studio', 'method-studio', 'publication-studio',
            'research-memory', 'analysis-bridge', 'field-alerts', 'contradiction-engine',
            'replication-pack', 'project-export', 'ai-disclosure', 'time-machine', 'impact-tracking',
            'grant-engine', 'writing-studio', 'dmp-builder', 'research-outputs', 'research-ethics',
            'research-funding', 'research-team', 'research-milestones',
        ] as $rosRoute) {
            \Illuminate\Support\Facades\Route::group([], __DIR__ . '/../../routes/' . $rosRoute . '.php');
        }
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'research');
    }
}