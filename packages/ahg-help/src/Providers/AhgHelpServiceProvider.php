<?php

namespace AhgHelp\Providers;

use AhgHelp\Services\HelpArticleService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AhgHelpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // #1332 - contextual help map (route/path -> help article slug).
        $this->mergeConfigFrom(__DIR__.'/../../config/help-context.php', 'help-context');
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-help');

        // #1332 - expose the current page's contextual help article to every
        // view as $contextualHelp (['slug','title','url'] or null). Resolved
        // once per request at render time (route is known by then) and cached.
        View::composer('*', function ($view) {
            static $resolved = false;
            static $help = null;
            if (! $resolved) {
                $resolved = true;
                try {
                    $req = request();
                    $help = HelpArticleService::contextualFor(
                        optional($req->route())->getName(),
                        $req->path()
                    );
                } catch (\Throwable $e) {
                    $help = null;
                }
            }
            $view->with('contextualHelp', $help);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgHelp\Commands\IngestHelpArticleCommand::class,
                \AhgHelp\Commands\IngestAllHelpCommand::class,
            ]);
        }
    }
}
