<?php

/**
 * ResearchLeadAdminController - curation of the "Research Leads" feed (north-star
 * heratio#1210: generative scholarship - AI finds connections no human spotted).
 *
 * Admin-gated (auth + admin) curation over the new research_lead table. A
 * curator promotes the most compelling AI-found cross-collection connections
 * (already surfaced + persisted by the Discoveries feature) into pending leads,
 * then publishes or dismisses them. Only PUBLISHED leads reach the public feed.
 *
 *   GET  /admin/research-leads                list    - the curation worklist
 *   POST /admin/research-leads/generate       generate - promote top discoveries into pending leads
 *   POST /admin/research-leads/{id}/publish   publish  - publish a lead (show it publicly)
 *   POST /admin/research-leads/{id}/dismiss   dismiss  - dismiss a lead (hide it)
 *   POST /admin/research-leads/{id}/repend    repend   - return a lead to pending (undo)
 *
 * Generation is an EXPLICIT, manual action (a POST behind auth+admin) - never on
 * a page load. AI enrichment of the "why it matters" prompt is opt-in (the
 * "Enrich with AI" toggle) and routes through the AHG gateway via LlmService
 * inside the service - never a direct inference node. Every write goes ONLY to
 * research_lead via ResearchLeadService; the Discoveries source table is read
 * only. Every screen has an empty-state and never 500s.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

use AhgSemanticSearch\Services\ResearchLeadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResearchLeadAdminController extends Controller
{
    protected ResearchLeadService $service;

    public function __construct()
    {
        $this->service = new ResearchLeadService;
    }

    /**
     * The curation worklist: every lead, optionally filtered to one status, with
     * status filter chips. Never 500s - any failure renders the empty-state.
     */
    public function index(Request $request)
    {
        $statusFilter = trim((string) $request->query('status', ''));

        $leads = [];
        $statusCounts = [];
        $discoveriesAvailable = false;
        try {
            $leads = $this->service->adminList($statusFilter !== '' ? $statusFilter : null);
            $statusCounts = $this->service->statusCounts();
            $discoveriesAvailable = $this->service->discoveriesAvailable();
        } catch (\Throwable $e) {
            Log::info('[research-leads] admin index failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::research-leads.admin', [
            'leads' => $leads,
            'statusCounts' => $statusCounts,
            'statuses' => ResearchLeadService::STATUSES,
            'statusFilter' => $statusFilter,
            'total' => array_sum($statusCounts),
            'discoveriesAvailable' => $discoveriesAvailable,
            'disclaimer' => ResearchLeadService::DISCLAIMER,
        ]);
    }

    /**
     * Promote the top persisted discoveries into pending leads. Explicit POST
     * action only. Optionally enriches the "why it matters" prompt via the AHG
     * gateway when the "enrich" flag is set. Idempotent per record. Redirects back
     * to the worklist with a summary.
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:200',
            'enrich' => 'nullable|boolean',
        ]);

        $limit = (int) ($validated['limit'] ?? 25);
        $enrich = (bool) ($validated['enrich'] ?? false);

        if (! $this->service->discoveriesAvailable()) {
            return back()->with('error', __('No discoveries are available to promote yet. Run the discovery generator first, then come back to promote the strongest connections into leads.'));
        }

        try {
            $r = $this->service->generate($limit, $enrich, false, $this->userId($request));
        } catch (\Throwable $e) {
            Log::warning('[research-leads] generate failed: '.$e->getMessage());

            return back()->with('error', __('Generation could not be completed. Please try again.'));
        }

        $msg = __(':promoted new lead(s) promoted, :refreshed refreshed.', [
            'promoted' => (int) ($r['promoted'] ?? 0),
            'refreshed' => (int) ($r['refreshed'] ?? 0),
        ]);
        if ($enrich) {
            $msg .= ' '.($r['ai_reached']
                ? __('AI enriched :n prompt(s) via the AHG gateway.', ['n' => (int) ($r['enriched'] ?? 0)])
                : __('The AI service was unreachable, so leads kept their factual prompts.'));
        }

        return back()->with('success', $msg);
    }

    /**
     * Publish a lead - it becomes visible on the public feed.
     */
    public function publish(Request $request, $id)
    {
        $lead = $this->service->find((int) $id);
        if ($lead === null) {
            abort(404);
        }

        $ok = $this->service->publish((int) $id, $this->userId($request));

        return back()->with($ok ? 'success' : 'error', $ok
            ? __('Lead published. It is now visible on the public Research Leads feed.')
            : __('The lead could not be published.'));
    }

    /**
     * Dismiss a lead - kept for the record but never shown publicly.
     */
    public function dismiss(Request $request, $id)
    {
        $lead = $this->service->find((int) $id);
        if ($lead === null) {
            abort(404);
        }

        $ok = $this->service->dismiss((int) $id, $this->userId($request));

        return back()->with($ok ? 'success' : 'error', $ok
            ? __('Lead dismissed. It will not appear on the public feed.')
            : __('The lead could not be dismissed.'));
    }

    /**
     * Return a lead to pending (undo a publish/dismiss).
     */
    public function repend(Request $request, $id)
    {
        $lead = $this->service->find((int) $id);
        if ($lead === null) {
            abort(404);
        }

        $ok = $this->service->repend((int) $id, $this->userId($request));

        return back()->with($ok ? 'success' : 'error', $ok
            ? __('Lead returned to pending review.')
            : __('The lead could not be updated.'));
    }

    /**
     * Current user id, when authenticated.
     */
    protected function userId(Request $request): ?int
    {
        try {
            $user = $request->user();

            return $user ? (int) $user->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
