<?php

/**
 * UsageEventController - beacon endpoint for per-event COUNTER R5 instrumentation.
 *
 * Receives `view`, `request` (download click), `link_click` events from the
 * usage-tracker.js bundle and writes them via LibraryUsageService::recordAccess.
 *
 * Public POST (no CSRF middleware) so navigator.sendBeacon works during
 * page unload. Validates library_item_id exists; otherwise silently drops.
 *
 * Issue: heratio#766
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class UsageEventController extends Controller
{
    public function __construct(private LibraryUsageService $usage)
    {
    }

    public function record(Request $request): JsonResponse
    {
        // Throttle per IP - prevents abuse beacons inflating stats.
        $ip = (string) $request->ip();
        if (RateLimiter::tooManyAttempts('usage_event:' . $ip, 240)) {
            return response()->json(['ok' => false, 'reason' => 'throttled'], 429);
        }
        RateLimiter::hit('usage_event:' . $ip, 60);

        $data = $request->validate([
            'library_item_id' => 'required|integer|min:1',
            'event' => 'required|in:view,request,link_click,denied,open_access',
        ]);

        // Confirm the library_item exists before writing - silent drop on bad data.
        $exists = DB::table('library_item')->where('id', $data['library_item_id'])->exists();
        if (!$exists) {
            return response()->json(['ok' => false, 'reason' => 'unknown_item'], 200);
        }

        $typeMap = [
            'view'         => 'investigation',
            'request'      => 'request',
            'link_click'   => 'request',
            'denied'       => 'denied',
            'open_access'  => 'open_access',
        ];

        $this->usage->recordAccess(
            (int) $data['library_item_id'],
            $typeMap[$data['event']] ?? 'investigation'
        );

        return response()->json(['ok' => true]);
    }
}
