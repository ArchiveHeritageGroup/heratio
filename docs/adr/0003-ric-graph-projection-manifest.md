# ADR-0003: RiC graph projection manifest (single source→IRI contract)

- **Status:** Accepted (2026-06-19)
- **Owner:** Johan Pieterse (The Archive and Heritage Group)
- **Relates to:** #1319 (ontology governance), `docs/reference/ontology-governance-pin.md`, ADR-0001 (sidecar pattern)

## Context

The RiC layer projects relational data into a Fuseki RDF graph. Three pieces
each hard-coded their **own** mapping of "which entity comes from which table
under which IRI", and they drifted:

- `FusekiInstanceLoadCommand` (`ahg:ric:fuseki-load`) loads **actors → `rico:Agent`**
  (`urn:ahg:ric:agent:<actor.id>`) and **place-taxonomy terms → `rico:Place`**
  (`urn:ahg:ric:place:<term.id>`), into the **default graph**.
- `CrmGraphSyncService` (`ahg:crm-graph-sync`) loads **information_object → CIDOC-CRM**
  named graphs (`urn:ahg:crm:e22_*:<io.id>`).
- `FusekiIntegrityCheckCommand` validates against the **`ric_place` / `ric_rule`
  / `ric_activity` / `ric_instantiation` / `relation` tables**, expecting one
  **named graph per entity** `urn:ahg:ric:<type>:<table.id>`.

Result: the integrity check never matched anything (it looked for named graphs
that don't exist, against an id space - `ric_place.id` - the loader never used:
the loader mints places from `term.id`). Drift was structural, not detectable.

## Decision

Introduce **one declarative projection manifest** as the single definition of
the RiC graph. The loader, the CRM sync, the integrity check, and the export
all read the manifest, so they cannot diverge. One manifest entry per RiC type:

| RiC type | Authoritative source (source of truth) | id in IRI | internal IRI | graph |
|---|---|---|---|---|
| `rico:Agent` | `actor` (+ `repository`) | `actor.id` | `urn:ahg:ric:agent:<id>` | default |
| `rico:Place` | **`ric_place`** (canonical - see below) | `ric_place.id` | `urn:ahg:ric:place:<id>` | default |
| `rico:Rule` | `ric_rule` | `id` | `urn:ahg:ric:rule:<id>` | default |
| `rico:Activity` | `ric_activity` | `id` | `urn:ahg:ric:activity:<id>` | default |
| `rico:Instantiation` | `ric_instantiation` | `id` | `urn:ahg:ric:instantiation:<id>` | default |
| relations | `relation` | `id` | edges on the above | default |
| `rico:Record` (CRM crosswalk) | `information_object` | `io.id` | `urn:ahg:crm:e22_*:<id>` | named (per record) |

### Rules (inherit from the governance pin)
1. **One source per type.** Each RiC type maps to exactly one authoritative
   relational table + id column. No type is loaded from one table and validated
   against another.
2. **Two-layer IRIs.** Internal live-graph node IRIs are `urn:ahg:ric:<type>:<id>`
   (from the source id); public/export IRIs are `https://ric.theahg.co.za/ric/<type>/<slug>`
   (governance pin section 2). The semantic-layer (Qdrant) join is `object_id -> urn:ahg:ric:<type>:<id>`.
3. **Source of truth = relational.** The graph is a derived, regenerable
   projection (pin section 3); drop + rebuild any time.
4. **The integrity check is generated from the manifest** - per type it asks
   "does a subject `urn:ahg:ric:<type>:<id>` exist (default or named graph)?"
   against the same source ids the loader used. Loader and check share the map.

### Place: `ric_place` is canonical (not the term taxonomy)
Two populated candidates existed: ~181 place-taxonomy terms vs 193 `ric_place`
rows. **`ric_place` is the canonical `rico:Place` source.** It is the RiC-native
place table (the serialization layer already reads it - `serializePlace()`), so
choosing it aligns the live graph with the export. `fuseki-load` currently mints
places from `term.id` and must migrate to `ric_place.id` (see migration).

## Consequences

- The integrity check becomes meaningful: it validates exactly what the manifest
  declares. Agents (loaded from `actor.id`, matching the manifest) will match;
  places and the not-yet-projected types (rule/activity/instantiation/relation)
  will report as genuinely missing - an accurate drift signal, not a structural
  false negative.
- A follow-up loader migration is required so the live graph matches the
  manifest for every type (places from `ric_place`; project rule/activity/
  instantiation/relation, which are populated relationally but never loaded).

## Migration (incremental, #1319)
1. **Add the manifest + shared resolver** (`RicGraphManifest`).
2. **Rewire `FusekiIntegrityCheckCommand`** to drive off the manifest (this pass).
   The subject-vs-named-graph query bug is also fixed (match subjects in the
   default or any named graph).
3. **Migrate `fuseki-load` + `crm-graph-sync`** to the manifest: places from
   `ric_place`; project rule/activity/instantiation/relation; keep the CRM
   crosswalk. After this, integrity `matched` reflects full coverage.
4. **Export layer** continues to mint public `https://ric.theahg.co.za/ric/...`
   IRIs from the same manifest at serialization time.
