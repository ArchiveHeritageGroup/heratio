<?php

/**
 * ResearchLeadsController - the PUBLIC "Research Leads" feed (north-star
 * heratio#1210: generative scholarship - AI finds connections no human spotted).
 *
 * The public, read-only face of the generative-scholarship work: the most
 * compelling AI-found cross-collection connections, promoted by curators into
 * browsable scholarly leads. Each lead shows the connection, the verified
 * records it links, a plain-language "why this might matter" prompt, and links
 * straight to the records.
 *
 *   GET /research-leads        index - the published leads feed (published records only)
 *   GET /research-leads/{id}   show  - one published lead in full
 *
 * READ-ONLY and published-only: it renders ONLY leads a curator has set to
 * "published" whose underlying record is itself published. It NEVER writes any
 * table and NEVER calls the AI (generation/enrichment is an explicit admin/CLI
 * action). Every path degrades to an empty-state rather than a 500.
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
use Illuminate\Support\Facades\Log;

class ResearchLeadsController extends Controller
{
    protected ResearchLeadService $service;

    public function __construct()
    {
        $this->service = new ResearchLeadService;
    }

    /**
     * Public feed: the published research leads, newest-published first. Never
     * 500s - any failure renders the grounded empty-state.
     */
    public function index()
    {
        $leads = [];
        try {
            $leads = $this->service->publicFeed();
        } catch (\Throwable $e) {
            Log::info('[research-leads] index failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::research-leads.index', [
            'leads' => $leads,
            'count' => count($leads),
            'disclaimer' => ResearchLeadService::DISCLAIMER,
        ]);
    }

    /**
     * Public detail for one PUBLISHED lead (published record only). Falls back to
     * the feed when the lead is absent, unpublished, or its record is not
     * published - never 500s.
     *
     * @param  int|string  $id
     */
    public function show($id)
    {
        $lead = null;
        try {
            $lead = $this->service->publicLead((int) $id);
        } catch (\Throwable $e) {
            Log::info('[research-leads] show('.$id.') failed: '.$e->getMessage());
        }

        if ($lead === null) {
            return redirect()->route('research-leads.index');
        }

        return view('ahg-semantic-search::research-leads.show', [
            'lead' => $lead,
            'disclaimer' => ResearchLeadService::DISCLAIMER,
        ]);
    }
}
