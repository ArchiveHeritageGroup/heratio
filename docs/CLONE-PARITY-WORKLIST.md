# Clone Parity Worklist — Heratio vs AtoM

Generated: 2026-03-22
Source: `docs/FULL-CONTROL-AUDIT.txt` (full audit) + `bin/audit-controls.php` (re-runnable)

## How to use
- [ ] = not started
- [~] = in progress
- [x] = done — re-run `php bin/audit-controls.php` to verify

---

## 1. MISSING FIELD BADGES (113 views)

Every form label needs a Required (bg-danger) / Recommended (bg-warning) / Optional (bg-secondary) badge.

### ahg-accession-manage
- [x] edit (32 labels, 0 badges) — AtoM: `atom-ahg-plugins/ahgAccessionManage/`

### ahg-acl
- [ ] approvers (4 labels, 0 badges)
- [ ] clearances (8 labels, 0 badges)
- [ ] edit-group (4 labels, 0 badges)

### ahg-actor-manage
- [ ] browse (6 labels, 0 badges) — filter form labels
- [ ] edit (27 labels, 0 badges) — AtoM: `atom-ahg-plugins/ahgActorManage/`
- [~] _contact-area (48 labels, 46 Optional, 0 Required) — needs 2 more badges

### ahg-ai-services
- [ ] config (47 labels, 0 badges)

### ahg-audit-trail
- [ ] browse (5 labels, 0 badges)
- [ ] settings (9 labels, 0 badges)

### ahg-backup
- [ ] index (4 labels, 1 Required partial)
- [ ] restore (5 labels, 0 badges)
- [ ] settings (4 labels, 0 badges)

### ahg-cart
- [ ] settings (10 labels, 0 badges)
- [ ] checkout (13 labels, 0 badges)

### ahg-dam
- [ ] edit (59 labels, 0 badges)

### ahg-data-migration
- [ ] batch-export (3 labels, 0 badges)
- [ ] upload (3 labels, 0 badges)

### ahg-dedupe
- [ ] browse (3 labels, 0 badges)

### ahg-display
- [ ] _advanced-search (27 labels, 0 badges)
- [ ] browse-settings (7 labels, 0 badges)
- [ ] browse (3 labels, 0 badges)
- [ ] bulk-set-type (3 labels, 0 badges)

### ahg-doi-manage
- [ ] config (8 labels, 0 badges)

### ahg-dropdown-manage
- [ ] edit (5 labels, 0 badges)
- [ ] index (5 labels, 0 badges)

### ahg-favorites
- [ ] browse (5 labels, 0 badges)

### ahg-feedback
- [ ] edit (11 labels, 0 badges)
- [ ] general (8 labels, 0 badges)

### ahg-function-manage
- [ ] edit (19 labels, 0 badges) — AtoM: `apps/qubit/modules/function/`

### ahg-gallery
- [ ] artist-create (25 labels, 0 badges)
- [~] edit (67 labels, 32 badges partial)

### ahg-iiif-collection
- [ ] add-items (5 labels, 0 badges)
- [ ] create (6 labels, 0 badges)
- [ ] edit (6 labels, 0 badges)

### ahg-information-object-manage
- [ ] create (38 labels, 0 badges) — AtoM: `atom-ahg-plugins/ahgInformationObjectManage/`
- [ ] edit (44 labels, 0 badges) — AtoM: `atom-ahg-plugins/ahgInformationObjectManage/`
- [ ] select (9 labels, 0 badges)
- [ ] skos-import (3 labels, 0 badges)
- [ ] condition/index (46 labels, 0 badges)
- [ ] research/assessment (3 labels, 0 badges)
- [ ] rights/embargo (10 labels, 0 badges)
- [~] rights/extended (5 labels, 4 Optional partial)

### ahg-library
- [ ] edit (54 labels, 0 badges)

### ahg-loan
- [ ] create (20 labels, 0 badges)
- [ ] edit (20 labels, 0 badges)
- [ ] index (5 labels, 0 badges)
- [ ] show (5 labels, 0 badges)

### ahg-media-processing
- [ ] watermark-settings (11 labels, 0 badges)

### ahg-menu-manage
- [ ] edit (5 labels, 0 badges) — AtoM: `apps/qubit/modules/menu/`

### ahg-museum
- [~] edit (61 labels, 32 badges partial)

### ahg-pdf-tools
- [ ] merge (10 labels, 0 badges)

### ahg-portable-export
- [ ] index (17 labels, 0 badges)

### ahg-reports
- [ ] _filters (5 labels, 0 badges)
- [ ] report-activity (5 labels, 0 badges)
- [ ] report-spatial (8 labels, 0 badges)

### ahg-repository-manage
- [ ] browse (3 labels, 0 badges)
- [ ] edit (46 labels, 0 badges) — AtoM: `atom-ahg-plugins/ahgRepositoryManage/`

### ahg-request-publish
- [ ] browse (3 labels, 0 badges)
- [ ] edit (9 labels, 0 badges)

### ahg-research
- [ ] admin-types (8 labels, 0 badges)
- [ ] annotations (13 labels, 0 badges)
- [ ] api-keys (6 labels, 0 badges)
- [ ] bibliographies (3 labels, 0 badges)
- [ ] book (6 labels, 0 badges)
- [ ] document-templates (8 labels, 0 badges)
- [ ] edit-room (15 labels, 0 badges)
- [ ] entity-resolution (11 labels, 0 badges)
- [ ] equipment (5 labels, 0 badges)
- [ ] journal-entry (6 labels, 0 badges)
- [ ] journal (7 labels, 0 badges)
- [ ] odrl-policies (8 labels, 0 badges)
- [ ] profile (15 labels, 0 badges)
- [ ] projects (6 labels, 0 badges)
- [ ] public-register (18 labels, 0 badges)
- [ ] register (15 labels, 0 badges)
- [ ] reports (4 labels, 0 badges)
- [ ] reproductions (4 labels, 0 badges)
- [ ] validation-queue (4 labels, 0 badges)
- [ ] view-bibliography (11 labels, 0 badges)
- [ ] view-collection (4 labels, 0 badges)
- [ ] view-report (5 labels, 0 badges)
- [ ] walk-in (11 labels, 0 badges)
- [ ] workspaces (3 labels, 0 badges)

### ahg-researcher-manage
- [ ] import-exchange (3 labels, 0 badges)

### ahg-ric
- [ ] logs (5 labels, 0 badges)

### ahg-search
- [ ] advanced (8 labels, 0 badges)
- [ ] description-updates (11 labels, 0 badges)
- [ ] global-replace (4 labels, 0 badges)

### ahg-settings
- [ ] ahg-section (7 labels, 0 badges)
- [ ] clipboard (13 labels, 0 badges) — AtoM: `settings/clipboardSuccess.php`
- [ ] cron-jobs (5 labels, 0 badges)
- [ ] csv-validator (4 labels, 0 badges) — AtoM: `settings/csvValidatorSuccess.php`
- [ ] default-template (3 labels, 0 badges) — AtoM: `settings/templateSuccess.php`
- [ ] diacritics (4 labels, 0 badges) — AtoM: `settings/diacriticsSuccess.php`
- [ ] dip-upload (3 labels, 0 badges) — AtoM: `settings/dipUploadSuccess.php`
- [ ] email (5 labels, 0 badges)
- [ ] errorLog (3 labels, 0 badges)
- [ ] finding-aid (8 labels, 0 badges) — AtoM: `settings/findingAidSuccess.php`
- [ ] global (18 labels, 0 badges) — AtoM: `settings/globalSuccess.php`
- [ ] header-customizations (4 labels, 0 badges) — AtoM: `settings/headerSuccess.php`
- [ ] identifier (10 labels, 0 badges) — AtoM: `settings/identifierSuccess.php`
- [ ] markdown (3 labels, 0 badges) — AtoM: `settings/markdownSuccess.php`
- [ ] oai (7 labels, 0 badges) — AtoM: `settings/oaiSuccess.php`
- [ ] permissions (14 labels, 0 badges) — AtoM: `settings/permissionsSuccess.php`
- [ ] privacy-notification (4 labels, 0 badges) — AtoM: `settings/privacyNotificationSuccess.php`
- [ ] section (3 labels, 0 badges)
- [ ] security (3 labels, 0 badges) — AtoM: `settings/securitySuccess.php`
- [ ] site-information (3 labels, 0 badges) — AtoM: `settings/siteInformationSuccess.php`
- [ ] storage-service (7 labels, 0 badges)
- [ ] themes (39 labels, 0 badges)
- [ ] treeview (8 labels, 0 badges) — AtoM: `settings/treeviewSuccess.php`
- [ ] uploads (8 labels, 0 badges) — AtoM: `settings/uploadsSuccess.php`

### ahg-static-page
- [ ] edit (3 labels, 0 badges) — AtoM: `apps/qubit/modules/staticpage/`

### ahg-storage-manage
- [x] edit (4→28 fields, badges added, 2col layout, all AtoM sections cloned)

### ahg-term-taxonomy
- [ ] browse (3 labels, 0 badges)
- [ ] edit (10 labels, 0 badges) — AtoM: `apps/qubit/modules/term/`
- [ ] show (3 labels, 0 badges)

### ahg-user-manage
- [ ] edit (19 labels, 0 badges) — AtoM: `apps/qubit/modules/user/`

### ahg-workflow
- [ ] create-workflow (12 labels, 0 badges)
- [~] edit-workflow (22 labels, 1 Optional partial)
- [ ] gate-edit (12 labels, 0 badges)

---

## 2. BAD BUTTON CLASSES (3 files)

These use raw Bootstrap `btn-*` instead of `atom-btn-*` theme classes:

- [ ] ahg-help/index — `btn-light` → `atom-btn-white`
- [ ] ahg-library/edit — `btn-light` x2 → `atom-btn-white`
- [ ] ahg-research/view-booking — `btn-dark` → needs review

---

## 3. MISSING FIELDS (views with fewer controls than AtoM)

These Heratio pages have significantly fewer fields/controls than their AtoM equivalents:

- [x] ahg-storage-manage/edit — was 4 fields, AtoM has 28 → FIXED (cloned all sections)
- [ ] ahg-actor-manage/edit — Heratio 45 fields vs AtoM extended version
- [ ] ahg-information-object-manage/edit — Heratio 44 fields vs AtoM extended
- [ ] ahg-repository-manage/edit — Heratio unknown vs AtoM extended
- [ ] ahg-accession-manage/edit — Heratio 54 fields vs AtoM extended
- [ ] ahg-donor-manage/edit — needs comparison
- [ ] ahg-function-manage/edit — needs comparison
- [ ] ahg-user-manage/edit — needs comparison
- [ ] ahg-term-taxonomy/edit — needs comparison

---

## 4. LAYOUT/SIDEBAR MISMATCHES

Pages where Heratio uses wrong layout vs AtoM:

- [x] ahg-storage-manage/edit — was 1col, AtoM uses 2col (8+4) → FIXED
- [ ] ahg-actor-manage/show — Heratio 3col, check if AtoM matches
- [ ] ahg-information-object-manage/show — check treeview sidebar

---

## 5. AtoM MAPPING GAPS (318 unmapped views)

The audit script only matched 43/361 views to AtoM equivalents. The `atom-ahg-plugins/` directory path needs fixing in the script. Many custom AHG packages exist at:
```
/usr/share/nginx/archive/atom-ahg-plugins/ahg*Plugin/modules/*/templates/
```

- [ ] Fix `bin/audit-controls.php` mapping to use correct `atom-ahg-plugins/` path
- [ ] Re-run audit after path fix to get accurate deltas

---

## Re-run audit
```bash
cd /usr/share/nginx/heratio
php bin/audit-controls.php > docs/FULL-CONTROL-AUDIT.txt
```
