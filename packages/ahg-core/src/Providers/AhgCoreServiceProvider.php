<?php

namespace AhgCore\Providers;

use AhgCore\Contracts\AgentRepository;
use AhgCore\Contracts\DescriptionRepository;
use AhgCore\Contracts\FunctionRepository;
use AhgCore\Contracts\PlaceRepository;
use AhgCore\Contracts\RelationRepository;
use AhgCore\Repositories\MysqlAgentRepository;
use AhgCore\Repositories\MysqlDescriptionRepository;
use AhgCore\Repositories\MysqlFunctionRepository;
use AhgCore\Repositories\MysqlPlaceRepository;
use AhgCore\Repositories\MysqlRelationRepository;
use AhgCore\Services\CronRunTrackerService;
use AhgCore\Services\CronSchedulerService;
use AhgCore\Services\SettingHelper;
use Illuminate\Support\ServiceProvider;

class AhgCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // #673 Phase 2: cron-monitoring config (high-priority commands +
        // notification user + inbox path + miss-threshold multiplier).
        $this->mergeConfigFrom(__DIR__.'/../../config/cron-monitoring.php', 'cron-monitoring');

        $this->app->singleton(CronRunTrackerService::class);
        $this->app->singleton(CronSchedulerService::class);

        // heratio#1211 - universal multilingual access: on-demand, display-only
        // translation of a record's key metadata via the sanctioned AI gateway.
        $this->app->singleton(\AhgCore\Services\MultilingualRecordService::class);

        // heratio#1205 - capture queue workflow service (the actionable layer on
        // top of the read-only capture-priority register).
        $this->app->singleton(\AhgCore\Services\CaptureQueueService::class);

        // Repository contracts → MySQL implementations
        $this->app->bind(DescriptionRepository::class, MysqlDescriptionRepository::class);
        $this->app->bind(AgentRepository::class, MysqlAgentRepository::class);
        $this->app->bind(RelationRepository::class, MysqlRelationRepository::class);
        $this->app->bind(FunctionRepository::class, MysqlFunctionRepository::class);
        $this->app->bind(PlaceRepository::class, MysqlPlaceRepository::class);
    }

    public function boot(): void
    {
        // Hydrate UI labels from the AtoM setting table so that
        // config('app.ui_label_*') returns admin-customised values, per current
        // request culture, with fallback to en when the target culture has no
        // setting_i18n row yet. The keys are pulled live from setting WHERE
        // scope='ui_label' so newly-added rows are picked up automatically.
        $this->app->booted(function () {
            $culture = app()->getLocale();
            $fallback = config('app.fallback_locale', 'en');
            try {
                $rows = \Illuminate\Support\Facades\DB::table('setting as s')
                    ->leftJoin('setting_i18n as si', function ($j) use ($culture) {
                        $j->on('s.id', '=', 'si.id')->where('si.culture', '=', $culture);
                    })
                    ->leftJoin('setting_i18n as si_fb', function ($j) use ($fallback) {
                        $j->on('s.id', '=', 'si_fb.id')->where('si_fb.culture', '=', $fallback);
                    })
                    ->where('s.scope', 'ui_label')
                    ->select('s.name', 'si.value as cur', 'si_fb.value as fb')
                    ->get();
                $translatorOverrides = [];
                foreach ($rows as $r) {
                    $val = ($r->cur !== null && $r->cur !== '') ? $r->cur : $r->fb;
                    $val = $val !== null ? strtr((string) $val, ['&nbsp;' => ' ']) : '';
                    if ($val === '') {
                        continue;
                    }

                    // a) config('app.ui_label_*') — used wherever code calls config()
                    config(["app.ui_label_{$r->name}" => $val]);

                    // b) translator override — so __('Archival description') in any
                    // blade also flips to the admin-configured label for the current
                    // culture without changing every call site to use config().
                    // The en value is the source key __() looks up; map it to the
                    // selected-culture value.
                    $en = $r->fb !== null ? strtr((string) $r->fb, ['&nbsp;' => ' ']) : '';
                    if ($en !== '' && $val !== $en) {
                        $translatorOverrides[$en] = $val;
                    }
                }
                if (! empty($translatorOverrides)) {
                    app('translator')->addLines($translatorOverrides, app()->getLocale(), '*');
                }
            } catch (\Throwable $e) {
                // Settings table missing (fresh install / misconfigured DB) — leave
                // the ui_label_* config defaults from config/app.php in place.
            }

            // Load element visibility settings into config('atom.element_visibility.*')
            SettingHelper::loadElementVisibility(app()->getLocale());
        });

        // Load routes
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');

        // #1193 - render a record's Gaussian-splat capture inline on its description page,
        // uniformly across every GLAM sector (museum/gallery/library/DAM/archival), without
        // editing each sector's locked show view. Registered in booted() so it runs AFTER the
        // HTTP kernel syncs its middleware groups (ahg-core boots early; a direct push here
        // would be overwritten by the kernel's web-group definition).
        $this->app->booted(function () {
            $this->app['router']->pushMiddlewareToGroup('web', \AhgCore\Middleware\InjectSplatViewer::class);
        });

        // Load views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-core');

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgCore\Console\Commands\NasWatchdogCommand::class,
                \AhgCore\Console\Commands\BackfillEmbeddedMetadataCommand::class,
                \AhgCore\Console\Commands\OptimizeModelsCommand::class,
                \AhgCore\Console\Commands\OptimizePdfsCommand::class,
                \AhgCore\Console\Commands\PointCloudSetupCommand::class,
                \AhgCore\Console\Commands\ConvertPointCloudCommand::class,
                \AhgCore\Console\Commands\SplatSetupCommand::class,
                // #1205 capture / at-risk register (endangered-heritage capture network)
                \AhgCore\Console\Commands\CapturePriorityCommand::class,
                // #1244 fixity slice - bounded fixity / integrity sweep
                \AhgCore\Console\Commands\FixitySweepCommand::class,
                \AhgCore\Commands\SearchPopulateCommand::class,
                \AhgCore\Commands\SearchUpdateCommand::class,
                \AhgCore\Commands\SearchCleanupCommand::class,
                \AhgCore\Commands\CacheXmlPurgeCommand::class,
                \AhgCore\Commands\RefreshFacetCacheCommand::class,
                \AhgCore\Commands\NotifySavedSearchesCommand::class,
                \AhgCore\Commands\DatabaseBackupCommand::class,
                \AhgCore\Commands\BackupCleanupCommand::class,
                \AhgCore\Commands\CleanupUploadsCommand::class,
                \AhgCore\Commands\DonorRemindersCommand::class,
                \AhgCore\Commands\CleanupLoginAttemptsCommand::class,
                \AhgCore\Commands\AuditRetentionCommand::class,
                \AhgCore\Commands\TranslationImportXliffCommand::class,
                \AhgCore\Commands\TranslationExportXliffCommand::class,
                \AhgCore\Commands\TranslationCoverageCommand::class,
                \AhgCore\Commands\TranslationLintCommand::class,
                \AhgCore\Commands\TranslationMtBatchCommand::class,
                \AhgCore\Commands\VocabularyImportCommand::class,
                \AhgCore\Commands\VocabularyMirrorCommand::class,
                \AhgCore\Commands\NestedSetRebuildCommand::class,
                \AhgCore\Commands\AuditPurgeCommand::class,
                \AhgCore\Commands\EmbargoProcessCommand::class,
                \AhgCore\Commands\EmbargoReportCommand::class,
                \AhgCore\Commands\StatisticsAggregateCommand::class,
                \AhgCore\Commands\StatisticsReportCommand::class,
                \AhgCore\Commands\WorkflowProcessCommand::class,
                \AhgCore\Commands\WorkflowSlaCheckCommand::class,
                \AhgCore\Commands\WorkflowStatusCommand::class,
                \AhgCore\Commands\IcipCheckExpiryCommand::class,
                \AhgCore\Commands\DisplayAutoDetectCommand::class,
                \AhgCore\Commands\DisplayReindexCommand::class,
                \AhgCore\Commands\HeritageInstallCommand::class,
                \AhgCore\Commands\HeritageRegionCommand::class,
                \AhgCore\Commands\HeritageBuildGraphCommand::class,
                \AhgCore\Commands\LinkedDataSyncCommand::class,
                \AhgCore\Commands\FormsExportCommand::class,
                \AhgCore\Commands\FormsImportCommand::class,
                \AhgCore\Commands\IpsasReportCommand::class,
                \AhgCore\Commands\WebhookRetryCommand::class,
                \AhgCore\Commands\PopiaBreachCheckCommand::class,

                // Digital Preservation
                \AhgCore\Commands\PreservationSchedulerCommand::class,
                \AhgCore\Commands\PreservationFixityCommand::class,
                \AhgCore\Commands\PreservationIdentifyCommand::class,
                \AhgCore\Commands\PreservationVirusScanCommand::class,
                \AhgCore\Commands\PreservationReplicateCommand::class,
                \AhgCore\Commands\PreservationPackageCommand::class,
                \AhgCore\Commands\PreservationObsolescenceCommand::class,
                \AhgCore\Commands\PreservationMigrateCommand::class,
                \AhgCore\Commands\PreservationStatsCommand::class,

                // Integrity Assurance
                \AhgCore\Commands\IntegrityScheduleCommand::class,
                \AhgCore\Commands\IntegrityVerifyCommand::class,
                \AhgCore\Commands\IntegrityReportCommand::class,
                \AhgCore\Commands\IntegrityRetentionCommand::class,

                // DOI Management
                \AhgCore\Commands\DoiMintCommand::class,
                \AhgCore\Commands\DoiProcessQueueCommand::class,
                \AhgCore\Commands\DoiVerifyCommand::class,
                \AhgCore\Commands\DoiUpdateCommand::class,
                \AhgCore\Commands\DoiReportCommand::class,
                \AhgCore\Commands\DoiSyncCommand::class,
                \AhgCore\Commands\DoiDeactivateCommand::class,

                // Metadata Export
                \AhgCore\Commands\MetadataExportCommand::class,

                // Privacy & Compliance
                \AhgCore\Commands\PrivacyScanPiiCommand::class,
                \AhgCore\Commands\PrivacyJurisdictionCommand::class,
                \AhgCore\Commands\CdpaLicenseCheckCommand::class,
                \AhgCore\Commands\CdpaStatusCommand::class,
                \AhgCore\Commands\NazClosureCheckCommand::class,
                \AhgCore\Commands\NazTransferDueCommand::class,
                \AhgCore\Commands\NmmzReportCommand::class,

                // Library Management
                \AhgCore\Commands\LibraryOverdueCheckCommand::class,
                \AhgCore\Commands\LibraryProcessFinesCommand::class,
                \AhgCore\Commands\LibraryHoldExpiryCommand::class,
                \AhgCore\Commands\LibraryPatronExpiryCommand::class,
                \AhgCore\Commands\LibrarySerialExpectedCommand::class,
                \AhgCore\Commands\LibraryIllOverdueCommand::class,
                \AhgCore\Commands\LibraryProcessCoversCommand::class,

                // Encryption
                \AhgCore\Commands\EncryptionBulkApplyCommand::class,
                \AhgCore\Commands\EncryptionBulkRevertCommand::class,

                // Museum
                \AhgCore\Commands\MuseumAatSyncCommand::class,
                \AhgCore\Commands\MuseumExhibitionCommand::class,

                // Accession Management
                \AhgCore\Commands\AccessionIntakeCommand::class,
                \AhgCore\Commands\AccessionReportCommand::class,

                // Authority Records
                \AhgCore\Commands\AuthorityCompletenessScanCommand::class,
                \AhgCore\Commands\AuthorityNerPipelineCommand::class,
                \AhgCore\Commands\AuthorityDedupScanCommand::class,
                \AhgCore\Commands\AuthorityMergeReportCommand::class,
                \AhgCore\Commands\AuthorityFunctionSyncCommand::class,

                // Portable Packages
                \AhgCore\Commands\PortableExportCommand::class,
                \AhgCore\Commands\PortableVerifyCommand::class,
                \AhgCore\Commands\PortableImportCommand::class,
                \AhgCore\Commands\PortableCleanupCommand::class,

                // Duplicate Detection
                \AhgCore\Commands\DedupeScanCommand::class,
                \AhgCore\Commands\DedupeMergeCommand::class,
                \AhgCore\Commands\DedupeReportCommand::class,

                // Import & Export
                \AhgCore\Commands\CsvImportCommand::class,
                \AhgCore\Commands\EadImportCommand::class,
                \AhgCore\Commands\ExportBulkCommand::class,

                // Finding Aids
                \AhgCore\Commands\FindingAidGenerateCommand::class,
                \AhgCore\Commands\FindingAidDeleteCommand::class,

                // Digital Objects
                \AhgCore\Commands\RegenDerivativesCommand::class,
                \AhgCore\Commands\LoadDigitalObjectsCommand::class,

                // OAI-PMH
                \AhgCore\Commands\OaiHarvestCommand::class,

                // Cron Scheduler
                \AhgCore\Commands\CronRunCommand::class,
                \AhgCore\Commands\CronSeedCommand::class,
                \AhgCore\Commands\CronStatusCommand::class,
                // #673 Phase 2: missed-run detector (embedded 5-min schedule
                // is registered in registerWithLaravelSchedule below).
                \AhgCore\Commands\CheckMissedCronRunsCommand::class,

                // Standalone install bootstrap (Phase 1 #6)
                \AhgCore\Commands\InstallBootstrapCommand::class,

                // #67 AHG Central
                \AhgCore\Console\Commands\AhgCentralPingCommand::class,
                \AhgCore\Console\Commands\AhgCentralHeartbeatCommand::class,
                // #127 AHG Central error-log sync
                \AhgCore\Console\Commands\AhgCentralSyncErrorsCommand::class,

                // #125 derivative encryption bulk-apply
                \AhgCore\Commands\EncryptionDerivativesBulkApplyCommand::class,

                // #45 GPU pool admin CLI - lets ops add/list/health/disable
                // GPU endpoints (.78=8GB existing, .115=20GB tomorrow,
                // 24GB host next week) without touching SQL.
                \AhgCore\Commands\GpuPoolCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);

                // #673 Phase 2: wrap every default schedule with the
                // ahg_cron_run tracking decorator + onOneServer() when
                // the cache driver supports atomic locks. Also embeds
                // cron:check-missed-runs every 5 minutes.
                try {
                    $this->app->make(CronSchedulerService::class)
                        ->registerWithLaravelSchedule($schedule);
                } catch (\Throwable $e) {
                    \Log::warning('[ahg-core] cron Schedule wiring failed: '.$e->getMessage());
                }

                // Daily safety net for unwrapped writers: walk every registered
                // ahg_encrypted_fields row and encrypt anything still in
                // plaintext. Per-service transparent wrappers (RepositoryService
                // etc.) catch writes immediately; this catches the rest.
                // Self-gates on encryption_enabled so it's a no-op when off.
                $schedule->command('ahg:encryption-bulk-apply')
                    ->dailyAt('04:00')
                    ->withoutOverlapping(60)
                    ->when(function () {
                        try {
                            return (new \AhgCore\Services\EncryptionService)->isEnabled();
                        } catch (\Throwable $e) {
                            return false;
                        }
                    });

                // #67 AHG Central daily heartbeat. Schedule fires
                // unconditionally; the command itself short-circuits when
                // ahg_central_enabled=0 so flipping the toggle in
                // /admin/ahgSettings/ahgIntegration is enough to silence it
                // without needing artisan re-cache.
                $schedule->command('ahg:central-heartbeat')
                    ->dailyAt('05:00')
                    ->withoutOverlapping(60);

                // #127 AHG Central error-log sync. Hourly so fleet errors
                // surface on the central.theahg.co.za dashboard with low
                // latency. The command self-gates on ahg_central_enabled AND
                // ahg_central_error_sync, so the schedule fires harmlessly
                // when either toggle is off.
                $schedule->command('ahg:central-sync-errors')
                    ->hourly()
                    ->withoutOverlapping(120);

                // #125 derivative encryption: daily walk over digital_object
                // rows + encrypt unencrypted files when the operator has
                // encryption_encrypt_derivatives on. Self-gates inside
                // handle() so the schedule fires harmlessly when off.
                $schedule->command('ahg:encryption-derivatives-bulk-apply')
                    ->dailyAt('04:30')
                    ->withoutOverlapping(120)
                    ->when(function () {
                        try {
                            return (new \AhgCore\Services\EncryptionService)->shouldEncryptDerivatives();
                        } catch (\Throwable $e) {
                            return false;
                        }
                    });
                // #755: RegenDerivativesCommand — regenerate thumbnails / reference
                // copies from master files. Weekly Sunday 02:00 (same slot as the
                // DB-driven cron_schedule entry so the two mechanisms stay in sync).
                $schedule->command('ahg:regen-derivatives --type=all')
                    ->weeklyOn(0, '02:00')
                    ->withoutOverlapping(120);
                // NAS watchdog: every 5 minutes, detect storage mount transitions.
                // Notifies on down -> up + up -> down only (state-transition gate),
                // so a healthy NAS does not spam the bell. Does NOT auto-remount;
                // operator preference is manual recovery on the NAS host itself.
                $schedule->command('ahg:nas-watchdog --quiet-ok')
                    ->everyFiveMinutes()
                    ->withoutOverlapping(5);

                // Auto-optimise oversized 3D models (Draco) hourly so freshly-uploaded
                // OBJ/GLB masters load in the walkthrough instead of placeholdering.
                // No-op when the /opt model tools are not installed.
                $schedule->command('ahg:optimize-models --commit --min-mb=20')
                    ->hourly()
                    ->withoutOverlapping(55)
                    ->runInBackground();

                // Auto-generate web-optimized PDF derivatives for large documents so
                // they load page-1-fast in the viewer. Masters are never touched; a
                // downsampled + linearized reference is added alongside. Idempotent
                // (skips PDFs that already have a web derivative). Off-peak + background
                // since gs/qpdf on a 100MB+ scan is CPU/IO heavy. No-op without gs/qpdf.
                $schedule->command('ahg:optimize-pdfs --commit --min-mb=20 --dpi=200')
                    ->dailyAt('03:10')
                    ->withoutOverlapping(120)
                    ->runInBackground();

                // #1244 fixity slice - daily BOUNDED fixity sweep. Re-verifies a
                // capped batch (default 100) of digital objects against their
                // stored checksum, logging each result to core_fixity_check_log so
                // the /admin/fixity report stays current and integrity drift is
                // caught early. Bounded + resilient by construction (a missing or
                // unreadable file becomes a missing_file/error row, never an
                // exception; oversized files are skipped + logged), so this is a
                // safe, low-cost daily task. Off-peak + background since hashing is
                // IO-heavy. The command self-no-ops when digital_object is absent.
                $schedule->command('ahg:fixity-sweep --limit=100')
                    ->dailyAt('03:40')
                    ->withoutOverlapping(120)
                    ->runInBackground();
            });
        }

        // #67 AHG Central: now that AhgCentralService exists, the per-row
        // is_locked guard the SettingsController::ahgIntegration page used
        // (to keep the form visible-but-immutable until a consumer shipped)
        // is no longer needed. Console-only idempotent unlock so the cost
        // sits with artisan / scheduler / install runs (one of which fires
        // on every deploy) rather than every web request. UPDATE matches
        // zero rows once the unlock has happened so subsequent runs are
        // free.
        if ($this->app->runningInConsole()) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('ahg_settings')) {
                    \Illuminate\Support\Facades\DB::table('ahg_settings')
                        ->whereIn('setting_key', [
                            'ahg_central_enabled', 'ahg_central_api_url',
                            'ahg_central_api_key', 'ahg_central_site_id',
                        ])
                        ->where('is_locked', 1)
                        ->update(['is_locked' => 0]);
                }
            } catch (\Throwable $e) {
                // is_locked column missing on older installs - ignore.
            }
        }

        // #127 AHG Central auto-commissioning. Seed the ahg_central_* rows so
        // a fresh install heartbeats + (optionally) error-syncs without an
        // operator touching the settings form. INSERT-if-missing only - an
        // operator's saved value is never overwritten. ahg_central_enabled is
        // seeded ON only when a fleet enrolment key is present (config <- the
        // .env AHG_CENTRAL_API_KEY); an install with no key stays quiet rather
        // than phoning home. Console-only + idempotent, like the block above.
        if ($this->app->runningInConsole()) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('ahg_settings')) {
                    $centralUrl = rtrim((string) config('heratio.central.api_url', 'https://central.theahg.co.za/api/v1'), '/');
                    $centralKey = (string) config('heratio.central.api_key', '');
                    $host = strtolower((string) (gethostname() ?: 'unknown'));
                    $host = preg_replace('/[^a-z0-9._-]/', '-', $host) ?: 'unknown';

                    $centralDefaults = [
                        'ahg_central_enabled' => $centralKey !== '' ? '1' : '0',
                        'ahg_central_api_url' => $centralUrl,
                        'ahg_central_api_key' => $centralKey,
                        'ahg_central_site_id' => 'heratio-'.$host,
                        'ahg_central_error_sync' => '0',
                        'ahg_central_last_error_id' => '0',
                    ];
                    $existing = \Illuminate\Support\Facades\DB::table('ahg_settings')
                        ->where('setting_key', 'like', 'ahg_central%')
                        ->pluck('setting_key')
                        ->all();
                    foreach ($centralDefaults as $key => $value) {
                        if (in_array($key, $existing, true)) {
                            continue;
                        }
                        \Illuminate\Support\Facades\DB::table('ahg_settings')->insert([
                            'setting_key' => $key,
                            'setting_value' => $value,
                            'setting_type' => in_array($key, ['ahg_central_enabled', 'ahg_central_error_sync'], true) ? 'boolean' : 'string',
                            'setting_group' => 'ahg_central',
                            'is_locked' => 0,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // ahg_settings missing / column drift - the client still works
                // off config defaults; the seed retries on the next console run.
            }
        }

        // Issue #99: per-user daily cloud-LLM call counter. Auto-install
        // on first boot so the table exists before VoiceLLMService tries
        // to enforce voice_daily_cloud_limit. The outer try also covers
        // hasTable() because composer's post-autoload-dump runs
        // package:discover before any DB is wired in CI; Laravel's default
        // sqlite fallback would otherwise throw and break composer install.
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('voice_usage')) {
                $sql = file_get_contents(__DIR__.'/../../database/install_voice_usage.sql');
                if (is_string($sql) && trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            // No DB connection or install hiccup — VoiceLLMService fails
            // open (no enforcement) when the table is missing; install
            // retries on next boot.
            \Log::warning('[ahg-core] voice_usage install failed: '.$e->getMessage());
        }

        // #673 Phase 2: cron-monitoring tables (ahg_cron_run +
        // ahg_cron_missed_run). Single outer try/catch around hasTable() +
        // unprepared() per reference_ci_schema_hastable.md - the
        // CI sqlite fallback otherwise crashes on package:discover before
        // a real DB is wired.
        try {
            $needsInstall = ! \Illuminate\Support\Facades\Schema::hasTable('ahg_cron_run')
                || ! \Illuminate\Support\Facades\Schema::hasTable('ahg_cron_missed_run');
            if ($needsInstall) {
                $sql = file_get_contents(__DIR__.'/../../database/install_cron_run.sql');
                if (is_string($sql) && trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] cron-monitoring install failed: '.$e->getMessage());
        }

        // #1202 storytelling: ahg_story (saved/published stories). Single outer try/catch
        // around hasTable() + unprepared() per reference_ci_schema_hastable.md.
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('ahg_story')) {
                $sql = file_get_contents(__DIR__.'/../../database/install_story.sql');
                if (is_string($sql) && trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                }
            } elseif (! \Illuminate\Support\Facades\Schema::hasColumn('ahg_story', 'sources_json')) {
                // #1202 grounding-sources slice: add the attribution column to existing tables.
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE `ahg_story` ADD COLUMN `sources_json` TEXT NULL AFTER `object_ids`');
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] ahg_story install failed: '.$e->getMessage());
        }

        // #1183 point clouds: ahg_point_cloud. Single outer try around hasTable + unprepared.
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('ahg_point_cloud')) {
                $sql = file_get_contents(__DIR__.'/../../database/install_pointcloud.sql');
                if (is_string($sql) && trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] ahg_point_cloud install failed: '.$e->getMessage());
        }

        // #1193 Gaussian splats: ahg_gaussian_splat. Single outer try around hasTable + unprepared.
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('ahg_gaussian_splat')) {
                $sql = file_get_contents(__DIR__.'/../../database/install_gaussian_splat.sql');
                if (is_string($sql) && trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                }
            } elseif (! \Illuminate\Support\Facades\Schema::hasColumn('ahg_gaussian_splat', 'information_object_id')) {
                // #1193 link slice: associate a capture with a museum object.
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE `ahg_gaussian_splat` ADD COLUMN `information_object_id` INT NULL AFTER `slug`');
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] ahg_gaussian_splat install failed: '.$e->getMessage());
        }

        // #1205 capture queue: ahg_capture_queue (the actionable workflow on top of the at-risk
        // register) + the capture_queue_status dropdown group. Single outer try/catch around
        // hasTable() + unprepared() per reference_ci_schema_hastable.md so the CI sqlite fallback
        // cannot crash package:discover before a real DB is wired. The dropdown seed only runs when
        // ahg_dropdown exists and the group is not yet present, so it is a no-op on every boot after
        // the first - and a missing dropdown table never blocks the table install.
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('ahg_capture_queue')) {
                $sql = file_get_contents(__DIR__.'/../../database/install_capture_queue.sql');
                if (is_string($sql) && trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                }
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_dropdown')
                && ! \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy', 'capture_queue_status')->exists()) {
                $seed = file_get_contents(__DIR__.'/../../database/seed_capture_queue_dropdowns.sql');
                if (is_string($seed) && trim($seed) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($seed);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] ahg_capture_queue install failed: '.$e->getMessage());
        }

        // #1244 fixity slice: core_fixity_check_log (the append-only fixity /
        // integrity verification log written by FixityService + ahg:fixity-sweep).
        // NEW, additive table - no existing table is ALTERed. Single outer try/catch
        // around hasTable() + unprepared() per reference_ci_schema_hastable.md so the
        // CI sqlite fallback cannot crash package:discover before a real DB is wired.
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('core_fixity_check_log')) {
                $sql = file_get_contents(__DIR__.'/../../database/install_fixity_check_log.sql');
                if (is_string($sql) && trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] core_fixity_check_log install failed: '.$e->getMessage());
        }
    }
}
