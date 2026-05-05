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
            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_io_security')) {
                return;
            }
            \Illuminate\Support\Facades\DB::unprepared(<<<'SQL'
                CREATE TABLE IF NOT EXISTS ahg_io_security (
                    object_id                       INT          NOT NULL PRIMARY KEY,
                    security_classification_id      INT UNSIGNED NULL,
                    security_reason                 TEXT         NULL,
                    security_review_date            DATE         NULL,
                    security_declassify_date        DATE         NULL,
                    security_handling_instructions  TEXT         NULL,
                    security_inherit_to_children    TINYINT(1)   NOT NULL DEFAULT 0,
                    created_at                      DATETIME     NULL,
                    updated_at                      DATETIME     NULL,
                    CONSTRAINT fk_ahg_io_security_object
                        FOREIGN KEY (object_id) REFERENCES information_object(id) ON DELETE CASCADE,
                    CONSTRAINT fk_ahg_io_security_class
                        FOREIGN KEY (security_classification_id) REFERENCES security_classification(id) ON DELETE SET NULL
                );
            SQL);
        } catch (\Throwable $e) {
            // Don't kill app boot on a schema hiccup; surface in the log.
            \Illuminate\Support\Facades\Log::warning('[ahg-io-manage] ensureSecurityTable failed: ' . $e->getMessage());
        }
    }
}
