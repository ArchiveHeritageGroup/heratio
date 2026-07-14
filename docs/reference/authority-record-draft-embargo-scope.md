# Authority-record draft/embargo + AtoM CVE hardening - design scope

Date: 2026-07-14
Owner: Johan Pieterse
Status: Scoped (approved), not yet implemented

## Background

An AtoM advisory (versions 2.5-2.10) describes an access-control gap where
unauthenticated users could access limited user-account metadata (usernames,
email addresses, user role) and the title of draft archival descriptions. The
AtoM fix is a `security.yml` patch.

Heratio is a Laravel rewrite and has **no `security.yml`** - so the AtoM patch is
not applicable to our stack. What matters is whether we share the underlying
*vulnerability* on our AtoM-compatible surfaces, and (separately) the long-standing
AtoM gap that authority records have no publish/draft flag at all. This is a
recurring GDPR/POPIA ask for the living-persons use case, and a clean differentiator.

Key implementation fact from recon: Heratio already stores publication status in the
generic `status` table (`object_id`, `type_id = 158`, `status_id` 159 = draft /
160 = published) - that is how information objects filter for guests in
`DisplayController::applyFilters`. Authority records can reuse the exact same
mechanism instead of a bolt-on.

## Part A - CVE verify + harden (ships first, independently)

| Vector | Status from recon | Action |
|---|---|---|
| Draft **description** titles | Mitigated for IO display (`status` type_id 158 / status_id 160 gate for guests) | Verify every surface filters, not just display: `/actor/autocomplete`, `/actor/browse`, IO browse/search/autocomplete, `apiGraphData`, `apiEacExport`, RiC `_ric-entities-panel`, related-entity links, any OAI/API |
| **User metadata** (username/email/role) unauth | Not yet verified - highest-risk item | Audit public routes for user data: `/actor/autocomplete`, "maintained by"/maintainer on actor records, `ahg-audit-trail`, NER "created by", feedback/clipboard author, any `/user*` or `/api` returning `username`/`email`/`role_id` without `auth`/`acl` middleware |

Deliverable: a findings table (exposed / not) + fixes for any real leak (add
`auth` / scope filter) + a regression test (guest fetches endpoint -> asserts no
draft title / no email). Greens independently of the feature.

## Part B - Draft + embargo for authority records

Data model (reuse the IO pattern):
- Publication state -> existing `status` table: `object_id = actor.id`,
  `type_id = 158`, `status_id` 159 (draft) / 160 (published). No new actor column
  for the flag.
- Embargo -> new `actor.embargo_until` (nullable date) via a migration in
  `ahg-actor-manage` (must be registered with `loadMigrationsFrom`).
- Visibility rule (guests): `status = 160 AND (embargo_until IS NULL OR
  embargo_until <= today)`. ACL-read users bypass entirely.

Enforcement points (every surface an actor can leak through):
1. `ActorController::browse` - guest status/embargo filter
2. `ActorController::autocomplete` - same filter (public route)
3. `ActorController::show` - guest hitting a draft/embargoed actor -> 404
4. `apiGraphData` / `apiEacExport` - suppress for guests
5. Related-entity links on IO show + RiC `_ric-entities-panel`
6. Search (semantic-search / display browse actor results)
7. A shared `ActorVisibility` scope/helper (in `ahg-acl` or `AhgCore`) so all
   surfaces call one thing - avoids copy-paste drift.

UI: publish/draft toggle + embargo-date field on the ISAAR edit form (Control
area); a "Draft" / "Embargoed until X" badge on the show page for editors.

Tests: guest vs editor visibility across all surfaces; embargo date boundary
(today / past / future).

## Sequencing & effort

- Part A (audit + hardening): ~half-day, ships as its own release, resolves the
  actual CVE question.
- Part B (feature): ~1.5-2 days; the shared visibility helper is the linchpin -
  build it first, then wire the surfaces.

## Related

- Publication-status pattern: `packages/ahg-display/src/Controllers/DisplayController.php`
  (`applyFilters`, status type_id 158 / status_id 160).
- Package migrations must use `loadMigrationsFrom` or they silently do not run.
- Precedent sensitivity flag already on `actor`: `icip_sensitivity`.
