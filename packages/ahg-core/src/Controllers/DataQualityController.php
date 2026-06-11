<?php

/**
 * DataQualityController - Heratio ahg-core
 *
 * Admin "Metadata completeness / data quality" dashboard. Renders the read-only
 * report from DataQualityService: how many PUBLISHED archival descriptions are
 * missing each key descriptive field, a headline completeness percentage, a
 * per-issue breakdown, and a bounded sample of the worst records so cataloguers
 * can drill in and close the gaps.
 *
 * Admin-gated via the route's `auth` middleware group (matching the other
 * /admin/* ahg-core report pages). Read-only - it computes and renders; it never
 * writes and makes no AI calls. The multi-segment path (/admin/data-quality)
 * keeps it clear of the single-segment /{slug} archival-record catch-all. The
 * service never throws; on any failure we render an honest empty result rather
 * than a 500.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\DataQualityService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DataQualityController extends Controller
{
    public function __construct(
        private DataQualityService $service,
    ) {}

    /**
     * The metadata-completeness dashboard. The worst-records sample is bounded by
     * ?sample= (default 50, max 200). The service never throws; on any failure we
     * render an empty, honest result rather than a 500.
     */
    public function index(Request $request)
    {
        $sampleLimit = (int) $request->query('sample', 50);
        if ($sampleLimit < 0) {
            $sampleLimit = 0;
        } elseif ($sampleLimit > 200) {
            $sampleLimit = 200;
        }

        try {
            $report = $this->service->report(['sampleLimit' => $sampleLimit]);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality dashboard failed: '.$e->getMessage());
            $report = [
                'total' => 0,
                'complete' => 0,
                'completeness_pct' => 0.0,
                'issues' => [],
                'sample' => [],
                'generated_at' => now()->toDateTimeString(),
                'error' => true,
            ];
        }

        return view('ahg-core::data-quality.index', [
            'report' => $report,
            'sampleLimit' => $sampleLimit,
        ]);
    }
}
