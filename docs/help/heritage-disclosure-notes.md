# Heritage Disclosure Notes (GRAP 103 / IPSAS 45)

## Overview

Heratio can generate the statutory disclosure notes that GRAP 103 (South Africa) and IPSAS 45 (international) require for heritage assets, populated from the live heritage register. The same engine also generates the transitional note used by first-time adopters of either standard.

The disclosure note is a markdown file that you, your accountant, or your auditor can paste into an annual financial statement, or attach as a supporting schedule.

## What gets disclosed

For the reporting period you pass in, the renderer pulls:

- Number of recognised heritage assets and total carrying amount (from `heritage_asset`).
- Distribution of measurement bases (cost / fair_value / revaluation / nominal).
- Movement totals for the period:
  - Revaluation increases and decreases.
  - Impairment losses and reversals.
  - Disposals.
  - Split by where each movement was posted: OCI (Revaluation Reserve), P&L, or Reserve (retained earnings on disposal).
- A list of active qualified valuers and their credentials (from the valuer registry).

Movements are pulled from `ahg_heritage_oci_movement`, which the OCI / Revaluation Reserve admin screen and the `OciMovementService` write to.

## Usage

### Render a GRAP 103 note for the SA fiscal year

```bash
php artisan heritage:disclosure-note \
    --standard=grap-103 \
    --period=2025-04-01..2026-03-31 \
    --out=/tmp/grap-103-note-2026.md
```

### Render an IPSAS 45 note for the calendar year

```bash
php artisan heritage:disclosure-note \
    --standard=ipsas-45 \
    --period=2025-01-01..2025-12-31
```

(Omitting `--out` prints to stdout.)

### Render the transitional note

```bash
php artisan heritage:disclosure-note --standard=transitional
```

## Valuer Registry

Manage qualified valuers at **Heritage Accounting -> Administration -> Valuer Registry** (`/admin/heritage/valuers`). Each valuer entry captures:

- Name and credential (e.g. RICS, ASA, AIC).
- Issuing professional body.
- Accreditation number.
- Contact details.
- Specialisations (e.g. `fine_art`, `manuscripts`, `natural_history`).

OCI movements can be tagged with a valuer; the disclosure note lists the active valuers automatically.

## OCI / Revaluation Reserve

Record revaluations, impairments, reversals, and disposals at **Heritage Accounting -> Administration -> OCI Movements** (`/admin/heritage/oci`). The service automatically splits each entry between OCI, P&L, and Reserve according to GRAP 103.51 / IPSAS 45.74 - you don't have to know the standard by heart.

## Audit trail

Every OCI movement writes a hashed chain row into `ahg_audit_log` via the #676 Phase 5 audit chain. This means each disclosure note is reproducible from an immutable ledger that can be independently verified.

## References

- Source: `packages/ahg-heritage-manage/`
- Reference: `docs/reference/heritage-accounting-phase-2-valuer-oci-disclosure.md`
- Issue: [GH #668](https://github.com/ArchiveHeritageGroup/heratio/issues/668)
