> Heratio Help Center article. Category: AI Services.

# AI Quotas, Cost Tracking, Translation Memory, and Custom NER

**Issue:** #667 Phase 1
**Category:** AI Services / Administration
**Audience:** Administrators

---

## What this is

Four operator-facing controls that sit on top of every gated AI service in Heratio:

1. **Quotas** - cap daily and monthly call volume per tenant, per service.
2. **Cost dashboard** - per-tenant per-service inference cost in USD, with a recent-100 call ledger.
3. **Translation memory** - cached translations indexed by source hash. A cache hit skips the inference dispatch entirely.
4. **Custom NER entities** - operator-curated gazetteer that runs as a pre-pass before the ML model.

A fifth surface, a face-detect driver status page, is included for parity but currently bound to a null driver.

## Where to find it

| Page | URL | Purpose |
| --- | --- | --- |
| Quotas | `/admin/ai/services/quotas` | List + edit per-tenant per-service caps |
| Cost dashboard | `/admin/ai/services/cost` | Per-service cost in USD; recent-100 call ledger |
| Translation memory | `/admin/ai/services/translation-memory` | Browse cached translations; delete stale entries |
| Custom NER entities | `/admin/ai/services/ner-entities` | CRUD on the operator gazetteer |
| Face detect | `/admin/ai/services/face-detect` | Driver status + health probe |

All five pages require admin login.

## Setting a quota

On `/admin/ai/services/quotas`:

1. Pick a tenant ID (use `0` to set the global default that applies to every tenant without an explicit row).
2. Pick a service: `llm`, `ner`, `htr`, `donut`, `translate`, `spellcheck`, or `face_detect`.
3. Enter a daily limit and a monthly limit. `0` means unlimited.
4. Pick a reset day of the month (1-28) for the monthly window. Day 1 = calendar month.
5. Save. The next inference call against that tenant + service will be counted against the new limit.

When a limit is hit, the AI service throws `QuotaExceededException` rather than dispatching to the model. The user sees a clear error; nothing silently drops.

## Reading the cost dashboard

On `/admin/ai/services/cost`:

- The three summary cards show total USD spent, total calls, and total tokens (in + out) over the selected window.
- The **By service** table breaks the totals down per service.
- The **Recent calls** table shows the 100 most recent dispatches with model, tokens, duration, cost, and request ID.
- The **Model pricing reference** table at the bottom is the source of truth for the per-call USD figure.

Filter by tenant ID and by a "since" timestamp using the form at the top.

## Translation memory

Every translation dispatch is keyed by sha256(source_text + source_lang + target_lang). When the same source comes back, the cached target text is returned and `hit_count` is incremented. The TM page lets you:

- Filter by target language or substring.
- See provenance (machine, human, gateway, mzansilm), confidence, hit count, last used.
- Delete a stale or wrong entry, which forces a fresh dispatch the next time that source is translated.

## Custom NER entities

NER's ML extractor is good at common labels but blind to domain-specific ones - project codenames, micro-locations, in-house acronyms. The gazetteer page lets you list each entity once and have NerService find it deterministically on every extraction.

- **Type** - the bucket the label belongs to. Use `person`, `organization`, `place`, `date`, or anything else (custom types land in a `customs` bucket).
- **Label** - the canonical name.
- **Aliases** - one per line. Each is matched case-insensitively as a substring.
- **Target URI** - optional link to an authority record (Wikidata, VIAF, Geonames, etc.).
- **Active** - off = no longer in the pre-pass.

## What happens if a service is broken

Everything in this phase fails soft. If the database is unreachable or the schema is missing, the quota gate logs a warning and lets the call through; the cost ledger silently skips logging; the TM lookup returns null. Inference still works. The only exception that propagates is `QuotaExceededException` itself, which is the deliberate signal that a limit has been hit.

## Related

- Reference: `/docs/reference/ai-services-phase-1-quotas-cost-tm-ner.md`
- GitHub issue: ArchiveHeritageGroup/heratio#667
- Source: `packages/ahg-ai-services/src/Services/{QuotaService,CostService,NerGazetteerService,TranslationMemoryService}.php`
