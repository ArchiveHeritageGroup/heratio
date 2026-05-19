# SPARQL recipes

Useful queries against the four provenance graphs. Run against the host's
Fuseki at `https://fuseki.theahg.co.za/openric-model/sparql` (or directly
via `AhgRic\Services\SparqlQueryService::executeQuery()`).

## Setup

```turtle
PREFIX prov:    <http://www.w3.org/ns/prov#>
PREFIX ahg:     <https://theahg.co.za/ns/auth-res#>
PREFIX ric:     <https://www.ica.org/standards/RiC/ontology#>
PREFIX xsd:     <http://www.w3.org/2001/XMLSchema#>
PREFIX dcterms: <http://purl.org/dc/terms/>
```

Graphs:

- `urn:heratio:auth-res:graph:decisions`
- `urn:atom:auth-res:graph:decisions`
- `urn:heratio:auth-res:graph:field-provenance`
- `urn:atom:auth-res:graph:field-provenance`

## 1. Triple counts per graph

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

## 2. Cross-codebase decision UNION

```sparql
SELECT ?d ?codebase ?type ?archivist ?when WHERE {
  {
    GRAPH <urn:heratio:auth-res:graph:decisions> {
      ?d a prov:Activity ;
         ahg:decisionType        ?type ;
         prov:wasAssociatedWith  ?archivist ;
         prov:startedAtTime      ?when ;
         ahg:codebase            ?codebase .
    }
  } UNION {
    GRAPH <urn:atom:auth-res:graph:decisions> {
      ?d a prov:Activity ;
         ahg:decisionType        ?type ;
         prov:wasAssociatedWith  ?archivist ;
         prov:startedAtTime      ?when ;
         ahg:codebase            ?codebase .
    }
  }
}
ORDER BY DESC(?when)
LIMIT 50
```

Useful during the dual-codebase transition: see decisions from both
deployments in one stream.

## 3. Per-archivist decision count

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

Quick productivity / workload view. Use this to balance review queues.

## 4. "Show me every Wikidata-sourced birth date"

```sparql
SELECT ?actor ?date ?source ?when WHERE {
  GRAPH <urn:heratio:auth-res:graph:field-provenance> {
    << ?actor ric:hasBeginningDate ?date >>
        ahg:lookupSource     "wikidata" ;
        prov:wasDerivedFrom  ?source ;
        ahg:retrievedAt      ?when .
  }
}
ORDER BY DESC(?when)
LIMIT 100
```

For data-quality audits when an upstream source is suspected of drift.

## 5. Decisions made on a specific mention

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

Returns the full decision history for one mention - useful when a
correction was recorded and you want to see "original decision -> later
correction".

## 6. Lookup cache effectiveness

The cache itself is in MySQL, but field provenance lets you sanity-check
which sources actually contributed values:

```sparql
SELECT ?lookupSource (COUNT(*) AS ?n) WHERE {
  GRAPH <urn:heratio:auth-res:graph:field-provenance> {
    << ?s ?p ?o >> ahg:lookupSource ?lookupSource .
  }
}
GROUP BY ?lookupSource
ORDER BY DESC(?n)
```

Cross-check against `php artisan auth-res:cache-stats`. A source with
many cache entries but few provenance entries means archivists are
overriding its values.

## 7. "What did the archivist see at decision time?"

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

The two literals are JSON strings. Parse client-side to render the exact
review-screen state as of `prov:startedAtTime`.

## 8. Override rate per evaluator-supported source

A combined query joining decisions + field provenance:

```sparql
SELECT ?source (COUNT(*) AS ?overrides) WHERE {
  GRAPH <urn:heratio:auth-res:graph:field-provenance> {
    << ?actor ?p ?value >>
        ahg:lookupSource     "archivist_override" ;
        ahg:originalSource   ?source .
  }
}
GROUP BY ?source
ORDER BY DESC(?overrides)
```

Tells you which adapter's pre-fills get overridden most often. Use this
to tune adapter weights or remove a low-quality adapter from
`lookup.precedence`.

## 9. "Park reasons in the last week"

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

Useful for spotting patterns ("dozens of mentions parked with the same
reason text" -> something systemic, e.g. a pending import).

## Performance notes

- Reified-triple queries (`<< ?s ?p ?o >> ahg:supportedBy ?d`) are
  slower than plain triple queries because the engine has to walk the
  reified-statement index. Materialise heavy queries via the
  `SparqlQueryService` cache (`useCache=true`), which keeps the result
  in Laravel's cache for the configured TTL.
- `COUNT(*)` over a large graph is cheap on Fuseki because of the
  built-in triple counter. Use it freely.
