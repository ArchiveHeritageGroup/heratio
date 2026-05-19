# Decisions graph

URI: `urn:heratio:auth-res:graph:decisions` (Heratio) or
`urn:atom:auth-res:graph:decisions` (AtoM).

Holds one `prov:Activity` per row in `ahg_mention_decision`. The graph is
the canonical answer to "who decided what, when, on what evidence".

## Triples emitted per decision type

### link / link_different

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

For `link_different`, `ahg:topSystemScore` is the **rank-1** candidate's
composite_score, **not** the picked candidate's. This is intentional: it
preserves "what the system thought was best, that the archivist
overrode".

### create_new

```turtle
ahg:decision/43 a prov:Activity ;
    ahg:decisionType         "create_new" ;
    prov:startedAtTime       "2026-05-19T09:14:01+02:00"^^xsd:dateTime ;
    prov:wasAssociatedWith   ahg:user/1 ;
    ahg:onMention            ahg:mention/25 ;
    ahg:newAuthorityCreated  ahg:actor/901999 ;
    ahg:topSystemScore       "0.4012"^^xsd:decimal ;
    ahg:codebase             "heratio" .

# No << ... ahg:resolvedTo ... >> assertion - the mention did NOT resolve
# to any existing authority. The new authority's field-level provenance
# is in the field-provenance graph (see field-provenance.md).
```

### park

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

### reject

```turtle
ahg:decision/45 a prov:Activity ;
    ahg:decisionType         "reject" ;
    prov:startedAtTime       "2026-05-19T09:15:11+02:00"^^xsd:dateTime ;
    prov:wasAssociatedWith   ahg:user/1 ;
    ahg:onMention            ahg:mention/27 ;
    ahg:rejectionReason      "Horse name, not a place." ;
    ahg:codebase             "heratio" .

# << ... ahg:resolvedTo ... >> is NOT emitted; the mention was not real.
# The rejection ALSO writes a row to ahg_ner_feedback (audit elsewhere).
```

## Common queries

### Decisions made today

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

### Override rate per archivist (link vs. link_different)

```sparql
PREFIX ahg: <https://theahg.co.za/ns/auth-res#>

SELECT ?archivist
       (SUM(IF(?type = "link", 1, 0)) AS ?confirmed)
       (SUM(IF(?type = "link_different", 1, 0)) AS ?overridden)
WHERE {
  GRAPH <urn:heratio:auth-res:graph:decisions> {
    ?d ahg:decisionType        ?type ;
       ahg:onMention            ?m ;
       <http://www.w3.org/ns/prov#wasAssociatedWith> ?archivist .
    FILTER (?type IN ("link", "link_different"))
  }
}
GROUP BY ?archivist
ORDER BY DESC(?overridden)
```

A high override rate is a quality signal for the scoring weights, not
the archivist - if the system keeps surfacing the wrong top candidate,
the evaluator weights need a look.

### "What evidence did this decision rest on?"

```sparql
PREFIX ahg: <https://theahg.co.za/ns/auth-res#>

SELECT ?evidence ?candidates WHERE {
  GRAPH <urn:heratio:auth-res:graph:decisions> {
    << ahg:mention/24  ahg:resolvedTo  ?actor >>
        ahg:supportedBy        ahg:decision/42 ;
        ahg:evidenceSnapshot   ?evidence ;
        ahg:candidatesVisible  ?candidates .
  }
}
```

Returns the frozen JSON snapshots from the decision row. Use this to
defend the decision later - "this is exactly what the archivist saw on
screen on 2026-05-19".

## Per-graph cleanup

Decisions provenance is **append-only** by policy. We never delete a
decision triple. If a decision needs to be revised, record a new
decision; both are visible in the audit.

To wipe the graph entirely (staging only):

```sparql
DROP GRAPH <urn:heratio:auth-res:graph:decisions>
```

Do NOT do this in production. There are no backups inside Fuseki itself;
the dataset has a host-level snapshot but rolling back loses every
provenance write since the last snapshot.
