<?php

/**
 * FixityController - Heratio ahg-core
 *
 * heratio#1244 (fixity slice). Admin "Fixity / integrity" report at
 * /admin/fixity. Renders the read-only coverage report from
 * AhgCore\Services\FixityService: how many digital objects carry a verifiable
 * checksum baseline, how many have never been verified, the result roll-up of
 * the most recent verification (match / mismatch / missing_file / ...), a
 * "last sweep" summary, and the most recent individual checks.
 *
 * Admin-gated via the route's `auth` middleware group (matching the other
 * /admin/* ahg-core report pages such as /admin/preservation-maturity and
 * /admin/data-quality). Read-only - it computes and renders; it never writes,
 * never runs ALTER, and makes no AI calls. The two-segment /admin/fixity path
 * keeps it clear of the single-segment /{slug} archival-record catch-all. The
 * service never throws; on any failure we render an honest empty report rather
 * than a 500.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\FixityService;
use Illuminate\Routing\Controller;

class FixityController extends Controller
{
    public function __construct(
        private FixityService $service,
    ) {}

    /**
     * The fixity / integrity report. The service never throws; on any failure we
     * render a clean, honest empty report rather than a 500.
     */
    public function index()
    {
        try {
            $coverage = $this->service->coverage(25);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] fixity report failed: '.$e->getMessage());
            $coverage = [
                'total'            => 0,
                'with_baseline'    => 0,
                'without_baseline' => 0,
                'never_verified'   => 0,
                'algorithms'       => [],
                'last_sweep'       => null,
                'recent'           => [],
                'results'          => [],
                'generated_at'     => now()->toDateTimeString(),
                'available'        => false,
                'error'            => true,
            ];
        }

        return view('ahg-core::fixity.index', [
            'coverage' => $coverage,
        ]);
    }
}
