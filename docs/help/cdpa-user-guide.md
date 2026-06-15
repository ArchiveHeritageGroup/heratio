> Heratio Help Center article. Category: Compliance.

# CDPA Data Protection Compliance

The CDPA module is a pluggable per-market compliance add-on that helps an organisation track and evidence its obligations under a Cyber and Data Protection Act type regime. It ships with the field set, deadlines, and regulator workflow modelled on the Zimbabwe Cyber and Data Protection Act [Chapter 12:07] (POTRAZ-administered), and sits alongside the jurisdiction-neutral Heratio core rather than inside it. If your market does not require this regime, the module is simply not installed.

---

## Overview

Heratio is an international, jurisdiction-neutral GLAM platform. Data-protection regimes differ from one market to the next, so each is delivered as its own optional module. The CDPA module is one such module. When installed, it adds an admin area at `/admin/cdpa` that lets compliance staff:

- Record the organisation's data controller licence and track expiry.
- Appoint and register a Data Protection Officer (DPO).
- Maintain a register of processing activities (ROPA).
- Capture and search consent records.
- Log, triage, and resolve data subject requests against a statutory deadline.
- Run Data Protection Impact Assessments (DPIA) with risk levels and DPO sign-off.
- Record data breaches and track regulator and data-subject notifications.
- Produce a compliance dashboard and summary reports.

Every create, update, and configuration change is written to an immutable audit log (`cdpa_audit_log`) with the acting user, IP address, and a JSON snapshot of the change.

The whole area is protected by the `admin` middleware, so only signed-in administrators can reach it.

---

## Key features

| Area | What it does | Route name |
|------|--------------|------------|
| Dashboard | Live compliance status (compliant / warning / non-compliant), licence countdown, pending and overdue request counts, open breaches, active processing activities. | `ahgcdpa.index` |
| Controller licence | Register the regulator licence (number, tier, organisation, issue and expiry dates, regulator reference, estimated data-subject count). | `ahgcdpa.license` |
| Data Protection Officer | Appoint a DPO, capture qualifications and certification, and track the DPO registration form submitted to the regulator. | `ahgcdpa.dpo` |
| Processing register (ROPA) | Document each processing activity: data categories, purpose, legal basis, retention, cross-border transfer, automated decisions, and special-category flags (children, biometric, health data). | `ahgcdpa.processing` |
| Consent records | Store and search consent given by data subjects, including method, withdrawal, guardian consent for children, and biometric consent. | `ahgcdpa.consent` |
| Data subject requests | Log access, rectification, erasure, objection, portability, and restriction requests; auto-assign a `DSR-` reference and a due date; track status to completion. | `ahgcdpa.requests` |
| DPIA | Assess high-risk processing, record risks and mitigations, set a residual risk level and review date, and capture DPO approval. | `ahgcdpa.dpia` |
| Breach register | Record incidents, severity, records and subjects affected, and track the 72-hour regulator notification plus data-subject notification. | `ahgcdpa.breaches` |
| Reports | Summary statistics across processing, consent, requests, breaches, DPIA, and special-data flags, with a date-range filter. | `ahgcdpa.reports` |
| Configuration | Set organisation details, DPO notification email, and the compliance deadlines. | `ahgcdpa.config` |

The dashboard turns the underlying records into a single compliance verdict. It flags an issue when no licence is registered, the licence is expired, no active DPO is appointed, or a request is past its statutory deadline. It raises a warning when the licence expires within 60 days or a breach investigation is still open.

---

## How to use

### Open the module

From the admin area, browse to **Admin -> CDPA** (`/admin/cdpa`). The dashboard loads first and shows your current compliance status with quick-action links down the left.

### 1. Register the controller licence

1. On the dashboard, click **Controller License** (or go to `/admin/cdpa/license`).
2. Click the edit / register button to open `/admin/cdpa/license/edit`.
3. Enter the licence number, tier, organisation name, registration date, issue date, and expiry date. Add the regulator reference and an estimated data-subject count if you have them.
4. Save. The dashboard licence countdown updates immediately and clears the "no licence" issue.

The dashboard warns when the licence is within 60 days of expiry; the renewal reminder lead time is configurable (see Configuration).

### 2. Appoint a Data Protection Officer

1. Click **Data Protection Officer** on the dashboard (or go to `/admin/cdpa/dpo`).
2. Open the edit form at `/admin/cdpa/dpo/edit`.
3. Enter the DPO's name, email, phone, qualifications, certification number, appointment date, and (if relevant) term end date.
4. Tick the box and add the date and reference once the DPO registration form has been submitted to the regulator.
5. Mark the record active and save. An active DPO clears the "no DPO" compliance issue.

### 3. Build the processing register (ROPA)

1. Go to **Processing Register** (`/admin/cdpa/processing`).
2. Click the add button to open `/admin/cdpa/processing/create`.
3. Complete the activity name, data-subject category, data types, purpose, and legal basis (consent, contract, legal obligation, vital interest, public interest, or legitimate interest).
4. Set the storage location, retention period, and security safeguards.
5. Tick any that apply: cross-border transfer (and its safeguards), automated decision-making, children's data, biometric data, health data.
6. Save. Use the category and status filters on the list page to find activities later; edit any entry at `/admin/cdpa/processing/edit`.

### 4. Record consent

1. Go to **Consent Management** (`/admin/cdpa/consent`).
2. The list page shows all consent records joined to their processing activity, with a search box (name, email, or purpose) and an active filter.
3. Each record captures the consent method, date, withdrawal date and reason, and flags for biometric or children's (guardian) consent.

### 5. Handle data subject requests

1. Go to **Data Subject Requests** (`/admin/cdpa/requests`).
2. Click the add button to open `/admin/cdpa/requests/create`.
3. Choose the request type, enter the data subject's details, the request date, and the due date. Heratio assigns a unique `DSR-` reference automatically and sets the status to pending.
4. Open a request from the list to view it (`/admin/cdpa/requests/view`). From there you can update the status, add response notes, record a rejection or extension reason, and set the completed date.
5. When you mark a request completed without a completion date, the current date is filled in for you. The request's own audit trail is shown on the view page.

Requests past their due date are highlighted on both the list and the dashboard as overdue.

### 6. Run a DPIA

1. Go to **DPIA** (`/admin/cdpa/dpia`).
2. Click to create at `/admin/cdpa/dpia/create`. Optionally link the assessment to a processing activity.
3. Record the necessity assessment, risk level, assessor, and next review date.
4. Open the DPIA at `/admin/cdpa/dpia/view` to add identified risks and mitigation measures, set the residual risk level, and capture DPO approval, approval date, and comments.

The dashboard counts DPIAs that are not yet completed and surfaces those due for review within 30 days.

### 7. Log and close a breach

1. Go to **Breach Register** (`/admin/cdpa/breaches`).
2. Click to create at `/admin/cdpa/breaches/create`. Enter the incident and discovery dates, a description, breach type, the records and subjects affected, and severity. Heratio assigns a `BRE-` reference and sets the status to investigating.
3. Open a breach at `/admin/cdpa/breaches/view` to track the regulator notification (with date and reference), the data-subject notification, root cause, remediation, and prevention measures.
4. Setting the status to closed without a closed date stamps it automatically.

The regulator notification window defaults to 72 hours and is configurable.

### 8. Review reports

Go to **Reports** (`/admin/cdpa/reports`) for summary statistics: processing by category and legal basis, consent totals (active, withdrawn, biometric, children), requests by type and status with average response days, breaches by type and severity, DPIA by status and risk, and the special-data flag counts. Use the date-from and date-to filters to scope the request and breach figures.

---

## Configuration

Open **Settings** from the dashboard, or browse to `/admin/cdpa/config`. Settings are saved to the `cdpa_config` table and a save is itself recorded in the audit log.

| Setting | Default | Purpose |
|---------|---------|---------|
| Organisation name | (empty) | Used in reports. |
| Organisation address | (empty) | Used in reports. |
| DPO email | (empty) | Address for compliance notifications. |
| Data subject request response (days) | 30 | Statutory response deadline for data subject requests. |
| Breach notification (hours) | 72 | Window within which the regulator must be notified. |
| Licence renewal reminder (days) | 90 | How far ahead of expiry to surface a warning. |
| DPIA review period (months) | 12 | Recommended interval between DPIA reviews. |

The deadlines above are seeded on first install so the module is usable immediately. Adjust them to match your market's regime if it differs from the shipped defaults.

---

## References

- Source: `packages/ahg-cdpa/`
- Issue: [GH #551](https://github.com/ArchiveHeritageGroup/heratio/issues/551)
- Database schema: `packages/ahg-cdpa/database/install.sql` (tables prefixed `cdpa_`)
- Routes: `packages/ahg-cdpa/routes/web.php` (prefix `/admin/cdpa`, `admin` middleware)
- Controller: `packages/ahg-cdpa/src/Controllers/CdpaController.php`
- Regime modelled: Cyber and Data Protection Act [Chapter 12:07], administered by the national regulator (POTRAZ) in the Zimbabwe market. Compliance modules are pluggable per market and sit alongside the Heratio core.
