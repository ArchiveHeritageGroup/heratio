# Privacy Compliance - Phase 1 (PII scan, Article 30, DPIA)

Issue #669 Phase 1 ships three additions on top of the existing PSIS-port privacy package (packages/ahg-privacy):

1. A pure-PHP PII scan engine (`PiiScanService`) that finds emails, phones, national IDs, credit cards, IPs and dates of birth in free text. No LLM dependency.
2. A regulator-aligned GDPR Article 30 Record of Processing Activities (RoPA) register in a new sidecar table `ahg_processing_activity`.
3. A GDPR Article 35 Data Protection Impact Assessment workflow with four steps (necessity, risks, mitigation, sign-off) writing tamper-evident sign-off rows through the audit-trail chain (#676 Phase 5).

Phase 1 deliberately keeps these tables ahg_-prefixed and decoupled from the inherited PSIS POPIA register (`privacy_processing_activity`). The two registers are linkable via `ahg_processing_activity.linked_psis_id`. Subject rights portal, auto-deletion and multi-jurisdiction tracking remain Phase 2+.

## New tables

- `ahg_pii_scan_report` - one row per scan invocation. `hits_by_type` JSON, `findings` JSON capped at 500 entries, status enum (`pending`, `reviewed`, `redacted`, `accepted_risk`).
- `ahg_processing_activity` - GDPR Article 30 register: name, purpose, lawful basis, categories of data and subjects, recipients, retention period, security measures, cross-border transfers + safeguards, DPO contact. Unique on `name`. Auto-seeded with five default activities (user authentication, archival cataloguing, AI inference logging, audit trail, email notifications).
- `ahg_dpia` - DPIA register: name, optional `processing_activity_id` link, necessity / risks / mitigation / DPO opinion blocks, status enum (`draft`, `review`, `completed`, `archived`), `signed_off_by_user_id` + `signed_off_at`.

Install SQL: `packages/ahg-privacy/database/install-phase1.sql`. Idempotent (every CREATE IF NOT EXISTS, every INSERT IGNORE). Auto-run on first boot from `AhgPrivacyServiceProvider`.

## PII scan engine

`AhgPrivacy\Services\PiiScanService`:

- `scan(string $text): array` returns ordered findings, one per detection: `{type, value, offset_start, offset_end, confidence}`.
- `scanAndPersist(string $text, ?int $ioId, ?int $userId): ?int` persists a row in `ahg_pii_scan_report` and returns its id.
- Configurable per-jurisdiction via `ahg_setting.privacy_jurisdiction` (`gdpr` | `popia` | `uk_gdpr` | `ccpa`). The default `gdpr` mode scans the union of POPIA, UK GDPR and CCPA national-id + phone patterns to maximise recall in a multi-tenant deployment.
- Credit cards are Luhn-validated; non-Luhn matches are discarded. SA ID numbers are Luhn-checked; failed checksums survive with reduced confidence (useful signal at review time).
- Hard cap: `MAX_FINDINGS = 500` per scan.

CLI: `php artisan privacy:scan-io {ioId} [--jurisdiction=...] [--no-persist]`.

## Article 30 export

CLI: `php artisan privacy:article-30-export [--format=csv|json|markdown] [--out=path]`. Output is regulator-ready: the JSON snapshot includes `controller` (from `ahg_setting.privacy_controller_name`), `generated_at` and `activity_count`, plus a flattened activity list.

Admin UI: `/admin/privacy/article-30` (Bootstrap 5) for CRUD; download buttons emit the three formats from the same `Article30Service`.

## DPIA workflow

Admin UI: `/admin/privacy/dpia`. The form is a four-step pill nav: necessity, risks, mitigation, sign-off. Sign-off records the user id, a UTC timestamp, sets `status='completed'`, and writes an `ahg_audit_log` row via `AhgAuditTrail\Services\ChainedAuditWriter` so the assessment becomes part of the tamper-evident hash chain. If the chain writer or Ed25519 signing key is unavailable the sign-off still records (unsigned fallback) - the workflow never blocks on chain issues.

Archiving emits a chain row of action `dpia.archive`. Both events carry the new status and the linked processing-activity id in `new_values`.

## Wiring summary

- Models: `AhgPrivacy\Models\{PiiScanReport,ProcessingActivity,Dpia}`.
- Services: `AhgPrivacy\Services\{PiiScanService,Article30Service,DpiaService}`.
- Controllers: `AhgPrivacy\Controllers\{Article30Controller,DpiaController}` (existing `PrivacyController` untouched).
- Commands: `privacy:scan-io`, `privacy:article-30-export`. Existing `privacy:check-overdue-dsars` retained.
- Routes: added under the existing `/admin/privacy` prefix with the `dp.enabled` + `auth` middleware stack.
- Service provider boot installs Phase 1 schema idempotently and registers the new commands.

## Open Phase 2+ work

- Subject rights portal (DSAR self-service for data subjects).
- Auto-deletion / disposal sweep tied to the retention schedules already present in `privacy_retention_schedule`.
- Multi-jurisdiction tracking (which subject is governed by which regime).
- UK GDPR + CCPA/CPRA specific extensions on top of the GDPR baseline.
