<?php

namespace AhgCore\Providers;

use AhgCore\Services\CronSchedulerService;
use AhgCore\Services\SettingHelper;
use Illuminate\Support\ServiceProvider;

class AhgCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CronSchedulerService::class);
    }

    public function boot(): void
    {
        // Hydrate UI labels from the AtoM setting table so that
        // config('app.ui_label_*') returns admin-customised values.
        $this->app->booted(function () {
            $labels = [
                'ui_label_repository',
                'ui_label_actor',
                'ui_label_informationobject',
                'ui_label_physicalobject',
                'ui_label_accession',
            ];

            foreach ($labels as $key) {
                $dbValue = SettingHelper::get($key);
                if ($dbValue !== '') {
                    config(["app.{$key}" => $dbValue]);
                }
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
                \AhgCore\Commands\IngestCommitCommand::class,
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
            ]);
        }
    }
}
