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
  the serializer. Uses the package's `InformationObjectFetcher` trait for all
  read-only fetches.
- `packages/ahg-metadata-export/src/Controllers/MetadataExportController.php` -
  `downloadCidocCrm()` method + `cidoc-crm` entry in the dashboard `$formats`.
- `packages/ahg-metadata-export/routes/web.php` - `ahgmetadataexport.cidoc` and
  `ahgmetadataexport.cidoc.ext` routes under `/admin/metadata-export`.

## CRM class / property mapping

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
GET /admin/metadata-export/cidoc-crm?io={id}[&culture=en][&rdf=ttl|rdf]
GET /admin/metadata-export/cidoc-crm.ttl?io={id}
GET /admin/metadata-export/cidoc-crm.rdf?io={id}
```

Behind `web` + `auth` middleware, under the `/admin/metadata-export` prefix so
the IO slug catch-all in `ahg-information-object-manage` cannot intercept it.
The dashboard view iterates the controller's `$formats` array, so the new
`cidoc-crm` format card and preview option appear with no Blade edit (the views
are page-locked).

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

- Every query is a SELECT via `InformationObjectFetcher`; no INSERT / UPDATE /
  DELETE / ALTER anywhere.
- Existing exporters are untouched; the only edits to shared files add the new
  format (import line, `$formats` entry, `downloadCidocCrm()` method, two
  routes).
- RDF/XML output is XML-escaped (`ENT_XML1`) and verified well-formed via
  simplexml; Turtle literals are escaped per Turtle string rules and verified
  structurally (every statement terminated).

## Validation performed

- `php -l` clean on the serializer, controller and routes.
- Standalone harness with stubbed facades renders both formats from synthetic
  data; RDF/XML parses under `simplexml_load_string` (well-formed), Turtle
  passes the structural terminator check. Special characters
  (`"`, `&`, `<`, `>`) round-trip correctly in both forms.

## Epic status

Issue #1197 remains OPEN. This slice adds CIDOC-CRM serialization to the
metadata-export framework; further slices (KM grounding, cross-standard graph
links) continue under the epic.
