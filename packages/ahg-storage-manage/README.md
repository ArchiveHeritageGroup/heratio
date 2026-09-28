# AhgStorageManage

AHG Heratio plugin package.

## Overview

Brief description of what this package provides.

## Structure

Describe the directory layout.

## Configuration

Any config options, env vars, or settings keys.

## Usage

How to use the package's features.

## Storage movement log

Where holdings have been, and where they are now (heratio#1514, atom-ahg-plugins#193).
The tables are shared verbatim with the AtoM `ahgStorageManagePlugin`, so both
platforms read one schema. Any change to them has to land on both sides in the
same release.

`ahg_storage_movement` is the record: one append-only row per move, per subject.
A move entered wrongly is corrected by a further move, never by editing or
deleting a row, because a history that can be quietly rewritten proves nothing
about the custody of the holdings.

`ahg_physical_object_location` is the current-state index beside it, written in
the same transaction, so a browse screen reads one row per object instead of
working out the latest movement. It is the same split as `parent_id` and its
closure table: the log is authoritative, the index is derived.

A NULL location means different things per subject, and the difference is
load-bearing:

| subject_type | `from_location_id` NULL | `to_location_id` NULL |
| --- | --- | --- |
| `physical_object` | first placement | removed from storage |
| `storage_location` | was at the root | moved to the root |

For a location the two columns hold its old and new **parent**, not its own
place. The foreign keys are RESTRICT rather than SET NULL for that reason:
nulling a deleted location's id would silently turn "moved out of Room A" into
"taken out of storage". The name snapshots (`subject_name`, `from_location_name`,
`to_location_name`, `username`) keep a row readable after a rename or after an
account is deleted.

`StorageMovementService`:

- `moveObject($id, $toLocationId, $context)` - one object, or out of storage with
  `null`. Refuses a move to where it already is.
- `moveObjects($ids, $toLocationId, $context)` - one transaction, one row per
  object, a shared `batch_id`, skipping objects already at the destination.
- `recordLocationMove($id, $fromParent, $toParent, $context)` - called by
  `StorageLocationService::update()` inside the same transaction as a reparent.
  One event, for the location that actually moved: what sits underneath is a
  closure query, so logging the subtree would restate the tree and go stale.
- Reads: `historyFor`, `historyForLocation`, `batch`, `objectsIn`,
  `currentLocationOf`.

`$context` accepts `note`, `batch_id`, `moved_at`, and `user_id` / `username` for
a command or import acting on someone's behalf. Left out, the actor comes from
`auth()`; unauthenticated console and queue runs record a null actor rather than
refusing to log.

Deleting a location is refused while it has children, while it still holds
objects, or once it appears anywhere in the log.

## Testing

How to run tests for this package.

`StorageMovementTest` carries the invariants ported from the plugin's
`testing/storage-movement-check.php`, the first of which is the one the design
rests on: the current-location index always equals the latest movement per
object.

```bash
php vendor/bin/phpunit packages/ahg-storage-manage/tests
```
