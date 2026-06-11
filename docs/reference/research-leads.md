# Research Leads: public generative-scholarship feed (North Star #1210)

A slice of the Heratio "North Star" vision (GitHub issue #1210): *AI finds
connections no human spotted*. Research Leads promotes the strongest AI-found
cross-collection connections - the ones the **Discoveries** feature already
surfaces and persists - into a public, curated, browsable feed of scholarly
leads. Each lead is a connection plus a plain-language "why this might matter"
prompt and links to the records it rests on.

Lives entirely in `packages/ahg-semantic-search` (alongside Discoveries,
displaced-heritage, endangered-heritage). Additive: one new sidecar table, no
ALTER of any existing table, no locked path touched, Discoveries untouched.

## What it does

1. **Promote (admin / CLI, read-only over Discoveries).** Reads the persisted
   discovery set in `ahg_scholarship_discovery` (written by
   `ahg:generate-discoveries`), highest confidence first, and promotes each into
   a row in the new `research_lead` table. Idempotent per record (unique key on
   `information_object_id`): a re-run refreshes the lead's grounding in place and
   PRESERVES any curator decision (published / dismissed are never clobbered;
   only the snapshotted evidence + factual prompt are refreshed). Research Leads
   never writes `ahg_scholarship_discovery`.
2. **Curate (admin).** A curator publishes or dismisses each lead. Only
   PUBLISHED leads - whose underlying record is itself published - reach the
   public feed.
3. **Read (public).** The public feed renders published leads only, with their
   verified links, the plain-language prompt, and a confidence band derived from
   the real graph evidence (not the model's self-assessment).

## "Why this might matter" prompt

Every lead carries a factual, graph-grounded prompt built with NO AI (connection
count, the domains the links cross, any second-hop reach). This is the floor the
lead always stands on - gateway up or down.

Optionally, generation can ENRICH that prompt via the AHG gateway. The model is
given only the lead text and the verified evidence and asked for a short,
inviting, plain-language research prompt, with a hard "use nothing else, never
invent" constraint. Enrichment is opt-in (the "Enrich with AI" toggle, or
`--enrich`) and runs ONLY during an explicit admin/CLI generation - never on a
page load.

## AI path (gateway only)

All AI routes through `AhgAiServices\Services\LlmService::complete()`, which goes
through the AHG gateway at `ai.theahg.co.za/ai/v1/...`. No direct inference-node
port is ever used. The call is labelled (`purpose = research-lead-why-it-matters`,
`data_scope = catalogue-graph`). Every lead is stored with `ai_labelled = 1` and
every surface carries a visible "AI-generated, grounded in catalogue links -
verify before citing" notice. If the gateway is down the lead keeps its factual
prompt; nothing throws.

## Where it lives (packages/ahg-semantic-search)

- `src/Services/ResearchLeadService.php` - the only writer of `research_lead`.
  `generate()`, `publicFeed()`, `publicLead()`, `adminList()`, `statusCounts()`,
  `publish()/dismiss()/repend()`, `isPublished()`. Reads Discoveries +
  catalogue (title/slug/status) read-only behind `Schema::hasTable` probes.
- `src/Controllers/ResearchLeadsController.php` - public `index` + `show`
  (published-only, read-only, never calls AI, never 500s).
- `src/Controllers/ResearchLeadAdminController.php` - admin `index`, `generate`
  (explicit POST), `publish`, `dismiss`, `repend`. Full validation.
- `src/Console/Commands/GenerateResearchLeadsCommand.php` -
  `php artisan ahg:generate-research-leads [--limit=25] [--enrich] [--dry-run]`.
- `resources/views/research-leads/{index,show,admin}.blade.php` - Bootstrap 5 +
  central theme (`theme::layouts.1col`), empty-states throughout.
- `database/install_research_lead.sql` - `CREATE TABLE IF NOT EXISTS research_lead`.

## The table: research_lead

One row per promoted lead. `information_object_id` is a SOFT reference (NO
foreign key) so the table never constrains or ALTERs the catalogue.
`source_discovery_id` is a soft reference back to the discovery it came from.
`status` is VARCHAR(32) - never a MySQL ENUM - with values `pending` /
`published` / `dismissed` (extensible via the Dropdown-Manager idiom). `evidence`
is a JSON snapshot of the verified links so a lead is self-contained and citable.

Auto-installed on first boot in `AhgSemanticSearchServiceProvider::boot()` via
`bootResearchLeadTable()`: a single `Schema::hasTable` probe wrapped in one
try/catch (the canonical Heratio package idiom - a missing/locked DB at boot can
never fatal the app; the feed then degrades to its empty-state). Prefers the
shipped SQL; falls back to a Schema builder create.

## Routes (catch-all-safe)

- PUBLIC: `/research-leads` (`research-leads.index`) and `/research-leads/{id}`
  (`research-leads.show`, numeric `{id}`). Bound in the provider's `register()`
  via `callAfterResolving('router')` so they bind BEFORE the single-segment
  `/{slug}` archival-record catch-all in `ahg-information-object-manage` - the
  same proven precedence trick as `/discoveries` and `/at-risk`. (`register()`
  runs for all providers before any `boot()`, so these win the match. See
  `reference_slug_catchall_route_precedence`.)
- ADMIN: `/admin/research-leads`, `.../generate`, `.../{id}/{publish,dismiss,
  repend}` under `['auth','admin']` (prefix `admin/research-leads`). Two-segment+
  so they never collide with the `/{slug}` catch-all.

## Published gate

Mirrors the rest of Heratio: an item is "published" when its row in the `status`
table (`type_id = 158`) carries `status_id = 160`; the catalogue root (`id = 1`)
is never surfaced. The public feed applies the gate to the lead's record before
showing it; pending and dismissed leads are admin-only.

## Constraints honoured

Additive only; no ALTER; one new VARCHAR-backed sidecar table; AI strictly via
the gateway, labelled, never on page-load, never a node port; published-only
public read with admin curate/dismiss; Bootstrap 5 + central theme; empty-states
everywhere; never 500s; Plain Sailing / AGPL headers; international and
jurisdiction-neutral. Discoveries is read read-only and otherwise untouched.
