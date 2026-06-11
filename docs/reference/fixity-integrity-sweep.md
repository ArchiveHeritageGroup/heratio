# Fixity / integrity verification (ahg-core)

Heratio's fixity slice (heratio#1244) makes the NDSA "Integrity" maturity area
actionable: a read-only coverage report plus a bounded, resilient verification
sweep that re-hashes digital objects and compares them to their stored checksum
baseline. It lives entirely in the non-locked `ahg-core` package and is additive
(no existing table is altered).

## Components

- **Table `core_fixity_check_log`** — the append-only fixity log. Columns:
  `id`, `digital_object_id`, `expected_checksum`, `expected_algo`,
  `computed_checksum`, `result` VARCHAR (match | mismatch | missing_file |
  no_baseline | skipped_oversize | error), `byte_size`, `detail`, `checked_at`.
  Created from `packages/ahg-core/database/install_fixity_check_log.sql` via a
  guarded `Schema::hasTable() + DB::unprepared()` block in
  `AhgCoreServiceProvider::boot()` (same pattern as the other ahg-core
  auto-install blocks, single outer try/catch per the CI sqlite-fallback rule).
  `result` is a VARCHAR, never an ENUM.

- **`AhgCore\Services\FixityService`** — read-only over `digital_object`; the
  only writes it performs anywhere are INSERTs into `core_fixity_check_log`.
  - `coverage()` — cheap aggregate: total local objects, how many carry a
    checksum + algorithm baseline, how many have never been verified, the
    algorithm breakdown, a "last sweep" summary, the latest-result-per-object
    roll-up, and the most recent individual checks. Never throws; a missing
    table yields an honest empty report.
  - `verifyBatch($limit)` — verifies a bounded batch (default 100, hard-capped
    at `MAX_LIMIT = 1000`), preferring never-verified objects first. Logs one
    row per object.
  - `verifyOne($do)` — pure; computes a result without writing. Resilient: a
    missing file → `missing_file`, an unreadable/over-cap/unsupported-algo file
    → `error` / `skipped_oversize`, never an exception.
  - `resolveFile($do)` — resolves the on-disk path from
    `config('heratio.uploads_path')` (falling back to `storage_path`), never a
    hardcoded path. It tries the known storage conventions (path-as-directory +
    `name`; path with a leading `/uploads/` stripped; path-as-full-file) and
    returns the FIRST candidate that exists. Every candidate is
    **traversal-guarded**: the resolved `realpath()` must sit inside the storage
    root, so a crafted `digital_object.path` containing `../` resolves to null
    rather than escaping the root.
  - Size cap: files above `MAX_BYTES` (default 4 GiB, tunable via
    `config('heratio.fixity.max_bytes')`) are skipped + logged, so one huge
    master cannot make a sweep run unreasonably long.

- **`AhgCore\Console\Commands\FixitySweepCommand`** — `ahg:fixity-sweep`
  (`--limit`, `--json`). Prints a coverage/result table or JSON. Exits non-zero
  only on a real `mismatch`, so a monitor/cron can alert. Registered in the
  provider's `commands()` array.

- **Daily schedule** — `ahg:fixity-sweep --limit=100` at 03:40,
  `withoutOverlapping`, `runInBackground`. No-op when `digital_object` is absent.

- **`AhgCore\Controllers\FixityController` + view
  `ahg-core::fixity.index`** — the admin report at `GET /admin/fixity`
  (`auth` middleware group → 302 for guests, never 500). Big numbers + CSS bars,
  no charting library; empty/zero/missing-table states render a calm card.
  Route is two-segment, so it can never collide with the single-segment
  `/{slug}` archival-record catch-all.

## Path resolution note

`digital_object.path` is typically a web-relative directory ending in `/`, with
the filename in `digital_object.name` (full file = base + path + name). Some
legacy rows store the full file in `.path`, and `.path` may carry a leading
`/uploads/` that is not present under the resolved uploads root. `resolveFile()`
handles all of these by trying candidates in order and existence-checking each.

## Boundaries

Read-only over existing tables except the boot auto-create of
`core_fixity_check_log` and the fixity-log row writes. No `ALTER` of any existing
table. No locked file touched (all changes are in non-locked `ahg-core` paths).
No AI calls. Epic heratio#1244 stays open.
