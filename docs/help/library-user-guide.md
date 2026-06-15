> Heratio Help Center article. Category: Library Management.

# Library Management User Guide

The Library module is a full integrated library system inside Heratio. It covers cataloguing (manual entry, ISBN lookup, MARC21 editing, Z39.50 copy cataloguing, ONIX ingest), authority control, acquisitions (vendors, budgets, orders), serials (subscriptions, issue prediction, claims, bindery), circulation (patrons, loans, holds, fines, notices), interlibrary loan with EDI trading partners, electronic-resource management (KBART, COUNTER/SUSHI usage, OpenURL link resolution, the ODI quality scorecard), and two public discovery surfaces: a browsable catalogue and an OPAC with patron self-service. Staff tools live under `/library-manage/...` (sign-in required, ACL enforced per action); the public catalogue is at `/library` and the OPAC at `/opac`. All admin screens use the Bootstrap 5 theme.

## Overview

The module is named "Library cataloguing and management for Heratio" and is organised around a few clear surfaces:

- **Public catalogue** at `/library` (alias `/library/browse`) - anyone can browse and open a record at `/library/{slug}`.
- **OPAC** at `/opac` - a public-facing catalogue with patron accounts, holds, and renewals (only active when OPAC is enabled).
- **Staff management** under `/library-manage/...` - cataloguing, acquisitions, serials, circulation, ILL, electronic resources, and reports. These routes require authentication and enforce ACL middleware (`acl:create`, `acl:update`, `acl:delete`) on the actions that change data.
- **Machine interfaces** - SRU (Search/Retrieve via URL) at `/library/sru`, a SUSHI 5.0 COUNTER server under `/api/sushi/r5/...`, and an ONIX ingest API at `/api/library/ingest/onix`.

Cataloguing data, authority records, acquisitions, serials, circulation, and usage statistics are all held in the module's own `library_*` tables.

## Key features

### Cataloguing
- **Manual cataloguing:** add a record at `/library/add`, edit at `/library/{slug}/edit`, with slug preview and a subject-suggestion helper.
- **ISBN lookup:** look up bibliographic data by ISBN (`/library-manage/isbn-lookup`); ISBN providers are configurable at `/library-manage/isbn-providers` (each provider can be edited, toggled active, or removed). Book-cover images resolve via `/library/cover-image/{isbn}`.
- **MARC editor:** a full MARC21 workbench at `/library-manage/marc` - edit records field by field, validate against MARC rules, preview a merge, import MARC from a form or from binary ISO 2709 files (with a preview-then-commit step), and download records as MARC text or binary.
- **Copy cataloguing (Z39.50):** search remote Z39.50 targets at `/library-manage/copy-cataloguing/search` and import matching records. Targets are managed at `/library-manage/copy-cataloguing/targets`.
- **ONIX ingest:** load publisher ONIX feeds at `/library-manage/onix` (upload -> review lines -> commit), set per-line status, or post XML directly to the API endpoint.

### Authority control
- Maintain authority records (names, subjects, etc.) at `/library-manage/authority`: create, view, edit, and delete authorities, and **link** / **unlink** them to catalogue entities so headings stay consistent.

### Acquisitions
- A dashboard at `/library-manage/acquisition/dashboard`.
- **Vendors:** maintain suppliers at `/library-manage/acquisition/vendors`.
- **Budgets:** create and track budgets at `/library-manage/acquisition/budgets`.
- **Orders:** create purchase orders at `/library-manage/acquisitions`, add and edit order lines, transition order status, receive all lines, and record a **write-off** for disposal accounting on an order.

### Serials
- Manage serials at `/library-manage/serials`: create, edit, clone, and delete serial titles, and add issues.
- **Subscriptions** are recorded per serial (`/library-manage/serial/{id}/subscription`).
- **Issue prediction** generates expected issues (`/library-manage/serial/{id}/predict`) and a **coverage** view shows holdings.
- **Claims** for missing issues: an overdue-claims list (`/library-manage/serial/overdue-claims`) and per-issue claiming.
- **Bindery:** send issues for binding and receive them back via the bindery dashboard (`/library-manage/serial-bindery`).

### Circulation
- A circulation desk at `/library-manage/circulation`: scan a barcode, check items out, return them, and renew loans, with a live loans list and per-patron history.
- **Holds:** place and cancel holds from the desk.
- **Patrons:** manage borrowers at `/library-manage/patrons` - create, view, edit, suspend, and reactivate; patron categories drive loan rules.
- **Loan rules and overdues:** read-only views at `/library-manage/circulation/loan-rules` and `/library-manage/circulation/overdue`.
- **Notices:** overdue-notice templates are editable with a live preview at `/library-manage/notice-templates`.
- Fines are calculated automatically (see scheduled tasks below).

### Interlibrary loan (ILL)
- A basic ILL workspace at `/library-manage/ill` (create, view, update, transition status, delete, OPAC suppress) plus ILL settings, and an OPAC-side request form at `/opac/ill/create`.
- A richer **ILL request management** surface at `/library-manage/ill-requests` with status transitions and an EDI send action.
- **EDI trading partners** at `/library-manage/trading-partners`: configure partners, toggle them, test the connection, and preview the EDI message that would be sent.

### Electronic resources and usage
- **KBART:** import and export KBART knowledge-base files at `/library-manage/kbart` (import preview -> commit, CSV / KBART export, a downloadable template) and manage scheduled remote KBART feeds at `/library-manage/kbart/remote` (create, edit, refresh, toggle, test a URL, view the refresh log).
- **Usage statistics (COUNTER / SUSHI):** a usage dashboard at `/library-manage/usage` with Title (TR) and Database (DR) reports, SUSHI harvesting from subscriptions, and exports for PR, TR, TR_J1, TR_J3, DR, and IR report types. Heratio also acts as a SUSHI 5.0 **server** under `/api/sushi/r5/...`.
- **ODI quality scorecard:** an Open Data / metadata-quality scorecard at `/library-manage/odi/scorecard`, refreshable on demand.
- **OpenURL link resolver:** resolves OpenURL requests to full-text or holdings (the resolver service backs the electronic-resource layer).

### Discovery surfaces
- **Public catalogue:** browse at `/library`, open a record at `/library/{slug}`.
- **SRU server:** standards-based search at `/library/sru` (explain + searchRetrieve, read-only, CORS-enabled).
- **OPAC:** at `/opac` with record views, holds, renewals, and an account page; patron self-service login at `/opac/patron/login` gives patrons their own loans, holds, fines, and renew-all.

### Reports
- A reports hub at `/library-manage/reports` with catalogue, creators, publishers, subjects, and call-numbers breakdowns.

## How to use

### Browse the catalogue (public)
1. Go to `/library` (or `/library/browse`).
2. Click any title to open its record at `/library/{slug}`.

### Catalogue a new item by ISBN
1. Sign in and go to **Library manage -> ISBN lookup** (`/library-manage/isbn-lookup`).
2. Enter the ISBN and search; the configured providers return bibliographic data.
3. Confirm to create the catalogue record. (Configure providers first under `/library-manage/isbn-providers` if none are set up.)

### Catalogue manually or with MARC
1. For a quick manual record, go to `/library/add`, fill in the fields, and save.
2. For MARC work, go to `/library-manage/marc`. Open a record to edit fields, use **Validate** before saving, and **Download** as MARC text or binary when needed.
3. To import MARC, choose **Import** (form) or **Binary import** (ISO 2709): upload, preview, then commit.

### Copy-catalogue from a remote library (Z39.50)
1. Go to `/library-manage/copy-cataloguing`.
2. Make sure a target exists under `/library-manage/copy-cataloguing/targets` (add one if needed).
3. Search (`/library-manage/copy-cataloguing/search`), pick a matching record, and **Import** it into the catalogue.

### Place a purchase order
1. Go to **Acquisitions** (`/library-manage/acquisitions`) and create an order.
2. Add order lines, then transition the order status as it progresses.
3. When stock arrives, use **Receive all** to mark the lines received. Budgets at `/library-manage/acquisition/budgets` track committed and spent amounts.

### Manage a serial subscription
1. Go to `/library-manage/serials` and create the serial title.
2. Open the serial and record a **subscription** (`/library-manage/serial/{id}/subscription`).
3. Use **Predict** to generate expected issues and **Coverage** to see holdings.
4. Watch the overdue-claims list (`/library-manage/serial/overdue-claims`) and claim missing issues; send completed volumes to the bindery from `/library-manage/serial-bindery`.

### Run the circulation desk
1. Go to `/library-manage/circulation`.
2. Scan or enter an item barcode to check out, choosing the patron.
3. Use **Return** and **Renew** as items come back, and **Place hold** for requested items.
4. Manage borrowers at `/library-manage/patrons` (create / suspend / reactivate).

### Handle an interlibrary loan
1. Go to `/library-manage/ill-requests` and create a request.
2. Transition its status as it progresses.
3. If you use EDI, configure the partner under `/library-manage/trading-partners` (test the connection and preview the message first), then **Send EDI** from the request.

### Manage electronic resources and usage
1. Import your knowledge base at `/library-manage/kbart` (preview, then commit), and set up automatic remote feeds at `/library-manage/kbart/remote`.
2. View and export COUNTER usage at `/library-manage/usage`; harvest SUSHI data from your subscriptions there.
3. Check metadata quality on the ODI scorecard at `/library-manage/odi/scorecard`.

### Patron self-service (OPAC)
1. A patron signs in at `/opac/patron/login`.
2. From their account they can view loans (`/opac/patron/loans`), holds (`/opac/patron/holds`), and fines (`/opac/patron/fines`), and **renew all** loans.

## Configuration

- **Access control:** staff routes require authentication; data-changing actions enforce `acl:create`, `acl:update`, and `acl:delete` middleware. The OPAC patron area is gated by a dedicated patron-authentication middleware.
- **OPAC toggle:** the public OPAC routes are wrapped in `opac.enabled` middleware, so the OPAC can be switched on or off.
- **ISBN providers:** configured at `/library-manage/isbn-providers` (or `/admin/library/isbn-providers`); add, edit, enable/disable, and order them.
- **Z39.50 targets:** managed at `/library-manage/copy-cataloguing/targets`.
- **KBART remote feeds:** managed at `/library-manage/kbart/remote`, refreshed daily by a scheduled task.
- **Notice templates:** overdue-notice wording is edited at `/library-manage/notice-templates`.
- **EDI trading partners:** configured at `/library-manage/trading-partners`.
- **Controlled vocabularies:** acquisition and related enumerated values come from the Dropdown Manager (`/admin/dropdowns`); do not hardcode them.
- **Scheduled tasks (cron):** the module schedules daily jobs - auto-expire holds (02:30), auto-expire patrons (02:45), calculate fines (03:15), KBART remote refresh (01:00), email usage reports, serial claim alerts (03:30), serial expiry alerts (03:45), and send overdue notices (06:00). It also ships artisan commands for CSV import (`ahg:library-...`) and a reindex command (`LibraryReindexCommand`).
- **Tables:** the module owns `library_*` tables including `library_subject_authority`, `library_entity_subject_map`, `library_ill_request`, `library_serial`, `library_serial_issue`, `library_serial_subscription`, `library_serial_prediction`, `library_claim`, `library_binding`, `library_acquisition_budget`, `library_acquisition_order`, `library_acquisition_order_line`, and `library_isbn_provider`, plus circulation, patron, notice, COUNTER/SUSHI, ODI, KBART, ONIX, and trading-partner tables added by migrations.

## References

- Source: `packages/ahg-library/`
- Issue: [GH #592](https://github.com/ArchiveHeritageGroup/heratio/issues/592)
