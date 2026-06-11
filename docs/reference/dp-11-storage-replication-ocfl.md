# Storage, replication, and OCFL preservation storage in Heratio

**Summary.** Long-term preservation needs more than one copy in one place. The
field's guidance (echoed by LOCKSS, the NDSA Storage area, and OAIS Archival
Storage) is: keep multiple copies, on independent systems / providers, in
geographically separated locations, and verify them. On top of replication, a
preservation-grade *storage layout* helps: the Oxford Common File Layout (OCFL
v1.1) stores each object hash-addressed and versioned, so any OCFL-aware tool can
read it and any change is captured as a new version without overwriting history.
Heratio addresses both: it replicates digital objects to multiple configurable
targets with verification and self-healing, and it can mirror objects into an
OCFL v1.1 storage root.

## The concept

- **Multiple copies, separated.** Two copies on the same disk protect against
  nothing. NDSA Storage maturity rewards three or more copies, on diverse systems
  or providers, in different geographic locations, with replication that actually
  runs and is logged.
- **Verification + self-healing.** Replicas are only useful if you know they are
  good. Verifying replicas against stored checksums - and restoring a damaged
  primary from a verified replica - turns "we have backups" into "we can prove
  and recover integrity".
- **OCFL.** A preservation-grade, application-independent storage layout: files
  are content-addressed by digest, each object is self-describing
  (`inventory.json` with fixity for every file), and every change writes a new
  version (v1, v2, ...) reusing unchanged files. It is tamper-evident and
  portable - you can hand an OCFL root to any other institution and they can read
  it without your software.

## How Heratio addresses this

- **Replication targets.** Heratio replicates digital objects to multiple
  configurable targets (local filesystem, rsync, SFTP, and cloud object stores),
  tracked in `preservation_replication_target`, with runs logged in
  `preservation_replication_log`. The backup / replication admin surface is
  `GET /admin/preservation/backup` (route `preservation.backup`).
- **Self-healing.** When a fixity check fails, Heratio searches the configured
  replication targets, verifies a candidate copy against the stored checksum,
  restores the primary, and logs the repair as a PREMIS event. See
  `dp-07-fixity-and-integrity`.
- **Storage-area maturity.** The preservation-maturity assessment scores Storage
  from `preservation_replication_target` (active-copy count + `target_type`
  diversity) and `preservation_replication_log` (replication actually running):
  one copy is "Not yet", two is L1, three or more diverse + verified runs reaches
  the top (see `dp-08-ndsa-levels`).
- **OCFL v1.1 storage root.** The `ahg-ocfl` package mirrors digital objects into
  an OCFL v1.1 root. Commands:
  - `php artisan ocfl:init` - initialise the storage root (namaste declaration +
    layout descriptor).
  - `php artisan ocfl:ingest {ioId}` - snapshot an item (first call = v1,
    later calls = v2, v3, ... reusing unchanged files).
  - `php artisan ocfl:verify {ioId}` (or whole-root `php artisan ocfl:verify`) -
    validate SHA-512 fixity + structure; exits non-zero on drift.
  - `php artisan ocfl:export {ioId}` - produce a portable tar of the full OCFL
    object (inventory + all versions + all content) for handover or audit.
  Configurable via `OCFL_DISK`, `OCFL_DIGEST_ALGORITHM` (sha512 default),
  `OCFL_STORAGE_LAYOUT`, and `OCFL_EXPORT_PATH`.
- **Storage paths are centralised.** Heratio's storage locations are configured
  centrally in `config/heratio.php` (`heratio.storage_path`,
  `heratio.uploads_path`, `heratio.backups_path`) via environment variables -
  there are no hardcoded paths, which makes a separate preservation / replication
  mount straightforward to configure per deployment.

## Gaps / not yet

- OCFL provides a tamper-evident, versioned *parallel* copy; it is operated via
  CLI commands rather than being wired into a one-click "also keep an OCFL copy"
  toggle in the ingest UI.
- Geographic separation and provider diversity are configuration choices the
  operator must make (Heratio can replicate to diverse targets, but it cannot
  guarantee that two configured targets are genuinely in different locations -
  the maturity assessment can only see target *type* diversity, not physical
  geography).
