# Authority Resolution - Provenance Model

Every decision and every accepted authority-creation pre-fill emits RDF-Star provenance to the host's Fuseki dataset. The provenance model is the answer to "who decided what, when, on what evidence" - and on `create_new` decisions, "where did each value on this new authority come from". This article documents the named-graph layout, the triple shape per decision type, the field-provenance triples per accepted pre-fill, and the SPARQL recipes that read the provenance back.

## Why RDF-Star

A plain RDF triple says "actor 901999 has end date 1868". That is true but not auditable: who said so, when, on what evidence. RDF-Star wraps the triple itself as a subject so we can attach metadata to the **claim** without polluting the canonical subject graph.

The same `ric:hasName` triple therefore lives in three places, each consumed by a different reader:

1. The canonical RiC graph (clean, no provenance) - what other systems consume.
2. The field-provenance graph (with `wasDerivedFrom`, `acceptedByUser`, `retrievedAt`) - what data-quality reports query.
3. The decisions graph (with `supportedBy` -> `evidenceSnapshot`) - what an audit defends.

## Named graphs (not separate datasets)

Provenance lives in a single Fuseki dataset (`/openric-model`). Isolation is by **named graph URI**, not by dataset. Four graphs are used:

| Graph URI | Holds |
|---|---|
| `urn:heratio:auth-res:graph:decisions` | Heratio decision provenance |
| `urn:atom:auth-res:graph:decisions` | AtoM decision provenance |
| `urn:heratio:auth-res:graph:field-provenance` | Heratio per-field authority-creation provenance |
| `urn:atom:auth-res:graph:field-provenance` | AtoM per-field authority-creation provenance |

The codebase prefix (`heratio:` vs `atom:`) lets one Fuseki instance hold both codebases' provenance without bleed. Cross-codebase SPARQL queries `UNION` across the two graphs.

### Override the URIs

Graph URIs are not hardcoded. They read from `ahg_settings`:

```sql
UPDATE ahg_settings
   SET setting_value = 'urn:custom:auth-res:graph:decisions'
 WHERE setting_key   = 'authority_resolution.decisions_graph_uri';
```

Use this for staging vs. production isolation on a shared Fuseki.

## Prefixes

```turtle
@prefix prov:    <http://www.w3.org/ns/prov#> .
@prefix ahg:     <https://theahg.co.za/ns/auth-res#> .
@prefix ric:     <https://www.ica.org/standards/RiC/ontology#> .
@prefix xsd:     <http://www.w3.org/2001/XMLSchema#> .
@prefix dcterms: <http://purl.org/dc/terms/> .
```

## Decisions graph

URI: `urn:heratio:auth-res:graph:decisions`.

Holds one `prov:Activity` per row in `ahg_mention_decision`.

### Triples emitted per decision type

**link / link_different:**

```turtle
ahg:decision/42 a prov:Activity ;
    ahg:decisionType         "link" ;          # or "link_different"
    prov:startedAtTime       "2026-05-19T09:13:44+02:00"^^xsd:dateTime ;
    prov:wasAssociatedWith   ahg:user/1 ;
    ahg:onMention            ahg:mention/24 ;
    ahg:chosenCandidate      ahg:candidate/77 ;
    ahg:chosenAuthority      ahg:actor/901990 ;
    ahg:topSystemScore       "0.7421"^^xsd:decimal ;
    ahg:codebase             "heratio" .

<< ahg:mention/24  ahg:resolvedTo  ahg:actor/901990 >>
    ahg:supportedBy           ahg:decision/42 ;
    ahg:evidenceSnapshot      "[...JSON...]" ;
    ahg:candidatesVisible     "[...JSON...]" .
```

For `link_different`, `ahg:topSystemScore` is the **rank-1** candidate's composite score, not the picked candidate's. This is intentional: it preserves "what the system thought was best, that the archivist overrode".

**create_new:**

```turtle
ahg:decision/43 a prov:Activity ;
    ahg:decisionType         "create_new" ;
    prov:startedAtTime       "2026-05-19T09:14:01+02:00"^^xsd:dateTime ;
    prov:wasAssociatedWith   ahg:user/1 ;
    ahg:onMention            ahg:mention/25 ;
    ahg:newAuthorityCreated  ahg:actor/901999 ;
    ahg:topSystemScore       "0.4012"^^xsd:decimal ;
    ahg:codebase             "heratio" .
```

No `<< ... ahg:resolvedTo ... >>` assertion: the mention did not resolve to any existing authority. The new authority's per-field provenance lives in the field-provenance graph.

**park:**

```turtle
ahg:decision/44 a prov:Activity ;
    ahg:decisionType         "park" ;
    prov:startedAtTime       "2026-05-19T09:14:33+02:00"^^xsd:dateTime ;
    prov:wasAssociatedWith   ahg:user/1 ;
    ahg:onMention            ahg:mention/26 ;
    ahg:topSystemScore       "0.6203"^^xsd:decimal ;
    ahg:parkReason           "Awaiting MARC import for early Zulu kings." ;
    ahg:codebase             "heratio" .
```

**reject:**

```turtle
ahg:decision/45 a prov:Activity ;
    ahg:decisionType         "reject" ;
    prov:startedAtTime       "2026-05-19T09:15:11+02:00"^^xsd:dateTime ;
    prov:wasAssociatedWith   ahg:user/1 ;
    ahg:onMention            ahg:mention/27 ;
    ahg:rejectionReason      "Horse name, not a place." ;
    ahg:codebase             "heratio" .
```

No `<< ... ahg:resolvedTo ... >>` assertion: the mention was not real. The rejection also writes a row to `ahg_ner_feedback` (audit elsewhere).

### `hadCandidate` triples

The current writer does not emit per-candidate triples ("decision X had candidate Y on its list") because the full candidate list is already preserved on the decision row in `candidates_visible_snapshot` as JSON. Treat the snapshot as the source of truth for what the archivist saw. The `hadCandidate` predicate is reserved for a future evidence-scoring graph; the field is presently empty.

## Field-provenance graph

URI: `urn:heratio:auth-res:graph:field-provenance`.

Emitted only on `decision_type = create_new`. One reified assertion per field on the new authority record, capturing exactly which external source supplied which value and whether the archivist overrode it.

### Per-field triples

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

  # Hand-typed field, no pre-fill candidate offered.
  << ahg:actor/901999  ric:hasBiographicalNote  "Founder of the Ndebele Kingdom" >>
      prov:wasDerivedFrom    ahg:user/1 ;
      ahg:lookupSource       "manual" ;
      ahg:fromDecision       ahg:decision/43 ;
      ahg:retrievedAt        "2026-05-19T09:13:30+02:00"^^xsd:dateTime .
}
```

### `ahg:lookupSource` values

| Value | Meaning |
|---|---|
| `viaf` | From VIAF adapter |
| `wikidata` | From Wikidata adapter |
| `geonames` | From GeoNames adapter |
| `tgn` | From Getty TGN adapter |
| `gnd` | From GND adapter |
| `isni` | From ISNI adapter |
| `sagnc` | From SAGNC adapter |
| `archivist_override` | Adapter offered a value, archivist replaced it |
| `manual` | No adapter offered a value; archivist typed it |

`archivist_override` rows also carry `ahg:originalValue` and `ahg:originalSource` so the override is auditable: "the VIAF spelling was 'Moselekatse', the archivist preferred 'Mzilikazi kaMashobane'".

## SPARQL recipes

Run against the host's Fuseki at the configured endpoint, or directly via `AhgRic\Services\SparqlQueryService::executeQuery()`.

### 1. Triple counts per graph

```sparql
SELECT ?g (COUNT(*) AS ?triples) WHERE {
  GRAPH ?g { ?s ?p ?o }
  FILTER ( STRSTARTS(STR(?g), "urn:heratio:auth-res:") ||
           STRSTARTS(STR(?g), "urn:atom:auth-res:") )
}
GROUP BY ?g
ORDER BY ?g
```

Should match what `auth-res:status` reports.

### 2. Decisions made today

```sparql
PREFIX prov: <http://www.w3.org/ns/prov#>
PREFIX ahg:  <https://theahg.co.za/ns/auth-res#>
PREFIX xsd:  <http://www.w3.org/2001/XMLSchema#>

SELECT ?d ?type ?archivist ?mention WHERE {
  GRAPH <urn:heratio:auth-res:graph:decisions> {
    ?d a prov:Activity ;
       ahg:decisionType        ?type ;
       prov:wasAssociatedWith  ?archivist ;
       ahg:onMention           ?mention ;
       prov:startedAtTime      ?when .
    FILTER ( ?when >= "2026-05-19T00:00:00"^^xsd:dateTime )
  }
}
ORDER BY DESC(?when)
```

### 3. Per-archivist decision count

```sparql
SELECT ?archivist
       (COUNT(?d) AS ?total)
       (SUM(IF(?type = "link",            1, 0)) AS ?linked)
       (SUM(IF(?type = "link_different",  1, 0)) AS ?overrode)
       (SUM(IF(?type = "create_new",      1, 0)) AS ?created)
       (SUM(IF(?type = "park",            1, 0)) AS ?parked)
       (SUM(IF(?type = "reject",          1, 0)) AS ?rejected)
WHERE {
  GRAPH <urn:heratio:auth-res:graph:decisions> {
    ?d ahg:decisionType        ?type ;
       prov:wasAssociatedWith  ?archivist .
  }
}
GROUP BY ?archivist
ORDER BY DESC(?total)
```

Useful for balancing review queues.

### 4. Where did this actor's birth date come from?

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

### 5. Adapter coverage report

```sparql
SELECT ?lookupSource (COUNT(*) AS ?n) WHERE {
  GRAPH <urn:heratio:auth-res:graph:field-provenance> {
    << ?s ?p ?o >> ahg:lookupSource ?lookupSource .
  }
}
GROUP BY ?lookupSource
ORDER BY DESC(?n)
```

Tells you which adapters are actually contributing values.

### 6. Override rate per source

```sparql
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

A high override rate from a given source means its pre-fill quality is poor for this archivist's domain. Use it to tune `lookup.precedence` or drop a low-quality adapter.

### 7. What did the archivist see at decision time?

```sparql
SELECT ?evidence ?candidates WHERE {
  GRAPH <urn:heratio:auth-res:graph:decisions> {
    << ?m ahg:resolvedTo ?a >>
        ahg:supportedBy         ahg:decision/42 ;
        ahg:evidenceSnapshot    ?evidence ;
        ahg:candidatesVisible   ?candidates .
  }
}
```

The two literals are JSON strings. Parse client-side to render the exact review-screen state as of `prov:startedAtTime`.

### 8. Decision history for a single mention

```sparql
SELECT ?d ?type ?archivist ?when ?evidence WHERE {
  GRAPH <urn:heratio:auth-res:graph:decisions> {
    ?d ahg:onMention            ahg:mention/24 ;
       ahg:decisionType         ?type ;
       prov:wasAssociatedWith   ?archivist ;
       prov:startedAtTime       ?when .
    OPTIONAL {
      << ?m ahg:resolvedTo ?a >>
          ahg:supportedBy        ?d ;
          ahg:evidenceSnapshot   ?evidence .
    }
  }
}
ORDER BY DESC(?when)
```

Returns the full decision history for one mention. Useful when a correction was recorded and you want to see "original decision -> later correction".

### 9. Park reasons in the last week

```sparql
SELECT ?d ?mention ?reason ?when WHERE {
  GRAPH <urn:heratio:auth-res:graph:decisions> {
    ?d ahg:decisionType    "park" ;
       ahg:onMention       ?mention ;
       ahg:parkReason      ?reason ;
       prov:startedAtTime  ?when .
    FILTER ( ?when >= "2026-05-12T00:00:00"^^xsd:dateTime )
  }
}
ORDER BY DESC(?when)
```

Useful for spotting systemic issues ("dozens of mentions parked with the same reason text" suggests a pending import).

## Reproducibility

Because every value carries `retrievedAt` and `prov:wasDerivedFrom` with a stable identifier (for example a VIAF URI), the new-authority record is reproducible: you can re-query VIAF at the recorded `retrievedAt`, compare, and detect upstream drift. The decision row's frozen `evidence_snapshot` plus the field-provenance graph together let you defend a decision under audit.

## Per-graph cleanup

Decisions provenance is **append-only** by policy. We never delete a decision triple. To revise a decision, record a new one; both are visible in the audit.

To wipe a graph entirely (staging only):

```sparql
DROP GRAPH <urn:heratio:auth-res:graph:decisions>
```

Do not do this in production. There are no backups inside Fuseki itself; the dataset has a host-level snapshot but rolling back loses every provenance write since the last snapshot.

## Performance notes

- Reified-triple queries (`<< ?s ?p ?o >> ahg:supportedBy ?d`) are slower than plain triple queries because the engine walks the reified-statement index. Materialise heavy queries via `SparqlQueryService` with `useCache=true` to keep the result in Laravel's cache for the configured TTL.
- `COUNT(*)` over a large graph is cheap on Fuseki because of the built-in triple counter. Use it freely.

## Related

- "Authority Resolution - Creating a New Authority Record" - the path that emits field-provenance triples.
- "AHG Authority Resolution - User Guide" - the five-outcome decision tree from the archivist's perspective.
- "Authority Resolution - CLI Commands" - `auth-res:status` reports per-graph triple counts.
