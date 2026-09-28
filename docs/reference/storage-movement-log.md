# Storage movement log - data model and decisions

Heratio v1.156.0 (28 September 2026) added the movement log to `ahg-storage-manage`, on top of the hierarchical storage locations that shipped in v1.155.0. It is a port of the AtoM `ahgStorageManagePlugin` v3.111.0 module, on the same tables, so an institution running both platforms reads one storage history. Tracking: heratio#1514, atom-ahg-plugins#193.

## The two tables

`ahg_storage_movement` is the record. One append-only row per move, per subject. Nothing edits or deletes a row: a move entered wrongly is corrected by a further move, and the correction becomes part of the record. A history that can be quietly rewritten proves nothing about the custody of the holdings, which is the only reason to keep one.

`ahg_physical_object_location` is the current-state index beside it, one row per object, written in the same transaction as the movement row. It exists so a browse screen reads a single row per object rather than working out the latest movement for each. It is the same split as `parent_id` and its closure table: the log is authoritative, the index is derived and can be rebuilt from it.

The invariant tying them together, and the first thing `StorageMovementTest` asserts: **for every object in the index, its location equals the `to_location_id` of its newest movement row.**

## A NULL location is load-bearing

| subject_type | `from_location_id` NULL | `to_location_id` NULL |
| --- | --- | --- |
| `physical_object` | first placement | removed from storage |
| `storage_location` | was at the root | moved to the root |

For a location the columns hold its old and new **parent**, not its own place in the building.

The foreign keys are RESTRICT rather than SET NULL because of that table. If deleting a location nulled its id in the log, "moved out of Room A" would silently become "taken out of storage" - the same row, a different fact. `StorageLocationService::delete()` refuses in plain words before the database refuses in an integrity error: a location cannot be deleted while it has children, while it holds objects, or once it appears in the log.

Name snapshots (`subject_name`, `from_location_name`, `to_location_name`, `username`) keep a row readable after a rename, after an account is deleted, and even if a location is ever force-deleted with foreign key checks off.

## One event for the thing that moved

Moving a location logs one row, for that location. What sits underneath is a closure query, so fanning the move out over every object beneath would only restate what the hierarchy already says, and would go stale the moment the tree changed.

A bulk move is the opposite case: one row per object, sharing a `batch_id`. Per-object history stays a plain lookup, and "what moved together" is a lookup by batch. The batch runs in one transaction, because a bulk move that half succeeded would leave the shelf list disagreeing with the history. Objects already at the destination are skipped rather than recorded as moves that did not happen.

## The actor

`user_id` carries no foreign key, deliberately. History has to outlive the account that made it, and deleting a user must not take the record of what they did with them. `username` is a snapshot for reading.

The actor comes from `auth()` unless the caller passes `user_id` or `username` in the context, which lets a command or an import say who it acted for. An unauthenticated console or queue run records a null actor rather than refusing to log the move.

## Where it lives

- `packages/ahg-storage-manage/src/Services/StorageMovementService.php` - `moveObject`, `moveObjects`, `recordLocationMove`, `historyFor`, `historyForLocation`, `batch`, `objectsIn`, `currentLocationOf`, `newBatchId`.
- `packages/ahg-storage-manage/database/install.sql` - the DDL, copied verbatim from the plugin, comments included. The provider self-installs it on boot, and its guard checks `ahg_storage_movement` as well as the closure table, or an install that predates the movement log would never receive it.
- UI: movement history and an objects-here card with bulk move on `storagelocation.show`; current location, history and a Move action on `physicalobject.show`; the move form in `move.blade.php`.
- Tests: `packages/ahg-storage-manage/tests/Feature/StorageMovementTest.php`, carrying the invariants from the plugin's `testing/storage-movement-check.php`.

## Parity contract

The tables are shared with AtoM verbatim, column comments included. Any change to them lands on both sides in the same release, and the AtoM side is owned by the archive session. Changing one platform alone would split the history an institution running both relies on.
