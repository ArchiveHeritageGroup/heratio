<!--
SPDX-License-Identifier: AGPL-3.0-or-later
SPDX-FileCopyrightText: 2026 Johan Pieterse / The Archive and Heritage Group (Pty) Ltd
-->

# `ahg-core` artisan command stubs - triage

**Author:** Johan Pieterse, The Archive and Heritage Group (Pty) Ltd
**Date:** 2026-04-28
**Source:** Heratio packages → `ahg-core/src/Commands/*.php` with `// TODO: Implement` in handle().

**88 stub commands** grouped into 20 domain buckets. Of these, **64 have direct AtoM symfony tasks to port from** (the `archive/` source code is the blueprint per CLAUDE.md Migration Rules).

## Effort estimate

| Class | Count | Estimate per command | Total |
|---|---:|---|---|
| **Port** (AtoM source exists, mechanical convert) | 64 | 30 min – 2 h | ~50–80 h |
| **Implement** (no AtoM equivalent, fresh design) | 24 | 1 – 6 h | ~30–60 h |
| **Total** | **88** | | **~80–140 h** |

Estimates are wall-clock. They assume the operator already knows the domain (POPIA, BagIt, OAI-PMH, DataCite, etc.) and isn't trying to learn it on the job.

## Buckets - recommended priority order

Order chosen by: business value × portability (AtoM equivalent exists). Highest leverage first.

### `cleanup` (9 commands, 2 portable)

Operational hygiene - login attempts, uploads, backups, cache, search index. Low complexity, high reliability impact. **Start here.**

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:backup-cleanup` | Remove old backups past retention | – | × |
| `ahg:cache-xml-purge` | Purge cached XML exports | `lib/task/tools/purgeTask.class.php` | ✓ |
| `ahg:cleanup-login-attempts` | Remove expired login attempts | – | × |
| `ahg:cleanup-uploads` | Remove temp upload files | – | × |
| `ahg:backup` | Database backup | – | × |
| `ahg:notify-saved-searches` | Email saved search notifications | – | × |
| `ahg:refresh-facet-cache` | Rebuild browse facet counts | `atom-ahg-plugins/ahgDisplayPlugin/lib/task/ahgRefreshFacetCacheTask.class.php` | ✓ |
| `ahg:search-cleanup` | Remove stale search entries | – | × |
| `ahg:search-update` | Incremental search index update | – | × |

### `preservation` (9 commands, 7 portable)

Digital preservation - fixity / virus scan / format ID / replication / OAIS BagIt. Most have AtoM equivalents in `ahgPreservationPlugin`. Core to GLAM trust.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:preservation-fixity` | Verify file integrity checksums | `atom-ahg-plugins/ahgPreservationPlugin/lib/task/preservationFixityTask.class.php` | ✓ |
| `ahg:preservation-identify` | Identify file formats via Siegfried/PRONOM | `atom-ahg-plugins/ahgPreservationPlugin/lib/task/preservationIdentifyTask.class.php` | ✓ |
| `ahg:preservation-migrate` | Execute format migrations | `lib/task/migrate/MigrateTask.class.php` | ✓ |
| `ahg:preservation-obsolescence` | Format obsolescence report | – | × |
| `ahg:preservation-package` | Generate OAIS packages (BagIt) | `atom-ahg-plugins/ahgPreservationPlugin/lib/task/preservationPackageTask.class.php` | ✓ |
| `ahg:preservation-replicate` | Sync to replication targets | `atom-ahg-plugins/ahgPreservationPlugin/lib/task/preservationReplicateTask.class.php` | ✓ |
| `ahg:preservation-scheduler` | Run scheduled preservation workflows | `atom-ahg-plugins/ahgPreservationPlugin/lib/task/preservationSchedulerTask.class.php` | ✓ |
| `ahg:preservation-stats` | Preservation statistics | – | × |
| `ahg:preservation-virus-scan` | Scan files for malware via ClamAV | `atom-ahg-plugins/ahgPreservationPlugin/lib/task/preservationVirusScanTask.class.php` | ✓ |

### `audit` (2 commands, 1 portable)

Audit log retention - POPIA + general housekeeping. Trivial to port.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:audit-purge` | Purge old audit trail entries | `lib/task/tools/purgeTask.class.php` | ✓ |
| `ahg:audit-retention` | Purge old audit log entries | – | × |

### `authority` (5 commands, 4 portable)

Authority management - dedup, completeness, NER pipeline. All have AtoM equivalents in `ahgAuthorityPlugin`.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:authority-completeness-scan` | Scan completeness scores | `atom-ahg-plugins/ahgAuthorityPlugin/lib/task/authorityCompletenessScanTask.class.php` | ✓ |
| `ahg:authority-dedup-scan` | Scan for duplicate authorities | – | × |
| `ahg:authority-function-sync` | Validate actor-function links | `atom-ahg-plugins/ahgAuthorityPlugin/lib/task/authorityFunctionSyncTask.class.php` | ✓ |
| `ahg:authority-merge-report` | Merge/split operations report | `atom-ahg-plugins/ahgAuthorityPlugin/lib/task/authorityMergeReportTask.class.php` | ✓ |
| `ahg:authority-ner-pipeline` | Create stub authorities from NER | `atom-ahg-plugins/ahgAuthorityPlugin/lib/task/authorityNerPipelineTask.class.php` | ✓ |

### `doi` (7 commands, 5 portable)

DOI minting / verification / tombstone via DataCite. All 7 have AtoM equivalents in `ahgDoiPlugin`. Self-contained workflow.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:doi-deactivate` | Deactivate/tombstone DOIs | `atom-ahg-plugins/ahgDoiPlugin/lib/task/doiDeactivateTask.class.php` | ✓ |
| `ahg:doi-mint` | Mint DOIs via DataCite | `atom-ahg-plugins/ahgDoiPlugin/lib/task/doiMintTask.class.php` | ✓ |
| `ahg:doi-process-queue` | Process DOI queue | `atom-ahg-plugins/ahgDoiPlugin/lib/task/doiProcessQueueTask.class.php` | ✓ |
| `ahg:doi-report` | DOI status reports | – | × |
| `ahg:doi-sync` | Sync DOI metadata | `atom-ahg-plugins/ahgDoiPlugin/lib/task/doiSyncTask.class.php` | ✓ |
| `ahg:doi-update` | Update DOI metadata at DataCite | – | × |
| `ahg:doi-verify` | Verify DOI registrations | `atom-ahg-plugins/ahgDoiPlugin/lib/task/doiVerifyTask.class.php` | ✓ |

### `library` (7 commands, 6 portable)

Library circulation - overdue / fines / holds / patron expiry / ILL. All have AtoM equivalents in `ahgLibraryPlugin`.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:library-hold-expiry` | Expire unfulfilled holds | `atom-ahg-plugins/ahgLibraryPlugin/lib/task/libraryHoldExpiryTask.class.php` | ✓ |
| `ahg:library-ill-overdue` | Report overdue ILL items | `atom-ahg-plugins/ahgLibraryPlugin/lib/task/libraryIllOverdueTask.class.php` | ✓ |
| `ahg:library-overdue-check` | Scan overdue checkouts | `atom-ahg-plugins/ahgLibraryPlugin/lib/task/libraryOverdueCheckTask.class.php` | ✓ |
| `ahg:library-patron-expiry` | Flag expired memberships | `atom-ahg-plugins/ahgLibraryPlugin/lib/task/libraryPatronExpiryTask.class.php` | ✓ |
| `ahg:library-process-covers` | Download book cover images | – | × |
| `ahg:library-process-fines` | Calculate overdue fines | `atom-ahg-plugins/ahgLibraryPlugin/lib/task/libraryProcessFinesTask.class.php` | ✓ |
| `ahg:library-serial-expected` | Generate expected serial issues | `atom-ahg-plugins/ahgLibraryPlugin/lib/task/librarySerialExpectedTask.class.php` | ✓ |

### `compliance` (12 commands, 10 portable)

Multi-jurisdiction (POPIA, NAZ, CDPA, ICIP, IPSAS, NMMZ). Each is small. Port as needed per-market.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:cdpa-license-check` | Zimbabwe CDPA compliance | `atom-ahg-plugins/ahgCDPAPlugin/lib/task/cdpaLicenseCheckTask.class.php` | ✓ |
| `ahg:cdpa-status` | Zimbabwe CDPA status | `atom-ahg-plugins/ahgCDPAPlugin/lib/task/cdpaStatusTask.class.php` | ✓ |
| `ahg:embargo-process` | Process and lift expired embargoes | `atom-ahg-plugins/ahgExtendedRightsPlugin/lib/task/embargoProcessTask.class.php` | ✓ |
| `ahg:embargo-report` | Embargo status report | `atom-ahg-plugins/ahgExtendedRightsPlugin/lib/task/embargoReportTask.class.php` | ✓ |
| `ahg:icip-check-expiry` | Check ICIP consent expiry | – | × |
| `ahg:ipsas-report` | IPSAS heritage asset report | `atom-ahg-plugins/ahgIPSASPlugin/lib/task/ipsasReportTask.class.php` | ✓ |
| `ahg:naz-closure-check` | Zimbabwe NAZ 25-year closure | `atom-ahg-plugins/ahgNAZPlugin/lib/task/nazClosureCheckTask.class.php` | ✓ |
| `ahg:naz-transfer-due` | Zimbabwe NAZ transfer due | `atom-ahg-plugins/ahgNAZPlugin/lib/task/nazTransferDueTask.class.php` | ✓ |
| `ahg:nmmz-report` | Zimbabwe NMMZ monuments report | `atom-ahg-plugins/ahgNMMZPlugin/lib/task/nmmzReportTask.class.php` | ✓ |
| `ahg:popia-breach-check` | POPIA breach notification check | – | × |
| `ahg:privacy-jurisdiction` | Jurisdiction compliance report | `atom-ahg-plugins/ahgPrivacyPlugin/lib/task/privacyJurisdictionTask.class.php` | ✓ |
| `ahg:privacy-scan-pii` | Scan for PII | `atom-ahg-plugins/ahgPrivacyPlugin/lib/task/privacyScanPiiTask.class.php` | ✓ |

### `reporting` (4 commands, 4 portable)

Stats aggregation + integrity reports. Mostly fresh design (Heratio-specific dashboards).

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:accession-report` | Accession reports | `atom-ahg-plugins/ahgAccessionManagePlugin/lib/task/accessionReportTask.class.php` | ✓ |
| `ahg:integrity-report` | Generate integrity reports | `atom-ahg-plugins/ahgIntegrityPlugin/lib/task/integrityReportTask.class.php` | ✓ |
| `ahg:statistics-aggregate` | Aggregate usage statistics | `atom-ahg-plugins/ahgStatisticsPlugin/lib/task/statisticsAggregateTask.class.php` | ✓ |
| `ahg:statistics-report` | Generate statistics reports | `atom-ahg-plugins/ahgStatisticsPlugin/lib/task/statisticsReportTask.class.php` | ✓ |

### `dedup` (3 commands, 3 portable)

Duplicate scanning + merging. Has AtoM equivalent in `ahgDedupePlugin`.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:dedupe-merge` | Merge confirmed duplicates | `atom-ahg-plugins/ahgDedupePlugin/lib/task/dedupeMergeTask.class.php` | ✓ |
| `ahg:dedupe-report` | Duplicate detection report | `atom-ahg-plugins/ahgDedupePlugin/lib/task/dedupeReportTask.class.php` | ✓ |
| `ahg:dedupe-scan` | Scan for duplicate records | `atom-ahg-plugins/ahgDedupePlugin/lib/task/dedupeScanTask.class.php` | ✓ |

### `metadata` (6 commands, 3 portable)

Metadata IO - EAD import, finding aids, OAI-PMH harvest, linked data sync, multi-format export. Some Atom equivalents.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:ead-import` | Bulk EAD/XML import | – | × |
| `ahg:finding-aid-delete` | Delete finding aids | – | × |
| `ahg:finding-aid-generate` | Generate finding aids | `lib/task/findingAid/findingAidGenerateTask.class.php` | ✓ |
| `ahg:linked-data-sync` | Sync with VIAF/Wikidata/Getty | `atom-ahg-plugins/ahgSemanticSearchPlugin/lib/task/linkedDataSyncTask.class.php` | ✓ |
| `ahg:metadata-export` | Export metadata in GLAM standards (EAD3, LIDO, MARC21, RIC-O, PREMIS, BIBFRAME, etc.) | `atom-ahg-plugins/ahgMetadataExportPlugin/lib/task/metadataExportTask.class.php` | ✓ |
| `ahg:oai-harvest` | Harvest OAI-PMH records | – | × |

### `workflow` (4 commands, 2 portable)

Workflow / SLA / webhook retry. Has AtoM equivalents.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:webhook-retry` | Retry failed webhook deliveries | – | × |
| `ahg:workflow-process` | Process workflow tasks | `atom-ahg-plugins/ahgWorkflowPlugin/lib/task/workflowProcessTask.class.php` | ✓ |
| `ahg:workflow-sla-check` | SLA breach detection | – | × |
| `ahg:workflow-status` | Workflow status report | `atom-ahg-plugins/ahgWorkflowPlugin/lib/task/workflowStatusTask.class.php` | ✓ |

### `ingest` (4 commands, 3 portable)

Ingest commit, CSV import, accession intake, digital object load. Has AtoM equivalents.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:accession-intake` | Manage accession intake queue | `atom-ahg-plugins/ahgAccessionManagePlugin/lib/task/accessionIntakeTask.class.php` | ✓ |
| `ahg:csv-import` | CSV import | `lib/task/import/csvImportTask.class.php` | ✓ |
| `ahg:ingest-commit` | Process data ingest commit | `atom-ahg-plugins/ahgIngestPlugin/lib/task/ingestCommitTask.class.php` | ✓ |
| `ahg:load-digital-objects` | Batch load digital objects | – | × |

### `portable` (4 commands, 4 portable)

Portable catalogue export/import - used for repository hand-off. Self-contained. Has AtoM equivalents.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:portable-cleanup` | Remove expired export packages | `atom-ahg-plugins/ahgPortableExportPlugin/lib/task/portableCleanupTask.class.php` | ✓ |
| `ahg:portable-export` | Generate portable catalogue | `atom-ahg-plugins/ahgPortableExportPlugin/lib/task/portableExportTask.class.php` | ✓ |
| `ahg:portable-import` | Import portable package | `atom-ahg-plugins/ahgPortableExportPlugin/lib/task/portableImportTask.class.php` | ✓ |
| `ahg:portable-verify` | Verify export package integrity | `atom-ahg-plugins/ahgPortableExportPlugin/lib/task/portableVerifyTask.class.php` | ✓ |

### `display` (2 commands, 2 portable)

Display reindex, derivative regeneration, facet cache refresh. Has AtoM equivalents in `ahgDisplayPlugin`.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:display-auto-detect` | Auto-detect GLAM object types | `atom-ahg-plugins/ahgDisplayPlugin/lib/task/displayAutoDetectTask.class.php` | ✓ |
| `ahg:regen-derivatives` | Regenerate image derivatives | `lib/task/digitalobject/digitalObjectRegenDerivativesTask.class.php` | ✓ |

### `heritage` (3 commands, 3 portable)

Heritage knowledge graph + region management. Mostly fresh.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:heritage-build-graph` | Build heritage knowledge graph | `atom-ahg-plugins/ahgHeritagePlugin/lib/task/heritageBuildGraphTask.class.php` | ✓ |
| `ahg:heritage-install` | Install heritage schema | `atom-ahg-plugins/ahgHeritageAccountingPlugin/lib/task/heritageInstallTask.class.php` | ✓ |
| `ahg:heritage-region` | Manage heritage regions | `atom-ahg-plugins/ahgHeritageAccountingPlugin/lib/task/heritageRegionTask.class.php` | ✓ |

### `museum` (2 commands, 2 portable)

Museum AAT sync (Getty) + exhibition schedule. Mostly fresh.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:museum-aat-sync` | Sync Getty AAT vocabulary cache | `atom-ahg-plugins/ahgMuseumPlugin/lib/task/museumAatSyncTask.class.php` | ✓ |
| `ahg:museum-exhibition` | Manage exhibition schedule | `atom-ahg-plugins/ahgExhibitionPlugin/lib/task/exhibitionTask.class.php` | ✓ |

### `donor` (1 command, 0 portable)

Donor reminders + saved-search notifications.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:donor-reminders` | Process donor agreement reminders | – | × |

### `forms` (2 commands, 2 portable)

Form configuration import/export.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:forms-export` | Export form configurations | `atom-ahg-plugins/ahgFormsPlugin/lib/task/formsExportTask.class.php` | ✓ |
| `ahg:forms-import` | Import form configurations | `atom-ahg-plugins/ahgFormsPlugin/lib/task/formsImportTask.class.php` | ✓ |

### `tree` (1 command, 0 portable)

NestedSet rebuild - single command, classic symfony port.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:nested-set-rebuild` | Rebuild nested set tree | – | × |

### `misc` (1 command, 1 portable)

Catch-all. Inspect individually.

| Command | Description | AtoM source | Port? |
|---|---|---|---|
| `ahg:export-bulk` | Bulk export descriptions | `lib/task/export/exportBulkTask.class.php` | ✓ |

## Notes

1. **AtoM source = blueprint.** Per CLAUDE.md's Migration Rules: read the symfony 1.x task in `archive/`, port the logic, convert namespace + signature, keep behaviour identical. Most are <300 lines.
2. **Schedule via `ahg:cron-seed`.** Once a command is implemented, register it in `cron_schedule` so it runs on the configured cadence. The cron framework is already live (CronRun, CronSeed, CronStatus exist).
3. **Re-scan after each batch.** `python3 /tmp/pkg-outstanding-v2.py` will report which TODOs remain - use the diff as the milestone tracker.
4. **Some are forward-looking.** Heritage knowledge graph, RiC linked-data sync, ICIP consent expiry are not all ported from AtoM - they're new features for international markets. Those need product input, not just code.

## Output

Raw triage data: `/tmp/ahg-core-triage.json` (programmatically regeneratable from this scan).
