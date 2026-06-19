# GraphRAG grounding: wiring KM into the RiC graph (#1320)

How KM (or any agent) grounds answers in the RiC knowledge graph so it
disambiguates entities instead of guessing - "vectors retrieve, the ontology
graph disambiguates."

## Heratio side (shipped)
- `GraphGroundingService` (`ahg-semantic-search`) - read-only.
  - `groundEntity(string $iri)` -> a disambiguation pack (type, label, dates,
    properties, relations, provenance) read from Fuseki via `SparqlQueryService`.
  - `groundQuery(string $query, int $max = 5)` -> resolves a free-text query to
    graph entities (name/label match) and returns `{query, entities[], grounding_text}`.
  - `groundingText(packs)` -> a compact, prompt-ready block.
- **Endpoint:** `GET /api/ric/ground?q=<query>&max=<n>` (public, throttled, no
  CSRF - server-to-server). Returns:
  ```json
  {
    "query": "Egypt",
    "entities": [ { "iri": "...", "types": ["Place"], "label": "Egypt", "relations": ["name -> Egypt"], ... } ],
    "grounding_text": "Authoritative facts from the archive's knowledge graph (...):\n- Egypt (Place) [urn:ahg:ric:place:912150]; ..."
  }
  ```

## KM side (to wire - KM is the separate Flask app at km.theahg.co.za)
In KM's answer pipeline, before building the LLM prompt:
1. `GET https://<heratio-host>/api/ric/ground?q=<user question>` (server-to-server).
2. If `grounding_text` is non-empty, **prepend it to the grounding context block**
   (above the vector-retrieved chunks), unchanged.
3. Build + send the prompt through the gateway (`ai.theahg.co.za`) as normal.

Pilot scope (#1320): start with the creators/agents + places slice; A/B the
answers with vs without the `grounding_text` block and measure the
hallucination/error-rate delta (increment 3).

## Two-layer IRI note
The grounding packs use the internal live-graph IRIs (`urn:ahg:ric:<type>:<id>`).
The public, dereferenceable IRIs (`https://ric.theahg.co.za/ric/<type>/<slug>`)
are the export identity (governance pin section 2). The semantic-layer join is
`object_id -> urn:ahg:ric:<type>:<id>`.

## KM-side injection (built, NOT yet deployed)
`/opt/ai/km/app.py` (the separate Flask app; not git-tracked, backed up as
`app.py.bak-graphground-1320`):
- `fetch_graph_grounding(question)` - GETs `/api/ric/ground`; **no-op by default**,
  enabled per-request via `?graph_ground=1` / JSON `graph_ground`, else by env
  `KM_GRAPH_GROUNDING=1`; **fail-open** (any error -> '' and the answer proceeds).
- `build_prompt()` prepends the returned facts as an "AUTHORITATIVE
  KNOWLEDGE-GRAPH FACTS" block above the retrieved chunks.
- Compiles (`py_compile` clean); the endpoint it calls is verified live.
- **Deploy = `sudo systemctl restart ahg-km.service`** (operator action - it's
  the live shared KM service). The change is a no-op until then and until a
  request sets `graph_ground=1`, so production is unaffected.

## Running the eval (post-deploy)
```
KM_BASE=https://km.theahg.co.za KM_API_KEY=<key> \
  python3 packages/ahg-semantic-search/tools/graphrag_eval.py
```
Runs the pilot question set through `/api/ask` with `graph_ground` 0 vs 1 and
reports the authoritative-fact-present / abstain deltas + side-by-side answers.

## Increment status (#1320)
- [x] 1. Graph disambiguation lookup (`GraphGroundingService`) - v1.154.30.
- [x] 2. Query resolver + KM-callable `/api/ric/ground` endpoint.
- [x] 3a. KM prompt injection (app.py, flag-guarded + fail-open; backed up).
- [x] 3b. Eval harness (`packages/ahg-semantic-search/tools/graphrag_eval.py`).
- [ ] 3c. Measured delta - deploy (restart ahg-km) then run the eval.
