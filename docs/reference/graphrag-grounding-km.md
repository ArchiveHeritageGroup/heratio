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

## Increment status (#1320)
- [x] 1. Graph disambiguation lookup (`GraphGroundingService`) - v1.154.30.
- [x] 2. Query resolver + KM-callable `/api/ric/ground` endpoint.
- [ ] 3. KM prompt injection (KM-side) + eval set + measured hallucination delta.
