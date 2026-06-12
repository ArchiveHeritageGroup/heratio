> Heratio Help Center article. Category: Public Trust.

# Transparency Report User Guide

## Overview

The Transparency Report is a public page at **/transparency** that gives any visitor an honest, catalogue-wide account of what an institution can - and cannot yet - attest about its published collection. It is the public, institution-wide counterpart to two existing surfaces: the operator-only **admin trust console** (which is for staff oversight) and the per-record **trust dossier** at **/trust-dossier/{record}** (which covers one record). The Transparency Report rolls the whole published catalogue up into five plain-language measures, each shown as a real number and an honest share.

It is completely read-only. It records nothing, runs no AI, performs no preservation action, and re-verifies nothing. It reuses the figures already computed elsewhere and presents them for the public.

---

## What it measures

Five dimensions, each a headline number plus the share of the published collection it covers:

1. **Content credentials** - how many published records carry a C2PA content credential, and how many master files are cryptographically signed. A content credential attests to a file's history (how it was captured and handled), not to the truth of what the source depicts.
2. **AI provenance** - how many published records have at least one logged AI step (a description, transcription, translation, or assessment), and what share of those steps a person has reviewed. A logged AI step is an open disclosure of involvement, never a claim that the AI was correct.
3. **Integrity** - how many published master files have a checksum (fixity) baseline on record, and of those, how many have no failed check. A fixity baseline guards against silent corruption; it does not certify a file's contents.
4. **Preservation** - how many published objects have at least one recorded PREMIS preservation event (ingest, format identification, migration, or a virus scan).
5. **Accessibility** - how many published images carry a genuine human-written text alternative (WCAG 1.1.1). Auto-generated captions are deliberately not counted here.

Every figure is scoped to **published records only**. Where a signal has not been captured, the report shows the gap rather than hiding it.

---

## How to use it

1. Open **/transparency** (for example `https://your-site.example/transparency`).
2. Read the two scope numbers at the top: how many published records and master files everything is measured against.
3. Read each of the five dimension cards: the big percentage is the share covered, with the raw count and total beside it, a progress bar, and a one-line honest framing of what the number does and does not mean.
4. Follow the **Explore further** links to drill down: **Trust at a glance** (/trust), **Verified records** (/verified-records), **Open data and APIs** (/open-data), and the **Open-data maturity** scorecard.
5. To check a single record, paste its permalink or reference into the box to open its full **trust dossier**.
6. For automation, fetch **/transparency.json** - the same figures as machine-readable JSON, served with open CORS so any page can read it.

---

## Honest framing

This report never overclaims. Authenticity, integrity, and preservation signals describe how a file was handled; they do not vouch for the truth of what a source depicts or claims. A gap means a signal has not been captured yet, not that anything is wrong. On a fresh installation with nothing recorded, the page shows a calm "nothing measured yet" state rather than failing.

---

## Where the numbers come from

- Content credentials and AI provenance reuse the same collection-wide figures as the **Trust at a glance** dashboard (/trust), so the two pages always agree.
- Integrity and preservation read the preservation fixity-check and PREMIS event logs.
- Accessibility reads the curated image alt-text store.

All of these are read with cheap aggregate counts only - the report never scans the whole catalogue row by row, and never writes to the database.
