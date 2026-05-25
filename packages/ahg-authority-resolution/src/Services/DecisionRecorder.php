<?php

/**
 * DecisionRecorder - Service for Heratio
 *
 * Single write-path for every archivist action that resolves a mention
 * (link, link_different, create_new, park, reject). The five Task 5
 * action handlers in AuthorityReviewController call into one of:
 *   - recordLink($mentionId, $candidateId)
 *   - recordLinkDifferent($mentionId, $authorityId, $entityType)
 *   - recordCreateNew($mentionId, $authorityId, $entityType)
 *   - recordPark($mentionId, $reason)
 *   - recordReject($mentionId)
 *
 * Each writes:
 *   1. one row to ahg_mention_decision (frozen evidence + candidates snapshot)
 *   2. updates ahg_mention.state ('linked' / 'parked' / 'rejected' /
 *      'new_record_created')
 *   3. if link / link_different / create_new: writes ahg_ner_entity.linked_actor_id
 *      back so the existing consumer contract keeps working
 *   4. if park: inserts ahg_mention_park
 *   5. fires DecisionProvenanceWriter to push the RDF-Star triples into
 *      Fuseki (Task 8). Failures are logged but do not block the UI
 *      decision (the audit row + DB state changes are durable; the writer
 *      can be re-run from the existing auth-res:write-provenance command).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DecisionRecorder
{
    public function __construct(
        private DecisionProvenanceWriter $provenanceWriter,
        private ?NerFeedbackService $nerFeedback = null,
    ) {}

    public function recordLink(int $mentionId, int $userId, int $candidateId): int
    {
        $candidate = DB::table('ahg_mention_candidate')
            ->where('id', $candidateId)
            ->where('mention_id', $mentionId)
            ->first();

        if (! $candidate) {
            throw new \RuntimeException("candidate {$candidateId} not found for mention {$mentionId}");
        }

        $decisionId = DB::transaction(function () use ($mentionId, $userId, $candidate) {
            $id = $this->insertDecision($mentionId, $userId, 'link', $candidate->id, $candidate->candidate_authority_id, $candidate->composite_score);
            $this->setMentionState($mentionId, 'linked');
            if ($candidate->candidate_authority_id !== null) {
                $this->backUpdateNerEntity($mentionId, (int) $candidate->candidate_authority_id);
            }

            return $id;
        });

        $this->writeProvenance($decisionId);

        return $decisionId;
    }

    public function recordLinkDifferent(int $mentionId, int $userId, int $authorityId): int
    {
        $decisionId = DB::transaction(function () use ($mentionId, $userId, $authorityId) {
            $id = $this->insertDecision($mentionId, $userId, 'link_different', null, $authorityId, null);
            $this->setMentionState($mentionId, 'linked');
            $this->backUpdateNerEntity($mentionId, $authorityId);

            return $id;
        });

        $this->writeProvenance($decisionId);

        return $decisionId;
    }

    /**
     * Record a 'create_new' decision. Task 6 widens the signature: when the
     * AuthorityCreator has actually inserted the new actor / term row, the
     * caller passes its id so we can (a) freeze it on the audit row and
     * (b) back-update ahg_ner_entity.linked_actor_id so the existing
     * downstream consumers keep working. Task 5 callers (no id) still work
     * - the audit row is written with NULL chosen_authority_id.
     */
    public function recordCreateNew(int $mentionId, int $userId, ?int $newAuthorityId = null): int
    {
        $decisionId = DB::transaction(function () use ($mentionId, $userId, $newAuthorityId) {
            $id = $this->insertDecision($mentionId, $userId, 'create_new', null, $newAuthorityId, null);
            $this->setMentionState($mentionId, 'new_record_created');
            if ($newAuthorityId !== null) {
                $this->backUpdateNerEntity($mentionId, $newAuthorityId);
            }

            return $id;
        });

        $this->writeProvenance($decisionId);

        return $decisionId;
    }

    public function recordPark(int $mentionId, int $userId, string $reason): int
    {
        $decisionId = DB::transaction(function () use ($mentionId, $userId, $reason) {
            $id = $this->insertDecision($mentionId, $userId, 'park', null, null, null);
            $this->setMentionState($mentionId, 'parked');

            DB::table('ahg_mention_park')
                ->updateOrInsert(
                    ['mention_id' => $mentionId],
                    [
                        'parked_by_user_id' => $userId,
                        'parked_at' => now(),
                        'reason' => $reason,
                        'new_candidate_available' => 0,
                    ]
                );

            return $id;
        });

        $this->writeProvenance($decisionId);

        return $decisionId;
    }

    /**
     * Reject a mention as a false positive.
     *
     * Task 9 widens the signature: an optional rejection reason is passed
     * through to NerFeedbackService::captureFromRejection() so the training
     * pipeline at /opt/ahg-ai sees the archivist's free-form note. The
     * reason is NOT persisted on ahg_mention_decision itself (that table is
     * an immutable audit row with a fixed shape); it lives on
     * ahg_ner_feedback.rejection_reason.
     *
     * Feedback capture is wrapped in try/catch - it MUST NOT break the
     * reject decision. The audit row + state flip are the durable spine.
     */
    public function recordReject(int $mentionId, int $userId, ?string $reason = null): int
    {
        $decisionId = DB::transaction(function () use ($mentionId, $userId) {
            $id = $this->insertDecision($mentionId, $userId, 'reject', null, null, null);
            $this->setMentionState($mentionId, 'rejected');

            return $id;
        });

        $this->writeProvenance($decisionId);

        if ($this->nerFeedback !== null) {
            try {
                $this->nerFeedback->captureFromRejection($decisionId, $reason);
            } catch (\Throwable $e) {
                Log::warning('DecisionRecorder: NER feedback capture threw', [
                    'decision_id' => $decisionId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $decisionId;
    }

    private function insertDecision(
        int $mentionId,
        int $userId,
        string $decisionType,
        ?int $chosenCandidateId,
        $chosenAuthorityId,
        $originalTopScore
    ): int {
        // Freeze the evidence + candidate list the archivist actually saw
        // at decision time. Pulled straight from ahg_mention_candidate.
        $candidates = DB::table('ahg_mention_candidate')
            ->where('mention_id', $mentionId)
            ->orderBy('rank_position')
            ->get();

        $candidateSnapshot = $candidates->map(function ($c) {
            return [
                'candidate_id' => (int) $c->id,
                'rank' => (int) $c->rank_position,
                'source' => $c->candidate_source,
                'authority_id' => $c->candidate_authority_id !== null ? (int) $c->candidate_authority_id : null,
                'fuseki_uri' => $c->candidate_fuseki_uri,
                'display_name' => $c->candidate_display_name,
                'name_similarity_score' => $c->name_similarity_score !== null ? (float) $c->name_similarity_score : null,
                'composite_score' => $c->composite_score !== null ? (float) $c->composite_score : null,
            ];
        })->all();

        $evidence = [];
        foreach ($candidates as $c) {
            $evidence[(int) $c->id] = [
                'signals' => $c->evidence_signals ? json_decode($c->evidence_signals, true) : null,
                'data' => $c->evidence_data ? json_decode($c->evidence_data, true) : null,
                'composite_score' => $c->composite_score !== null ? (float) $c->composite_score : null,
            ];
        }

        $topScore = $originalTopScore;
        if ($topScore === null && ! empty($candidates)) {
            $topScore = $candidates[0]->composite_score;
        }

        return (int) DB::table('ahg_mention_decision')->insertGetId([
            'mention_id' => $mentionId,
            'decision_type' => $decisionType,
            'chosen_candidate_id' => $chosenCandidateId,
            'chosen_authority_id' => $chosenAuthorityId !== null ? (int) $chosenAuthorityId : null,
            'original_system_top_score' => $topScore,
            'archivist_user_id' => $userId,
            'decided_at' => now(),
            'evidence_snapshot' => $evidence ? json_encode($evidence, JSON_UNESCAPED_UNICODE) : null,
            'candidates_visible_snapshot' => $candidateSnapshot ? json_encode($candidateSnapshot, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    private function setMentionState(int $mentionId, string $state): void
    {
        DB::table('ahg_mention')
            ->where('id', $mentionId)
            ->update(['state' => $state, 'updated_at' => now()]);
    }

    private function backUpdateNerEntity(int $mentionId, int $authorityId): void
    {
        $nerEntityId = DB::table('ahg_mention')->where('id', $mentionId)->value('ner_entity_id');
        if ($nerEntityId) {
            DB::table('ahg_ner_entity')
                ->where('id', $nerEntityId)
                ->update([
                    'linked_actor_id' => $authorityId,
                    'status' => 'linked',
                ]);
        }
    }

    /**
     * Best-effort: push RDF-Star to Fuseki. Failure is logged but does not
     * roll the UI decision back - the audit row and state flip are durable.
     */
    private function writeProvenance(int $decisionId): void
    {
        try {
            $result = $this->provenanceWriter->write($decisionId);
            if (! ($result['ok'] ?? false)) {
                Log::warning('DecisionRecorder: provenance write returned not-ok', [
                    'decision_id' => $decisionId,
                    'error' => $result['error'] ?? 'unknown',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('DecisionRecorder: provenance write threw', [
                'decision_id' => $decisionId,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
