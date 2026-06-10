# Generative Scholarship: Suggested Connections (North Star #1210)

First slice of the Heratio "North Star" vision (GitHub issue #1210): *AI surfaces
novel connections across the heritage graph that no human had noticed*. This slice
ships a **Suggested Connections** engine inside `packages/ahg-ai-services` that finds
NON-OBVIOUS links between catalogue records and explains them with the gateway LLM.

## What it does

1. **Candidate discovery (pure SQL, free).** It finds pairs of information objects
   that share two or more *access points* but are **not directly linked** to each
   other. Access points are subjects, places and genres held in
   `object_term_relation` (taxonomy ids 35 = subjects, 42 = places, 78 = genres).
   "Directly linked" means an edge in the generic `relation` table - those are the
   obvious links, and they are deliberately excluded so only the non-obvious pairs
   surface. Pairs are ranked by the count of distinct shared access points
   ("shared-signal strength").
2. **Explanation (on demand, one gateway LLM call per pair).** For a chosen pair,
   the engine asks the LLM to write a one-paragraph hypothesis of *why* a researcher
   might find a connection worth investigating. The prompt is grounded ONLY in the
   two record titles and the shared access-point names; the system prompt forbids
   invented names, dates, events or facts and mandates tentative language. Each
   hypothesis is cached so it is generated once per pair.

## Where it lives (all inside packages/ahg-ai-services)

- `src/Services/SuggestedConnectionsService.php`
  - `candidatesForObject(int $id, int $minShared=2, int $limit=25)` - non-obvious
    peers of one seed record.
  - `topPairs(int $minShared=2, int $limit=25, ?int $ancestorId=null)` - collection-wide
    (or sub-tree by MPTT `lft/rgt`) strongest non-obvious pairs.
  - `explainPair(array $pair, bool $useCache=true)` - gateway LLM hypothesis, cached.
  - `ACCESS_POINT_TAXONOMIES = [35, 42, 78]`.
- `src/Controllers/SuggestedConnectionsController.php` - `index` (scan UI) + `explain`
  (JSON for the inline reveal). The explain endpoint rebuilds titles + shared terms
  server-side from authoritative data; it never trusts client-supplied titles.
- `resources/views/connections/index.blade.php` - admin page: pick a seed record id
  or "Scan whole collection", see ranked candidate pairs with links to both records,
  and an "Explain" button that reveals the AI hypothesis inline.
- Routes (package loader, `web` group, multi-segment URLs):
  - `GET  /admin/ai/connections` -> `admin.ai.connections`
  - `POST /admin/ai/connections/explain` -> `admin.ai.connections.explain`
- Cache table `ahg_suggested_connection` (ordered pair `object_id_1 < object_id_2`,
  unique). Auto-created by `AhgAiServicesServiceProvider::ensureSchema()` from
  `database/install.sql` (single outer try/catch, `Schema::hasTable` probe).

## AI routing

Every LLM call goes through `AhgAiServices\Services\LlmService::complete()`, i.e. the
AHG gateway (`ai.theahg.co.za`). No direct GPU-node calls. The explanation uses
`temperature 0.3`, `purpose = suggested_connection`, `data_scope = internal`, and
passes the titles + shared-term list as `context_sources` so the guardrail grounding
check can score the output.

## Why it is the North Star, concretely

The candidate query is the cheap, scalable half: it turns the existing access-point
graph into a ranked list of "these two records keep co-occurring on the same people /
places / subjects yet nobody linked them." The LLM half turns that signal into a
human-readable, hedged research hypothesis. Together they make the catalogue
*generate* leads rather than only answer queries.

## Test surface

- URL: `/admin/ai/connections` then `?object_id=<id>` for one record, or
  `?scan=1` for the whole collection. `min_shared` (default 2) tunes the threshold.
- Verified end-to-end on real records (e.g. seed 901851): candidate discovery,
  collection scan, a live gateway explanation (model `mistral:7b`), and the cache-hit
  path that skips the second LLM call. Test cache rows were removed after the run.

## Next slices (not in this one)

- Indirect bridges through the `relation` table (shared neighbour, not just shared
  term) as an additional candidate source and signal.
- Cross-collection / cross-institution / cross-language pairs (federation + MT).
- Persisted "accepted / dismissed" researcher feedback to tune ranking.
- A batch job to pre-warm explanations for the top-N pairs.
