<?php

/**
 * EndangeredInboundController - Heratio
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

namespace AhgSemanticSearch\Controllers;

use AhgSemanticSearch\Services\EndangeredInboundService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * #1205 PUSH-MODEL peer inbound - the staff REVIEW queue. Federation peers POST
 * at-risk flags to /api/v1/endangered/inbound; they land 'pending' and are shown
 * here for a curator to ACCEPT (then they surface on the cross-institution board)
 * or DECLINE. Admin-gated (the route group applies auth + admin).
 */
class EndangeredInboundController extends Controller
{
    public function __construct(
        private EndangeredInboundService $service = new EndangeredInboundService,
    ) {}

    /** The pending-pushes review queue, with per-status counts. */
    public function index()
    {
        return view('ahg-semantic-search::endangered.inbound', [
            'pending'      => $this->service->pending(),
            'statusCounts' => $this->service->statusCounts(),
        ]);
    }

    /** Record a curator decision (accept / decline) on one pushed flag. */
    public function review(Request $request, $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:accepted,declined',
        ]);

        $userId = $request->user() ? (int) $request->user()->id : null;
        $ok = $this->service->review((int) $id, $validated['decision'], $userId);

        $msg = $validated['decision'] === 'accepted'
            ? __('Pushed flag accepted - it will now appear on the cross-institution board.')
            : __('Pushed flag declined.');

        return back()->with($ok ? 'success' : 'error', $ok ? $msg : __('The decision could not be recorded.'));
    }
}
