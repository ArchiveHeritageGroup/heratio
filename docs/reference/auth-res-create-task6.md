# Authority Resolution - Create New Authority (Task 6)

Task 6 of the AHG Authority Resolution Engine adds the "Create new authority
record" sub-workflow that the archivist reaches from the review screen.
It pre-fills the new record from the mention's own context packet plus any
external authority sources the admin has opted into, and writes per-field
provenance to Fuseki so every value's origin is auditable later.

## Flow at a glance

1. Archivist clicks "Create new authority record" on `/admin/authority-resolution/review/{mention}`.
2. `GET /admin/authority-resolution/review/{mention}/create-new` calls
   `PrefillEngine::prefill($mention)`.
3. The engine walks every registered adapter that supports the mention's
   entity type, collecting candidates from each (cached or freshly fetched).
4. The engine merges the top hit per source into a single best-guess form
   payload, tagging each field with provenance metadata.
5. The form renders with a small badge next to every pre-filled field showing
   the source, with the licence as a tooltip and a link to the source URI.
6. Archivist edits / confirms / submits.
7. `POST /admin/authority-resolution/review/{mention}/create-new` validates,
   inserts the new actor / term row via the Qubit class-table-inheritance
   pattern (`AuthorityCreator`), writes one reified RDF-Star turtle assertion
   per surviving pre-filled field to Fuseki (`FieldProvenanceWriter`), and
   records a `create_new` decision via `DecisionRecorder::recordCreateNew()`
   with the new authority id.

## Source adapters and licences

| Source   | Entity types  | Status | Licence (default note)                                          |
| -------- | ------------- | ------ | --------------------------------------------------------------- |
| viaf     | PERSON / ORG  | live   | CC0 1.0 Universal                                               |
| wikidata | PERSON / ORG / PLACE | live | CC0 1.0 Universal                                       |
| geonames | PLACE         | live   | CC BY 4.0  (attribution required)                               |
| tgn      | PLACE         | stub   | ODC-BY 1.0 + attribution to Getty Research Institute            |
| gnd      | PERSON / ORG  | stub   | CC0 1.0 Universal (DNB dedication)                              |
| isni     | PERSON / ORG  | stub   | ODC-BY 1.0                                                      |
| sagnc    | PLACE         | stub   | Crown Copyright (RSA); placeholder for jurisdictional gazetteers|

Stub adapters are registered but return `[]` until a stable endpoint is
wired. Enabling them in settings is harmless.

Heratio is jurisdiction-neutral. SAGNC is included as the South African
gazetteer alongside the international sources; Brazil (IBGE), Australia
(AusGazetteer), Aotearoa New Zealand and any other market may add their
own adapters by implementing `LookupAdapterInterface` and registering with
`AhgAuthorityResolutionServiceProvider`.

## Settings keys

All under `setting_group='authority_resolution_lookup'`. Per source:

- `lookup.<src>.enabled`        (`bool`, default `0`)
- `lookup.<src>.rate_limit`     (`int`, calls per minute)
- `lookup.<src>.cache_ttl`      (`int`, seconds; cache row in `ahg_authority_lookup_cache`)
- `lookup.<src>.license_note`   (`string`)
- `lookup.<src>.license_url`    (`string`)

GeoNames also has `lookup.geonames.username` (string, default `demo`).

Cross-source:

- `lookup.precedence`                  (`json` array; default
  `["viaf","wikidata","geonames","tgn","gnd","isni","sagnc"]`)
- `lookup.http_timeout`                (`int`, seconds; default `8`)
- `lookup.field_provenance_graph_uri`  (`string`; default
  `urn:heratio:auth-res:graph:field-provenance`)

The admin settings page lives at `/admin/authority-resolution/settings/lookup`.
Default posture: every source OFF. No HTTP fires until the admin opts in.

## ISAAR-CPF validation

For PERSON / ORG records the form enforces three mandatory fields
both client-side (HTML `required`) and server-side
(`AuthorityCreator::assertIsaarCpf`):

- `authorized_form_of_name`
- `dates_of_existence`
- `history`

For PLACE records (ISDF) only `name` is mandatory. Latitude and longitude
are optional, but if one is supplied both must be.

## RDF-Star field provenance

Each pre-filled field becomes one reified turtle assertion in the
`urn:heratio:auth-res:graph:field-provenance` named graph. Example:

```turtle
PREFIX prov:     <http://www.w3.org/ns/prov#>
PREFIX auth_res: <https://heratio.theahg.co.za/ontology/auth-res#>
PREFIX xsd:      <http://www.w3.org/2001/XMLSchema#>

<< <https://heratio.theahg.co.za/actor/913465>
     auth_res:hasField "authorized_form_of_name" >>
    auth_res:fieldValue "Frederick Douglass, 1818-1895" ;
    prov:wasDerivedFrom <https://viaf.org/viaf/10088/> ;
    prov:generatedAtTime "2026-05-19T17:15:21Z"^^xsd:dateTime ;
    auth_res:source "viaf" ;
    auth_res:licence "VIAF data is dedicated to the public domain under CC0 1.0 Universal." ;
    auth_res:licenceUrl <https://creativecommons.org/publicdomain/zero/1.0/> .
```

This is a separate graph from the decisions graph (`urn:heratio:auth-res:graph:decisions`)
so SPARQL queries can target either.

Sample query - "show me every source that contributed a value to authority 913465":

```sparql
PREFIX auth_res: <https://heratio.theahg.co.za/ontology/auth-res#>
PREFIX prov:     <http://www.w3.org/ns/prov#>

SELECT ?field ?value ?source ?sourceUri ?licence
WHERE {
  GRAPH <urn:heratio:auth-res:graph:field-provenance> {
    << <https://heratio.theahg.co.za/actor/913465> auth_res:hasField ?field >>
      auth_res:fieldValue ?value ;
      auth_res:source     ?source ;
      auth_res:licence    ?licence .
    OPTIONAL {
      << <https://heratio.theahg.co.za/actor/913465> auth_res:hasField ?field >>
        prov:wasDerivedFrom ?sourceUri .
    }
  }
}
```

## Files

- `packages/ahg-authority-resolution/src/Services/Lookup/LookupAdapterInterface.php`
- `packages/ahg-authority-resolution/src/Services/Lookup/AbstractLookupAdapter.php`
- `packages/ahg-authority-resolution/src/Services/Lookup/Adapters/{Viaf,Wikidata,GeoNames,Tgn,Gnd,Isni,Sagnc}Adapter.php`
- `packages/ahg-authority-resolution/src/Services/Lookup/PrefillEngine.php`
- `packages/ahg-authority-resolution/src/Services/AuthorityCreator.php`
- `packages/ahg-authority-resolution/src/Services/FieldProvenanceWriter.php`
- `packages/ahg-authority-resolution/database/seed_lookup_settings.sql`
- `packages/ahg-authority-resolution/resources/views/create-new.blade.php`
- `packages/ahg-authority-resolution/resources/views/_prefill-field.blade.php`
- `packages/ahg-authority-resolution/resources/views/settings.blade.php`

## Operational defaults

- External HTTP fires only when `lookup.<src>.enabled = 1`.
- Cache lives in `ahg_authority_lookup_cache` (one row per (source,
  entity_type, query_text); UNIQUE key).
- Rate-limit ledger is in-process (resets on php-fpm worker restart).
  Switch to a Redis-backed sliding window if strict cross-process limits
  are required.
- Failures are logged via `Log::warning`; never bubbled to the controller.
  External-service flake degrades to "no pre-fill from that source", not
  an HTTP 500 on the form.
