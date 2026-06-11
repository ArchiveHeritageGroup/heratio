> Heratio Help Center article. Category: Collection Mgmt / Provenance.

# AI Inference Provenance Explorer User Guide

## Overview

The **AI Inference Provenance Explorer** is a public, per-record page that shows, honestly and read-only, which automated AI steps contributed to a published archival record's metadata. For one record it lists each recorded inference - a description, a named-entity extraction, a handwritten-text recognition pass, a machine translation, a condition scan, and so on - together with the **model** that produced it, the **gateway** it ran through, **when** it ran, who triggered it, and whether a **human curator** kept, corrected, or rejected the result.

Where the **Authenticity Report** (`/authenticity/{idOrSlug}`) answers "can I trust this source?" by consolidating signing and provenance signals, the Inference Provenance Explorer answers a narrower question: "what did AI contribute here, and did a person stay accountable for it?" Open it at **/inference-provenance/{idOrSlug}** - for example `https://your-site.example/inference-provenance/1234` or `https://your-site.example/inference-provenance/fonds/series/item`.

---

## What it does

The page reads the dedicated AI-inference provenance record that Heratio keeps whenever an AI service writes to a record's metadata. For each recorded inference it shows:

- **AI service and model** - which service ran (named-entity recognition, machine translation, language-model description, condition assessment, ...) and the model name and version.
- **Gateway** - where the inference ran. Calls that go through the AHG AI gateway are labelled as such; only the host is shown, never an internal URL.
- **When and who** - the timestamp, and the user (or "automated / batch process") that triggered it.
- **Human accountability** - whether a curator reviewed the output, and the outcome: **kept**, **corrected**, **rejected**, or **AI-suggested, not yet reviewed**. Any human correction or rejection is recorded alongside the original AI output.

Above the list, at-a-glance counts show the total inferences, how many distinct models were used, how many were human-reviewed, and how many are still awaiting review. A by-service breakdown groups the inferences by AI service.

A machine-readable companion is available at **/inference-provenance/{idOrSlug}.json**.

---

## How to use it

1. **Open the explorer:** go to **/inference-provenance/{idOrSlug}**, replacing `{idOrSlug}` with the record's numeric id or its slug. Only published records have a page; an unknown or unpublished reference returns a "not found" page.
2. **Read the headline:** one honest sentence states how many inferences are recorded and the human-accountability posture.
3. **Scan the counts:** total inferences, models used, human-reviewed, and awaiting review.
4. **Read the timeline:** each entry shows the service, model, gateway, timing, and the review outcome, newest first.
5. **Go deeper:** follow "See the full authenticity report" for the consolidated trust verdict, or "This page as JSON" for the machine-readable version.

---

## Good to know

- **Published only.** The explorer is a public surface and uses the same publication gate as the public catalogue browse, so it never reveals a draft or embargoed record. An unpublished record is indistinguishable from a missing one.
- **Honest by design.** The page never claims an AI output is correct - only that it was recorded, by which model and gateway, when, and whether a human reviewed it. A step that no one has reviewed is shown as "AI-suggested, not yet reviewed", never as "verified".
- **Nothing is exposed that should not be.** Only the model identity, gateway host, field touched, timing, and review outcome are shown. The raw AI input and output stay server-side.
- **A clean empty state.** A published record with no recorded inference shows a dignified "No AI inference recorded for this record" message - that is not an error, only an absence. Records described by hand, or described before inference logging began, simply have nothing on file.
- **Read-only.** The page records nothing, runs no AI, and re-verifies nothing. It is purely a window onto the inference provenance that already exists.
