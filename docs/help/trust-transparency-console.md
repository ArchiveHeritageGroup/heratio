> Heratio Help Center article. Category: Administration.

# Trust and Transparency Console

## Overview

The Trust and Transparency Console is a single operator page that gathers, in
one place, every surface that lets the institution and its visitors see how the
collection is cared for and accounted for. It is the institution's transparency
control panel.

These surfaces are real and live across the platform, but they had been
scattered and hard to find. The console does not re-implement any of them - it
is a hub that links to each one, and only shows a link when that feature is
installed on this site.

Open it at **/admin/trust-console** (administrators only).

## What you will find

The console groups the surfaces into four clear sections.

### Authenticity and provenance
- **Trust home** - the public front door to the institution's trust signals.
- **Verified records** - a public roll of records carrying content credentials.
- **Authenticity report (per record)** - a plain-language "what we can and
  cannot verify" report for one record.
- **AI inference provenance (per record)** - which AI inferences contributed to
  a record's metadata, with a human kept accountable.
- **Verify authenticity** and **Check content credentials of a file** - the
  public "is this real?" tools.
- **Authenticity coverage** - how much of the collection carries content
  credentials.

### Preservation
- **Preservation dashboard** - the operator hub for fixity, events, formats,
  virus scans and packages.
- **Fixity and integrity report** - checksum baseline coverage and the latest
  verification sweep.
- **Preservation maturity (NDSA levels)** - an evidence-based self-assessment.
- **Preservation timeline (per record)** - the lifecycle of one record's
  digital objects.

### Accessibility
- **Accessibility coverage report** - a heuristic coverage report citing WCAG
  2.1 AA.
- **Alt-text curation** - add human-authored alternative text to image
  surrogates.

### Open data and transparency
- **Open data home**, **protocol** and **maturity scorecard**.
- **DCAT data catalog** and the **linked-data / RDF dataset dump**.
- **Union catalogue**, the **OAI-PMH harvest endpoint**, and **public themes**.

## How to read the cards

- A green **Available** badge plus an **Open** button means the surface is
  installed and ready. The button opens it in a new tab.
- A grey **Not configured** badge means that feature is not installed on this
  site. The card still appears, so you know the capability exists, but there is
  no link to a page that is not there.
- Some cards show a small count badge (for example signed manifests, fixity
  checks logged, or alt texts curated) where that figure is cheap to compute.
- Per-record surfaces (authenticity report, inference provenance, preservation
  timeline) open a sample record so you can demonstrate them.

## Notes

- The console is completely read-only. It changes nothing and runs no AI.
- It never fails because a feature is missing: absent surfaces simply show as
  Not configured.
- Counts are best-effort and reflect the data currently in the system.
