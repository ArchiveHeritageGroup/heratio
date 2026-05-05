<?php

namespace AhgInformationObjectManage\Providers;

use Illuminate\Support\ServiceProvider;

class AhgInformationObjectManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-io-manage');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-information-object-manage');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgInformationObjectManage\Console\Commands\ImportArchivesCsvCommand::class,
            ]);
        }

        $this->ensureSecurityTable();
    }

    /**
     * Idempotent first-boot install for the ahg_io_security sidecar.
     * Mirrors the convention used by ahg-dropdown-manage / ahg-registry —
     * cheap Schema::hasTable() guard + CREATE TABLE IF NOT EXISTS so a
     * fresh install lights up the security panel without a manual migrate.
     */
    private function ensureSecurityTable(): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('ahg_io_security')) {
                \Illuminate\Support\Facades\DB::unprepared(<<<'SQL'
                    CREATE TABLE IF NOT EXISTS ahg_io_security (
                        object_id                       INT          NOT NULL PRIMARY KEY,
                        security_classification_id      INT UNSIGNED NULL,
                        security_reason                 TEXT         NULL,
                        security_review_date            DATE         NULL,
                        security_declassify_date        DATE         NULL,
                        security_handling_instructions  TEXT         NULL,
                        security_inherit_to_children    TINYINT(1)   NOT NULL DEFAULT 0,
                        watermark_type_id               INT UNSIGNED NULL,
                        created_at                      DATETIME     NULL,
                        updated_at                      DATETIME     NULL,
                        CONSTRAINT fk_ahg_io_security_object
                            FOREIGN KEY (object_id) REFERENCES information_object(id) ON DELETE CASCADE,
                        CONSTRAINT fk_ahg_io_security_class
                            FOREIGN KEY (security_classification_id) REFERENCES security_classification(id) ON DELETE SET NULL,
                        CONSTRAINT fk_ahg_io_security_wm
                            FOREIGN KEY (watermark_type_id) REFERENCES watermark_type(id) ON DELETE SET NULL
                    );
                SQL);
                return;
            }
            // Forward-migrate existing installs: add the watermark_type_id
            // column if it predates the watermark UI block.
            if (!\Illuminate\Support\Facades\Schema::hasColumn('ahg_io_security', 'watermark_type_id')) {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE ahg_io_security ADD COLUMN watermark_type_id INT UNSIGNED NULL');
                try {
                    \Illuminate\Support\Facades\DB::statement(
                        'ALTER TABLE ahg_io_security ADD CONSTRAINT fk_ahg_io_security_wm '
                        . 'FOREIGN KEY (watermark_type_id) REFERENCES watermark_type(id) ON DELETE SET NULL'
                    );
                } catch (\Throwable $e) { /* FK may already exist; skip */ }
            }
            // Forward-migrate: persistent "Make this the default for existing
            // children" flag (sticky version of the form's updateDescendants
            // checkbox — keeps the box visibly ticked across reloads).
            if (!\Illuminate\Support\Facades\Schema::hasColumn('ahg_io_security', 'update_descendants_default')) {
                \Illuminate\Support\Facades\DB::statement(
                    'ALTER TABLE ahg_io_security ADD COLUMN update_descendants_default TINYINT(1) NOT NULL DEFAULT 0'
                );
            }
        } catch (\Throwable $e) {
            // Don't kill app boot on a schema hiccup; surface in the log.
            \Illuminate\Support\Facades\Log::warning('[ahg-io-manage] ensureSecurityTable failed: ' . $e->getMessage());
        }
    }
}
