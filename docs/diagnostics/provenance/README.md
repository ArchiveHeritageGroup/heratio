# Provenance diagnostic SPARQL queries

Issue #61 Phase 4b. Read-only queries you can run against the live Fuseki store at the configured `fuseki_endpoint` (`http://192.168.0.112:3030/ric` on this deployment) to audit AI inference provenance coverage and find gaps between schema and reality.

Each query is annotated with what it answers and how to interpret the result. Run via `curl` or the Fuseki web UI:

```bash
ENDPOINT="http://192.168.0.112:3030/ric/sparql"
curl -sk -G --data-urlencode "query@coverage-by-service.rq" -H 'Accept: application/sparql-results+json' "$ENDPOINT" | jq
```

## Files

- `coverage-by-service.rq` - count of inferences per AI service (NER / HTR / TRANSLATION / LLM / DONUT). Reveals which services are landing provenance and which aren't.
- `recent-inferences-without-override.rq` - inferences in the last N days that have NO override recorded. The "AI suggested, no human review yet" backlog.
- `confidence-distribution.rq` - histogram of confidence scores per service. Used for drift detection - sudden shifts in distribution suggest model degradation.
- `lineage-by-record.rq` - given a record URI, return every inference + override + reviewer chain that touched it. The FOIA shape.
- `unprovenanced-triples.rq` - assertions in the store that look like AI output (heuristic: short literals on subject/access_point predicates) but lack a `prov:wasGeneratedBy` back-pointer. The discipline-gap radar.

## Cross-reference: same answers via SQL

For ops dashboards that need fast counts without round-tripping SPARQL, the same shapes are answerable from `ahg_ai_inference` and `ahg_ai_override` MySQL tables. The dual-store architecture (ADR-0002 sec 1) ensures both sides converge once the Fuseki replay job catches up. Use SPARQL when:

- Asking semantic questions ("which inferences targeted RiC-O fields and were overridden by the same reviewer twice?")
- Producing FOIA-defensible export ("here's the canonical provenance chain in PROV-O")

Use SQL when:

- Counting / paginating / filtering by user
- Building review queue UIs
- Checking `fuseki_pending` (rows where `fuseki_graph_uri IS NULL`)
