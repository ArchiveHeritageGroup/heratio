---
title: ADR-0001 — AtoM base schema is read-only; performance work uses AHG sidecar tables
status: Accepted
date: 2026-04-28
author: Johan Pieterse / The Archive and Heritage Group (Pty) Ltd
license: AGPL-3.0-or-later
---

# ADR-0001 — AtoM base schema is read-only; performance work uses AHG sidecar tables

## Status

Accepted — 2026-04-28. Supersedes any ad-hoc decision to alter `information_object`,
`actor`, `term`, `object_term_relation`, or any other Qubit table for performance
reasons.

## Context

Heratio inherits the AtoM (Qubit) relational schema for archival description. At
production scale (atom DB: 444k information objects, 5.0M `object_term_relation`
rows, 556k terms) several built-in queries — most visibly the GLAM browse subject
facet — exceed acceptable response times and have repeatedly caused MySQL CPU
saturation incidents (most recently 2026-04-28, 12 stuck COUNT(DISTINCT) queries
running for >25h, server load 47+).

There is a recurring temptation to "just add a column" to `information_object` or
"just add an index" to `object_term_relation`. We are choosing not to do that.

Reasons:

1. **Migration parity.** Heratio is a destination for many AtoM source instances.
   Every divergence from base Qubit makes future ingest, comparison against the
   AtoM reference at `/usr/share/nginx/archive`, and round-trip export harder.
2. **Upstream compatibility.** Although Heratio is a fork in spirit, base Qubit
   tables are still the lingua franca for tooling, CSV import templates, ES
   document shape, and third-party AtoM plugins that may be ported.
3. **Audit and reproducibility.** A clean separation between "AtoM said" and
   "Heratio derived" makes provenance arguments to clients (and POPIA / GDPR /
   GRAP-103 audit) straightforward.
4. **Multi-tenant DBs.** A single server hosts archive / atom / dam / heratio /
   psis. Schema drift on any one of them turns every ALTER into a four-database
   coordination problem.

## Decision

**AtoM base tables are read-only.** No `ALTER TABLE`, no new column, no new
index, no trigger. This applies to:

- `information_object`, `information_object_i18n`
- `actor`, `actor_i18n`
- `term`, `term_i18n`, `taxonomy`, `taxonomy_i18n`
- `object_term_relation`, `relation`, `relation_i18n`
- `repository`, `event`, `slug`, `status`, `digital_object`, `physical_object`,
  `property`, `note`, `contact_information`, `other_name`
- Any other table created by the upstream Qubit migrations.

Performance work that needs denormalised, materialised, cached, or precomputed
data lives in **AHG sidecar tables** prefixed `ahg_`, owned by the relevant
package, with their own install SQL and service provider.

Three sidecar patterns are sanctioned, in order of increasing cost and
specificity. Pick the lightest one that solves your problem.

### Pattern A — Eventually-consistent facet/aggregate cache

Use when: the read is an aggregate (count, distinct, group-by) over a base
table, and a few minutes of staleness is acceptable.

Shape:

- Table: `display_facet_cache` (already exists; owned by `ahg-display`).
  Generic `(facet, value, label, count, repository_id, updated_at)` rows.
- Read path: GLAM browse and facet endpoints **only ever read from the cache**.
  No fall-through to live aggregation. If the cache is empty for a slice the
  facet is simply absent from that page until the next refresh.
- Write path: `ahg:refresh-facet-cache` artisan command, scheduled per
  database via `/etc/cron.d/ahg-facet-cache` under an outer `flock -n` so a
  long-running rebuild can never overlap itself.
- Scope refresh by `--repository=N` so one slow repo can't starve the others
  and one stuck job can't poison the next tick.

Tradeoff: facet counts lag reality by up to one refresh cycle. Acceptable for
browse facets; not acceptable for "how many records am I about to bulk-edit?"
style precise counts.

### Pattern B — Per-repository chunked refresh of Pattern A

Use when: Pattern A's full-corpus refresh exceeds MySQL `max_execution_time`
(currently 300s server-wide) or runs long enough to overlap the next cron tick.

Shape:

- Same cache table as Pattern A.
- Refresher iterates repositories in `repository_id` order, one repo per job.
- Each job is short enough to finish well under 300s on the largest repo.
- Failure of one repo's job does not block the others.
- Optional: queue worker instead of cron, so refreshes can be triggered by
  write events (e.g. on save of an information object, enqueue a refresh of
  that record's repository's facets).

Tradeoff: more moving parts (queue / chunked iterator) but isolates blast
radius per repository and removes the single 5M-row scan entirely.

### Pattern C — Sidecar denormalisation table for one hot collection

Use when: a single repository or collection is so large that even cached
aggregates aren't fast enough to compute, **or** the read needed is per-record
(not aggregate) — e.g. "give me every IO's subject term IDs in one query
without joining `object_term_relation`."

Shape:

- New table `ahg_<feature>_denorm`, e.g.
  `ahg_io_facet_denorm(io_id PK, repository_id, subject_ids, place_ids,
  genre_ids, name_ids, updated_at)`.
- Populated **only for the repository or repositories that need it.** Empty
  for everyone else.
- Reads check the sidecar first, fall through to the base-table join if the
  row is absent. This is the only pattern where fall-through is permitted,
  because absence is meaningful (this repo isn't enrolled).
- Refresh strategy is documented in the package's technical manual — either
  on-save event handler, nightly rebuild scoped to the enrolled repository
  IDs, or both.

Tradeoff: highest maintenance cost; only justified when Patterns A and B have
been measured and shown to be insufficient for that specific collection.

### What is NOT permitted

- Adding a column to `information_object` even "just for one repository."
  Every base-table ALTER is a global change.
- Adding an index to a Qubit table to make a query fast. If the query needs a
  different index, materialise the query result in a sidecar.
- Triggers on Qubit tables. Use Eloquent model events on the AHG side, or a
  scheduled refresh.
- Storing AHG-specific flags (e.g. `is_ai_processed`, `ahg_visibility`) inline
  on Qubit tables. They go in a sidecar keyed by `(io_id)` or via the
  existing `property` table if it must be in-band.

## Choosing between the three patterns

Decision order, every time:

1. Can this read tolerate minutes of staleness? → **Pattern A**.
2. Does Pattern A's refresh blow past 300s or overlap itself? → **Pattern B**.
3. Is the read per-record (not aggregate), or is one repository so large that
   even chunked aggregate refresh is too slow? → **Pattern C**, scoped to
   that repository only.

If the answer to all three is "no," the slowness is probably an indexing or
query-shape problem on the AHG side, not a base-schema problem. Fix it there.

## Consequences

Positive:

- Base AtoM schema stays diff-free against the reference instance — migrations,
  exports, audits, and CSV templates remain interchangeable.
- Sidecar tables are versioned, owned, and shipped per-package with
  `database/install.sql`, so a fresh install is reproducible.
- Performance failures are isolated: a stuck refresh on one repository or one
  feature does not threaten the rest of the platform.
- Per-database installs (archive / atom / dam / heratio) can opt into different
  sidecars without coordination.

Negative:

- Two writes for hot data — one to the Qubit table, one to the sidecar. Must
  be reasoned about for consistency.
- Sidecar staleness is now a category of bug. Every sidecar needs a documented
  refresh path and a way to force-rebuild from authoritative base tables.
- Reads sometimes need a small fall-through layer (Pattern C) which adds code
  complexity vs. a single SELECT.

## Worked example — GLAM browse subject facet (2026-04-28)

- Symptom: 12 concurrent `COUNT(DISTINCT io.id) … JOIN object_term_relation`
  queries on `atom`, each running for hours, server load 47+.
- Root cause: live aggregation over a 5M-row join, no cache enforcement on
  the read path, cron refresh covered only the `archive` DB.
- Wrong fix (rejected): add a denormalised `subject_ids` column to
  `information_object` — modifies AtoM base schema.
- Applied fix: Pattern A enforced (browse reads only from
  `display_facet_cache`) plus Pattern B (per-repository, flock-guarded
  refresh via `ahg:refresh-facet-cache --repository=N`, extended to all four
  application DBs in `/etc/cron.d/ahg-facet-cache`).
- Reserved: Pattern C — `ahg_io_facet_denorm` scoped to the largest single
  repository — kept on the shelf for when measurement shows Patterns A+B are
  insufficient for that one collection.

## References

- `packages/ahg-display/` — owns `display_facet_cache` and the GLAM browse.
- `packages/ahg-core/src/Commands/DisplayReindexCommand.php` —
  full-rebuild path; per-facet rebuilders.
- `packages/ahg-core/src/Commands/RefreshFacetCacheCommand.php` —
  per-database, per-repository refresh command.
- `/etc/cron.d/ahg-facet-cache` — outer flock + multi-DB iteration.
- `/etc/mysql/mysql.conf.d/heratio-overrides.cnf` —
  `max_execution_time = 300000` server-wide ceiling.
- AtoM reference instance: `/usr/share/nginx/archive`.
