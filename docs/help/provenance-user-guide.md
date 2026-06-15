> Heratio Help Center article. Category: Collections / Provenance.

# Provenance and Chain of Custody

Heratio records the ownership history and chain of custody of an archival description, capturing each transfer of ownership or custody as a dated event, with supporting agents and documents. This guide explains how archivists and curators view, build, and maintain provenance for a record.

---

## Overview

Provenance answers the question "who has held this item, and how did it come to be here?" Each archival description (information object) can have one provenance record attached to it. That record holds the overall acquisition and status summary, and links to an ordered list of chain-of-custody events, the people or organisations involved (agents), and supporting documents such as deeds of gift, bills of sale, and certificates.

Provenance is stored in dedicated tables (`provenance_record`, `provenance_event`, `provenance_agent`, `provenance_document`) and is keyed to the information object by its slug. The feature is jurisdiction-neutral and is intended for any GLAM context worldwide. It includes fields for special diligence areas that matter to museums and galleries internationally, such as Nazi-era / WWII due diligence and cultural-property / repatriation status.

Three views are provided: a browse list of all records that have provenance, a read-only detail view per record, and a vertical timeline of the custody events. Editing is done through a single full-page form.

---

## Key features

- One provenance record per archival description, linked by slug.
- Ordered chain-of-custody events with from-agent and to-agent, date (precise or free text such as "circa 1920"), location, certainty, and notes.
- A grouped event-type list covering ownership changes (sale, purchase, auction, gift, donation, bequest, inheritance, descent, transfer, exchange), loans and deposits, creation and discovery, loss and recovery (theft, recovery, confiscation, restitution, repatriation), movement (import, export), documentation (authentication, appraisal, conservation, restoration), and institutional events (accessioning, deaccessioning).
- Acquisition details: type, date, free-text date, price, and currency.
- Provenance certainty levels from "certain - documented evidence" through to "unknown - no evidence", plus a gap flag and gap description.
- Research status tracking (not started, in progress, complete, inconclusive) with research notes.
- Nazi-era provenance checked / clear flags with notes.
- Cultural-property status (none, claimed, disputed, repatriated, cleared) with notes.
- Agents (people, organisations, families, or unknown) that are reused across events. Typing a name that does not yet exist creates the agent automatically.
- Supporting documents by upload or external URL, classified by document type (deed of gift, bill of sale, invoice, receipt, auction catalog, certificate, and more).
- A read-only timeline view of events along a date axis.
- Visibility controls: mark the whole record public or private, and mark research as complete.

---

## How to use

### Browse records with provenance

1. Go to **/provenance** in the application.
2. The list shows every archival description that has a provenance record, with the event count and the earliest and latest event dates.
3. Use the **View** or **Timeline** buttons in the Actions column to open a record, or click the record title to open the underlying archival description.

### View provenance for a record

1. From the browse list click **View**, or go to **/provenance/{slug}** where `{slug}` is the record slug.
2. The page lists each recorded event with its type, date, and notes.
3. Use the **Timeline** button (top right) for the chronological view, or **Edit** to make changes.

### View the timeline

1. From a provenance view click **Timeline**, or go to **/provenance/{slug}/timeline**.
2. Events are shown as points on a vertical date line. Records with no events show an empty-state message.

### Build or edit provenance

1. Open the edit form via the **Edit** button or **/provenance/{slug}/edit**.
2. **Provenance Summary** - enter a human-readable provenance statement. This is shown publicly when the record is marked public.
3. **Acquisition Details** - choose the acquisition type, set a date or a free-text date such as "circa 1950", and optionally record a price and currency.
4. **Chain of Custody Events** - click **Add Event** to add a row. For each event choose the type, set a date or date text, pick a certainty, and enter the From (Agent) and To (Agent), a location, and notes. The agent name fields offer autocomplete against existing agents. Remove a row with its **X** button.
5. **Research Notes** - set the research status and notes. Tick "There are gaps in the provenance chain" to reveal a gap-description box.
6. **Supporting Documents** - click **Add Document**, choose a document type and title, set a date, and either upload a file or supply an external URL. Files upload when you save the form.
7. In the sidebar set the **Status** (current status, custody type, certainty level), the **Current Owner/Holder** (name and type), the **Nazi-Era Provenance** check and result, and the **Cultural Property** status.
8. Use the **is public** and **research is complete** checkboxes to control visibility and completion.
9. Click **Save Provenance**. You are returned to the archival description, which shows a short provenance panel with a link back to the full details.

> Note: when you save, the chain-of-custody events are fully rebuilt from the rows currently in the form. Keep every event you wish to retain on the page before saving, otherwise it is removed.

### Delete events and documents

- On the edit form, remove an event row before saving to delete it.
- For an existing uploaded document, click its **Delete** button. You are asked to confirm; the file and its record are then removed.

---

## Configuration

- **Routes** - all provenance routes are under the `/provenance` prefix and require an authenticated user (`auth` middleware). There is no separate menu entry shipped by the package; reach the feature at **/provenance** or from the provenance panel on an archival description.
- **Agents** - stored in `provenance_agent` and reused across events. New agent names are created on first use; existing names are matched and reused.
- **Document storage** - uploaded files are written to a `provenance` subfolder under the configured uploads path (`heratio.uploads_path` in `config/heratio.php`), and served from `/uploads/provenance/`. Uploaded documents default to not public.
- **Event and acquisition types, certainty levels** - supplied by the service as fixed grouped lists for the dropdowns; document types are listed on the document form.
- **Tables** - installed from `database/install.sql`: `provenance_record`, `provenance_record_i18n`, `provenance_event`, `provenance_event_i18n`, `provenance_agent`, `provenance_agent_i18n`, and `provenance_document`. Deleting an archival description cascades to its provenance record and events.
- **Related package** - a separate `ahg-provenance-ai` package may assist with provenance research. The `ahg-provenance` package documented here covers manual capture and display only.

---

## Known issues

- **Events are replaced on save.** Saving the edit form deletes all existing events for the record and re-creates them from the submitted rows. Any event not present on the page at save time is lost.
- **Single record per description.** Each archival description supports exactly one provenance record; there is no support for multiple parallel records on one item.
- **Timeline detail is limited.** The timeline view derives its entries from event type and date; agent and rich description columns are not populated in this view.
- **No public-facing browse.** All routes require login, so the provenance browse and views are staff tools rather than a public discovery surface.

---

## References

- Source: packages/ahg-provenance/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/611
