<?php
/**
 * Heratio - EU AI Act Article 14 human-oversight service.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Services;

use AhgAiCompliance\Models\AiOperatorAttestation;
use AhgAiCompliance\Models\AiOversightPolicy;
use AhgAiCompliance\Models\AiReviewDecision;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class OversightService
{
    public const ATTESTATION_VERSION = '2026-08';

    public function __construct(private InferenceLogger $logger)
    {
    }

    /**
     * Default policy per AI service, seeded on first boot. Operators tune via
     * the admin UI; never delete rows.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function seedPolicies(): array
    {
        $bias = 'This is an AI-generated suggestion. Verify against the source before using.';
        return [
            // LLM - human review by default, generative output is high-risk for hallucination
            ['service' => 'llm',       'requires_human_review' => 1, 'confidence_threshold' => 0.000, 'dual_review_required' => 0, 'automation_bias_prompt_text' => $bias],
            // HTR - low-confidence pages need review; the threshold gates it
            ['service' => 'htr',       'requires_human_review' => 1, 'confidence_threshold' => 0.800, 'dual_review_required' => 0, 'automation_bias_prompt_text' => $bias],
            // NER - high recall, review only below threshold
            ['service' => 'ner',       'requires_human_review' => 1, 'confidence_threshold' => 0.700, 'dual_review_required' => 0, 'automation_bias_prompt_text' => $bias],
            // Donut - operator confirms form-field extraction
            ['service' => 'donut',     'requires_human_review' => 1, 'confidence_threshold' => 0.800, 'dual_review_required' => 0, 'automation_bias_prompt_text' => $bias],
            // Guardrail - no human review (this IS the policy mechanism)
            ['service' => 'guardrail', 'requires_human_review' => 0, 'confidence_threshold' => 0.000, 'dual_review_required' => 0, 'automation_bias_prompt_text' => null],
            // Translate - SA-language translations especially benefit from human review
            ['service' => 'translate', 'requires_human_review' => 1, 'confidence_threshold' => 0.000, 'dual_review_required' => 0, 'automation_bias_prompt_text' => 'AI-translated content. Verify proper nouns and culturally-sensitive terms against the source.'],
            // Face-detect - Art. 14(5) two-person verification REQUIRED for any biometric ID
            ['service' => 'facedetect', 'requires_human_review' => 1, 'confidence_threshold' => 1.000, 'dual_review_required' => 1, 'automation_bias_prompt_text' => 'Biometric identification. Art. 14(5) requires verification by at least two natural persons before any action is taken.'],
        ];
    }

    public function seedIfEmpty(): int
    {
        if (AiOversightPolicy::query()->exists()) {
            return 0;
        }

        $count = 0;
        foreach (self::seedPolicies() as $row) {
            AiOversightPolicy::create($row);
            $count++;
        }
        return $count;
    }

    /**
     * @return Collection<int,AiOversightPolicy>
     */
    public function allPolicies(): Collection
    {
        return AiOversightPolicy::query()->orderBy('service')->get();
    }

    public function policyFor(string $service): ?AiOversightPolicy
    {
        return AiOversightPolicy::query()->where('service', $service)->first();
    }

    public function isHalted(string $service): bool
    {
        $p = $this->policyFor($service);
        return $p !== null && (bool) $p->halted;
    }

    public function requiresReview(string $service, ?float $confidence = null): bool
    {
        $p = $this->policyFor($service);
        if ($p === null) {
            return true;
        }
        if (!$p->requires_human_review) {
            return false;
        }
        if ($confidence === null) {
            return true;
        }
        return $confidence < (float) $p->confidence_threshold;
    }

    public function requiresDualReview(string $service): bool
    {
        $p = $this->policyFor($service);
        return $p !== null && (bool) $p->dual_review_required;
    }

    public function halt(string $service, string $reason): ?AiOversightPolicy
    {
        $p = $this->policyFor($service);
        if ($p === null) {
            return null;
        }
        $p->halted = true;
        $p->halted_reason = substr($reason, 0, 255);
        $p->halted_at = now();
        $p->halted_by_user_id = Auth::id();
        $p->save();

        try {
            $this->logger->log(
                'halt',
                $service,
                null,
                "halt:{$service}",
                (string) json_encode([
                    'service'  => $service,
                    'reason'   => $reason,
                    'user_id'  => Auth::id(),
                    'halted_at' => $p->halted_at?->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                [],
            );
        } catch (Throwable) {
            // chain failure must not abort the halt itself - we already wrote the DB row
        }

        return $p;
    }

    public function resume(string $service): ?AiOversightPolicy
    {
        $p = $this->policyFor($service);
        if ($p === null) {
            return null;
        }
        $p->halted = false;
        $p->halted_reason = null;
        $p->halted_at = null;
        $p->halted_by_user_id = null;
        $p->save();

        try {
            $this->logger->log(
                'resume',
                $service,
                null,
                "resume:{$service}",
                (string) json_encode(['service' => $service, 'user_id' => Auth::id()], JSON_UNESCAPED_UNICODE),
                [],
            );
        } catch (Throwable) {
            // already resumed in DB
        }

        return $p;
    }

    public function haltAll(string $reason): int
    {
        $count = 0;
        foreach (AiOversightPolicy::query()->where('halted', 0)->get() as $p) {
            $this->halt($p->service, $reason);
            $count++;
        }
        return $count;
    }

    public function resumeAll(): int
    {
        $count = 0;
        foreach (AiOversightPolicy::query()->where('halted', 1)->get() as $p) {
            $this->resume($p->service);
            $count++;
        }
        return $count;
    }

    /**
     * Operator attests they understand the automation-bias risk and agree to
     * review AI output critically (Art. 14(4)(b)). Annual renewal. Writes a
     * receipt to the #693 chain.
     */
    public function recordAttestation(int $userId, string $version = self::ATTESTATION_VERSION): AiOperatorAttestation
    {
        $now = now();
        $row = AiOperatorAttestation::create([
            'user_id'     => $userId,
            'attested_at' => $now,
            'expires_at'  => $now->copy()->addYear(),
            'version'     => $version,
        ]);

        try {
            $receipt = $this->logger->log(
                'attestation',
                'automation-bias-training',
                $version,
                "attestation:user:{$userId}:v{$version}",
                (string) json_encode([
                    'user_id'    => $userId,
                    'version'    => $version,
                    'attested_at' => $now->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                [],
            );
            if ($receipt !== null) {
                $row->chain_entry_hash = $receipt->entryHash;
                $row->save();
            }
        } catch (Throwable) {
            // chain write failed; row stays in DB without chain link
        }

        return $row;
    }

    public function hasActiveAttestation(int $userId): bool
    {
        return AiOperatorAttestation::query()
            ->where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function latestAttestation(int $userId): ?AiOperatorAttestation
    {
        return AiOperatorAttestation::query()
            ->where('user_id', $userId)
            ->orderByDesc('attested_at')
            ->first();
    }

    /**
     * Record an operator decision on AI output: confirm, override, reject.
     * Writes a receipt to the #693 chain.
     */
    public function recordDecision(
        string $service,
        string $decision,
        ?int $inferenceLogId,
        ?string $note,
    ): AiReviewDecision {
        $userId = (int) Auth::id();
        $row = AiReviewDecision::create([
            'service'           => $service,
            'decision'          => $decision,
            'inference_log_id'  => $inferenceLogId,
            'reviewer_user_id'  => $userId,
            'note'              => $note,
        ]);

        try {
            $receipt = $this->logger->log(
                'review',
                $service,
                null,
                "review:{$service}:{$inferenceLogId}",
                (string) json_encode([
                    'service'          => $service,
                    'decision'         => $decision,
                    'inference_log_id' => $inferenceLogId,
                    'reviewer_user_id' => $userId,
                    'note'             => $note,
                ], JSON_UNESCAPED_UNICODE),
                [],
            );
            if ($receipt !== null) {
                $row->chain_entry_hash = $receipt->entryHash;
                $row->save();
            }
        } catch (Throwable) {
            // DB row stands; chain link missing
        }

        return $row;
    }

    /**
     * Countersign a review decision for Art. 14(5) dual-review requirement.
     * Countersigner MUST be a different user than the original reviewer.
     */
    public function countersign(int $reviewDecisionId): ?AiReviewDecision
    {
        $row = AiReviewDecision::query()->find($reviewDecisionId);
        if ($row === null) {
            return null;
        }
        $countersigner = (int) Auth::id();
        if ($row->reviewer_user_id === $countersigner) {
            return null;
        }
        $row->countersigner_user_id = $countersigner;
        $row->countersigned_at = now();
        $row->save();

        try {
            $this->logger->log(
                'countersign',
                $row->service,
                null,
                "countersign:{$reviewDecisionId}",
                (string) json_encode([
                    'review_decision_id'    => $reviewDecisionId,
                    'service'               => $row->service,
                    'reviewer_user_id'      => $row->reviewer_user_id,
                    'countersigner_user_id' => $countersigner,
                ], JSON_UNESCAPED_UNICODE),
                [],
            );
        } catch (Throwable) {
            // DB row stands
        }

        return $row;
    }
}
