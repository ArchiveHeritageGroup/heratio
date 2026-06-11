<?php

namespace AhgSemanticSearch\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgSemanticSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // heratio#1210 - public "Discoveries" surface. Registered here in
        // register() (not boot()) via callAfterResolving('router') so it binds
        // BEFORE the single-segment /{slug} archival-record catch-all in
        // ahg-information-object-manage. That package's composer name sorts
        // before this one, so its boot() (which registers the catch-all) runs
        // ahead of our boot(); but register() runs for ALL providers before ANY
        // boot(), so this route wins the match for the single-segment public
        // path /discoveries. See memory/reference_slug_catchall_route_precedence.md.
        $this->callAfterResolving('router', function ($router) {
            $router->middleware('web')
                ->get('/discoveries', [
                    \AhgSemanticSearch\Controllers\DiscoveriesController::class, 'index',
                ])
                ->name('scholarship.discoveries');

            // heratio#1210 - detail view for a single stored discovery. Numeric
            // constraint so it never shadows the /discoveries index or a slug.
            $router->middleware('web')
                ->get('/discoveries/{id}', [
                    \AhgSemanticSearch\Controllers\DiscoveriesController::class, 'show',
                ])
                ->where('id', '[0-9]+')
                ->name('scholarship.discoveries.show');

            // heratio#1207 - public "Displaced heritage register" surface.
            // Single-segment public path, registered the same way as
            // /discoveries (register() + callAfterResolving('router')) so it
            // binds BEFORE the single-segment /{slug} archival-record catch-all
            // in ahg-information-object-manage. See the note above and
            // memory/reference_slug_catchall_route_precedence.md.
            $router->middleware('web')
                ->get('/displaced-heritage', [
                    \AhgSemanticSearch\Controllers\DisplacedHeritageRegisterController::class, 'index',
                ])
                ->name('displaced-heritage.index');
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-semantic-search');

        // heratio#1210 - persistence layer for generative scholarship. The
        // curated discovery set is stored in ahg_scholarship_discovery so the
        // public page can render stable, browsable, citable discoveries instead
        // of regenerating them on every load. Auto-created on first boot, the
        // canonical Heratio package idiom (Schema::hasTable probe wrapped in a
        // single try/catch so a fresh boot never fatals - see the CI rule in
        // memory/reference_ci_schema_hastable.md).
        $this->bootScholarshipDiscoveryTable();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgSemanticSearch\Console\Commands\KmGraphSyncCommand::class,
                \AhgSemanticSearch\Console\Commands\KmExportGraphCommand::class,
                \AhgSemanticSearch\Console\Commands\ScholarshipDiscoverCommand::class,
                \AhgSemanticSearch\Console\Commands\GenerateDiscoveriesCommand::class,
                \AhgSemanticSearch\Console\Commands\DisplacedHeritageScanCommand::class,
            ]);
        }
    }

    /**
     * Idempotent first-boot creation of the persisted-discovery table.
     *
     * One row per curated information object holds the AI-surfaced discovery
     * (title, lead/summary, confidence, and a JSON snapshot of the evidence the
     * lead rests on) so the public Discoveries page can read a stable, fast,
     * citable set rather than recomputing on every request. The whole probe is
     * wrapped in a single try/catch so a missing/locked DB at boot can never
     * fatal the app - the controller falls back to on-demand generation.
     */
    protected function bootScholarshipDiscoveryTable(): void
    {
        try {
            if (Schema::hasTable('ahg_scholarship_discovery')) {
                return;
            }

            Schema::create('ahg_scholarship_discovery', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('information_object_id');
                $table->string('title', 1024)->nullable();
                $table->text('summary')->nullable();
                $table->unsignedInteger('connection_count')->default(0);
                $table->unsignedSmallInteger('confidence')->default(0);
                $table->json('evidence')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->unique('information_object_id', 'uq_scholarship_discovery_io');
                $table->index(['information_object_id', 'confidence'], 'ix_scholarship_discovery_io_conf');
            });

            Log::info('ahg-semantic-search: ahg_scholarship_discovery created (first-boot)');
        } catch (\Throwable $e) {
            // Never block boot on install failure - log and continue. The
            // Discoveries page degrades to on-demand generation when the table
            // is absent.
            Log::warning('ahg-semantic-search scholarship-discovery boot install skipped: '.$e->getMessage());
        }
    }
}
