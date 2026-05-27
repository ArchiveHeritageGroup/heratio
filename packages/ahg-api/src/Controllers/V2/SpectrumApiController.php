<?php

declare(strict_types=1);

/**
 * Heratio - Spectrum public API controller (v2).
 *
 * Exposes machine-readable Spectrum 5.1 surfaces to external consumers:
 *
 *   GET /api/v2/spectrum/statistics              Aggregate procedure stats
 *                                                (object count, per-procedure
 *                                                breakdown, per-status counts,
 *                                                trailing-30-day activity).
 *                                                Cached 5 minutes.
 *
 *   GET /api/v2/spectrum/events?since=&limit=    Chronological procedure events
 *                                                feed (object accepted, valued,
 *                                                loaned, etc.). RFC 3339 since
 *                                                cursor, server caps limit at
 *                                                500.
 *
 *   GET /api/v2/spectrum/activity/{object_id}    Per-object procedure timeline
 *                                                + current per-procedure status.
 *
 * Uses the existing ahg-api bearer-token middleware (api.auth:read) so the
 * caller must hold a valid scoped API key. Delegates all aggregation to
 * \AhgSpectrum\Services\SpectrumStatisticsService.
 *
 * Reference: PSIS ahgSpectrumPlugin statisticsApiAction / eventApiAction.
 *
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems (Pty) Ltd
 * @license   AGPL-3.0-or-later
 * @package   AhgApi\Controllers\V2
 */

namespace AhgApi\Controllers\V2;

use AhgSpectrum\Services\SpectrumStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SpectrumApiController extends BaseApiController
{
    /**
     * Cache key for the /statistics aggregate. Per the brief, statistics are
     * cached 5 minutes.
     */
    public const STATS_CACHE_KEY = 'ahg.api.v2.spectrum.statistics';

    public const STATS_CACHE_TTL = 300; // 5 minutes in seconds

    public function __construct(
        protected SpectrumStatisticsService $stats,
    ) {
        parent::__construct();
    }

    /**
     * GET /api/v2/spectrum/statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $fresh = (bool) $request->boolean('fresh', false);
        if ($fresh) {
            Cache::forget(self::STATS_CACHE_KEY);
        }

        $data = Cache::remember(
            self::STATS_CACHE_KEY,
            self::STATS_CACHE_TTL,
            fn () => $this->stats->aggregate(),
        );

        return $this->success($data, 200, [
            'meta' => [
                'cached_for_seconds' => self::STATS_CACHE_TTL,
                'cache_key' => self::STATS_CACHE_KEY,
            ],
        ]);
    }

    /**
     * GET /api/v2/spectrum/events.
     */
    public function events(Request $request): JsonResponse
    {
        $sinceRaw = $request->get('since');
        $since = null;
        if (! empty($sinceRaw)) {
            try {
                $since = new \DateTimeImmutable((string) $sinceRaw);
            } catch (\Throwable $e) {
                return $this->error('Bad Request', 'Invalid since parameter; use an ISO-8601 timestamp.', 400);
            }
        }

        $limit = (int) $request->get('limit', 50);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $events = $this->stats->events($since, $limit);

        return $this->success([
            'events' => $events,
        ], 200, [
            'meta' => [
                'count' => count($events),
                'limit' => $limit,
                'since' => $since?->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }

    /**
     * GET /api/v2/spectrum/activity/{objectId}.
     */
    public function activity(Request $request, int $objectId): JsonResponse
    {
        if ($objectId < 1) {
            return $this->error('Bad Request', 'object_id must be a positive integer.', 400);
        }

        $payload = $this->stats->activity($objectId);
        if ($payload === null) {
            return $this->error('Not Found', "Information object {$objectId} not found.", 404);
        }

        return $this->success($payload);
    }
}
