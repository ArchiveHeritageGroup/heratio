> Heratio Help Center article. Category: Federation.

# CIDOC-CRM Export

**Version:** 1.0
**Date:** 2026-05-26
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## What it does

The CIDOC-CRM export emits a single archival record as a CIDOC Conceptual Reference Model (CRM) 7.1.3 RDF document. CIDOC-CRM is the de-facto interoperability ontology for museum and cultural-heritage data, used by ResearchSpace, the Erlangen-CRM importer, Getty's Linked Open Data services, and most major aggregator pipelines outside the archival sector.

Heratio's native ontology is RiC-O (ICA's Records in Contexts). The CRM export bridges the two so a Heratio record can be ingested anywhere a CIDOC-CRM consumer expects to see data.

## Endpoint

```
GET /admin/export/crm/{slug}
GET /admin/export/crm/id/{id}
```

Both routes accept the same query parameters:

| Parameter | Default | Notes |
|---|---|---|
| `culture` | session locale | ISO 639-1 language code (`en`, `af`, `fr`, etc.) |
| `format` | `rdfxml` | `rdfxml`, `turtle`, or `ttl` |

You can also pick the format via the standard HTTP `Accept` header:

- `Accept: application/rdf+xml` -> RDF/XML
- `Accept: text/turtle` -> Turtle
- no header / anything else -> RDF/XML (default)

The `?format=` query parameter wins over `Accept` when both are supplied.

## Examples

Fetch the RDF/XML representation of a record by slug:

```bash
curl -H 'Accept: application/rdf+xml' \
  https://heratio.example.org/admin/export/crm/my-record-slug
```

Fetch the Turtle representation of the same record:

```bash
curl -H 'Accept: text/turtle' \
  https://heratio.example.org/admin/export/crm/my-record-slug
```

Same thing using the `?format=` override:

```bash
curl https://heratio.example.org/admin/export/crm/my-record-slug?format=turtle
```

## Response headers

| Header | Value |
|---|---|
| `Content-Type` | `application/rdf+xml; charset=utf-8` or `text/turtle; charset=utf-8` |
| `Content-Disposition` | `inline; filename="cidoc-crm-<id>.<ext>"` |
| `X-CRM-Version` | `7.1.3` |
| `X-Bridge-Phase` | `659.phase-1` |

## What's in the document

For every record the export emits:

- **The CHO node** typed as `crm:E73_Information_Object`, with `rdfs:label`, `crm:P102_has_title`, `crm:P1_is_identified_by` (the archival identifier), `crm:P3_has_note` (scope and content).
- **One `crm:P14_carried_out_by`** link per creator actor.
- **One `crm:P4_has_time-span`** link per dated event.
- **`crm:P129_is_about`** literals for subject access points.
- **`crm:P7_took_place_at`** literals for place access points.
- **`crm:P72_has_language`** literals for language access points.
- **`crm:P50_has_current_keeper`** link to the holding repository.
- **One Actor node** per creator, typed as `crm:E21_Person` / `crm:E40_Legal_Body` / `crm:E74_Group` based on the actor's entity type.
- **One `crm:E40_Legal_Body`** node for the repository.
- **One `crm:E52_Time-Span`** node per dated event, carrying `crm:P82a_begin_of_the_begin` and `crm:P82b_end_of_the_end` as `xsd:date` literals.

## When to use it

- Submitting a record to a CIDOC-CRM aggregator (e.g. an art-museum union catalogue, a ResearchSpace tenant, a Getty pipeline).
- Cross-walking Heratio holdings into a museum collections system that speaks CRM natively.
- Sharing a single record with a researcher who wants the data in a CRM-native triple store.

For bulk export to **Europeana** use the EDM export instead (see "Europeana EDM Publish"). EDM is purpose-built for Europeana's ingest gate; CRM is the broader interoperability format.

## Authentication

The endpoint is behind the standard `web` middleware group. Admin sessions can browse to the URL directly. Headless clients should either reuse the session cookie of an authenticated browser or proxy through the `/api/v2/` token-authenticated alias once it ships.

## Known gaps

Phase 1 ships the per-record export only. The following work is tracked under issue #659 for later phases:

- **CRM-aware SPARQL endpoint.** Today's `/api/sparql` proxy returns RiC triples only. Phase 2 extends it to accept `crm:E73_Information_Object ?p ?o` queries.
- **Getty reconciliation.** Phase 3 promotes subject + access-point literals into `owl:sameAs` links onto Getty AAT/ULAN/TGN IRIs.
- **Reciprocal RiC <-> CRM `owl:sameAs`** on the per-record document, so round-trippers can re-attach the original RiC IRI after a CRM consumer enriches the graph.

## Troubleshooting

- **404 "Record not found":** the slug does not resolve via the `slug` table. Confirm the record exists and the slug is current.
- **404 "Record not found in culture XX":** the record has no i18n row for the supplied locale. Either supply `?culture=en` or have the record translated.
- **Empty Actor block:** no `event` row with `type_id = 111` exists for the record. CRM `crm:P14_carried_out_by` is only emitted when a creation event ties an actor to the record.
- **Empty Time-Span block:** the events on the record have no `start_date` / `end_date` / `date_display`. Add at least one of the three on the event row.

## Related articles

- "Europeana EDM Publish" - sibling per-record exporter for the Europeana pipeline.
- "OpenRiC Fuseki Instance Load" - bulk-loads RiC place and agent IRIs into Fuseki for federated querying.
- "RiC Dashboard User Guide" - Heratio's RiC-O CRUD surface.
