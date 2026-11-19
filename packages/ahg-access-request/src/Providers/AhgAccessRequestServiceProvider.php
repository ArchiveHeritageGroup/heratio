<?php

namespace AhgAccessRequest\Providers;

use Illuminate\Support\ServiceProvider;

class AhgAccessRequestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-access-request');

        // heratio#1773: the access-request workflow's last decision decides the request.
        \Illuminate\Support\Facades\Event::listen('workflow.finished', function (string $type, int $id, string $decision, int $userId, ?string $comment = null) {
            if ($type === \AhgAccessRequest\Services\AccessRequestService::WORKFLOW_TYPE) {
                app(\AhgAccessRequest\Services\AccessRequestService::class)->onWorkflowFinished($id, $decision, $userId, $comment);
            }
        });
    }
}
