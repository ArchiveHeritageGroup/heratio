> Heratio Help Center article. Category: AI & Automation.

# Authority Resolution - Creating a New Authority Record

When none of the candidates fit a mention, the archivist clicks **Create new** on the review screen. The engine opens a pre-fill wizard, queries every registered external authority source the admin has opted into (VIAF, Wikidata, GeoNames, TGN, GND, ISNI, plus any regional gazetteer adapter such as SAGNC), and pre-fills the new-authority form. The archivist may accept, override, or skip each field. On submit the engine inserts a fresh `actor` (or `term`) row, writes one RDF-Star reified assertion per accepted field to the field-provenance graph, and records a `create_new` decision in `ahg_mention_decision`.

This article covers the flow, the source adapters, the settings, and the ISAAR-CPF mandatory fields.

## Flow at a glance

1. Archivist clicks **Create new** on `/admin/authority-resolution/review/{mention}`.
2. `GET /admin/authority-resolution/review/{mention}/create-new` calls `PrefillEngine::prefillForMention($mention)`.
3. The engine walks every adapter that supports the mention's entity type, collecting one normalised result per source (cached or freshly fetched).
4. The engine merges the top hit per source into a single best-guess form payload, tagging each field with its source URI, the lookup source name, and the licence note.
5. The form renders with a small badge next to every pre-filled field showing the source, the licence as a tooltip, and a link to the source URI.
6. Archivist edits, confirms, and submits.
7. `POST /admin/authority-resolution/review/{mention}/create-new` validates, inserts the new record via `AuthorityCreator`, writes one reified turtle assertion per surviving pre-filled field via `FieldProvenanceWriter`, and records a `create_new` decision via `DecisionRecorder::recordCreateNew()` with the new authority id.

## External authority sources

The engine ships with seven adapters. Heratio targets the international GLAM market; SAGNC is one regional adapter among many. All adapters share the same interface and respect the same rate-limit and cache contract.

| Source | Provider | Entity types | Licence | Status |
|---|---|---|---|---|
| **VIAF** | OCLC Virtual Internet Authority File | PERSON, ORG | OCLC ODC-By / CC0 | live |
| **Wikidata** | Wikimedia Foundation | PERSON, ORG, PLACE | CC0 | live |
| **GeoNames** | GeoNames.org | PLACE | CC BY 4.0 (free tier 1000 req/hr/user; requires `username`) | live |
| **TGN** | Getty Thesaurus of Geographic Names | PLACE | ODC-By 1.0 + attribution to Getty | live |
| **GND** | Deutsche Nationalbibliothek | PERSON, ORG, PLACE | CC0 | live |
| **ISNI** | ISO 27729 / OCLC | PERSON, ORG | Free for non-commercial; commercial needs licence | live |
| **SAGNC** | South African Geographical Names Council | PLACE | Crown copyright RSA | stub |

The endpoint for each adapter and the precedence ordering are documented in the settings page at `/admin/authority-resolution/settings/lookup`.

### Default posture: every source OFF

External HTTP fires **only when** `lookup.<src>.enabled = 1`. The default posture for a fresh install is every adapter off. The admin opts in per deployment, per source. This is a privacy-by-default stance: no archivist click leaks to a third-party API until the admin says so.

### What each adapter returns

`LookupAdapterInterface::search()` returns a normalised array:

```
[
    'source'        => 'viaf',
    'authority_id'  => 'https://viaf.org/viaf/123456789',
    'display_name'  => 'Mzilikazi, ca. 1790-1868',
    'dates'         => ['birth' => '1790', 'death' => '1868'],
    'places'        => ['Zululand', 'Matabeleland'],
    'identifiers'   => ['viaf' => '123456789', 'wikidata' => 'Q1234567'],
    'raw_payload'   => [ ... adapter-specific ... ],
    'license_note'  => 'OCLC ODC-By',
]
```

`PrefillEngine` walks the precedence list (`lookup.precedence`) field by field. For each form field it asks adapter 1 first; if it has a value, use it and record the source URI; otherwise fall through to adapter 2; and so on. The chosen source URI is recorded **per field** in the field-provenance graph.

## Settings keys

All under `setting_group = 'authority_resolution_lookup'`. Per source:

- `lookup.<src>.enabled` (bool, default `0`)
- `lookup.<src>.rate_limit` (int, calls per minute)
- `lookup.<src>.cache_ttl` (int, seconds; cache row in `ahg_authority_lookup_cache`)
- `lookup.<src>.license_note` (string)
- `lookup.<src>.license_url` (string)

GeoNames also has `lookup.geonames.username` (string, default `demo`).

Cross-source:

- `lookup.precedence` (JSON array; default `["viaf","wikidata","geonames","tgn","gnd","isni","sagnc"]`)
- `lookup.http_timeout` (int seconds; default `8`)
- `lookup.field_provenance_graph_uri` (string; default `urn:heratio:auth-res:graph:field-provenance`)

The admin settings page is at `/admin/authority-resolution/settings/lookup`.

## ISAAR-CPF mandatory fields (PERSON / ORG)

For person and organisation records the form enforces three mandatory fields, both client-side (HTML `required`) and server-side (`AuthorityCreator::assertIsaarCpf`):

- `authorized_form_of_name`
- `dates_of_existence`
- `history`

These are the minimum the ICA ISAAR-CPF standard expects on a corporate-body, person, or family record. If a pre-fill candidate is missing one of these, the form will not submit until you supply a value.

## ISDF fields (PLACE)

For place records only `name` is mandatory. Latitude and longitude are optional, but if you supply one you must supply both - the validator rejects half-coordinates.

## Cache and rate limit

External lookups are cached in `ahg_authority_lookup_cache` (one row per `(source, entity_type, query_text)` with a UNIQUE key). The eviction policy is lazy: `PrefillEngine` checks `retrieved_at + ttl_seconds` on read; if stale, it refetches and UPSERTs.

The rate-limit ledger is in-process (token bucket, resets on php-fpm worker restart). For strict cross-process limits switch to a Redis-backed sliding window; that path is documented separately for operators.

## Failures degrade, never block

External-service flake degrades to "no pre-fill from that source", not an HTTP 500 on the form. Failures are logged via `Log::warning`; they never bubble to the controller. A VIAF outage means the Wikidata column still fills in - the archivist sees fewer source badges but the form still loads.

## Field provenance: one triple per accepted field

Each pre-filled field that survives to submission becomes one reified turtle assertion in the `urn:heratio:auth-res:graph:field-provenance` named graph. Example:

```
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

This is a separate graph from the decisions graph so SPARQL queries can target one or the other without bleed.

### Override is auditable

When you override a pre-filled value, the engine writes the override with `ahg:lookupSource = "archivist_override"` and also keeps the original value and original source:

```
<< ahg:actor/901999 ric:hasName "Mzilikazi kaMashobane" >>
    prov:wasDerivedFrom    ahg:user/1 ;
    ahg:lookupSource       "archivist_override" ;
    ahg:originalValue      "Moselekatse" ;
    ahg:originalSource     "viaf" ;
    ahg:fromDecision       ahg:decision/43 .
```

A hand-typed field with no pre-fill candidate is recorded with `ahg:lookupSource = "manual"`.

### Why this matters

The new authority record is **reproducible**. Every field carries `prov:retrievedAt` and `prov:wasDerivedFrom` with a stable identifier. You can re-query VIAF later at the recorded timestamp, compare, and detect upstream drift. This is the audit story that lets a digital authority record survive a freedom-of-information challenge.

## Where the new record lives

A new PERSON or ORG record is inserted via the Qubit class-table-inheritance pattern: a row in `actor`, then i18n rows for every language with content (at minimum the deployment's default culture). A new PLACE record is inserted into `term` in the place taxonomy, plus `term_i18n` rows.

The new authority id is wired back into the decision row's `chosen_authority_id` column. The mention transitions to `state = new_record_created`. The originating `ahg_ner_entity.linked_actor_id` is updated to point at the new authority (back-compat with the discovery pipeline).

## Adding a regional adapter

A new market (Brazil's IBGE, Australia's gazetteer, Aotearoa's authority files, ...) implements `LookupAdapterInterface` and registers with `AhgAuthorityResolutionServiceProvider`. The pattern:

1. Create `src/Services/Lookup/Adapters/<Name>Adapter.php` extending `AbstractLookupAdapter` (for rate-limit + cache wiring).
2. Register the singleton in `AhgAuthorityResolutionServiceProvider::register()`.
3. Add to the `PrefillEngine` adapter list (same provider).
4. Seed default `ahg_settings` rows in `database/seed_lookup_settings.sql`.
5. Document the licence and the endpoint in the settings page.

The point is that Heratio is jurisdiction-neutral. SAGNC is included alongside the international sources as one regional option. Any other market may add their own adapter; the precedence ordering is purely a setting.

## Caveats

- **SAGNC is a stub**. The South African Geographical Names Council does not yet publish an open API. The `SagncAdapter` exists so the provenance graph URIs and the UI copy line up; in production it will wire to a periodic scrape of the public gazetteer, cached like every other source. Do not treat SAGNC as the canonical source for non-SA places.
- **Stub adapters** (TGN, GND, ISNI in older deployments; SAGNC in all deployments) are registered but return `[]` until a stable endpoint is wired. Enabling them in settings is harmless.
- **Licence note** is surfaced as a tooltip on the source badge next to each pre-filled field. Click the tooltip for the full text and the `license_url`.

## Related

- "AHG Authority Resolution - User Guide" - the five-outcome decision tree and where Create new sits in the flow.
- "Authority Resolution - Review Screen Reference" - the screen the Create new button lives on.
- "Authority Resolution - Provenance Model" - the field-provenance graph in detail, with SPARQL recipes.
