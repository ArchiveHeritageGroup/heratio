<?php

/**
 * LoanAnalyticsController - admin-gated, read-only loan analytics dashboard
 * for the federated GLAM network (#1203 loan-analytics slice).
 *
 *   GET /federation/loans/analytics       HTML dashboard (big numbers + CSS bars)
 *   GET /federation/loans/analytics.json  machine surface (CORS-open) of the same
 *
 * Read-only aggregate report over federation_loan_request (+ federation_member
 * for partner names): counts by status, incoming vs outgoing relative to the
 * self-member, top borrowers / top lenders, approval rate, and average
 * turnaround. No new table, no writes, no ALTER.
 *
 * The path is multi-segment (/federation/loans/analytics), so the locked
 * single-segment /{slug} catch-all never intercepts it. It also sits ALONGSIDE
 * the /federation/loans/{id} numeric route without colliding: the {id} route is
 * constrained with ->whereNumber('id'), so the word "analytics" never matches
 * it, and the analytics routes are additionally registered before {id} in the
 * loan provider for ordering safety.
 *
 * Admin-gated (auth + admin middleware on the route group). Never 500s - the
 * service is Schema::hasTable-guarded and the view carries an empty-state.
 *
 * Fresh code under #1203 - never touches the locked F3 FederationController /
 * edit-peer view / Connectors / FederatedSearchService.
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

namespace AhgFederation\Controllers;

use AhgFederation\Services\LoanAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class LoanAnalyticsController extends Controller
{
    public function __construct(private LoanAnalyticsService $service)
    {
    }

    /** HTML analytics dashboard (big numbers + simple CSS bars). */
    public function index()
    {
        $report = $this->service->report();

        return view('ahg-federation::loans.analytics', [
            'report' => $report,
            'statuses' => LoanAnalyticsService::STATUSES,
        ]);
    }

    /** JSON machine surface (CORS-open) of the same aggregates. */
    public function json(): JsonResponse
    {
        $report = $this->service->report();

        $self = $report['self_member'];

        $payload = [
            'network' => 'glam-federation',
            'report' => 'loan-analytics',
            'ready' => (bool) $report['ready'],
            'total' => (int) $report['total'],
            'self_member' => [
                'id' => (int) $report['self_member_id'],
                'name' => $self->name ?? null,
            ],
            'status_counts' => $report['status_counts'],
            'direction' => $report['direction'],
            'approval' => $report['approval'],
            'turnaround' => $report['turnaround'],
            'top_borrowers' => $report['top_borrowers'],
            'top_lenders' => $report['top_lenders'],
        ];

        return response()
            ->json($payload)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }
}
