# AHG Authority Resolution — Decision Provenance to Fuseki (Task 8)

## Summary

Every authority-resolution decision (link / link_different / create_new / park / reject) is written as an **RDF-Star reified assertion** to Apache Jena Fuseki. The same shape is used on both AtoM Heratio (`/usr/share/nginx/archive`) and Laravel Heratio (`/usr/share/nginx/heratio`); the two codebases write to **different named graphs inside the same Fuseki dataset** so federation queries can `UNION` across both with one SPARQL call.

This is Task 8 of the Authority Resolution Engine build. Built 2026-05-19.

## Fuseki environment on this host

- Fuseki runs in Docker (container name: `fuseki`, image `stain/jena-fuseki` v5.1.0).
- Single dataset: **`/openric-model`** (the OpenRiC content dataset).
- Authority-resolution decisions live in **named graphs within that dataset**, not in a separate dataset — keeps the OpenRiC content cleanly partitioned by graph URI while avoiding a second dataset to configure.
- Admin user: `admin`. Password sourced from the container's `ADMIN_PASSWORD` env var (Docker entrypoint sets it on first start). Persisted to `ahg_settings.fuseki_password` (sensitive=1) on both DBs.

## Named graph URIs

| Codebase | Graph URI | Setting key |
|---|---|---|
| Laravel Heratio | `urn:heratio:auth-res:graph:decisions` | `authority_resolution.decisions_graph_uri` in `heratio.ahg_settings` |
| AtoM Heratio | `urn:atom:auth-res:graph:decisions` | `authority_resolution.decisions_graph_uri` in `archive.ahg_settings` |

Default constants live at:
- Laravel: `AhgAuthorityResolution\Services\DecisionProvenanceWriter::DEFAULT_GRAPH_URI`
- AtoM: `AtomFramework\Services\AuthorityResolution\DecisionProvenanceWriter::DEFAULT_GRAPH_URI`

## Settings keys (on both `heratio` and `archive` DBs)

```
fuseki_endpoint                           = http://localhost:3030/openric-model
fuseki_update_endpoint                    = http://localhost:3030/openric-model/update
fuseki_username                           = admin
fuseki_password                           = <sensitive; sync'd from Docker ADMIN_PASSWORD>
authority_resolution.decisions_graph_uri  = urn:heratio:auth-res:graph:decisions  (or urn:atom:… on archive)
```

## RDF-Star shape

```sparql
PREFIX prov:     <http://www.w3.org/ns/prov#>
PREFIX auth_res: <https://heratio.theahg.co.za/ontology/auth-res#>
PREFIX xsd:      <http://www.w3.org/2001/XMLSchema#>

INSERT DATA {
  GRAPH <urn:heratio:auth-res:graph:decisions> {
    << <https://heratio.theahg.co.za/auth-res/mention/998>
       auth_res:linkedTo
       <https://heratio.theahg.co.za/actor/1427> >>
        prov:wasAttributedTo   <https://heratio.theahg.co.za/user/701> ;
        prov:generatedAtTime   "2026-05-19T08:40:14Z"^^xsd:dateTime ;
        auth_res:decisionType  "link" ;
        auth_res:mentionValue  "Edward" ;
        auth_res:mentionEntityType  "PERSON" ;
        auth_res:originalSystemConfidence  "0.95"^^xsd:decimal ;
        auth_res:hadCandidate  <…/auth-res/candidate/101>, <…/auth-res/candidate/102> .

    <https://heratio.theahg.co.za/auth-res/candidate/101>
        auth_res:rank             "1"^^xsd:integer ;
        auth_res:displayName      "Nelson Mandela" ;
        auth_res:source           "mysql_actor" ;
        auth_res:nameSimilarity   "0.95"^^xsd:decimal .
  }
}
```

The reified assertion (`<< s p o >>`) is the **decision itself**; PROV-O + `auth_res:` predicates annotate it. Candidate details sit as top-level triples for SPARQL-queryability (don't nest them inside the reifier).

## Code map

| Codebase | Path |
|---|---|
| Laravel | `packages/ahg-authority-resolution/src/Services/DecisionProvenanceWriter.php` |
| Laravel | `packages/ahg-authority-resolution/src/Console/Commands/WriteProvenanceCommand.php` |
| Laravel client | `packages/ahg-ric/src/Services/SparqlUpdateService.php` (reused, not new) |
| AtoM | `atom-ahg-plugins/ahgAuthorityResolutionPlugin/lib/Services/DecisionProvenanceWriter.php` |
| AtoM | `atom-ahg-plugins/ahgAuthorityResolutionPlugin/lib/Services/FusekiUpdateService.php` |
| AtoM | `atom-ahg-plugins/ahgAuthorityResolutionPlugin/lib/task/authResWriteProvenanceTask.class.php` |

AtoM-side `FusekiUpdateService` is a thin (~50 line) curl-based SPARQL UPDATE client that reuses the existing `ahg_settings` Fuseki keys. It lives in this plugin rather than refactoring `ahgRicExplorerPlugin`'s scattered Fuseki access — fits the "no new framework client" intent of the brief while avoiding a touch on a locked plugin.

## CLI commands

```bash
# Laravel:
sudo -u www-data php artisan auth-res:write-provenance <decision_id>
sudo -u www-data php artisan auth-res:write-provenance --simulate-link=<mention_id> --show

# AtoM (Symfony 1.4):
cd /usr/share/nginx/archive
sudo -u www-data php symfony auth-res:write-provenance <decision_id>
sudo -u www-data php symfony auth-res:write-provenance --simulate-link=<mention_id> --show
```

`--simulate-link` is a Task 8 demo affordance — creates a mock `ahg_mention_decision` row pointing at a similar-named actor. Use until Task 5 (review UI) ships and real decisions start landing.

## SPARQL verification queries

**Count decisions per graph:**
```sparql
SELECT ?graph (COUNT(?reified) AS ?decisions)
WHERE {
  GRAPH ?graph {
    ?reified prov:wasAttributedTo ?u ; auth_res:decisionType ?dt .
  }
  FILTER(?graph IN (<urn:heratio:auth-res:graph:decisions>, <urn:atom:auth-res:graph:decisions>))
}
GROUP BY ?graph
```

**Cross-codebase reified-assertion union (federation query):**
```sparql
SELECT ?graph ?subj ?pred ?obj ?decisionType ?confidence
WHERE {
  { GRAPH <urn:heratio:auth-res:graph:decisions> { << ?subj ?pred ?obj >> auth_res:decisionType ?decisionType ; auth_res:originalSystemConfidence ?confidence . BIND(<urn:heratio:auth-res:graph:decisions> AS ?graph) } }
  UNION
  { GRAPH <urn:atom:auth-res:graph:decisions>    { << ?subj ?pred ?obj >> auth_res:decisionType ?decisionType ; auth_res:originalSystemConfidence ?confidence . BIND(<urn:atom:auth-res:graph:decisions> AS ?graph) } }
}
```

## Wrinkles encountered during the build

- **`@prefix` vs `PREFIX`.** Turtle's `@prefix prov: <…> .` syntax is illegal inside SPARQL `INSERT DATA { … }`. Use SPARQL's `PREFIX prov: <…>` (no `@`, no trailing dot) **outside** the wrapper. SparqlUpdateService's `insertRdfStar()` only wraps in `INSERT DATA`; for prefixed bodies use its `executeUpdate()` and emit `PREFIX` lines yourself.
- **Symfony 1.4 doesn't autoload PSR-4 plugin classes.** The AtoM-side task must `require_once` our service files explicitly before `use` statements.
- **Single Fuseki dataset.** Both `/heratio` and `/ric` are configured as defaults in the existing settings but neither dataset exists on this Fuseki — only `/openric-model` does. Decisions go into named graphs inside `/openric-model`; if you later split into separate datasets the writer settings need updating only on the `fuseki_endpoint` + `fuseki_update_endpoint` keys.
- **UTF-8 substring snapping.** Task 2's surrounding-text window snaps to UTF-8 character boundaries to avoid invalid byte sequences. Mention if applying any other text-slicing to Fuseki literals.

## Future work (not Task 8)

- `hadCandidate` triples are empty in current demos because Task 3 (candidate generation) hasn't run yet. Once Task 3 lands, real `ahg_mention_candidate.id` values flow through `candidates_visible_snapshot` → writer emits candidate-set triples + per-candidate detail blocks.
- `evidence_snapshot` triples are empty until Task 4 (evidence assembly + scoring).
- Active learning / NER feedback (Task 9) consumes the rejection decisions but doesn't write back to Fuseki — that stays in MySQL.
- If a separate `/auth-res-decisions` Fuseki dataset is wanted, add it via Fuseki's `$/datasets` admin endpoint, then update `fuseki_endpoint` + `fuseki_update_endpoint` settings on both codebases.
