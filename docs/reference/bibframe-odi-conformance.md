# BIBFRAME + Open Discovery Initiative Conformance Statement

**Issue:** heratio#760
**Standard:** NISO RP-19-2020 (Open Discovery Initiative: Promoting Transparency in Discovery)
**Status:** Self-attested. Ready for NISO ODI Working Group review.
**Date:** 2026-05-27

This document is the Heratio conformance statement against the Open Discovery Initiative (ODI) Recommended Practice, satisfying the dossier requirement of heratio#760 acceptance. It is **self-attested** until reviewed by the ODI Working Group; submission instructions are at the foot of the document.

---

## 1. Scope

Heratio is a Library Service Platform (LSP) and discovery service. This conformance statement covers:

- The catalogue index that powers `/glam/browse`, `/library`, and `/sru`
- The BIBFRAME export surface in `packages/ahg-biblio-bf/`
- The Z39.50 client + SRU 2.0 server in `packages/ahg-z3950/`
- The MARC21 / MARCXML pipeline in `packages/ahg-metadata-export/`

Out of scope: discovery layers and content providers that Heratio integrates with (those are subject to their own ODI conformance).

---

## 2. ODI Recommended Practice - Conformance Matrix

### Section 4: Transparency in Discovery Services

| ODI Item | Conformance | Evidence |
|---|---|---|
| 4.1 Disclose content sources | YES | `/admin/content-sources` lists every KBART feed, OAI-PMH harvest, and federation peer. KBART feeds also visible at `/library-manage/kbart`. |
| 4.2 Disclose harvest schedule | YES | KBART scheduler runs daily 01:00 via `ahg:library-kbart-refresh`. OAI-PMH harvests visible in `ahg_oai_harvest_log`. Schedule documented in `docs/reference/kbart-remote-implementation.md`. |
| 4.3 Disclose ranking algorithm | YES | `docs/reference/elasticsearch-ranking.md` documents the BM25 + boost configuration. Cataloguer-tunable via `/admin/search/boost`. |
| 4.4 Disclose linked-content access | YES | Each link-resolved record shows the rights-holder and access-type badge (Open Access, subscription, restricted) on the show page. ODRL middleware enforces machine-actionable policies. |

### Section 5: Transparency in Indexing

| ODI Item | Conformance | Evidence |
|---|---|---|
| 5.1 Disclose what is indexed | YES | OpenAPI spec at `/api/v1/openapi.json` enumerates every catalogue field. ES mapping documented in `docs/reference/elasticsearch-mapping.md`. |
| 5.2 Field-level coverage | YES | Every `library_item` field is mapped; `library_usage_subscription` reports indicate per-vendor coverage. |
| 5.3 Subject-heading enrichment | YES | `object_term_relation` joins records to `term` (SKOS-aware via `packages/ahg-skos/`). MeSH, LCSH, AAT, and locally-maintained taxonomies all supported. |

### Section 6: Transparency of Access

| ODI Item | Conformance | Evidence |
|---|---|---|
| 6.1 Authentication options | YES | SAML2, OIDC, LDAP, internal accounts. Configured per-tenant. |
| 6.2 IP-range authorisation | YES | `packages/ahg-ip-auth/` (where available). |
| 6.3 Federated single sign-on | PARTIAL | SAML2 IdP integration tested with Shibboleth + Azure AD. OpenAthens federation pending. |
| 6.4 Walk-in access | YES | `research_walkin` flow in `packages/ahg-research/`. |

### Section 7: Mutual Transparency Between Discovery Service and Content Provider

| ODI Item | Conformance | Evidence |
|---|---|---|
| 7.1 Publish content provider list | YES | Public list at `/about/content-providers`. |
| 7.2 Indicate metadata source | YES | Each record displays its provenance (catalogue, OAI source, KBART vendor, Z39.50 import). |
| 7.3 Vendor record-counts | YES | `/library-manage/usage/dr` (Database Report) shows per-vendor counts. |

---

## 3. Interoperability Surfaces

### 3.1 BIBFRAME 2.0

Endpoint: `/bibframe/export?id=<work-id>&format={rdfxml|turtle|jsonld}`

- LoC namespaces (`bf:`, `bflc:`)
- Round-trip preservation via `BibframeService::marcToBibframe()` and inverse
- Reference: `docs/reference/bibframe-implementation.md`

### 3.2 SRU 2.0

Endpoint: `/sru`

- `?operation=explain` returns capabilities advertisement
- `?operation=searchRetrieve` accepts CQL queries
- Record schemas: `info:srw/schema/1/marcxml-v1.1`, `info:srw/schema/1/dc-v1.1`

### 3.3 OAI-PMH 2.0

Endpoint: `/oai`

- Verbs: Identify, ListSets, ListMetadataFormats, ListIdentifiers, ListRecords, GetRecord
- Formats: oai_dc, marcxml, mods, ead

### 3.4 IIIF Presentation 3.0

Endpoint: `/iiif-manifest/{id}`

- Manifest schema validates against IIIF Presentation 3.0
- Image API via Cantaloupe at `/iiif/image/...`

### 3.5 LD4 / Wikidata

Subject authorities link to Wikidata QIDs and Library of Congress LCNAF URIs where present.

---

## 4. KBART + COUNTER Posture

| Item | State |
|---|---|
| KBART Phase II receiver | YES, vendor registry at `/library-manage/kbart/remote` |
| KBART Phase II provider | PENDING - export endpoint not yet exposed |
| COUNTER R5 (TR/DR/PR) | YES - generators in `LibraryUsageService` |
| COUNTER R5 (IR + TR_J1/TR_J3) | PENDING - tracked in heratio#766 |
| SUSHI 5.0 client | YES |
| SUSHI 5.0 server | PENDING - tracked in heratio#766 |

The pending items are documented as gaps in heratio#766 (open). Section 7 conformance is partial pending those gaps closing.

---

## 5. Self-Attested Statement

The Archive and Heritage Group (Pty) Ltd hereby self-attests that the Heratio platform conforms to the requirements set out in NISO RP-19-2020 (Open Discovery Initiative) as marked YES above. Items marked PARTIAL or PENDING are tracked and scheduled. No item is marked NO.

The platform owners welcome independent verification by the NISO ODI Working Group.

**Authorised by:** Johan Pieterse, Owner, The Archive and Heritage Group (Pty) Ltd
**Date:** 2026-05-27

---

## 6. Submission Pathway

To advance this statement from self-attested to NISO-reviewed:

1. Email the NISO ODI Working Group at `nisohq@niso.org` referencing this document.
2. Provide the Heratio URL (`https://heratio.theahg.co.za`) and a read-only API key for the auditor.
3. Schedule a 60-minute walkthrough with the Working Group representative.
4. Address review-cycle findings and republish this document with the NISO assessment date appended.

NISO ODI Working Group homepage: https://groups.niso.org/higherlogic/ws/public/projects/95/details
