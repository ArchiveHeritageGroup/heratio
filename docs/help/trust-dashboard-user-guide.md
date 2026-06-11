> Heratio Help Center article. Category: Collection Mgmt / Provenance.

# Trust Dashboard User Guide

## Overview

The **Trust Dashboard** is a public, collection-wide "trust at a glance" page. Where **/authenticity/{idOrSlug}** answers "can I trust THIS record?" and **/verify** is the content-credentials front door, the Trust Dashboard rolls the whole collection up into a handful of honest big numbers and simple bars. It answers, for everything published here: how much carries content credentials, how much is cryptographically signed and still verifies, and how much of the metadata involved AI with a human kept accountable. Open it at **/trust**.

---

## What it shows

The dashboard is split into two halves, both scoped to **published records only**.

**Content credentials**

- The percentage of master files that are cryptographically signed, and the raw count signed out of the total.
- How many published records carry content credentials, out of all published records.
- How many records are cryptographically signed.
- A single bar splitting master files into signed-and-verifiable, signed-but-failed, and not-yet-signed.
- Signing detail: how many signed content credentials have been issued, by how many distinct keys, and when the most recent signing happened.

**AI in our metadata**

- The percentage of records that involved an AI step.
- How many records carry a recorded AI inference, and how many inferences are on record in total.
- The share of those AI inferences that a person has reviewed, with a bar splitting reviewed from not-yet-reviewed.

A machine-readable companion is available at **/trust.json**.

---

## How to use it

1. **Open the dashboard:** go to **/trust**. No login is needed; it is a public trust summary.
2. **Read the headline numbers:** the big percentages tell you, at a glance, how much of the collection can be verified and how much involved AI.
3. **Read the bars:** the master-files bar shows the signed / failed / unsigned split; the AI bar shows reviewed vs not-yet-reviewed.
4. **Drill into one record:** use the "Check a specific record" box to jump to that record's full **/authenticity/{idOrSlug}** report.
5. **Fetch the JSON:** **/trust.json** returns the same figures for dashboards, monitoring, or reporting. It is read-only and CORS-open.

---

## Honest framing

The dashboard never overclaims. The standing caveat is shown on every state: **content credentials attest to a file's history - how it was captured and handled - not that the content itself is true, accurate, or complete.** A signed record is a verifiable record, not a guarantee of what the source depicts. An AI step shown as "reviewed" means a person accepted, corrected, or rejected it; a step "not yet reviewed" is a suggestion, never presented as verified.

---

## Good to know

- **Published only.** Every figure uses the same publication gate as the public catalogue browse, so drafts and embargoed records never count toward the totals. The synthetic catalogue root is excluded.
- **Cheap and live.** The page runs only lightweight aggregate counts - no per-record loops and no live signature crypto - so it stays fast even on large collections. Per-record verification happens on the **/authenticity** page, not here.
- **Honest empty state.** Before anything has been signed or recorded, the dashboard shows "authenticity signals are still being established" rather than empty zeros. That is not an error, only an absence of recorded signals.
- **Never errors out.** A missing layer or database hiccup degrades to the empty state; the page and the JSON both stay up.
- **International.** The dashboard is jurisdiction-neutral and uses the open C2PA content-credentials standard.
