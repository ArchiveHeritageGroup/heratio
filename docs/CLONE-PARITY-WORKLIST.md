# Clone Parity Worklist — Heratio vs AtoM

Generated: 2026-03-22
Source: `docs/FULL-CONTROL-AUDIT.txt` + `bin/audit-controls.php`

## How to use
- [ ] = not started
- [~] = in progress
- [x] = done

---

## 1. FIELD BADGES — COMPLETE

**1,164 badges added across 148 files** via `bin/fix-badges-and-buttons.php` + `bin/fix-badges-pass2.php`.

All `form-label` labels now have Required (bg-danger) / Recommended (bg-warning) / Optional (bg-secondary) badges.
Remaining PARTIAL counts in the audit are `form-check-label` (checkbox labels) which correctly don't need badges.

### ahg-accession-manage
- [x] edit (32 labels → 29 badges added)

### ahg-acl
- [x] approvers (4 labels → 3 badges)
- [x] clearances (8 labels → 8 badges)
- [x] edit-group (4 labels → 4 badges)

### ahg-actor-manage
- [x] browse (6 labels → 6 badges)
- [x] edit (27 labels → 23 badges)
- [x] _contact-area (was 46/48, already had badges)

### ahg-ai-services
- [x] config (47 labels → 43 badges)
- [x] index (2 badges)

### ahg-audit-trail
- [x] browse (5 labels → 5 badges)
- [x] settings (9 labels — all form-check-label, correctly excluded)

### ahg-backup
- [x] index (was partial → complete)
- [x] restore (5 labels → 1 badge + existing)
- [x] settings (4 labels → 4 badges)

### ahg-cart
- [x] settings (10 labels → 8 badges)
- [x] checkout (13 labels → 13 badges)

### ahg-core
- [x] clipboard/load (2 badges)

### ahg-dam
- [x] edit (59 labels → 57 badges)

### ahg-data-migration
- [x] batch-export (3 badges)
- [x] map (2 badges)
- [x] upload (3 badges)

### ahg-dedupe
- [x] browse (3 badges)

### ahg-display
- [x] _advanced-search (22 badges)
- [x] browse-settings (4 badges)
- [x] browse (2 badges)
- [x] bulk-set-type (2 badges)
- [x] levels (1 badge)

### ahg-doi-manage
- [x] config (8 badges)

### ahg-donor-manage
- [x] edit (1 badge)

### ahg-dropdown-manage
- [x] edit (4 badges)
- [x] index (5 badges)

### ahg-favorites
- [x] browse (5 badges)

### ahg-feedback
- [x] edit (11 badges)
- [x] general (8 badges)

### ahg-function-manage
- [x] edit (16 badges)

### ahg-gallery
- [x] artist-create (24 badges)
- [x] edit (17 badges added, was partial)

### ahg-iiif-collection
- [x] add-items (4 badges)
- [x] create (5 badges)
- [x] edit (5 badges)

### ahg-information-object-manage
- [x] create (33 badges)
- [x] edit (43 badges)
- [x] ai/translate (1 badge)
- [x] findingaid/upload (1 badge)
- [x] import/select (6 badges)
- [x] import/skos-import (3 badges)
- [x] import/validate-csv (2 badges)
- [x] partials/_upload-form (1 badge)
- [x] preservation/index (2 badges)
- [x] provenance/index (42 badges)
- [x] research/assessment (3 badges)
- [x] rights/embargo (7 badges)

### ahg-integrity
- [x] index (1 badge)

### ahg-library
- [x] edit (52 badges)

### ahg-loan
- [x] create (20 badges)
- [x] edit (20 badges)
- [x] index (4 badges)
- [x] show (5 badges)

### ahg-media-processing
- [x] watermark-settings (6 badges)

### ahg-menu-manage
- [x] edit (4 badges)

### ahg-metadata-extraction
- [x] index (2 badges)

### ahg-museum
- [x] edit (20 badges added, was partial)

### ahg-pdf-tools
- [x] index (2 badges)
- [x] merge (7 badges)

### ahg-portable-export
- [x] index (9 badges)

### ahg-reports
- [x] _filters (5 badges)
- [x] report-activity (5 badges)
- [x] report-authorities (1 badge)
- [x] report-descriptions (2 badges)
- [x] report-recent (1 badge)
- [x] report-spatial (6 badges)
- [x] report-taxonomy (1 badge)

### ahg-repository-manage
- [x] browse (3 badges)
- [x] edit (44 badges)

### ahg-request-publish
- [x] browse (3 badges)
- [x] edit (9 badges)

### ahg-research (26 files)
- [x] admin-statistics (2 badges)
- [x] admin-types (8 badges)
- [x] annotations (13 badges)
- [x] api-keys (3 badges)
- [x] bibliographies (3 badges)
- [x] book (5 badges)
- [x] collections (2 badges)
- [x] document-templates (8 badges)
- [x] edit-room (14 badges)
- [x] entity-resolution (11 badges)
- [x] equipment (5 badges)
- [x] journal-entry (6 badges)
- [x] journal (7 badges)
- [x] odrl-policies (8 badges)
- [x] profile (15 badges)
- [x] projects (6 badges)
- [x] public-register (18 badges)
- [x] register (15 badges)
- [x] renewal (1 badge)
- [x] reports (4 badges)
- [x] reproductions (4 badges)
- [x] saved-searches (2 badges)
- [x] seats (2 badges)
- [x] validation-queue (4 badges)
- [x] view-bibliography (11 badges)
- [x] view-collection (4 badges)
- [x] view-report (5 badges)
- [x] view-researcher (1 badge)
- [x] walk-in (10 badges)
- [x] workspace (2 badges)
- [x] workspaces (3 badges)

### ahg-researcher-manage
- [x] import-exchange (3 badges)

### ahg-ric
- [x] logs (5 badges)

### ahg-rights-holder-manage
- [x] edit (1 badge)

### ahg-search
- [x] advanced (7 badges)
- [x] description-updates (6 badges)
- [x] global-replace (3 badges)

### ahg-settings (24 files)
- [x] ahg-section (6 badges)
- [x] clipboard (7 badges)
- [x] cron-jobs (4 badges)
- [x] csv-validator (1 badge)
- [x] default-template (3 badges)
- [x] diacritics (2 badges)
- [x] digital-objects (2 badges)
- [x] dip-upload (1 badge)
- [x] email (4 badges)
- [x] errorLog (3 badges)
- [x] finding-aid (4 badges)
- [x] global (9 badges)
- [x] header-customizations (4 badges)
- [x] identifier (5 badges)
- [x] interface-labels (1 badge)
- [x] inventory (1 badge)
- [x] languages (1 badge)
- [x] markdown (1 badge)
- [x] oai (7 badges)
- [x] permissions (8 badges)
- [x] privacy-notification (2 badges)
- [x] security (3 badges)
- [x] site-information (3 badges)
- [x] storage-service (5 badges)
- [x] themes (37 badges)
- [x] treeview (5 badges)
- [x] uploads (4 badges)
- [x] web-analytics (2 badges)

### ahg-static-page
- [x] edit (3 badges)

### ahg-storage-manage
- [x] edit (full rewrite: 4→28 fields, 2col layout, all AtoM sections, badges)
- [x] show (full rewrite: 2col layout, extended data sections, capacity progress bars)

### ahg-term-taxonomy
- [x] edit (9 badges)

### ahg-user-manage
- [x] edit (17 badges)

### ahg-workflow
- [x] create-workflow (7 badges)
- [x] edit-workflow (14 badges)
- [x] gate-edit (11 badges)
- [x] view-task (1 badge)

---

## 2. BAD BUTTON CLASSES — COMPLETE

- [x] ahg-help/index — `btn-light` → `atom-btn-white`
- [x] ahg-library/edit — `btn-light` x2 → `atom-btn-white`
- [x] ahg-research/view-booking — `btn-dark` → `atom-btn-white`

---

## 3. THEAD HEADER ROWS — COMPLETE (done before this worklist)

10 tables had empty `<thead>` tags. All fixed with styled header rows:

- [x] ahg-reports/report-accessions
- [x] ahg-reports/report-authorities
- [x] ahg-reports/report-descriptions
- [x] ahg-reports/report-donors
- [x] ahg-reports/report-recent
- [x] ahg-reports/report-repositories
- [x] ahg-reports/report-storage
- [x] ahg-reports/report-taxonomy
- [x] ahg-cart/orders
- [x] ahg-favorites/shared

---

## 4. STORAGE MANAGE — FULL CLONE COMPLETE

- [x] edit.blade.php: 4→28 fields, 1col→2col, 7 card sections, all badges
- [x] show.blade.php: full rewrite with extended data, capacity bars, status sidebar
- [x] StorageController.php: passes $extendedData to edit/create/show, saves on store/update, deletes on destroy
- [x] StorageService.php: getExtendedData(), saveExtendedData(), deleteExtendedData()

---

## 5. REMAINING WORK (not yet started)

### Missing extended fields (views with fewer controls than AtoM)
- [ ] ahg-actor-manage/edit — compare full field list with AtoM AHG theme
- [ ] ahg-information-object-manage/edit — compare with AtoM AHG theme
- [ ] ahg-information-object-manage/create — compare with AtoM AHG theme
- [ ] ahg-repository-manage/edit — compare with AtoM AHG theme
- [ ] ahg-accession-manage/edit — compare with AtoM AHG theme
- [ ] ahg-donor-manage/edit — compare with AtoM AHG theme
- [ ] ahg-function-manage/edit — compare with AtoM AHG theme
- [ ] ahg-user-manage/edit — compare with AtoM AHG theme
- [ ] ahg-term-taxonomy/edit — compare with AtoM AHG theme

### Layout/sidebar mismatches
- [ ] Verify all show pages use correct col layout vs AtoM
- [ ] Verify all edit pages use correct col layout vs AtoM

### AtoM mapping gaps
- [ ] Fix `bin/audit-controls.php` to use correct `atom-ahg-plugins/` path structure
- [ ] Re-run audit for accurate field-count deltas across all 361 views

---

## Re-run audit
```bash
cd /usr/share/nginx/heratio
php bin/audit-controls.php > docs/FULL-CONTROL-AUDIT.txt
```
