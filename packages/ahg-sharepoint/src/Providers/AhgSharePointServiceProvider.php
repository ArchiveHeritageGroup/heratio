<?php

namespace AhgSharePoint\Providers;

use AhgSharePoint\Federation\SharePointFederationConfig;
use AhgSharePoint\Federation\SharePointGraphConnector;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * AhgSharePointServiceProvider — Heratio package wiring.
 *
 * Mirrored in atom-ahg-plugins/ahgSharePointPlugin/config/ahgSharePointPluginConfiguration.class.php.
 * Both must register: routes, queue handlers, settings section, services.
 */
class AhgSharePointServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phase 1 singletons
        $this->app->singleton(\AhgSharePoint\Services\GraphTokenCache::class);
        $this->app->singleton(\AhgSharePoint\Services\GraphClientService::class);

        // Phase 2.A — repositories + services
        $this->app->singleton(\AhgSharePoint\Repositories\SharePointTenantRepository::class);
        $this->app->singleton(\AhgSharePoint\Repositories\SharePointDriveRepository::class);
        $this->app->singleton(\AhgSharePoint\Repositories\SharePointSubscriptionRepository::class);
        $this->app->singleton(\AhgSharePoint\Repositories\SharePointEventRepository::class);
        $this->app->singleton(\AhgSharePoint\Services\SharePointMappingService::class);
        $this->app->singleton(\AhgSharePoint\Services\SharePointRetentionMapper::class);
        $this->app->singleton(\AhgSharePoint\Services\SharePointSubscriptionService::class);
        $this->app->singleton(\AhgSharePoint\Services\SharePointWebhookHandler::class);
        $this->app->singleton(\AhgSharePoint\Services\SharePointIngestAdapter::class);

        // Phase 2.B — push + user mapping + JWT
        $this->app->singleton(\AhgSharePoint\Repositories\SharePointUserMappingRepository::class);
        $this->app->singleton(\AhgSharePoint\Services\GraphTokenValidatorService::class);
        $this->app->singleton(\AhgSharePoint\Services\SharePointUserMappingService::class);
        $this->app->singleton(\AhgSharePoint\Services\SharePointPushService::class);

        // Phase 2 (v2 ingest plan)
        $this->app->singleton(\AhgSharePoint\Services\SharePointBrowserService::class);
        $this->app->singleton(\AhgSharePoint\Services\SharePointAutoIngestService::class);

        // Issue #1221 — SharePoint federated search, self-contained in this
        // package. The connector + its runner read tenant/credentials from this
        // package's OWN M365 tenant store (via GraphClientService /
        // SharePointTenantRepository), never from ahg-federation peer config.
        $this->app->singleton(SharePointFederationConfig::class);
        $this->app->singleton(SharePointGraphConnector::class);
        $this->app->singleton(\AhgSharePoint\Federation\SharePointFederationRunner::class);

        // Connector-discovery registry. ahg-sharepoint contributes its SharePoint
        // arm to config('federation.connectors') so a future EXTENSIBLE
        // ahg-federation dispatcher (post-cutover; see MIGRATION.md step C and
        // cutover.patch) can resolve the connector class WITHOUT the SharePoint
        // FQCN ever appearing in ahg-federation source. Until that dispatcher
        // ships upstream, this registry is harmless metadata: nothing in the
        // live tree reads it, so it neither breaks nor double-registers anything.
        $existing = (array) config('federation.connectors', []);
        $existing[SharePointGraphConnector::PEER_TYPE] = SharePointGraphConnector::class;
        config(['federation.connectors' => $existing]);
    }

    public function boot(): void
    {
        // Routes
        Route::middleware('web')->group(__DIR__.'/../../routes/web.php');
        Route::prefix('api')->middleware('api')->group(__DIR__.'/../../routes/api.php');

        // Migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Views (Phase 1 admin UI templates)
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-sharepoint');

        // Issue #1221 — inject SharePoint config options into the LOCKED
        // ahg-federation peer-edit blade WITHOUT editing it. The peer form is at
        // packages/ahg-federation/resources/views/edit-peer.blade.php, which is
        // locked AND no-push. A View::composer is the unlocked-caller pattern
        // (see memory/reference_view_composer_for_locked_callers): when (and only
        // when) that view renders, these variables become available to it, so the
        // SharePoint sub-fields for peer_type=sharepoint_graph_search
        // (tenant picker + scope hints) can be surfaced by ahg-federation's own
        // markup if/when it opts to read them. We never modify the blade. The
        // composer is registered defensively against the view NAME; if the view
        // does not exist on this instance, Laravel simply never fires it.
        View::composer('ahg-federation::edit-peer', function ($view): void {
            try {
                $cfg = $this->app->make(SharePointFederationConfig::class);
                $view->with([
                    'sharepointConfigured'    => $cfg->isConfigured(),
                    'sharepointTenantOptions' => $cfg->tenantOptions(),
                    'sharepointDefaultTenant' => $cfg->defaultTenantId(),
                ]);
            } catch (\Throwable $e) {
                // Degrade cleanly: never let the composer break the peer form.
                $view->with([
                    'sharepointConfigured'    => false,
                    'sharepointTenantOptions' => [],
                    'sharepointDefaultTenant' => null,
                ]);
            }
        });

        // CLI commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgSharePoint\Console\Commands\SharePointInstallCommand::class,
                \AhgSharePoint\Console\Commands\SharePointTestConnectionCommand::class,
                \AhgSharePoint\Console\Commands\SharePointSyncCommand::class,
                \AhgSharePoint\Console\Commands\SharePointStatusCommand::class,
                // Phase 2 commands registered up-front; they fail loudly until implemented.
                \AhgSharePoint\Console\Commands\SharePointSubscribeCommand::class,
                \AhgSharePoint\Console\Commands\SharePointRenewSubscriptionsCommand::class,
                \AhgSharePoint\Console\Commands\SharePointIngestEventCommand::class,
                \AhgSharePoint\Console\Commands\SharePointAutoIngestCommand::class,
            ]);
        }
    }
}
