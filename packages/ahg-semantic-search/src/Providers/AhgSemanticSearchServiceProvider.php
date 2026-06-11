<?php

namespace AhgSemanticSearch\Providers;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgSemanticSearch\Console\Commands\KmGraphSyncCommand::class,
                \AhgSemanticSearch\Console\Commands\ScholarshipDiscoverCommand::class,
                \AhgSemanticSearch\Console\Commands\DisplacedHeritageScanCommand::class,
            ]);
        }
    }
}
