<!--
SPDX-License-Identifier: AGPL-3.0-or-later
SPDX-FileCopyrightText: 2026 Johan Pieterse / The Archive and Heritage Group (Pty) Ltd

This file is part of Heratio.
Heratio is free software: you can redistribute it and/or modify it under the
terms of the GNU Affero General Public License version 3 or (at your option)
any later version. See <https://www.gnu.org/licenses/agpl-3.0.html>.
-->

# GenDSs 2026 paper - operational status snapshot

**Author:** Johan Pieterse, The Archive and Heritage Group (Pty) Ltd
**Date:** 2026-04-28
**Host:** `theahg` (192.168.0.110 / 192.168.0.112), Heratio v1.18.0
**Scope:** read-only inspection of the four-strategy hybrid retrieval pipeline currently deployed alongside an in-flight ANC pilot.

> **Terminology note (carried from the brief).** The paper outline refers to "OpenSearch"; this deployment runs **Elasticsearch 7.17.29** (`http://localhost:9200`, prefix `heratio_`). No OpenSearch fork is present. All ES paragraphs below describe Elasticsearch and the wire protocol used in code is the Elasticsearch JSON REST API.

---

## 1. Plugin and code state

Both a Heratio-native package and the legacy AtoM plugin coexist on this server. They are two different code bases solving the same problem; the AtoM plugin still runs the production ANC pilot site (`psis.theahg.co.za` / `archive` DB), the Heratio package runs against the much smaller `heratio` DB.

### 1.1 Heratio-side package - `packages/ahg-discovery/`

| Item | Value |
|---|---|
| Path | `/usr/share/nginx/heratio/packages/ahg-discovery/` |
| `composer.json` `"name"` | `ahg/discovery` |
| Declared version | none (composer.json has no `version` field) |
| Heratio platform version | **1.18.0** (`/usr/share/nginx/heratio/version.json`) |
| Service provider | `AhgDiscovery\Providers\AhgDiscoveryServiceProvider` |
| Routes file | `packages/ahg-discovery/routes/web.php` (11 routes registered, all confirmed via `php artisan route:list`) |

Registered routes (verbatim from `route:list`):

```
GET   /discovery               ahgdiscovery.index
GET   /discovery/index
GET   /discovery/search        ahgdiscovery.search
GET   /discovery/suggest       ahgdiscovery.suggest
GET   /discovery/popular       ahgdiscovery.popular
POST  /discovery/click         ahgdiscovery.click
GET   /discovery/pageindex     ahgdiscovery.pageindex
GET   /discovery/pageindex/api ahgdiscovery.pageindex.api
POST  /discovery/pageindex/api
GET   /discovery/build         ahgdiscovery.build         (auth)
POST  /discovery/build         ahgdiscovery.build.store   (auth)
```

Source files (every file under `src/`):

| File | Class | Role |
|---|---|---|
| `src/Providers/AhgDiscoveryServiceProvider.php` | `AhgDiscoveryServiceProvider` | Boot hook + idempotent seed of 17 `ahg_settings` defaults |
| `src/Controllers/DiscoveryController.php` | `DiscoveryController` | All 11 routes; **the entire 4-strategy pipeline lives in this one ~1400-line controller**, not in separate strategy classes |
| `src/Services/DiscoveryQueryLogger.php` | `DiscoveryQueryLogger` | Per-query telemetry into `ahg_discovery_log` |
| `src/Services/PageIndexService.php` | `PageIndexService` | LLM tree builder for EAD / PDF / RiC-O |
| `src/Services/OllamaPageIndexClient.php` | `OllamaPageIndexClient` | Ollama HTTP client at `192.168.0.112:11434`, model `llama3.1:8b` |
| `src/Services/Search/SearchStrategyInterface.php` | interface | `name(): string`, `isEnabled(): bool`, `search(string,$ctx): array` |
| `src/Services/Search/VectorSearchStrategy.php` | `VectorSearchStrategy` | Wraps `\AhgSearch\Services\VectorSearchService` |
| `src/Services/Search/ImageSearchStrategy.php` | `ImageSearchStrategy` | CLIP image-similarity (no text-search path) |

The four retrieval strategies on the Heratio side:

| Strategy | Implemented as | Input | Returns |
|---|---|---|---|
| **Keyword** | `DiscoveryController::keywordSearch()` (private) | `expandQuery()` output (keywords + phrases) | `[{object_id, es_score, highlights, slug}]`. **Not Elasticsearch - pure MySQL `LIKE %term%` against `information_object_i18n.title` and `.scope_and_content`** with a hand-rolled relevance score (`title=3, scope=1`). |
| **Entity (NER)** | `DiscoveryController::entitySearch()` | expansion entities + phrases + long keywords | `[{object_id, match_count, entity_types, matched_values}]` from `ahg_ner_entity` table (`LIKE %term%` on `entity_value`). |
| **Hierarchical** | `DiscoveryController::hierarchicalSearch()` | top 20 results from keyword+entity | siblings (limit 5/parent) + children-of-fonds (limit 10) walked on `information_object` `parent_id`. |
| **Vector** | `VectorSearchStrategy` → `\AhgSearch\Services\VectorSearchService` (`packages/ahg-search/src/Services/VectorSearchService.php`) | raw query string | `[{object_id, score, source:"vector", slug, title}]` from Qdrant `/points/search`, embedding via Ollama at `semantic_embedding_url` (default `http://192.168.0.78:11434`). |

A fifth helper `ImageSearchStrategy` exists but its `search()` always returns `[]` for plain text - image search needs `$context['image_path']` or `$context['similar_to_object_id']`. It is not on the text-query path.

### 1.2 AtoM-side legacy plugin - `ahgDiscoveryPlugin`

| Item | Value |
|---|---|
| Path | `/usr/share/nginx/archive/atom-ahg-plugins/ahgDiscoveryPlugin/` |
| `extension.json` `version` | `1.0.0` |
| `atom_plugin` table version | **`0.2.0`** (`SELECT version FROM archive.atom_plugin WHERE name='ahgDiscoveryPlugin'`) |
| `is_enabled` | **1** (enabled) |
| `status` | `enabled` |
| `updated_at` | 2026-02-26 06:54:07 |
| Discovered classes | `KeywordSearchStrategy`, `EntitySearchStrategy`, `VectorSearchStrategy`, `HierarchicalStrategy`, `ImageSearchStrategy`, `ResultMerger`, `ResultEnricher`, `QueryExpander` |

The two extension manifest values disagree (`extension.json: 1.0.0` vs `atom_plugin row: 0.2.0`); I record both, the DB row is authoritative for the runtime.

Other relevant atom-side plugins are also enabled: `ahgAIPlugin v2.1.0`, `ahgSearchPlugin v1.0.0`, `ahgSemanticSearchPlugin v1.0.0`.

### 1.3 Code-architecture deltas worth noting

The **Heratio port is not a faithful translation** of the AtoM plugin:

* AtoM has **`ResultMerger`** with explicit 3-/4-/5-strategy weight tables (constants `WEIGHT_*_3S/_4S/_5S`). The Heratio controller has a hand-rolled `mergeResults()` that supports only keyword + entity + hierarchical, then post-applies **Reciprocal Rank Fusion (RRF, k=60)** for vector results in a separate pass (`rrfBoostWithVector()`). The two do not produce identical ranking.
* AtoM's `QueryExpander` and Heratio's `expandQuery()` are byte-for-byte the same regex / stop-word logic (no LLM expansion). Both touch `ahg_thesaurus_synonym` if present - that table exists in `heratio` but I did not query its size.
* **No LLM is used for query expansion in either implementation.** The only LLM call in this code base is `OllamaPageIndexClient` (PageIndex tree build + node retrieval), not the live `/discovery/search` path.

---

## 2. Corpus and indexes - actual numbers

### 2.1 Fuseki (RiC-O)

* **Local instance:** Apache Jena Fuseki 5.1.0 in Docker container `fuseki` (image `stain/jena-fuseki`), exposed on `0.0.0.0:3030`. Auth: `admin:admin123` (`ADMIN_PASSWORD` in container env).
* **Datasets:** one - `/ric` (services: `query`, `sparql`, `update`, GSP-r, GSP-rw).
* **Endpoint used by code:** `http://192.168.0.112:3030/ric` (`ahg_settings.fuseki_endpoint`); `PageIndexService` and `RicSyncService` POST SPARQL to `/query` or `/sparql`.

`SELECT (COUNT(*) AS ?c) WHERE { ?s ?p ?o }` on the local `/ric` dataset **timed out at 60s** - there is no full-triple count available without a longer-running scan; this is itself a finding (no aggregation index, ~M-scale store on cold cache).

Per-class counts that did complete (each query bounded ≤30 s):

| Class | Local Fuseki `/ric` | Remote `https://ric.theahg.co.za/sparql` |
|---|---:|---:|
| Total triples | **timeout >60 s** | 7,192 |
| `rico:Record` | 648 | - (not queried) |
| `rico:RecordSet` | 56 | - |
| `rico:Place` | 21,272 | - |
| `rico:CorporateBody` | 16 | - |
| `rico:Person` | 26 | - |
| `rico:Agent` | 0 | - |
| `rico:Activity` | 0 | - |
| `rico:Event` | 0 | - |

The `ric.theahg.co.za` endpoint is the **OpenRiC v0.2.0 reference API** (route `/api/ric/v1/sparql`); it is intentionally tiny (proof corpus only, 7,192 triples). Do not conflate it with the local Fuseki, which is the operational store the pageindex code talks to.

Place modelling: `rico:Place → rico:hasPlaceName → [bnode] → rico:textualValue → "..."`. There is no `rdfs:label` on Places, which is why the AtoM plugin's place-lookup queries are slow (see §5).

### 2.2 Qdrant

* **Local instance:** docker container `qdrant` (image `qdrant/qdrant:latest`), `0.0.0.0:6333` (REST) and `:6334` (gRPC). Up 12 days.
* **No remote Qdrant on 192.168.0.78** (Ollama port 11434 also closed - see §3).

| Collection | points_count | indexed_vectors_count | dim | distance | inferred model |
|---|---:|---:|---:|---|---|
| `anc_records` | **454,392** | 449,024 | 384 | Cosine | `all-minilm` (matches default in `VectorSearchService` + 384-dim) |
| `archive_records` | 693 | 0 | 384 | Cosine | `all-minilm` |
| `archive_images` | **279** | 0 | 512 | Cosine | CLIP ViT-B/32 (`clip-vit-b-32` per settings) |
| `km_threads` | 11,451 | 0 | 384 | Cosine | `all-minilm` |
| `km_heratio` | 10,056 | 0 | 384 | Cosine | `all-minilm` |
| `km_qa` | 2,131 | 0 | 384 | Cosine | `all-minilm` |
| `km_ric` | 69 | 0 | 384 | Cosine | `all-minilm` |

**Discrepancy with memory:** the memory snippet records `archive_images ≈ 693 vectors`. The live API reports **279 points** in `archive_images` and **693 points** in `archive_records`. The 693 number is correct but applies to the *text* collection, not the *image* collection. Memory is wrong on this point.

A sample point payload from each:

```json
// anc_records id=450
{"database":"atom","title":"Department of Education","parent_id":1,
 "slug":"department-of-education-1","has_scope":false,"has_transcript":false}

// archive_images id=842
{"database":"archive","object_id":837,"title":"Engelbrecht Family Bible",
 "slug":"engelbrecht-family-bible","mime_type":"image/webp"}
```

Heratio's `VectorSearchService` defaults to `semantic_qdrant_collection = anc_records` and `semantic_embedding_model = all-minilm`. **Neither setting is currently present in `heratio.ahg_settings`**, so the hard-coded defaults are what runs. `ahg_discovery_image_collection = archive_images` is set; image search is wired but the image strategy has no entry on the text path.

### 2.3 Elasticsearch indices

ES 7.17.29 at `http://localhost:9200`, single-node. All four heratio indices exist and are populated with the heratio DB (NOT the larger atom DB):

| Index | docs | size |
|---|---:|---:|
| `heratio_qubitinformationobject` | 455,293 | 221.3 MB |
| `heratio_qubitactor` | 12,545 | 3.0 MB |
| `heratio_qubitterm` | 733 | 527.7 KB |
| `heratio_qubitrepository` | 13 | 42.2 KB |

For comparison the AtoM-side ES indices that the legacy `psis.theahg.co.za` site queries:

| Index | docs |
|---|---:|
| `atom_qubitinformationobject` | 322,068 |
| `atom_qubitactor` | 12,215 |
| `archive_qubitinformationobject` | 918 |

The mapping on `heratio_qubitinformationobject` is the standard AtoM-derived one (`dynamic: strict`, per-language `i18n.{en,fr,es,...}.title/scopeAndContent` text fields with locale analysers, all `copy_to: all`). **This is the index the AtoM plugin's `KeywordSearchStrategy` would target - but Heratio's `DiscoveryController::keywordSearch()` does NOT use Elasticsearch at all; it uses MySQL `LIKE`.** That is a real architectural divergence.

### 2.4 PageIndex tree

* Tables: `ahg_pageindex_tree`, `ahg_pageindex_query_log` exist in both `heratio` and `archive` databases.
* **Row counts: 0** in `heratio.ahg_pageindex_tree`, **0** in `archive.ahg_pageindex_tree`, **0** in `atom.ahg_pageindex_tree`.
* **No PageIndex tree has been built yet on this server.** The `searchAll()` path always returns 0 results regardless of query.

### 2.5 EAD source

There is no `ead`, `ead_*`, or population in `finding_aid` anywhere:

| DB | `finding_aid` rows | `%ead%` tables |
|---|---:|---|
| atom | 0 | none archival |
| heratio | 0 | none archival |
| archive | not checked | not checked |

The matches for `%ead%` (`integrity_dead_letter`, `research_reading_room*`) are unrelated word-substring hits. **There is no EAD source corpus loaded** - PageIndex's `extractEadContent()` reads from `information_object` + `_i18n`, not from EAD XML files.

For context the IO base table:

| DB | `information_object` rows | Published | Draft |
|---|---:|---:|---:|
| atom | 454,393 | 322,067 | 132,326 |
| heratio | 743 | n/a | n/a |
| archive | not counted | - | - |

ANC corpus is in `atom`; Heratio's `heratio` DB holds 743 demo / smoke-test rows.

---

## 3. Local inference (Ollama)

### 3.1 Service status

* `systemctl status ollama` reports **`inactive (dead) since Tue 2026-04-28 11:01:50 SAST`** - i.e. Ollama on 192.168.0.112 stopped this morning, ~3 hours before this report was written. `Loaded` but `disabled`; binary at `/usr/local/bin/ollama`, override config at `/etc/systemd/system/ollama.service.d/override.conf` sets `OLLAMA_HOST=0.0.0.0`, `OLLAMA_KEEP_ALIVE=-1`, `OLLAMA_MAX_LOADED_MODELS=1`, `OLLAMA_NUM_PARALLEL=1`, **`CUDA_VISIBLE_DEVICES=`** (empty - i.e. CPU-only).
* `curl http://192.168.0.112:11434/api/tags` → `HTTP 000` (no listener; ss shows no process on port 11434).
* `curl http://192.168.0.78:11434/api/tags` → **connection refused**. The AI server's Ollama is also down. The brief states 192.168.0.78 hosts CLIP for image embeddings; today it is unreachable.

### 3.2 Models on disk (local 192.168.0.112)

`ls /usr/share/ollama/.ollama/models/manifests/registry.ollama.ai/library/` - **the model the discovery code expects (`llama3.1:8b`) is NOT on disk.** Models actually pulled (~68 GB blob store):

```
llava/7b
mistral/7b
nomic-embed-text/latest
qwen2.5/7b
qwen2.5/14b
qwen2.5/32b
qwen2.5-coder/7b
qwen3/8b
qwen3/32b
```

`OllamaPageIndexClient::__construct()` defaults `model = 'llama3.1:8b'`; if the service is restarted as-is, `getHealth()` will return `model_missing` and PageIndex tree builds and queries will fail with that error. The closest substitute already on disk is `qwen3:8b`.

### 3.3 Live latency probe

Not measurable today - both Ollama instances are down. No `POST /api/generate` was attempted because the connection refuses immediately.

### 3.4 Query expansion path

`grep -rn 'expandQuery' packages/` finds expansion in three places:

| File | Class / method | LLM-backed? |
|---|---|---|
| `packages/ahg-discovery/src/Controllers/DiscoveryController.php:745` | `DiscoveryController::expandQuery()` | **No** - pure deterministic regex + stop words + thesaurus DB |
| `packages/ahg-semantic-search/src/Services/SemanticSearchService.php:270` | `SemanticSearchService::expandQuery()` | "Ported from AtoM ahgSemanticSearchPlugin ThesaurusService" - thesaurus only |
| `packages/ahg-display/src/Controllers/DisplayController.php:200` | calls `SemanticSearchService::expandQuery()` | thesaurus only |

The Heratio Discovery query-expansion prompt is **not an LLM prompt at all**; it is the regex pipeline:

```
extractDateRange()    -> '1960s', '19th century', '1960-1969', 'before 1900', 'after 1950', '1960'
extractPhrases()      -> "quoted strings" + multi-word capitalised noun phrases
extractKeywords()     -> tokenise, strip ~140 STOP_WORDS, drop pure numbers
identifyEntityTerms() -> phrases + capitalised words not at sentence start
lookupSynonyms()      -> ahg_thesaurus_synonym lookups (synonym/use_for/related, w. weight)
```

The only Ollama prompts that exist anywhere in the code base are in `OllamaPageIndexClient`:

* **System prompt (tree construction)** - verbatim from `OllamaPageIndexClient::getTreeConstructionSystemPrompt()`:

```
You are a document structure analyser for an archival management system.

Your task is to build a hierarchical JSON tree (table of contents) from {$typeDesc}.

Each node in the tree MUST have:
- "id": a unique string identifier (e.g., "n1", "n2", "n1.1")
- "title": a short descriptive title for this section/level
- "summary": a 1-2 sentence summary of what this section contains
- "level": the hierarchical level (e.g., "fonds", "series", "sub-series", ...)
- "children": an array of child nodes (empty array if leaf node)
- "keywords": an array of 3-5 key terms for this section

Rules:
1. Preserve the original document hierarchy faithfully
2. Every piece of content must belong to at least one node
3. Summaries must be factual - do not invent information not in the source
4. The root node represents the entire document
5. Return ONLY valid JSON - no markdown, no explanation, no preamble

Output format: a single JSON object representing the root node.
```

* **System prompt (retrieval)** - verbatim from `getRetrievalSystemPrompt()`:

```
You are a retrieval reasoning engine for an archival management system.

Given a hierarchical tree index of a document and a user query, determine
which nodes in the tree are relevant to answering the query.

For each relevant node, explain WHY it matches the query.

Output format (JSON only, no markdown):
{
  "matches": [
    { "node_id": "n1.2", "relevance": 0.95,
      "reason": "This series contains correspondence about the queried topic" }
  ],
  "reasoning": "Brief overall explanation of the search strategy and findings"
}

Rules:
1. Return ALL relevant nodes, ranked by relevance (1.0 = perfect, 0.0 = irrelevant)
2. Only include nodes with relevance >= 0.3
3. Consider parent nodes as context - a child node inherits relevance from its parent
4. The "reason" field must reference specific content from the node, not generic statements
5. Return ONLY valid JSON - no markdown fences, no preamble
```

These prompts are wired only into the PageIndex routes (`/discovery/pageindex`, `/discovery/build`). They are **not** invoked by the live `/discovery/search` retrieval pipeline. Query expansion in the live retrieval path is regex-only.

---

## 4. Re-ranking / fusion

`grep` for `fusion|reciprocal|RRF|combine|merge|rerank` in `packages/ahg-discovery/`:

* **`DiscoveryController::mergeResults()`** - weighted normalised sum across keyword/entity/hierarchical (no vector). Hard-coded weights:
  ```
  hasEntity ? (kw 0.35, ent 0.40, hier 0.25) : (kw 0.70, ent 0, hier 0.30)
  multi-source bonus: score *= (1 + (sourceCount - 1) * 0.1)
  ```
* **`DiscoveryController::rrfBoostWithVector()`** - Reciprocal Rank Fusion, **k=60** (Cormack et al., SIGIR 2009), applied as a *post-hoc* re-rank on the merger's output to fold in the vector strategy:
  ```php
  foreach ($merged as &$row) {
      if (isset($vectorRank[$id])) {
          $row['score'] += 1.0 / ($k + $vectorRank[$id] + 1);
      }
  }
  usort($merged, fn($a,$b) => $b['score'] <=> $a['score']);
  ```
  Vector items the 3-way merger missed are appended with `score=0.0` so RRF can still rank them in.

**Configurability:** the merge weights and the RRF `k=60` are PHP `private const` / hard-coded literals. They are NOT in `ahg_settings`. The only fusion-related settings present are pool sizes and min-score thresholds:

```
ahg_discovery_keyword_pool_size = 100
ahg_discovery_entity_pool_size  = 200
ahg_discovery_vector_pool_size  = 100
ahg_discovery_image_pool_size   = 50
ahg_discovery_hierarchical_top_n = 20
ahg_discovery_vector_min_score  = 0.25
ahg_discovery_image_min_score   = 0.30
ahg_discovery_max_results       = 100
```

The AtoM-side `ResultMerger` (`/usr/share/nginx/archive/atom-ahg-plugins/ahgDiscoveryPlugin/lib/Services/ResultMerger.php`) has separate constants for 3-/4-/5-strategy modes (incl. `WEIGHT_VECTOR=0.25`, `WEIGHT_IMAGE_5S=0.15`). The Heratio port does NOT carry that across; it does not have a 5-way mode at all on the text path.

---

## 5. Latency - measured today

### 5.1 Hardware

| Item | Value |
|---|---|
| Host | `theahg` (192.168.0.110 LAN, 192.168.0.112 bridge) |
| Cores | 24 (`nproc`) |
| Memory | 251 GiB total, 159 GiB available, 1.0 GiB swap in use |
| Kernel | Linux 6.8.0-107-generic |
| PHP | 8.3.30 CLI |
| Storage rotation | Mostly loop devices in `lsblk`; the data volume is on `/mnt/nas/heratio` (NFS / SAN - `lsblk` does not show it) |

### 5.2 Per-strategy timings for query **"Dakawa"**

Cold/warm where noted; **all warm runs are after the cold run primed caches.**

| Step | Endpoint / call | Cold (ms) | Warm runs (ms) |
|---|---|---:|---:|
| Query expansion (regex + stop-words + thesaurus) | `DiscoveryController::expandQuery()` (logged in `ahg_discovery_log.strategy_breakdown`) | 12 | 11 |
| Keyword retrieval - Heratio path (MySQL `LIKE` on `information_object_i18n.title/scope`) | controller `keywordSearch()` (logged) | 7 | 7 |
| Keyword retrieval - equivalent Elasticsearch query against `atom_qubitinformationobject` (322 k docs) | `POST /atom_qubitinformationobject/_search` | 2,383 | **6 / 6 / 6 / 2 / 6** (server-side `took`, ≤9 ms wall) |
| Keyword retrieval - same multi-LIKE against `atom.information_object_i18n` (455 k rows) | `mysql -e "SELECT ... LIKE '%Dakawa%' OR ..."` | 240 | 212 / 176 |
| Entity retrieval - `LIKE` on `atom.ahg_ner_entity` (9.79 M rows, no FTS index) | `mysql -e "SELECT object_id, COUNT(*) FROM ahg_ner_entity WHERE entity_value LIKE '%Dakawa%' ..."` | **27,284** | 20,821 / 20,108 |
| Hierarchical expansion (sibling/child walk on top 20) | controller `hierarchicalSearch()` (logged) | 0 | 0 |
| Vector retrieval - Qdrant `anc_records` `points/search` (raw, no embedding) | `POST :6333/.../points/search` (cold-loads 454 k-pt collection) | 5,521 | 1,034 / 6 / 23 / 81 |
| Embedding for vector retrieval (Ollama all-minilm) | `POST 192.168.0.78:11434/api/embeddings` | **n/a - Ollama 78 unreachable today** | - |
| Local SPARQL entity retrieval (rico:Place CONTAINS lookup, no FTS index) | `POST :3030/ric/sparql` for `?nm rico:textualValue ?n . FILTER(CONTAINS(LCASE(?n),"dakawa"))` | **30,005 (timeout)** | 30,004 / 30,004 |
| Re-ranking / fusion (`mergeResults` + `rrfBoostWithVector`) | controller `merge` step (logged) | 0 | 0 |

### 5.3 End-to-end via the actual Heratio HTTP endpoint

`GET https://heratio.theahg.co.za/discovery/search?q=Dakawa&...` - this is the production code path. The `heratio` DB has only 743 IOs and **does not contain ANC content**; both calls return `total: 0` but the latency budget is real:

| Mode | Total wall (s) | log_id | Per-strategy ms (from `strategy_breakdown` JSON) |
|---|---:|---:|---|
| `mode=standard` | 0.374 | 37 | `expansion=12, keyword=7, hierarchical=0, merge=0` (response_ms=48) |
| `mode=vector` | 0.423 | 38 | `expansion=11, keyword=7, entity=27, vector=8, hierarchical=0, merge=0` (response_ms=80) |

The `vector=8 ms` is misleading: with Ollama down, `VectorSearchStrategy::search()` catches the throw from `embedQuery()` returning `null` and exits in <10 ms with zero hits. **The 8 ms is "vector search aborted at the embedding step", not "vector search served".**

### 5.4 Honest end-to-end with Ollama healthy and against the ANC corpus

This deployment cannot produce that number today. The closest realistic reconstruction from the AtoM-side production site (psis.theahg.co.za / `archive` DB / atom plugin) would require running the same probe there, which is out of scope for this read-only report. From the per-component numbers above, a synthetic best-case for the `vector` mode against the ANC corpus, *with Ollama healthy and the embedding step costing roughly the AtoM scripts' historical 80–250 ms*, would be:

```
expansion 12 + keyword (ES) 9 + entity (NER LIKE) 20,000 + hierarchical 5
       + embed 200 + qdrant 25 + merge 5  ≈ 20.3 s
```

The entity strategy is the killer; it dominates the budget by three orders of magnitude over every other component.

---

## 6. Pilot evaluation infrastructure

`grep -rn` for `qrels|ndcg|mrr|recall@|relevance_judg|mean_reciprocal` across the entire heratio repo (excluding `node_modules`):

* **Zero matches.** No qrels file, no nDCG calculator, no MRR/Recall@k harness, no relevance-judgement controller or blade view.

`tests/fixtures/` contains only:

```
parity-map.json
role-credentials.json
seed-urls.json
```

- these are end-to-end smoke / parity fixtures (URL routing checks against the AtoM source), not retrieval-quality fixtures.

There is **no `tests/` subdirectory under `packages/ahg-discovery/`** at all.

What does exist for capturing retrieval data:

* `ahg_discovery_log` table (heratio DB): 36 rows, schema includes `query_text, expanded_terms, result_count, clicked_object, clicked_at, dwell_ms, response_ms, session_id, keywords (JSON), strategy_breakdown (JSON), pre_merge_ranks (JSON), post_merge_ranks (JSON)`. 36 rows is roughly 50/50 smoke-test queries (`Department of Education`, `*`, `Dakawa`) - not a curated relevance set.
* `ahg_discovery_log` table (archive DB): 14 rows, all 2026-02-26 between 08:58 and 14:56 - historical AtoM-plugin queries from a single afternoon's bring-up, not a pilot evaluation.
* `ahg_discovery_cache` (heratio + archive): query-result cache, not for evaluation.
* `ahg_pageindex_query_log`: schema present, 0 rows.

There is no ground-truth `qrels` set of `(query, document_id, relevance_grade)` tuples anywhere in the repo or filesystem. No relevance-judgement UI - `grep` for `relevance|judge|assess` under `packages/ahg-discovery` and `packages/ahg-research` returns only the per-result `relevance` field on PageIndex matches (LLM-assigned, not human-judged).

**Conclusion:** the system can capture per-query telemetry but cannot today compute nDCG, MRR, or Recall@k against a fixed query set. A pilot evaluation needs the qrels set + the evaluation script to be built before any reportable numbers can be produced.

---

## 7. Audit and provenance

### 7.1 `DiscoveryQueryLogger`

File: `/usr/share/nginx/heratio/packages/ahg-discovery/src/Services/DiscoveryQueryLogger.php`. Methods:

* `logQuery(array $payload): ?int` - inserts one row into `ahg_discovery_log` and returns the new `id` so the JS click handler can correlate.
* `logClick(?int $logId, int $clickedObjectId, ?string $sessionId)` - sets `clicked_object` + `clicked_at`.
* `logDwell(int $logId, int $dwellMs)` - sets `dwell_ms` (clamped 0…3,600,000 ms).

Per-query the row carries:

| Column | Source | Format |
|---|---|---|
| `query_text` | request `q` | varchar(8000) |
| `user_id` | `auth()->id()` | nullable int |
| `session_id` | `Session::getId()` | varchar(64) |
| `expanded_terms` | full `expanded` array (keywords, phrases, synonyms, dateRange, entityTerms) | JSON text |
| `keywords` | `expanded['keywords']` | JSON |
| `strategy_breakdown` | `{strategy_name => {hits:int, ms:int, top_ids:int[≤50]}}` | JSON, **per-strategy timings present** |
| `pre_merge_ranks` | `{strategy => [object_id...100]}` ordered as each strategy returned | JSON |
| `post_merge_ranks` | final user-facing order, top 200 | JSON |
| `result_count` | `count(final_ids)` | int |
| `response_ms` | `microtime` total | int |
| `clicked_object` | from `/discovery/click` | nullable int |
| `clicked_at` | `now()` on click | nullable datetime |
| `dwell_ms` | from page-leave beacon | nullable uint |

Confirmed live from row id=38: `strategy_breakdown` JSON contains six keys (expansion, keyword, entity, vector, hierarchical, merge), each with `ms` and `hits`. No Ollama prompt text or tokens are logged - only `OllamaPageIndexClient`'s internal `tokens_used` count is recorded into `ahg_pageindex_query_log` (model_used + response_ms + reasoning_text), and only when PageIndex is exercised; that table is currently empty.

### 7.2 Retention

* No cron cleanup for `ahg_discovery_log` exists. `crontab -l` contains `ahg-facet-cache`, `atom-backup`, `atom-bot-watch`, `mysql-full-dump`, etc. - none touch the discovery log.
* No `discovery:prune` artisan command is registered (verified via `php artisan list | grep -i discov`).
* The table will grow unbounded.

---

## 8. Honest gap list

Things that **don't work yet**:

* Ollama at `192.168.0.112:11434` is offline today (since 2026-04-28 11:01 SAST). PageIndex tree construction, PageIndex retrieval, and any LLM-mediated functionality are unreachable.
* Ollama at `192.168.0.78:11434` is unreachable (connection refused). The default text-embedding endpoint AND the CLIP image-embedding endpoint both depend on it. Vector search cannot embed query strings → returns `[]`.
* `llama3.1:8b` - the model the code targets - **is not pulled locally**. Available 8B-class substitutes on disk: `qwen3:8b`, `mistral:7b`. The default `OllamaPageIndexClient` constructor will fail health checks on a fresh restart until either the model is pulled or settings switch the default.
* PageIndex tree table is empty (0 rows in every database). The `/discovery/pageindex` route always returns no matches. Nothing has been indexed via the LLM-driven tree builder.
* `finding_aid` table is empty - there is no EAD-XML corpus to index. PageIndex's `extractEadContent()` would draw from `information_object` if invoked, but no invocation has happened.
* Local Fuseki `SELECT (COUNT(*)) WHERE { ?s ?p ?o }` does not return within 60 s. Either the dataset is large enough that an unbounded count is genuinely slow, or the store needs a load - either way, baseline triple count is currently unknown.

Things that **work but haven't been measured**:

* Vector retrieval on `anc_records` (454 k points, 384-dim, all-minilm). Raw `/points/search` warms in ~25 ms after first hit. We have not measured end-to-end including embedding (because Ollama is down).
* Image search on `archive_images` (279 CLIP points). The `ImageSearchStrategy::searchByExistingObject()` and `searchByVector()` paths exist but no benchmark exists.
* The query-expansion regex pipeline is fast (≤12 ms) but its retrieval-quality contribution has never been A/B'd.
* RRF fusion (`rrfBoostWithVector`, k=60) is implemented but its effect on result quality is unmeasured (no qrels set).
* `DiscoveryQueryLogger` strategy_breakdown JSON is being written and rich enough for offline ablation, but no ablation run has been performed.

Things that are **mocked, stubbed, or hard-coded**:

* The Heratio `keywordSearch()` is **not Elasticsearch** - it's MySQL `LIKE %term%` with hand-rolled `CASE WHEN ... THEN 3 ELSE 0`-style scoring. Branded as "keyword retrieval" in the controller signature, but it does not use the heratio_qubitinformationobject ES index that exists right next to it. The index is sized correctly for the role (455 k docs, 221 MB) but is unused on this code path.
* Merge weights in `DiscoveryController::mergeResults()` (0.35 / 0.40 / 0.25 etc.) and the RRF `k = 60` are PHP class constants. Not configurable through `ahg_settings` despite the file's comments in adjacent classes implying they should be.
* `ahg_settings` is missing entries for `semantic_qdrant_collection`, `semantic_embedding_model`, `semantic_embedding_url`, `semantic_qdrant_url`, `semantic_timeout_ms` - the code falls back to defaults baked into `VectorSearchService`. Not broken, but not auditable from settings either.
* `getHighLevelIds()` falls back to **hard-coded term IDs `[227, 228, 229, 231]`** (Fonds / Sub-fonds / Series / Sub-series) when the `term_i18n` lookup fails. These IDs are AtoM-instance-specific and will silently misbehave on a fresh install.
* `enrichResults()` hard-codes `usage_id = 142` for thumbnail lookup and `TermId::EVENT_TYPE_CREATION` for creator/date queries. AtoM-derived constants; no defensive guard if the term ids were re-seeded.
* The `archive_images` Qdrant payload schema (`object_id`, `mime_type`, `slug`, `title`) and the `anc_records` payload schema (`title`, `parent_id`, `slug`, `has_scope`, `has_transcript`, `database`) are documented only by example - there is no JSON Schema and no enforcement.

What needs to exist before a real pilot evaluation can run:

1. A frozen **qrels set** - at least 20–50 representative ANC queries with graded relevance judgements over a fixed top-K from the union of all four strategies.
2. An **evaluation harness** (`php artisan discovery:eval` or similar) that replays the qrels, captures per-strategy and fused rankings, and computes nDCG@10, MRR, Recall@K. Today nothing of the sort exists.
3. **Ollama brought back online** with `llama3.1:8b` (or a chosen substitute) - and the choice of model recorded in `ahg_settings`, not just hard-coded.
4. **Discovery's keyword strategy switched from MySQL `LIKE` to Elasticsearch** (or, if MySQL is intentional, the paper needs to say MySQL, not Elasticsearch / OpenSearch).
5. **Index the ANC corpus into either heratio's ES indices or directly run Discovery against the `atom` DB.** Today the heratio code base talks to a DB that does not contain the ANC content, so any benchmark numbers from `heratio.theahg.co.za` would be meaningless.
6. **Build at least one PageIndex tree** (an EAD finding-aid for one ANC fonds - Dakawa Development Centre, ~20 k descendants - would be the obvious target). Until then, the LLM-driven retrieval paragraph in the paper is theoretical.
7. **A SPARQL FTS / regex-acceleration plan for Fuseki.** Place / CorporateBody / Person lookups by string fragment time out at 30 s. Either `text:query` (Lucene index) or a denormalised lookup table is needed before entity-by-name SPARQL is on the critical path.
8. **A `discovery:prune` artisan command + cron** so `ahg_discovery_log` doesn't grow unbounded once telemetry capture is turned on for real users.
9. **A reproducible benchmark script** that pins the query set, the index versions, and the model checksums - so v1 of the pilot results can be replayed in v2.

### Pilot status as of 2026-04-28

The ANC pilot is **in flight, not yet evaluable.** Today's progress: the `ahg_io_facet_denorm` sidecar (ADR-0001 Pattern C) was populated against the `atom` DB earlier today - `SELECT taxonomy_id, COUNT(*) FROM atom.ahg_io_facet_denorm GROUP BY taxonomy_id` confirms **2,410,881 rows for taxonomy_id=35 (Subjects) and 2,652,060 rows for taxonomy_id=42 (Places), 5,062,941 rows total**. The brief's "subject facet aggregate dropped from 7.6 s to 2.0 s on cold cache" is consistent with that population but is a Pattern-C facet-cache result, separate from the Discovery retrieval pipeline this report covers. No retrieval-quality numbers are available yet for the pilot.

---

*End of report.*
