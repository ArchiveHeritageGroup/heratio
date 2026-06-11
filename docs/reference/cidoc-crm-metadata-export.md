# CIDOC-CRM serializer in ahg-metadata-export (issue #1197)

A second CIDOC-CRM (ISO 21127) per-record RDF exporter, living in the
`ahg-metadata-export` package alongside the DACS / MODS / RAD / dcterms / EAD /
EAC / MARC serializers. It is a slice of the unified G/L/A/M knowledge graph
epic (issue #1197, RiC + CIDOC-CRM + KM) and complements the existing
RiC-bridge CRM exporter in `packages/ahg-ric/src/Crm/CrmSerializer.php`.

## Why a second CRM exporter

The ahg-ric `CrmSerializer` types the central node as `crm:E73_Information_Object`
(via the rico:Record -> E73 mapping) and flattens the creator onto the object as
a direct `crm:P14_carried_out_by`. The metadata-export `CidocCrmSerializer`
instead:

- types the central node as `crm:E22_Human-Made_Object` (the made artefact), and
- models the creation as an explicit `crm:E12_Production` event, so the chain
  `E22 - P108i was produced by - E12 - P14 carried out by - E39 Actor` and
  `E12 - P4 has time-span - E52` is first-class CRM rather than flattened.

Both exporters share the same namespace conventions and actor sub-typing so the
two documents interoperate.

## Files

- `packages/ahg-metadata-export/src/Services/Exporters/CidocCrmSerializer.php` -
  the records serializer. Uses the package's `InformationObjectFetcher` trait
  for all read-only fetches.
- `packages/ahg-metadata-export/src/Services/Exporters/CidocCrmActorSerializer.php` -
  the actor serializer (person / corporate body / family). Read-only.
- `packages/ahg-metadata-export/src/Services/Exporters/CidocCrmTermSerializer.php` -
  the term / place serializer (subjects, genres -> E55 Type; places -> E53
  Place). Read-only.
- `packages/ahg-metadata-export/src/Services/Exporters/CrmRdfRenderer.php` -
  shared Turtle + RDF/XML rendering trait. The actor and term serializers reuse
  it (it is the records serializer's render code, lifted verbatim) so the three
  CIDOC-CRM surfaces cannot drift in their serialisation. The records serializer
  is left untouched and keeps its own private render copies.
- `packages/ahg-metadata-export/src/Controllers/MetadataExportController.php` -
  `downloadCidocCrm()` (records) + `downloadCidocCrmActor()` +
  `downloadCidocCrmTerm()`; `cidoc-crm` entry in the dashboard `$formats`.
- `packages/ahg-metadata-export/routes/web.php` - `ahgmetadataexport.cidoc[.ext]`
  (records), `ahgmetadataexport.cidoc.actor[.ext]` and
  `ahgmetadataexport.cidoc.term[.ext]` routes, all under `/admin/metadata-export`.

## Entity coverage

CIDOC-CRM export now spans all three core G/L/A/M entity types, not just
records:

| Entity | Endpoint param | Central CRM class |
|---|---|---|
| Information object (record) | `?io=` | `E22_Human-Made_Object` |
| Actor (person / corporate body / family) | `?actor=` | `E21_Person` / `E40_Legal_Body` / `E74_Group` / `E39_Actor` |
| Term - subject / genre | `?term=` | `E55_Type` |
| Term - place (taxonomy 42) | `?term=` | `E53_Place` |

The actor and term documents reference the same record/production fragment URIs
the records exporter mints (`<record-url>#crm-object`, `<record-url>#crm-production`),
so an actor, a term and a record document join cleanly in one triple store.

## CRM class / property mapping - records

| Heratio source | CRM class | Linking property |
|---|---|---|
| information_object | `E22_Human-Made_Object` | (central node) |
| title | `E35_Title` | `P102_has_title` |
| identifier | `E42_Identifier` | `P1_is_identified_by` |
| scope_and_content / extent_and_medium | (literal) | `P3_has_note` |
| creation (event type_id 111) | `E12_Production` | `P108i_was_produced_by` |
| creator actor | `E21_Person` / `E40_Legal_Body` / `E74_Group` / `E39_Actor` | `E12 - P14_carried_out_by` |
| event start/end date | `E52_Time-Span` | `E12 - P4_has_time-span`, then `P82a_begin_of_the_begin` / `P82b_end_of_the_end` (`xsd:date`) |
| repository | `E40_Legal_Body` | `P50_has_current_keeper` |
| subject access point (taxonomy 35) | `E1_CRM_Entity` | `P129_is_about` |
| place access point (taxonomy 42) | `E53_Place` | `P67_refers_to` |
| language access point (taxonomy 7) | `E56_Language` | `P72_has_language` |

Actor sub-typing uses the AtoM `actor.entity_type_id` ids
(131 = Person, 132 = Corporate body, 133 = Family), mirroring
`RicToCrmMapper::AGENT_SUBCLASS`.

## CRM class / property mapping - actors

`CidocCrmActorSerializer::serializeActor($actorId, $culture, $format, $publicOnly)`.

| Heratio source | CRM class | Linking property |
|---|---|---|
| actor (person) | `E21_Person` | (central node) |
| actor (corporate body) | `E40_Legal_Body` | (central node) |
| actor (family) | `E74_Group` | (central node) |
| actor (unknown type) | `E39_Actor` | (central node, generic super-class) |
| authorized_form_of_name | `E82_Actor_Appellation` | `P1_is_identified_by` |
| history | (literal) | `P3_has_note` |
| existence dates / dates_of_existence | `E52_Time-Span` | persons: `E67_Birth - P98_brought_into_life` + `P4_has_time-span`; other types: actor `P4_has_time-span`. Span carries `P82a_begin_of_the_begin` / `P82b_end_of_the_end` (`xsd:date`) and the display label |
| created records (creation event type_id 111) | `E12_Production` (and an `E22` stub per record) | actor `P11i_participated_in` -> `E12`, which carries `P14_carried_out_by` (back to the actor) and `P108_has_produced` -> the record `E22` |

The production node URI is `<record-url>#crm-production` and the record stub is
`<record-url>#crm-object`, identical to the records exporter's fragments, so the
actor graph dovetails with each record graph. The produced-record list is
published-aware: with `$publicOnly = true` only published records (status
type_id 158, status_id 160; root id 1 excluded) appear, so a public actor
document never leaks a draft record title.

## CRM class / property mapping - terms and places

`CidocCrmTermSerializer::serializeTerm($termId, $culture, $format, $publicOnly)`.

A term is typed by its taxonomy: the **Places** taxonomy (id 42) -> `E53_Place`;
every other taxonomy (subjects id 35, genres, etc.) -> `E55_Type`.

| Heratio source | CRM class | Linking property |
|---|---|---|
| place term (taxonomy 42) | `E53_Place` | (central node) |
| subject / genre term | `E55_Type` | (central node) |
| term_i18n.name (place) | `E48_Place_Name` | `P1_is_identified_by` |
| term_i18n.name (type) | `E41_Appellation` | `P1_is_identified_by` |
| parent term (place) | `E53_Place` | `P89_falls_within` |
| child term (place) | `E53_Place` | `P89i_contains` |
| parent term (type) | `E55_Type` | `P127_has_broader_term` |
| child term (type) | `E55_Type` | `P127i_has_narrower_term` |
| citing record (place) | `E22_Human-Made_Object` (stub) | `P67i_is_referred_to_by` (inverse of the record's `P67_refers_to`) |
| citing record (type) | `E22_Human-Made_Object` (stub) | `P129i_is_subject_of` (inverse of the record's `P129_is_about`) |

The cited-record links are the exact inverses of the records exporter's forward
properties (`P67_refers_to` for places, `P129_is_about` for subjects), so a term
document and a record document are two halves of the same edge. The cited-record
list is published-aware on the same gate as the actor export.

## Namespaces

| Prefix | IRI |
|---|---|
| `rdf` | `http://www.w3.org/1999/02/22-rdf-syntax-ns#` |
| `rdfs` | `http://www.w3.org/2000/01/rdf-schema#` |
| `xsd` | `http://www.w3.org/2001/XMLSchema#` |
| `crm` | `http://www.cidoc-crm.org/cidoc-crm/` (default) |
| `ecrm` | `http://erlangen-crm.org/current/` (Erlangen alias) |

## Formats

`serializeRecord(int $objectId, string $culture, string $format, bool $publicOnly)`
emits:

- **Turtle** (`FORMAT_TURTLE`, default) - `text/turtle`.
- **RDF/XML** (`FORMAT_RDFXML`) - `application/rdf+xml`.

Both are produced from one format-neutral node bag so the two serialisations
stay in lock-step.

## Exposure

Mirrors the `download/{format}` pattern of the DACS/MODS/RAD/dcterms exporters,
but emits RDF instead of an XML envelope:

```
# records
GET /admin/metadata-export/cidoc-crm?io={id}[&culture=en][&rdf=ttl|rdf]
GET /admin/metadata-export/cidoc-crm.ttl?io={id}
GET /admin/metadata-export/cidoc-crm.rdf?io={id}

# actors (person / corporate body / family)
GET /admin/metadata-export/cidoc-crm-actor?actor={id}[&culture=en][&rdf=ttl|rdf]
GET /admin/metadata-export/cidoc-crm-actor.ttl?actor={id}
GET /admin/metadata-export/cidoc-crm-actor.rdf?actor={id}

# terms and places (place taxonomy -> E53 Place, else E55 Type)
GET /admin/metadata-export/cidoc-crm-term?term={id}[&culture=en][&rdf=ttl|rdf]
GET /admin/metadata-export/cidoc-crm-term.ttl?term={id}
GET /admin/metadata-export/cidoc-crm-term.rdf?term={id}
```

All three families are behind `web` + `auth` middleware, under the
`/admin/metadata-export` prefix so the IO slug catch-all in
`ahg-information-object-manage` cannot intercept them. They use the same format
negotiation (Turtle default; RDF/XML via `?rdf=rdf` or a `.rdf` extension) as
the record export. The actor and term endpoints are exposed as named routes; the
dashboard `$formats` array (record-centric, iterated per IO) is left unchanged so
the per-record download/preview view continues to behave exactly as before.

## Published-records gate

`serializeRecord(..., $publicOnly = true)` returns `''` unless a published
status row exists for the record:

```
status.type_id  = 158   (publication status type)
status.status_id = 160   (Published)
object id        != 1    (synthetic root excluded)
```

The admin download passes `$publicOnly = false` (staff may export drafts). The
gate is wired and ready for any future unauthenticated Linked Data surface.

## Read-only / safety

- Every query is a SELECT (records via `InformationObjectFetcher`; actors and
  terms via the serializers' own private fetch methods); no INSERT / UPDATE /
  DELETE / ALTER anywhere.
- The records exporter (`CidocCrmSerializer`) is untouched. The actor and term
  serializers are new files; they reuse the records render logic through the new
  `CrmRdfRenderer` trait (a verbatim copy), so the records serializer's own
  render path is unchanged.
- Shared-file edits are purely additive: the controller gains two new download
  methods + two import lines; `routes/web.php` gains four named routes. No
  existing route, method or `$formats` entry was modified.
- RDF/XML output is XML-escaped (`ENT_XML1`) and verified well-formed via
  simplexml; Turtle literals are escaped per Turtle string rules and verified
  structurally (every statement terminated).

## Validation performed

- `php -l` clean on `CrmRdfRenderer`, `CidocCrmActorSerializer`,
  `CidocCrmTermSerializer`, the controller and routes.
- Render harness (stubbed `url()`): Turtle structurally sound, RDF/XML
  well-formed under `simplexml_load_string`, all five namespaces declared,
  special characters (`"`, `&`, `<`, `>`, newlines) escaped correctly in both
  forms.
- Graph harness over `CidocCrmTermSerializer::buildGraph`: place ->
  E53/E48 + P89/P89i/P67i; subject -> E55/E41 + P127/P127i/P129i.
- Live read-only smoke test booting the Laravel app against the real `heratio`
  DB: a real actor, place and subject all serialise to valid Turtle + well-formed
  RDF/XML; the `$publicOnly = true` path runs clean. Actor sub-typing verified on
  real rows: entity_type_id 131 -> `E21_Person`, 132 -> `E40_Legal_Body`, NULL ->
  `E39_Actor` (generic fallback).

## Epic status

Issue #1197 remains OPEN. This slice adds CIDOC-CRM serialization to the
metadata-export framework; further slices (KM grounding, cross-standard graph
links) continue under the epic.
