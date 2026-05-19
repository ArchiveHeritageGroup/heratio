# Authority Resolution Task 6 - Create-new sub-workflow (AtoM-side)

Task 6 of the AHG Authority Resolution Engine, on the AtoM Heratio side.
This document captures the AtoM-specific implementation: Qubit class-table
inheritance, Symfony 1.4 action wiring, BS5 templates, the seven external
lookup adapters, and the per-field provenance graph in Fuseki.

Sibling spec for the Laravel side: see the AhgAuthorityResolution package
under `/usr/share/nginx/heratio/packages/ahg-authority-resolution/` whose
contracts these files mirror.

## Where everything lives

Plugin root: `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/`.

Task 6 additions:

| File | Purpose |
|---|---|
| `lib/Services/AuthorityCreator.php` | Capsule inserts for actor (PERSON/ORG) and term (PLACE) via Qubit CTI |
| `lib/Services/FieldProvenanceWriter.php` | Reified RDF-Star per-field assertions written to Fuseki named graph |
| `modules/authorityResolution/actions/actions.class.php` | Adds `executeCreateNew` / `executeCreateNewSubmit` / `executeLookupSettings` / `executeLookupSettingsSave` |
| `modules/authorityResolution/templates/createNewSuccess.php` | Pre-fill form (PERSON / ORG / PLACE variants) |
| `modules/authorityResolution/templates/_prefillField.php` | Partial: one form field + provenance badge + hidden `_provenance[...]` inputs |
| `modules/authorityResolution/templates/lookupSettingsSuccess.php` | Admin settings table for the seven sources + precedence + GeoNames username |
| `config/ahgAuthorityResolutionPluginConfiguration.class.php` | Adds four new routes |

Already in place (Task 5 + earlier Task 6 lookup infrastructure):

- `lib/Services/Lookup/PrefillEngine.php` (consumed by `executeCreateNew`)
- `lib/Services/Lookup/Adapters/{ViafAdapter, WikidataAdapter, GeoNamesAdapter, TgnAdapter, GndAdapter, IsniAdapter, SagncAdapter}.php`
- `lib/Services/Lookup/AbstractLookupAdapter.php` + `LookupAdapterInterface.php`
- `lib/Services/DecisionRecorder.php` (consumed for the `create_new` decision row)
- `lib/Services/FusekiUpdateService.php` (consumed by FieldProvenanceWriter)
- `database/seed_lookup_settings.sql` (37 settings rows)

## Qubit CTI inserts

PERSON / ORG: object -> actor -> actor_i18n -> slug.
PLACE       : object -> term  -> term_i18n  -> slug.

Constants used (mirror of `QubitActor` / `QubitTerm` model constants - we
hand-code the values to avoid a runtime dependency on the SF1.4 model layer
from a Capsule transaction):

- `actor.entity_type_id` = 132 (PERSON) or 131 (CORPORATE_BODY)
- `actor.parent_id`      defaults to 3   (QubitActor::ROOT_ID)
- `term.taxonomy_id`     = 42  (places taxonomy)
- `term.parent_id`       defaults to 110 (QubitTerm::ROOT_ID)
- `actor.source_standard` defaults to `ISAAR-CPF`
- `actor_i18n.culture` and `term_i18n.culture` default to `en`

All inserts wrap in a single `DB::connection()->transaction(...)` so a
partial failure leaves no orphaned object / slug rows.

ISAAR-CPF mandatory fields enforced for persons / orgs:

- `authorized_form_of_name`
- `dates_of_existence`
- `history`

For places: name is mandatory. If one of (latitude, longitude) is provided
the other must be; both stored as a `lat,lng` pair on
`term_i18n.description` (matches the parser used by the review screen's
`resolvePlaceCoord()` helper).

Slug generation: hand-rolled `slugify(name)` (iconv + lowercase +
`preg_replace('/[^a-z0-9]+/i','-')`), length-capped at 240 chars, with a
numeric suffix loop on collision (and a random hex fallback after 1000
attempts). The slug table has a UNIQUE index so a collision at insert time
still surfaces as a SQL error.

## SF1.4 action structure

`require_once` is mandatory for every consumer of the namespaced classes
because no PSR-4 autoload covers the plugin. Centralised in the four service
loader methods on `authorityResolutionActions`:

- `decisionRecorder()`
- `actorAdapter()` / `termAdapter()` (Task 5)
- `authorityCreator()`
- `fieldProvenanceWriter()`
- `prefillEngine()` (instantiates all seven adapters)

ACL: every Task 6 action calls `requireEditor()` or, for the settings page,
`requireAuth()` + `isAdministrator()`. Settings save is admin-only.

## Routes (added in `config/ahgAuthorityResolutionPluginConfiguration.class.php`)

```
GET  /admin/authorityResolution/:id/create-new          -> createNew
POST /admin/authorityResolution/:id/create-new-submit   -> createNewSubmit
GET  /admin/authorityResolution/settings/lookup         -> lookupSettings
POST /admin/authorityResolution/settings/lookup         -> lookupSettingsSave
```

Route names: `ar_auth_res_create_new`, `ar_auth_res_create_new_submit`,
`ar_auth_res_lookup_settings`, `ar_auth_res_lookup_settings_save`.

The Task 5 "Create new" button in `reviewSuccess.php` (previously a POST
form against the legacy `create_new` stub action) is now a plain anchor
link to `@ar_auth_res_create_new` - the rest of the Task 5 markup is
unchanged.

## BS5 form patterns

`_prefillField.php` is the only partial. Each call passes:
`name`, `label`, `value`, `prov`, `type` (text | textarea | number),
`rows` (textarea only), `help`.

When `prov` is non-null:
- A coloured badge appears next to the label showing the source, license,
  and retrieval timestamp.
- Hidden `_provenance[<field>][source|uri|license|license_url|at]` inputs
  are emitted alongside the visible field so the submit handler can
  replay the original attribution into Fuseki.

PERSON / ORG renders these fields (every one carries a provenance badge if
the PrefillEngine filled it): `authorized_form_of_name`, `dates_of_existence`,
`history`, `places`, `mandates`, `functions`, `legal_status`, plus a small
"descriptive_standard" + "source_culture" pair (not provenance-tracked).

PLACE renders: `name`, `latitude`, `longitude`.

## Source adapter inventory (Task 6 baseline)

| Source | Adapter | API | License | Status |
|---|---|---|---|---|
| `viaf`     | `ViafAdapter`     | VIAF AutoSuggest               | CC0-1.0 | live |
| `wikidata` | `WikidataAdapter` | wbsearchentities + wbgetentities | CC0-1.0 | live |
| `geonames` | `GeoNamesAdapter` | searchJSON                     | CC BY 4.0 | live (requires username) |
| `tgn`      | `TgnAdapter`      | Getty TGN SPARQL               | ODbL 1.0  | stub |
| `gnd`      | `GndAdapter`      | lobid (DNB GND)                | CC0-1.0   | stub |
| `isni`     | `IsniAdapter`     | ISNI SRU                       | ISNI ToU  | stub |
| `sagnc`    | `SagncAdapter`    | South African Geographical Names Council | TBD | stub |

## Settings keys (37 rows, seeded by `database/seed_lookup_settings.sql`)

```
authority_resolution.lookup.<source>.enabled        boolean   default '0'
authority_resolution.lookup.<source>.rate_limit     integer
authority_resolution.lookup.<source>.cache_ttl      integer
authority_resolution.lookup.<source>.license_note   string
authority_resolution.lookup.<source>.license_url    string
authority_resolution.lookup.geonames.username       string   (extra: required for GeoNames)
authority_resolution.lookup.precedence              json     '["viaf","wikidata",...]'
```

All `enabled` rows default to `'0'`. Heratio never makes outbound HTTP
calls until an admin opts in via `/admin/authorityResolution/settings/lookup`.

Verified: with all sources disabled, `PrefillEngine::prefill(138)` returns
`merged_fields` populated only from the mention itself (`authorized_form_of_name`
with `source=mention`) and any mention-context derivations (e.g. `places`
with `source=mention_context`).

## Field-provenance graph

Distinct from the Task 5 decisions graph:

- decisions:        `urn:atom:auth-res:graph:decisions`
- field-provenance: `urn:atom:auth-res:graph:field-provenance` (Task 6)

Graph URI is overridable via the optional `authority_resolution.field_provenance_graph_uri`
setting; otherwise the class constant `FieldProvenanceWriter::DEFAULT_GRAPH_URI`
is used. Base URI for actor / term subjects comes from `ahg_settings.site_base_url`
(falls back to `https://psis.theahg.co.za`).

### Sample emitted turtle (one field)

```turtle
PREFIX prov: <http://www.w3.org/ns/prov#>
PREFIX auth_res: <https://psis.theahg.co.za/ontology/auth-res#>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

INSERT DATA {
  GRAPH <urn:atom:auth-res:graph:field-provenance> {
    << <https://psis.theahg.co.za/actor/912515> auth_res:hasField "authorized_form_of_name" >>
        auth_res:fieldValue "Frederick Douglass, 1818-1895" ;
        prov:wasDerivedFrom <https://viaf.org/viaf/10088> ;
        prov:generatedAtTime "2026-05-19T17:42:31Z"^^xsd:dateTime ;
        auth_res:source "viaf" ;
        auth_res:licence "CC0-1.0" ;
        auth_res:licenceUrl <https://creativecommons.org/publicdomain/zero/1.0/> .
  }
}
```

### Sample audit / FOIA query

Count every field-provenance triple ever asserted against a particular actor:

```sparql
SELECT (COUNT(*) AS ?n)
WHERE {
  GRAPH <urn:atom:auth-res:graph:field-provenance> {
    << <https://psis.theahg.co.za/actor/912515> ?p ?o >> ?pp ?oo .
  }
}
```

List which fields were sourced from which adapter:

```sparql
PREFIX auth_res: <https://psis.theahg.co.za/ontology/auth-res#>
SELECT ?field ?source ?sourceUri ?at
WHERE {
  GRAPH <urn:atom:auth-res:graph:field-provenance> {
    << <https://psis.theahg.co.za/actor/912515> auth_res:hasField ?field >>
        auth_res:source ?source ;
        prov:generatedAtTime ?at .
    OPTIONAL {
      << <https://psis.theahg.co.za/actor/912515> auth_res:hasField ?field >>
          prov:wasDerivedFrom ?sourceUri .
    }
  }
}
```

## End-to-end create flow (what `executeCreateNewSubmit` actually does)

1. Validate POST + load mention.
2. Normalise `entity_type` (GPE / LOC / ISAD_PLACE -> PLACE).
3. Collect form fields via `collectCreateForm()`.
4. Re-hydrate per-field provenance from hidden inputs via `collectPrefillProvenance()`.
5. `AuthorityCreator::createPerson/Org/Place(...)` -> new authority id.
6. `FieldProvenanceWriter::writeForCreation(...)` -> reified RDF-Star to Fuseki.
7. `DecisionRecorder::record(mention_id, DECISION_CREATE_NEW, user_id, ['authority_id'=>new_id])`.
8. Flash + redirect to next pending mention (or queue index).

DecisionRecorder takes care of:
- inserting the `ahg_mention_decision` row with frozen candidate slate +
  evidence snapshot,
- advancing `ahg_mention.state` to `new_record_created`,
- firing `DecisionProvenanceWriter::write()` for the decision graph
  (synchronous, non-fatal on failure).

## Smoke results (mention 138 = "Frederick Douglass", PERSON)

- Default-OFF: 0 external hits, merged_fields keys = `authorized_form_of_name(mention), places(mention_context)`.
- VIAF + Wikidata: 4 + 10 hits. Top VIAF result (VIAF id 10088) wins precedence: "Frederick Douglass, 1818-1895".
- Degrade (VIAF off, Wikidata on): name source falls back to `wikidata`; merged_fields still populated.
- Full create: new `actor.id = 912515`, slug `frederick-douglass-1818-1895`, 8 reified field assertions emitted -> 38 metadata triples in `urn:atom:auth-res:graph:field-provenance` (Fuseki HTTP 204 on INSERT, HTTP 200 + count 38 on SELECT verification). Decision row id 5 written with state `new_record_created`.

## Not done in Task 6

- Per-source rate limiting (the `rate_limit` setting is read but not yet enforced; AbstractLookupAdapter relies on cache + caller frequency for now).
- Federation of the field-provenance graph onto the Laravel-side Heratio - both codebases emit into separate named graphs (`urn:atom:...` vs `urn:heratio:...`); a future cross-graph SPARQL union query is the integration point.
- A "preview" step on the create form (currently goes straight from prefill to commit).
