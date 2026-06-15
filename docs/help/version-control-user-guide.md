# Version Control

> Version Control keeps an automatic, snapshot-based history of every archival description and authority record: each save stores a full canonical snapshot, so staff can browse past versions, compare any two, and restore an earlier state, with retention pruning and clearance-aware permission gating throughout.

## Overview

The Version Control module (`ahg-version-control`) captures a complete JSON snapshot of an information object or actor each time it is saved. Snapshots are written by Eloquent observers, recorded as numbered versions in the `information_object_version` and `actor_version` tables, and exposed through a version-history UI for listing, viewing, comparing (diff) and restoring. A snapshot builder produces a deterministic, byte-equivalent capture covering the base row, all translations, access points, events, relations, physical objects and custom fields.

Versioning never blocks a save: if a snapshot fails, the save still succeeds and the failure is logged. All version UI actions are gated by `version.*` ACL permissions, and restoring a classified record additionally requires the classified-restore permission plus sufficient clearance.

## Key features

- **Automatic capture** - a version is recorded on every save of an information object or actor, with a change summary and the list of changed fields.
- **Comprehensive snapshots** - each snapshot includes the base record, all language variants, access points, events, relations, physical objects and (if installed) custom fields, in a stable, comparable form.
- **Version history list** - a paginated list (20 per page) per record, showing each version's number, summary, changed fields, author and timestamp, and whether it was a restore.
- **Version detail view** - inspect a single version's full snapshot and changed fields.
- **Diff** - compare any two versions of the same record field by field.
- **Restore** - roll a record back to an earlier version; this creates a new version (rather than overwriting history) marked as a restore and noting the source version.
- **Clearance-aware gating** - listing, diffing and restoring each require the matching `version.*` permission; restoring a classified record also checks clearance.
- **Bulk-import friendly** - a version context lets import paths suppress intermediate captures and emit one version per record after processing.
- **Retention pruning** - a console command trims old versions while always keeping the baseline and the most recent N.
- **CLI capture and backfill** - console commands can capture a version on demand or backfill history.

## How to use

### Browse, compare and restore (staff)

All version-control routes require an authenticated user, and each action is checked against its ACL permission.

1. From an information object or actor, open its version history: `/version-control/{entity}/{id}` where `{entity}` is `information_object` or `actor`. This requires the version-list permission.
2. Review the list of versions with their summaries, authors and timestamps.
3. To inspect one version, open `/version-control/{entity}/{id}/{number}` to see its full snapshot and changed fields.
4. To compare two versions, open the diff at `/version-control/{entity}/{id}/diff/{v1}/{v2}` (requires the diff permission).
5. To roll back, restore a version (requires the restore permission; classified records also require the classified-restore permission and sufficient clearance). The restore creates a new version recording where it was restored from, so nothing is lost.

### Operator commands

- `php artisan ahg:version-capture --entity=information_object --id=<id> [--summary="..."] [--user-id=<id>]` - build and write a snapshot on demand.
- `php artisan ahg:version-prune [--entity=information_object,actor] [--retain-count=N] [--retain-days=N] [--dry-run]` - apply retention rules; version 1 (the baseline) and the most recent N versions are always kept.

## Configuration

Retention is configured via `ahg_settings` keys in the `version_control` group:

- `version_control.retain_count` - how many recent versions to keep per record; `0` means unlimited. The v1 baseline is always kept.
- `version_control.retain_days` - keep versions newer than N days; `0` means unlimited.
- `version_control.skip_on_minor_edit` - reserved for future use; currently unused.

Pruning only deletes when at least one retention rule is greater than zero; with both at the default of `0`, nothing is pruned. The prune command's `--retain-count` and `--retain-days` options override the stored settings for a single run.

## Known issues

- Capture currently covers information objects and actors; other entity types are not versioned.
- Snapshot capture is best-effort: a failure is logged and skipped rather than blocking the save, so a record's history can have a gap if a capture failed.
- `version_control.skip_on_minor_edit` is defined but not yet acted upon.

## References

- Source: packages/ahg-version-control/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues (see the ahg-version-control tracker)
