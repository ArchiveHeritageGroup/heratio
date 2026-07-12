<?php

/**
 * ParkQueueController - Heratio
 *
 * Task 7 of the AHG Authority Resolution Engine. Dedicated screen for the
 * parked-mention queue plus the un-park action plus a JSON endpoint for a
 * future admin dashboard widget. Shares the auth-res route prefix and admin
 * middleware with AuthorityReviewController (Task 5).
 *
 *   GET  /admin/authority-resolution/park
 *   POST /admin/authority-resolution/park/{mention}/unpark
 *   GET  /admin/authority-resolution/park/dashboard.json
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Http\Controllers;

use AhgAuthorityResolution\Services\ParkQueueService;
use AhgAuthorityResolution\Support\MentionVocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ParkQueueController extends Controller
{
    public function __construct(
        private ParkQueueService $parkQueue,
    ) {}

    /**
     * GET /admin/authority-resolution/park
     */
    public function index(Request $request)
    {
        $parkedBy = (int) $request->query('parked_by', 0);
        $entityType = trim((string) $request->query('entity_type', ''));
        $reasonQ = trim((string) $request->query('reason_q', ''));
        $newCandidateOnly = (int) $request->query('new_candidate_only', 0) === 1;
        $sortBy = trim((string) $request->query('sort_by', 'parked_at_desc'));

        $rows = $this->parkQueue->listFor(
            userId: $parkedBy > 0 ? $parkedBy : null,
            entityType: $entityType !== '' ? $entityType : null,
            newCandidateOnly: $newCandidateOnly ? true : null,
            sinceParked: null,
            reasonQuery: $reasonQ !== '' ? $reasonQ : null,
            sortBy: $sortBy,
            limit: 200
        );

        // Map archivist ids to display names (best-effort; falls back to id).
        $archivistIds = array_unique(array_map(fn ($r) => (int) $r->parked_by_user_id, $rows));
        $archivistNames = [];
        if (! empty($archivistIds)) {
            $names = DB::table('users')
                ->whereIn('id', $archivistIds)
                ->pluck('name', 'id')
                ->all();
            foreach ($names as $id => $name) {
                $archivistNames[(int) $id] = (string) $name;
            }
        }

        $totalParked = DB::table('ahg_mention_park')->count();
        $totalNewCandidate = DB::table('ahg_mention_park')->where('new_candidate_available', 1)->count();
        $countsByArchivist = $this->parkQueue->countsByArchivist();

        // For the parked_by filter dropdown.
        $allParkedBy = DB::table('ahg_mention_park as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.parked_by_user_id')
            ->select('p.parked_by_user_id as id', 'u.name as name', DB::raw('COUNT(*) as c'))
            ->groupBy('p.parked_by_user_id', 'u.name')
            ->get()
            ->all();

        return view('auth-res::park', [
            'rows' => $rows,
            'entityTypes' => MentionVocabulary::ENTITY_TYPES,
            'archivistNames' => $archivistNames,
            'totalParked' => $totalParked,
            'totalNewCandidate' => $totalNewCandidate,
            'countsByArchivist' => $countsByArchivist,
            'allParkedBy' => $allParkedBy,
            'filterParkedBy' => $parkedBy,
            'filterEntityType' => $entityType,
            'filterReasonQ' => $reasonQ,
            'filterNewCandidateOnly' => $newCandidateOnly,
            'filterSortBy' => $sortBy,
        ]);
    }

    /**
     * POST /admin/authority-resolution/park/{mention}/unpark
     */
    public function unpark(Request $request, int $mention)
    {
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        $exists = DB::table('ahg_mention_park')->where('mention_id', $mention)->exists();
        if (! $exists) {
            return redirect()->route('auth-res.park.index')
                ->withErrors(['unpark' => "Mention #{$mention} is not parked."]);
        }

        try {
            $result = $this->parkQueue->unparkAndRereview($mention, $userId);
        } catch (\Throwable $e) {
            return redirect()->route('auth-res.park.index')
                ->withErrors(['unpark' => 'Unpark failed: '.$e->getMessage()]);
        }

        $count = count($result['candidate_ids']);

        return redirect()->route('auth-res.review.show', ['mention' => $mention])
            ->with('notice', sprintf(
                'Mention #%d unparked. %d candidate(s) regenerated, %d scored.',
                $mention,
                $count,
                $result['scored_count']
            ));
    }

    /**
     * GET /admin/authority-resolution/park/dashboard.json
     *
     * Tiny JSON probe used by an admin-dashboard widget. Shape:
     *   {
     *     "total_parked": int,
     *     "total_new_candidate": int,
     *     "by_archivist": [ {"user_id": int, "name": string|null, "count": int}, ... ]
     *   }
     */
    public function dashboard(): JsonResponse
    {
        $counts = $this->parkQueue->countsByArchivist();
        $ids = array_keys($counts);
        $names = [];
        if (! empty($ids)) {
            $names = DB::table('users')
                ->whereIn('id', $ids)
                ->pluck('name', 'id')
                ->all();
        }

        $byArchivist = [];
        foreach ($counts as $userId => $count) {
            $byArchivist[] = [
                'user_id' => (int) $userId,
                'name' => $names[$userId] ?? null,
                'count' => (int) $count,
            ];
        }
        usort($byArchivist, fn ($a, $b) => $b['count'] <=> $a['count']);

        $totalParked = DB::table('ahg_mention_park')->count();
        $totalNewCandidate = DB::table('ahg_mention_park')->where('new_candidate_available', 1)->count();

        return response()->json([
            'total_parked' => (int) $totalParked,
            'total_new_candidate' => (int) $totalNewCandidate,
            'by_archivist' => $byArchivist,
        ]);
    }
}
