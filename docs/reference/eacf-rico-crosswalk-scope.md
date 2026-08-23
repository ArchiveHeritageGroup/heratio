# EAC-F to RiC-O crosswalk - scope

**Summary.** A bidirectional crosswalk between EAC-F (Encoded Archival Context - Functions, released by TS-EAS on 31 July 2026) and RiC-O, plus how ahg-ric (Heratio) / OpenRiC import and export EAC-F, validated with the TS-EAS XML validator. EAC-F is the first XML serialization of ISDF (describing functions); RiC-O already models activities/functions as first-class context, so EAC-F is a clean ingestion source into a RiC-O graph. The Who/Why/What of the EAS suite (EAC-CPF / EAC-F / EAD) maps onto the RiC provenance chain: Agent performed Activity, Activity resulted-in Record.

> Names to confirm: exact EAC-F element/attribute names and relation vocabulary against the released **EAC-F Tag Library**; exact RiC-O IRIs against **RiC-O v1.0** (or the OpenRiC profile). The tables below map at the ISDF / EAC-CPF-parallel level and are a skeleton to refine, not final IRIs.

## A. Element mapping: EAC-F to RiC-O

| EAC-F construct (confirm) | ISDF concept | RiC-O target (confirm IRI) | Notes |
|---|---|---|---|
| control (recordId, maintenance, sources) | description control | provenance metadata on the Activity node (or a rico:Record describing the description) | the EAC-F instance *describes* an Activity; keep control data as provenance |
| the described function entity | Function / Activity | `rico:Activity`, typed via `rico:ActivityType` | RiC folded the ISDF "function" into Activity + Type; confirm whether OpenRiC profile keeps a distinct `rico:Function` |
| authorized / parallel / other names | Name | `rico:Name` + `rdfs:label` via `rico:hasOrHadName` | |
| function type / classification | Type / classification | `rico:ActivityType` via `rico:hasActivityType` | |
| existence dates | Dates | `rico:Date` + begin/end date properties | |
| description / abstract / history | Description, History | `rico:descriptiveNote` / `rico:history` | |
| associated place | (place) | `rico:Place` via `rico:hasOrHadLocation` | |
| mandate / legal basis (if present) | (mandate) | `rico:Mandate` / `rico:Rule` via `rico:isOrWasRegulatedBy` | |

## B. EAS cross-references to RiC relations

EAC-F relation elements (function-to-function, function-to-corporateBody, function-to-resource - by analogy with EAC-CPF's relations) become RiC relations:

| EAC-F relation (confirm) | Meaning | RiC-O relation (confirm IRI) |
|---|---|---|
| function-to-function, hierarchical | sub / super activity | `rico:hasOrHadSubactivity` / `isOrWasSubactivityOf` (or part-of) |
| function-to-function, temporal | predecessor / successor | precedes / follows (earlier-than / later-than) |
| function-to-function, associative | related function | `rico:isAssociatedWith...` |
| function-to-corporateBody (EAC-CPF) | who performs the function | `rico:isOrWasPerformedBy` / `performsOrPerformed` (Agent) |
| function-to-resource (EAD) | records resulting from the function | `rico:resultsOrResultedIn` / `isOrWasResultOf` (Record / RecordResource) |

Chain: `rico:Agent` -performed-> `rico:Activity` -resultedIn-> `rico:Record`.

## C. Import pipeline (EAC-F XML to RiC-O graph)

1. Ingest EAC-F XML (single file / batch / OAI).
2. **Validate against the TS-EAS EAC-F validator** (GitHub) *before* mapping; reject and report invalid input.
3. Parse control + function + relations.
4. Mint / resolve IRIs: reconcile to existing `rico:Agent` (from EAC-CPF) and `rico:Record` (from EAD) by identifier; mint a stable IRI for the Activity.
5. Map to RiC-O triples per tables A and B.
6. Load into the OpenRiC graph store; ahg-ric surfaces the Activity as a RiC entity in Heratio, linked to actors and records.
7. Record EAC-F source + control metadata as provenance on the node.

## D. Export pipeline (RiC-O to EAC-F XML)

1. Select `rico:Activity` nodes and their relations.
2. Serialize to EAC-F XML (reverse mapping).
3. **Validate the output with the TS-EAS validator.**
4. Emit; support a round-trip (import -> graph -> export) parity test.

## E. Integration points

- **ahg-ric (Heratio):** EAC-F import/export service + controller; RiC entity-management UI for Activity/Function; link to actors/records.
- **OpenRiC (openric/service):** the RiC-O store and the mapping profile/config; the crosswalk definition lives here.
- **Shared:** identifier reconciliation (agents/records) and an IRI-minting / PID policy for functions.

## F. Validation and round-trip

- Use the TS-EAS GitHub validator as the conformance gate on both import (input) and export (output).
- Golden test: import a sample EAC-F set, export it back, diff for semantic round-trip parity.

## G. Open questions / to confirm

- Exact EAC-F element/attribute names + relation vocabulary (EAC-F Tag Library).
- Exact RiC-O IRIs; whether "Function" is `rico:Activity` + `rico:ActivityType` or a distinct class in the OpenRiC profile.
- IRI-minting / PID strategy for functions; reconciliation keys for agents and records.
- Scope of a contribution back to the community Best Practices Guide (BPG).

## H. Phasing

1. **MVP:** read-only EAC-F import -> Activities linked to existing Agents/Records; validate on input.
2. Export + round-trip parity.
3. Entity-management UI in ahg-ric; reconciliation tooling.
4. Contribute the RiC / graph perspective to the community BPG (ties to the TC46/SC11 standards lever).

Related: [[project_eacf_ric_crosswalk]], [[project_openric_spec]], [[project_ric_roadmap]].
