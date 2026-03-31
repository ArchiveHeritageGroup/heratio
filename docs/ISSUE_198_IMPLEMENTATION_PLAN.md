# Issue #198 — Implementation Plan
# Public Portal + Archives-led UX + Hard Multi-Tenancy + Governance/Hardening

**The Archive and Heritage Group (Pty) Ltd**
**Date:** 2026-02-28
**Framework:** v2.8.2 | **Plugins:** 79
**Tracking Issue:** ArchiveHeritageGroup/atom-extensions-catalog#198
**Related Issues:** #185 (Security DevOps), #197 (Security Hardening)

---

## Executive Summary

This plan addresses issue #198's five EPICs through a phased delivery over 6 milestones. Codebase analysis reveals the system is **60-70% ready** — substantial infrastructure exists for multi-tenancy (ahgMultiTenantPlugin v1.2.0), search (ahgDiscoveryPlugin + ahgSemanticSearchPlugin), rights enforcement (3-layer model), and research workflows (ahgResearchPlugin v3.0.0 with 87 tables). The primary gaps are **hard isolation** (uploads/search/cache/audit lack tenant_id), **search explainability UI**, **bulk edit operations**, and **security hardening** (unsafe `unserialize()`, missing upload MIME validation).

---

## Current State Summary

### What Already Exists (Strength Map)

| Capability | Maturity | Plugin(s) |
|-----------|----------|-----------|
| Tenant CRUD, domain routing, user roles, branding | 80% | ahgMultiTenantPlugin (DISABLED) |
| GLAM-aware display profiles + dynamic faceting | 95% | ahgDisplayPlugin |
| Multi-strategy discovery search | 80% | ahgDiscoveryPlugin |
| Template-driven description forms (14 field types, 5 seeds) | 100% | ahgFormsPlugin |
| Publish gates (8 rule types, editor bridge) | 100% | ahgWorkflowPlugin |
| Change tracking + field-level audit | 100% | ahgWorkflowPlugin + ahgAuditTrailPlugin |
| Duplicate detection (6 algorithms, real-time check) | 90% | ahgDedupePlugin |
| 3-layer rights (ACL + security clearance + embargo) | 95% | ahgRightsPlugin + ahgExtendedRightsPlugin + ahgSecurityClearancePlugin |
| Research platform (RO-Crate, annotations, bookings) | 95% | ahgResearchPlugin (87 tables) |
| Access/publish/reproduction requests | 90% | ahgAccessRequestPlugin + ahgRequestToPublishPlugin + ahgCartPlugin |
| REST API v2 + GraphQL + webhooks | 90% | ahgAPIPlugin + ahgGraphQLPlugin |
| Backup (5 presets, manifest tracking) | 80% | ahgBackupPlugin |
| Preservation (checksums, fixity, PREMIS, PRONOM) | 90% | ahgPreservationPlugin |
| Heritage discovery portal + contributor system | 85% | ahgHeritagePlugin (58 tables) |

### What's Missing (Gap Map)

| Gap | Impact | EPIC |
|-----|--------|------|
| Upload paths not tenant-scoped (`/uploads/r/{objectId}/`) | Data leakage | C1 |
| Elasticsearch — single global index, no tenant filter | Search leakage | C2 |
| Cache keys have no tenant namespace | Cache contamination | C3 |
| Audit tables missing `tenant_id` column | Compliance breach | C4 |
| No search ranking explainability UI | UX gap | A2 |
| No hierarchy breadcrumb + context blocks on record view | UX gap | A3 |
| No unified restrictions banner (rights/embargo/POPIA) | UX gap | A3 |
| No bulk edit operations (update N records at once) | Workflow gap | B2 |
| No external authority linking (Wikidata/VIAF/GeoNames) | Quality gap | B3 |
| `unserialize()` without `allowed_classes` in 9 files | RCE risk | D4 |
| No `finfo` MIME validation on uploads | Malware risk | D1 |
| No health endpoints (`/api/health`) | Ops gap | E2 |
| No automated backup scheduling | DR gap | E3 |
| No job retry/timeout (nohup pattern) | Reliability | E1 |

---

## Milestone Plan

### M0: Immediate Security Fixes (Week 1)
**Priority: CRITICAL — Do before any feature work**
**Scope:** Issues #197 partial, #185 partial

These are zero-feature-debt items that reduce risk immediately.

#### M0.1: Serialization Hardening (D4)
**Files to modify:**
| File | Line(s) | Change |
|------|---------|--------|
| `ahgMuseumPlugin/lib/Services/Getty/GettyCacheService.php` | 62, 198, 233 | Add `['allowed_classes' => false]` to all `unserialize()` calls; migrate to `json_encode`/`json_decode` |
| `ahgSemanticSearchPlugin/lib/Services/SemanticSearchService.php` | 255, 358, 409 | Replace `unserialize()` with `json_decode()` |
| `ahgSettingsPlugin/modules/ahgSettings/actions/handlers/*.php` | Multiple | Add `['allowed_classes' => false]` |
| `ahgInformationObjectManagePlugin` | ~1100 | Add `['allowed_classes' => false]` |
| `ahgThemeB5Plugin` | 48, 58, 110 | Add `['allowed_classes' => false]` |

**New file:** Move Getty cache from `/tmp/getty_cache/` to `/var/cache/atom/getty/` with `0700` permissions.

#### M0.2: Upload Hardening (D1)
**Files to modify:**
| File | Change |
|------|--------|
| `ahgAPIPlugin/modules/apiv2/actions/fileUploadAction.class.php` | Add `finfo_file()` MIME validation; add `basename()` on `type` parameter to prevent path traversal; add file size limit |
| `ahgDisplayPlugin/modules/digitalobject/actions/uploadAction.class.php` | Add `finfo_file()` MIME check alongside existing size check |

**New file:** `atom-framework/src/Services/FileValidationService.php`
- Extension allowlist (configurable via `ahg_settings`)
- MIME magic-bytes validation via `finfo`
- Max file size enforcement
- Called by all upload handlers

#### M0.3: API Path Traversal Fix
**File:** `ahgAPIPlugin/modules/apiv2/actions/fileUploadAction.class.php`
- Add `$type = basename($request->getParameter('type'))` before building upload path

**Deliverable:** Security fix release via `./bin/release patch "Security hardening: serialization, uploads, API path traversal"`

---

### M1: Hard Multi-Tenancy Foundation (Weeks 2-5)
**Scope:** EPIC C (complete) + EPIC A1 (tenant-scoped routing/branding)
**Plugin:** ahgMultiTenantPlugin (currently disabled)

#### M1.1: Upload Isolation (C1)
**Schema change:** Add `tenant_id INT NULL` to `digital_object` table (nullable for backward compat — existing objects get NULL = "default tenant")

**Files to modify:**
| File | Change |
|------|--------|
| `atom-framework/src/Services/Write/StandaloneDigitalObjectWriteService.php` | Upload path: `/uploads/r/{tenantCode}/{objectId}/` when tenant active; fallback to `/uploads/r/{objectId}/` when NULL |

**New files:**
| File | Purpose |
|------|---------|
| `ahgMultiTenantPlugin/database/migrations/002_tenant_isolation.sql` | ALTER `digital_object` ADD `tenant_id`; ALTER audit tables ADD `tenant_id`; ALTER cache infrastructure |
| `ahgMultiTenantPlugin/lib/Services/TenantFileService.php` | Tenant-aware upload path resolution, cleanup on tenant deletion, migration of existing uploads |

**Data migration CLI:** `php symfony tenant:migrate-uploads` — assigns existing digital objects to default tenant, creates tenant subdirectories, moves files

#### M1.2: Search Isolation (C2)
**Strategy:** Single index with mandatory tenant filter (simpler than per-tenant indexes; avoids reindex on tenant creation)

**Files to modify:**
| File | Change |
|------|--------|
| `ahgCorePlugin/lib/Services/ElasticsearchService.php` | Add `tenant_id` field to all indexed documents; apply `TenantQueryFilter::getElasticsearchFilter()` to ALL queries |
| `ahgMultiTenantPlugin/lib/Filter/TenantQueryFilter.php` | Update `getElasticsearchFilter()` to use `tenant_id` (not just `repository.id`) |
| `atom-framework/src/Services/Search/SearchService.php` | Inject tenant context into all search calls; apply filter before query execution |

**Reindex CLI:** `php symfony tenant:reindex` — populates `tenant_id` field in ES for all existing documents

#### M1.3: Cache Isolation (C3)
**Files to modify:**
| File | Change |
|------|--------|
| `atom-framework/src/Services/CacheService.php` | Prefix cache keys with `tenant_{id}:`; add `clearTenantCache(int $tenantId)` method |
| `ahgDisplayPlugin/lib/Services/DynamicFacetService.php` | Pass tenant context to cache keys |

#### M1.4: Audit Isolation (C4)
**Schema change:** Add `tenant_id INT NULL` + `request_id VARCHAR(36) NULL` to:
- `ahg_audit_log`
- `ahg_audit_access`
- `ahg_audit_authentication`

**Files to modify:**
| File | Change |
|------|--------|
| `ahgAuditTrailPlugin/lib/Services/AhgAuditService.php` | Accept `tenant_id` parameter; auto-populate from TenantContext if available |

#### M1.5: Tenant-Scoped Branding (A1)
**Already implemented:** TenantBranding service + domain routing in ahgMultiTenantPlugin.
**Gap:** Need to activate and test.

**Files to modify:**
| File | Change |
|------|--------|
| `ahgMultiTenantPlugin/config/ahgMultiTenantPluginConfiguration.class.php` | Ensure `onContextLoadFactories` hook runs TenantResolver + TenantContext initialization |

**Verification tests:**
```bash
# Cross-tenant leakage test
curl -H "Host: tenant-a.heritage.example.com" https://localhost/browse | grep -c "tenant-b-data"
# Should return 0

# Upload isolation test
# Create file as tenant A, try to access as tenant B → expect 404

# Cache isolation test
# Set cache as tenant A, read as tenant B → expect NULL

# Audit test
mysql -u root archive -e "SELECT DISTINCT tenant_id FROM ahg_audit_log WHERE tenant_id IS NOT NULL"
```

**Deliverable:** `./bin/release minor "Hard multi-tenancy: upload/search/cache/audit isolation"`

---

### M2: Public Portal VNext — Search & Browse (Weeks 6-9)
**Scope:** EPIC A2, A3 partial
**Plugins:** ahgDisplayPlugin (bug fixes only — it's stable), ahgDiscoveryPlugin, ahgThemeB5Plugin overrides

#### M2.1: Hierarchy-Aware Search Ranking (A2)
**Strategy:** Boost ES scores for records whose parent fonds/series matches query terms.

**Files to modify:**
| File | Change |
|------|--------|
| `ahgDiscoveryPlugin/lib/Services/DiscoverySearchService.php` | Add `function_score` query with `match` on `parent_titles` field; boost factor 1.5 for fonds-level matches |

**Index change:** Add `parent_titles` field to ES mapping (denormalized from MPTT hierarchy)

#### M2.2: Search Explainability Panel (A2)
**New files:**
| File | Purpose |
|------|---------|
| `ahgDiscoveryPlugin/modules/discovery/templates/_searchExplain.php` | Sidebar panel showing: matched fields, boost factors, query expansion terms used |

**Files to modify:**
| File | Change |
|------|--------|
| `ahgDiscoveryPlugin/lib/Services/DiscoverySearchService.php` | Add `?explain=true` parameter to ES queries; parse `_explanation` from ES response |

**UI:** "Why this result?" expandable panel per search result showing:
- Which fields matched (title, scope_and_content, creator, etc.)
- Which synonyms/expansions contributed
- Hierarchy context boost applied

#### M2.3: Record View — Hierarchy Breadcrumb (A3)
**New files:**
| File | Purpose |
|------|---------|
| `ahgDisplayPlugin/modules/display/templates/_hierarchyBreadcrumb.php` | Breadcrumb trail: Fonds > Series > Sub-series > File > Item |
| `ahgDisplayPlugin/modules/display/templates/_childrenList.php` | Collapsible children list with counts |

**Implementation:** Use MPTT (`lft`/`rgt`) columns on `information_object` to walk the tree. Laravel QB query with `ancestor_slugs` for breadcrumb links.

#### M2.4: Record View — Restrictions Banner (A3)
**New files:**
| File | Purpose |
|------|---------|
| `ahgDisplayPlugin/modules/display/templates/_restrictionsBanner.php` | Unified banner showing: rights statement, embargo status, POPIA flag, security classification, access conditions |

**Implementation:** Call `RightsService`, `ExtendedRightsService`, `SecurityClearanceService` to build restriction summary. Display as Bootstrap 5 alert (color-coded: red=blocked, yellow=restricted, green=open).

#### M2.5: Provenance & Integrity Display (A3)
**New files:**
| File | Purpose |
|------|---------|
| `ahgDisplayPlugin/modules/display/templates/_provenanceBlock.php` | Show: acquisition source, digitization notes, checksum/fixity status, last verified date |

**Implementation:** Query `ahg_audit_log` for provenance chain + `preservation_checksum` for fixity data.

**Deliverable:** `./bin/release minor "Public portal: search explainability, hierarchy breadcrumb, restrictions banner"`

---

### M3: Description UX & Authority Workbench (Weeks 10-14)
**Scope:** EPIC B (complete)
**Plugins:** ahgFormsPlugin (minor), ahgWorkflowPlugin, ahgDedupePlugin, NEW: ahgBulkEditPlugin

#### M3.1: Bulk Edit with Diff Preview (B2)
**New plugin:** `ahgBulkEditPlugin`

**Database tables:**
| Table | Purpose |
|-------|---------|
| `ahg_bulk_edit_job` | Job tracking: query, fields, values, user, status, progress, error_log |
| `ahg_bulk_edit_snapshot` | Pre-edit snapshots for rollback (object_id, field, old_value, job_id) |

**New service:** `BulkEditService.php`
```
planBulkEdit(array $objectIds, array $fieldChanges, string $culture): array
    → returns [{object_id, field, old_value, new_value}] preview

executeBulkEdit(int $jobId, int $userId): void
    → async: iterates objects, applies changes via StandaloneInformationObjectWriteService
    → stores snapshots in ahg_bulk_edit_snapshot
    → logs each change to ahg_audit_log with correlation_id
    → emits workflow events

rollbackBulkEdit(int $jobId, int $userId): void
    → restores old_values from snapshots
    → logs rollback event
```

**Routes:**
| URL | Purpose |
|-----|---------|
| `/admin/bulk-edit` | Bulk edit wizard (select objects → choose fields → preview diff → confirm) |
| `/admin/bulk-edit/preview` | AJAX: show diff preview |
| `/admin/bulk-edit/execute` | AJAX: start async job |
| `/admin/bulk-edit/status/:jobId` | AJAX: poll progress |
| `/admin/bulk-edit/rollback/:jobId` | Rollback a completed bulk edit |
| `/admin/bulk-edit/history` | Browse past bulk edit jobs |

**Integration points:**
- ChangeSummaryService: reuse for diff computation
- AhgAuditService: log all changes with `correlation_id`
- WorkflowEventService: emit `bulk_edit_started`, `bulk_edit_completed`, `bulk_edit_rolled_back`

#### M3.2: Authority Workbench — Merge with Rollback (B3)
**Extend ahgDedupePlugin:**

**New tables:**
| Table | Purpose |
|-------|---------|
| `ahg_authority_merge_log` | Merge operations: source_ids, target_id, merge_map (JSON), user, created_at |
| `ahg_authority_merge_snapshot` | Pre-merge state: entity type, entity_id, full data JSON |

**New service:** `AuthorityMergeService.php` (in ahgDedupePlugin)
```
previewMerge(int $targetId, array $sourceIds): array
    → returns field-by-field comparison: which values kept, which discarded
    → identifies linked records (events, relations) that will be re-pointed

executeMerge(int $targetId, array $sourceIds, array $fieldChoices, int $userId): int
    → takes pre-merge snapshots
    → merges chosen fields into target
    → re-points all events, relations, contact info from sources to target
    → marks sources as redirects (or deletes)
    → logs merge in ahg_authority_merge_log + ahg_audit_log

rollbackMerge(int $mergeLogId, int $userId): bool
    → restores snapshots
    → re-creates source actors
    → re-points events/relations back
    → logs rollback
```

**Routes:**
| URL | Purpose |
|-----|---------|
| `/admin/authority/merge/:targetId` | Merge wizard (select fields, preview, confirm) |
| `/admin/authority/merge-history` | Browse past merges |
| `/admin/authority/rollback/:mergeId` | Rollback a merge |

#### M3.3: External Authority Linking (B3)
**Extend ahgSemanticSearchPlugin or create new service:**

**New service:** `ExternalAuthorityService.php`
```
searchWikidata(string $query, string $entityType): array
    → SPARQL query to wikidata.org, returns [{qid, label, description, sameAs}]

searchViaf(string $query): array
    → REST query to viaf.org, returns [{viafId, name, sources}]

searchGeoNames(string $query): array
    → REST query to api.geonames.org, returns [{geonameId, name, country, coordinates}]

linkAuthority(int $actorId, string $source, string $externalId): void
    → stores in new `ahg_authority_link` table
    → pulls enrichment data (dates, alternate names)
    → logs in audit trail

getLinkedAuthorities(int $actorId): array
    → returns all external links for actor
```

**New table:** `ahg_authority_link` (actor_id, source, external_id, external_label, cached_data JSON, linked_at, linked_by)

**UI:** Authority detail page shows "External Links" panel with search + link capability.

**Deliverable:** `./bin/release minor "Bulk edit with rollback, authority merge workbench, external authority linking"`

---

### M4: Digital Object Delivery & Public Requests (Weeks 15-17)
**Scope:** EPIC A4, A5

#### M4.1: Derivative Policy Engine (A4)
**New table:** `ahg_derivative_policy` (id, scope_type, scope_id, access_level, policy JSON, created_at)
- `scope_type`: global, repository, collection, object
- `access_level`: public, authenticated, researcher, admin
- Policy JSON: `{thumb: true, reference: true, master: false, watermark: true, max_dimension: 1200}`

**New service:** `DerivativePolicyService.php` (in atom-framework)
```
resolvePolicy(int $objectId, ?int $userId): array
    → cascading resolution: object → collection → repository → global
    → factors in user role + security clearance
    → returns {thumb, reference, master, watermark, max_dimension, streaming_allowed}

canAccess(int $objectId, string $derivativeType, ?int $userId): bool
    → resolvePolicy() → check if requested derivative is allowed

applyPolicy(int $objectId, string $derivativeType, ?int $userId): array
    → returns {allowed, watermark, max_dimension, reason}
```

**Integration:** IIIF manifest generation, digital object download handler, and API file endpoints all call `DerivativePolicyService::canAccess()` before serving.

#### M4.2: Unified Requester Dashboard (A5)
**New module in ahgAccessRequestPlugin** (or new lightweight plugin):

**New file:** `ahgAccessRequestPlugin/modules/security/templates/requesterDashboardSuccess.php`

**Implementation:** Aggregates across 3 request types:
1. `access_request` (ahgAccessRequestPlugin) — restricted material access
2. `request_to_publish` (ahgRequestToPublishPlugin) — publication requests
3. `ahg_order` (ahgCartPlugin) — reproduction orders

**UI:** Tabbed dashboard showing:
- All requests with status (pending/approved/denied/completed)
- Filter by type, date, status
- Click through to individual request detail

**Route:** `/my-requests` (public, authenticated users only)

#### M4.3: Automated Rights Gate Before Submission (A5)
**Extend PublishGateService** to also act as a "request gate":

**New method:**
```
evaluateRequestGate(int $objectId, string $requestType, int $userId): array
    → checks: user has researcher role, object not embargoed, object has reproduction rights, user signed terms
    → returns [{rule, status, message}]
```

**UI:** Before any request form submission, AJAX call to `evaluateRequestGate()`. If blockers exist, show them with instructions (e.g., "This object is under embargo until 2028-01-01").

---

### M5: Security & Ops Hardening (Weeks 18-22)
**Scope:** EPIC D (remaining) + EPIC E (complete)
**Related issues:** #185, #197

#### M5.1: Outbound HTTP Policy (D2)
**New service:** `atom-framework/src/Services/HttpClientService.php`
```
get(string $url, array $options): Response
post(string $url, array $data, array $options): Response

// Built-in protections:
// - RFC1918/link-local IP blocking (configurable allowlist)
// - Redirect limit (max 3)
// - Timeout enforcement (connect: 5s, total: 30s)
// - Request logging (tenant_id + request_id)
// - DNS resolution check before connect
```

**Files to modify:** Replace raw `curl_*()` and `file_get_contents()` in:
- ElasticsearchService (localhost exempt from IP check)
- GettySparqlService
- WebhookService (user-provided URLs get full validation)
- DOI plugin (DataCite API)
- Federation plugin (OAI-PMH endpoints)

#### M5.2: Shell Execution Policy (D3)
**New service:** `atom-framework/src/Services/ProcessService.php`
```
run(string $command, array $args, array $options): ProcessResult
    → $command must be in allowlist (python3, clamdscan, sf, tesseract, etc.)
    → all $args passed through escapeshellarg()
    → working directory constrained via realpath()
    → timeout enforcement
    → no secrets in argv (use env vars)
```

**Files to modify:** Replace any remaining `shell_exec()` calls with `ProcessService::run()`.

#### M5.3: Health Endpoints (E2)
**New files:**
| File | Purpose |
|------|---------|
| `ahgAPIPlugin/modules/apiv2/actions/healthAction.class.php` | `GET /api/health` — full health check |
| `ahgAPIPlugin/modules/apiv2/actions/healthLiveAction.class.php` | `GET /api/health/live` — liveness probe |
| `ahgAPIPlugin/modules/apiv2/actions/healthReadyAction.class.php` | `GET /api/health/ready` — readiness probe |

**Health checks:**
- Database connection (SELECT 1)
- Elasticsearch ping
- Upload directory writable
- PHP memory/version
- Disk space (warn if >90%)
- Queue worker running (if applicable)

**Response:**
```json
{
  "status": "healthy",
  "checks": {
    "database": {"status": "up", "latency_ms": 2},
    "elasticsearch": {"status": "up", "latency_ms": 15},
    "disk": {"status": "warning", "usage_pct": 92},
    "uploads": {"status": "up", "writable": true}
  },
  "version": "2.8.2"
}
```

#### M5.4: Job Queue Improvements (E1)
**Strategy:** Keep existing nohup/CLI pattern but add reliability:

**New table:** `ahg_job_queue` (id, job_type, payload JSON, status, attempts, max_attempts, last_attempt_at, timeout_seconds, started_at, completed_at, error_log, created_by)

**New service:** `JobQueueService.php`
```
enqueue(string $jobType, array $payload, array $options): int
    → options: {max_attempts: 3, timeout: 3600, priority: 'normal'}

processNext(): bool
    → picks oldest pending job, marks in_progress, executes
    → on failure: increment attempts, schedule retry with backoff (1min, 5min, 15min)
    → on timeout: mark failed, allow retry

getStatus(int $jobId): array
heartbeat(int $jobId): void  // worker calls every 30s
detectStale(int $timeoutMinutes): array  // find jobs without heartbeat
```

**CLI:** `php symfony queue:worker --daemon` — persistent worker process

**Systemd unit file:** `/etc/systemd/system/atom-worker.service`

#### M5.5: Backup Automation (E3)
**Files to modify:**
| File | Change |
|------|--------|
| `ahgBackupPlugin/lib/Services/BackupService.php` | Add `retentionCleanup(int $keepDays)` method; add `verifyBackup(int $backupId)` method |

**New files:**
| File | Purpose |
|------|---------|
| `ahgBackupPlugin/lib/task/backupScheduleTask.class.php` | CLI: `php symfony backup:schedule` — configurable daily/weekly |
| `ahgBackupPlugin/lib/task/backupCleanupTask.class.php` | CLI: `php symfony backup:cleanup --older-than=30d` |
| `ahgBackupPlugin/lib/task/backupVerifyTask.class.php` | CLI: `php symfony backup:verify --last` — test restore to temp dir |

**Cron configuration:**
```cron
# Daily DB backup at 2 AM
0 2 * * * cd /usr/share/nginx/archive && php symfony backup:create --preset=db --quiet

# Weekly full backup at 3 AM Sunday
0 3 * * 0 cd /usr/share/nginx/archive && php symfony backup:create --preset=full --quiet

# Daily cleanup (keep 30 days)
0 4 * * * cd /usr/share/nginx/archive && php symfony backup:cleanup --older-than=30d --quiet
```

#### M5.6: Audit Log Retention & Tamper Detection (E-cross)
**Files to modify:**
| File | Change |
|------|--------|
| `ahgAuditTrailPlugin/lib/Services/AhgAuditService.php` | Add `sequence_hash` column computation (HMAC chain); add `archiveLogs(int $olderThanDays)` method |

**New table:** `ahg_audit_archive` — cold storage for audit records older than 1 year

**New CLI:** `php symfony audit:verify-integrity` — verify HMAC chain is unbroken

**Deliverable:** `./bin/release minor "Security hardening: HTTP policy, health endpoints, job queue, backup automation"`

---

### M6: Documentation & Governance (Weeks 23-24)
**Scope:** Issue #197 documentation deliverables

**New documentation files (in atom-extensions-catalog/docs/):**

| File | Purpose |
|------|---------|
| `SECURITY_MODEL.md` | Trust boundaries, secrets management, upload/outbound HTTP policy, serialization policy |
| `MULTI_TENANCY.md` | Hard isolation architecture, tenant lifecycle, data flow diagrams |
| `JOBS_AND_QUEUES.md` | Worker model, retry policy, idempotency rules, monitoring |
| `BACKUP_AND_DR.md` | Backup presets, retention, restore runbooks, RTO/RPO targets |
| `UPGRADE_PLAYBOOK.md` | Migration order, rollback procedures, version compatibility |
| `PR_REVIEW_CHECKLIST.md` | Security + documentation checklist for PR reviews |
| `PLUGIN_DOC_TEMPLATE.md` | Standard doc skeleton for all plugins (README structure) |

**Feature overview documents (per plugin `docs/`):**
- `ahgMultiTenantPlugin/docs/Heratio_Multi_Tenancy_Feature_Overview.md` + `.docx`
- `ahgBulkEditPlugin/docs/Heratio_Bulk_Edit_Feature_Overview.md` + `.docx`

---

## File Summary

| Milestone | New Files | Modified Files | New Tables | Schema Changes |
|-----------|-----------|---------------|------------|----------------|
| M0 | 1 (FileValidationService) | 5-9 | 0 | 0 |
| M1 | 3 (migration, TenantFileService, reindex CLI) | 6 | 0 | 4 ALTER TABLE (add tenant_id) |
| M2 | 5 (templates/partials) | 2 | 0 | 1 ES mapping change |
| M3 | 8 (plugin + services + templates) | 3 | 4 (bulk_edit, merge, authority_link) | 0 |
| M4 | 3 (templates + service) | 3 | 1 (derivative_policy) | 0 |
| M5 | 8 (services + actions + CLI) | 4 | 2 (job_queue, audit_archive) | 1 ALTER TABLE |
| M6 | 9 (documentation) | 0 | 0 | 0 |
| **TOTAL** | **37** | **23-27** | **7** | **5-6** |

---

## Dependency Graph

```
M0 (Security Fixes)
 └─→ M1 (Multi-Tenancy)
      ├─→ M2 (Search & Browse)
      │    └─→ M4 (Digital Delivery & Requests)
      └─→ M3 (Description UX)
                └─→ M4
                     └─→ M5 (Ops Hardening)
                          └─→ M6 (Documentation)
```

M0 is a prerequisite for everything (security baseline).
M1 must complete before M2 (search needs tenant isolation).
M2 and M3 can run in parallel after M1.
M4 depends on both M2 (UI patterns) and M3 (form patterns).
M5 can start during M3/M4 (independent ops work).
M6 is the final wrap-up.

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Schema changes to `digital_object` break base AtoM | High | ADD COLUMN only (nullable), no existing column changes; test on PSIS first |
| ES reindex takes too long for production | Medium | Run during maintenance window; incremental reindex by tenant |
| Bulk edit corrupts data | High | Pre-edit snapshots mandatory; rollback tested before release |
| Multi-tenant activation breaks existing single-tenant | High | Feature-flag `tenant_enabled=false` by default; null tenant_id = all-access |
| Getty cache migration loses data | Low | Keep old format readable; migration is one-way JSON conversion |
| ahgAuditTrailPlugin is LOCKED | Medium | Only ADD columns (no schema changes to existing columns); coordinate with owner |
| Disk space (99% full) | High | Backup retention + cleanup before M5; archive old backups to NAS |

---

## Issues to Close After Completion

| Issue | Close After | Comment |
|-------|------------|---------|
| #185 | M5 complete | Security DevOps addressed by M0 + M5 |
| #197 | M6 complete | Security + Documentation hardening addressed by M0 + M5 + M6 |
| #198 | M6 complete | Full ROADMAP addressed by M0-M6 |

## Duplicate Issues to Close Now

Issues 187-191 are exact duplicates of 192-196. Close the duplicates:
| Close | Duplicate Of | Title |
|-------|-------------|-------|
| #192 | #187 | Integrity: Fixity engine |
| #193 | #188 | Integrity: Append-only validation ledger |
| #194 | #189 | Integrity: Retention policy engine |
| #195 | #190 | Integrity: Real-time dashboard |
| #196 | #191 | Integrity: REST API for validation jobs |

---

## Verification Plan

### M0 Verification
```bash
# Serialization: grep for unserialize without allowed_classes
grep -rn "unserialize(" atom-ahg-plugins/ --include="*.php" | grep -v "allowed_classes"
# Should return 0 results

# Upload MIME: test with crafted file
echo "#!/bin/bash" > /tmp/test.jpg
curl -X POST -F "file=@/tmp/test.jpg" /api/v2/file-upload -H "Authorization: Bearer <key>"
# Should return 400 Bad Request (MIME mismatch)
```

### M1 Verification
```bash
# Upload isolation
ls /mnt/nas/heratio/archive/r/ | head -5  # Should show tenant subdirectories

# Search isolation
curl -s "localhost:9200/archive_qubitinformationobject/_search?q=*&size=1" | python3 -m json.tool | grep tenant_id
# Should show tenant_id field

# Cache isolation
mysql -u root archive -e "SELECT setting_key FROM ahg_settings WHERE setting_key LIKE 'tenant_%'"

# Audit isolation
mysql -u root archive -e "SELECT COUNT(*) FROM ahg_audit_log WHERE tenant_id IS NOT NULL"
```

### M2 Verification
```bash
# Search explainability
curl -s "https://psis.theahg.co.za/discovery/search?q=test&explain=true" -b cookies.txt | grep "explanation"

# Hierarchy breadcrumb
curl -s "https://psis.theahg.co.za/test-slug" -b cookies.txt | grep "breadcrumb"
```

### M5 Verification
```bash
# Health endpoint
curl -s https://psis.theahg.co.za/api/health | python3 -m json.tool

# Job worker
systemctl status atom-worker

# Backup schedule
crontab -l | grep backup
```

---

*The Archive and Heritage Group (Pty) Ltd*
*https://github.com/ArchiveHeritageGroup*
