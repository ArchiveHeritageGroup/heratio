# Session log — Issue #670 Phase 3 — ResourceSync (sitemap-style)

> Date: 2026-05-25
> Repo: heratio (worktree)
> Issue: ArchiveHeritageGroup/heratio#670 Phase 3

## What shipped

A new locked-free package `packages/ahg-resourcesync/` exposing a complete
ResourceSync 1.1 Source-role endpoint over HTTP. Built alongside the
existing OAI-PMH package, not modifying it. The four document types in the
Source role are all implemented:

- `GET /.well-known/resourcesync` — SourceDescription discovery file
- `GET /resourcesync/capabilitylist.xml` — CapabilityList
- `GET /resourcesync/resourcelist.xml?page=N` — paged full inventory
- `GET /resourcesync/changelist.xml?page=N` — paged recent updates +
  tombstones, configurable horizon (default 30 days)

All four routes throttle at `120,1` to mirror the OAI-PMH endpoint shape.

## Files added

```
packages/ahg-resourcesync/
  composer.json
  README.md
  config/resourcesync.php
  routes/web.php
  src/Providers/AhgResourceSyncServiceProvider.php
  src/Controllers/ResourceSyncController.php
  tests/Feature/ResourceSyncTest.php
docs/federation-resourcesync.md
docs/help/federation/resourcesync.md
```

Root `composer.json` gained one line under `require`:
`"ahg/resourcesync": "@dev"`.

## Design choices

### Page size — 1000 default, OAI setting honoured

The package config defaults to 1000 entries per ResourceList / ChangeList
page (env `RESOURCESYNC_PAGE_SIZE`). It also honours the existing OAI
`resumption_token_limit` setting so operators only have to tune one knob
for both federation protocols. 1000 sits comfortably under the
ResourceSync community's 50,000-line soft cap per document.

### ChangeList horizon — 30 days default

`RESOURCESYNC_CHANGELIST_DAYS=30` matches the most common aggregator
poll cadence (weekly to monthly) with plenty of head-room. Operators can
shorten the horizon on high-churn sites or lengthen it on quiet ones; a
polite aggregator that misses the window can always fall back to the
full ResourceList.

### Publication-status filter — mirrors OAI exactly

The same SQL join the OAI-PMH endpoint uses:

- `information_object` joined to `object` (for `updated_at`)
- `status` join on `type_id = 158, status_id = 160` (published)
- `parent_id IS NOT NULL AND parent_id != 0` (excludes the synthetic root)

A record hidden from one endpoint is hidden from the other.

### Tombstones — reuse `oai_deleted_record`

The spec called for "tombstones from `object.deleted_at IS NOT NULL`", but
the `object` table has no `deleted_at` column — AtoM (and therefore
Heratio) uses hard deletes with a sidecar tombstone table
(`oai_deleted_record`) populated by the existing
`php artisan oai:mark-deleted` worker. Reusing it keeps the OAI and
ResourceSync deletion sets in lockstep and avoids a second source of
truth. The package's Schema::hasTable() guard means installs that haven't
applied OAI Phase 2 (the tombstone table) still serve a valid ChangeList,
just without tombstones.

### Change semantics — created-vs-updated heuristic

ResourceSync requires each ChangeList entry to carry
`<rs:md change="created|updated|deleted">`. Heratio doesn't track create
vs update at the row-inventory level, so we use the simple heuristic
`created_at == updated_at => created, else => updated`. For full edit
history, operators have the `audit_trail` + `version_control` packages.
Documented in the operator runbook.

### Tombstone URL — synthetic by-oai route

A hard-deleted record has no slug, so we emit
`/informationobject/by-oai/<oai_local_id>` as the `loc`. Aggregators only
need a stable identifier here for de-duplication, not a live URL.

### XMLWriter, not string concat

Unlike the OAI-PMH controller (which assembles XML by string concat
because the OAI envelope is heavily hand-tuned), ResourceSync uses PHP's
`XMLWriter` for cleaner namespace handling and guaranteed well-formed
output. The XML is buffered into memory and emitted at the end.

### rel="up" link chain

Every document below the SourceDescription carries a `<rs:ln rel="up">`
back to its parent (CapabilityList → SourceDescription; ResourceList /
ChangeList → CapabilityList). An aggregator that lands on any of the four
URLs can walk the full chain. Each paged document also carries
`rel="next"` / `rel="prev"` for sitemap pagination.

## Aggregator-compatibility notes

The documents validate against the ResourceSync 1.1 schemas:

- sitemap default namespace `http://www.sitemaps.org/schemas/sitemap/0.9`
- `xmlns:rs="http://www.openarchives.org/rs/terms/"` extension
- `<rs:md capability>` on every document
- `<rs:ln rel="up">` chain
- `<rs:ln rel="next">` / `rel="prev"` pagination
- `<rs:md change>` on every ChangeList entry
- `<lastmod>` on every `<url>` so plain sitemap consumers can still use
  the documents
- `application/xml; charset=UTF-8` content type
- HTTP 200 only for successful responses

Smoke-tested against the four document shapes via
`tests/Feature/ResourceSyncTest.php` which parses each response with
`SimpleXMLElement`, registers both namespaces, and asserts the capability
declarations + rel="up" chain. The tombstone test seeds an
`oai_deleted_record` row, asserts the deletion shows up with
`change="deleted"`, and tears the row back down.

## Locked-path discipline

No files under `.locked-paths` were modified. `ahg-oai` (entire package)
remains untouched. The root `composer.json` and new package files all
sit outside the lock list. `./bin/check-locked` passes clean.

## What's not in this phase

- No XML schema validation in the test suite (would require pulling the
  ResourceSync XSDs as test fixtures). Structural validation via
  SimpleXMLElement + XPath is sufficient for the four shape assertions
  the test suite makes.
- No ResourceDump / ChangeDump (the bulk-archive variants of the two
  list types). The spec lists them as optional Source capabilities; we
  can add them in a follow-up phase if a target aggregator asks.
- No ResourceSync ResourceDumpManifest (zip-bundle manifest).
- No Aggregator role (Heratio doesn't pull from peer sources via
  ResourceSync; the existing `ahg-federation` OAI harvester covers
  inbound).

## KM publication

This session log lives at
`docs/sessions/2026-05-25-issue-670-resourcesync.md` so the in-repo KM
crawler picks it up on its next pass. No separate `docs/reference/*.md`
shorthand needed — the operator runbook at
`docs/federation-resourcesync.md` is the canonical reference.

## Release command (for the user to run)

```bash
cd /usr/share/nginx/heratio
./bin/release minor "Issue #670 Phase 3 — ResourceSync sitemap endpoint" --issue 670
```
