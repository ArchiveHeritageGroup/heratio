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
            // library_item_id is required for everything except a platform search.
            'library_item_id' => 'required_unless:event,search|nullable|integer|min:1',
            'event' => 'required|in:view,request,link_click,denied,open_access,search',
        ]);

        $event = $data['event'];

        $typeMap = [
            'view'         => 'investigation',
            'request'      => 'request',
            'link_click'   => 'request',
            'denied'       => 'denied',
            'open_access'  => 'open_access',
            'search'       => 'search',
        ];
        $type = $typeMap[$event] ?? 'investigation';

        // Resolve an anonymised session token + the acting user (if any).
        $sessionId = $this->resolveSessionId($request);
        $userId    = optional($request->user())->id;

        if ($type === 'search') {
            // Platform-level event: no item to validate.
            $this->usage->recordAccess(0, 'search', $sessionId, $userId ? (int) $userId : null);
            return response()->json(['ok' => true]);
        }

        // Confirm the library_item exists before writing - silent drop on bad data.
        $exists = DB::table('library_item')->where('id', $data['library_item_id'])->exists();
        if (!$exists) {
            return response()->json(['ok' => false, 'reason' => 'unknown_item'], 200);
        }

        $this->usage->recordAccess(
            (int) $data['library_item_id'],
            $type,
            $sessionId,
            $userId ? (int) $userId : null
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Resolve the anonymised per-session token injected by InjectUsageTracker
     * (cookie 'lib_usage_sid'), falling back to the framework session id. The
     * value is hashed downstream so no raw identifier is persisted.
     */
    private function resolveSessionId(Request $request): ?string
    {
        $cookie = $request->cookie('lib_usage_sid');
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }
        try {
            $sid = $request->hasSession() ? $request->session()->getId() : null;
            return $sid ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
