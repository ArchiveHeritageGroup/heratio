# NDSA Levels of Digital Preservation in Heratio

**Summary.** The NDSA Levels of Digital Preservation (v2.0) are a widely used,
jurisdiction-neutral self-assessment grid published by the National Digital
Stewardship Alliance. They grade an organisation across five functional areas -
Storage; Integrity (fixity and write protection); Control (information security
and access); Metadata; and Content (file formats) - at four progressive levels
(Level 1 "know your content" through Level 4 "repair / advanced"). The model is
deliberately practical: it tells you the *next concrete step* in each area rather
than demanding a pass/fail certification. Heratio ships a live, read-only
self-assessment that scores the running instance against all five areas,
evidence-based, at `GET /admin/preservation-maturity`.

## The concept

Five functional areas, each scored Level 1 to Level 4 (Heratio adds Level 0,
"Not yet", for an area with no evidence):

1. **Storage and geographic location** - how many copies you keep and how far
   apart. L1 two copies; L2 three copies plus geographic separation; higher
   levels add provider / system diversity and verified replication.
2. **Integrity (fixity + write protection)** - L1 record checksums on ingest; L2
   verify fixity periodically; L3 add write protection; L4 demonstrate
   detect-and-repair. See `dp-07-fixity-and-integrity`.
3. **Control (information security / access)** - who may read and who may change
   content, plus audit logging of those actions.
4. **Metadata** - from minimal inventory metadata (L1) up through technical,
   administrative / provenance (PREMIS), and full preservation metadata.
5. **Content (file formats)** - from knowing your MIME types (L1) through PUID
   identification (L2) to a monitored format-risk registry and recorded migration.

A key NDSA reading: overall maturity is the *minimum* level across the five areas
(weakest-link), because a chain is only as strong as its weakest link.

## How Heratio addresses this

- **Live self-assessment.** `GET /admin/preservation-maturity` (auth-gated,
  route `preservation-maturity.index`) renders a Bootstrap 5 dashboard with an
  overall summary plus a per-area card (level badge, evidence, and the concrete
  next step). It is the first slice of heratio#1244 and lives in `ahg-core`
  (`PreservationMaturityService` + `PreservationMaturityController`), not in the
  locked preservation package.
- **Evidence-based, conservative scoring.** The scorer reads cheap aggregates
  from real tables and never invents a higher score; absence of evidence lowers
  the level and surfaces a gap recommendation. By area:
  - Storage: `preservation_replication_target` (active copies + type diversity)
    and `preservation_replication_log` (replication actually running).
  - Integrity: `digital_object.checksum` / `preservation_checksum` (recorded
    fixity), `preservation_fixity_check` (verification runs), and
    `integrity_legal_hold` / `integrity_retention_policy` (write protection).
  - Control: `acl_group` + `acl_permission` and `object_security_classification`
    (access controls), plus `ahg_audit_log` / `ahg_audit_access` /
    `ahg_audit_authentication` (audit logging).
  - Metadata: `information_object_i18n.title` (descriptive), `event`
    (administrative / provenance), `digital_object_metadata` (technical), and
    `preservation_event` (PREMIS).
  - Content: `digital_object.mime_type` (basic ID + diversity),
    `preservation_object_format.puid` (PRONOM ID), and `preservation_format`
    with `preservation_action` (monitored risk registry).
- **Honest absence handling.** Every probe is `Schema::hasTable` / `hasColumn`
  guarded; a missing optional table is treated as "no evidence" rather than an
  error, so the dashboard never 500s and an empty instance reads honestly as
  "Not yet" with next steps.
- **Read-only.** No INSERT / UPDATE / DELETE / ALTER, no AI calls; it only
  inspects schema and counts.

## Gaps / not yet

- The assessment scores at the *instance* level (one running deployment); it does
  not yet roll up across a multi-tenant fleet into a single fleet maturity view.
- It measures evidence the database can show; some Level-4 criteria (for example a
  documented schedule of *regular log review*, or a *demonstrated* fixity repair)
  are partly procedural and cannot be fully proven from table counts, so those
  ceilings depend on operational practice the database alone cannot witness.
