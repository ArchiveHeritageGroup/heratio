# Repatriation engine - community knowledge contributions (heratio#1207)

Summary: A new slice of the repatriation engine in `packages/ahg-semantic-search`
lets communities contribute KNOWLEDGE about a displaced object / repatriation
claim - oral history, provenance knowledge, corrections, or a pointer to the
source community. Contributions are admin-moderated (pending -> approved /
rejected) and shown publicly only once approved, mirroring the language-revival
glossary and transcription moderation flow. It is additive and read-only over
all existing data except writes to one new table.

## What it adds

- New sidecar table `repatriation_knowledge_contribution` (CREATE TABLE IF NOT
  EXISTS, auto-installed on first boot behind a `Schema::hasTable` probe in one
  try/catch). Columns: `id`, `claim_id` (soft ref to `displaced_heritage_claim.id`,
  no FK), `item_ref` (soft ref to `information_object.id`, no FK),
  `contribution_type` VARCHAR [provenance|oral_history|correction|source_community|other],
  `body` MEDIUMTEXT, `source`, `contributor_name`, `credit_consent` TINYINT,
  `contributed_by`, `moderation_status` VARCHAR [pending|approved|rejected],
  `moderated_by`, `moderated_at`, `created_at`, `updated_at`. VARCHAR not ENUM;
  no foreign keys, no ALTER of any existing table.
- `RepatriationKnowledgeService` - the one write path (`contribute()`, lands
  pending), public reads (`approvedForClaim()` / `approvedForItem()`), and admin
  moderation (`moderationQueue()` / `moderationCounts()` / `moderate()`). It reads
  claim / item context read-only via `RepatriationClaimService::find()`.
- `RepatriationKnowledgeController` - public submit form + POST, admin moderation
  queue + approve/reject. Full validation; every screen has an empty-state and
  never 500s.
- Views `repatriation-knowledge/form.blade.php` (public) and
  `repatriation-knowledge/moderate.blade.php` (admin), Bootstrap 5 + central
  theme.

## How it links from /virtual-return/{id}

`VirtualReturnController::show()` now also fetches `approvedForClaim($claimId)`
(read-only, wrapped) and passes `claimId` + `knowledge` to the existing
`virtual-return/show.blade.php`. The view gained a "Community knowledge" section
that lists approved contributions and a "Share what you know" button linking to
the public submit form. The existing claim / virtual-return behaviour is
unchanged - the new props default defensively so the page is robust if they are
absent.

## Routes (catch-all-safe)

- Public (bound in the provider's `register()` via `callAfterResolving('router')`
  so they win ahead of the single-segment `/{slug}` archival-record catch-all):
  - `GET  /repatriation-knowledge/{claim}` -> `repatriation-knowledge.form`
  - `POST /repatriation-knowledge/{claim}` -> `repatriation-knowledge.contribute`
  - `{claim}` is numeric-only (`[0-9]+`), a two-segment path, so it can never
    shadow a slug.
- Admin (auth + admin, in `routes/web.php` under the `repatriation` prefix):
  - `GET  /repatriation/knowledge` -> `repatriation-knowledge.moderate`
  - `POST /repatriation/knowledge/{id}` -> `repatriation-knowledge.set`

## Sensitive framing

Knowledge belongs to its communities; a contribution is a documented piece of an
open dialogue, NOT a legal determination of origin, ownership or wrongful
removal. Contributors are credited by name ONLY where they explicitly consent
(`credit_consent`); otherwise the contribution appears anonymously. The framing
is non-partisan and jurisdiction-neutral.

## Guarantees

- No ALTER, no foreign keys, no writes to any existing table; the only write
  target is the new `repatriation_knowledge_contribution` table.
- Existing detection register, claim workflow and virtual-return view are not
  broken; the slice reads them read-only.
- All read/write paths are `Schema::hasTable`-guarded and wrapped so a missing
  table degrades to an empty-state, never a 500.
- Any AI would route through the AHG gateway only; this slice uses no AI.

Epic heratio#1207 remains OPEN.
