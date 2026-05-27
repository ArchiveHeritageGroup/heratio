<?php

namespace AhgMediaStreaming\Providers;

use AhgMediaStreaming\Services\CaptionTrackService;
use AhgMediaStreaming\Services\StreamingService;
use AhgMediaStreaming\Services\TranscodingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AhgMediaStreamingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TranscodingService::class, function () {
            return new TranscodingService;
        });

        $this->app->singleton(StreamingService::class, function ($app) {
            return new StreamingService($app->make(TranscodingService::class));
        });

        $this->app->singleton(CaptionTrackService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');

        // #757 caption tracks: auto-inject active tracks into the media-player
        // Blade component whenever a caller has not supplied them. Avoids
        // editing the (locked) caller views in ahg-library / ahg-io-manage /
        // ahg-theme-b5 / ahg-core that already invoke the component.
        View::composer('theme::components.media-player', function ($view) {
            $data = $view->getData();
            if (isset($data['tracks']) && !empty($data['tracks'])) {
                return; // caller already passed tracks; respect them
            }
            // Prop is named differently across callers in the locked tree. Try
            // the explicit names first, then fall back to extracting the
            // trailing numeric ID from playerId, which the IO-manage callers
            // construct as 'ahg-{video|audio}-{ref|show|stream}-{digital_object.id}'.
            $digitalObjectId = $data['digitalObjectId']
                ?? $data['digital_object_id']
                ?? null;
            if (!$digitalObjectId && isset($data['playerId']) && is_string($data['playerId'])) {
                if (preg_match('/-(\d+)$/', $data['playerId'], $m)) {
                    $digitalObjectId = (int) $m[1];
                }
            }
            if (!$digitalObjectId) return;
            try {
                $svc = $this->app->make(CaptionTrackService::class);
                $tracks = $svc->getActiveForPlayer((int) $digitalObjectId)->map(function ($t) {
                    return [
                        'src'      => route('media-streaming.captions', ['trackId' => $t->id]),
                        'kind'     => $t->track_type ?? 'subtitles',
                        'srclang'  => $t->language_code ?? 'en',
                        'label'    => $t->label ?? '',
                        'default'  => (bool) ($t->is_default ?? false),
                    ];
                })->all();
                $view->with('tracks', $tracks);
            } catch (\Throwable) {
                // Never let caption hydration break the page.
            }
        });
    }
}
