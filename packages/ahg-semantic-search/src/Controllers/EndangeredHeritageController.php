<?php

/**
 * EndangeredHeritageController - endangered-heritage register + capture-priority
 * worklist (north-star heratio#1205: the race against loss).
 *
 * Admin-gated (auth + admin) workflow over the new endangered_heritage_item
 * table, plus a PUBLIC read-only "at risk" register:
 *
 *   ADMIN (auth + admin):
 *     GET  /endangered/priority           worklist - capture-priority list (urgency-ordered)
 *     GET  /endangered/flag               flagForm - flag-a-record form (prefilled from ?item=)
 *     POST /endangered/flag                flag     - record / update an at-risk flag
 *     POST /endangered/{id}/capture-status capture  - advance the capture status
 *
 *   PUBLIC (web):
 *     GET  /at-risk                        register - the public "at risk" register (published items only)
 *
 * Every write goes ONLY to the new table via EndangeredHeritageService. The flag
 * form can be prefilled from a record (?item=<information_object_id>) by reading
 * the catalogue title read-only, so a curator flagging a record they are viewing
 * starts with its context filled in. No existing table is written or ALTERed.
 *
 * Factual, non-alarmist framing: a flag is a prioritisation judgement and the
 * reason for it, surfaced so the most-vulnerable heritage is captured first.
 * Forms have full validation; every screen has an empty-state and never 500s.
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

use AhgSemanticSearch\Services\EndangeredHeritageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EndangeredHeritageController extends Controller
{
    protected EndangeredHeritageService $service;

    public function __construct()
    {
        $this->service = new EndangeredHeritageService;
    }

    /**
     * Admin capture-priority worklist: every flag still awaiting capture, ordered
     * most-urgent first, with status / urgency filter chips. Never 500s: a missing
     * table or any failure renders the empty-state.
     */
    public function worklist(Request $request)
    {
        $statusFilter = trim((string) $request->query('status', ''));

        $flags = [];
        $statusCounts = [];
        $urgencyCounts = [];
        try {
            if ($statusFilter !== '') {
                // Filtered view: list of that status, still priority-ordered.
                $flags = $this->service->list($statusFilter, 0);
                usort($flags, fn ($a, $b) => (int) $b['priority_score'] <=> (int) $a['priority_score']);
            } else {
                $flags = $this->service->priorityList(0);
            }
            $statusCounts = $this->service->statusCounts();
            $urgencyCounts = $this->service->urgencyCounts();
        } catch (\Throwable $e) {
            Log::info('[endangered] worklist failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::endangered.worklist', [
            'flags' => $flags,
            'statusCounts' => $statusCounts,
            'urgencyCounts' => $urgencyCounts,
            'statuses' => EndangeredHeritageService::CAPTURE_STATUSES,
            'urgencies' => EndangeredHeritageService::URGENCIES,
            'risks' => EndangeredHeritageService::RISK_CATEGORIES,
            'statusFilter' => $statusFilter,
            'disclaimer' => EndangeredHeritageService::DISCLAIMER,
            'total' => array_sum($statusCounts),
        ]);
    }

    /**
     * Flag-a-record form. When ?item=<information_object_id> is supplied, the form
     * is prefilled with the record's catalogue title (read-only) and any existing
     * flag for that item, so re-flagging amends rather than duplicates.
     */
    public function flagForm(Request $request)
    {
        $itemRef = (int) $request->query('item', 0);

        $prefill = [
            'item_ref' => $itemRef > 0 ? $itemRef : '',
            'risk_category' => 'other',
            'urgency' => 'medium',
            'capture_status' => 'flagged',
            'reason' => '',
        ];

        $existing = null;
        $itemTitle = null;
        if ($itemRef > 0) {
            $existing = $this->service->findByItem($itemRef);
            if ($existing !== null) {
                $prefill['risk_category'] = $existing['risk_category'];
                $prefill['urgency'] = $existing['urgency'];
                $prefill['capture_status'] = $existing['capture_status'];
                $prefill['reason'] = $existing['reason'] ?? '';
                $itemTitle = $existing['item_title'];
            } else {
                $itemTitle = $this->catalogueTitle($itemRef);
            }
        }

        return view('ahg-semantic-search::endangered.flag', [
            'prefill' => $prefill,
            'existing' => $existing,
            'itemTitle' => $itemTitle,
            'risks' => EndangeredHeritageService::RISK_CATEGORIES,
            'urgencies' => EndangeredHeritageService::URGENCIES,
            'statuses' => EndangeredHeritageService::CAPTURE_STATUSES,
            'disclaimer' => EndangeredHeritageService::DISCLAIMER,
        ]);
    }

    /**
     * Persist (or update) an at-risk flag. Full validation; redirects back to the
     * worklist on success.
     */
    public function flag(Request $request)
    {
        $validated = $request->validate([
            'item_ref' => 'required|integer|min:1',
            'risk_category' => 'required|string|max:64',
            'urgency' => 'required|string|max:32',
            'capture_status' => 'required|string|max:32',
            'reason' => 'nullable|string|max:20000',
        ]);

        // Defensive normalisation (the service re-normalises regardless).
        $validated['risk_category'] = $this->service->normaliseRisk($validated['risk_category']);
        $validated['urgency'] = $this->service->normaliseUrgency($validated['urgency']);
        $validated['capture_status'] = $this->service->normaliseCaptureStatus($validated['capture_status']);

        $id = $this->service->flag($validated, $this->userId($request));

        if ($id === null) {
            return back()
                ->withInput()
                ->with('error', __('The item could not be flagged. Please check the details and try again.'));
        }

        return redirect()
            ->route('endangered.priority')
            ->with('success', __('Item flagged for priority capture.'));
    }

    /**
     * Advance just the capture status of a flag (quick action from the worklist).
     */
    public function captureStatus(Request $request, $id)
    {
        $flag = $this->service->find((int) $id);
        if ($flag === null) {
            abort(404);
        }

        $validated = $request->validate([
            'capture_status' => 'required|string|max:32',
        ]);

        $ok = $this->service->updateCaptureStatus((int) $id, $validated['capture_status']);

        return back()->with($ok ? 'success' : 'error', $ok
            ? __('Capture status updated.')
            : __('The status could not be updated.'));
    }

    /**
     * Public "at risk" register: PUBLISHED at-risk items only, most-urgent first,
     * optionally narrowed to one risk category (?risk=). Frames why heritage is
     * endangered and the race to capture it. Never 500s: any failure renders the
     * grounded empty-state.
     */
    public function register(Request $request)
    {
        $riskFilter = trim((string) $request->query('risk', ''));

        $entries = [];
        $riskCounts = [];
        try {
            $entries = $this->service->publicRegister($riskFilter !== '' ? $riskFilter : null, 0);
            // Counts are computed across the whole published register (unfiltered)
            // so the chips always show the full breakdown.
            $riskCounts = $this->service->publicRiskCounts($this->service->publicRegister(null, 0));
        } catch (\Throwable $e) {
            Log::info('[endangered] public register failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::endangered.register', [
            'entries' => $entries,
            'riskCounts' => $riskCounts,
            'risks' => EndangeredHeritageService::RISK_CATEGORIES,
            'urgencies' => EndangeredHeritageService::URGENCIES,
            'riskFilter' => $riskFilter,
            'total' => array_sum($riskCounts),
            'shownCount' => count($entries),
            'disclaimer' => EndangeredHeritageService::DISCLAIMER,
        ]);
    }

    /**
     * The PUBLIC, cross-institution "at risk" board (north-star heratio#1205
     * federation slice). Merges THIS instance's published register with a LIVE
     * fetch of every active federation peer's /api/v1/endangered, ranked into one
     * leaderboard with source-institution badges. Additive: the single-instance
     * /at-risk register is unchanged. Fail-soft - federation absent / a peer down
     * degrades to local-only with warnings, never a 500.
     */
    public function globalRegister(Request $request)
    {
        $filters = [
            'risk' => trim((string) $request->query('risk', '')),
            'urgency' => trim((string) $request->query('urgency', '')),
            'status' => trim((string) $request->query('status', '')),
        ];

        $result = [
            'items' => [],
            'peers_queried' => 0,
            'peers' => [],
            'warnings' => [],
            'local_count' => 0,
            'total_count' => 0,
        ];

        try {
            $federated = new \AhgSemanticSearch\Services\FederatedEndangeredService($this->service);
            $result = $federated->globalRegister($filters);
        } catch (\Throwable $e) {
            // Belt-and-braces: the service is already fail-soft, but never let the
            // board 500 even if instantiation itself fails on a slim install.
            Log::info('[endangered] global register failed: '.$e->getMessage());
            $result['warnings'][] = __('The federated register is temporarily unavailable; showing local items only.');
        }

        // Risk-category chips over the merged board, so the breakdown spans peers.
        $riskCounts = [];
        foreach ($result['items'] as $row) {
            $key = (string) ($row['risk_category'] ?? 'other');
            $riskCounts[$key] = ($riskCounts[$key] ?? 0) + 1;
        }

        return view('ahg-semantic-search::endangered.global', [
            'items' => $result['items'],
            'peers' => $result['peers'],
            'peersQueried' => (int) $result['peers_queried'],
            'warnings' => $result['warnings'],
            'localCount' => (int) $result['local_count'],
            'totalCount' => (int) $result['total_count'],
            'riskCounts' => $riskCounts,
            'risks' => EndangeredHeritageService::RISK_CATEGORIES,
            'urgencies' => EndangeredHeritageService::URGENCIES,
            'statuses' => EndangeredHeritageService::CAPTURE_STATUSES,
            'riskFilter' => $filters['risk'],
            'urgencyFilter' => $filters['urgency'],
            'statusFilter' => $filters['status'],
            'disclaimer' => EndangeredHeritageService::DISCLAIMER,
        ]);
    }

    /**
     * Read-only catalogue title for one information object, used only to give the
     * flag form a friendly label. Existence-guarded; null on any uncertainty.
     */
    protected function catalogueTitle(int $itemRef): ?string
    {
        if ($itemRef <= 0) {
            return null;
        }

        try {
            if (! Schema::hasTable('information_object_i18n')) {
                return null;
            }
            $title = DB::table('information_object_i18n')->where('id', $itemRef)->value('title');

            return $title !== null ? (string) $title : null;
        } catch (\Throwable $e) {
            Log::info('[endangered] catalogue title lookup failed for '.$itemRef.': '.$e->getMessage());

            return null;
        }
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
