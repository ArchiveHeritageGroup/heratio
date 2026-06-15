> Heratio Help Center article. Category: Compliance Modules.

# NAZ Compliance Module (National Archives of Zimbabwe)

The NAZ module is an optional, pluggable per-market compliance pack for Heratio. It adds the records-management and access-control workflows that institutions operating under the National Archives of Zimbabwe Act [Chapter 25:06] need: closure periods on restricted records, protected-record registers, agency retention schedules, records transfers, a researcher registry, and research permits. Heratio's core platform is jurisdiction-neutral and international; NAZ is one of several market modules (alongside GRAP 103, IPSAS, POPIA, CDPA, NMMZ and others) that you enable only if your repository operates in that jurisdiction. If you do not need Zimbabwe-specific archival rules, you can leave this module unused and nothing in the core product changes.

## Overview

When enabled, the module exposes a self-contained admin area under **Admin -> NAZ** (`/admin/naz`). All pages require an authenticated session, and create/update actions are gated by Heratio's standard ACL middleware (`acl:create`, `acl:update`), so read access and write access can be granted separately.

The module keeps its own tables (closure periods, protected records, retention schedules, transfers, researchers, permits, research visits, configuration and an audit log). Every create and update is written to a dedicated audit trail so that compliance actions can be reviewed later.

## Key features

- **Dashboard** with live counts (active closures, pending and active permits, local vs foreign researchers, pending transfers, active schedules and protected records) plus a compliance panel that flags overdue closures, expired permits and overdue transfers.
- **Closure periods** - place a time-bound closure on an archival description, with a closure type (standard, extended, indefinite, ministerial), reason, start/end dates, an authority reference and a review date. The default closure length is configurable (25 years by default, per Section 10 of the Act).
- **Protected records** - a register of records exempt from access (cabinet, security, personal, legal or commercial protection) with protection start/end dates and review dates.
- **Retention (records) schedules** - per-agency disposal schedules: record series, active and semi-active retention periods, disposal action (destroy, transfer, review, permanent), legal authority and classification.
- **Records transfers** - track transfers of records from a government agency into the archive, optionally linked to a retention schedule, with quantities (linear metres, boxes, items), restriction flags and itemised series lines.
- **Researchers** - a researcher registry distinguishing local, foreign and institutional researchers. Sensitive personal fields (phone, passport number, national ID, address and personal notes) are encrypted at rest through Heratio's shared EncryptionService and decrypted only for display.
- **Research permits** - issue and manage permits tied to a researcher, with permit type, research topic, validity dates, fee details (amount, currency, paid flag, receipt) and recorded research visits.
- **Reports** - summary and per-area reports (closures, protected records, schedules, permits, transfers, audit) with optional date-range filters.
- **Audit log** - a filterable record of every create/update action across the module.

## How to use

All paths below sit under `/admin/naz` and appear in the **Admin -> NAZ** menu when the module is enabled.

1. **Open the dashboard.** Go to **Admin -> NAZ** (`/admin/naz`). Review the headline counts and the compliance panel for any overdue items.
2. **Register a researcher.** Open **Researchers** (`/admin/naz/researchers`), click **Create** (`/admin/naz/researchers/create`), choose the researcher type (local, foreign or institutional), complete the contact and identification details, set the registration date, and save. The researcher record opens at `/admin/naz/researchers/{id}`.
3. **Issue a research permit.** Open **Permits** (`/admin/naz/permits`), click **Create** (`/admin/naz/permits/create`), select the registered researcher, enter the permit number, research topic, validity dates and any fee, then save. The permit view (`/admin/naz/permits/{id}`) lists recorded research visits.
4. **Record a closure period.** Open **Closures** (`/admin/naz/closures`), click **Create** (`/admin/naz/closures/create`), pick the archival description, set the closure type, start date and (where applicable) end date and authority reference, then save. Edit an existing closure at `/admin/naz/closures/{id}/edit` to release or extend it.
5. **Maintain the protected-records register.** Open **Protected Records** (`/admin/naz/protected-records`) to review records exempt from access. Use the status, protection-type and search filters to narrow the list.
6. **Define a retention schedule.** Open **Schedules** (`/admin/naz/schedules`), click **Create** (`/admin/naz/schedules/create`), enter a unique schedule number, the agency and record series, retention periods and disposal action, then save. View and update it at `/admin/naz/schedules/{id}`.
7. **Log a records transfer.** Open **Transfers** (`/admin/naz/transfers`), click **Create** (`/admin/naz/transfers/create`), name the transferring agency, optionally link a retention schedule, enter quantities and add itemised series lines, then save. Track progress through its status on the transfer view (`/admin/naz/transfers/{id}`).
8. **Run a report.** Open **Reports** (`/admin/naz/reports`) and choose a report type (summary, closures, protected, schedules, permits, transfers or audit). Optionally set a date range to scope the figures.
9. **Review the audit trail.** Open **Audit Log** (`/admin/naz/audit-log`) and filter by action type, entity type, user or date range to see who changed what and when.

## Configuration

Settings live under **Admin -> NAZ -> Config** (`/admin/naz/config`). Edit the values and save; every change is recorded in the audit log. The module ships with these keys:

| Key | Default | Purpose |
|-----|---------|---------|
| `closure_period_years` | `25` | Default closure period in years (Section 10) |
| `foreign_permit_fee_usd` | `200` | Research permit fee for foreign researchers |
| `local_permit_fee_usd` | `0` | Research permit fee for local researchers |
| `permit_validity_months` | `12` | Default permit validity in months |
| `transfer_reminder_months` | `6` | Months before due date to flag a transfer |
| `naz_repository_name` | `National Archives of Zimbabwe` | Repository name shown in the module |
| `director_name` | (blank) | Director of the National Archives |
| `naz_email` | `info@archives.gov.zw` | Contact email |
| `naz_phone` | `+263 242 792741` | Contact phone |

These defaults are seeded on install and can be changed at any time. The fees and validity values drive the figures shown on the dashboard. Personal data in the researcher registry is encrypted at rest using the same category gates as the research portal, so encryption follows whatever policy your installation has set for contact details and personal notes.

The admin interface uses the standard Heratio Bootstrap 5 theme, so the NAZ pages match the rest of the admin area.

## References

- Source package: `packages/ahg-naz/`
- GitHub issue: https://github.com/ArchiveHeritageGroup/heratio/issues/604
