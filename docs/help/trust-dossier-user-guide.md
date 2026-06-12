> Heratio Help Center article. Category: Collection Mgmt / Provenance.

# Trust Dossier User Guide

## Overview

The **Trust Dossier** is a public, per-record page that brings the three separate per-record trust reports together onto **one** page, with one honest top-line summary of **what can and cannot be verified** about the record. It is the one-stop "defence dossier" for a published archival record: everything this system can show about the record's trustworthiness, in one place, that you can print or save as a PDF.

It consolidates, read-only, the three existing per-record surfaces:

1. **Authenticity and content credentials** - from the **Authenticity Report** (`/authenticity/{idOrSlug}`): the C2PA content credentials / signing layer and the whole-record provenance verdict.
2. **AI inference provenance** - from the **AI Inference Provenance Explorer** (`/inference-provenance/{idOrSlug}`): which AI inferences contributed to the record's metadata, with which model and gateway, and whether a human curator stayed accountable.
3. **Preservation lifecycle** - from the **Preservation Timeline** (`/preservation-timeline/{idOrSlug}`): the recorded PREMIS preservation lifecycle of the record's digital objects (ingest, fixity checks, format identification, migrations or normalisations, virus scans).

Open it at **/trust-dossier/{idOrSlug}** - for example `https://your-site.example/trust-dossier/1234` or `https://your-site.example/trust-dossier/fonds/series/item`.

---

## What it does

The dossier resolves one published record and assembles the three reports into a single page:

- **Record identity** - title, reference code, and permalink.
- **Honest top-line** - an overall confidence badge (drawn from the authenticity layer's live verdict, never assumed) and a plain-language statement of what can and cannot be verified about the record. The statement is built only from what each underlying report actually found; it never claims more than the evidence supports.
- **Section 1 - Authenticity and content credentials** - the content-credentials state (how many signed credentials verify live), the provenance verdict, and whether AI processing is recorded, with links to the full authenticity report and the provenance trace.
- **Section 2 - AI inference provenance** - at-a-glance counts (total inferences, models used, human-reviewed, awaiting review) and a by-service breakdown, with a link to the full inference explorer.
- **Section 3 - Preservation lifecycle** - at-a-glance counts (total events, lifecycle stages, recorded successes and failures) and a by-stage breakdown, with a link to the full preservation timeline.

Each section links out to its full report for the underlying detail, and each full report links back to the dossier.

A machine-readable companion is available at **/trust-dossier/{idOrSlug}.json**, with the same consolidated structure (record identity, headline, can/cannot-verify lists, and the three sections). It is CORS-open so any page can fetch it.

---

## Honest framing

The dossier is a read-only view of what was **recorded** and can be **re-checked**. It is not a verdict on whether the source itself is authentic, complete, or true. The overall confidence badge reflects the live cryptographic verification of content credentials, not an opinion about the record. Where a layer has nothing on file, the dossier shows that layer's dignified empty state ("No authenticity signals recorded yet", "No AI inference recorded for this record", "No preservation events recorded yet") rather than inferring or inventing a history. Absence is shown as absence.

If a sub-layer is unavailable on a given install (for example, no AI inference store is configured), that one section degrades to a clear "not available" note and the rest of the dossier still renders.

---

## How to use it

1. **Open the dossier:** go to **/trust-dossier/{idOrSlug}**, replacing `{idOrSlug}` with the record's numeric id or its slug. Only published records have a page; an unknown or unpublished reference returns a "not found" page.
2. **Read the top-line** for the overall confidence badge and the honest "what we can and cannot verify" statement.
3. **Work down the three sections** for a summary of each trust layer, and follow any **Open full report** link for the complete detail.
4. **Print or save as PDF** with the button at the foot of the page (or your browser's print dialog). The action buttons and section links are hidden from the printed copy, leaving a clean evidence dossier.
5. **Fetch the JSON** at **/trust-dossier/{idOrSlug}.json** for the machine-readable companion.

---

## Where it fits

The Trust Dossier sits above the three per-record reports as their unified front door. Use the dossier when you want the whole trust picture for a record on one page or as a single exportable artifact; use an individual report when you need the full detail of one layer.

These epics remain open - the dossier is an additive consolidation slice, not a replacement for the individual reports.
