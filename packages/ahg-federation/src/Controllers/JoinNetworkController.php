<?php

/**
 * JoinNetworkController - the PUBLIC "Join the network" surface (#1203 slice).
 *
 *   GET  /federation/join            explains the network + the request form
 *   POST /federation/join            submits the request (lands status=pending)
 *   GET  /federation/join/thanks     confirmation page after a successful POST
 *
 * Anonymous-readable and anonymous-submittable on purpose - this is how an
 * institution that is not yet on the system asks to join the federated GLAM
 * network. The submission is MODERATED: it lands as 'pending' and an admin
 * reviews it via JoinRequestModerationController. Approving never auto-creates
 * a federation_member; that stays the admin's deliberate action in the member
 * registry.
 *
 * Spam-resilience without requiring auth:
 *   - full server-side validation (required institution name, email format,
 *     URL shape, length caps);
 *   - a honeypot field ("website") that real users never fill - bots that fill
 *     every input get silently accepted-looking but dropped;
 *   - a minimum-dwell timestamp check (a form submitted within ~2s of render
 *     is almost certainly a bot).
 *
 * Never 500s: the write path is Schema::hasTable-guarded in the service and a
 * failed insert degrades to a friendly "could not record your request" message.
 *
 * Fresh code under #1203 - separate from the locked F3 FederationController.
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

use AhgFederation\Services\JoinRequestService;
use AhgFederation\Services\NetworkDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class JoinNetworkController extends Controller
{
    public function __construct(
        private JoinRequestService $service,
        private NetworkDirectoryService $directory
    ) {
    }

    /** The public landing + request form. */
    public function index()
    {
        // Headline totals make the network-effects framing concrete; the
        // directory service is itself guarded + empty-state safe.
        $dir = $this->directory->directory();

        return view('ahg-federation::join.index', [
            'memberCount' => $dir['memberCount'] ?? 0,
            'recordCount' => $dir['recordCount'] ?? 0,
            // Render timestamp for the minimum-dwell anti-bot check.
            'renderedAt' => time(),
        ]);
    }

    /** Handle a public submission. */
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a hidden "website" field. Real users leave it blank; many
        // bots fill every field. If it has content, pretend success and drop.
        if (trim((string) $request->input('website', '')) !== '') {
            return redirect()->route('federation.join.thanks');
        }

        // Minimum-dwell: the form carries the server render time. A submission
        // that arrives implausibly fast is almost certainly automated. We
        // accept-and-drop rather than error so we do not coach a bot.
        $renderedAt = (int) $request->input('rendered_at', 0);
        if ($renderedAt > 0 && (time() - $renderedAt) < 2) {
            return redirect()->route('federation.join.thanks');
        }

        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'base_url' => ['nullable', 'url', 'max:1024'],
            'what_they_share' => ['nullable', 'string', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ]);

        $id = $this->service->submit($data);

        if ($id === null) {
            // Table missing / insert failed - never a 500; bounce back with a
            // friendly message and the user's input preserved.
            return redirect()
                ->route('federation.join')
                ->withInput()
                ->with('error', __('Sorry, we could not record your request just now. Please try again shortly.'));
        }

        return redirect()->route('federation.join.thanks');
    }

    /** Confirmation page. */
    public function thanks()
    {
        return view('ahg-federation::join.thanks');
    }
}
