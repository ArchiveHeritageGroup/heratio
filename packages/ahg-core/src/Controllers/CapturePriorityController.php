<?php

/**
 * CapturePriorityController - Heratio ahg-core
 *
 * heratio#1205 north-star, first slice. Admin report that renders the capture /
 * at-risk register from CapturePriorityService: the records most in need of
 * digitisation or most at risk of loss, ranked by transparent catalogue signals,
 * each with a plain-language reason list.
 *
 * Admin-gated via the route's `auth` middleware group (matching the other
 * /admin/* ahg-core report pages). Read-only - it computes and renders; it never
 * writes. Multi-segment path (/admin/capture-priority) keeps it clear of the
 * single-segment /{slug} archival-record catch-all.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\CapturePriorityService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CapturePriorityController extends Controller
{
    public function __construct(private CapturePriorityService $service) {}

    /**
     * The capture-priority admin report. Bounded by ?limit= (default 100, max 1000,
     * 0 = show all). The service never throws; on any failure we render an empty,
     * honest result rather than a 500.
     */
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 100);
        if ($limit < 0) {
            $limit = 0;
        } elseif ($limit > 1000) {
            $limit = 1000;
        }

        try {
            $report = $this->service->register(['limit' => $limit]);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] capture-priority report failed: '.$e->getMessage());
            $report = [
                'rows' => [],
                'summary' => ['total' => 0, 'no_master' => 0, 'poor_condition' => 0, 'endangered' => 0, 'scored' => 0],
                'reason_counts' => [],
                'weights' => CapturePriorityService::DEFAULT_WEIGHTS,
                'generated_at' => now()->toDateTimeString(),
                'notes' => ['condition_reports' => false, 'museum_metadata' => false],
                'error' => true,
            ];
        }

        return view('ahg-core::capture-priority', [
            'report' => $report,
            'limit' => $limit,
        ]);
    }
}
