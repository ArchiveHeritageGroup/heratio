# AI usage transparency report - technical reference

Summary: an admin, read-only AI-usage aggregate at `/admin/ai-usage` in `packages/ahg-reports`, mirroring the Trust & Transparency console and Collection data-quality pattern (Schema::hasTable-guarded aggregate COUNTs, Route::has-gated links, `theme::layouts.2col`). Shipped in the same `ahg-reports` release wave as the trust-console and data-quality slices. Reads the AI inference provenance log; writes nothing.

## What it reads

Two existing provenance tables (read-only, no ALTER, no new table):

- `ahg_ai_inference` - the inference log, one row per AI inference applied to a record. DESCRIBE-verified columns used:
  - `service_name` - the inference type / task (NER, SUMMARIZE, HTR, TRANSLATION, DONUT, LLM, ...) - the type breakdown.
  - `model_name` - the model that produced it - the model breakdown.
  - `endpoint` - the gateway / endpoint URL (nullable) - reduced to host as a presentational gateway hint per service type.
  - `target_entity_type` + `target_entity_id` - the touched record - `COUNT(DISTINCT target_entity_type, target_entity_id)`.
  - `created_at` - drives the per-month trend.
  - `id` - the FK that `ahg_ai_override.inference_id` references.
- `ahg_ai_override` - the human review / correction log. Column used: `inference_id` (FK to `ahg_ai_inference.id`) - the reviewed share.

## Metrics (each a single grouped/aggregate COUNT)

| Metric | Query shape |
|---|---|
| Inferences logged (total) | `COUNT(*)` over `ahg_ai_inference` (the denominator) |
| Records touched | `COUNT(DISTINCT target_entity_type, target_entity_id)` |
| By inference type | `GROUP BY service_name` `COUNT(*)`, share of total, host hint from `endpoint` |
| By model | `GROUP BY model_name` `COUNT(*)`, share of total |
| Human oversight (reviewed share) | `COUNT(*)` of inference rows with a matching `ahg_ai_override.inference_id` (WHERE EXISTS), as a share of total |
| Over time | `GROUP BY DATE_FORMAT(created_at,'%Y-%m')` `COUNT(*)`, trailing 12 months back-filled with zeros, rendered as CSS bars (no charting library) |

Blank/NULL group keys fold into a single "(unspecified)" bucket. The human-oversight metric is framed as accountability (how much AI output a person reviewed), never as AI accuracy.

## Properties

- Read-only: SELECT/aggregate only; no writes, no ALTER, no new table, no AI calls.
- Bounded: grouped aggregate COUNTs only; no per-row PHP scan of the log.
- Resilient: every metric Schema::hasTable-guarded + try/catch; missing table or no AI logged degrades to a calm "No AI activity recorded" empty state, never a 500.
- Admin-gated route; two-segment `/admin/ai-usage` (catch-all-safe against the `/{slug}` archival-record route).
- Links (back to Reports, across to the Trust & Transparency console) are Route::has-gated so no dead links render.
- International: no jurisdiction-specific framing.

## Files

- `packages/ahg-reports/src/Controllers/AiUsageController.php`
- `packages/ahg-reports/resources/views/ai-usage/index.blade.php`
- route in `packages/ahg-reports/routes/web.php` (`reports.ai-usage`)
