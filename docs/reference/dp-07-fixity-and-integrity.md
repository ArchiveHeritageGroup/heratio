# Fixity and integrity in Heratio

**Summary.** Fixity is the property of a digital file being unchanged - bit for
bit - over time. It is verified with a checksum (a cryptographic hash such as
SHA-256): you compute the hash when a file enters the archive, store it, and
recompute it later; if the two hashes match, the file is intact; if they differ,
something has corrupted, truncated, or tampered with the file. Fixity is the
single most fundamental integrity control in digital preservation and a core
requirement of OAIS Archival Storage and of the NDSA Levels' Integrity area.
Heratio records a checksum per digital object, verifies fixity on demand and on
schedule, logs every check as a PREMIS event, and can self-heal corrupted files
from replicated copies.

## The concept

- **Checksum / hash.** A short, fixed-length fingerprint derived from a file's
  bytes. Changing even one bit changes the hash. SHA-256 is the recommended
  default (good security / speed balance); SHA-512 for high-security archives;
  MD5 / SHA-1 only for legacy or quick checks.
- **Fixity check.** Recompute the hash and compare to the stored value. A match
  is a "fixity pass"; a mismatch (or a missing file) is a failure that must be
  investigated and, ideally, repaired from a known-good copy.
- **Why it matters.** Storage media degrade silently ("bit rot"), and tampering
  must be detectable. Regular, logged fixity checking is what lets an archive
  *assert* that what it holds today is what it received.
- **Write protection.** Beyond detection, mature archives restrict who can alter
  preserved bytes (legal holds, retention locks) so corruption is prevented, not
  just caught.

## How Heratio addresses this

- **Checksums at rest.** Each `digital_object` carries a `checksum` and
  `checksum_type`; `preservation_checksum` records recorded fixity values.
  Checksums can be generated on a record via
  `POST /admin/preservation/api/checksum/{id}/generate` (route
  `preservation.api.checksum.generate`).
- **Verification.** Fixity is verified per object via
  `POST /admin/preservation/api/fixity/{id}/verify` (route
  `preservation.api.fixity.verify`), and at scale by
  `AhgPreservation\Services\FixityScanService`. The CLI sweep is
  `php artisan preservation:scan` (with `--stale-days` to target objects not
  checked recently). Verification outcomes (pass / fail / error / missing) are
  recorded in `preservation_fixity_check`.
- **Scheduled fixity.** `php artisan ahg:preservation-fixity-run`
  (`RunFixitySchedulesCommand`) runs due fixity schedules from
  `preservation_workflow_schedule`; the scheduler UI is
  `GET /admin/preservation/scheduler` (route `preservation.scheduler`). The
  fixity log is at `GET /admin/preservation/fixity-log` (route
  `preservation.fixity-log`).
- **Events.** Every check is recorded as a PREMIS `fixity check` event (see
  `dp-03-premis-preservation-metadata`), visible at
  `GET /admin/preservation/events`.
- **Self-healing.** When a fixity check fails, Heratio can restore the corrupted
  or missing file from a verified replicated copy (it searches configured
  replication targets, verifies the backup against the stored checksum, restores,
  and logs the repair as a PREMIS event). See `dp-11-storage-replication-ocfl`.
- **Write protection.** Integrity legal holds (`integrity_legal_hold`) and
  retention policies (`integrity_retention_policy`) provide the
  write-protection layer that the maturity assessment looks for when scoring
  Integrity beyond bare checksums (see `dp-08-ndsa-levels`).
- **OCFL fixity.** A second, independent fixity layer is available via the OCFL
  storage root: `php artisan ocfl:verify {ioId}` (or whole-root
  `php artisan ocfl:verify`) re-validates SHA-512 digests for hash-addressed
  OCFL objects. See `dp-11-storage-replication-ocfl`.

## Gaps / not yet

- Heratio detects fixity failures reliably and can auto-repair from replicas,
  but reaching the *highest* maturity level for Integrity requires demonstrating
  the detect-and-repair loop in practice (replicas configured and a recorded
  repair); a fresh instance with checksums only sits at a lower level until that
  is exercised.
- Auto-repair depends on at least one healthy replication target being
  configured; without replicas, a failed fixity check can be detected and logged
  but not automatically healed.
