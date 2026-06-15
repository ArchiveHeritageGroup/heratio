> Heratio Help Center article. Category: Compliance.

# NMMZ Heritage Compliance Module

The NMMZ module is a pluggable per-market compliance pack for Heratio that manages national monuments, antiquities, export permits, archaeological sites, and heritage impact assessments under the National Museums and Monuments of Zimbabwe Act [Chapter 25:11]. It sits alongside the jurisdiction-neutral Heratio core as one of several per-market modules, so it only appears when the deployment serves that regime.

---

## Overview

Heritage agencies that operate under a statutory monuments-and-antiquities regime need to keep a defensible register of protected places and objects, control the export of cultural material, and assess the heritage impact of new development. The NMMZ module gives administrators a single dashboard and a set of registers covering each of those duties.

Like every per-market compliance pack in Heratio, this module is optional and additive. The core platform stays jurisdiction-neutral. When the NMMZ package is installed, its tables, routes, and dashboard activate; when it is not, nothing about the core changes. Other markets are served by their own modules (for example GRAP 103, POPIA, NAZ, CDPA), and the same register-and-permit pattern can be cloned for any comparable national heritage law.

All NMMZ screens live under the `/nmmz` route prefix and require an authenticated administrator (the routes are protected by the `auth` and `admin` middleware).

---

## Key features

- **Compliance dashboard** (`/nmmz`) with live counts for monuments, antiquities, export permits, archaeological sites, and heritage impact assessments, plus a compliance banner that warns when export permits are awaiting review.
- **National Monuments register** with category, location (province, district, GPS), legal/gazette status, ownership, condition rating, and World Heritage status. Each monument also carries a history of condition inspections.
- **Antiquities register** for objects above a configurable age threshold, capturing object type, material, estimated age, provenance, find location, dimensions, condition, current location, and estimated value.
- **Export permits** workflow: applicants and object details are recorded, then an administrator approves (auto-generating a permit number in the form `EP-YYYY-NNNN`) or rejects with a reason.
- **Archaeological sites register** with site type, period, discovery details, protection status, threats, and research potential.
- **Heritage Impact Assessments (HIA)** for development projects, recording the developer, assessor, impact level, and mitigation measures.
- **Configuration screen** for the statutory thresholds and contact details that drive the module.

---

## How to use

All tasks below assume you are signed in as an administrator. The module is reached from the `/nmmz` URL prefix; open the **NMMZ Compliance Dashboard** first to orient yourself.

### Open the dashboard

1. Go to **/nmmz**.
2. Review the compliance banner at the top. It shows green when requirements are met, or a warning when one or more export permits are pending review.
3. Use the four statistic cards (National Monuments, Antiquities, Pending Permits, Archaeological Sites) and the **Quick Actions** list to jump into each register.

### Register a national monument

1. From the dashboard, open **National Monuments** (or go to **/nmmz/monuments**).
2. Filter the list by category, legal status, province, or a free-text search if needed.
3. Click the add (plus) button, or go to **/nmmz/monument/create**.
4. Complete the form: category, name, description, historical significance, province, district, location description, GPS latitude/longitude, protection level, legal status, ownership type, and condition rating.
5. Submit. You are returned to the monument view page, where you can also see its condition inspection history.

### Maintain the antiquities register

1. Open **Antiquities Register** (**/nmmz/antiquities**). Filter by status, object type, or search text.
2. Click add or go to **/nmmz/antiquity/create**.
3. Capture name, description, object type, material, estimated age in years, provenance, find location, dimensions, condition rating, current location, and estimated value.
4. Submit. New antiquities are created with status `registered`.

### Process an export permit

1. Open **Export Permits** (**/nmmz/permits**). Filter by status to find pending applications.
2. To log a new application, click add or go to **/nmmz/permit/create**, then record the applicant details, object description, quantity, estimated value, export purpose, destination country and institution, and proposed export/return dates. New applications start as `pending`.
3. To decide an application, open it from the list (the permit view page).
4. Choose **Approve** to issue the permit (a permit number `EP-YYYY-NNNN` is generated and any approval conditions you enter are stored), or **Reject** and enter a rejection reason. The reviewing user and review date are recorded automatically.

### Register an archaeological site

1. Open **Archaeological Sites** (**/nmmz/sites**). Filter by province or protection status.
2. Click add or go to **/nmmz/site/create**.
3. Capture name, site type, description, province, district, location, GPS coordinates, period, discovery date, who discovered it, protection status, and research potential.
4. Submit.

### Submit a Heritage Impact Assessment

1. Open **Heritage Impact Assessments** (**/nmmz/hia**). Filter by status or province.
2. Click add or go to **/nmmz/hia/create**.
3. Record the project name, type, description, and location; the developer name, contact, and email; the assessor name and qualification; and the impact level, impact description, and mitigation measures.
4. Submit. New assessments start as `submitted`.

---

## Configuration

Open **Settings** from the dashboard, or go to **/nmmz/config**. The following keys are editable and are stored under the `nmmz` settings group:

| Setting | Purpose |
|---|---|
| `antiquity_age_years` | Age threshold (in years) at which an object is treated as an antiquity. Default 100. |
| `export_permit_fee_usd` | Standard export permit fee. |
| `export_permit_validity_days` | Validity period for an issued export permit. |
| `nmmz_contact_email` | Public contact email for the heritage authority. |
| `nmmz_contact_phone` | Public contact phone number. |
| `director_name` | Name of the executive director shown on official output. |

Save the form to persist changes. The module also ships seed data for monument categories (for example Archaeological Sites, Historical Buildings, Rock Art Sites, Ruins, Memorials, Burial Sites) which appear in the monument category dropdown.

Installation runs `packages/ahg-nmmz/database/install.sql`, which creates the register tables idempotently and seeds the default categories and configuration. The module registers its routes and views automatically through its service provider once the package is enabled.

---

## Known issues

- The **Reports** screen (**/nmmz/reports**) is a placeholder; report content is not yet built out.
- The dashboard compliance check currently raises warnings only for pending export permits; other compliance conditions are not yet evaluated.

---

## References

- Source: packages/ahg-nmmz/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/605
