<?php

/**
 * ContradictionEngineService - Service for Heratio
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

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ContradictionEngineService - Research OS moonshot 17 (heratio#1236).
 *
 * The Contradiction Engine scans a project's CLAIM LEDGER for contradictions no
 * human holds in working memory: two claims that disagree, a single source that
 * both supports and opposes the project's own claims, a claim that has weakened,
 * or two claims that use the same key term to mean different things.
 *
 * It reads ONLY:
 *   - research_assertion           (the claim: text, status, confidence, version)
 *   - research_assertion_evidence  (evidence links: source_type, source_id, relationship)
 *   - research_claim_meta          (sidecar: confidence_level, opposing/supporting sources)
 *
 * It NEVER alters those tables. Findings are written to the slice's own
 * research_contradiction table, keyed by a stable signature so re-running the
 * scan upserts rather than duplicates. Every read is Schema::hasTable-guarded and
 * wrapped in try/catch so the engine degrades to an empty result rather than a 500.
 *
 * Optional AI deepening routes through the AHG gateway via
 * AhgAiServices\Services\LlmService only, is always user-triggered (never
 * automatic), and every AI finding is labelled source='ai'. No node port is ever
 * contacted directly.
 */
class ContradictionEngineService
{
    /** @var array<string,string> Finding kinds. VARCHAR-backed, never ENUM. */
    public const KINDS = [
        'opposing_status'        => 'Opposing status',
        'shared_source_conflict' => 'Shared source conflict',
        'confidence_drop'        => 'Confidence drop',
        'definition_drift'       => 'Definition drift',
        'ai_flagged'             => 'AI flagged',
    ];

    /** @var array<string,string> Bootstrap badge colour per severity. */
    public const SEVERITY_BADGES = [
        'high'   => 'danger',
        'medium' => 'warning',
        'low'    => 'secondary',
    ];

    /** @var array<string,string> Bootstrap badge colour per status. */
    public const STATUS_BADGES = [
        'open'      => 'danger',
        'dismissed' => 'secondary',
        'resolved'  => 'success',
    ];

    /**
     * Statuses that read as a POSITIVE / accepted claim.
     * @var array<int,string>
     */
    private const POSITIVE_STATUSES = ['supported', 'verified', 'publishable', 'confirmed'];

    /**
     * Statuses that read as a NEGATIVE / rejected / contested claim.
     * @var array<int,string>
     */
    private const NEGATIVE_STATUSES = ['rejected', 'disputed', 'contested', 'weak', 'refuted'];

    /** Short, common words ignored when matching claim "topics". */
    private const STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'but', 'of', 'to', 'in', 'on', 'at', 'for',
        'with', 'is', 'are', 'was', 'were', 'be', 'been', 'that', 'this', 'it', 'as',
        'by', 'from', 'has', 'have', 'had', 'not', 'no', 'all', 'any', 'can', 'will',
        'than', 'then', 'they', 'their', 'its', 'which', 'who', 'what', 'when',
    ];

    // =====================================================================
    // READINESS
    // =====================================================================

    protected function findingsReady(): bool
    {
        try {
            return Schema::hasTable('research_contradiction');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function claimsReady(): bool
    {
        try {
            return Schema::hasTable('research_assertion');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function evidenceReady(): bool
    {
        try {
            return Schema::hasTable('research_assertion_evidence');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // PERSISTED FINDINGS (read)
    // =====================================================================

    /**
     * List persisted findings for a project, newest first, optionally by status.
     * Each row is decorated with the two referenced claims (label + status).
     *
     * @return array<int,object>
     */
    public function listFindings(int $projectId, ?string $status = 'open'): array
    {
        if (! $this->findingsReady()) {
            return [];
        }
        try {
            $q = DB::table('research_contradiction')
                ->where('project_id', $projectId);
            if ($status !== null && $status !== '' && $status !== 'all') {
                $q->where('status', $status);
            }
            $rows = $q->orderByRaw("FIELD(severity,'high','medium','low')")
                ->orderBy('updated_at', 'desc')
                ->get();

            // Batch-resolve referenced claims for labels.
            $claimIds = [];
            foreach ($rows as $r) {
                $claimIds[] = (int) $r->claim_a_id;
                if ($r->claim_b_id !== null) {
                    $claimIds[] = (int) $r->claim_b_id;
                }
            }
            $claimMap = $this->loadClaimMap($projectId, array_values(array_unique($claimIds)));

            foreach ($rows as $r) {
                $r->claim_a = $claimMap[(int) $r->claim_a_id] ?? null;
                $r->claim_b = $r->claim_b_id !== null ? ($claimMap[(int) $r->claim_b_id] ?? null) : null;
            }
            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Count findings per status (for the summary pills). @return array<string,int> */
    public function statusCounts(int $projectId): array
    {
        $out = ['open' => 0, 'dismissed' => 0, 'resolved' => 0];
        if (! $this->findingsReady()) {
            return $out;
        }
        try {
            $rows = DB::table('research_contradiction')
                ->where('project_id', $projectId)
                ->select('status', DB::raw('COUNT(*) as n'))
                ->groupBy('status')->pluck('n', 'status');
            foreach ($rows as $status => $n) {
                $out[$status] = (int) $n;
            }
            return $out;
        } catch (\Throwable $e) {
            return $out;
        }
    }

    /**
     * Load claims as id => {id,label,status} for label rendering.
     *
     * @param array<int,int> $ids
     * @return array<int,object>
     */
    protected function loadClaimMap(int $projectId, array $ids): array
    {
        if (empty($ids) || ! $this->claimsReady()) {
            return [];
        }
        try {
            $rows = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->whereIn('id', $ids)
                ->get(['id', 'subject_label', 'object_value', 'object_label', 'status']);
            $map = [];
            foreach ($rows as $r) {
                $r->label = $this->claimText($r);
                $map[(int) $r->id] = $r;
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =====================================================================
    // SCAN
    // =====================================================================

    /**
     * Run the heuristic scan over a project's Claim Ledger and persist findings
     * idempotently. Returns a summary of what was found / written.
     *
     * @return array{ok:bool, scanned:int, found:int, persisted:int, by_kind:array<string,int>, error?:string}
     */
    public function scan(int $projectId): array
    {
        $summary = ['ok' => false, 'scanned' => 0, 'found' => 0, 'persisted' => 0, 'by_kind' => []];
        if (! $this->claimsReady() || ! $this->findingsReady()) {
            $summary['error'] = 'Claim Ledger or contradiction store not installed yet.';
            return $summary;
        }

        try {
            $claims = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->get(['id', 'subject_label', 'object_value', 'object_label', 'predicate', 'status', 'confidence', 'version'])
                ->all();
            $summary['scanned'] = count($claims);

            $evidence = $this->loadEvidence(array_map(fn ($c) => (int) $c->id, $claims));
            $meta     = $this->loadMeta(array_map(fn ($c) => (int) $c->id, $claims));

            $findings = array_merge(
                $this->detectOpposingStatus($claims),
                $this->detectSharedSourceConflict($evidence, $claims),
                $this->detectConfidenceDrop($claims, $meta),
                $this->detectDefinitionDrift($claims)
            );

            $summary['found'] = count($findings);
            foreach ($findings as $f) {
                $summary['by_kind'][$f['kind']] = ($summary['by_kind'][$f['kind']] ?? 0) + 1;
            }

            $summary['persisted'] = $this->persistFindings($projectId, $findings, 'heuristic');
            $summary['ok'] = true;
            return $summary;
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] contradiction scan failed: '.$e->getMessage());
            $summary['error'] = 'Scan failed.';
            return $summary;
        }
    }

    // ---------------------------------------------------------------------
    // Heuristic rule (a): two claims with OPPOSING STATUS on the same topic.
    // ---------------------------------------------------------------------

    /**
     * Pair claims that share a topic (overlapping significant keywords) but sit
     * on opposite sides of the accepted/rejected line.
     *
     * @param array<int,object> $claims
     * @return array<int,array<string,mixed>>
     */
    protected function detectOpposingStatus(array $claims): array
    {
        $out = [];
        $n = count($claims);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $claims[$i];
                $b = $claims[$j];
                $sa = $this->statusPolarity($a->status);
                $sb = $this->statusPolarity($b->status);
                if ($sa === 0 || $sb === 0 || $sa === $sb) {
                    continue; // need one positive and one negative
                }
                $overlap = $this->keywordOverlap($this->claimText($a), $this->claimText($b));
                if ($overlap < 2) {
                    continue; // not clearly the same topic
                }
                $out[] = [
                    'kind'       => 'opposing_status',
                    'claim_a_id' => (int) $a->id,
                    'claim_b_id' => (int) $b->id,
                    'severity'   => $overlap >= 4 ? 'high' : 'medium',
                    'detail'     => 'These two claims share a topic ('.$overlap.' shared key terms) but hold opposing positions: "'
                        .$this->shorten($a->label ?? $this->claimText($a)).'" is '.$this->statusLabel($a->status)
                        .', while "'.$this->shorten($b->label ?? $this->claimText($b)).'" is '.$this->statusLabel($b->status).'.',
                ];
            }
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // Heuristic rule (b): a SHARED SOURCE that supports one claim and opposes
    // another (joins research_assertion_evidence by source_type + source_id).
    // ---------------------------------------------------------------------

    /**
     * @param array<int,object> $evidence  rows from research_assertion_evidence
     * @param array<int,object> $claims
     * @return array<int,array<string,mixed>>
     */
    protected function detectSharedSourceConflict(array $evidence, array $claims): array
    {
        $out = [];
        // Bucket evidence rows by their source key.
        $bySource = [];
        foreach ($evidence as $e) {
            $key = $e->source_type.':'.$e->source_id;
            $bySource[$key][] = $e;
        }
        $claimById = [];
        foreach ($claims as $c) {
            $claimById[(int) $c->id] = $c;
        }

        $seenPair = [];
        foreach ($bySource as $key => $rows) {
            $supports = [];
            $opposes  = [];
            foreach ($rows as $r) {
                $rel = strtolower((string) $r->relationship);
                if ($rel === 'supports') {
                    $supports[(int) $r->assertion_id] = true;
                } elseif ($rel === 'opposes') {
                    $opposes[(int) $r->assertion_id] = true;
                }
            }
            if (! $supports || ! $opposes) {
                continue;
            }
            foreach (array_keys($supports) as $aId) {
                foreach (array_keys($opposes) as $bId) {
                    if ($aId === $bId) {
                        continue; // same claim both ways - not a cross-claim conflict
                    }
                    $pairKey = min($aId, $bId).'-'.max($aId, $bId).'#'.$key;
                    if (isset($seenPair[$pairKey])) {
                        continue;
                    }
                    $seenPair[$pairKey] = true;
                    $la = isset($claimById[$aId]) ? $this->shorten($this->claimText($claimById[$aId])) : ('Claim #'.$aId);
                    $lb = isset($claimById[$bId]) ? $this->shorten($this->claimText($claimById[$bId])) : ('Claim #'.$bId);
                    $out[] = [
                        'kind'       => 'shared_source_conflict',
                        'claim_a_id' => $aId,
                        'claim_b_id' => $bId,
                        'severity'   => 'high',
                        'detail'     => 'The same source ('.$this->humanSource($key).') is cited to SUPPORT "'.$la
                            .'" but to OPPOSE "'.$lb.'". One source cannot do both - reconcile the reading of this source.',
                    ];
                }
            }
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // Heuristic rule (c): a claim whose CONFIDENCE dropped / status weakened.
    // No assertion-history table exists, so this reads the claim's current
    // state: a claim that has been revised (version > 1) yet now sits in a
    // weakened status, or whose stored numeric confidence contradicts its
    // sidecar confidence_level (e.g. high number but a "low" label).
    // ---------------------------------------------------------------------

    /**
     * @param array<int,object> $claims
     * @param array<int,object> $meta   keyed by assertion_id
     * @return array<int,array<string,mixed>>
     */
    protected function detectConfidenceDrop(array $claims, array $meta): array
    {
        $out = [];
        foreach ($claims as $c) {
            $status   = strtolower((string) $c->status);
            $version  = (int) ($c->version ?? 1);
            $conf     = $c->confidence !== null ? (float) $c->confidence : null;
            $m        = $meta[(int) $c->id] ?? null;
            $level    = $m && isset($m->confidence_level) ? strtolower((string) $m->confidence_level) : null;

            $weakened = in_array($status, self::NEGATIVE_STATUSES, true);

            // (i) revised into a weakened state while still holding high numeric confidence
            if ($version > 1 && $weakened && $conf !== null && $conf >= 0.70) {
                $out[] = [
                    'kind'       => 'confidence_drop',
                    'claim_a_id' => (int) $c->id,
                    'claim_b_id' => null,
                    'severity'   => 'medium',
                    'detail'     => 'This claim has been revised ('.$version.' versions) and is now '.$this->statusLabel($c->status)
                        .', yet still carries a high stored confidence of '.number_format($conf, 2)
                        .'. The recorded confidence has not been brought down to match the weaker status.',
                ];
                continue;
            }

            // (ii) numeric confidence and the sidecar confidence_level disagree
            if ($conf !== null && $level !== null) {
                $expected = $this->levelBand($level);
                if ($expected !== null && ($conf < $expected[0] || $conf > $expected[1])) {
                    $out[] = [
                        'kind'       => 'confidence_drop',
                        'claim_a_id' => (int) $c->id,
                        'claim_b_id' => null,
                        'severity'   => 'low',
                        'detail'     => 'Recorded confidence ('.number_format($conf, 2).') does not match the stated confidence level "'
                            .$level.'" for "'.$this->shorten($this->claimText($c)).'". One of the two has drifted.',
                    ];
                }
            }
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // Heuristic rule (d) [optional]: DEFINITION DRIFT - the same distinctive
    // term appears as the leading subject of two claims that otherwise sit on
    // opposite polarities, suggesting the term is being used two different ways.
    // ---------------------------------------------------------------------

    /**
     * @param array<int,object> $claims
     * @return array<int,array<string,mixed>>
     */
    protected function detectDefinitionDrift(array $claims): array
    {
        $out = [];
        // Index claims by their leading distinctive term.
        $byTerm = [];
        foreach ($claims as $c) {
            $term = $this->leadTerm($c);
            if ($term === '') {
                continue;
            }
            $byTerm[$term][] = $c;
        }
        foreach ($byTerm as $term => $group) {
            if (count($group) < 2) {
                continue;
            }
            // Find a positive/negative pair sharing the lead term but with LOW
            // body overlap - same word, different surrounding claim => drift.
            $n = count($group);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $group[$i];
                    $b = $group[$j];
                    $pa = $this->statusPolarity($a->status);
                    $pb = $this->statusPolarity($b->status);
                    if ($pa === 0 || $pb === 0 || $pa === $pb) {
                        continue;
                    }
                    $overlap = $this->keywordOverlap($this->claimText($a), $this->claimText($b));
                    if ($overlap > 2) {
                        continue; // high overlap is covered by opposing_status, not drift
                    }
                    $out[] = [
                        'kind'       => 'definition_drift',
                        'claim_a_id' => (int) $a->id,
                        'claim_b_id' => (int) $b->id,
                        'severity'   => 'low',
                        'detail'     => 'The term "'.$term.'" leads two claims that point in opposite directions but otherwise share little wording. '
                            .'It may be carrying two different meanings across the project. Check the definition is consistent.',
                    ];
                }
            }
        }
        return $out;
    }

    // =====================================================================
    // PERSISTENCE (idempotent upsert)
    // =====================================================================

    /**
     * Persist a batch of findings idempotently using a per-project signature.
     * Existing open findings keep their status; re-detected findings refresh
     * their detail/severity. Returns the count of rows written or refreshed.
     *
     * @param array<int,array<string,mixed>> $findings
     */
    public function persistFindings(int $projectId, array $findings, string $source): int
    {
        if (! $this->findingsReady() || empty($findings)) {
            return 0;
        }
        $written = 0;
        foreach ($findings as $f) {
            try {
                $sig = $this->signature($f['kind'], (int) $f['claim_a_id'], $f['claim_b_id'] !== null ? (int) $f['claim_b_id'] : null);
                $existing = DB::table('research_contradiction')
                    ->where('project_id', $projectId)
                    ->where('signature', $sig)
                    ->first();

                if ($existing) {
                    // Refresh detail/severity but never re-open a dismissed/resolved finding.
                    DB::table('research_contradiction')
                        ->where('id', $existing->id)
                        ->update([
                            'detail'     => $f['detail'] ?? null,
                            'severity'   => $f['severity'] ?? 'medium',
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('research_contradiction')->insert([
                        'project_id' => $projectId,
                        'claim_a_id' => (int) $f['claim_a_id'],
                        'claim_b_id' => $f['claim_b_id'] !== null ? (int) $f['claim_b_id'] : null,
                        'kind'       => $f['kind'],
                        'signature'  => $sig,
                        'detail'     => $f['detail'] ?? null,
                        'severity'   => $f['severity'] ?? 'medium',
                        'status'     => 'open',
                        'source'     => $source === 'ai' ? 'ai' : 'heuristic',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $written++;
            } catch (\Throwable $e) {
                // skip the offending finding; keep the batch going
            }
        }
        return $written;
    }

    /** Set a finding's status (dismiss / resolve / reopen). Project-scoped. */
    public function setStatus(int $projectId, int $findingId, string $status): bool
    {
        if (! $this->findingsReady()) {
            return false;
        }
        if (! in_array($status, ['open', 'dismissed', 'resolved'], true)) {
            return false;
        }
        try {
            return DB::table('research_contradiction')
                ->where('id', $findingId)
                ->where('project_id', $projectId)
                ->update(['status' => $status, 'updated_at' => now()]) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // OPTIONAL AI DEEPENING (gateway only, user-triggered, labelled)
    // =====================================================================

    /**
     * Ask the AHG AI gateway to surface contradictions a heuristic scan can
     * miss (semantic disagreement, implied mutual exclusivity). NEVER automatic
     * - only called from the user-triggered controller action. Routes through
     * AhgAiServices\Services\LlmService->complete(), which talks to the gateway
     * abstraction; no node port is ever contacted here. Every persisted finding
     * is labelled source='ai'.
     *
     * @return array{ok:bool, persisted:int, message:string}
     */
    public function aiDeepen(int $projectId, int $maxClaims = 40): array
    {
        if (! $this->claimsReady() || ! $this->findingsReady()) {
            return ['ok' => false, 'persisted' => 0, 'message' => 'Claim Ledger not available.'];
        }
        if (! class_exists(\AhgAiServices\Services\LlmService::class)) {
            return ['ok' => false, 'persisted' => 0, 'message' => 'AI services are not installed on this instance.'];
        }

        try {
            $claims = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->orderBy('id')
                ->limit($maxClaims)
                ->get(['id', 'subject_label', 'object_value', 'object_label', 'status'])
                ->all();
            if (count($claims) < 2) {
                return ['ok' => true, 'persisted' => 0, 'message' => 'Not enough claims for an AI pass.'];
            }

            $lines = [];
            foreach ($claims as $c) {
                $lines[] = (int) $c->id.'. '.$this->shorten($this->claimText($c), 240).' ['.$this->statusLabel($c->status).']';
            }

            $prompt = "You are reviewing a research project's claim ledger for CONTRADICTIONS that a busy researcher would miss. "
                ."Each numbered line is a claim with its id and status. Identify pairs of claims that contradict each other - "
                ."that cannot both be true, or that disagree in substance. Use ONLY the claims listed; never invent claims, ids, facts or sources. "
                ."Return ONE finding per line in EXACTLY this pipe format and nothing else:\n"
                ."ID_A|ID_B|severity(high|medium|low)|one sentence explaining the contradiction\n"
                ."If there are no contradictions, return the single word: NONE\n\n"
                ."CLAIMS:\n".implode("\n", $lines);

            $raw = (string) app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 700, 'temperature' => 0.2]);

            $findings = $this->parseAiFindings($raw, $claims);
            $persisted = $this->persistFindings($projectId, $findings, 'ai');

            return [
                'ok'        => true,
                'persisted' => $persisted,
                'message'   => $persisted > 0
                    ? ('AI review added or refreshed '.$persisted.' finding(s).')
                    : 'AI review found no additional contradictions.',
            ];
        } catch (\Throwable $e) {
            Log::warning('[ahg-research] contradiction AI deepen failed: '.$e->getMessage());
            return ['ok' => false, 'persisted' => 0, 'message' => 'The AI gateway call did not complete. Please try again.'];
        }
    }

    /**
     * Parse the gateway's pipe-delimited response into validated findings.
     * Only claim ids that exist in the supplied set are accepted.
     *
     * @param array<int,object> $claims
     * @return array<int,array<string,mixed>>
     */
    protected function parseAiFindings(string $raw, array $claims): array
    {
        $valid = [];
        foreach ($claims as $c) {
            $valid[(int) $c->id] = true;
        }
        $out = [];
        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '' || strtoupper($line) === 'NONE') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 4) {
                continue;
            }
            $aId = (int) preg_replace('/\D/', '', $parts[0]);
            $bId = (int) preg_replace('/\D/', '', $parts[1]);
            if ($aId === 0 || $bId === 0 || $aId === $bId) {
                continue;
            }
            if (! isset($valid[$aId]) || ! isset($valid[$bId])) {
                continue; // model referenced a claim that is not in the set - reject
            }
            $sev = strtolower($parts[2]);
            if (! in_array($sev, ['high', 'medium', 'low'], true)) {
                $sev = 'medium';
            }
            $out[] = [
                'kind'       => 'ai_flagged',
                'claim_a_id' => $aId,
                'claim_b_id' => $bId,
                'severity'   => $sev,
                'detail'     => '[AI - via gateway] '.mb_substr($parts[3], 0, 1000),
            ];
        }
        return $out;
    }

    // =====================================================================
    // INTERNAL HELPERS
    // =====================================================================

    /**
     * @param array<int,int> $claimIds
     * @return array<int,object>
     */
    protected function loadEvidence(array $claimIds): array
    {
        if (empty($claimIds) || ! $this->evidenceReady()) {
            return [];
        }
        try {
            return DB::table('research_assertion_evidence')
                ->whereIn('assertion_id', $claimIds)
                ->get(['assertion_id', 'source_type', 'source_id', 'relationship'])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<int,int> $claimIds
     * @return array<int,object> keyed by assertion_id
     */
    protected function loadMeta(array $claimIds): array
    {
        if (empty($claimIds)) {
            return [];
        }
        try {
            if (! Schema::hasTable('research_claim_meta')) {
                return [];
            }
            return DB::table('research_claim_meta')
                ->whereIn('assertion_id', $claimIds)
                ->get()->keyBy('assertion_id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Best available human text for a claim row. */
    protected function claimText(object $c): string
    {
        $text = $c->object_value ?? $c->subject_label ?? $c->object_label ?? '';
        return trim((string) $text);
    }

    /** Map a status to polarity: 1 = positive, -1 = negative, 0 = neutral/unknown. */
    protected function statusPolarity(?string $status): int
    {
        $s = strtolower(trim((string) $status));
        if (in_array($s, self::POSITIVE_STATUSES, true)) {
            return 1;
        }
        if (in_array($s, self::NEGATIVE_STATUSES, true)) {
            return -1;
        }
        return 0;
    }

    /** Human label for a status value. */
    protected function statusLabel(?string $status): string
    {
        $s = trim((string) $status);
        return $s === '' ? 'unstated' : str_replace('_', ' ', $s);
    }

    /** Numeric confidence band expected for a textual level, or null. @return array{0:float,1:float}|null */
    protected function levelBand(string $level): ?array
    {
        return match ($level) {
            'high'      => [0.70, 1.00],
            'medium'    => [0.40, 0.79],
            'low'       => [0.10, 0.49],
            'tentative' => [0.00, 0.29],
            default     => null,
        };
    }

    /**
     * Count significant (non-stopword, length>=4) keywords two claim texts share.
     */
    protected function keywordOverlap(string $a, string $b): int
    {
        $ka = $this->keywords($a);
        $kb = $this->keywords($b);
        if (empty($ka) || empty($kb)) {
            return 0;
        }
        return count(array_intersect(array_keys($ka), array_keys($kb)));
    }

    /** @return array<string,bool> significant keyword set for a text. */
    protected function keywords(string $text): array
    {
        $text = mb_strtolower($text);
        $words = preg_split('/[^a-z0-9]+/i', $text) ?: [];
        $out = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 4) {
                continue;
            }
            if (in_array($w, self::STOPWORDS, true)) {
                continue;
            }
            $out[$w] = true;
        }
        return $out;
    }

    /** The leading distinctive term of a claim (first significant keyword). */
    protected function leadTerm(object $c): string
    {
        foreach (array_keys($this->keywords($this->claimText($c))) as $w) {
            return $w;
        }
        return '';
    }

    /** Human-readable label for an evidence source key (type:id). */
    protected function humanSource(string $key): string
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, '');
        return ucfirst(str_replace('_', ' ', $type)).' #'.$id;
    }

    /** Shorten a string for inline display. */
    protected function shorten(string $text, int $len = 90): string
    {
        $text = trim($text);
        return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1).'…' : $text;
    }

    /** Stable per-project signature so a re-scan upserts rather than duplicates. */
    protected function signature(string $kind, int $aId, ?int $bId): string
    {
        if ($bId === null) {
            $pair = (string) $aId;
        } else {
            $lo = min($aId, $bId);
            $hi = max($aId, $bId);
            $pair = $lo.'-'.$hi;
        }
        return $kind.':'.$pair;
    }
}
