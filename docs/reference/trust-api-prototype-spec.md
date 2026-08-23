# Trust API - proof-of-concept specification

| Field | Detail |
|---|---|
| Document | Trust API - proof-of-concept spec (the archive as an endpoint AI can cite) |
| For | The AHG partners (Johan / Alexio / Bandile) - decision + build brief |
| Prepared by | The Archive and Heritage Digital Commons Group (Pty) Ltd (The AHG) |
| Author | Dr Johan Pieterse |
| Version | 1.0 |
| Purpose | Move #2 of the trustworthy-memory strategy: the one demo that turns "the archive is becoming an endpoint AI must cite" into something you can click |

*The strategy (provenance is the new scarcity; trustworthy memory is infrastructure) rests on a claim reviewers, funders and ministers will not take on faith: that an archive can serve provenance as a machine-queryable, citable service. This proof-of-concept proves it with the least possible build. Scope is deliberately tiny - one collection, read-only, one JSON endpoint. Not a product; a proof. See [[ahg-strategic-angle-trustworthy-memory]] for the strategy and [[provenance-is-the-new-scarcity]] for the essay this makes real.*

## The endpoint

`GET /trust/v1/records/{reference}` returns a JSON evidence package (and a human-readable page at the same path with `Accept: text/html`). Read-only. Public records only. No auth or data-model changes.

## The evidence package (the six fields, mapped to what Heratio already holds)

| Field | What it returns | Heratio source |
|---|---|---|
| Identity | Persistent reference code, creating authority, responsible custodian | information_object (reference code), actor (creator), repository |
| Context | Fonds -> series -> file -> item, related records, functions, people, events | RiC/ISAD hierarchy (lft/rgt), relation, linked actors |
| Custody | Accession/transfer, preservation events, accountable agents | accession, PREMIS events, digital-object history |
| Integrity | Fixity (SHA-256) per digital object, timestamps, known transformations | digital-object checksums, preservation metadata |
| Authority & rights | Publication/access status, rights statement, community / TK-BC labels | status (publication), rights, object_term_relation (TK/BC) |
| Citation & status | Stable citation, verification date, version, known gaps, superseded/revoked flag | generated at response time |

## What makes it *trust*, not just an API

- **Standards under the hood:** PREMIS (custody and fixity), Records in Contexts (context and relationships), and - for digital objects - C2PA content credentials where present. Nothing proprietary.
- **The response is itself verifiable:** the evidence package carries a stable citation and a SHA-256 of its own content, and (stretch goal) is signed with JWS, so a consumer can prove the archive asserted it, on that date, unaltered. That signature is what lets an assertion later be marked *superseded* or *revoked*.
- **It asserts traceability, not truth.** The package reports what is documented and, crucially, the **gaps** - it never claims the record's contents are correct, only that this custodian vouches for this evidence as at this date. The "what provenance cannot prove" discipline from the essay is built into the schema.

## What "done" looks like (acceptance)

1. One chosen collection; the endpoint returns a valid evidence package for every record in it.
2. Restricted records are refused, not leaked; gaps are shown, not hidden.
3. A demo: an AI is asked a question about the collection (for example, "who authorised this, and what is the provenance?"), calls the endpoint, and answers with a citation and the evidence package attached - "according to this record-holder, verified on this date."

## Effort and guardrails

- Read-only, one collection, already-public records, roughly one to two weeks of build on top of Heratio's existing data.
- No new data model, no auth changes, no ingestion pipeline. Reuse the fixity, RiC context and publication status that already exist.
- Do not turn it into a product inside the PoC. The goal is a clickable proof for the partners and a first external conversation, not a launch.

## Why it matters

Once this exists, "provenance is the new scarcity / trustworthy memory is infrastructure" stops being a slogan. It becomes a URL an AI can cite, a demo a minister can see, and the concrete seed of the protocol (strategy move #3 - OpenRiC reframed as the open trust-and-provenance protocol for memory). Everything downstream gets easier because there is something real to point at: the OpenRiC positioning, the ISJ paper's empirical base, and the first funder or institutional conversations.

Related: [[ahg-strategic-angle-trustworthy-memory]], [[project_openric_spec]], [[provenance-is-the-new-scarcity]], [[preservation-in-ai-archives]], [[project_thought_leadership_blog]], [[isj-digital-sovereignty-paper-angle]].
