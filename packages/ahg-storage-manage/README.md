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

## Location types

The location types are a managed list: the Dropdown Manager taxonomy
`storage_location_type`, seeded with building, floor, room, aisle, bay, rack,
shelf, container and storage unit by `database/seed_dropdowns.sql` (the service
provider loads it on first boot when the taxonomy is absent). An archive that
keeps things in a plan cabinet adds "cabinet" there and it appears in the forms.
A type switched off is no longer offered; locations that already carry it keep
it, including through an edit. If the list is empty, or `ahg_dropdown` is not
there, `StorageLocationService::types()` falls back to the nine shipped types,
because a form with no types in it cannot save anything (heratio#1528).

## Containers inside containers

A box in a carton on a pallet in a bay is modelled with the tree, not beside it.
The carton and the pallet are **locations** (types `container` and
`storage_unit`); the box is the **physical object** placed in the carton. There
is no parent link between physical objects. Moving a pallet is then one location
move in the log, and everything on it goes with it.

A location's page lists what is held in it directly and, separately, what is
held in every location beneath it, each with the location it is actually in
(`StorageMovementService::objectsUnder()`, one closure join).

## Placing objects

A location's page offers the physical objects that have no place yet, searchable
by name and capped at 100, to editors and administrators. Selecting some and
pressing **Place here** (`POST /storagelocation/{slug}/place-objects`) is one
batch of first placements in the movement log. Objects that already have a place
are moved from the page of the location they are in.

## Capacity

Each location may declare a capacity and a unit. A location's page shows its own
figure and, apart from it, the sum declared beneath, **per unit**
(`StorageLocationService::capacityRollup()`). Units are never added to each
other, and the two figures are never added together: a room and its shelves may
describe the same space. What is held is counted in physical objects - here,
beneath, and in all. There is no percentage full, because nothing records how
much of a shelf a box takes up.

## The physical storage form as the way into the tree

The box form keeps Building, Floor, Room, Aisle, Bay, Rack and Shelf
(heratio#1545). Each suggests the places of its type inside the place chosen
above it. On save `StoragePlacementService` finds each filled level beneath the
one above it - same parent, same type, name compared without regard to case -
and places the box in the innermost one through the movement log. Empty levels
are skipped. Below building level a bare number or code gets its level in front
("1" on Floor becomes "Floor 1"), the same rule the migration uses, so both find
the same place.

- A name that is not there yet adds a new place, for editors and administrators
  only. Anybody else is told the location was not changed.
- A resave with the same places does nothing, so a box moved elsewhere (into a
  container below its shelf, say) is not pulled back.
- Clearing every level leaves the box where it is; taking a box out of storage is
  done from its Move screen.
- The flat text columns in `physical_object_extended` are still written, so
  reports that read them keep working.
- The box page shows its location as a linked path and its latest five moves.
  The physical storage list shows the tree path, or the flat fields for a box
  that is not placed, with the old free-text location as a note.
- The strongroom block is gone from the box form: a strongroom is a room in the
  tree. The strongroom screens themselves are unchanged.

## Bringing the old location fields into the tree

    php artisan ahg:storage-migrate-flat-locations                  # report only
    php artisan ahg:storage-migrate-flat-locations --apply
    php artisan ahg:storage-migrate-flat-locations --free-text-type=room --apply

For each physical object without a place it finds or creates the chain of
locations the structured fields on `physical_object_extended` name and places the
object in the innermost one. Failing those it uses the strongroom assignment.
Every strongroom becomes a room, whether or not anything is in it, with its
description and capacity.

- Nothing is deleted or overwritten. The old fields stay as they were.
- It can be run again. Objects already placed are left alone and existing
  locations are reused, matched on parent, type and name without regard to case.
- It is one transaction. If it cannot finish, nothing is changed.
- Each placement is a first placement in the log, with a note saying where it
  came from and `ahg:storage-migrate-flat-locations` as the actor.
- **Free text is not guessed at.** An object with only free text on
  `physical_object_i18n.location` is listed and left, unless `--free-text-type`
  names the type it stands for.

Not carried over, because they are not locations of a physical object:
`information_object_physical_location` records where a description sits inside
its container, and `spectrum_location` belongs to the Spectrum package.

## Testing

How to run tests for this package.

`StorageContentsTest` (types, nested containers, roll-up, placing, the
migration) and `StoragePlacementTest` (the box form) port the plugin's
`testing/storage-contents-check.php` and `testing/storage-placement-check.php`.

`StorageMovementTest` carries the invariants ported from the plugin's
`testing/storage-movement-check.php`, the first of which is the one the design
rests on: the current-location index always equals the latest movement per
object.

```bash
php vendor/bin/phpunit packages/ahg-storage-manage/tests
```
