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
 * The same service also backs a public, anonymous "race against loss" awareness
 * board (publicBoard) - a dignified, read-only top-N list of the records most at
 * risk of being lost, with a plain explainer of why each is flagged. The public
 * surface is bounded (a small top-N), shows no operator-only detail (raw scoring
 * weights, full reason tallies, internal record ids) and, like the admin report,
 * never writes and never 500s.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\CapturePriorityService;
use AhgCore\Services\CaptureQueueService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CapturePriorityController extends Controller
{
    public function __construct(
        private CapturePriorityService $service,
        private CaptureQueueService $queue,
    ) {}

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

        // #1205 capture queue: surface which of the shown rows are already queued so
        // the view can offer "Add to capture queue" only where it makes sense. The
        // queue service fails safe (returns empty / false) when its table is missing,
        // so a fresh install simply gets no queue controls - the register still renders.
        $rowIds = array_map(fn ($r) => (int) ($r['id'] ?? 0), $report['rows'] ?? []);
        $queueEnabled = $this->queue->isAvailable();
        $queuedIds = $queueEnabled ? $this->queue->queuedIds($rowIds) : [];

        return view('ahg-core::capture-priority.index', [
            'report' => $report,
            'limit' => $limit,
            'queueEnabled' => $queueEnabled,
            'queuedIds' => $queuedIds,
        ]);
    }

    /**
     * Public "race against loss" awareness board. Read-only, anonymous, bounded to
     * a dignified top-N of the most at-risk records (capped well below the admin
     * report). Shows only what is safe in public: title, a plain "why it is at
     * risk" line, and a High/Medium/Low priority badge - never raw weights, full
     * reason tallies or internal record ids. The service never throws; on any
     * failure (or an install with no scored records) we render the empty-state
     * explainer rather than a 500.
     */
    public function publicBoard(Request $request)
    {
        // Bounded, public-safe top-N. Not operator-tunable from the query string.
        $topN = 24;

        try {
            $report = $this->service->register(['limit' => $topN]);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] race-against-loss board failed: '.$e->getMessage());
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

        // Highest achievable score from the active weights, used to map a raw score
        // onto a clear High/Medium/Low band + percentage for the public badge.
        $maxScore = 0;
        foreach (($report['weights'] ?? []) as $w) {
            $maxScore += (int) $w;
        }

        return view('ahg-core::capture-priority.public', [
            'report' => $report,
            'maxScore' => $maxScore,
            'topN' => $topN,
        ]);
    }
}
