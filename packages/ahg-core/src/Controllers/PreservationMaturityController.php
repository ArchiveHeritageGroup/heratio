<?php

/**
 * PreservationMaturityController - Heratio ahg-core
 *
 * Admin "preservation maturity" self-assessment dashboard. Renders the
 * read-only assessment from PreservationMaturityService: the running instance
 * scored, evidence-based, against the five functional areas of the NDSA Levels
 * of Digital Preservation - storage and geographic location, integrity (fixity
 * and write protection), information security and access control, metadata, and
 * content and file formats. Each area shows its achieved level (Not yet, then
 * Level 1..4), the concrete evidence behind the score, and the next gap to
 * close, alongside an overall summary.
 *
 * Admin-gated via the route's `auth` middleware group (matching the other
 * /admin/* ahg-core report pages such as /admin/data-quality). Read-only - it
 * computes and renders; it never writes, never runs ALTER, and makes no AI
 * calls. The multi-segment path (/admin/preservation-maturity) keeps it clear
 * of the single-segment /{slug} archival-record catch-all. The service never
 * throws; on any failure we render an honest, empty assessment rather than a
 * 500.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\PreservationMaturityService;
use Illuminate\Routing\Controller;

class PreservationMaturityController extends Controller
{
    public function __construct(
        private PreservationMaturityService $service,
    ) {}

    /**
     * The preservation-maturity dashboard. The service never throws; on any
     * failure we render a clean, honest empty assessment rather than a 500.
     */
    public function index()
    {
        try {
            $assessment = $this->service->assess();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] preservation-maturity dashboard failed: '.$e->getMessage());
            $assessment = [
                'areas'              => [],
                'overall_level'      => 0,
                'overall_level_name' => 'Not yet',
                'max_level'          => PreservationMaturityService::MAX_LEVEL,
                'framework'          => 'NDSA Levels of Digital Preservation',
                'framework_note'     => '',
                'digital_objects'    => 0,
                'generated_at'       => now()->toDateTimeString(),
                'error'              => true,
            ];
        }

        return view('ahg-core::preservation-maturity.index', [
            'assessment' => $assessment,
        ]);
    }
}
