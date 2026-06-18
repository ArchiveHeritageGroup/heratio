# Generative scholarship: connections across institutions

**Generative scholarship finds connections no human spotted - now across institutional boundaries.** The Discovered-connections report for a record already surfaces non-obvious links inside your own catalogue. This federated layer extends it: it also searches OTHER institutions in your federation for records that relate to the one you are viewing, and explains each match with a short, grounded AI rationale.

## What it does

For a single archival record, the report now has two parts:

1. **Local discovery** (unchanged) - non-obvious connections and research leads drawn from your own catalogue's relationship graph (people, places, subjects, records), with AI commentary grounded strictly in those real links.
2. **Connections across institutions** (new) - related records held by OTHER federation peers, found live by searching their catalogues.

The cross-institutional section appears only when at least one federation peer is configured and reachable. If federation is not set up, or no peer responds, the section is simply absent and the local discovery is unaffected.

## How a cross-institutional connection is built

1. **Access points.** The system takes the record's strongest access points - its title plus the names of the people, organisations, subjects and places it is directly linked to in your catalogue.
2. **Federated search (cached).** Those terms are sent, in a single query, to every active federation peer's search endpoint. The result - matches plus their AI rationales - is then **persisted** so the next viewer is served from the store rather than paying a fresh peer + AI round-trip (see "Persistence and freshness" below).
3. **Shared access points.** For each peer hit, the system records which of your access-point terms appear in the match - this overlap is the evidence the connection is real, and it is what the results are ranked by (most shared terms first).
4. **AI rationale.** For the top matches, an AI model writes one short sentence explaining why the record likely connects. The model is given ONLY the record's label and the shared access points for that match, and is instructed never to introduce any person, place, date or fact that was not supplied. As always with generative output: treat each line as a hypothesis and verify against the source institution before citing.

## Reading the results

Each cross-institutional connection card shows:

- The **matched record title**, linking out to the record at the holding institution (opens in a new tab).
- The **source peer** - the institution that holds the matched record.
- The **AI rationale** - the one-line "why this likely connects" (omitted if the AI service was unreachable).
- The **shared access points** - the catalogue terms common to both records (the verified evidence behind the match).
- Any **"also present in"** pills the federation layer stamped (the same record surfaced from more than one source).

Results are deduplicated per institution and capped at the strongest matches.

## Resilience

The whole cross-institutional layer is fail-soft by design:

- Federation package not installed, or no peers configured -> section omitted.
- A peer times out or errors -> that peer contributes nothing; others still appear.
- The AI gateway is down -> matches still render with their shared access points, just without the AI rationale lines, and a notice explains why.

None of these conditions ever affects the local discovery above, and none can produce an error page.

## Persistence and freshness

Cross-institutional discovery is expensive (a live peer round-trip plus a per-match AI rationale), so its results are **persisted in a read-through cache** rather than recomputed on every page load:

- The first time a record's report is opened, the federated result is computed live and stored, with a "generated" timestamp.
- Subsequent views are served from the store until the result ages past a freshness window (default 24 hours, set with the `scholarship_federated_cache_minutes` setting).
- When the window passes, the next view recomputes it live and re-stores it.
- If a live refresh fails (a peer is down, the gateway is unreachable), the **last-known stored result is served** instead of a blank section - so an outage degrades to slightly-stale data, never to nothing.
- Appending `?refresh=1` to the report URL forces a live recompute and re-store for staff who want the latest immediately.

To keep the most worthwhile records pre-warmed, the scheduled command `php artisan ahg:refresh-federated-discoveries` refreshes the cache for the records that already carry a local discovery (`--object=<id>` to target one, `--stale-only` to skip rows still fresh). This makes federated results behave like the local discoveries: stored, browsable, and resilient to peer availability.

## AI routing and grounding

All AI calls in this feature route through the AHG AI gateway via the shared LlmService abstraction - never a direct model or node endpoint. Every rationale is grounded only in the shared catalogue terms shown on its card; the model is told never to invent entities or facts.

## Limitations and follow-ups (this increment)

This is the first cross-peer increment - deliberately scoped to "find and explain cross-institutional connections for one record." Deferred follow-ups:

- **Cross-language discovery** - matching access points across languages (via the translation layer) is not yet wired; matches today rely on shared wording.
- **Persistence / caching of federated discoveries** - DONE (see "Persistence and freshness" above): results are now stored in a read-through cache and refreshable on a schedule. A fully curated, editorially-managed cross-institutional feed (staff pinning/hiding individual connections) remains a future step.

## Where to find it

Open the Discovered-connections report for any record (the generative-scholarship report under the semantic-search admin area). The "Connections across institutions" panel appears below the local discovery when federation is available.
