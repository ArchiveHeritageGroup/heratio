<?php

/**
 * AccessibilityReportController - Heratio ahg-core
 *
 * heratio#1211 north-star ("every museum for everyone"), accessibility slice.
 * Admin "digital accessibility" coverage dashboard. Renders the read-only
 * snapshot from AccessibilityReportService: how much PUBLISHED content carries
 * the accessibility-relevant metadata Heratio stores - image descriptions,
 * captions / subtitles, transcripts, 3D-model alternative text, and multilingual
 * reach - each with a coverage level and an honest gap recommendation, framed as
 * a heuristic coverage report rather than a WCAG conformance audit.
 *
 * Admin-gated via the route's `auth` middleware group (matching the other
 * /admin/* ahg-core report pages such as /admin/data-quality and
 * /admin/preservation-maturity). Read-only - it computes and renders; it never
 * writes, never runs ALTER, and makes no AI calls. The two-segment
 * /admin/accessibility path keeps it clear of the single-segment /{slug}
 * archival-record catch-all (that route only ever matches ONE path segment). The
 * service never throws; on any failure we render an honest, empty report rather
 * than a 500.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\AccessibilityReportService;
use Illuminate\Routing\Controller;

class AccessibilityReportController extends Controller
{
    public function __construct(
        private AccessibilityReportService $service,
    ) {}

    /**
     * The digital-accessibility coverage dashboard. The service never throws; on
     * any failure we render a clean, honest empty report rather than a 500.
     */
    public function index()
    {
        try {
            $report = $this->service->snapshot();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] accessibility dashboard failed: '.$e->getMessage());
            $report = [
                'framework'          => 'WCAG 2.1 AA (heuristic coverage, not a conformance audit)',
                'framework_note'     => '',
                'total_published'    => 0,
                'areas'              => [],
                'overall_level'      => AccessibilityReportService::LEVEL_NOT_MEASURED,
                'overall_level_name' => 'Not measured',
                'generated_at'       => now()->toDateTimeString(),
                'error'              => true,
            ];
        }

        return view('ahg-core::accessibility.index', [
            'report' => $report,
        ]);
    }
}
