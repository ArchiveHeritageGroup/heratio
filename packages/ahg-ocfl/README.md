# ahg-ocfl

OCFL v1.1 (Oxford Common File Layout) storage layer for Heratio.

Implements the [OCFL v1.1 specification](https://ocfl.io/1.1/spec/): storage root,
object root, deterministic `inventory.json`, sha512 content addressing, and
multi-version object semantics. Wraps Laravel's filesystem abstraction so the
backing store can be local disk, S3, Wasabi, or any other Flysystem adapter.

Tracked under Heratio issue #691. Consumer wire-up (ahg-preservation calling
`ocfl:ingest`) and the admin UI surface are follow-ups.

## Components

- `Layout/StorageRoot` - storage-root namaste + layout descriptor; lists / reads / writes objects
- `Layout/OcflObject` - one logical archival object with N versions
- `Layout/Inventory` - inventory.json round-tripper with deterministic key ordering
- `Layout/Version` - one version's state map + user / message / timestamp
- `Layout/ContentAddressing` - sha512 / sha256 digest helper, returns `vN/content/<rel>` paths
- `Layout/StorageLayout` - flat-id / pairtree / hashed-n-tuple object-root path resolvers
- `Storage/OcflStorageAdapter` - thin wrapper over `Storage::disk('ocfl')`

## Artisan commands

```
php artisan ocfl:init {path?}      # write 0=ocfl_1.1 + ocfl_layout.json
php artisan ocfl:ingest {ioId}     # snapshot IO's digital object into OCFL (new object or version)
php artisan ocfl:verify {ioId?}    # validate one / all objects (fixity + structure)
php artisan ocfl:export {ioId}     # tar an OCFL object to storage/ocfl-exports/<id>.tar
```

## Filesystem disk

Add to `config/filesystems.php`:

```php
'ocfl' => [
    'driver' => 'local',
    'root'   => env('OCFL_DISK_ROOT', storage_path('ocfl')),
    'throw'  => true,
],
```

Or point it at an S3 / Wasabi bucket - the OCFL layer only uses the
Filesystem contract (`get`, `put`, `exists`, `directories`, `files`).

## Spec links

- OCFL v1.1: https://ocfl.io/1.1/spec/
- Namaste 0.4: https://confluence.ucop.edu/display/Curation/Namaste
