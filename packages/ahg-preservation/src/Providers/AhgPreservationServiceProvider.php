<?php

/**
 * AhgPreservationServiceProvider
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgPreservation\Providers;

use AhgPreservation\Console\PremisExportCommand;
use AhgPreservation\Console\PreservationScanCommand;
use AhgPreservation\Console\RunFixitySchedulesCommand;
use AhgPreservation\Services\BagItService;
use AhgPreservation\Services\FixityScanService;
use AhgPreservation\Services\OaisLifecycleService;
use AhgPreservation\Services\PremisRightsService;
use AhgPreservation\Services\PremisXmlSerializer;
use AhgPreservation\Services\PreservationService;
use AhgPreservation\Services\PronomIdentificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgPreservationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PreservationService::class);
        $this->app->singleton(BagItService::class);
        $this->app->singleton(OaisLifecycleService::class);
        $this->app->singleton(PronomIdentificationService::class);
        $this->app->singleton(PremisRightsService::class);
        $this->app->singleton(PremisXmlSerializer::class);
        $this->app->singleton(FixityScanService::class, function ($app) {
            return new FixityScanService($app->make(PreservationService::class));
        });
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-preservation');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunFixitySchedulesCommand::class,
                PremisExportCommand::class,
                PreservationScanCommand::class,
            ]);
        }

        $this->bootCronRegistration();
        $this->bootPremisRightsTable();
        $this->bootPremisObjectColumns();
        $this->bootNormalizationRuleTable();
    }

    /**
     * #1385 Phase 1 - idempotent auto-seed of the normalization rule registry
     * (the FPR) + the "Preservation Master" usage term. Mirrors the package
     * convention: probe via Schema::hasTable and create/seed if absent so fresh
     * and overlay installs never need manual SQL.
     */
    protected function bootNormalizationRuleTable(): void
    {
        try {
            if (! Schema::hasTable('preservation_normalization_rule')) {
                DB::statement(<<<'SQL'
                    CREATE TABLE IF NOT EXISTS preservation_normalization_rule (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        source_pronom VARCHAR(50) NULL,
                        source_mime VARCHAR(100) NULL,
                        purpose VARCHAR(20) NOT NULL DEFAULT 'preservation',
                        target_format VARCHAR(50) NOT NULL,
                        target_ext VARCHAR(12) NOT NULL,
                        target_mime VARCHAR(100) NULL,
                        tool VARCHAR(50) NOT NULL,
                        options JSON NULL,
                        priority INT NOT NULL DEFAULT 100,
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_norm_mime (source_mime),
                        INDEX idx_norm_pronom (source_pronom),
                        INDEX idx_norm_purpose (purpose),
                        INDEX idx_norm_active (is_active)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
            }

            // Seed default preservation rules once (only if the table is empty).
            if (DB::table('preservation_normalization_rule')->count() === 0) {
                $now = now()->format('Y-m-d H:i:s');
                $rule = fn (string $mime, string $fmt, string $ext, string $tmime, string $tool) => [
                    'source_mime' => $mime, 'purpose' => 'preservation',
                    'target_format' => $fmt, 'target_ext' => $ext, 'target_mime' => $tmime,
                    'tool' => $tool, 'priority' => 100, 'is_active' => 1,
                    'created_at' => $now,
                ];
                DB::table('preservation_normalization_rule')->insert([
                    // Images -> TIFF (LZW)
                    $rule('image/jpeg', 'TIFF', 'tiff', 'image/tiff', 'imagemagick'),
                    $rule('image/png',  'TIFF', 'tiff', 'image/tiff', 'imagemagick'),
                    $rule('image/gif',  'TIFF', 'tiff', 'image/tiff', 'imagemagick'),
                    $rule('image/bmp',  'TIFF', 'tiff', 'image/tiff', 'imagemagick'),
                    // PDF -> PDF/A (Ghostscript)
                    $rule('application/pdf', 'PDF/A', 'pdf', 'application/pdf', 'ghostscript'),
                    // Office -> PDF (LibreOffice)
                    $rule('application/msword', 'PDF', 'pdf', 'application/pdf', 'libreoffice'),
                    $rule('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'PDF', 'pdf', 'application/pdf', 'libreoffice'),
                    $rule('application/vnd.ms-excel', 'PDF', 'pdf', 'application/pdf', 'libreoffice'),
                    $rule('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'PDF', 'pdf', 'application/pdf', 'libreoffice'),
                    $rule('application/vnd.ms-powerpoint', 'PDF', 'pdf', 'application/pdf', 'libreoffice'),
                    $rule('application/vnd.openxmlformats-officedocument.presentationml.presentation', 'PDF', 'pdf', 'application/pdf', 'libreoffice'),
                    $rule('application/vnd.oasis.opendocument.text', 'PDF', 'pdf', 'application/pdf', 'libreoffice'),
                    // Audio -> WAV
                    $rule('audio/mpeg', 'WAV', 'wav', 'audio/x-wav', 'ffmpeg'),
                    $rule('audio/ogg',  'WAV', 'wav', 'audio/x-wav', 'ffmpeg'),
                    $rule('audio/aac',  'WAV', 'wav', 'audio/x-wav', 'ffmpeg'),
                    // Video -> MKV (FFV1)
                    $rule('video/mp4',       'MKV', 'mkv', 'video/x-matroska', 'ffmpeg'),
                    $rule('video/quicktime', 'MKV', 'mkv', 'video/x-matroska', 'ffmpeg'),
                    $rule('video/x-msvideo',  'MKV', 'mkv', 'video/x-matroska', 'ffmpeg'),
                ]);
            }

            // Seed the "Preservation Master" usage term (taxonomy 47 = usage).
            $exists = DB::table('term_i18n')
                ->join('term', 'term.id', '=', 'term_i18n.id')
                ->where('term.taxonomy_id', 47)
                ->where('term_i18n.name', 'Preservation Master')
                ->exists();
            if (! $exists) {
                $now = now()->format('Y-m-d H:i:s');
                $oid = DB::table('object')->insertGetId([
                    'class_name' => 'QubitTerm',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'serial_number' => 0,
                ]);
                DB::table('term')->insert([
                    'id' => $oid,
                    'taxonomy_id' => 47,
                    'source_culture' => 'en',
                ]);
                DB::table('term_i18n')->insert([
                    'id' => $oid,
                    'name' => 'Preservation Master',
                    'culture' => 'en',
                ]);
            }
        } catch (\Throwable $e) {
            // Never block boot.
        }
    }

    /**
     * #1179 - make premis_object self-sufficient as a preservation record:
     * its own checksum, significant properties, preservation-master flag and a
     * link to the access digital object. Additive + sentinel-guarded.
     */
    protected function bootPremisObjectColumns(): void
    {
        try {
            // NB: premis_object.id is a FK to object.id (AtoM shared-PK) - it is
            // 1:1 with a digital object, NOT auto-increment; callers set id = the
            // digital object's id.
            if (! Schema::hasTable('premis_object') || Schema::hasColumn('premis_object', 'checksum')) {
                return;
            }
            Schema::table('premis_object', function ($t) {
                $t->string('checksum', 128)->nullable();
                $t->string('checksum_algorithm', 16)->nullable();
                $t->json('significant_properties')->nullable();
                $t->boolean('is_preservation_master')->nullable();
                $t->integer('digital_object_id')->nullable();
            });
        } catch (\Throwable $e) {
            // Never block boot.
        }
    }

    /**
     * Idempotent auto-seed of ahg_premis_rights (Issue #653 Phase 1).
     *
     * Mirrors the package convention: probe via Schema::hasTable and create
     * the table if absent so fresh installs and overlay installs on legacy
     * DBs never need manual SQL execution.
     */
    protected function bootPremisRightsTable(): void
    {
        try {
            if (Schema::hasTable('ahg_premis_rights')) {
                return;
            }
            DB::statement(<<<'SQL'
                CREATE TABLE IF NOT EXISTS ahg_premis_rights (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    information_object_id INT NOT NULL,
                    rights_basis VARCHAR(32) NOT NULL,
                    rights_granted_act VARCHAR(64) NOT NULL,
                    rights_granted_restriction TEXT,
                    applicable_dates_start DATE NULL,
                    applicable_dates_end DATE NULL,
                    source_xml LONGTEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ahg_premis_rights_io (information_object_id),
                    INDEX idx_ahg_premis_rights_basis (rights_basis),
                    INDEX idx_ahg_premis_rights_act (rights_granted_act)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        } catch (\Throwable $e) {
            // Never block boot.
        }
    }

    /**
     * Idempotently register the scheduled-fixity entry in cron_schedule.
     *
     * Runs once per app boot if the entry is missing — keeps fresh installs
     * (and overlay-installs onto existing AtoM DBs) from needing manual setup.
     */
    protected function bootCronRegistration(): void
    {
        try {
            if (! Schema::hasTable('cron_schedule')) {
                return;
            }
            // The legacy slug `preservation-fixity` already exists in some installs and points
            // at an older command. We register the P3.5 scheduled-runner under a distinct slug.
            $exists = DB::table('cron_schedule')->where('slug', 'preservation-fixity-scheduled')->exists();
            if ($exists) {
                return;
            }
            DB::table('cron_schedule')->insert([
                'slug'             => 'preservation-fixity-scheduled',
                'name'             => 'Scheduled Fixity Verification (P3.5)',
                'description'      => 'Walk preservation_workflow_schedule (workflow_type=fixity_check) and verify checksums on stale digital objects. Writes preservation_workflow_run audit rows + PREMIS fixityCheck events.',
                'category'         => 'preservation',
                'artisan_command'  => 'ahg:preservation-fixity-run',
                'is_enabled'       => 1,
                'cron_expression'  => '17 4 * * *',
                'timeout_minutes'  => 120,
                'duration_hint'    => 'long',
                'log_file'         => 'logs/preservation-fixity-scheduled.log',
            ]);
        } catch (\Throwable $e) {
            // Never block boot.
        }
    }
}
