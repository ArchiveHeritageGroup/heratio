# DOCiD / Africa PID Alliance - sovereign-PID decision (#1388 Phase 0.2)

Date: 2026-07-16
Status: Decision spike (no code). Deliverable for #1388 Phase 0.2.
Decision: **GO (conditional)** - adopt DOCiD as Heratio's sovereign-PID for African concepts, pending three confirmations (below).

## What DOCiD is

DOCiD (Digital Object Container Identifier), by the **Africa PID Alliance** (APA) / TCC Africa, is an Africa-originated persistent identifier for research outputs, **indigenous knowledge, and cultural heritage**. Actively deployed across African institutions (Q1-2026 report: "shift from advocacy to active technical deployment"). UNESCO Open-Science-listed.

- **Model:** hybrid - local handles (prefix `20.`) + DOIs (prefix `10.`, via DOI Foundation / Crossref), resolved through a **CORDRA-based federated architecture**. A "container" aggregates multiple related outputs (HyperGraph data model).
- **Open source + self-hostable:** `github.com/Africa-PID-Alliance/DOCiD` - JS frontend (:3001) + Python backend (:5002), PostgreSQL + Redis, Docker Compose.
- **REST API exists:** endpoint groups for authentication, object management/publication, comments, and external-service integration.
- **Metadata schema v1.0** (`docid.africapidalliance.org/docs`): 8 required + 14 optional properties, and - critically - **native Traditional Knowledge (TK) + Biocultural (BC) labels, Local Contexts protocols, multilingual African-language metadata, cultural creator roles, and Indigenous titles**, plus 10+ cross-identifier types (DOI, Handle, CSTR, DOCiD).

## Why GO (fit against the #1388 principles)

| #1388 principle | DOCiD fit |
|---|---|
| 1. Peer vocabularies with **sovereign identifiers** | Purpose-built African-owned PID; the anti-subordination identifier the model needs |
| 2. Equally-multilingual, oral-first | Native multilingual African metadata; multi-identifier container can hold audio surrogates |
| 3. Protocol-bearing terms | Schema **natively carries TK/BC labels + Local Contexts protocols** - not a bolt-on |
| 4. Community governance + **data sovereignty** | Open-source + **self-hostable** (Docker) = mirror locally, survive withdrawal of any external service |
| 6. Pluggable per-region | Federated CORDRA model + container aggregation aligns with per-region modules |

No other PID (DOI/Handle/ARK) models TK/BC protocol + African sovereignty natively. DOCiD is the correct choice.

## Integration path for Heratio

Mirror the existing `VocabularyResolverService` + mirror-command pattern:

- New `SovereignPidService` (`ahg-core`) - `mint($termProtocol): string` and `resolve($pid): array`, calling the DOCiD REST API. Store the returned PID on `term_protocol.pid` (already in the Phase-1 data model).
- **Sovereignty option:** self-host a DOCiD instance (Docker) as a gateway-internal upstream (like other AHG nodes) so Heratio never depends on an external service being up, and IK metadata is not shipped to a third party without protocol clearance. Route through `ai.theahg.co.za`-style internal service, not a direct public call.
- Map Heratio `term_protocol` (label_family/label_code/access_condition/owner) onto DOCiD's TK/BC + Local-Contexts fields (1:1 or close - the schemas were designed for the same model).
- Provenance substrate already in place: C2PA/fixity/PREMIS underwrites the minted record's authenticity.

## Confirm before Phase 2/4 (the three open items)

1. **Exact API spec** - endpoint URLs, auth (API key / OAuth / token), request/response JSON. Not in the overview docs; get from the repo backend routes (`:5002`) or APA directly.
2. **Membership / prefix registration** - what's required to obtain a `20.` handle prefix / mint rights, and any cost. No pricing surfaced; APA is a membership alliance - engage them.
3. **Production readiness / SLA** vs self-host - decide hosted-APA vs self-hosted DOCiD (recommend **self-host** for sovereignty + no external dependency).

## ACTION: contact the Africa PID Alliance (owner: Johan)

Open a conversation with APA now - it's the long-pole dependency for Phase 2/4 and can run fully in parallel with the code work. Contact via `africapidalliance.org` (contact form / partnership enquiry; they actively partner with heritage bodies - e.g. African Digital Heritage). Introduce Heratio (open-source Laravel archival platform, African GLAM focus, existing `ahg-icip` Local Contexts + TK/BC support) and ask specifically:

1. **Membership / onboarding** - how does an institution join APA and obtain mint rights + a `20.` handle prefix? Any cost / membership tier?
2. **API access** - REST endpoint base URL(s), authentication (API key / OAuth / token), and how to get sandbox/test credentials + the API reference.
3. **Self-hosting** - is running our own DOCiD instance (their Docker stack) a supported/sanctioned deployment for data-sovereignty, and how does a self-hosted node federate/resolve against the APA CORDRA network?
4. **TK/BC + Local Contexts mapping** - confirm the DOCiD schema fields our `term_protocol` (label_family/label_code/access_condition/owner) maps onto, and whether protocol enforcement travels with the resolved record.
5. **Partnership angle** - Heratio as an integration/reference implementation for GLAM/archives (they're courting exactly this sector).

Capture their answers back into this doc; they resolve the "three open items" above and unblock Phase 2/4.

## Recommendation

Proceed to **Phase 0.3** (one-term end-to-end prototype) using a **provisional/local PID** so the term-protocol + enforcement work is not blocked on APA onboarding; wire the real DOCiD mint in Phase 2/4 once the APA conversation confirms the three open items.

Sources: africapidalliance.org; docid.africapidalliance.org/docs; github.com/Africa-PID-Alliance/DOCiD; UNESCO Open Science (DOCiD); TCC Africa DOCiD papers.

## Outreach - Africa PID Alliance contact

**2026-07-17:** Outreach email prepared and sent to APA via the africapidalliance.org contact / partnership form. Status: **awaiting reply.** Follow-up reminder set for **2026-07-31** (workbench bell). This conversation is the long-pole dependency that resolves the three open items above and unblocks #1388 Phase 2/4.

### Email sent
Subject: *Heratio (The AHG) - DOCiD sovereign-PID integration for TK/BC cultural heritage: membership, API access, self-hosting*

> Dear Africa PID Alliance team,
>
> I am writing on behalf of **The Archive and Heritage Digital Commons Group (Pty) Ltd (The AHG)** about integrating **DOCiD** as the sovereign persistent identifier in our archival platform, **Heratio**.
>
> Heratio is an open-source (AGPL, Laravel) archival and heritage management platform built for the African and wider international GLAM sector. We have implemented native support for **Traditional Knowledge (TK) and Biocultural (BC) labels and Local Contexts protocols** - communities self-identify, protocols are enforced at every access and export point, and our data model already reserves a persistent-identifier field on each protocol record. DOCiD is, to our assessment, the only PID that models African data sovereignty and TK/BC protocols natively rather than as a bolt-on, so we would like to adopt it and, ideally, become a reference integration for the archives and heritage community.
>
> Before we wire DOCiD minting into Heratio, we would be grateful for your guidance on five points:
>
> 1. **Membership and onboarding** - How does an institution join the Alliance and obtain minting rights and a `20.` handle prefix? Is there a membership tier structure or cost we should plan for?
> 2. **REST API access** - Could you share the API base URL(s), the authentication model (API key / OAuth / token), the API reference, and how we obtain **sandbox / test credentials** so we can prototype against a non-production endpoint?
> 3. **Self-hosting and federation** - For data-sovereignty reasons we would prefer to run our own DOCiD instance (your Docker stack) rather than depend on an external service. Is self-hosting a supported and sanctioned deployment, and how does a self-hosted node **federate and resolve** against the APA CORDRA network?
> 4. **TK/BC and Local Contexts schema mapping** - We would like to confirm the DOCiD schema fields onto which our protocol model maps - specifically our `label_family`, `label_code`, `access_condition`, and owning-community fields - and, importantly, whether **protocol/access conditions travel with the resolved record**.
> 5. **Partnership** - We would welcome positioning Heratio as an open-source integration / reference implementation for GLAM and archives, and are happy to collaborate, test, and provide feedback on the schema and API.
>
> Heratio is at https://github.com/ArchiveHeritageGroup/heratio if it is useful for context. I would be glad to arrange a call at your convenience.
>
> With thanks and appreciation for the Alliance's work,
> Johan Pieterse - The Archive and Heritage Digital Commons Group (Pty) Ltd (The AHG) - johan@theahg.co.za

### APA answers
_(capture here when APA replies - each answer resolves an open item: Q1->#2 membership/prefix, Q2->#1 API spec, Q3->#3 self-host vs hosted, Q4->TK/BC mapping. Then move #1388 Phase 2/4 off blocked.)_
