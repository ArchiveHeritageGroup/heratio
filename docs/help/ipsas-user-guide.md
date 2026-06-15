> Heratio Help Center article. Category: Accounting & Compliance.

# IPSAS Heritage Asset Accounting

IPSAS Heritage Asset Accounting is an optional, pluggable international compliance module for Heratio. It lets an institution keep a financial register of its heritage assets - artworks, archival records, historical objects, buildings, library and photographic collections and more - alongside their valuations, impairment reviews, insurance policies and year-end summaries. The module is built around the International Public Sector Accounting Standards (IPSAS), with explicit support for switching the reporting framework to other jurisdictional standards (IFRS, GAAP, or South Africa's GRAP) where a market requires it. It is one of several accounting and compliance modules that sit alongside the Heratio core; it is not required and can be left disabled if your institution does not report under public-sector accounting rules.

## Overview

The module maintains a heritage asset register that is distinct from the descriptive catalogue. Each asset row can link back to an archival information object and a repository, but it carries its own financial fields: acquisition cost, valuation basis, current value, condition, risk level, insurance and status. A dashboard summarises totals and flags compliance warnings (for example, active assets with no current valuation, or insurance policies expiring within 30 days). All screens use the standard Heratio Bootstrap 5 admin theme.

Because the standard is configurable, the same register can be presented as IPSAS, IFRS, GAAP or GRAP reporting. The default is IPSAS as the international baseline; other frameworks act as jurisdictional overlays.

## Key features

- **Heritage asset register** - record assets with auto-generated asset numbers (format HA-00001), category, location, acquisition details and currency.
- **Asset categories** - ships with a default set covering Art, Archival Records, Historical Objects, Natural Heritage, Heritage Buildings, Library Collections, Photographic Collections, Audio-Visual and operational Equipment.
- **Valuation history** - record initial valuations, revaluations, impairments, reversals and disposals, including valuer name, qualification and method. Recording a new value updates the asset's current value automatically.
- **Impairment reviews** - track assessments, impairment indicators and whether a loss was recognised.
- **Insurance register** - track policies, insurers, sums insured, coverage dates and renewal status, with automatic flagging of policies expiring within 30 days.
- **Financial year summary** - calculate totals, acquisitions, valuations and recognised impairments for any year.
- **Dashboard and compliance status** - headline totals (asset count, total value, insured value, expiring insurance) plus a breakdown by valuation basis and category.
- **CSV report export** - download the asset register as a CSV file.
- **Configurable reporting standard** - switch between IPSAS, IFRS, GAAP and GRAP, set the default currency and financial-year start.

## How to use

The module lives under the `/ipsas` route prefix and is restricted to authenticated administrators.

1. **Open the dashboard.** Go to `/ipsas`. You will see headline statistics, a compliance status banner, valuation-basis and category breakdowns, recently added assets and any insurance expiring soon.
2. **Register an asset.** From the dashboard Quick Actions, choose Add New Asset, or go to `/ipsas/asset/create`. Fill in the title, category, location, acquisition details (date, method, source, cost and currency), the valuation basis and current value, and the condition rating, then submit. The system assigns the next asset number automatically and opens the asset view.
3. **Browse and filter the register.** Go to `/ipsas/assets`. Filter by category, status or valuation basis, or search by title or asset number.
4. **View an asset.** Open `/ipsas/asset/{id}` to see its detail together with its full valuation history and any impairment records.
5. **Edit an asset.** From the asset view, choose Edit, or go to `/ipsas/asset/{id}/edit`, to update the title, description, location, status, condition rating and risk level.
6. **Record a valuation.** Go to `/ipsas/valuation/create` (you can pass an asset to pre-select it). Enter the valuation date, type (initial, revaluation, impairment, reversal or disposal), basis, the previous and new values, and the valuer details. Saving a new value updates the asset's current value.
7. **Review valuations and impairments.** Browse `/ipsas/valuations` (filter by type and year) and `/ipsas/impairments` (optionally show only recognised impairments).
8. **Manage insurance.** Go to `/ipsas/insurance` to list policies; filter by status. The dashboard highlights any policy expiring within 30 days.
9. **Close out a year.** Go to `/ipsas/financial-year` and pick a year to see its summary of total assets and value, acquisitions, valuations and recognised impairments.
10. **Export a report.** Go to `/ipsas/reports`, choose a year, and generate the CSV asset-register export.

## Configuration

Open `/ipsas/config` (Settings on the dashboard) to set module-wide options. Settings are stored under the `ipsas` settings group and include:

- **Organization name** - shown on reports.
- **Accounting standard** - IPSAS (international, the default baseline), IFRS, GAAP, or GRAP (South Africa). Other frameworks are jurisdictional overlays on the IPSAS baseline.
- **Default currency** - a three-letter ISO 4217 code (for example USD, EUR, GBP, ZAR). Leave blank to show amounts without a currency prefix.
- **Financial year start** - the month the financial year begins.
- **Depreciation policy** - none (the usual choice for heritage assets), straight line, or reducing balance.
- **Valuation frequency (years)** - how often revaluations are expected (default 5).
- **Nominal value** - the value applied to assets that cannot be reliably measured.
- **Impairment threshold (%)** - the threshold above which an impairment is recognised (default 10).
- **Insurance review period (months)** - how often policies should be reviewed (default 12).

Under IPSAS, heritage assets may be recognised at nominal value, are typically not depreciated, and should be subject to regular impairment assessment, with fair-value revaluation every few years. These defaults reflect that approach and can be changed per institution and per jurisdiction.

## References

- Source package: `packages/ahg-ipsas/`
- GitHub issue: [#587](https://github.com/ArchiveHeritageGroup/heratio/issues/587)
