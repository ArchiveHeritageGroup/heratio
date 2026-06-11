> Heratio Help Center article. Category: Metadata Export.

# CIDOC-CRM (Metadata Export)

**Version:** 1.0
**Date:** 2026-06-11
**Author:** Plain Sailing Information Systems

---

## What it does

This exporter, part of the **Metadata Export** dashboard, emits a single
archival record as a CIDOC Conceptual Reference Model (CIDOC-CRM / ISO 21127)
RDF document. CIDOC-CRM is the de-facto interoperability ontology for cultural
heritage, used by ResearchSpace, the Erlangen-CRM importer, Getty Linked Open
Data and most museum aggregator pipelines.

It is a sibling of the RiC-bridge CIDOC-CRM export (see "CIDOC-CRM Export"). The
key difference is that this exporter models the **creation chain as an explicit
`crm:E12_Production` event** rather than flattening it onto the object, so the
production - actor - time-span relationship is expressed as first-class CRM:

```
E22 Human-Made Object
  - P108i was produced by - E12 Production
                              E12 - P14 carried out by - E39/E21/E40/E74 Actor
                              E12 - P4 has time-span   - E52 Time-Span
```

It is one of the slices of the unified G/L/A/M knowledge graph (RiC +
CIDOC-CRM + KM, issue #1197).

## Endpoint

The exporter is reached from the Metadata Export dashboard, or directly:

```
GET /admin/metadata-export/cidoc-crm?io={id}
GET /admin/metadata-export/cidoc-crm.ttl?io={id}
GET /admin/metadata-export/cidoc-crm.rdf?io={id}
```

| Parameter | Default | Notes |
|---|---|---|
| `io` | (required) | `information_object.id` of the record to export |
| `culture` | session locale | ISO 639-1 language code (`en`, `af`, `fr`, etc.) |
| `rdf` | `ttl` | `ttl` (Turtle) or `rdf` (RDF/XML); ignored when a `.ttl`/`.rdf` path extension is used |

The path extension wins over `?rdf=` when both are present.

## Examples

Turtle (default):

```bash
curl 'https://heratio.example.org/admin/metadata-export/cidoc-crm?io=42'
```

RDF/XML:

```bash
curl 'https://heratio.example.org/admin/metadata-export/cidoc-crm.rdf?io=42'
```

## What is in the document

For every record the export emits:

- **The object node** typed `crm:E22_Human-Made_Object`, with `rdfs:label`,
  `crm:P102_has_title` (to an `E35 Title` node), `crm:P1_is_identified_by` (to an
  `E42 Identifier` node) and `crm:P3_has_note` (scope and content, extent).
- **`crm:P108i_was_produced_by`** to a single `crm:E12_Production` node when the
  record has creators or dated creation events.
- On the **E12 Production** node: one `crm:P14_carried_out_by` per creator actor
  and one `crm:P4_has_time-span` per dated creation event.
- **One Actor node** per creator, typed `crm:E21_Person` / `crm:E40_Legal_Body`
  / `crm:E74_Group` / `crm:E39_Actor` based on the actor's entity type.
- **`crm:P50_has_current_keeper`** to an `crm:E40_Legal_Body` repository node.
- **`crm:P129_is_about`** to `crm:E1_CRM_Entity` nodes for subject access points.
- **`crm:P67_refers_to`** to `crm:E53_Place` nodes for place access points.
- **`crm:P72_has_language`** to `crm:E56_Language` nodes for language access points.
- **`crm:E52_Time-Span`** nodes per dated creation event, carrying
  `crm:P82a_begin_of_the_begin` and `crm:P82b_end_of_the_end` as `xsd:date`.

The default namespace is the official CIDOC ns
(`http://www.cidoc-crm.org/cidoc-crm/`); the Erlangen CRM
(`http://erlangen-crm.org/current/`) is declared as the `ecrm:` alias so
Erlangen-based tooling resolves the same local names.

## Formats

- **Turtle** (`text/turtle`) - default; compact, good for human review and git diffs.
- **RDF/XML** (`application/rdf+xml`) - for CRM tooling (ResearchSpace, Apache Jena, Erlangen importer).

Both express the same graph.

## Authentication and visibility

The dashboard endpoint sits behind the `web` + `auth` middleware - authenticated
staff can export any record, including unpublished ones, for review. The
serializer additionally carries a **published-records gate**
(`status.type_id = 158 AND status.status_id = 160`, root record `id = 1`
excluded) that any future unauthenticated Linked Data surface can opt into so
only published records are exposed publicly.

## Read-only

The exporter only reads the database. It never writes, alters or migrates any
table, and leaves the existing exporters (DACS, MODS, RAD, dcterms, EAD, EAC,
MARC) untouched.

## Troubleshooting

- **404 "No record produced":** the `io` id does not resolve to an
  `information_object` row in the supplied culture. Supply `?culture=en` or
  translate the record.
- **Empty Production block:** no creation `event` (`type_id = 111`) ties an
  actor to the record and no dated creation event exists. Add a creator or a
  date.
- **Empty Time-Span block:** the creation events carry no `start_date` /
  `end_date` / `date_display`. Add at least one.

## Related articles

- "CIDOC-CRM Export" - the RiC-bridge per-record CRM exporter (E73-centred).
- "RiC Dashboard User Guide" - Heratio's RiC-O CRUD surface.
