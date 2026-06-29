<?php

namespace AhgIngest\Providers;

use Illuminate\Support\ServiceProvider;

class AhgIngestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ingest');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgIngest\Console\IngestCommitCommand::class,
            ]);
        }

        $this->bootNormalizeColumn();
        $this->bootProvenanceColumn();
    }

    /**
     * #1385 - idempotently add the opt-in ingest_session.process_normalize
     * toggle. Mirrors the package convention (Schema probe in boot) so fresh
     * and overlay installs never need manual SQL.
     */
    protected function bootNormalizeColumn(): void
    {
        try {
            $schema = \Illuminate\Support\Facades\Schema::class;
            if ($schema::hasTable('ingest_session') && ! $schema::hasColumn('ingest_session', 'process_normalize')) {
                $schema::table('ingest_session', function ($t) {
                    $t->boolean('process_normalize')->default(0)->after('process_face_detect');
                });
            }
        } catch (\Throwable $e) {
            // Never block boot.
        }
    }

    /**
     * Custodial-provenance capture for folder/directory ingest: a free-form
     * "source / donor / acquisition" note recorded against the ingest_file
     * batch, embedded into each parsed row and written to provenance_entry at
     * commit. Same idempotent Schema-probe convention as the normalize toggle.
     */
    protected function bootProvenanceColumn(): void
    {
        try {
            $schema = \Illuminate\Support\Facades\Schema::class;
            if ($schema::hasTable('ingest_file') && ! $schema::hasColumn('ingest_file', 'provenance_source')) {
                $schema::table('ingest_file', function ($t) {
                    $t->text('provenance_source')->nullable();
                });
            }
        } catch (\Throwable $e) {
            // Never block boot.
        }
    }
}
