<?php
/**
 * Heratio - EU AI Act Article 9 risk management service.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Services;

use AhgAiCompliance\Models\AiRisk;
use AhgAiCompliance\Models\AiRiskIncident;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AiRiskService
{
    public function __construct(private InferenceLogger $logger)
    {
    }

    /**
     * Default risks seeded on first boot. One row per AI service. Operator
     * can edit / archive / extend via the admin UI; never delete - the audit
     * trail prefers archive over hard-delete for compliance review.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function seedRisks(): array
    {
        return [
            // LLM
            [
                'service'           => 'llm',
                'risk_description' => 'Hallucination - the model generates plausible-sounding but factually incorrect descriptions, dates, or attributions.',
                'severity'          => 'high',
                'likelihood'        => 'high',
                'intended_or_misuse' => 'intended',
                'affected_group'    => 'researchers',
                'mitigation'        => 'Suggestions are draft-only; flagged as AI-generated in the UI; human review required before publish; grounding score from GuardrailService::checkGrounding() flags low-grounding outputs.',
                'residual_risk'     => 'medium',
            ],
            [
                'service'           => 'llm',
                'risk_description' => 'Prompt injection from user-supplied text fields leaks system prompt or causes model to ignore safety constraints.',
                'severity'          => 'medium',
                'likelihood'        => 'medium',
                'intended_or_misuse' => 'misuse',
                'affected_group'    => null,
                'mitigation'        => 'GuardrailService::inspect() applies policy gates pre-dispatch (block / mask / allow); PII redaction on cloud-bound prompts; receipts to the #693 chain capture every block decision.',
                'residual_risk'     => 'low',
            ],

            // HTR
            [
                'service'           => 'htr',
                'risk_description' => 'OCR / HTR misreads on degraded handwriting introduce factual errors into transcripts; downstream consumers may treat as authoritative.',
                'severity'          => 'high',
                'likelihood'        => 'high',
                'intended_or_misuse' => 'intended',
                'affected_group'    => 'researchers',
                'mitigation'        => 'Confidence score surfaced in viewer; per-line CER reported; human review required before exporting; original page image always linked for verification.',
                'residual_risk'     => 'medium',
            ],
            [
                'service'           => 'htr',
                'risk_description' => 'Indigenous / rare-script handwriting (e.g. Ge\'ez, Khoekhoegowab, Old Cyrillic) under-represented in training data; systematic errors disproportionately affect those collections.',
                'severity'          => 'high',
                'likelihood'        => 'medium',
                'intended_or_misuse' => 'intended',
                'affected_group'    => 'indigenous_language_collections',
                'mitigation'        => 'Per-script accuracy metrics tracked in ai_model_registry; collections in under-served scripts flagged for elevated human review; Article 9(9) vulnerable-group escalation applies.',
                'residual_risk'     => 'medium',
            ],

            // NER
            [
                'service'           => 'ner',
                'risk_description' => 'Misidentification of named entities - false positive on persons (wrong name attributed to a document) or false negative (missed entity not surfaced for indexing).',
                'severity'          => 'medium',
                'likelihood'        => 'high',
                'intended_or_misuse' => 'intended',
                'affected_group'    => 'data_subjects',
                'mitigation'        => 'NER review queue requires human confirmation before linking to actor records; per-entity confidence score surfaced; reversible: linked actor associations can be unlinked.',
                'residual_risk'     => 'low',
            ],

            // Donut
            [
                'service'           => 'donut',
                'risk_description' => 'Layout / form-field extraction drift on non-standard forms produces wrong field-to-value mapping.',
                'severity'          => 'medium',
                'likelihood'        => 'medium',
                'intended_or_misuse' => 'intended',
                'affected_group'    => null,
                'mitigation'        => 'Extracted fields presented for human verification before ingestion; needs_review flag surfaces low-confidence extractions.',
                'residual_risk'     => 'low',
            ],

            // Guardrail
            [
                'service'           => 'guardrail',
                'risk_description' => 'Guardrail mis-classification: allows a policy-violating prompt or blocks a legitimate one (false negative / false positive on data-scope or purpose).',
                'severity'          => 'medium',
                'likelihood'        => 'low',
                'intended_or_misuse' => 'intended',
                'affected_group'    => null,
                'mitigation'        => 'Every inspect() decision (allow / mask / block) writes a receipt to #693; operators can audit false-classification incidents via ai_risk_incident; configuration in ahg_setting is operator-editable.',
                'residual_risk'     => 'low',
            ],

            // Translate
            [
                'service'           => 'translate',
                'risk_description' => 'Mistranslation of legally-significant or culturally-sensitive text alters meaning of archival records.',
                'severity'          => 'high',
                'likelihood'        => 'medium',
                'intended_or_misuse' => 'intended',
                'affected_group'    => 'indigenous_language_collections',
                'mitigation'        => 'SA-language targets routed to MzansiLM (purpose-trained on SA corpus, not qwen3); translations flagged as AI-output; human reviewer must approve before publish; source text always preserved alongside translation.',
                'residual_risk'     => 'medium',
            ],
        ];
    }

    /**
     * Run on first boot to populate the register if empty.
     */
    public function seedIfEmpty(): int
    {
        if (AiRisk::query()->exists()) {
            return 0;
        }

        $count = 0;
        foreach (self::seedRisks() as $row) {
            AiRisk::create($row);
            $count++;
        }
        return $count;
    }

    /**
     * @return Collection<int,AiRisk>
     */
    public function listAll(?string $service = null, string $status = 'active'): Collection
    {
        $q = AiRisk::query();
        if ($service !== null) {
            $q->where('service', $service);
        }
        if ($status !== '*') {
            $q->where('status', $status);
        }
        return $q->orderBy('service')->orderByDesc('severity')->get();
    }

    public function find(int $id): ?AiRisk
    {
        return AiRisk::query()->find($id);
    }

    public function create(array $attrs): AiRisk
    {
        return AiRisk::create($attrs);
    }

    public function update(int $id, array $attrs): ?AiRisk
    {
        $r = AiRisk::query()->find($id);
        if ($r === null) {
            return null;
        }
        $r->fill($attrs);
        $r->save();
        return $r;
    }

    public function archive(int $id): bool
    {
        $r = AiRisk::query()->find($id);
        if ($r === null) {
            return false;
        }
        $r->status = 'archived';
        $r->save();
        return true;
    }

    /**
     * Record a reviewer sign-off on a risk: stamp the review timestamp + user,
     * AND emit a receipt to the #693 inference chain so the review history is
     * tamper-evident.
     */
    public function signOff(int $id): ?AiRisk
    {
        $r = AiRisk::query()->find($id);
        if ($r === null) {
            return null;
        }
        $userId = Auth::id();
        $r->last_reviewed_at = now();
        $r->reviewer_user_id = $userId;
        $r->save();

        try {
            $this->logger->log(
                'risk-signoff',
                'risk-register',
                null,
                "risk:{$r->id}:{$r->service}",
                (string) json_encode([
                    'risk_id'          => $r->id,
                    'service'          => $r->service,
                    'severity'         => $r->severity,
                    'mitigation'       => $r->mitigation,
                    'residual_risk'    => $r->residual_risk,
                    'reviewer_user_id' => $userId,
                ], JSON_UNESCAPED_UNICODE),
                [],
            );
        } catch (Throwable) {
            // Chain write failure must not abort the sign-off
        }
        return $r;
    }

    public function recordIncident(int $riskId, string $description, string $severityObserved, ?int $inferenceLogId = null): ?AiRiskIncident
    {
        if (!AiRisk::query()->where('id', $riskId)->exists()) {
            return null;
        }
        return AiRiskIncident::create([
            'risk_id'           => $riskId,
            'reporter_user_id'  => Auth::id(),
            'description'       => $description,
            'severity_observed' => $severityObserved,
            'inference_log_id'  => $inferenceLogId,
            'created_at'        => now(),
        ]);
    }

    /**
     * Weekly post-market monitoring sweep. Looks at the #693 ai_inference_log
     * for anomalous patterns (sudden spike in low-grounding decisions, surge
     * in guardrail block actions). Returns a digest array; the caller posts
     * it to the workbench notification spool or surfaces it in admin UI.
     *
     * @return array<string,mixed>
     */
    public function postMarketDigest(?\DateTimeInterface $since = null): array
    {
        $since = $since ?? now()->subDays(7);

        $byService = DB::table('ai_inference_log')
            ->where('ts', '>=', $since)
            ->selectRaw('service, COUNT(*) AS total')
            ->groupBy('service')
            ->pluck('total', 'service')
            ->all();

        $guardrailBlocks = DB::table('ai_inference_log')
            ->where('ts', '>=', $since)
            ->where('service', 'guardrail')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.output_fingerprint')) IS NOT NULL")
            ->count();

        return [
            'since'             => $since instanceof \DateTimeImmutable ? $since->format(DATE_ATOM) : (string) $since,
            'inferences'        => $byService,
            'guardrail_events'  => $guardrailBlocks,
            'open_incidents'    => AiRiskIncident::query()->whereNull('resolved_at')->count(),
            'overdue_reviews'   => AiRisk::query()
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('last_reviewed_at')
                    ->orWhere('last_reviewed_at', '<', now()->subYear()))
                ->count(),
        ];
    }
}
