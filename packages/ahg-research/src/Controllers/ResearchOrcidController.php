<?php

/**
 * ResearchOrcidController - Controller for Heratio
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
use AhgResearch\Services\ResearchService;
use AhgResearch\Services\OrcidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchOrcidController - Researcher ORCID integration.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Covers per-researcher ORCID client credentials, the OAuth
 * authorize/callback handshake, works sync, profile pull, unlink, and the
 * public (no-auth) ORCID record lookup used by the registration forms.
 *
 * All work is delegated to AhgResearch\Services\OrcidService (resolved via the
 * container). The methods used only the shared trait helpers
 * (getResearcherOrRedirect + getSidebarData) and the OrcidService - there were
 * no cross-calls to other ResearchController methods and no exclusive private
 * helpers, so the move is a verbatim lift.
 *
 * NOTE on auth: orcidFetchPublic is registered in the PUBLIC research route
 * group (no auth middleware) - it is the register-form prefill endpoint and is
 * rate-limited per IP in the method body. Every other method here is in the
 * auth-gated group.
 */
class ResearchOrcidController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function orcidLink(Request $request)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        $svc = app(\AhgResearch\Services\OrcidService::class);
        $link = $svc->getLink((int) $researcher->id);
        // Per-researcher self-service: each researcher supplies their own ORCID
        // client. isConfigured reflects THIS researcher's creds (or the global
        // env fallback). Tokenless Fetch/Pull-profile work regardless.
        $isConfigured = $svc->isConfiguredFor((int) $researcher->id);
        $orcidCreds = $svc->getCredentials((int) $researcher->id);
        $orcidRedirectUri = url('/research/orcid/callback');

        return view('research::research.orcid-link', array_merge(
            $this->getSidebarData('orcid'),
            compact('researcher', 'link', 'isConfigured', 'orcidCreds', 'orcidRedirectUri')
        ));
    }

    public function orcidSaveCredentials(Request $request)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        $data = $request->validate([
            'client_id'     => 'required|string|max:100',
            'client_secret' => 'required|string|max:255',
            'api_base'      => 'nullable|in:https://pub.orcid.org,https://api.orcid.org',
        ]);

        app(\AhgResearch\Services\OrcidService::class)->saveCredentials(
            (int) $researcher->id,
            $data['client_id'],
            $data['client_secret'],
            url('/research/orcid/callback'),
            $data['api_base'] ?? null
        );

        return redirect()->route('research.orcid')->with('success', 'ORCID client credentials saved. You can now Connect & Sync.');
    }

    public function orcidClearCredentials(Request $request)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        app(\AhgResearch\Services\OrcidService::class)->clearCredentials((int) $researcher->id);
        return redirect()->route('research.orcid')->with('success', 'ORCID client credentials removed.');
    }

    public function orcidAuthorize(Request $request)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        $svc = app(\AhgResearch\Services\OrcidService::class);
        if (!$svc->isConfiguredFor((int) $researcher->id)) {
            return redirect()->route('research.orcid')->with('error', 'Enter your ORCID Client ID and Secret first, then Connect & Sync.');
        }

        session(['orcid_researcher_id' => $researcher->id]);
        return redirect()->away($svc->authorizeUrl((int) $researcher->id));
    }

    public function orcidCallback(Request $request)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        $code = $request->query('code');
        $state = $request->query('state');
        if (!$code) {
            return redirect()->route('research.orcid')->with('error', 'ORCID returned no authorisation code.');
        }
        if (session('orcid_oauth_state') && $state !== session('orcid_oauth_state')) {
            return redirect()->route('research.orcid')->with('error', 'ORCID state mismatch.');
        }

        $svc = app(\AhgResearch\Services\OrcidService::class);
        try {
            $token = $svc->exchangeCode($code, (int) $researcher->id);
            $svc->linkResearcher((int) $researcher->id, $token);
        } catch (\Throwable $e) {
            return redirect()->route('research.orcid')->with('error', 'ORCID link failed: ' . $e->getMessage());
        }

        return redirect()->route('research.orcid')->with('success', 'ORCID linked successfully.');
    }

    public function orcidSync(Request $request)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        $svc = app(\AhgResearch\Services\OrcidService::class);
        try {
            $svc->pullWorks((int) $researcher->id);
        } catch (\Throwable $e) {
            return redirect()->route('research.orcid')->with('error', 'Sync failed: ' . $e->getMessage());
        }

        return redirect()->route('research.orcid')->with('success', 'Works pulled from ORCID.');
    }

    public function orcidUnlink(Request $request)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        app(\AhgResearch\Services\OrcidService::class)->unlink((int) $researcher->id);
        return redirect()->route('research.orcid')->with('success', 'ORCID unlinked.');
    }

    /**
     * AJAX: fetch a public ORCID record by iD and return prefill fields for the
     * registration form. Public endpoint (no researcher account yet) - rate
     * limited per IP. Returns {ok, fields} or {ok:false, error}.
     */
    public function orcidFetchPublic(Request $request)
    {
        $request->validate(['orcid_id' => 'required|string|max:64']);

        $ip = (string) $request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts('orcid_fetch:' . $ip, 20)) {
            return response()->json(['ok' => false, 'error' => 'Too many lookups, try again in a minute.'], 429);
        }
        \Illuminate\Support\Facades\RateLimiter::hit('orcid_fetch:' . $ip, 60);

        // No isConfigured() gate: the public-record read works tokenless against
        // pub.orcid.org, so Fetch-from-ORCID is available even with no client
        // credentials. (Connect & Sync / Works push still require a client.)
        $svc = app(\AhgResearch\Services\OrcidService::class);

        $normalised = $svc->normaliseOrcidId($request->input('orcid_id'));
        if (!$normalised) {
            return response()->json(['ok' => false, 'error' => 'That does not look like a valid ORCID iD (expected 0000-0000-0000-0000).'], 422);
        }

        $record = $svc->fetchPublicRecord($normalised);
        if (!$record) {
            return response()->json(['ok' => false, 'error' => 'No public ORCID record found for ' . $normalised . ', or ORCID is unreachable.'], 404);
        }

        return response()->json([
            'ok' => true,
            'fields' => [
                'orcid_id'           => $record['orcid_id'],
                'first_name'         => $record['first_name'],
                'last_name'          => $record['last_name'],
                'institution'        => $record['institution'],
                'department'         => $record['department'],
                'position'           => $record['position'],
                'research_interests' => $record['research_interests'],
                'email'              => $record['emails'][0] ?? null,
            ],
        ]);
    }

    /**
     * Pull the logged-in researcher's ORCID profile and apply it to their
     * record. Used by the "Pull from ORCID" button on the profile/orcid page.
     */
    public function orcidPullProfile(Request $request)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        // No isConfigured() gate: pullProfile reads the public record tokenless.
        $svc = app(\AhgResearch\Services\OrcidService::class);

        try {
            $record = $svc->pullProfile((int) $researcher->id);
        } catch (\Throwable $e) {
            return redirect()->route('research.orcid')->with('error', 'Profile pull failed: ' . $e->getMessage());
        }

        if (!$record) {
            return redirect()->route('research.orcid')->with('error', 'No public ORCID record found to pull.');
        }

        return redirect()->route('research.orcid')->with('success', 'Profile details pulled from ORCID and applied to your researcher record.');
    }
}
