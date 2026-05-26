# CIDOC-CRM v7 Bridge (Heratio Phase 1)

**Status:** Shipped in Phase 1 of issue #659 (CIDOC-CRM v7).
**Package:** `packages/ahg-ric/` (new subdir `src/Crm/`).
**Spec:** CIDOC-CRM 7.1.3 - https://www.cidoc-crm.org/sites/default/files/cidoc_crm_v7.1.3.pdf
**Sibling spec:** RiC-O 1.0 - https://www.ica.org/standards/RiC/RiC-O_v1.0

## What it does

Phase 1 ships the read-only RiC -> CIDOC-CRM crosswalk plus a per-record serialiser that emits one CIDOC-CRM RDF document per Heratio Information Object. It does NOT yet ship the SPARQL endpoint extension (that uses the SPARQL proxy added in #658 - Phase 2 work) or Getty AAT/ULAN reconciliation (Phase 3).

## Class crosswalk (16 entries)

| RiC-O | CIDOC-CRM | Notes |
|---|---|---|
| `rico:Record` | `crm:E73_Information_Object` | the central CHO node |
| `rico:RecordSet` | `crm:E78_Curated_Holding` | fonds, series, collection wrappers |
| `rico:RecordPart` | `crm:E73_Information_Object` | components inside an IO |
| `rico:Instantiation` | `crm:E84_Information_Carrier` | digital surrogate / manifestation |
| `rico:Activity` | `crm:E7_Activity` | creation, custody, modification events |
| `rico:CarrierType` | `crm:E55_Type` | paper, parchment, file medium |
| `rico:Agent` | `crm:E39_Actor` | generic actor (super-class) |
| `rico:Person` | `crm:E21_Person` | natural person |
| `rico:Family` | `crm:E74_Group` | family group |
| `rico:Group` | `crm:E74_Group` | informal group |
| `rico:CorporateBody` | `crm:E40_Legal_Body` | legal entity |
| `rico:Place` | `crm:E53_Place` | geospatial extent |
| `rico:Date` | `crm:E52_Time-Span` | time-span anchor |
| `rico:Mandate` | `crm:E30_Right` | governing mandate |
| `rico:Rule` | `crm:E30_Right` | governing rule |
| `rico:Occupation` | `crm:E55_Type` | role/profession typology |

## Property crosswalk (20 entries)

| RiC-O predicate | CIDOC-CRM predicate |
|---|---|
| `rico:hasCreator` | `crm:P14_carried_out_by` |
| `rico:isAssociatedWithDate` | `crm:P4_has_time-span` |
| `rico:isAssociatedWithPlace` | `crm:P7_took_place_at` |
| `rico:hasOrHadHolder` | `crm:P52_has_current_owner` |
| `rico:isOrWasHeldBy` | `crm:P50_has_current_keeper` |
| `rico:hasSubject` | `crm:P129_is_about` |
| `rico:hasLanguage` | `crm:P72_has_language` |
| `rico:hasOrHadIdentifier` | `crm:P1_is_identified_by` |
| `rico:title` | `crm:P102_has_title` |
| `rico:hasBeginningDate` | `crm:P82a_begin_of_the_begin` |
| `rico:hasEndDate` | `crm:P82b_end_of_the_end` |
| `rico:descriptiveNote` | `crm:P3_has_note` |
| `rico:hasOrHadPart` | `crm:P46_is_composed_of` |
| `rico:isPartOf` | `crm:P46i_forms_part_of` |
| `rico:hasCarrierType` | `crm:P2_has_type` |
| `rico:hasInstantiation` | `crm:P128_carries` |
| `rico:isInstantiationOf` | `crm:P128i_is_carried_by` |
| `rico:hasOrHadAgent` | `crm:P11_had_participant` |
| `rico:hasMandate` | `crm:P104_is_subject_to` |
| `rico:performs` | `crm:P14i_performed` |

The actor sub-typing table picks the most-specific CRM Actor class by `actor.entity_type_id`: 131 -> `crm:E21_Person`, 132 -> `crm:E40_Legal_Body`, 133 -> `crm:E74_Group`. Anything else falls back to the generic `crm:E39_Actor`.

## Documented gaps

### RiC-only (no direct CRM equivalent)

| RiC concept | Why dropped |
|---|---|
| `rico:RecordResource` | RiC abstract super-class; CRM has no equivalent abstract record concept. |
| `rico:hasOrHadSubject` | Folded into `crm:P129_is_about`; archival vs museum subjecting is not distinguished in CRM. |
| `rico:isOrWasRegulatedBy` | Closest match is `crm:P104_is_subject_to`, but weaker than RiC regulation semantics. |
| `rico:hasProvenance` | CRM models provenance via E7_Activity chains, not a single predicate - bridge does not synthesise the chain. |
| `rico:hasOrHadConstitutiveActivity` | CRM uses `crm:P108_has_produced`; constitutive vs productive distinction is RiC-specific. |

### CRM-only (no direct RiC equivalent)

| CRM concept | Why bridge does not emit it |
|---|---|
| `crm:E12_Production` | Specialised activity sub-class; RiC keeps creation under generic Activity. |
| `crm:E83_Type_Creation` | Used for museum typology events; out of scope for archival records. |
| `crm:P108_has_produced` | Use `rico:hasOrHadConstitutiveActivity` (lossy) on import. |
| `crm:E22_Human-Made_Object` | Three-dimensional objects belong to `ahg-spectrum`, not the records ontology. |

## Output formats

`CrmSerializer::serializeRecord($id, $culture, $format)` takes:

- `CrmSerializer::FORMAT_RDFXML` -> `application/rdf+xml; charset=utf-8` (default, suits Fuseki POST + ResearchSpace + Erlangen-CRM importer)
- `CrmSerializer::FORMAT_TURTLE` -> `text/turtle; charset=utf-8` (compact, suits human review + git diffs)

The serialiser is read-only and stateless. It reuses the same DB-fetch shape as `EdmSerializer` (taxonomy 35 = subjects, 42 = places, 7 = languages; event type 111 = creation). The information object IRI is `<host>/<slug>` and its CRM identity node is `<host>/<slug>#crm-cho`. Actor IRIs are `<host>/actor/<id>`. Time-span IRIs are `<host>/<slug>#crm-ts-<event-id>`.

## Export endpoint

```
GET /admin/export/crm/{slug}
GET /admin/export/crm/id/{id}
```

Sits under `/admin/*` to stay outside the locked `/{slug}` catch-all in `ahg-information-object-manage`. Content negotiation rules:

- `Accept: text/turtle` -> Turtle
- `Accept: application/rdf+xml` (or no header) -> RDF/XML
- `?format=turtle` overrides the Accept header

Response headers:

- `Content-Type: application/rdf+xml; charset=utf-8` or `text/turtle; charset=utf-8`
- `Content-Disposition: inline; filename="cidoc-crm-<id>.<ext>"`
- `X-CRM-Version: 7.1.3`
- `X-Bridge-Phase: 659.phase-1`

## Phase 2+ outstanding (NOT in this release)

1. **SPARQL CRM-aware endpoint.** Extend the read-only SPARQL proxy added in #658 so a `crm:E73_Information_Object ?p ?o` graph pattern resolves through the bridge (issue #659 Phase 2).
2. **Getty reconciliation.** Add an AAT/ULAN lookup pass that promotes `crm:P2_has_type` literals into `owl:sameAs` links onto canonical Getty IRIs (issue #659 Phase 3).
3. **RiC <-> CRM Fuseki rule pack.** Materialise the crosswalk as a SPARQL CONSTRUCT rule set that runs on every Fuseki sync, so the graph store carries both sets of triples without a per-record export.
4. **Reciprocal `owl:sameAs` emission** between the RiC IRI and the CRM identity node so downstream consumers can round-trip the graph.

## Files

| Path | Role |
|---|---|
| `packages/ahg-ric/src/Crm/RicToCrmMapper.php` | Crosswalk tables + helper methods |
| `packages/ahg-ric/src/Crm/CrmSerializer.php` | Per-IO RDF/XML + Turtle serialiser |
| `packages/ahg-ric/src/Controllers/CrmExportController.php` | `/admin/export/crm/{slug}` endpoint |
| `packages/ahg-ric/routes/web.php` | Route registration (added near `/api/sparql`) |
| `packages/ahg-ric/tests/Unit/RicToCrmMapperTest.php` | Row-by-row mapper assertions |
| `packages/ahg-ric/tests/Unit/CrmSerializerTest.php` | DOM parse + structural smoke |
| `docs/help/cidoc-crm-export.md` | User-facing help article |

## Maintenance notes

- The `RicToCrmMapper::mappingCount()` returns 36 today (16 classes + 20 properties). The mapper test asserts that exact number - any add/remove must update both the constant and the doc table above.
- Heratio's locked tree includes `packages/ahg-information-object-manage/`; the export endpoint deliberately mounts at `/admin/export/crm/...` to stay outside the lock. Future SPARQL CRM bridge work should also avoid the lock.
- The bridge ships **without** RDF library dependencies (no `easyrdf`, no `php-rdf`); the serialiser hand-writes the document, mirroring `EdmSerializer` and `Ead*Serializer`. Keep it that way - a new vendor dependency is a separate decision.
