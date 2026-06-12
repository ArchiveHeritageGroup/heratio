# Preservation-knowledge grounding for the AI assistant - technical reference

Summary: issue #1243. The library-assistant chatbot (`packages/ahg-ai-chatbot`) now grounds its answers to digital-preservation questions in the curated in-repo preservation knowledge corpus, with verbatim cited passages, instead of improvising. Shipped Heratio v1.142.69.

## How it works

`PreservationKnowledgeService` is a deterministic keyword/section index over the curated preservation docs - **no LLM, no embedding, no network, no database**. It globs `docs/reference/dp-*.md` (OAIS, PREMIS, METS, BagIt, PRONOM, fixity, NDSA Levels, DPC RAM, significant properties, OCFL, WARC) plus the preservation help/reference articles (39 files, ~436 sections), splits each into heading-anchored sections, and scores them against the user's question by term coverage, frequency and heading proximity (with a preservation synonym map so "integrity checks" matches "fixity"). It returns verbatim excerpts, each carrying a `docs/<path>.md#<anchor>` citation.

## Wiring (additive, fail-safe)

In `ChatbotService::dispatch()`: when `looksLikePreservationQuestion()` is true and passages are found, a `PRESERVATION KNOWLEDGE` block is appended to the system prompt via an optional 5th parameter on `buildRagPrompt()` (default empty, so existing callers are unaffected). The block is added **after** the catalogue `SOURCES`, never replacing them; retrieved passages are also added to the response `sources[]` as `kind: preservation-knowledge`. The whole path is wrapped in try/catch and falls back to the exact prior behaviour on any error.

## Gateway compliance

The grounding adds **zero AI calls** and zero network access, so it cannot bypass the AHG gateway. The assistant's generative turn continues to route through `LlmService` cloud-mode to `https://ai.theahg.co.za/ai/v1/...` unchanged.

## Testable surface

`GET /admin/chatbot/preservation-knowledge?q=...` (auth-gated) returns the matched passages, citations and corpus stats without any LLM call. Service methods: `retrieve()`, `buildContextBlock()`, `looksLikePreservationQuestion()`, `getIndex()`, `corpusFiles()`. Unit test: `tests/Unit/PreservationKnowledgeServiceTest.php`.

## Known pre-existing finding (separate from this change)

`packages/ahg-ai-chatbot/src/Services/QdrantRetriever.php` (the chatbot's pre-existing catalogue RAG retriever, not part of this feature) embeds via a direct Ollama node port (`ollama_embed_host` default `http://localhost:11434`). That is a direct-to-node call that bypasses the AHG gateway, contrary to the standing gateway rule. It was left untouched here and should be migrated to a gateway-routed embeddings path as a separate task.
