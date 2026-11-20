# Heratio records management - MoReq2010 and DoD 5015.2 compliance mapping

Clause-by-clause mapping of Heratio's records-management functions against MoReq2010 (core services and the relevant plug-in modules) and DoD 5015.2-STD version 3 (chapter 2 baseline, chapter 4 classified records where relevant). Prepared for GCIS RFB 008/2026/2027 clauses 4.4.6 and 4.4.7 (heratio#1740, merged with #1741).

| Field | Value |
| --- | --- |
| Document | MoReq2010 and DoD 5015.2 compliance mapping |
| Issue | heratio#1740 (GCIS 4.4.6), heratio#1741 (GCIS 4.4.7) |
| Version | 1.0 |
| Status | Draft for bid review |
| Date | 20 November 2026 |
| Owner | Dr Johan Pieterse |
| Code base | Heratio dev, v1.166.27 plus the changes listed in "Gaps closed" |

## How to read this

Every row was checked against the code on 20 November 2026. Evidence points at files and routes, not at intentions. Three statuses are used:

- **Meets** - the function exists, is reachable, and does what the requirement asks.
- **Partial** - some of it exists; the note says what is missing.
- **Gap** - not implemented.

The requirement texts are paraphrases, not quotations. MoReq2010 rows carry the service they belong to; DoD 5015.2 rows carry the chapter 2 or chapter 4 paragraph, marked "approx." where the exact sub-paragraph number was not checked against the standard. Row ids (MQ-..., DOD-...) are this document's own.

Two points of position matter for the bid. Heratio is **not certified** against either standard, and certification is not being pursued: on 16 November 2026 Dr Johan Pieterse decided that compliance for GCIS is shown against the ICA requirements (ISO 16175) that NARSSA adopts, which is a separate mapping. This document is the evidence base for the MoReq2010 and DoD 5015.2 questions only. And no claim here should be read as more than the row says.

Main code locations, abbreviated in the tables:

| Short | Path |
| --- | --- |
| RM | `packages/ahg-records-manage/` |
| INT | `packages/ahg-integrity/` |
| CORE | `packages/ahg-core/` |
| AUD | `packages/ahg-audit-trail/` |
| SEC | `packages/ahg-security-clearance/` |
| IOM | `packages/ahg-information-object-manage/` |

: Abbreviations used for code locations

## Summary

| Standard | Meets | Partial | Gap | Rows |
| --- | --- | --- | --- | --- |
| MoReq2010 (core services and plug-in modules) | 42 | 24 | 1 | 67 |
| DoD 5015.2 v3 (chapter 2 and chapter 4) | 19 | 12 | 1 | 32 |

: Status counts per standard

The strongest areas are the disposal workflow (four-eyes approval, administrator-only destruction, destruction certificates, verification), declared-record locking, the signed audit chain and file fixity. The weakest are the things a dedicated records system builds in from the start: a single retention model, schedule versioning, cut-off, residual records, and a standard interchange format.

## Gaps closed in this change

Four small gaps were closed in code, each with a feature test in `tests/Feature/RecordsDisposalComplianceTest.php`.

**1. Destruction now actually destroys the files, including copies.** `digital_object.path` holds the web folder (`/uploads/r/<id>/`), not the file. `DisposalExecutionService::executeDestroy()` used that folder as if it were the file, never found it on disk, deleted nothing, and recorded "File not found on disk"; `verifyDestruction()` made the same mistake and so passed. Destruction now resolves the real file with `DigitalObjectService::resolveDiskPath()`, covers the master and its derivatives (reference copy, thumbnail), records a SHA-256 of each file before removal, and refuses the whole destruction up front if any file sits in an uploads folder another system still uses (`SharedUploadsGuard`, the same fail-closed rule the trash follows). Verification checks the same resolved files.

**2. A legal hold on an aggregation holds everything inside it.** The hold check used to look only at the record's own id, so an item inside a held file or series could be destroyed. `RM/src/Services/LegalHoldGuard.php` walks the parent chain (not the nested-set bounds, which some records lack) and is now used at initiation, legal clearance, execution of destruction and, newly, execution of transfer, which had no hold check at all.

**3. Destruction needs a reason, and the certificate carries it.** `initiateStore` requires a reason when the action is `destroy`, and `DisposalWorkflowService::initiateDisposal()` refuses a blank one as well, so the rule holds outside the web form. The destruction certificate's metadata now carries the reason, the disposal class and the whole four-eyes chain (initiated, recommended, approved, legally cleared and executed by whom, and when).

**4. Disposal events join the signed audit chain; one record exports with its history.** `RM/src/Services/Concerns/WritesDisposalAuditLog.php` wrote straight into `ahg_audit_log`, outside the Ed25519 hash chain, and stored the raw session id (the live session cookie) and the full IP address. It now writes through `AUD/src/Services/ChainedAuditWriter.php`, stores a keyed hash of the session id and honours `audit_ip_anonymize`, as `AuditLogger` does. New route `GET /admin/records/export/{ioId}` (`records.export`, `RM/src/Services/RecordExportService.php`) downloads one record as JSON: its description in every language, component checksums, declaration, disposal class, holds (own and inherited), disposal actions, destruction certificates, the change trail with its chain columns so a receiver can verify it, and the request trail. Session ids and user agents are left out, and the export is itself audited.

The raw session ids already stored by the old writer are still in `ahg_audit_log` rows with `module = 'records-manage'`. Clearing them is a data change and has not been done.

## MoReq2010

### System services

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-SYS-01 | Every entity has a system identifier that is unique and never reused | Partial | Integer keys from the shared `object` table (class table inheritance) plus slugs. Unique within one installation, not globally unique (no UUID on records). Audit rows do carry a UUID (`ahg_audit_log.uuid`). |
| MQ-SYS-02 | Every entity records when it was created and last modified | Meets | `object.created_at`, `object.updated_at`; RM tables carry `created_at`/`updated_at`. |
| MQ-SYS-03 | Every entity keeps an event history: who did what, when | Partial | Request trail `security_audit_log` (`app/Http/Middleware/AuditLog.php`, every web request, with field diffs on edits) and change trail `ahg_audit_log`. Not every service writes an entity-level event: legal hold place/release and disposal-class assignment appear only in the request trail. |
| MQ-SYS-04 | Event history cannot be altered without detection | Partial | Ed25519/JCS hash chain in `AUD/src/Services/ChainedAuditWriter.php`, checked by `php artisan auditlog:verify-chain`. Admin, authentication, security and (since this change) disposal events are chained; views, downloads and searches are not, by design. `audit:prune` deletes rows older than `audit_retention_days` (default 365). |
| MQ-SYS-05 | Access to every entity is controlled | Meets | `acl_permission` (user, group, object, action, grant/deny), `CORE/src/Services/AclService.php`, `acl:*` route middleware on edit/delete routes (`IOM/routes/web.php`). |
| MQ-SYS-06 | Administrative functions are restricted to administrators | Meets | `admin` middleware on every `/admin/records/*` route (`RM/routes/web.php`); `AclService::isAdministrator()` for destruction. |

: MoReq2010 system services

### User and group service

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-UG-01 | Create, modify and deactivate users | Meets | `packages/ahg-user-manage/` routes `/user/add`, `/user/{slug}/edit`, `/admin/users`; `user.active`. |
| MQ-UG-02 | Maintain groups and their membership | Meets | `acl_group`, `acl_user_group`; `/admin/acl`, `/admin/acl/group/new` (`packages/ahg-acl/routes/web.php`). |
| MQ-UG-03 | Users and groups referenced in event histories stay resolvable | Partial | Users can be deactivated rather than deleted. Audit rows keep `username`/`user_email` alongside `user_id`, so the actor survives a user deletion; no code was found that blocks deleting a user referenced by the audit trail. |
| MQ-UG-04 | Users are authenticated before any access | Meets | Laravel authentication; MFA (TOTP, WebAuthn, OTP) with per-tenant policy, `SEC/src/Http/Middleware/EnforceMfaPolicy.php`, `/admin/security/mfa-policy`. |

: MoReq2010 user and group service

### Model role service

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-MR-01 | Roles define the functions a user may perform | Partial | ACL actions (read, create, update, delete, translate, export and others) granted per group in `acl_permission`. The action set is fixed in code, not a configurable function catalogue. |
| MQ-MR-02 | Roles can be granted on a specific entity | Partial | `acl_permission.object_id` allows per-object grants; repository-scoped accounts (`app/Http/Middleware/ScopedAccountGuard.php`, heratio#1776). How per-object grants inherit down a hierarchy was not re-verified for this mapping. |
| MQ-MR-03 | Separation of duties for sensitive functions | Meets | Disposal: the initiator cannot approve, the approver cannot legally clear, and neither can execute (`RM/src/Services/DisposalWorkflowService.php` `approve()`, `clearLegal()`; `DisposalExecutionService::executeDestroy()`), heratio#1374. |

: MoReq2010 model role service

### Classification service

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-CL-01 | Maintain a hierarchical classification scheme (file plan) | Meets | `rm_fileplan_node` (nested set), `RM/src/Services/FilePlanService.php`; `/admin/records/fileplan`, `/tree`, `/{id}/move`. |
| MQ-CL-02 | Import a classification scheme | Meets | `RM/src/Services/FilePlanImportService.php` (spreadsheet, directory, XML); `/admin/records/fileplan/import/*`. |
| MQ-CL-03 | A class carries a default disposal schedule that its records inherit | Partial | `rm_fileplan_node.disposal_class_id`. A record gets the class's schedule only when a classification rule assigns it (`ClassificationRuleService::classifyIO()`); there is no automatic inheritance when a record is filed under a node by hand. |
| MQ-CL-04 | Records can be classified automatically on capture | Meets | `rm_classification_rule` (`apply_on`, priority), `ClassificationRuleService::classify()`, `classifyBatch()`; `/admin/records/classification`, `/run-batch`, `/{id}/test`. |
| MQ-CL-05 | Reclassification is recorded | Partial | `rm_classification_log` records rule-driven classification; `FilePlanService::moveNode()` moves nodes (subtree-safe, see `tests/Feature/ClosureHierarchyReadsTest.php`). A per-record history of manual reclassification is only in the request trail. |
| MQ-CL-06 | Classes can be closed to new records | Partial | `rm_fileplan_node.status` exists (default `active`); closing a node does not stop new records being filed under it. |

: MoReq2010 classification service

### Record service

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-RC-01 | Capture records and aggregations of records | Meets | `information_object` hierarchy (fonds to item); `packages/ahg-ingest/` (CSV and batch), `packages/ahg-scan/` (watched folders, scan API). |
| MQ-RC-02 | Declare a record | Meets | `record_declaration`; `INT/src/Services/RecordDeclarationService.php`; `/integrity/declare-record`, `/integrity/declarations/{ioId}/approve`. |
| MQ-RC-03 | A declared record's content and core metadata cannot be changed | Meets | `CORE/src/Http/Middleware/DeclaredRecordLock.php` (`record.unlocked`) on description edit/update/delete and every file upload/replace/delete route in `IOM/routes/web.php`; returns 423 for JSON. Test: `tests/Feature/RecordsLifecycleTest.php::test_a_declared_record_is_read_only`. |
| MQ-RC-04 | Declaration can require approval | Meets | `pending_approval` then `declared`; optional "Record Declaration" workflow (`ahg_workflow`). |
| MQ-RC-05 | Records hold electronic components with integrity checks | Meets | `digital_object.checksum`, `preservation_checksum`; nightly `ahg:preservation-fixity --notify` (`CORE/src/Commands/PreservationFixityCommand.php`) alerts administrators. |
| MQ-RC-06 | Earlier versions of a component are kept | Meets | `CORE/src/Services/FileVersionService.php`, `ahg_file_version`; restore route `io.digitalobject.version.restore`. |
| MQ-RC-07 | Deletion outside disposal is controlled and recoverable | Partial | `acl:delete` on delete routes; declared records locked; deleted files go to a trash with restore (`CORE/src/Services/DigitalObjectTrash.php`, `ahg:trash-purge`). Deleting an undeclared description leaves no residual entity. |
| MQ-RC-08 | Records can be moved to a less active tier without losing protection | Meets | `ahg:records-archive` (`CORE/src/Console/Commands/RecordsArchiveCommand.php`): declared records inactive for `records_archive_after_days` become `archived`, stay read-only, held records are skipped. The tier is a status, not a storage move. |
| MQ-RC-09 | Vital records are identified and reviewed | Meets | `RM/src/Services/VitalRecordService.php`; `/integrity/vital-records`, `/overdue`, `/flag`, `/{id}/review`. |
| MQ-RC-10 | Scheduled reviews of records | Meets | `rm_review_schedule`, `RM/src/Services/ReviewScheduleService.php`; `/admin/records/reviews`. |

: MoReq2010 record service

### Metadata model service

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-MD-01 | Metadata elements are defined, with rules for each entity type | Partial | The element set is fixed by the descriptive standards implemented (ISAD(G) and the sector standards); controlled values come from the Dropdown Manager (`ahg_dropdown`). User-defined element definitions in the MoReq2010 sense are not provided by the records module. |
| MQ-MD-02 | Metadata can be held in more than one language | Meets | `*_i18n` tables keyed by culture, e.g. `information_object_i18n`. |
| MQ-MD-03 | Metadata changes are recorded | Meets | Edit diffs in `security_audit_log` (`audit.diff` request attribute in `app/Http/Middleware/AuditLog.php`); `AuditLogger::logUpdate()` keeps old and new values of changed fields. |

: MoReq2010 metadata model service

### Disposal scheduling service

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-DS-01 | Disposal schedules hold a retention trigger, period and disposal action | Meets | `rm_retention_schedule`, `rm_disposal_class` (`retention_period_years`/`_months`, `retention_trigger`, `disposal_action`, `citation`); `/admin/records/schedules`, `/classes/*`. |
| MQ-DS-02 | Schedules are approved before use and versioned when changed | Partial | Draft to active via `/admin/records/schedules/{id}/approve`. `RetentionScheduleService::supersede()` exists but no route calls it; editing an active schedule changes it in place and does not raise `version`. |
| MQ-DS-03 | A disposal date is calculated for each record | Meets | `DisposalClassService::calculateDisposalDate()`, `rm_record_disposal_class.calculated_disposal_date` with override date and reason; `/admin/records/record-class/{ioId}`. |
| MQ-DS-04 | Event-based retention triggers | Partial | `retention_trigger_event` and `RetentionService::fireRetentionEvent()`; `/integrity/retention-events`. These feed `integrity_retention_policy`, a second retention model beside `rm_disposal_class`; the two are not unified. |
| MQ-DS-05 | Records due for disposal are listed for action | Partial | `RetentionService::scanEligible()` fills `integrity_disposition_queue` (`/integrity/disposition`) from `integrity_retention_policy`. `rm_record_disposal_class.calculated_disposal_date` does not feed any due list. |
| MQ-DS-06 | Disposal is reviewed and confirmed before it happens | Meets | `rm_disposal_action` states pending, recommended, approved, cleared, executed; `/admin/records/disposal/{id}/recommend`, `/approve`, `/clear-legal`, `/reject`. |
| MQ-DS-07 | Disposal actions include destroy, transfer, retain and review | Meets | `DisposalController::initiateStore()`, `DisposalExecutionService::executeDestroy()`, `executeTransfer()` (a BagIt AIP via `OaisLifecycleService` for transfer to archives), `executeRetain()`. |
| MQ-DS-08 | Destruction removes all components, including copies | Meets | Closed in this change (gap 1): master and derivatives resolved and removed; shared-folder files refused. |
| MQ-DS-09 | A residual record with minimal metadata remains after destruction | Partial | The description is kept in full, `record_declaration.status` becomes `destroyed`, the certificate links back. Metadata is not reduced to a defined residual set. |
| MQ-DS-10 | Every disposal step is recorded in the event history | Meets | `WritesDisposalAuditLog` on initiate, recommend, approve, legal clearance, reject, cancel and every execution; chained since this change (gap 4). Timeline on `/admin/records/disposal/{id}`. |
| MQ-DS-11 | Destruction produces evidence | Meets | `destruction_certificate` (DC-YYYY-NNNNN, content hash, per-file hashes, reason and approval chain since this change); `/integrity/certificates/{id}`; verification `/admin/records/disposal/{id}/verify`. |
| MQ-DS-12 | Destruction must be justified | Meets | Closed in this change (gap 3). |
| MQ-DS-13 | Only authorised users can destroy | Meets | Administrator-only check in `executeDestroy()` plus separation of duties. |

: MoReq2010 disposal scheduling service

### Disposal holding service

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-DH-01 | Place a hold with a reason; release it with a reason | Partial | `INT/src/Services/LegalHoldService.php`; `/integrity/holds` (place requires a reason). Release requires `release_reason` in the form but the table `integrity_legal_hold` has no column for it, so the reason is dropped. |
| MQ-DH-02 | A held record cannot be disposed of | Meets | Checked at initiation, legal clearance and execution of destruction and transfer (`RM/src/Services/LegalHoldGuard.php`). Test: `test_a_hold_on_the_series_holds_the_items_inside_it`. |
| MQ-DH-03 | A hold on an aggregation applies to its contents | Meets | Closed in this change (gap 2). |
| MQ-DH-04 | A held record cannot be deleted by any other route | Gap | The description delete route and `DigitalObjectService::delete()` check the declared-record lock, not holds. An undeclared record under hold can still be deleted. |
| MQ-DH-05 | Placing and releasing holds is in the event history | Partial | Request trail only (`security_audit_log`); `LegalHoldService` writes no change-trail entry. Hold history per record: `/integrity/holds/{ioId}/history`. |
| MQ-DH-06 | Holds can be listed and reported | Meets | `/integrity/holds`, `LegalHoldService::getHoldCounts()`, `/integrity/holds/check/{ioId}`. |
| MQ-DH-07 | One hold can cover many entities | Partial | One hold row per record; covering a set is done by holding the aggregation above it (gap 2). |
| MQ-DH-08 | Held records are excluded from automatic processes | Meets | `RetentionService::scanEligible()` and `ahg:records-archive` skip held records (directly held; aggregation inheritance is enforced at disposal). |

: MoReq2010 disposal holding service

### Searching and reporting service

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-SR-01 | Search on metadata and full text | Meets | Elasticsearch (`packages/ahg-search/`), `/search`, `/search/advanced`, semantic `/search/semantic`; GLAM browse with filters (`packages/ahg-display/`). |
| MQ-SR-02 | Search results respect access rules | Partial | `CORE/src/Services/DisclosureGate.php` removes unpublished, ICIP-restricted, ODRL-prohibited, embargoed and classified records from public surfaces (OAI, APIs, exports). Clearance-based trimming of staff search results was not found. |
| MQ-SR-03 | Reports on records, disposal and compliance | Meets | Report builder (`packages/ahg-reports/`, `report_definition` and related tables); `/admin/records/compliance` assessments with automatic checks (`RM/src/Services/ComplianceReportService.php`); disposal queue and history. |
| MQ-SR-04 | Reports on the event history | Meets | `/admin/audit`, `/admin/audit/statistics`, `/admin/audit/export` (CSV), `/admin/audit/entity/{type}/{id}`; `auditlog:report` command. |

: MoReq2010 searching and reporting service

### Export service

| Id | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- |
| MQ-EX-01 | Export a record with its metadata, components and event history | Partial | `GET /admin/records/export/{ioId}` (gap 4): metadata, RM state, component checksums, change and request trails. File content is not included; the format is Heratio's own JSON, not the MoReq2010 XML schema. |
| MQ-EX-02 | Export whole aggregations and the classification scheme | Partial | EAD and CSV export of hierarchies (`packages/ahg-export/`, `/export/ead`, `/export/csv`). The file plan and the disposal schedules can be imported but not exported. |
| MQ-EX-03 | The export can be verified by the receiver | Partial | The change trail is exported with `seq`, `prev_hash`, `entry_hash`, `signature`, `kid`, so it can be checked against the public key; there is no signed manifest over the export file itself. |
| MQ-EX-04 | After transfer, the source records are marked as transferred | Meets | `executeTransfer()` sets `record_declaration.status = 'transferred'` and records the AIP id. |
| MQ-EX-05 | Exports are themselves recorded | Meets | `RecordExportService` writes an `export` event to the chained audit trail. |

: MoReq2010 export service

### Plug-in modules (relevant to the bid)

| Id | Module | Status | Evidence |
| --- | --- | --- | --- |
| MQ-PM-01 | User interface (web) | Meets | Browser interface for every function above; Bootstrap 5 theme (`packages/ahg-theme-b5/`). |
| MQ-PM-02 | Electronic components and file formats | Meets | Format identification (`ahg:preservation-identify`), checksums, fixity, versions, derivatives. |
| MQ-PM-03 | E-mail records | Partial | `.eml` upload, parsing of sender, recipients, subject and dates, classification and declaration (`RM/src/Services/EmailCaptureService.php`, `/admin/records/emails`). No live mailbox connector. |
| MQ-PM-04 | Import of records and metadata | Partial | Ingest wizard (`packages/ahg-ingest/`), scan capture (`packages/ahg-scan/`), file plan import. No import of MoReq2010 XML. |
| MQ-PM-05 | Scanned and physical records | Meets | Scan pipeline with watched folders; descriptions without a digital object stand for physical records. |

: MoReq2010 plug-in modules

Security classification is scored once, under DoD chapter 4 below.

## DoD 5015.2 version 3

### Chapter 2 - baseline requirements

| Id | Ref | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- | --- |
| DOD-01 | C2.1 (approx.) | Manage records whatever their medium | Meets | Descriptions with or without digital objects; all disposal functions work on either. |
| DOD-02 | C2.1 (approx.) | Handle dates correctly (four-digit years, leap years) | Meets | MySQL `DATE`/`DATETIME`, Carbon date arithmetic in `calculateDisposalDate()`. |
| DOD-03 | C2.2.1 | Create and maintain file plan components with their metadata | Partial | `rm_fileplan_node` (code, title, description, disposal class, retention period, disposal action, department). The vital-record indicator sits on records, not on file plan categories. |
| DOD-04 | C2.2.1 | Only authorised users may change the file plan | Meets | `admin` middleware on `/admin/records/fileplan/*`. |
| DOD-05 | C2.2.2 | Disposition schedules: time, event and time-event based | Partial | Periods and triggers exist (MQ-DS-01, MQ-DS-04), split across two retention models. |
| DOD-06 | C2.2.2 | Link disposition instructions to file plan categories | Meets | `rm_fileplan_node.disposal_class_id`; rule-based assignment to records. |
| DOD-07 | C2.2.2 (approx.) | Cut off (close) folders on a schedule | Gap | No cut-off processing. |
| DOD-08 | C2.2.3 | Declare and file records with the mandatory record metadata | Partial | Declaration exists (MQ-RC-02). The DoD mandatory metadata set (originator, date filed, media type, format and so on) is not enforced as required at declaration. |
| DOD-09 | C2.2.3 | Declared records cannot be changed or deleted | Meets | `DeclaredRecordLock` (MQ-RC-03). |
| DOD-10 | C2.2.4 | File e-mail with its transmission data | Partial | `rm_email_capture` (from, to, cc, subject, sent and received dates, attachment count); upload of `.eml` only. |
| DOD-11 | C2.2.5 | Store records so that they cannot be changed undetected | Meets | Checksums and nightly fixity with alerts (MQ-RC-05); versions (MQ-RC-06). |
| DOD-12 | C2.2.6 (approx.) | Freeze and unfreeze records (holds) | Partial | Holds block disposal, including inherited holds (MQ-DH-02, MQ-DH-03); they do not block ordinary deletion (MQ-DH-04); the release reason is not stored (MQ-DH-01). |
| DOD-13 | C2.2.6 (approx.) | Identify records eligible for disposition and present them for review | Partial | MQ-DS-05. |
| DOD-14 | C2.2.6 (approx.) | Identify vital records and cycle their review | Meets | MQ-RC-09. |
| DOD-15 | C2.2.6 (approx.) | Transfer records, with their metadata, to another custodian | Partial | `executeTransfer()`; BagIt AIP for transfer to archives; transfer to an external body records the destination only. |
| DOD-16 | C2.2.6 (approx.) | Destroy records only after approval, by authorised users | Meets | Approval chain with separation of duties; administrator-only execution (MQ-DS-06, MQ-DS-13). |
| DOD-17 | C2.2.6 (approx.) | Destroyed records cannot be recovered | Partial | Files are deleted with `unlink()`; there is no overwrite, and the certificate records the method truthfully as `file_deletion`. Backups and storage snapshots are outside the disposal process. |
| DOD-18 | C2.2.6 (approx.) | Keep evidence of destruction | Meets | Destruction certificate and verification (MQ-DS-11). |
| DOD-19 | C2.2.7 | Access controls by user and group | Meets | MQ-SYS-05, MQ-UG-02. |
| DOD-20 | C2.2.8 | Audit actions on records: who, what, when, on which record | Meets | `security_audit_log` and `ahg_audit_log` (MQ-SYS-03); disposal steps in the chained trail. |
| DOD-21 | C2.2.8 | Protect audit data and allow it to be kept as records | Partial | Signed chain for admin, auth, security and disposal events (MQ-SYS-04). `audit:prune` deletes old rows after `audit_retention_days` unless set to 0. |
| DOD-22 | C2.2.8 | Report and export audit data | Meets | MQ-SR-04. |
| DOD-23 | C2.2.9 | Back up and recover the system | Meets | `packages/ahg-backup/`: `/admin/backup`, `/admin/restore`. |
| DOD-24 | C2.2.10 (approx.) | Search and retrieve records by metadata | Meets | MQ-SR-01. |
| DOD-25 | C2.2.10 (approx.) | View and print records with their metadata | Meets | Record show page with its metadata and files; record export (MQ-EX-01). |
| DOD-26 | Chapter 6 (approx.) | Export records with metadata for interchange | Partial | MQ-EX-01 to MQ-EX-03. |

: DoD 5015.2 chapter 2

### Chapter 4 - classified records

Included because government records in South Africa carry protective markings under the Minimum Information Security Standards; the DoD chapter 4 functions map onto the same needs.

| Id | Ref | Requirement (paraphrased) | Status | Evidence |
| --- | --- | --- | --- | --- |
| DOD-C4-01 | C4.1 (approx.) | Record the classification of each record: level, reason, review and declassification dates, handling | Meets | `ahg_io_security` (classification, reason, review date, declassify date, handling instructions, inherit to children) on the record edit form (`IOM/src/Controllers/InformationObjectController.php`); `object_security_classification` with compartments. |
| DOD-C4-02 | C4.1 (approx.) | Record who classified and on what authority | Partial | `SecurityClearanceService::classifyObject()` keeps the user and reason; no derived-from or classification-guide reference field. |
| DOD-C4-03 | C4.1 (approx.) | Limit access to classified records by user clearance | Partial | Clearances, compartments and access requests (`SEC/routes/web.php`, `/admin/security-clearance/*`); classified records are kept off public surfaces (`DisclosureGate::classifiedIds()`); share links and version control check clearance. Staff record views and search are not gated by clearance level. |
| DOD-C4-04 | C4.1 (approx.) | Schedule and carry out downgrading and declassification | Meets | `object_declassification_schedule`, `CORE/src/Services/DeclassificationScheduleService.php`, `/admin/security-clearance/declassification/{id}`, due list on the dashboard. |
| DOD-C4-05 | C4.1 (approx.) | Audit classification changes and access to classified records | Meets | `SecurityClearanceService::logSecurityAudit()`; `/admin/security-clearance/audit`, `/audit/object-access`, `/audit/export`. |
| DOD-C4-06 | C4.1 (approx.) | Mark classified output | Meets | Watermark settings and traceable watermarks (`/admin/security-clearance/watermark-settings`, `/trace-watermark`). |

: DoD 5015.2 chapter 4

DoD chapter 3 (non-mandatory) and chapter 5 (Privacy Act and FOIA) are out of scope for this mapping.

## Roadmap - gaps not closed here

Estimates are for one developer who knows the code base, including a feature test, and exclude review and release.

| Gap | Rows | Effort | Notes |
| --- | --- | --- | --- |
| Holds block every deletion path (description delete, file delete, trash purge) | MQ-DH-04, DOD-12 | 0.5 day | A hold check beside `DeclaredRecordLock`. Touches `IOM/routes/web.php`, a locked path. |
| Store the hold release reason; change-trail events for hold place and release | MQ-DH-01, MQ-DH-05 | 0.5 day | Adds a column to `integrity_legal_hold`. |
| Destruction beyond file deletion | DOD-17 | Policy decision | The certificate now records `file_deletion` (relabelled from `secure_delete` on 20 Nov 2026). Overwriting is of little use on the NAS (snapshots, copy-on-write); a documented backup and snapshot policy is the honest complement. |
| Protect chained and disposal audit rows from `audit:prune` | MQ-SYS-04, DOD-21 | 0.5 day | |
| Disposal action types from the Dropdown Manager | - | 0.5 day | The initiate form and its validation list the types in code; the `rm_disposal_action` taxonomy is already seeded. |
| One retention model, and a due-for-disposal list from `calculated_disposal_date` | MQ-DS-04, MQ-DS-05, DOD-05, DOD-13 | 2 to 3 days | `rm_disposal_class` and `integrity_retention_policy` run side by side; the disposal initiate form lists policies from the second while `rm_disposal_action.disposal_class_id` implies the first. |
| Schedule versioning when an active schedule is edited; a route for supersede | MQ-DS-02 | 1 day | |
| Automatic inheritance of a class's disposal schedule when a record is filed | MQ-CL-03 | 1 day | |
| Residual record after destruction | MQ-DS-09 | 1 to 2 days | Needs a policy decision on the residual element set. |
| Cut-off processing | DOD-07 | 2 days | |
| DoD mandatory record metadata enforced at declaration | DOD-08 | 2 days | |
| Clearance enforcement on staff views and search | DOD-C4-03, MQ-SR-02 | 2 to 3 days | Security-sensitive; needs its own review. |
| Export of the file plan and disposal schedules | MQ-EX-02 | 1 day | |
| Live mailbox capture (IMAP or Microsoft Graph) | MQ-PM-03, DOD-10 | 3 to 5 days | |
| MoReq2010 XML export and import | MQ-EX-01, MQ-PM-04 | 5 to 10 days | Only worth doing if a buyer asks for MoReq2010 interchange. |
| Globally unique record identifiers | MQ-SYS-01 | 1 to 2 days | A UUID per record, carried in exports. |
| Certification | all | not planned | Decision of 16 November 2026: compliance is shown against ICA-Req (ISO 16175). |

: Roadmap of remaining gaps
