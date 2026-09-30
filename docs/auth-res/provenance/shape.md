# Provenance shape

Every decision and every accepted authority-creation pre-fill emits
provenance to the host's single Fuseki dataset (`/openric-model`).
Isolation is by **named graph URI**, not by dataset. Two graphs are
used per codebase:

| Graph URI                                       | Holds                                      |
|------------------------------------------------|--------------------------------------------|
| `urn:heratio:auth-res:graph:decisions`         | Heratio decision provenance                |
| `urn:atom:auth-res:graph:decisions`            | AtoM decision provenance                   |
| `urn:heratio:auth-res:graph:field-provenance`  | Heratio per-field authority-creation prov  |
| `urn:atom:auth-res:graph:field-provenance`     | AtoM per-field authority-creation prov     |

## Prefixes

```turtle
@prefix rdf:     <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix prov:    <http://www.w3.org/ns/prov#> .
@prefix ahg:     <https://theahg.co.za/ns/auth-res#> .
@prefix ric:     <https://www.ica.org/standards/RiC/ontology#> .
@prefix xsd:     <http://www.w3.org/2001/XMLSchema#> .
@prefix dcterms: <http://purl.org/dc/terms/> .
```

## Decision provenance shape

For every row in `ahg_mention_decision`, the engine writes:

```turtle
GRAPH <urn:heratio:auth-res:graph:decisions> {

  ahg:decision/42
      a                          prov:Activity ;
      dcterms:identifier          "42"^^xsd:integer ;
      ahg:decisionType            "link" ;
      prov:startedAtTime          "2026-05-19T09:13:44+02:00"^^xsd:dateTime ;
      prov:wasAssociatedWith      ahg:user/1 ;
      ahg:onMention               ahg:mention/24 ;
      ahg:chosenAuthority         ahg:actor/901990 ;
      ahg:topSystemScore          "0.7421"^^xsd:decimal ;
      ahg:codebase                "heratio" .

  # Reification: the actual claim, as an rdf:Statement node, and the
  # meta-claim about which evidence supported it.
  <urn:heratio:auth-res:statement:HASH> a rdf:Statement ;
      rdf:subject ahg:mention/24 ;
      rdf:predicate ahg:resolvedTo ;
      rdf:object ahg:actor/901990 ;
      ahg:supportedBy             ahg:decision/42 ;
      ahg:evidenceSnapshot        "[{\"dim\":\"temporal\",\"signal\":\"match\",...}]" ;
      ahg:candidatesVisible       "[{\"rank\":1,\"id\":901990,...}]" .
}
```

## Field-provenance shape

For every `decision_type=create_new` decision, the engine writes one
reified assertion per pre-filled field that the archivist accepted:

```turtle
GRAPH <urn:heratio:auth-res:graph:field-provenance> {

  <urn:heratio:auth-res:statement:HASH> a rdf:Statement ;
      rdf:subject ahg:actor/901990 ;
      rdf:predicate ric:hasBeginningDate ;
      rdf:object "1790"^^xsd:gYear ;
      prov:wasDerivedFrom    <https://viaf.org/viaf/123456789> ;
      ahg:lookupSource       "viaf" ;
      ahg:retrievedAt        "2026-05-19T09:12:01+02:00"^^xsd:dateTime ;
      ahg:acceptedByUser     ahg:user/1 ;
      ahg:fromDecision       ahg:decision/42 .

  <urn:heratio:auth-res:statement:HASH> a rdf:Statement ;
      rdf:subject ahg:actor/901990 ;
      rdf:predicate ric:hasEndDate ;
      rdf:object "1868"^^xsd:gYear ;
      prov:wasDerivedFrom    <https://www.wikidata.org/entity/Q1234567> ;
      ahg:lookupSource       "wikidata" ;
      ahg:retrievedAt        "2026-05-19T09:12:03+02:00"^^xsd:dateTime ;
      ahg:acceptedByUser     ahg:user/1 ;
      ahg:fromDecision       ahg:decision/42 .

  # An archivist override is captured as wasDerivedFrom an internal user URI.
  <urn:heratio:auth-res:statement:HASH> a rdf:Statement ;
      rdf:subject ahg:actor/901990 ;
      rdf:predicate ric:hasName ;
      rdf:object "Mzilikazi kaMashobane" ;
      prov:wasDerivedFrom    ahg:user/1 ;
      ahg:lookupSource       "archivist_override" ;
      ahg:originalValue      "Moselekatse" ;
      ahg:originalSource     "viaf" ;
      ahg:fromDecision       ahg:decision/42 .
}
```

## Why a statement node?

A plain RDF triple says "actor 901990 has end date 1868". That's true
but not auditable: who said so, when, on what evidence? A reified
`rdf:Statement` node stands for the triple itself, so we can attach
metadata to the *claim* without polluting the canonical subject graph.

This used to be an RDF-star quoted triple (`<< s p o >>`). heratio#1517
moved it to plain RDF 1.1 reification because RDF-star ties the record to
stores that support it - QLever rejects `<<` outright - and RDF 1.2
triple terms use a different syntax again. `HASH` in the examples is the
`sha1` of the graph IRI and the N-Triples forms of s, p and o, so the
same claim in the same graph always gets the same node (see
`AhgCore\Support\ReifiedStatement`). Older graphs are converted with
`php artisan ahg:provenance-ai:migrate-star --apply`.

This means the same `ric:hasName` triple can appear in:

1. The canonical RiC graph (clean, no provenance).
2. The field-provenance graph (with `wasDerivedFrom`, `acceptedByUser`,
   `retrievedAt`).
3. The decisions graph (with `supportedBy` -> `evidenceSnapshot`).

Each consumer queries the graph it cares about.

## Codebase isolation

The codebase prefix in the graph URI (`heratio:` vs `atom:`) lets a
single Fuseki instance hold both codebases' provenance without bleed:

- Heratio's `DecisionProvenanceWriter` writes only to the `heratio:`
  graphs.
- AtoM's `ahgDecisionProvenanceWriter` writes only to the `atom:`
  graphs.
- Cross-codebase SPARQL queries do `FROM NAMED <urn:heratio:...> FROM
  NAMED <urn:atom:...>` and `UNION` the bindings.

See [SPARQL recipes](sparql.md) for cross-codebase query examples.

## Override

Graph URIs are not hardcoded in the writer - they read from
`ahg_settings`:

```sql
UPDATE ahg_settings
   SET setting_value = 'urn:custom:auth-res:graph:decisions'
 WHERE setting_key   = 'authority_resolution.decisions_graph_uri';
```

Use this for staging vs. production isolation on a shared Fuseki.
