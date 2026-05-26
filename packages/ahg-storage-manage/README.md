# ahg-storage-manage

Physical storage browse + management for Heratio. Tracks strongrooms, shelves, boxes, and any other physical-container hierarchy that backs archival holdings; surfaces the link between an information object and its physical location.

## Install

Path-loaded via the root `composer.json`'s `packages/*` repository. Auto-registered by Laravel package discovery; no manual provider wiring required.

## Key surfaces

- `AhgStorageManage\Services\StorageBrowseService` - top-level browse for physical containers (used by /admin/storage)
- `AhgStorageManage\Services\StorageService` - CRUD operations against the `physical_object` hierarchy
- `AhgStorageManage\Services\StrongroomService` - shelf / box / row navigation
- `AhgStorageManage\Controllers\StorageController` - admin routes
- `AhgStorageManage\Controllers\StrongroomController` - storage-strongroom admin

## Routes

- `GET /admin/storage` - browse physical containers
- `GET /admin/storage/{slug}` - show one container + its children + linked IOs
- `POST /physicalobject/link-to` - link an IO to a physical location

## Configuration

Inherits the central storage paths from `config/heratio.php` (`heratio.storage_path`). No additional `ahg_setting` keys today.

## Database

This package reads / writes the base AtoM `physical_object` table; no sidecar tables. The IO -> physical-object linkage flows through the generic `relation` table (type_id resolves through the taxonomy).

## Related packages

- `ahg-information-object-manage` - the IO show page surfaces the linked physical location returned from this package
- `ahg-repository-manage` - repositories own strongrooms; navigation hands off here
