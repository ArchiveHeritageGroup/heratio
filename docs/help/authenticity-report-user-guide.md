> Heratio Help Center article. Category: Collection Mgmt / Provenance.

# Authenticity Report User Guide

## Overview

The **Authenticity Report** is a public, per-record page that gathers every authenticity signal Heratio already holds for one published archival record and presents them together, in plain language, with an honest statement of what can and cannot be verified. Where **/verify** checks a single object and **/verify/record/{id}/trace** lays out the full provenance timeline, the Authenticity Report sits above both as a single readable summary for anyone asking "can I trust this source?". Open it at **/authenticity/{idOrSlug}** - for example `https://your-site.example/authenticity/1234` or `https://your-site.example/authenticity/fonds/series/item`.

---

## What it does

The report consolidates three signals that already exist for a record into one verdict:

- **Content credentials / C2PA signing** - whether the record's digital files carry cryptographically signed content credentials, and whether those signatures re-verify live when the page loads.
- **Provenance verification** - the whole-record verdict computed from every digital file's provenance: verified, partially verified, recorded-but-unsigned, or failed.
- **AI processing** - whether any automated AI step is recorded in the record's signed or documented provenance, and how many.

From these it derives a single **confidence tier** - High, Partial, Low (recorded, unsigned), Failed, or None recorded - and two honest lists: **what we can verify** and **what we cannot verify**. The report never overclaims. Content credentials attest to a file's recorded history, not to whether what the source depicts is itself true, and the page says so plainly.

A machine-readable companion is available at **/authenticity/{idOrSlug}.json**, and an embeddable trust badge at **/authenticity/{idOrSlug}/badge**.

---

## How to use it

1. **Open the report:** go to **/authenticity/{idOrSlug}**, replacing `{idOrSlug}` with the record's numeric id or its slug. Only published records have a report; an unknown or unpublished reference returns a "not found" page.
2. **Read the headline:** the confidence tier and one-sentence summary tell you, honestly, how far the record's authenticity can be confirmed.
3. **Check the three signals:** the Content credentials, Provenance, and AI processing cards each show their own state, so you can see exactly which signals are present.
4. **Read what can and cannot be verified:** these two lists are the heart of the page. They never promise more than the live verification supports.
5. **Go deeper:** follow "See the full provenance trace" to **/verify/record/{id}/trace** for the complete timeline, or "Trace as JSON" for the machine-readable version.
6. **Embed the badge:** copy the badge snippet to display a live authenticity badge on another page that links readers back to the report.

---

## Good to know

- **Published only.** The report is a public surface and uses the same publication gate as the public catalogue browse, so it never reveals a draft or embargoed record. An unpublished record is indistinguishable from a missing one.
- **Live, never cached.** Signatures are Ed25519 and re-checked every time the page loads. A green "High confidence" verdict means the signatures verified right now.
- **Honest by design.** A record with no signals shows a dignified "no authenticity signals recorded yet" state - that is not an error, only an absence of recorded provenance. A failed signature is shown loudly, not hidden.
- **It does not attest to truth.** The report can confirm that a signed file is unaltered and how it was handled; it cannot confirm that what the source depicts or states is itself true, accurate, or complete.
- **Read-only.** The report writes nothing and runs no AI. It only consolidates the existing verify, provenance-trace, and content-credentials services.
- The badge is extensionless on purpose so it is served by the application (not as a static file) and always reflects the current verdict.
