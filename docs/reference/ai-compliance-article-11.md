# AI Compliance - EU AI Act Article 11 / Annex IV technical documentation

Implementation reference for the per-service Annex IV technical-documentation generator in Heratio. Closes issue #725 (Phase 1).

## What it is

Article 11 of Regulation (EU) 2024/1689 ("EU AI Act") requires the provider of a high-risk AI system to draw up technical documentation **before** placing the system on the market and to keep it up to date. Annex IV enumerates the nine sections that must be covered. Documentation must be retained for **at least 10 years** from the date of last placing on the market.

Enforcement deadline: **2026-08-02**.

Heratio's implementation produces one Markdown bundle per AI service. Each bundle is fingerprinted into the tamper-evident inference chain (Article 12, issue #693) so the existence and content of every regulator-facing document is independently verifiable.

## Architecture

```
+------------------------------+        +-----------------------------+
|  /admin/ai-compliance/       |        |  storage/ai-compliance/     |
|    models/                   |        |    annex-iv/                |
|    documentation/            |        |    <service>-<date>.md      |
+-------------+----------------+        +--------------+--------------+
              |                                        ^
              | reads/edits                            | writes
              v                                        |
+------------------------------+        +-----------------------------+
|  ai_model_registry table     |        |  AnnexIvCommand             |
|  (per-service model card)    +------->+  php artisan ai-compliance: |
|                              |  pulls |   annex-iv                  |
+------------------------------+        +-------------+---------------+
                                                      |
                                                      | fingerprints
                                                      v
                                       +-----------------------------+
                                       |  ai_inference_log           |
                                       |  (Article 12 chain - #693)  |
                                       +-----------------------------+
```

## Components

| Component | Path |
| --- | --- |
| Model registry table | `packages/ahg-ai-compliance/database/install-model-registry.sql` |
| Eloquent model | `packages/ahg-ai-compliance/src/Models/AiModelRegistry.php` |
| Artisan command | `packages/ahg-ai-compliance/src/Console/Commands/AnnexIvCommand.php` |
| EU Declaration template | `packages/ahg-ai-compliance/resources/templates/eu-declaration-of-conformity.md.template` |
| Admin controllers | `packages/ahg-ai-compliance/src/Controllers/ModelRegistryController.php`, `Annex4Controller.php` |
| Admin views | `packages/ahg-ai-compliance/resources/views/models/`, `documentation/` |
| Output bundles | `storage/ai-compliance/annex-iv/<service>-<YYYY-MM-DD>.md` |

## Services covered

Six services are pre-seeded by the install SQL (INSERT IGNORE so re-runs are safe):

| Service | Model |
| --- | --- |
| `llm` | Mistral 7B Instruct v0.2 (via AHG gateway) |
| `htr` | Kraken handwritten-text-recognition (historic EN/LA/AF) |
| `ner` | spaCy `en-core-web-trf` 3.7 |
| `donut` | Donut document understanding (CORD v2 fine-tune) |
| `guardrail` | Heratio rule-based content-policy gate |
| `translate` | NLLB-200 distilled 600M + Mistral 7B post-edit (MzansiLM) |

Each row carries `intended_purpose`, `training_data_summary`, `known_limits`, and `accuracy_metrics_json`. Operators edit these via `/admin/ai-compliance/models` at any time.

## Workflow

### One-shot generation

```
php artisan ai-compliance:annex-iv
# writes one bundle per known service to storage/ai-compliance/annex-iv/

php artisan ai-compliance:annex-iv --service=llm
# restrict to a single service

php artisan ai-compliance:annex-iv --out=/tmp/regulator-export
# override output directory
```

### Admin UI

- `/admin/ai-compliance/models` - CRUD over `ai_model_registry`. Add a new row when you deploy a new model version; set `retired_at` on the old row (do not delete - keep the lifecycle history for Annex IV section 6).
- `/admin/ai-compliance/documentation` - lists generated bundles grouped by service, with a "Generate now" button per service.

### Receipt chain entry

Every bundle write calls `InferenceLogger::log()` with `service = 'annex-iv'`. The output fingerprint is the SHA-256 of the bundle contents. A regulator can demand the on-disk bundle, hash it, and look up the matching row in `ai_inference_log`; tampering with either copy is detectable.

## What lands in each Annex IV bundle

1. **EU Declaration of Conformity** (Annex V) - prepended to every bundle, substituted from the template.
2. **General description** - service, model, version, gateway endpoint, provider, intended purpose, intended users, interactions with other systems.
3. **System elements and development process** - methods, design choices, architecture, computational resources, data requirements (with cross-link to Article 10).
4. **Monitoring, functioning and control** - capabilities, known limits, accuracy metrics (free-form JSON), live operational telemetry pulled from `ai_inference_log`.
5. **Performance metrics and foreseeable risks** - cross-cutting mitigations + per-service limits.
6. **Risk management** - rows from `ai_risk_register` (sibling issue #724 / Article 9) where present.
7. **Lifecycle changes** - full lifecycle table from `ai_model_registry`.
8. **Harmonised standards applied** - ISO/IEC 42001, 23894; NIST AI RMF as guidance; tamper-evident receipts as alternative solution.
9. **EU Declaration of conformity** - cross-reference to the prepended block.
10. **Post-market monitoring plan** - inference logging + quarterly review + user feedback + annual model review + this Annex IV regeneration cycle.

## 10-year retention

Bundles in `storage/ai-compliance/annex-iv/` are **never auto-pruned**. The `ai-compliance:prune` command (Article 12) only nulls `payload_json` columns in `ai_inference_log` past the receipt retention window; it does not touch Annex IV bundles. Article 11(3) requires regulators to be able to obtain documentation for 10 years from date of last placing on the market.

If the storage volume hosting `storage/ai-compliance/` is rotated, operators must copy the entire `annex-iv/` directory to the new volume. Document this in the operator runbook.

## Phase 2 follow-ups (deferred)

- **PDF emission** - the issue body mentions PDF + Markdown; Phase 1 ships Markdown only. PDF rendering will hook into the existing `ahg-reports` PDF pipeline.
- **Post-market monitoring loop** - section 9 lists the plan; automated anomaly detection on `ai_inference_log` and an archivist-override capture path are not yet built.
- **Annual regeneration cron** - currently operator-triggered. A scheduled artisan run on each anniversary of deployment is the obvious next step.
- **Per-collection accuracy metrics** - `accuracy_metrics_json` accepts free-form JSON today; structured per-collection back-fill from held-out test sets is future work.

## Cross-references

- Article 9 (risk management) - sibling issue #724.
- Article 10 (data governance) - covered indirectly via `ai_model_registry.training_data_summary`; standalone issue not yet open.
- Article 12 (logging) - issue #693, shipped in v1.74.0+. The receipt chain that fingerprints every Annex IV bundle.
- Article 14 (human oversight) - sibling issue (TBD); covered in narrative form in each bundle's section 3.
