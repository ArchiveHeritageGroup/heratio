> Heratio Help Center article. Category: Collection Mgmt / Provenance.

# Preservation Timeline User Guide

## Overview

The **Preservation Timeline** is a public, per-record page that shows, honestly and read-only, the recorded digital-preservation lifecycle of a published archival record's digital objects. For one record it lists, in chronological order, each recorded preservation step - **ingest**, **fixity checks**, **format identification**, **migrations or normalisations**, and **virus scans** - together with the step's recorded **outcome**, **when** it ran, and the **agent or tool** responsible for it.

It follows the **PREMIS** discipline for preservation metadata: every step in a digital object's life is recorded with its outcome, its timing, and the responsible agent. Where the **Authenticity Report** (`/authenticity/{idOrSlug}`) consolidates C2PA signing and provenance signals, and the **AI Inference Provenance Explorer** (`/inference-provenance/{idOrSlug}`) shows what AI contributed to the metadata, the Preservation Timeline answers a third, distinct question: "what has actually happened to the bits of this record over time?" Open it at **/preservation-timeline/{idOrSlug}** - for example `https://your-site.example/preservation-timeline/1234` or `https://your-site.example/preservation-timeline/fonds/series/item`.

---

## What it does

The page reads the preservation record that Heratio keeps for a record's digital objects and merges every recorded step into one chronological timeline. For each event it shows:

- **Step and lifecycle stage** - what happened (an ingest, a fixity check, a format identification, a migration or normalisation, a virus scan) and which lifecycle stage it belongs to.
- **Outcome** - the recorded result of the step: **success**, **warning**, or **failure**. (Where a source did not record an outcome, the event is simply marked "recorded".)
- **When** - the timestamp of the step.
- **Responsible agent or tool** - the preservation process, tool, or engine that carried out the step (for example the ingest process, a checksum algorithm, a format-identification tool, or a virus-scan engine).
- **Source** - which preservation log the event came from (the preservation event log, the fixity-check log, the format-identification log, the migration log, or the virus-scan log).

Above the list, at-a-glance counts show the total events, how many distinct lifecycle stages are present, and how many recorded successes and failures there are. A by-stage breakdown groups the events by lifecycle stage.

A machine-readable companion is available at **/preservation-timeline/{idOrSlug}.json**.

---

## Honest framing

The Preservation Timeline is a read-only view of what was **recorded**. It is the recorded preservation history; it is **not** a verdict on whether the source itself is authentic, complete, or true. When no preservation events are on file for a record, the page says so plainly - "No preservation events recorded yet" - rather than inferring or inventing a history. Absence of an event is shown as absence.

---

## How to use it

1. **Open the timeline:** go to **/preservation-timeline/{idOrSlug}**, replacing `{idOrSlug}` with the record's numeric id or its slug. Only published records have a page; an unknown or unpublished reference returns a "not found" page.
2. **Read the summary** at the top for a one-line, plain-language statement of how many preservation events are recorded and across how many lifecycle stages.
3. **Scan the timeline**, oldest first, to follow the lifecycle from ingest forward. Each event shows its outcome as a coloured badge (green success, amber warning, red failure).
4. **Follow the trust links** at the foot of the page to the **Authenticity Report** and the **AI Inference Provenance Explorer** for the same record, to assemble the full trust picture.
5. **Fetch the JSON** companion at `/preservation-timeline/{idOrSlug}.json` for a machine-readable copy.

---

## Frequently asked questions

**Why does a published record show no preservation events?**
Its digital objects may pre-date preservation logging, or no preservation step has been recorded for them yet. That does not mean anything is wrong - only that no automated preservation step is on file.

**Is this the same as the Authenticity Report?**
No. The Authenticity Report consolidates C2PA content-credentials / signing and the whole-record provenance verdict. The Preservation Timeline is the PREMIS-style lifecycle of the bits: ingest, fixity, format identification, migration, virus scan. The two are complementary and link to each other.

**Does opening the page change anything?**
No. The page is strictly read-only. It runs no preservation action, re-verifies nothing, and writes nothing.
