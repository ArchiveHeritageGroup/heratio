> Heratio Help Center article. Category: Research / Researcher Portal.

# Researcher Portal

The Researcher Portal is the personal hub for registered researchers in Heratio. It brings registration, a workspace dashboard, research projects, saved searches, evidence collections, bibliographies, notes (annotations), reading-room bookings and notifications together under one set of `/research/*` pages, with admin tools for staff to approve researchers and run the reading room.

## Overview

Every authenticated user reaches the portal at `/research` (which redirects to `/research/dashboard`). The portal is gated by researcher status: a logged-in user must register as a researcher and be **approved** by staff before booking visits, creating projects, generating API keys, or saving most workspace items. Visitors who do not yet have an account can self-register through a public form.

The portal is organised around a left sidebar that adapts to the page you are on (workspace, profile, projects, and so on). A self-declared **research mode** (beginning, intermediate, advanced) tailors the sidebar and on-screen guidance to your experience.

Reproduction requests and team workspaces are part of the portal but have their own dedicated help articles; this guide covers them only in passing and focuses on the portal hub.

## Key features

- **Researcher registration** - public self-service signup plus an authenticated registration form, with re-application for previously rejected accounts.
- **Profile** - your researcher details, affiliation, ORCID iD, identity fields, and research mode selector.
- **Workspace dashboard** - landing view with your stats, recent activity, unread notifications, recent journal entries, and (for staff) pending approvals and the day's bookings.
- **Research projects** - create and manage projects with analysis and output tools (knowledge graph, timeline, map, snapshots, exports).
- **Saved searches** - store catalogue searches, re-run them, and snapshot or diff results over time.
- **Collections (evidence sets)** - group archival records into named sets with per-item notes.
- **Bibliographies** - build reference lists and export them as BibTeX, RIS, or CSL-JSON.
- **Annotations (notes)** - private notes attached to records or kept standalone.
- **Reading-room bookings** - request a visit, then have staff confirm, check in, check out, or mark a no-show.
- **Notifications** - in-portal alerts plus notification preferences.
- **Citations** - generate and export citations for any record in multiple styles.
- **API keys** - approved researchers can mint personal API keys for programmatic access.
- **Admin tools** - researcher approval, rooms, seats, equipment, retrieval queue, walk-ins, institutions, and statistics.

## How to use

### Register as a researcher

If you do not have an account yet:

1. Open **`/research/publicRegister`**.
2. Enter a username (3+ characters), a valid email, and a password (8+ characters, confirmed), along with your name, affiliation, research interests, and identity details.
3. Submit. Your account is created in the researcher seat and marked **pending approval**. You land on the registration-complete confirmation page.

If you are already logged in but not yet a researcher, open **`/research/register`** and complete the same details. A previously rejected applicant can re-apply: the rejected record is reset to **pending** and the rejection reason is cleared.

Staff then approve, verify, suspend, or reject your application (see Configuration). Until you are approved, booking, projects, and API keys are blocked.

### Set up your profile

Go to **`/research/profile`** to maintain your title, name, phone, affiliation type, institution, department, position, research interests, current project, and ORCID iD.

- **Identity fields** (ID type and ID number) can be set once by you while still empty; after that only an administrator can change them.
- **Research mode** - pick beginning, intermediate, or advanced. This drives the sidebar selector and the on-screen levels guide. It can also be saved from the sidebar without leaving the page.

### Work from the dashboard

**`/research/dashboard`** is your home base. Approved researchers see personal stats, recent activity, unread notification count, and recent journal entries. The sidebar links out to every workspace area below.

### Save and re-run searches

1. Run a search in the GLAM catalogue browse, then use the **Save search** action (or post to `/research/saved-searches`).
2. Manage stored searches at **`/research/savedSearches`**.
3. **Run** a saved search to re-execute it, **snapshot** its current results, or **diff** a new run against an earlier snapshot to see what changed.
4. Delete a saved search you no longer need.

### Build evidence collections

1. Go to **`/research/collections`** and create a named collection (an evidence set).
2. Add archival records to it from the **Add to collection** action, or directly from the collection view.
3. Open a collection at **`/research/viewCollection?id=...`** to add per-item notes, edit the collection, remove items, or delete the whole set.

### Keep notes (annotations)

Open **`/research/annotations`** to create private notes. A note can be attached to a specific record or kept standalone, and you can edit or delete your own notes at any time.

### Compile bibliographies

1. Open **`/research/bibliographies`** and create a bibliography.
2. View it at **`/research/viewBibliography/{id}`** to add, edit, or remove entries.
3. Export the whole bibliography as **BibTeX, RIS, or CSL-JSON** via the export action, or export a single entry the same way, for use in reference managers.

### Manage research projects

1. Go to **`/research/projects`** and create a project.
2. Open a project at **`/research/viewProject/{id}`** to manage it.
3. From a project you can reach analysis and output tools: knowledge graph, assertions and hypotheses, snapshots, timeline, map, network graph, and packaging/output options. These are advanced tools layered on top of the core project record.

### Book a reading-room visit

1. You must be an **approved** researcher. Open **`/research/book`**.
2. Choose a reading room, date, and time, and list the materials you want to consult.
3. Submit the request. Track it at **`/research/viewBooking/{id}`**.
4. Staff confirm the booking, then check you in on arrival and check you out when you leave (or mark a no-show). You can cancel a booking you no longer need.

### Generate citations

From any record use the **Cite** action, which opens **`/research/cite/{slug}`**. Export the citation in formats including RIS, BibTeX, EndNote, APA, MLA, and Chicago.

### Notifications

Open **`/research/notifications`** to read portal alerts. The **Preferences** tab lets you choose which notifications you receive.

### Generate an API key

Approved researchers can open **`/research/apiKeys`** to mint a personal API key. The key value is shown **once** at creation, so copy it immediately - it cannot be retrieved later. Keys can be revoked from the same page.

## Configuration

Most portal settings live in admin pages behind the `admin` middleware (staff only) and in the central Dropdown Manager.

### Approving and managing researchers

- **`/research/researchers`** - the researcher admin list. Review pending applications and **approve**, **verify**, **reject** (with a reason), **suspend**, or **reset password**.
- **`/research/viewResearcher/{id}`** - the individual researcher record.
- Approval and account changes are written through a central user provisioner so the portal does not write directly to core auth tables; approval also manages researcher seat membership.

### Reading room, seats, and equipment

- **`/research/rooms`** and **`/research/editRoom`** - reading rooms.
- **`/research/seats`** - seat inventory.
- **`/research/equipment`** and **`/research/equipment-history/{id}`** - bookable equipment and its history.
- **`/research/retrievalQueue`** - materials to be retrieved for visits.
- **`/research/walkIn`** - register walk-in visitors who arrive without a prior booking.
- **`/research/bookings`** (admin) - all bookings for confirm/check-in/check-out workflows.

### Reference data and statistics

- **`/research/institutions`** - the institutions list used by registration and profiles.
- **`/research/adminTypes`** - portal reference/enumerated types.
- **`/research/adminStatistics`** (also `/research/admin/statistics`) - portal usage statistics.
- Enumerated values (for example identity type, equipment type and condition, seat type) come from the Dropdown Manager at `/admin/dropdowns`, never from hardcoded lists.

### ODRL rights policies

Digital rights are enforced through ODRL policies managed at **`/research/odrlPolicies`**. Policies govern viewing (`odrl:use`) and reproduction/printing (`odrl:reproduce`) of archival descriptions. Records with no matching policy are accessible by default, and administrators bypass policy checks. Autocomplete endpoints back the researcher and target fields on the policy form.

### Audit trail

Portal and record changes are logged and viewable under **`/audit`**, including per-record (`/audit/record/{table}/{id}`) and per-user (`/audit/user/{id}`) views.

## Known issues

- The authenticated `/research/register` URL is shadowed by the application login route, so the workspace "re-apply" path for rejected researchers is routed through **`/research/renewal`** instead. This is intentional and handled in code; use the on-page links rather than typing `/research/register` directly.

## References

- Source: packages/ahg-research/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/618
