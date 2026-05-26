# OCFL v1.1 Storage Layer (`ahg-ocfl`)

Heratio ships an OCFL v1.1 (Oxford Common File Layout) storage layer as a
parallel preservation surface. This document is the operator + developer
reference. The package lives at `packages/ahg-ocfl/` and is tracked under
GitHub issue #691.

## Why OCFL

Many funders + national archives (NDSA, BL, NARA, ANJ, etc.) now require
content held in an OCFL storage root. OCFL is a versioned, hash-addressable,
implementation-neutral on-disk format. Any conformant OCFL client can read
content written by `ahg-ocfl`.

Spec: https://ocfl.io/1.1/spec/

## Package layout

```
packages/ahg-ocfl/
  composer.json
  config/ocfl.php                       # disk, layout, digest, auto-init
  database/install.sql                  # ahg_ocfl_object_map (idempotent)
  src/
    Providers/AhgOcflServiceProvider.php
    Layout/
      StorageRoot.php                   # storage-root with namaste, read/write/verify
      OcflObject.php                    # one logical archival item + staged content
      Inventory.php                     # deterministic inventory.json round-trip
      Version.php                       # one logical version (state + user + msg)
      ContentAddressing.php             # sha512 / sha256 digest helper
      StorageLayout.php                 # flat-id / pairtree / hashed-n-tuple
    Storage/OcflStorageAdapter.php      # wraps Storage::disk('ocfl')
    Console/Commands/
      OcflInitCommand.php               # ocfl:init {path?}
      OcflIngestCommand.php             # ocfl:ingest {ioId} [--message=]
      OcflVerifyCommand.php             # ocfl:verify {ioId?}
      OcflExportCommand.php             # ocfl:export {ioId}
  tests/Unit/
    InventoryTest.php
    StorageRootTest.php
```

## The four commands

| Command          | What it does                                                                              |
| ---------------- | ----------------------------------------------------------------------------------------- |
| `ocfl:init`      | Writes `0=ocfl_1.1` namaste + `ocfl_layout.json`. Optional path arg targets any local dir.|
| `ocfl:ingest`    | Snapshots `digital_object` rows for one IO into a new OCFL object (or new vN).            |
| `ocfl:verify`    | Validates fixity (sha512 / sha256) + structure for one IO or all objects.                 |
| `ocfl:export`    | Streams an OCFL object into a `.tar` at `storage/ocfl-exports/`.                          |

User attribution comes from `Auth::id()` in a request context; CLI / queue
runs use `config('ocfl.cli_user_name')` (env `OCFL_CLI_USER_NAME`).

## Fixity model

- Default digest: **sha512** (OCFL v1.1 §6.1 recommended). Override via
  `OCFL_DIGEST_ALGORITHM=sha256` if you have a hard infrastructure constraint.
- Every content file gets its digest stored in `inventory.json` manifest.
- The inventory.json itself has a sidecar `inventory.json.sha512` carrying the
  hash of the inventory bytes - so tampering with the inventory is also
  detected by `ocfl:verify`.
- Version reuse: identical bytes between versions are stored once under their
  original `vN/content/...` path (OCFL v1.1 §3.5.3.1).

## Deterministic inventory

Two writers ingesting identical content MUST produce identical
`inventory.json` bytes. `Inventory::toJson()` enforces this by:

- Sorting top-level keys alphabetically.
- Sorting manifest digest keys.
- Sorting per-version state digest keys.
- Using `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`
  with a trailing newline.

This is verified by `tests/Unit/InventoryTest.php::test_round_trip_is_deterministic`.

## Filesystem disk

`ahg-ocfl` only ever touches storage through a Laravel filesystem disk
(`config('ocfl.disk')`, default `ocfl`). Register the disk yourself:

```php
// config/filesystems.php
'ocfl' => [
    'driver' => 'local',
    'root'   => env('OCFL_DISK_ROOT', storage_path('ocfl')),
    'throw'  => true,
],
```

Swap to S3 / Wasabi by changing the driver - no OCFL code needs to change.

## Restore-from-OCFL workflow

1. `php artisan ocfl:verify <ioId>` - confirm fixity is intact.
2. `php artisan ocfl:export <ioId>` - writes
   `storage/ocfl-exports/urn_heratio_io_<id>.tar`.
3. Unpack the tar; the head inventory's manifest tells you which
   `vN/content/<path>` to restore back into the live `uploads/` tree.
4. Re-import via the standard Heratio digital_object workflow.

## Storage layouts

- `flat-id` (default) - one directory per object id. Cheap, good for
  < ~10k objects on most filesystems.
- `pairtree` - classic Namaste pairtree, good for medium volumes.
- `hashed-n-tuple` - sha256(id) split into 3 x 3-char buckets; scales to
  millions per root.

Set via `OCFL_STORAGE_LAYOUT`. The chosen layout is recorded in the storage
root's `ocfl_layout.json`.

## Status (2026-05-26)

- Foundation ships in this package (#691). Consumer wire-up (the
  preservation module calling `ocfl:ingest` on every accession +
  reingest) and the admin UI surface (browse / verify / export
  buttons) remain - tracked as follow-ups under #691.
- `ahg-preservation/` is locked, so cross-package wire-up requires an
  explicit unlock when that work begins.
