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
use AhgCore\Services\CronSchedulerService;
use AhgCore\Services\SettingHelper;
use Illuminate\Support\ServiceProvider;

class AhgCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CronSchedulerService::class);

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
                    ->leftJoin('setting_i18n as si',    function ($j) use ($culture)  { $j->on('s.id', '=', 'si.id')->where('si.culture', '=', $culture); })
                    ->leftJoin('setting_i18n as si_fb', function ($j) use ($fallback) { $j->on('s.id', '=', 'si_fb.id')->where('si_fb.culture', '=', $fallback); })
                    ->where('s.scope', 'ui_label')
                    ->select('s.name', 'si.value as cur', 'si_fb.value as fb')
                    ->get();
                $translatorOverrides = [];
                foreach ($rows as $r) {
                    $val = ($r->cur !== null && $r->cur !== '') ? $r->cur : $r->fb;
                    $val = $val !== null ? strtr((string) $val, ['&nbsp;' => ' ']) : '';
                    if ($val === '') continue;

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
                if (!empty($translatorOverrides)) {
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
            ->group(__DIR__ . '/../../routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-core');

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
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

                // Standalone install bootstrap (Phase 1 #6)
                \AhgCore\Commands\InstallBootstrapCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
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
                            return (new \AhgCore\Services\EncryptionService())->isEnabled();
                        } catch (\Throwable $e) {
                            return false;
                        }
                    });
            });
        }

        // Issue #99: per-user daily cloud-LLM call counter. Auto-install
        // on first boot so the table exists before VoiceLLMService tries
        // to enforce voice_daily_cloud_limit. The outer try also covers
        // hasTable() because composer's post-autoload-dump runs
        // package:discover before any DB is wired in CI; Laravel's default
        // sqlite fallback would otherwise throw and break composer install.
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('voice_usage')) {
                $sql = file_get_contents(__DIR__ . '/../../database/install_voice_usage.sql');
                if (is_string($sql) && trim($sql) !== '') {
                    \Illuminate\Support\Facades\DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            // No DB connection or install hiccup — VoiceLLMService fails
            // open (no enforcement) when the table is missing; install
            // retries on next boot.
            \Log::warning('[ahg-core] voice_usage install failed: ' . $e->getMessage());
        }
    }
}
