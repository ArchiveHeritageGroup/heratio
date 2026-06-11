> Heratio Help Center article. Category: AI & Automation.

# Research Leads User Guide

## Overview

Research Leads is the public face of Heratio's generative scholarship: it promotes the most compelling AI-found cross-collection connections - the same connections surfaced by the Discoveries feature - into browsable scholarly leads. Each lead pairs a connection with a plain-language "why this might matter" prompt and links straight to the records it rests on. Open it at **/research-leads**.

A research lead is a starting point for enquiry, not a finding. Every lead is grounded in real catalogue links and is reviewed by staff before it appears publicly.

---

## What it does

Research Leads turns the strongest discoveries into a curated, public feed:

- It draws on the persisted Discoveries set (the AI-found cross-collection connections), highest confidence first.
- A curator promotes the most compelling discoveries into leads, then publishes the best to the public feed.
- Each published lead shows the connection's centre record, a plain-language prompt of why it might be worth following, the verified records and entities it links, and a confidence indicator based on how much real catalogue evidence underpins it.
- Only leads a curator has published - and whose underlying record is itself published - appear publicly.

The aim is to help researchers and curators notice relationships across silos that a single keyword search would never reveal, and to frame each as a question worth pursuing.

---

## How to use it (public)

1. Go to **/research-leads**.
2. Browse the published leads. Each card shows the connection, why it might matter, and the verified links it rests on.
3. Open a lead (**View lead**) to read it in full, including every linked record and entity.
4. Click through to the underlying records to read them and judge the connection for yourself.

Every lead carries a clear notice that it is AI-generated and grounded in catalogue links: treat it as a hypothesis to verify before citing.

---

## Curating leads (administrators)

The curation screen is at **/admin/research-leads** (administrators only).

1. **Promote discoveries into leads.** Use the "Promote discoveries into leads" panel. Choose how many of the highest-confidence discoveries to promote. Optionally tick "Enrich the why it matters prompt with AI" to have the AHG gateway write a richer, still-grounded prompt for each lead. Promotion only ever runs when you click - never on a page load. Re-running refreshes existing leads in place and never overrides a publish or dismiss you have already made.
2. **Review the worklist.** Each lead shows its confidence, link count, and current status (Pending review, Published, or Dismissed). Filter by status with the chips.
3. **Publish** a lead to make it visible on the public feed. **Dismiss** a lead to keep it on record but hide it. Use the undo button to return a lead to pending.

You must publish a lead before the public sees it; promotion alone leaves it pending.

---

## Generating from the command line

Administrators can refresh leads on a schedule:

```
php artisan ahg:generate-research-leads --limit=25 [--enrich] [--dry-run]
```

- `--limit` caps how many discoveries to promote (highest confidence first).
- `--enrich` enriches each lead's "why it matters" prompt via the AHG gateway.
- `--dry-run` reports the counts without writing.

Promoted leads are created as **pending** - a curator still publishes the best from the curation screen.

---

## How it relates to Discoveries

Research Leads builds on Discoveries (**/discoveries**), it does not replace it. Discoveries is the broad surface of AI-found connections; Research Leads is the curated, published selection of the strongest ones, framed as questions to pursue. Run the discovery generator first, then promote the strongest discoveries into leads.

---

## Trust and verification

- Every lead is produced by an AI model that was given only the catalogue connections it shows. The model is instructed to cite entities by their exact catalogue names and never to invent people, places, dates or records.
- All AI runs through the AHG AI gateway; nothing is sent to an external service.
- AI can still misread or overstate a link. Verify every lead against the records it cites before relying on it.
