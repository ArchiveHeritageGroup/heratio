<?php

/**
 * ResearchCustodyController - Controller for Heratio
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

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchCustodyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Chain of custody for reading-room material - #1478.
 *
 * Three views (custody-chain, custody-checkout, custody-return-verify) and the
 * research_custody_handoff table shipped, and nothing else did: no controller,
 * no route, no service, and not one read or write of the table anywhere in
 * PHP. The feature was a table and three screens with no way to reach them.
 *
 * The model is deliberately two-part and mirrors how the physical process
 * actually runs:
 *
 *   - research_material_request holds the CURRENT state of one request - where
 *     the item is, who confirmed the checkout, what condition it came back in.
 *   - research_custody_handoff is the append-only LOG of movements. A row is
 *     never edited; a correction is another row.
 *
 * That split matters because chain of custody is an evidentiary claim. A
 * status column alone can tell you an item is back; only the log can tell you
 * every pair of hands it passed through, which is the entire point.
 */
class ResearchCustodyController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchCustodyService $custody;

    public function __construct(ResearchCustodyService $custody)
    {
        $this->custody = $custody;
    }

    /**
     * The custody history of one material request.
     */
    public function chain(int $id)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $item = $this->custody->getRequestForCustody($id);

        if (! $item) {
            abort(404);
        }

        return view('research::research.custody-chain', array_merge(
            $this->getSidebarData('retrievalQueue'),
            ['item' => $item, 'chain' => $this->custody->getChain($id)]
        ));
    }

    /**
     * Record a checkout - the item leaving the store for a researcher.
     */
    public function checkout(Request $request, int $id)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $item = $this->custody->getRequestForCustody($id);

        if (! $item) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'checkout_date'   => 'nullable|date',
                'expected_return' => 'nullable|date',
                'condition'       => 'nullable|string|max:50',
                'notes'           => 'nullable|string',
            ]);

            $this->custody->recordCheckout($id, $validated, (int) Auth::id());

            return redirect()->route('research.custodyChain', ['id' => $id])
                ->with('success', 'Checkout recorded.');
        }

        return view('research::research.custody-checkout', array_merge(
            $this->getSidebarData('retrievalQueue'),
            [
                'item' => $item,
                // The checkout screen names the researcher from the booking the
                // request belongs to; there is no separate researcher on the
                // request itself.
                'researcher' => $this->custody->getResearcherForRequest($id),
            ]
        ));
    }

    /**
     * Verify a return - the item coming back, and in what condition.
     */
    public function returnVerify(Request $request, int $id)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $checkout = $this->custody->getCheckoutForVerification($id);

        if (! $checkout) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'return_condition' => 'required|string|max:50',
                'return_notes'     => 'required|string',
                // The physical-inspection confirmation is the point of the
                // screen, so it is required rather than optional - the view's
                // own checkbox already carries `required`.
                'confirm_return'   => 'accepted',
            ]);

            $this->custody->recordReturn($id, $validated, (int) Auth::id());

            return redirect()->route('research.custodyChain', ['id' => $id])
                ->with('success', 'Return verified.');
        }

        return view('research::research.custody-return-verify', array_merge(
            $this->getSidebarData('retrievalQueue'),
            ['checkout' => $checkout]
        ));
    }
}
