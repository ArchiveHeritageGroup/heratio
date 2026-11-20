<?php

/**
 * MediaRegenerateController - the "Regenerate playback copies for all videos"
 * button on the media settings Protection card. Queues one RegenerateMediaJob
 * per video master, as `ahg:media-regenerate --all-video --queue` does.
 * Administrators only (the route sits in the admin group).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgSettings\Controllers;

use AhgCore\Jobs\RegenerateMediaJob;
use AhgCore\Services\MediaRegenerateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class MediaRegenerateController extends Controller
{
    public function store(): RedirectResponse
    {
        $ids = MediaRegenerateService::targets([], [], true);
        foreach ($ids as $id) {
            RegenerateMediaJob::dispatch($id);
        }
        Log::info('[media-regenerate] '.count($ids).' video master(s) queued from the media settings by user '.auth()->id());

        return redirect()->route('settings.ahg.media')
            ->with('notice', __(':count video master(s) queued to have their playback copies made again.', ['count' => count($ids)]));
    }
}
