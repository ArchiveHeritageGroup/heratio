# Audit Methodology: AtoM → Heratio Parity

**Purpose:** This document describes the complete audit and parity-checking process used to measure and close the gap between AtoM (Symfony 1.4) and Heratio (Laravel 12). Use this to replicate the same audit on another instance.

---

## Overview

The audit is a multi-layer process that answers these questions:

1. **Route parity** — Does Heratio have every URL/route that AtoM has?
2. **HTTP parity** — Do both systems return the same status codes and DOM structure for each route?
3. **Control parity** — Does every Heratio page have the same number of buttons, links, fields, headings, badges, tables, etc. as the AtoM equivalent?
4. **Field parity** — Does every Heratio edit/create form have the same `name=` fields as AtoM?
5. **URL/link parity** — Does every link on every page point to the correct Heratio equivalent of the AtoM URL?
6. **View parity** — Does every AtoM template have a Heratio blade equivalent?
7. **API parity** — Does Heratio expose the same REST/OAI-PMH endpoints as AtoM?
8. **Media processing parity** — Does Heratio handle the same media types and operations as AtoM?
9. **Menu parity** — Does Heratio's navigation match AtoM's menu structure?

---

## Prerequisites

- **Heratio instance** at a known URL (e.g., `http://localhost`)
- **AtoM instance** at a known URL (e.g., `http://psis.theahg.co.za`)
- **AtoM source code** on disk (e.g., `/usr/share/nginx/archive`) — needed for template-level comparison
- **PHP 8.3+** with CLI
- **Laravel artisan** accessible (`php artisan route:list --json`)
- **curl**, **python3** (for the bash parity-check script)

---

## Step-by-Step Audit Process

### Step 1: Route Parity — `bin/parity-routes.php`

**What it does:** Reads all AtoM `routing.yml` files (base Symfony + every AHG plugin) and compares against Heratio's `artisan route:list`.

**Run:**
```bash
cd /usr/share/nginx/heratio
php bin/parity-routes.php                          # terminal output
php bin/parity-routes.php --output /tmp/routes.html # HTML report
php bin/parity-routes.php --missing-only            # only show gaps
php bin/parity-routes.php --plugin actor             # filter by plugin
php bin/parity-routes.php --json                     # JSON output
```

**Output:** List of every AtoM route, whether it exists in Heratio, and missing routes grouped by package.

**Result from our audit:** 2,249 Heratio routes, 327/332 AtoM app routes matched (5 protocol-level skipped).

---

### Step 2: HTTP Parity — `bin/parity-check` (bash)

**What it does:** For every GET route in Heratio, fetches the page from both Heratio and AtoM, compares HTTP status codes and DOM element counts (inputs, selects, textareas, table rows, headings, links, buttons).

**Run:**
```bash
cd /usr/share/nginx/heratio
bin/parity-check                                                # defaults
bin/parity-check --heratio-url http://localhost --atom-url http://psis.theahg.co.za
bin/parity-check --filter "information"                         # test subset
bin/parity-check --cookie /tmp/cookies.txt --verbose            # authenticated
bin/parity-check --output /tmp/parity-report.html               # HTML report
```

**Output:** HTML report at `/tmp/parity-report.html` with:
- Summary cards: matched, diffs, errors, missing
- Per-route table with status codes, field counts, heading counts
- Color-coded status: MATCH / DIFF / HERATIO_500 / HERATIO_404 / ATOM_FAIL

---

### Step 3: Control Parity — `bin/audit-controls.php`

**What it does:** Reads every Heratio blade view and its mapped AtoM template. Counts every UI element: buttons, links, inputs, selects, textareas, checkboxes, radios, headings (h1-h6), badges, tables, labels, icons, images, forms. Computes delta per package.

**Run:**
```bash
cd /usr/share/nginx/heratio
php bin/audit-controls.php > docs/FULL-CONTROL-AUDIT.txt
```

**Key configuration:** The `$mapping` array (lines ~130–243) maps each Heratio package to its AtoM source directories. This must be correct for accurate results.

**Output:** `docs/FULL-CONTROL-AUDIT.txt` — per-package breakdown:
- H-Ctrl (Heratio controls), A-Ctrl (AtoM controls), Delta
- Layout type (1col/2col/3col), sidebar presence
- AtoM mapping percentage

**Result from our audit:** 22,766 Heratio controls vs 22,279 AtoM controls, 11,590 total delta.

---

### Step 4: Field Parity — `bin/parity-check.php`

**What it does:** For each edit/create form, extracts `name=` attributes from both AtoM templates and Heratio blade views. Reports fields present in AtoM but missing in Heratio.

**Run:**
```bash
cd /usr/share/nginx/heratio
php bin/parity-check.php > docs/FIELD-PARITY-REPORT.txt
```

**Output:** `docs/FIELD-PARITY-REPORT.txt` — per-form breakdown:
- H-Fld (Heratio fields), A-Fld (AtoM fields), Miss (missing), Extra
- List of missing field names per form

**Result from our audit:** 260 missing fields across 24 forms.

---

### Step 5: URL/Link Parity — Three levels

#### 5a. All route() calls — `bin/audit-all-urls.php`
Validates every `route('name')` call in blade files against `artisan route:list`.

```bash
php bin/audit-all-urls.php > docs/URL-AUDIT-ALL.txt
```

**Result:** 1,270 route() calls found, 110 broken (pointing to non-existent routes).

#### 5b. Scoped URL audit — `bin/audit-urls.php`
Checks that links on show/edit pages are record-scoped (contain `$slug`, `$id`, etc.) rather than generic.

```bash
php bin/audit-urls.php
```

#### 5c. Page-by-page link comparison — `bin/audit-urls-v2.php`
Extracts every `<a href>` from each Heratio blade and its AtoM equivalent, pairs by link text, flags MISSING/EXTRA/MISMATCH.

```bash
php bin/audit-urls-v2.php > docs/URL-AUDIT-V2.txt
```

---

### Step 6: View Parity — `bin/missing-views.php`

**What it does:** Scans all AtoM templates across all plugins and checks if a corresponding Heratio blade exists.

```bash
php bin/missing-views.php > docs/MISSING-VIEWS-REPORT.txt
```

**Result from our audit:** 2,058 AtoM templates, all have Heratio equivalents at file level (but many 30-50% feature-complete).

---

### Step 7: Menu Comparison (manual + documented)

Compare AtoM's navigation menus item-by-item against Heratio. Document in `docs/AHG-MENU-COMPARISON.md`.

**Check:**
- Every top-level menu item
- Every dropdown sub-item
- Badge counts (pending researchers, bookings, etc.)
- URL targets for each item

---

### Step 8: API Comparison (manual + documented)

Compare all AtoM API endpoints (v1, v2, OAI-PMH) against Heratio. Document in `docs/API-COMPARISON.md`.

---

### Step 9: Media Processing Comparison (manual + documented)

Compare AtoM's media handling features (thumbnails, derivatives, 3D, IIIF, metadata extraction, AI, watermarks, transcoding) against Heratio. Document in `docs/MEDIA-PROCESSING-COMPARISON.md`.

---

## Auto-Fix Scripts

After auditing, these scripts automate common fixes:

| Script | Purpose |
|--------|---------|
| `bin/fix-badges-and-buttons.php` | Add Required/Recommended/Optional badges to form labels |
| `bin/fix-badges-pass2.php` | Second pass — edge cases |
| `bin/fix-badges-pass3.php` | Third pass — final refinements |
| `bin/fix-broken-routes.php` | Generate stub routes for broken `route()` calls |
| `bin/create-missing-packages.php` | Scaffold Heratio packages for missing AtoM plugins |

---

## The 12 Rules (mandatory for every page fix)

Every page fix must follow ALL 12 rules:

| # | Rule |
|---|------|
| 1 | Apply central CSS theme (`var(--ahg-primary)`, `atom-btn-*` classes) |
| 2 | Count controls, report in comparison table (AtoM vs Heratio vs delta) |
| 3 | Clone exactly — same controls, same look, same text, same CSS |
| 4 | No asking permission — just fix it |
| 5 | No "future enhancements" — everything done in one pass |
| 6 | Text = controls — headings, labels, static text, help text all count |
| 7 | Field badges — Required (bg-danger), Recommended (bg-warning), Optional (bg-secondary) |
| 8 | Report again after fixes — confirm 0 deltas |
| 9 | Layout template must match AtoM (1col/2col/3col) |
| 10 | Sidebar position must match AtoM (left/right) |
| 11 | Page width/structure must match (container vs container-fluid, column ratios) |
| 12 | Every button/link URL must route to correct Heratio equivalent |

**Workflow:** BEFORE (read AtoM + Heratio, generate table) → FIX (clone all 12 dimensions) → AFTER (regenerate table, confirm 0 delta)

---

## 3-Phase Execution Plan

### Phase 1: Fix Audit Mapping
Correct the `$mapping` array in `bin/audit-controls.php` so it points to the right AtoM source directories for all packages. This unblocks accurate delta measurement.

### Phase 2: Extended Data Wiring
For each AtoM `*_extended` table, ensure:
- Service class queries the table
- Controller passes data to view
- View renders all fields

Follow the pattern in `StorageService::getExtendedData()` → `StorageController::edit()` → `edit.blade.php`.

### Phase 3: View-by-View Field Parity
For each edit/create/show form, compare field-by-field against AtoM and close the delta. Priority:
1. information-object-manage (largest gap)
2. actor-manage
3. repository-manage
4. accession-manage
5. settings
6. All remaining entities

---

## Output Files Summary

| File | Generated By | Contains |
|------|-------------|----------|
| `docs/FULL-CONTROL-AUDIT.txt` | `bin/audit-controls.php` | Per-package control counts and deltas |
| `docs/FIELD-PARITY-REPORT.txt` | `bin/parity-check.php` | Missing form fields per edit/create view |
| `docs/URL-AUDIT-ALL.txt` | `bin/audit-all-urls.php` | Broken route() calls |
| `docs/URL-AUDIT-V2.txt` | `bin/audit-urls-v2.php` | Page-by-page link comparison |
| `docs/MISSING-VIEWS-REPORT.txt` | `bin/missing-views.php` | AtoM templates without Heratio equivalents |
| `docs/AHG-MENU-COMPARISON.md` | Manual | Menu item parity |
| `docs/AHG-MENU-PAGE-COMPARISON.md` | Manual | Deep page-by-page feature parity |
| `docs/API-COMPARISON.md` | Manual | API endpoint parity |
| `docs/MEDIA-PROCESSING-COMPARISON.md` | Manual | Media processing feature parity |
| `docs/CLONE-PARITY-PLAN.md` | Manual | Master 3-phase roadmap |
| `docs/CLONE-PARITY-WORKLIST.md` | Manual | Granular task checklist with progress |
| `/tmp/parity-report.html` | `bin/parity-check` | HTTP response comparison report |
| `/tmp/parity-routes-report.html` | `bin/parity-routes.php` | Route comparison HTML report |

---

## Replicating on Another Instance

To run this audit on a new AtoM → Heratio migration:

1. **Copy all `bin/` audit scripts** to the new instance
2. **Update paths** in each script:
   - AtoM source path (default: `/usr/share/nginx/archive`)
   - Heratio app path (default: `/usr/share/nginx/heratio`)
   - AtoM URL (for HTTP parity check)
   - Heratio URL (for HTTP parity check)
3. **Update the `$mapping` array** in `bin/audit-controls.php` and `bin/parity-check.php` to reflect the AtoM plugin directory structure of the new instance
4. **Run scripts in order:** routes → HTTP → controls → fields → URLs → views
5. **Generate reports** into `docs/`
6. **Apply fixes** using the auto-fix scripts or manually following the 12 Rules
7. **Re-run audits** to confirm deltas approach zero

### Key paths to update per script:

| Script | Config to change |
|--------|-----------------|
| `bin/parity-routes.php` | AtoM routing.yml glob path |
| `bin/parity-check` (bash) | `--heratio-url` and `--atom-url` CLI args |
| `bin/audit-controls.php` | `$mapping` array (package → AtoM directories) |
| `bin/parity-check.php` | `$mapping` array (same structure) |
| `bin/audit-urls-v2.php` | AtoM template base path |
| `bin/missing-views.php` | AtoM plugin glob paths |
