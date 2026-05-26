# AI Services Phase 1 - Quotas, Cost, Translation Memory, Custom NER

Heratio issue #667 Phase 1 ships five operator-facing controls on top of every gated AI service: per-tenant quotas, a per-call cost ledger, a translation memory cache, an operator-curated NER gazetteer, and a stubbed face-detect driver. All five are wired into the existing AI service call sites; this reference covers the data model, the admin UI, and where each component sits in the request lifecycle.

## Where the gate runs

Five AI services participate in the quota / cost system today:

| Service | Quota gate site | Cost record site |
| --- | --- | --- |
| `llm` (LlmService::complete) | before dispatch | after dispatch |
| `ner` (NerService::extract) | before dispatch | after dispatch |
| `htr` (HtrService::extract) | before dispatch | after dispatch |
| `donut` (DonutService::extract) | before dispatch | after dispatch |
| `translate` (LlmService::translate) | before TM lookup | after dispatch |
| `spellcheck` (LlmService::spellcheck) | before dispatch | after dispatch |
| `face_detect` (NullFaceDetector::detect) | before dispatch | after dispatch |

`QuotaService::consume($service, $tenantId)` throws `QuotaExceededException` when the daily or monthly limit is reached. `CostService::record($service, $modelId, $meta)` writes one `ahg_ai_call_cost` row per dispatch.

## Data model

Five new tables live in `packages/ahg-ai-services/database/install.sql`, auto-installed by the service provider on boot when absent (Schema::hasTable probe + idempotent CREATE TABLE IF NOT EXISTS).

- **ahg_ai_quota** - one row per (tenant_id, service). `daily_limit` / `monthly_limit` of 0 means unlimited. `used_today` rolls at calendar-day change; `used_this_month` rolls at `reset_day` of each month (anchor 1-28). Tenant 0 is the global default; the seven seeded rows ship unlimited.
- **ahg_ai_call_cost** - per-call ledger. `cost_usd` is computed from `ahg_ai_pricing` at insert time; NULL = no pricing row for that model. `request_id` cross-links into the inference receipt chain via the X-Request-Id header.
- **ahg_ai_pricing** - per-model input / output cost per 1k tokens. Local / amortised models ship at 0. Operators update via SQL or future settings UI.
- **ahg_translation_memory** - sha256(source || src_lang || tgt_lang) lookup. On a hit, `hit_count` is bumped and the call site skips the inference dispatch. Provenance distinguishes machine / human / gateway / mzansilm.
- **ahg_ner_custom_entity** - operator gazetteer. Exact label + alias substring pre-pass before the ML model in NerService::extract.

## Admin UI

All four pages live in `packages/ahg-ai-services/resources/views/` and are mounted under `admin/ai/services/...` via the existing `admin/ai` route group.

- **`admin/ai/services/quotas`** - lists every quota row with used / limit / reset day; small upsert form sets a tenant + service quota.
- **`admin/ai/services/cost`** - summary cards (total USD, calls, tokens), per-service table, recent-100 call ledger, pricing reference. Tenant + since filters.
- **`admin/ai/services/translation-memory`** - paginated browse with target-language + substring filters, single-click delete.
- **`admin/ai/services/ner-entities`** - paginated browse + per-row edit modal + add-new form. Aliases are newline-separated, stored as a JSON array.
- **`admin/ai/services/face-detect`** - driver status, enabled flag, health probe. Null driver ships by default.

All five use Bootstrap 5 + bi-* icons and extend `theme::layouts.1col`. Auth middleware is inherited from the parent route group.

## Soft-fail behaviour

Every service in this phase fails soft. If the schema is missing, the facades are unreachable, or the underlying DB call throws, the service logs a warning and returns silently rather than blocking inference. This is intentional - the quota / cost / TM / gazetteer layer is a control-plane convenience, not a hard dependency. Inference still works without it.

The `QuotaExceededException` is the one exception (literally): it is a deliberate signal, must propagate out of `consume()`, and is caught by the calling controller / API layer where it surfaces as a user-visible error.

## Testing

Three smoke-test files live in `packages/ahg-ai-services/tests/Unit/`:

- `QuotaServiceTest.php` - services list, consume() short-circuit + soft-fail, snapshot empty fallback, QuotaExceededException payload.
- `CostServiceTest.php` - record() soft-fail, totals() zeroes when unreachable, lookupCost() null on missing pricing.
- `NerGazetteerServiceTest.php` - scan() empty fallbacks, merge() dedup + case-insensitive + customs bucket.

All three set up a minimal `Container` + `Log` facade in `setUp()` so the inner `Log::warning()` calls inside Throwable catches resolve cleanly without a full Laravel kernel boot.

## Files of record

- `packages/ahg-ai-services/src/Services/QuotaService.php`
- `packages/ahg-ai-services/src/Services/CostService.php`
- `packages/ahg-ai-services/src/Services/NerGazetteerService.php`
- `packages/ahg-ai-services/src/Services/TranslationMemoryService.php`
- `packages/ahg-ai-services/src/Services/NullFaceDetector.php`
- `packages/ahg-ai-services/src/Exceptions/QuotaExceededException.php`
- `packages/ahg-ai-services/src/Controllers/PhaseOneController.php`
- `packages/ahg-ai-services/database/install.sql` (sections 7 + face_detect)
- `packages/ahg-ai-services/resources/views/{quotas,costs,translation-memory,ner-custom,face-detect}.blade.php`
- `packages/ahg-ai-services/routes/web.php` (Phase 1 admin routes block)
