# Field provenance graph

URI: `urn:heratio:auth-res:graph:field-provenance` (Heratio) or
`urn:atom:auth-res:graph:field-provenance` (AtoM).

Emitted only on `decision_type=create_new`. One reified assertion per
**field on the new authority record**, capturing exactly which external
source supplied which value and whether the archivist overrode it.

## Why a separate graph?

Decisions are about *which authority was chosen*. Field provenance is
about *where each value on a newly-created authority came from*. The two
have different consumers:

- The audit UI shows decisions (what happened).
- The "data quality" / "show me Wikidata-sourced birth years" reports
  query field provenance (what each value came from).

Keeping them in separate graphs makes each query smaller and lets us
drop / rebuild field provenance without losing decision history.

## Triples emitted per field

```turtle
GRAPH <urn:heratio:auth-res:graph:field-provenance> {

  # Pre-filled field, accepted as-is.
  << ahg:actor/901999  ric:hasBeginningDate  "1790"^^xsd:gYear >>
      prov:wasDerivedFrom    <https://viaf.org/viaf/123456789> ;
      ahg:lookupSource       "viaf" ;
      ahg:retrievedAt        "2026-05-19T09:12:01+02:00"^^xsd:dateTime ;
      ahg:acceptedByUser     ahg:user/1 ;
      ahg:fromDecision       ahg:decision/43 .

  # Pre-filled field, overridden by the archivist.
  << ahg:actor/901999  ric:hasName  "Mzilikazi kaMashobane" >>
      prov:wasDerivedFrom    ahg:user/1 ;
      ahg:lookupSource       "archivist_override" ;
      ahg:originalValue      "Moselekatse" ;
      ahg:originalSource     "viaf" ;
      ahg:fromDecision       ahg:decision/43 ;
      ahg:retrievedAt        "2026-05-19T09:13:11+02:00"^^xsd:dateTime .

  # Hand-typed field (no pre-fill candidate was offered).
  << ahg:actor/901999  ric:hasBiographicalNote  "Founder of the Ndebele Kingdom" >>
      prov:wasDerivedFrom    ahg:user/1 ;
      ahg:lookupSource       "manual" ;
      ahg:fromDecision       ahg:decision/43 ;
      ahg:retrievedAt        "2026-05-19T09:13:30+02:00"^^xsd:dateTime .
}
```

## ahg:lookupSource values

| value                  | meaning                                                |
|------------------------|--------------------------------------------------------|
| `viaf`                 | from VIAF adapter (`PrefillEngine`)                    |
| `wikidata`             | from Wikidata adapter                                  |
| `geonames`             | from GeoNames adapter                                  |
| `tgn`                  | from Getty TGN adapter                                 |
| `gnd`                  | from GND adapter                                       |
| `isni`                 | from ISNI adapter                                      |
| `sagnc`                | from SAGNC adapter                                     |
| `archivist_override`   | adapter offered a value, archivist replaced it         |
| `manual`               | no adapter offered a value; archivist typed it         |

`archivist_override` rows also carry `ahg:originalValue` +
`ahg:originalSource` so the rejection is auditable - "the VIAF spelling
was 'Moselekatse', the archivist preferred 'Mzilikazi kaMashobane'".

## Writer location

| Codebase | Writer class                                                                   |
|----------|--------------------------------------------------------------------------------|
| Heratio  | `AhgAuthorityResolution\Services\FieldProvenanceWriter`                         |
| AtoM     | `ahgFieldProvenanceWriter` (`atom-ahg-plugins/.../lib/Services/`)               |

Both implement:

```php
public function writeForNewAuthority(int $decisionId, int $authorityId, array $fieldDecisions): int;
```

`fieldDecisions` is a list of `['field' => 'ric:hasName', 'value' => ...,
'source' => 'viaf', 'original_value' => ..., 'original_source' => ...]`
items. Returns the triple count emitted.

## Useful queries

### Where did this actor's birth date come from?

```sparql
PREFIX ric:  <https://www.ica.org/standards/RiC/ontology#>
PREFIX prov: <http://www.w3.org/ns/prov#>
PREFIX ahg:  <https://theahg.co.za/ns/auth-res#>

SELECT ?value ?source ?retrievedAt ?archivist WHERE {
  GRAPH <urn:heratio:auth-res:graph:field-provenance> {
    << ahg:actor/901999 ric:hasBeginningDate ?value >>
        prov:wasDerivedFrom  ?source ;
        ahg:lookupSource     ?lookupSource ;
        ahg:retrievedAt      ?retrievedAt ;
        ahg:acceptedByUser   ?archivist .
  }
}
```

### Adapter coverage report

```sparql
PREFIX ahg: <https://theahg.co.za/ns/auth-res#>

SELECT ?lookupSource (COUNT(*) AS ?n) WHERE {
  GRAPH <urn:heratio:auth-res:graph:field-provenance> {
    << ?s ?p ?o >> ahg:lookupSource ?lookupSource .
  }
}
GROUP BY ?lookupSource
ORDER BY DESC(?n)
```

Tells you which adapters are actually contributing values.

### Override rate per source

```sparql
PREFIX ahg: <https://theahg.co.za/ns/auth-res#>

SELECT ?originalSource (COUNT(*) AS ?n) WHERE {
  GRAPH <urn:heratio:auth-res:graph:field-provenance> {
    << ?s ?p ?o >>
        ahg:lookupSource     "archivist_override" ;
        ahg:originalSource   ?originalSource .
  }
}
GROUP BY ?originalSource
ORDER BY DESC(?n)
```

A high override rate from a given source means its pre-fill quality is
poor for this archivist's domain.

## Reproducibility

Because every value carries `retrievedAt` and `prov:wasDerivedFrom` with
a stable identifier (e.g. a VIAF URI), the new-authority record is
**reproducible**: you can re-query VIAF at the recorded `retrievedAt`,
compare, and detect upstream drift.
