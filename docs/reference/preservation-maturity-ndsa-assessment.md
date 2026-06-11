# Preservation maturity self-assessment (NDSA Levels) - ahg-core

**Summary.** Heratio ships an admin, read-only preservation MATURITY
self-assessment at `GET /admin/preservation-maturity` (auth-gated) that scores
the running instance, evidence-based, against the five functional areas of the
**NDSA Levels of Digital Preservation** (v2.0). First slice of heratio#1244.
Lives in `packages/ahg-core` (not in the locked `ahg-preservation` package).
Scoring is conservative: absence of evidence lowers the level and surfaces a
gap recommendation; it never invents a higher score and never 500s.

## Framework

The NDSA Levels of Digital Preservation are a widely used, jurisdiction-neutral
self-assessment grid. Five functional areas, each graded Level 1 through
Level 4; this assessment adds "Not yet" (Level 0) for an area with no evidence.

Areas: Storage and geographic location; Integrity (fixity and write
protection); Information security and access control; Metadata; Content and
file formats.

Overall maturity = the **minimum** level across the five areas (weakest-link
reading, per the NDSA model).

## Files

- `packages/ahg-core/src/Services/PreservationMaturityService.php` - read-only
  scorer. Every probe is `Schema::hasTable`/`hasColumn` guarded and wrapped in
  a `guardCount()` try/catch; each area wrapped in try/catch in `assess()`.
  Cheap aggregates only (COUNT / EXISTS / DISTINCT), no per-record loops.
- `packages/ahg-core/src/Controllers/PreservationMaturityController.php` -
  controller; wraps `assess()` in try/catch and renders an honest empty
  assessment on any failure.
- `packages/ahg-core/resources/views/preservation-maturity/index.blade.php` -
  Bootstrap 5 dashboard: overall summary, five per-area cards (big level
  badge + CSS progress bar + Evidence + Next step). Empty-state when no areas.
- `packages/ahg-core/routes/web.php` - route registered under
  `Route::middleware('auth')->group(...)`. Two-segment `/admin/...` path keeps
  it clear of the single-segment `/{slug}` archival-record catch-all.

## Per-area scoring evidence (which DB tables each area reads)

- **Storage**: `preservation_replication_target` (active copies +
  `target_type` diversity = provider/system diversity), `preservation_replication_log`
  (replication actually running). The primary store always counts as one copy.
  Copies <=1 -> Not yet; ==2 -> L1; >=3 same type -> L2; >=3 diverse types but
  no runs -> L3; >=3 diverse + runs -> L4.
- **Integrity**: `digital_object.checksum` / `checksum_type` and
  `preservation_checksum` (recorded fixity), `preservation_fixity_check`
  (verification run + fail/error/missing outcomes), `integrity_legal_hold` /
  `integrity_retention_policy` (write protection). No checksum -> Not yet;
  checksum only -> L1; checks run but no write protection -> L2; checks + write
  protection -> L3 (L4 needs demonstrated detect-and-repair).
- **Information security (Control)**: `acl_group` + `acl_permission` and
  `object_security_classification` (access controls); `ahg_audit_log` /
  `ahg_audit_access` / `ahg_audit_authentication` (audit logging). No ACL ->
  Not yet; ACL only -> L1; partial audit coverage -> L2; full change + access +
  auth logging -> L3 (L4 needs regular log review).
- **Metadata**: `information_object_i18n.title` (descriptive), `event`
  (administrative/provenance), `digital_object_metadata` (technical),
  `preservation_event` (PREMIS). Tiers L1..L4 as each layer is present.
- **Content / file formats**: `digital_object.mime_type` (basic
  identification + distinct-format diversity), `preservation_object_format.puid`
  (PRONOM identification), `preservation_format` with `preservation_action`
  (monitored risk registry). No format info -> Not yet; MIME only -> L1; PUID
  identified -> L2; PUID + monitored registry -> L3 (L4 needs recorded
  migration/normalisation).

## Honest-absence handling

Each area returns Level 0 / "Not yet" with a concrete gap recommendation when
its evidence is missing. A missing optional table (e.g. `preservation_format`
on an instance without the preservation package installed) is treated as "no
evidence" via the `Schema::hasTable` guards, which lowers the level rather than
throwing. The view has an explicit empty-state and an error banner; it never
500s.

## Read-only guarantees

No INSERT/UPDATE/DELETE/ALTER anywhere. No AI calls. The `ahg-preservation`,
`ahg-display`, and IO-show locked trees are only READ (schema inspected); none
of their files were modified. None of the individually locked `ahg-core` files
(VoiceController, TtsController, SectorIdentifierService, IiifController,
`_action-icons.blade.php`, `clipboard/index.blade.php`) were touched.
