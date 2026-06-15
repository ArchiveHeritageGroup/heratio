> Heratio Help Center article. Category: Heritage Accounting.

# Heritage Asset Accounting

Heritage Asset Accounting lets you maintain a financial register of your collection items as heritage assets: recognition, measurement, valuations, impairments, movements, and the revaluation-reserve (OCI) ledger. Accounting standards are pluggable per market, so the same register can be reported against GRAP 103, IPSAS 45, or another standard configured for your jurisdiction.

---

## Overview

The Heritage Accounting subsystem sits inside the `ahg-heritage-manage` package. It treats each recognised collection item as a heritage asset with its own carrying amount, measurement basis, acquisition details, insurance, condition, and movement history. It links optionally to an archival description (information object) so financial records and catalogue records stay connected.

Heratio is jurisdiction-neutral. Each market plugs in the accounting standard it must report against. The package ships templates and compliance tooling for GRAP 103 (a public-sector heritage standard) and IPSAS 45, and supports additional standards as pluggable per-market modules. None of these is the "core" of the product; they are interchangeable standards you select per asset.

All accounting screens live under the admin area and require an authenticated admin user. Create, update, and delete actions are additionally gated by ACL middleware (`acl:create`, `acl:update`, `acl:delete`).

---

## Key features

- **Heritage asset register** - record each item with accounting standard, asset class and sub-class, recognition status (pending, recognised, not recognised), measurement basis (cost, fair value, nominal, not practicable), acquisition details, carrying amounts, heritage significance, insurance, location and condition.
- **Link to archival records** - attach an asset to an existing archival description by type-ahead search, keeping the catalogue and the financial register joined.
- **Valuations, impairments, journals and movements** - dedicated entry screens for each transaction type against an asset.
- **OCI / revaluation reserve ledger** - records revaluations, impairments, reversals and disposals and automatically splits each movement between Other Comprehensive Income (the revaluation reserve in equity), profit and loss, and reserves, following the recognition rules of the selected standard.
- **Qualified valuer registry** - maintain a register of accredited valuers (credential, professional body, accreditation number, specialisations) used to evidence revaluations.
- **GRAP 103 compliance views** - a compliance dashboard, batch check, per-asset check, and a National Treasury report dataset (capitalised vs non-capitalised assets, carrying amounts, valuations) with status and standard filters.
- **Heritage reports** - asset register, movement, and valuation reports.
- **Administration** - regions, rules and standards reference screens, plus the valuer and OCI registries.
- **Statutory disclosure notes** - a console command renders a GRAP 103, IPSAS 45, or transitional disclosure note populated from live register data.

---

## How to use

### Open the accounting dashboard

Sign in as an admin and go to `/heritage/accounting`. The dashboard shows totals: number of assets, recognised count, pending count, and total carrying amount. A sidebar groups the workspace into Heritage Accounting, GRAP 103 Compliance, Administration, and Reports.

### Add a heritage asset

1. Go to **Heritage Accounting -> Add Asset** (`/heritage/accounting/add`).
2. Optionally link an archival record: start typing in **Link to Archival Record** and pick a match from the type-ahead list. (Opening the form with `?io_id=` pre-links a record.)
3. Choose the **Accounting Standard** (for example GRAP 103 or IPSAS 45) and an **Asset Class** and sub-class.
4. Set **Recognition**: status, recognition date, measurement basis, and a reason note.
5. Fill in **Acquisition** (method, date, cost, fair value at acquisition, nominal value, donor name and restrictions).
6. Enter the **Carrying Amounts** (initial and current).
7. In the right column add **Heritage Information** (significance, statement, current location, condition), **Insurance**, and **Notes**.
8. Click **Save Asset**.

### Browse and view assets

- **Browse Assets** (`/heritage/accounting/browse`) lists assets, most recent first, paginated.
- Open an asset to view it (`/heritage/accounting/{id}`), or view by linked archival record (`/heritage/accounting/by-object/{id}`).
- Edit an asset at `/heritage/accounting/{id}/edit`.

### Record valuations, impairments, journals and movements

From an asset, use the dedicated entry screens:

- **Add valuation** - `/heritage/accounting/add-valuation/{id}`
- **Add impairment** - `/heritage/accounting/add-impairment/{id}`
- **Add journal entry** - `/heritage/accounting/add-journal/{id}`
- **Add movement** - `/heritage/accounting/add-movement/{id}`

### Use the OCI / revaluation reserve ledger

1. Go to **Administration -> OCI Movements** (`/admin/heritage/oci`). Filter by date range, movement type, or asset.
2. Click **Create** (`/admin/heritage/oci/create`) to record a movement.
3. Choose a **movement type**: revaluation, impairment, reversal, or disposal, and enter the values, valuation date, valuer, method, and reason.
4. On save, the ledger applies the standard's recognition split automatically. A revaluation surplus posts to OCI; a decrease or impairment first reduces any accumulated OCI surplus for that asset, then the balance hits profit and loss; reversals recycle through profit and loss first; on disposal any residual surplus transfers to retained earnings. Each row records whether it was posted to OCI, P&L, or Reserve, and is written to the audit trail when available.

### Manage the valuer registry

1. Go to **Administration -> Valuer Registry** (`/admin/heritage/valuers`).
2. Search by name, credential, professional body, or accreditation number, and filter by active status.
3. Use **Create** to add a valuer (name, credential, professional body, accreditation number, email, phone, specialisations, notes). Edit at `/admin/heritage/valuers/{id}/edit`. Deleting a valuer deactivates it rather than removing the record.

### Run GRAP 103 compliance checks

- **Compliance Dashboard** (`/heritage/grap`) summarises assets scoped to the GRAP 103 standard with compliant, partially compliant, and non-compliant counts plus recent assets.
- **Batch Check** (`/heritage/grap/batch-check`) and per-asset **Check** (`/heritage/grap/check/{id}`).
- **Treasury Report** (`/heritage/grap/national-treasury-report`) builds the disclosure dataset (capitalised and non-capitalised assets, current carrying amount, last valuation) and supports status and standard filters.

This compliance pack is the per-market module for jurisdictions that report against GRAP 103. Other markets use the standard configured for them.

### View reports

Go to **Reports** (`/heritage/reports`) for the Reports Index, then:

- **Asset Register** - `/heritage/reports/asset-register`
- **Movement Report** - `/heritage/reports/movement`
- **Valuation Report** - `/heritage/reports/valuation`

### Generate a statutory disclosure note

Run the console command to render a disclosure note from live register data:

```
php artisan heritage:disclosure-note --standard=grap-103 --period=2025-04-01..2026-03-31 --out=/tmp/grap-103-note.md
php artisan heritage:disclosure-note --standard=ipsas-45 --period=2025-01-01..2025-12-31
php artisan heritage:disclosure-note --standard=transitional
```

`--standard` accepts `grap-103`, `ipsas-45`, or `transitional`. If `--period` is omitted it defaults to the current calendar year. Without `--out` the note prints to the console. The note is machine-generated; review and adjust it before signing.

---

## Configuration

- **Accounting standards** - stored in `heritage_accounting_standard` (code, name, country, capitalisation flag, valuation methods, disclosure requirements, active flag). Each asset references one standard, which makes the standard a pluggable per-market choice rather than a hardcoded default.
- **Asset classes** - stored in `heritage_asset_class` (hierarchical via `parent_id`).
- **Administration screens** - **Regions** (`/heritage/hadmin/regions`), **Rules** (`/heritage/hadmin/rules`), and **Standards** (`/heritage/hadmin/standards`) provide reference management under the Administration group.
- **Enumerated values** - follow the Heratio Dropdown Manager convention; do not hardcode option lists.
- **Currency** - OCI movements carry a 3-letter currency code per row (defaulting to ZAR when not supplied); set it appropriately for your market.
- **Audit trail** - OCI movements are logged to the audit service when the audit-trail package is installed; the financial write still succeeds if the audit chain is unavailable.
- **Access** - all accounting routes require an admin session; write actions also require the matching ACL permission.
- **Disclosure templates** - markdown templates live in `templates/disclosures/` (`grap-103-note.md.template`, `ipsas-45-note.md.template`, `transitional-note.md.template`) using simple `{{ key }}` placeholders so they stay auditor-readable.

---

## References

- Source: `packages/ahg-heritage-manage/`
- Issue: [GH #580](https://github.com/ArchiveHeritageGroup/heratio/issues/580)
- Related help: Access Requests & Researcher Portal, Privacy and Compliance modules
