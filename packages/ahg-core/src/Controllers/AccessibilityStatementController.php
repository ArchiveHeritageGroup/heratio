<?php

/**
 * AccessibilityStatementController - Heratio ahg-core
 *
 * heratio#1211 north-star ("every museum for everyone"), public accessibility
 * statement slice. Renders the OUTWARD, public, human-readable accessibility
 * statement at GET /accessibility-statement, following the W3C model accessibility
 * statement structure: commitment, conformance status against WCAG (configurable
 * level, referencing EN 301 549 as a recognised harmonised standard), what is
 * accessible, known limitations stated honestly, how to report a barrier, and the
 * preparation / last-reviewed date.
 *
 * Distinct from the internal /admin/accessibility coverage report (which measures
 * metadata coverage) and /admin/alt-text (which curates descriptions). This page is
 * PUBLIC and needs no login; it is the institution's commitment and conformance
 * claim, jurisdiction-neutral and international.
 *
 * Read-only. It never writes, never runs ALTER, makes no AI calls, and never 500s:
 * AccessibilityStatementService::statement() is guarded, and on any failure here we
 * fall back to a fully-default statement rather than erroring.
 *
 * The route is a SINGLE-segment public path, registered in ahg-core (which boots
 * before ahg-information-object-manage), so it is matched before the single-segment
 * /{slug} archival-record catch-all and wins (first-registered route wins) - exactly
 * like the existing /explore and /open-data public hubs. A normal record slug still
 * resolves because the catch-all only ever matches a path that no earlier route
 * claimed.
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
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\AccessibilityStatementService;
use Illuminate\Routing\Controller;

class AccessibilityStatementController extends Controller
{
    public function __construct(
        private AccessibilityStatementService $service,
    ) {}

    /**
     * The public accessibility statement. Never 500s: on any failure we render a
     * neutral all-defaults statement rather than erroring.
     */
    public function index()
    {
        try {
            $statement = $this->service->statement();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] accessibility statement failed: '.$e->getMessage());
            $statement = [
                'institution'       => AccessibilityStatementService::DEFAULT_INSTITUTION,
                'contact_email'     => AccessibilityStatementService::DEFAULT_CONTACT,
                'contact_url'       => '',
                'conformance_level' => AccessibilityStatementService::DEFAULT_LEVEL_LABEL,
                'wcag_version'      => AccessibilityStatementService::DEFAULT_WCAG_VERSION,
                'prepared_on'       => date('j F Y'),
                'response_days'     => 10,
                'features'          => [],
                'limitations'       => [],
            ];
        }

        return view('ahg-core::accessibility-statement.index', [
            's' => $statement,
        ]);
    }
}
