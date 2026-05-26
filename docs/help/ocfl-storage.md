---
title: OCFL Storage
slug: ocfl-storage
category: Preservation
order: 50
---

# OCFL Storage

Heratio can mirror your digital objects into an [Oxford Common File Layout
(OCFL) v1.1](https://ocfl.io/1.1/spec/) storage root. OCFL is a
preservation-grade format: every file is hash-addressed, every change is
versioned, and any OCFL-aware tool (anywhere in the world) can read what
Heratio writes.

## When to use it

- A funder, national archive, or audit body asks for OCFL deposits.
- You want a tamper-evident, versioned copy of your digital masters
  separate from the live `uploads/` tree.
- You're migrating to a new instance and want a portable, self-describing
  bundle per archival item.

## How it works

1. **Initialise the storage root** once per deployment:

   ```
   php artisan ocfl:init
   ```

   This writes the OCFL v1.1 namaste declaration + layout descriptor into
   the configured disk (default: `storage/ocfl`).

2. **Ingest an archival item** any time you want a snapshot:

   ```
   php artisan ocfl:ingest 12345
   ```

   The first call writes version `v1`. Subsequent calls (after the item's
   digital content changes) write `v2`, `v3`, ... and reuse unchanged
   files across versions.

3. **Verify fixity** on demand or on schedule:

   ```
   php artisan ocfl:verify 12345     # one item
   php artisan ocfl:verify           # the entire root
   ```

   Exits 0 if every digest matches, 1 if anything has drifted.

4. **Export a portable bundle** for handover or audit:

   ```
   php artisan ocfl:export 12345
   ```

   Produces `storage/ocfl-exports/urn_heratio_io_12345.tar`. The tar
   contains the full OCFL object - inventory, all versions, all content
   files.

## What gets stored

- Every file referenced by `digital_object` rows for the information
  object.
- A deterministic `inventory.json` with sha512 fixity for every file plus
  a sidecar hash of the inventory itself.
- One namaste declaration per object (`0=ocfl_object_1.1`).

## Restoring from OCFL

If a live file is lost or damaged, use `ocfl:verify` to identify the bad
digest, `ocfl:export` to pull the OCFL object out, then unpack the tar
and copy the affected `vN/content/<path>` file back into the live
`uploads/` tree.

## Configuration

| Env var                 | Default      | Notes                                    |
| ----------------------- | ------------ | ---------------------------------------- |
| `OCFL_DISK`             | `ocfl`       | Laravel filesystem disk to use           |
| `OCFL_DIGEST_ALGORITHM` | `sha512`     | Or `sha256`                              |
| `OCFL_STORAGE_LAYOUT`   | `flat-id`    | `flat-id`, `pairtree`, `hashed-n-tuple`  |
| `OCFL_AUTO_INIT`        | `false`      | Auto-run `ocfl:init` on first boot       |
| `OCFL_EXPORT_PATH`      | `storage/ocfl-exports` | Where `ocfl:export` writes tarballs |
| `OCFL_CLI_USER_NAME`    | `cli`        | Recorded in inventory.user.name for CLI/queue runs |
