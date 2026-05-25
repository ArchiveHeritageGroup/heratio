# AI Compliance - EU AI Act Article 14 human oversight

Implementation reference for the human-oversight controls in Heratio. Phase 1 of issue #726.

## What it is

Article 14 of the EU AI Act requires that high-risk AI systems be designed so they can be effectively overseen by natural persons during use. The persons assigned must be able to: understand capabilities + limits, monitor for anomalies, remain aware of automation bias, interpret output correctly, decide not to use it, intervene / stop, recall + audit.

Phase 1 ships the policy layer, halt switch, attestation flow, and dual-review countersign path. Phase 2 wires the per-page UI banners onto every existing AI-output surface (currently locked).

## Architecture

```
+-----------------------------------+
|  OversightService                  |
|  - isHalted(service)               |
|  - requiresReview(service, conf)   |
|  - requiresDualReview(service)     |
|  - halt / resume / haltAll         |
|  - recordAttestation / hasActive   |
|  - recordDecision / countersign    |
+-----------------------------------+
            |
            v
+-----------------------------------+
|  ai_oversight_policy               |
|  ai_operator_attestation           |
|  ai_review_decision                |
+-----------------------------------+
            |
            v
+-----------------------------------+
|  Admin UI /admin/ai-compliance/   |
|  oversight (policies, halt,        |
|  attestations, pending countersign)|
+-----------------------------------+
            |
            v   every state change ->
+-----------------------------------+
|  #693 inference receipt chain      |
+-----------------------------------+
```

## Database schema

### `ai_oversight_policy`

One row per AI service. Operator-editable. Auto-seeded on first boot with sensible defaults.

| Column                       | Type           | Purpose |
|------------------------------|----------------|---------|
| `service`                    | VARCHAR(32)    | llm / htr / ner / donut / guardrail / translate / facedetect |
| `requires_human_review`      | TINYINT(1)     | Master review toggle |
| `confidence_threshold`       | DECIMAL(4,3)   | Force review when output confidence is below this |
| `dual_review_required`       | TINYINT(1)     | Art. 14(5) two-person verification (biometric) |
| `halted`                     | TINYINT(1)     | Per-service kill switch |
| `halted_reason` / `halted_at` / `halted_by_user_id` | | Halt provenance |
| `automation_bias_prompt_text`| VARCHAR(512)   | Banner shown above AI output in the UI |

### `ai_operator_attestation`

Annual operator acknowledgement of automation bias (Art. 14(4)(b)). One row per attestation event; latest wins. Without an active attestation, operators cannot approve AI output (Phase 2 gate).

| Column           | Type        | Purpose |
|------------------|-------------|---------|
| `user_id`        | BIGINT      | Operator id |
| `attested_at`    | DATETIME    | When |
| `expires_at`     | DATETIME    | +1 year by default |
| `version`        | VARCHAR(16) | Bump when training material changes; forces re-attestation |
| `chain_entry_hash`| CHAR(64)   | Link to the #693 receipt |

### `ai_review_decision`

Every operator override / confirm / reject + optional Art. 14(5) countersignature.

| Column                  | Type        | Purpose |
|-------------------------|-------------|---------|
| `inference_log_id`      | BIGINT      | Cross-link to ai_inference_log when known |
| `service`               | VARCHAR(32) | Which AI service produced the output |
| `reviewer_user_id`      | BIGINT      | First reviewer (Art. 14(4)(d)) |
| `decision`              | VARCHAR(16) | confirm / override / reject |
| `note`                  | TEXT        | Reviewer rationale |
| `countersigner_user_id` | BIGINT      | Second reviewer for Art. 14(5) biometric |
| `countersigned_at`      | DATETIME    | Required for facedetect actions |
| `chain_entry_hash`      | CHAR(64)    | Link to #693 receipt |

## Default policies seeded

| Service     | Review? | Threshold | Dual? | Notes |
|-------------|---------|-----------|-------|-------|
| llm         | yes     | 0.000     | no    | All generative LLM output needs human review |
| htr         | yes     | 0.800     | no    | Per-page CER < 0.20 means transcript needs review |
| ner         | yes     | 0.700     | no    | Per-entity confidence threshold |
| donut       | yes     | 0.800     | no    | Form-field extraction confidence |
| guardrail   | no      | 0.000     | no    | Guardrail IS the policy mechanism; not reviewed |
| translate   | yes     | 0.000     | no    | All translations reviewed (especially SA languages via MzansiLM) |
| facedetect  | yes     | 1.000     | **YES** | Art. 14(5) biometric requires two-person verification before any action |

## Operator workflow

### One-time setup

`php artisan ai-compliance:install-key` triggers the schema-probe block in the service provider, which auto-seeds the policy table with the defaults above.

### Operator attestation (annual)

Operators visit `/admin/ai-compliance/oversight`. The "Automation-bias attestation" card shows status:

- Green badge "Active" + expiry date - good
- Red badge "Expired" or yellow "Not attested" - operator clicks the "Attest now" button

Clicking writes:

1. A row in `ai_operator_attestation` with expiry = now + 1 year
2. A receipt to the #693 chain capturing `{user_id, version, attested_at}`
3. `chain_entry_hash` back-references the receipt

### Halt a service

UI: `/admin/ai-compliance/oversight` -> per-row halt button. Writes a chain receipt with the halt reason.

CLI:

```bash
# Halt one service
php artisan ai-compliance:halt llm --reason="Suspected prompt-injection vector"

# Halt all AI services NOW
php artisan ai-compliance:halt --reason="Outage - block until investigation"

# Resume
php artisan ai-compliance:halt llm --resume
php artisan ai-compliance:halt --resume    # all services
```

### Two-person verification (Art. 14(5))

For services with `dual_review_required=1` (facedetect by default), every `recordDecision()` lands in `ai_review_decision` with `countersigner_user_id=null`. The oversight admin page lists pending countersignatures; a different operator clicks "Countersign" to complete the Art. 14(5) requirement. Countersignature is rejected if it's by the original reviewer.

## Article 14 mapping

| Article 14 paragraph | Heratio implementation |
|---|---|
| 14(4)(a) - understand capabilities + limits | Risk register (#724) + model registry (#725) + Annex IV docs |
| 14(4)(b) - automation-bias awareness | Annual attestation + banner text in policy |
| 14(4)(c) - correctly interpret output | Confidence threshold on each service |
| 14(4)(d) - decide not to use / disregard | `recordDecision()` with reject/override |
| 14(4)(e) - intervene + stop | `ai-compliance:halt` CLI + admin UI buttons |
| **14(5)** - two-person verification for biometric ID | `dual_review_required` + `countersign()` flow |

## Phase 2 follow-ups (NOT in this release)

Adding the actual review banners + override / confirm / reject buttons on every existing AI-output surface is locked behind individual unlock approvals for each surface:

- IO show page (locked)
- Suggestion modal in IO edit (locked)
- NER review queue (locked)
- HTR transcript viewer (locked)
- Translation modal (locked)

For Phase 2, the per-page wiring will be:

```blade
@if (config('ai-compliance.show_banner', true) && $aiSuggestion)
  <div class="alert alert-warning small">
    <i class="bi bi-robot"></i>
    {{ $oversight->policyFor($service)->automation_bias_prompt_text }}
  </div>
@endif
```

Plus three buttons per output that POST to `recordDecision()`:

```
[ Confirm ]  [ Override ]  [ Reject ]
```

When the policy is `dual_review_required=1`, a second-reviewer button appears on the oversight admin page.

## Threat model

Detects:
- Operator halts a service but doesn't tell anyone (receipt in #693 chain)
- Operator approves AI output without attestation (admin UI blocks the path; receipt absence is the audit signal)
- Single-person biometric ID action (countersign requirement enforced server-side)

Does not detect:
- Operator backdoors the policy table directly via SQL. Mitigation: row-level audit trigger; future Phase 3 alongside #676.
- Collusion between two reviewers on a dual-review countersign. Mitigation: pick reviewers from disjoint operational teams; Art. 14(5) is not a cryptographic guarantee, it's a procedural one.

## Related issues

- #693 Article 12 record-keeping (closed) - sign-off + decision + halt events write here
- #724 Article 9 risk management (open) - vulnerable-group escalation shared
- #725 Article 11 Annex IV documentation (open) - oversight controls described per service
