> Heratio Help Center article. Category: Metadata Export.

# CIDOC-CRM (Metadata Export)

**Version:** 1.0
**Date:** 2026-06-11
**Author:** Plain Sailing Information Systems

---

## What it does

This exporter, part of the **Metadata Export** dashboard, emits Heratio entities
as CIDOC Conceptual Reference Model (CIDOC-CRM / ISO 21127) RDF documents.
CIDOC-CRM is the de-facto interoperability ontology for cultural heritage, used
by ResearchSpace, the Erlangen-CRM importer, Getty Linked Open Data and most
museum aggregator pipelines.

It now covers all three core G/L/A/M entity types - **records, actors and terms
/ places** - so a Heratio holding can be published to a CRM consumer as a joined
graph of objects, the people and bodies who made them, and the subjects and
places they describe. Each document references the others by URI, so they slot
together in one triple store.

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

## Endpoints

There is one endpoint family per entity type. All share the same format
negotiation (Turtle by default, RDF/XML via `?rdf=rdf` or a `.rdf` extension) and
all live under `/admin/metadata-export`:

```
# record
GET /admin/metadata-export/cidoc-crm?io={id}
GET /admin/metadata-export/cidoc-crm.ttl?io={id}
GET /admin/metadata-export/cidoc-crm.rdf?io={id}

# actor (person / corporate body / family)
GET /admin/metadata-export/cidoc-crm-actor?actor={id}
GET /admin/metadata-export/cidoc-crm-actor.ttl?actor={id}
GET /admin/metadata-export/cidoc-crm-actor.rdf?actor={id}

# term / place (place taxonomy -> E53 Place, else E55 Type)
GET /admin/metadata-export/cidoc-crm-term?term={id}
GET /admin/metadata-export/cidoc-crm-term.ttl?term={id}
GET /admin/metadata-export/cidoc-crm-term.rdf?term={id}
```

| Parameter | Default | Notes |
|---|---|---|
| `io` / `actor` / `term` | (required) | the id of the entity to export |
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

### What is in an actor document

For an actor (`?actor={id}`):

- **The actor node** typed by entity type: `crm:E21_Person` (person),
  `crm:E40_Legal_Body` (corporate body), `crm:E74_Group` (family), or
  `crm:E39_Actor` when the type is unknown.
- **`crm:P1_is_identified_by`** to a `crm:E82_Actor_Appellation` node carrying
  the authorized form of name.
- **`crm:P3_has_note`** for the actor history.
- **An existence `crm:E52_Time-Span`** when dates exist: for a person via a
  `crm:E67_Birth` event (`P98_brought_into_life` + `P4_has_time-span`), for other
  types via a direct `P4_has_time-span`. The span carries
  `crm:P82a_begin_of_the_begin` / `crm:P82b_end_of_the_end` (`xsd:date`) and the
  display dates label.
- **`crm:P11i_participated_in`** to the `crm:E12_Production` of each record the
  actor created - the same production URI the record export mints, so the two
  documents join.

### What is in a term / place document

For a term (`?term={id}`). A term in the **Places** taxonomy maps to
`crm:E53_Place`; any other taxonomy (subjects, genres) maps to `crm:E55_Type`:

- **`crm:P1_is_identified_by`** to a `crm:E48_Place_Name` (places) or
  `crm:E41_Appellation` (types) appellation node.
- **Hierarchy:** places use `crm:P89_falls_within` (parent) and
  `crm:P89i_contains` (children); types use `crm:P127_has_broader_term` and
  `crm:P127i_has_narrower_term`.
- **Records that cite the term**, via the inverse of the record export's forward
  link: places use `crm:P67i_is_referred_to_by`; types use
  `crm:P129i_is_subject_of`. Each points at the record's `#crm-object` node.

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

The actor and term endpoints carry the same published-records gate on their
linked-record lists, so a public surface that opts in never exposes a draft
record title through an actor's produced works or a term's citations.

## Read-only

The exporter only reads the database. It never writes, alters or migrates any
table, and leaves the existing exporters (DACS, MODS, RAD, dcterms, EAD, EAC,
MARC) and the existing record CIDOC-CRM export untouched. The actor and term
serializers are new files; they reuse the record serializer's rendering through
a shared trait so the three CRM surfaces produce identical Turtle / RDF/XML.

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
