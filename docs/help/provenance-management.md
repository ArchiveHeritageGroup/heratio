> Heratio Help Center article. Category: Collection Mgmt / Provenance.

# Provenance Management

## Overview

Heratio provides comprehensive provenance tracking for archival records, museum objects, and library materials. The Provenance module records the complete chain of custody for each item, including ownership history, transfer events, and supporting documentation.

---

## Chain of Custody Visualization

The chain of custody is displayed as a visual timeline on the provenance view page. Each entry in the chain is represented with an owner type icon using Font Awesome:

| Owner Type | Icon | Description |
|------------|------|-------------|
| Person | `fa-user` | Individual owner or holder |
| Organization | `fa-building` | Corporate body, museum, gallery, or institution |
| Family | `fa-users` | Family group or estate |
| Government | `fa-landmark` | Government agency or department |
| Unknown | `fa-question-circle` | Unidentified previous owner |

The timeline is rendered using D3.js and supports interactive features such as zooming, hovering for details, and clicking entries to expand full event information.

---

## Ownership History Table

Below the timeline, a detailed ownership history table displays all recorded custody events:

| Column | Description |
|--------|-------------|
| Owner | Name of the person, organization, or family |
| Type | Owner type with Font Awesome icon |
| Location | Geographic location with optional TGN (Getty Thesaurus of Geographic Names) link |
| TGN Links | Clickable links to the Getty TGN authority for verified place names |
| Period | Date range of ownership (start date to end date) |
| Transfer Method | How the item changed hands (sale, donation, bequest, inheritance, etc.) |
| Certainty | Level of confidence in this ownership record (Certain, Probable, Possible, Uncertain, Unknown) |
| Gap Highlighting | Rows with gaps in the chain of custody are highlighted in amber to draw attention |

Gap highlighting is applied automatically when there is a period between two consecutive ownership entries that is not accounted for. This helps researchers identify periods requiring further investigation.

---

## Add, Edit, and Delete Entries

All provenance entries are managed through a modal form that appears when you click **Add Entry**, or click the edit icon on an existing row.

### Modal Form Fields

| Field | Type | Description |
|-------|------|-------------|
| Owner Name | Text (autocomplete) | Name of the owner, with suggestions from existing authority records |
| Owner Type | Dropdown | Person, Organization, Family, Government, Unknown |
| Location | Text (autocomplete) | Geographic location with TGN lookup |
| Start Date | Date picker | When ownership began |
| End Date | Date picker | When ownership ended |
| Transfer Method | Dropdown | Sale, Purchase, Auction, Gift, Donation, Bequest, Inheritance, By Descent, Transfer, Exchange, Loan, Confiscation, Restitution, Repatriation, Discovery, Excavation, Accessioning, Deaccessioning |
| Sale Price | Decimal | Price paid (if applicable) |
| Currency | Dropdown | Currency code (ZAR, USD, GBP, EUR, etc.) |
| Auction House | Text | Name of the auction house (if auction) |
| Lot Number | Text | Auction lot number |
| Catalogue Reference | Text | Reference to auction or sale catalogue |
| Sources | Textarea | Documentary sources supporting this entry |
| Notes | Textarea | Additional notes or commentary |
| Mark as Gap | Checkbox | Flag this period as an unresolved gap in the chain |
| Certainty | Dropdown | Certain, Probable, Possible, Uncertain, Unknown |

To delete an entry, click the delete icon on the row and confirm in the confirmation dialog.

---

## Timeline Visualization

The timeline view is built with D3.js and provides a graphical representation of the chain of custody over time:

- **Horizontal axis** represents time (years)
- **Nodes** represent ownership events, colour-coded by transfer method
- **Connecting lines** show the flow from one owner to the next
- **Gaps** are shown as dashed lines with amber shading
- **Hover tooltips** display owner name, dates, transfer method, and certainty
- **Click** a node to navigate to the full entry detail

Access the timeline at `/provenance/{slug}/timeline`.

---

## CSV Export

The full provenance chain can be exported as a CSV file for external analysis or reporting. Click the **Export CSV** button on the provenance view page.

The CSV includes all fields: owner name, type, location, TGN reference, start date, end date, transfer method, sale price, currency, auction details, sources, notes, certainty, and gap status.

---

## Nazi-Era Provenance

For museum objects, the provenance module includes a dedicated Nazi-era provenance check for the period 1933--1945. Objects with ownership gaps during this period are automatically flagged. The check result (Clear, Requires Investigation) is displayed prominently on the provenance view.

---

## Cultural Property Status

Track cultural property claims and disputes:

| Status | Description |
|--------|-------------|
| None | No cultural property issues |
| Claimed | Subject to ownership claim |
| Disputed | Active dispute in progress |
| Repatriated | Returned to community or country of origin |
| Cleared | Investigated and cleared |

---

## Integration

The Provenance module integrates with:

- **Donor Agreements** --- link provenance to formal donor agreements
- **Rights Management** --- connect provenance to rights and restrictions
- **Condition Reports** --- cross-reference provenance with condition assessments
- **Digital Objects** --- track provenance for digital assets

---

*Part of the Heratio AHG Framework*
