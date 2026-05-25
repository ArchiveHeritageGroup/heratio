# AI Compliance - EU AI Act Article 9 risk management

Implementation reference for the AI risk register and post-market monitoring loop. Closes Phase 1 of issue #724.

## What it is

Article 9 of the EU AI Act requires a continuous, iterative risk management process for high-risk AI systems, maintained over the system's lifecycle. The risk register captures every known risk per AI service, the mitigation in place, and the residual risk after mitigation. Operators sign-off reviews at least annually; sign-off writes an entry into the #693 tamper-evident chain so the review history is itself auditable.

## Architecture

```
+--------------------------+        writes incident
|  AI services             | -------------------------> ai_risk_incident
|  (LlmService, HtrService,| (operator-flagged or auto)
|   NerService, ...)       |
+--------------------------+
            |
            v
+--------------------------+
|  AiRiskService            |
|  - listAll                |
|  - signOff (-> #693)      |
|  - recordIncident         |
|  - postMarketDigest       |
+--------------------------+
            |
            v
+--------------------------+      +-------------------------+
|  Admin UI                 |      |  Cron - weekly digest   |
|  /admin/ai-compliance/risk|      |  ai-compliance:risk-monitor
+--------------------------+      |  -> workbench inbox     |
                                  +-------------------------+
```

## Database schema

### `ai_risk_register`

One row per identified risk per AI service. Operator-editable via the admin UI; seeded on first boot with one default entry per service.

| Column              | Type              | Purpose |
|---------------------|-------------------|---------|
| `id`                | BIGINT PK         | |
| `service`           | VARCHAR(32)       | llm / htr / ner / donut / guardrail / translate |
| `risk_description`  | VARCHAR(512)      | Free-text description |
| `severity`          | VARCHAR(16)       | low / medium / high / critical (before mitigation) |
| `likelihood`        | VARCHAR(16)       | low / medium / high |
| `intended_or_misuse`| VARCHAR(32)       | intended / misuse (Art. 9(2)(b) - foreseeable misuse pathway) |
| `affected_group`    | VARCHAR(64)       | Tag for Art. 9(9) vulnerable-group escalation |
| `mitigation`        | TEXT              | Concrete controls in place |
| `residual_risk`     | VARCHAR(16)       | low / medium / high / critical (after mitigation) |
| `status`            | VARCHAR(16)       | active / archived |
| `last_reviewed_at`  | DATETIME          | When operator last signed off (null = never reviewed) |
| `reviewer_user_id`  | BIGINT            | Operator who signed off |
| `created_at` / `updated_at` | DATETIME  | |

### `ai_risk_incident`

Operator-flagged real-world reports. Feeds the weekly post-market monitoring digest.

| Column              | Type              | Purpose |
|---------------------|-------------------|---------|
| `id`                | BIGINT PK         | |
| `risk_id`           | BIGINT FK         | References `ai_risk_register.id` |
| `reporter_user_id`  | BIGINT            | Operator who flagged |
| `description`       | TEXT              | What happened |
| `severity_observed` | VARCHAR(16)       | low / medium / high / critical |
| `inference_log_id`  | BIGINT            | Optional link to the specific #693 receipt that triggered the report |
| `resolved_at`       | DATETIME          | Set when the incident is closed |
| `created_at`        | DATETIME          | |

## Default seed

On first boot, `AiRiskService::seedIfEmpty()` populates the register with one row per service covering known risk classes:

- **llm**: hallucination (high/high); prompt injection (medium/medium, misuse pathway)
- **htr**: OCR misreads (high/high); indigenous-script under-representation (high/medium, vulnerable group)
- **ner**: misidentification (medium/high, affects data subjects)
- **donut**: layout drift (medium/medium)
- **guardrail**: mis-classification (medium/low)
- **translate**: mistranslation of legally-significant text (high/medium, vulnerable group)

Operators can edit these or add new ones via the admin UI; never delete - the audit trail prefers archive over hard-delete.

## Operator workflow

```bash
# Browse + edit the register
open https://<host>/admin/ai-compliance/risk

# Sign-off a review (writes a receipt to the #693 chain)
# done via the UI "Sign-off" button

# Weekly post-market monitoring digest (cron candidate)
php artisan ai-compliance:risk-monitor

# Skip the workbench notification when nothing is notable
php artisan ai-compliance:risk-monitor --quiet-empty
```

Cron entry (already supported by CronSchedulerService once registered):

```
0 9 * * 1   /usr/bin/php /usr/share/nginx/heratio/artisan ai-compliance:risk-monitor --quiet-empty
```

(Mondays 09:00 SAST; uses the workbench notification spool at `/var/spool/workbench/notifications/`.)

## Article 9 mapping

| Article 9 paragraph | Heratio implementation |
|---|---|
| 9(2)(a) - identify foreseeable risks | Risk register seeded with known risks per service |
| 9(2)(b) - intended use AND foreseeable misuse | `intended_or_misuse` column captures both pathways |
| 9(2)(c) - post-market monitoring data | `RiskMonitorCommand` weekly sweep over #693 inference log |
| 9(2)(d) - adopted measures | `mitigation` column + `residual_risk` after measures |
| 9(7) - testing | Operator review + sign-off; outcomes recorded in #693 chain |
| 9(9) - vulnerable groups | `affected_group` tag escalates severity at review |

## Phase 2 follow-ups (NOT in this release)

- `getKnownRisks()` method on each AI service class so the register stays in sync with code (today the register is operator-curated only)
- Vulnerable-group user/content tagging integration (Art. 9(9)) so risk severity automatically escalates when an inference involves a flagged group
- Risk-mitigation effectiveness metrics from the #693 chain (e.g. measure correction rate per service over time)
- Cross-link to Article 11 (#725) Annex IV - the risk register is part of the technical documentation bundle

## Threat model

Detects:
- Operator skipped or backdated a review (sign-off receipt in #693 chain has a real timestamp)
- Tampering with the risk register history (chain receipts capture the state at each sign-off)

Does not detect:
- Operator simply doesn't add a known risk class. Mitigation: the seeded defaults cover the obvious classes; future Phase 2 `getKnownRisks()` on each service narrows this further.

## Related issues

- #693 Article 12 record-keeping (closed) - this issue's sign-off events write to its chain
- #725 Article 11 technical documentation - consumes this register for Annex IV
- #726 Article 14 human oversight - vulnerable-group escalation is shared
