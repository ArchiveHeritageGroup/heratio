<?php

namespace AhgRepositoryManage\Providers;

use Illuminate\Support\ServiceProvider;

class AhgRepositoryManageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-repository-manage');

        $this->ensureDescLangScriptColumns();
    }

    /**
     * Self-heal the ISDIAH "Language(s) and script(s) of the description"
     * columns on repository_i18n. These are mirrored in the core schema
     * (00_core_schema.sql) for fresh installs, but existing databases
     * predate them, so add them idempotently on boot. Wrapped so a
     * missing connection during a fresh install can't fault the provider
     * chain (per reference_ci_schema_hastable.md).
     */
    private function ensureDescLangScriptColumns(): void
    {
        try {
            $schema = \Illuminate\Support\Facades\Schema::class;
            if (! $schema::hasTable('repository_i18n')) {
                return;
            }
            foreach (['desc_language', 'desc_script'] as $col) {
                if (! $schema::hasColumn('repository_i18n', $col)) {
                    \Illuminate\Support\Facades\DB::statement(
                        "ALTER TABLE `repository_i18n` ADD COLUMN `{$col}` varchar(255) DEFAULT NULL AFTER `desc_revision_history`"
                    );
                }
            }
        } catch (\Throwable $e) {
            // Operator can add manually:
            //   ALTER TABLE repository_i18n ADD COLUMN desc_language varchar(255) NULL, ADD COLUMN desc_script varchar(255) NULL;
        }
    }
}
