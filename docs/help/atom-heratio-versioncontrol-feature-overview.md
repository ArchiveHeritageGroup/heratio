> Heratio Help Center article. Category: Reference.

# Version Control — Feature Overview

**Plugin:** `ahgVersionControlPlugin` (AtoM) / `ahg-version-control` (Heratio)
**Version:** 0.1.0 (initial release)
**Author:** The Archive and Heritage Group (Pty) Ltd
**Category:** Records management

## What it does

Captures a full, restorable snapshot of every information-object and authority-record (actor) save. Adds a "Versions" history to every record with side-by-side diff and a one-click restore. Integrates with the AHG audit trail and security clearance system so every version event is auditable and classified records are protected from unauthorised rollbacks.

Designed for clients whose tender, regulatory, or policy environment requires demonstrable change-tracking on archival metadata — POPIA, GDPR, NARSSA, GCIS RFB 001 (clauses 4.1.1.3, 4.1.1.9, 4.6.2), and equivalents internationally.

## Key features

- **Automatic capture on every save.** No user action required. A snapshot of the record's full state — including all `i18n` cultures, access points, events, relations, physical-object links, and custom-field values — lands in the version table after every commit.
- **Word-level diff with inline highlights.** Side-by-side comparison of any two versions, with `<ins>` (green) and `<del>` (red strikethrough) spans inside long-text fields so curators see exactly which words changed.
- **One-click restore.** A "Restore this version" button on the version detail page rolls the record back to the chosen snapshot. The restore creates a new version row tagged `is_restore=1, restored_from_version=N` for full auditability — restores never lose history.
- **Two-layer security on restore.**
  - Class-action ACL: `version.list`, `version.diff`, `version.restore`, `version.restore_classified` permissions plug into AtoM's existing `acl_permission` table.
  - Security clearance: when a record is classified under `ahgSecurityClearancePlugin`, restoring requires a clearance level matching the record's CURRENT classification (security upgrades cannot be reversed by lower-cleared users).
- **Audit dual-write.** Every version create and restore writes a row to `ahg_audit_log` with `action=version_created|version_restored`, the full metadata, and the responsible user. Compliance reviews see version events alongside every other auditable action without querying a second table.
- **Retention pruning.** Optional `retain_count` and `retain_days` settings keep storage bounded. The v1 baseline is always preserved; the most-recent N versions are always preserved.
- **Backfill on install.** A one-shot CLI task creates a v1 baseline for every existing record so the version timeline doesn't start mid-history. Idempotent — safe to re-run.
- **Cross-surface parity.** Identical behaviour, schema, and snapshot shape across AtoM (Symfony 1.x) and Heratio (Laravel) deployments. Cross-surface migration of version history is byte-equivalent.

## Compliance and standards alignment

| Standard / regulation | How this plugin supports it |
|---|---|
| **GCIS RFB 001 2026/2027** clauses 4.1.1.3, 4.1.1.9, 4.6.2 | Version management, version control for search/retrieval, version control + audit trail management |
| **POPIA** | Records integrity and traceability of who changed what, when; complements the existing `ahgAuditTrailPlugin` |
| **NARSSA** | Records-management lifecycle integrity; restore operations are themselves audited |
| **MISS classification** | Restores on classified records require matching clearance; lower-cleared users see a clear 403 with a friendly reason |
| **ISO 15489** (records management) | Audit trail of all changes; non-destructive corrections via versioned restore |

## Technical requirements

- **AtoM** 2.10 (Symfony 1.x), MySQL 8, PHP 8.1+
- **Heratio** (Laravel 10+), MySQL 8, PHP 8.2+
- **Required plugin:** `ahgCorePlugin`
- **Recommended plugins (graceful fall-back if missing):**
  - `ahgAuditTrailPlugin` — for the central audit feed dual-write
  - `ahgSecurityClearancePlugin` — for clearance-gated restore on classified records
  - `ahgCustomFieldsPlugin` — included in snapshots when present
- **Storage:** ~3–5 KB per version (varies by record richness). On a 10,000-record archive with retention pruning of "100 most-recent", typical disk usage is ~5 GB. Retention pruning is configurable.

## What is restored, and what is not (v1.0 scope)

| Restored | Not restored (current scope) |
|---|---|
| Base record fields (identifier, level, repository, etc.) | Access points (`object_term_relation`) |
| All i18n rows in all cultures (titles, scope, notes, etc.) | Events |
| Custom field values | Relations between entities |
|  | Physical-object links |

The non-restored data stays as it is at the time of restore — the modal makes this explicit so users decide before confirming. Full restore of access points / events / relations is planned for the next release; for now an admin can re-apply them manually using the snapshot view.

## How it scales

- **Capture latency:** sub-50 ms per save on PSIS-equivalent hardware (1 vCPU, 4 GB RAM, MySQL 8 on local disk).
- **Concurrency:** parent-row `SELECT … FOR UPDATE` serialises version writes per entity. Two simultaneous saves of the same record produce sequential `version_number`s with no deadlocks.
- **Backfill:** ~120 entities/second sustained on the same hardware. A 50,000-record archive completes in ~7 minutes.
- **Prune:** chunked deletes in batches of 1,000, transactional per chunk. No table-wide lock.

## Licensing

Proprietary AHG plugin licensed to the deploying client as part of the contract. Source code distributed to the client; modifications by the client are permitted under the contract terms.

The plugin sits on top of open-source AtoM (AGPL-3.0) without modifying base AtoM source — every customisation lives inside the plugin directory tree.

## Contact

The Archive and Heritage Group (Pty) Ltd · johan@theahg.co.za

---

*This is a distributable feature overview. For installation and configuration see the User Manual; for schema and service reference see the Technical Manual.*
