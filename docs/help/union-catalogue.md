> Heratio Help Center article. Category: Federation.

# Union catalogue (federated GLAM network)

**Version:** 1.0
**Date:** 2026-06-11
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## 1. Overview

The union catalogue lets several institutions share their discovery
metadata into one cross-institutional search. A visitor searches once
at `/union-catalogue` and sees matching records from every participating
institution, each result linking back to the record at its source
catalogue.

Sharing is strictly opt-in and OFF by default. Nothing leaves your
institution until an administrator turns sharing on. Only published
records are shared, and you can require a minimum level of description as
well. The union index holds discovery metadata only - title, level,
dates, holding repository, and a permalink - never the full record.

This is a federation-discovery surface. It sits alongside the other
federation publish surfaces (OAI-PMH at `/oai`, Europeana EDM at
`/federation/europeana`).

---

## 2. Where to find it

- Public search: `/union-catalogue` (anonymous, full-text `?q=`).
- Machine surface: `/union-catalogue.json` (CORS-open JSON, same `?q=`).
- Admin: `Admin > Federation > Members` (or directly at
  `/federation/members`).

---

## 3. Setting up sharing (administrators)

1. Open `/federation/members`.
2. Add a member for **this institution** and tick **This institution**.
   This is the self-member that owns the records you publish. Tick
   **Include this member in union searches** so your own records show up
   in the cross-member results.
3. Add the other participating institutions as members. Tick
   **Include this member in union searches** for each one you want
   searchable.
4. In **Opt-in sharing**, turn on **Share this institution's records
   into the union catalogue**. Leave **Only share Published records**
   ticked unless you have a reason not to. Optionally set a minimum
   level-of-description term id.
5. Click **Publish now** (or let the daily 04:00 schedule run it). This
   runs `ahg:federation-publish`, which pushes your opt-in, published
   records into the union index. The run reports how many records were
   shared, how many were skipped, and why.

Turning sharing OFF stops future publishes. The publish command refuses
to share anything while sharing is OFF or while no self-member is
registered.

---

## 4. How records get into the index

The publish pass streams your published records in batches, applies the
opt-in gate (sharing on, published-only, optional minimum level), and
upserts each one into the union index keyed by member + record
reference. Re-running is safe and idempotent - it refreshes existing
rows rather than duplicating them.

---

## 5. Searching the union catalogue

Type a term and search. Results are grouped by institution and link to
the source record. The JSON surface returns the same results as a flat
record list for integrators. When no institutions have opted in yet, or
there are no matches, the page shows a clear empty-state message rather
than an error.

---

## 6. Privacy and scope

- Opt-in, OFF by default. No record is shared without an administrator
  enabling it.
- Published-only by default; draft and embargoed records are never
  shared.
- Discovery metadata only - the index never holds the full record body.
- Jurisdiction-neutral: the union catalogue makes no assumptions about
  any particular country's rules. Each institution decides what it
  shares.
