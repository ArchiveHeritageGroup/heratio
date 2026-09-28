# QLever vs Fuseki benchmark - RiC graph, 28 Sep 2026

Benchmark behind heratio#1518. It ran against a copy of the live `ric` dataset: 17,966,644 quads (17.9M in the default graph plus 3,353 named graphs), loaded into two separate localhost-only test containers. They were Fuseki 5.1.0 (same image and `-Xmx8G` as production) and QLever (Docker image built 27 Sep 2026). The 3,350 RDF-star lines from ahg-provenance-ai were left out of both, because QLever cannot parse them (heratio#1517).

## Files

- `workload.py` - the 18 queries. Each names the Heratio code it came from (SparqlQueryService, RelationshipService, RicController, FusekiAgentAdapter, authority-resolution) or marks itself as an analytics query. The sample IRIs are real entities from the export.
- `run_bench.py` - checks each query's results agree across engines, then does 1 cold run and 10 warm runs per query, then 30 s of mixed load at 1, 4 and 16 clients. Standard library only.
- `update_test.py` - the write shapes Heratio sends (INSERT DATA into a named graph, DELETE WHERE, DROP GRAPH), timed. It has a `verify` phase to run after restarting both engines.
- `results.json` - raw output of `run_bench.py`.
- `update_test.log` - output of both `update_test.py` phases.

## Rerun

1. Export the dataset (read-only): `curl -H 'Accept: application/n-quads' http://<fuseki>/ric -o ric.nq`
2. Remove the RDF-star lines until heratio#1517 lands: `grep -v '<< ' ric.nq > ric-nostar.nq`
3. Load a test Fuseki: `java -Xmx8G -cp fuseki-server.jar tdb2.tdbloader --loc <dir> ric-nostar.nq`. Serve it with `--update` on a spare port, and set `FUSEKI_BASE` to a writable directory.
4. Build the QLever index (`adfreiburg/qlever`): `qlever-index -i ric -F nq -f ric-nostar.nq -s settings.json -m 10G`
5. Serve QLever with the result cache off, so warm runs measure real work: `qlever-server -i ric -p <port> -j 16 -m 8G -c 0GB -e 0GB -a <token> --persist-updates`
6. Run the benchmark: `python3 run_bench.py <fuseki-query-url> <qlever-url> results.json`
7. Run the write test: `python3 update_test.py <fuseki-query> <fuseki-update> <qlever-url> <token> write`, restart both engines, then run it again with `verify`.

Never point these scripts at the production Fuseki. The write test inserts and deletes data.

## Reading the result differences

QLever's default graph is the union of all named graphs; Fuseki's is not. Seven of the 18 queries differ for that reason alone (vocabulary-graph types and counts, and different rows under LIMIT without ORDER BY). Neither engine returned a wrong answer.
