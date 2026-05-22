# Loading RiC instance data into the /openric-model Fuseki dataset

The Fuseki dataset `/openric-model` originally held only the **RiC-O ontology**
(243 `owl:Class`, 485 `owl:ObjectProperty`, ~16,500 triples) and **zero RiC
instances**. The authority-resolution engine's Fuseki candidate adapters
(`FusekiAgentAdapter` / `FusekiPlaceAdapter`) were wired correctly but always
returned `[]` because there was nothing to match. Issue #139 fixes that.

## The `ahg:ric:fuseki-load` command

A console command in the `ahg-ric` package
(`AhgRic\Console\Commands\FusekiInstanceLoadCommand`) bulk-publishes RiC
instances into `/openric-model`:

```
sudo -u www-data php artisan ahg:ric:fuseki-load
```

Run it as the web user (not root) so it leaves no root-owned cache/log files.

It loads:

- **Agents** - every `actor` row with an `authorized_form_of_name` (in the
  actor's `source_culture`) becomes a `rico:Agent`. The subclass comes from
  `actor.entity_type_id`: `rico:Person`, `rico:CorporateBody` or `rico:Family`;
  an actor with no entity type falls back to plain `rico:Agent` (still matched
  by the adapter). URI: `urn:ahg:ric:agent:{actorId}`.
- **Places** - every `term` in the place taxonomy (`taxonomy_id = 42`) with a
  name becomes a `rico:Place`. URI: `urn:ahg:ric:place:{termId}`. (The RiC-native
  `ric_place` table is not loaded - real place data lives in the term taxonomy.)

Each instance carries a `rico:name` literal - one of the predicates the
adapters' name-matching UNION looks for (`rico:name`, `rico:hasOrHadName` ->
`rico:textualValue`, `rdfs:label`, `skos:prefLabel`).

### Options

- `--agents-only` / `--places-only` - load just one kind.
- `--limit=N` - cap rows per kind (testing).
- `--batch=N` - entities per Fuseki update request (default 200).
- `--dry-run` - build the SPARQL update and report counts without writing.

## Why the default graph

A live probe established: the ontology sits in the **default graph**, and the
dataset does **not** union named graphs into GRAPH-less queries. The adapters
query `?s a rico:Agent` / `?s a rico:Place` **without a GRAPH clause**, so they
only see the default graph. The loader therefore writes instances into the
default graph - named-graph placement would be invisible to the adapters.

## Idempotent

The loader is a re-runnable sync. Per batch it issues
`DELETE { ?s ?p ?o } WHERE { VALUES ?s { ... } ?s ?p ?o }` for the batch's
URNs, then `INSERT DATA { ... }`. Re-running updates instances in place - the
instance count stays stable. Re-run it after a bulk actor/term import to pick
up new records. Only `urn:ahg:ric:agent:*` / `urn:ahg:ric:place:*` subjects are
touched; the RiC-O ontology is never modified.

## Result

On a first load this install published **569 instances** (388 agents - 132
CorporateBody, 186 Person, 4 Family, 66 untyped Agent - and 181 places). Both
adapter query shapes now return candidates, so the Fuseki candidate source
contributes alongside the MySQL adapters in authority resolution.

## Notes

- The `SparqlQueryService::executeQuery()` defect referenced as a blocker in
  issue #139 was already resolved under #138 - the Python branch is now gated
  behind `config('heratio.ric_sparql_via_python', false)` and the endpoint
  defaults to `/openric-model`. The HTTP query path works; no further fix was
  needed.
- One shared Fuseki instance serves both the heratio `ahg-authority-resolution`
  package and the AtoM `ahgAuthorityResolutionPlugin`, so a single load serves
  both codebases' adapters - there is no AtoM-side loader.
