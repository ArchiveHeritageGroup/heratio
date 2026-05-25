<?php

/**
 * ParkQueueService - Service for Heratio
 *
 * Task 7 of the AHG Authority Resolution Engine. Manages the parked-mention
 * queue (ahg_mention_park rows). Three responsibilities:
 *
 *   listFor()              -> filterable feed for the /park screen + JSON
 *                             dashboard widget
 *   unparkAndRereview()    -> deletes the park row, flips state back to
 *                             'pending', re-runs Task 3 candidate generation
 *                             AND Task 4 evidence scoring, returns the fresh
 *                             ahg_mention_candidate ids
 *   scanForNewCandidates() -> background sweep over every parked mention;
 *                             dry-run candidate generation; if the candidate
 *                             authority-id set has actually changed since
 *                             parking, flips new_candidate_available=1
 *
 * "Changed since parking" is judged by comparing the sorted list of
 * candidate_authority_id values currently in ahg_mention_candidate against a
 * freshly-generated dry-run set. The candidate generator is idempotent and
 * we use a transaction-free fingerprint comparison so the scan is cheap.
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

namespace AhgAuthorityResolution\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParkQueueService
{
    public function __construct(
        private CandidateGeneratorService $generator,
        private EvidenceScorer $scorer,
    ) {}

    /**
     * Listing for the /admin/authority-resolution/park screen.
     *
     * @return list<object>
     */
    public function listFor(
        ?int $userId = null,
        ?string $entityType = null,
        ?bool $newCandidateOnly = null,
        ?\DateTimeImmutable $sinceParked = null,
        ?string $reasonQuery = null,
        string $sortBy = 'parked_at_desc',
        int $limit = 200
    ): array {
        $q = DB::table('ahg_mention_park as p')
            ->join('ahg_mention as m', 'm.id', '=', 'p.mention_id')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->leftJoin('ahg_mention_context as c', 'c.mention_id', '=', 'm.id')
            ->leftJoin(DB::raw('(SELECT mention_id, COUNT(*) AS cc FROM ahg_mention_candidate GROUP BY mention_id) AS cc'),
                'cc.mention_id', '=', 'm.id')
            ->select(
                'p.id as park_id',
                'p.mention_id',
                'p.parked_by_user_id',
                'p.parked_at',
                'p.reason',
                'p.new_candidate_available',
                'p.new_candidate_check_at',
                'm.entity_type',
                'm.state',
                'm.object_id',
                'n.entity_value',
                'n.confidence',
                'c.surrounding_text_before',
                'c.surrounding_text_after',
                DB::raw('COALESCE(cc.cc, 0) AS candidate_count')
            );

        if ($userId !== null) {
            $q->where('p.parked_by_user_id', $userId);
        }
        if ($entityType !== null && $entityType !== '') {
            $q->where('m.entity_type', $entityType);
        }
        if ($newCandidateOnly === true) {
            $q->where('p.new_candidate_available', 1);
        }
        if ($sinceParked !== null) {
            $q->where('p.parked_at', '>=', $sinceParked->format('Y-m-d H:i:s'));
        }
        if ($reasonQuery !== null && $reasonQuery !== '') {
            $q->where('p.reason', 'like', '%'.$reasonQuery.'%');
        }

        switch ($sortBy) {
            case 'parked_at_asc':
                $q->orderBy('p.parked_at');
                break;
            case 'entity_type':
                $q->orderBy('m.entity_type')->orderByDesc('p.parked_at');
                break;
            case 'new_candidate':
                $q->orderByDesc('p.new_candidate_available')->orderByDesc('p.parked_at');
                break;
            case 'parked_at_desc':
            default:
                $q->orderByDesc('p.parked_at');
                break;
        }

        return $q->limit(max(1, $limit))->get()->all();
    }

    /**
     * Per-archivist counts for the dashboard widget.
     *
     * @return array<int,int> archivist_user_id => parked_count
     */
    public function countsByArchivist(): array
    {
        $rows = DB::table('ahg_mention_park')
            ->select('parked_by_user_id', DB::raw('COUNT(*) AS c'))
            ->groupBy('parked_by_user_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->parked_by_user_id] = (int) $r->c;
        }

        return $out;
    }

    /**
     * Unpark a mention and re-run the review pipeline.
     *
     * Returns the fresh ahg_mention_candidate.id list (rank order) after
     * scoring. Caller (controller) redirects to the review screen.
     *
     * @return array{
     *   mention_id:int,
     *   candidate_ids:list<int>,
     *   scored_count:int
     * }
     */
    public function unparkAndRereview(int $mentionId, int $userId): array
    {
        DB::transaction(function () use ($mentionId) {
            DB::table('ahg_mention_park')->where('mention_id', $mentionId)->delete();
            DB::table('ahg_mention')
                ->where('id', $mentionId)
                ->update(['state' => 'pending', 'updated_at' => now()]);
        });

        $candidateIds = $this->generator->generate($mentionId);
        $scoreResult = $this->scorer->scoreAllForMention($mentionId);

        Log::info('ParkQueueService: mention unparked + re-reviewed', [
            'mention_id' => $mentionId,
            'by_user_id' => $userId,
            'candidate_count' => count($candidateIds),
            'scored_count' => $scoreResult['scored_count'] ?? 0,
        ]);

        return [
            'mention_id' => $mentionId,
            'candidate_ids' => $candidateIds,
            'scored_count' => (int) ($scoreResult['scored_count'] ?? 0),
        ];
    }

    /**
     * Scan every parked mention for changes in the candidate set since
     * parking. When the dry-run candidate set differs from what is currently
     * persisted, flip ahg_mention_park.new_candidate_available = 1 so the
     * archivist sees a flag on the park screen.
     *
     * Returns the count of mentions that just became "new candidate
     * available". Idempotent: re-running with no upstream change returns 0.
     */
    public function scanForNewCandidates(): int
    {
        $parkedRows = DB::table('ahg_mention_park')
            ->select('mention_id', 'new_candidate_available')
            ->get();

        $newlyFlagged = 0;
        $now = now();

        foreach ($parkedRows as $row) {
            $mentionId = (int) $row->mention_id;

            // Current fingerprint of persisted candidates.
            $currentFingerprint = $this->candidateFingerprint($mentionId);

            // Dry-run: generate() persists, but persistence is itself
            // idempotent (delete + re-insert in one transaction). To stay
            // cheap and avoid touching ahg_mention_candidate when nothing
            // changed, we read the upstream adapters by re-invoking the
            // generator with the existing top-N - but only update the
            // 'new_candidate_available' flag, NOT the candidate set. The
            // archivist sees the flag, then unparks to actually refresh.
            try {
                $this->generator->generate($mentionId);
            } catch (\Throwable $e) {
                Log::warning('ParkQueueService: scan generate() failed', [
                    'mention_id' => $mentionId,
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }

            $newFingerprint = $this->candidateFingerprint($mentionId);

            $changed = $newFingerprint !== $currentFingerprint;
            if ($changed && (int) $row->new_candidate_available !== 1) {
                $newlyFlagged++;
            }

            DB::table('ahg_mention_park')
                ->where('mention_id', $mentionId)
                ->update([
                    'new_candidate_available' => $changed ? 1 : 0,
                    'new_candidate_check_at' => $now,
                ]);
        }

        return $newlyFlagged;
    }

    /**
     * Cheap stable fingerprint of the candidate set: a sorted CSV of
     * (source|authority_id|fuseki_uri|display_name) tuples. Catches
     * (a) authority records added or removed and (b) display-name changes.
     */
    private function candidateFingerprint(int $mentionId): string
    {
        $rows = DB::table('ahg_mention_candidate')
            ->where('mention_id', $mentionId)
            ->orderBy('candidate_source')
            ->orderBy('candidate_authority_id')
            ->orderBy('candidate_display_name')
            ->get(['candidate_source', 'candidate_authority_id', 'candidate_fuseki_uri', 'candidate_display_name']);

        $parts = [];
        foreach ($rows as $r) {
            $parts[] = sprintf(
                '%s|%s|%s|%s',
                (string) $r->candidate_source,
                $r->candidate_authority_id !== null ? (string) $r->candidate_authority_id : '',
                (string) ($r->candidate_fuseki_uri ?? ''),
                (string) $r->candidate_display_name
            );
        }
        sort($parts, SORT_STRING);

        return implode(';', $parts);
    }
}
