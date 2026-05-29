# Chatbot, Discovery API and Channels - Administrator Guide

This guide covers configuring and operating the Heratio AI assistant: the
RAG chatbot, the task skills, the discovery JSON API, and the optional
WhatsApp channel. It is the operator companion to the user guide at
`docs/features/chatbot-user-guide.md`.

No credentials or host addresses appear in this document - every secret is read
from environment variables via package config.

## Components at a glance

| Component | Package | Surface |
|---|---|---|
| RAG chatbot | `ahg-ai-chatbot` | `/chatbot`, `POST /chatbot/message`, widget |
| Task skills | `ahg-ai-chatbot` | inside `ChatbotService::dispatch()` |
| Discovery API | `ahg-search` | `POST /api/discovery/search`, `POST /api/discovery/recommend` |
| Query expansion | `ahg-ai-services` | `QueryExpansionService` (opt-in) |
| History re-ranking | `ahg-semantic-search` | `HistoryRerankService` (opt-in) |
| WhatsApp channel | `ahg-ai-chatbot` | `GET/POST /webhooks/whatsapp` (opt-in) |

## Chatbot configuration

Config lives in `config/ahg-ai-chatbot.php`, all keys overridable by env:

- `AHG_CHATBOT_ENABLED` - master on/off (default on).
- `AHG_CHATBOT_MAX_HISTORY` - conversation turns kept as context.
- `AHG_CHATBOT_MAX_CONTEXT` - catalogue records injected per turn.
- `AHG_CHATBOT_GROUNDING` - minimum grounding score (0-1) before a reply is
  flagged for review.
- `AHG_CHATBOT_SYSTEM_PROMPT` - optional custom persona text.
- `AHG_CHATBOT_TEMPERATURE` - LLM sampling temperature (keep near 0.3).
- `AHG_CHATBOT_GUARDRAIL` - `off | warn | mask | block`.
- `AHG_CHATBOT_WIDGET_ENABLED` - floating widget injection.

### Rate limiting

`POST /chatbot/message` is throttled at 60 requests per minute per client.
The discovery endpoints are throttled at 100 per minute, and the WhatsApp
webhook at 120 per minute. These are applied as route middleware and need no
configuration.

### Review queue

Replies below the grounding threshold are flagged and surfaced at
`/admin/chatbot/review`. The dashboard at `/admin/chatbot` shows message and
session totals over the last 30 days.

## Task skills

The assistant detects three library intents before falling back to RAG:

- `renew_loan` -> `LibraryCirculationService::renew()`
- `submit_ill_request` -> `LibraryIllService::patronCreate()`
- `check_item_status` -> `LibraryOpacService::getAvailability()`

Intent detection is keyword based (see `ChatbotSkillService::$intentKeywords`).
When an intent matches, the skill answers deterministically from the library
services and the LLM is not called; otherwise the message flows into the normal
RAG pipeline.

Patron resolution links the authenticated user to a `library_patron` row by
email. If there is no signed-in user, or no matching patron, the renew and ILL
skills reply asking the user to sign in - they never error. Availability checks
need no authentication (public OPAC data).

To add an intent, extend `$intentKeywords` and add a handler method plus a
`match` arm in `ChatbotSkillService::handle()`.

## Discovery API

Two stateless JSON endpoints (mounted on the `api` middleware group, so they
are CSRF-exempt and rate-limited rather than session-bound):

### `POST /api/discovery/search`

Request: `{ "q": "railway", "filters": { "repository": 3, "hasDigitalObject": true }, "limit": 30, "offset": 0 }`

Response: `{ "results": [...], "total": N, "facets": {...}, "time_ms": N, "meta": {...} }`

`offset` is converted to a page number internally. `filters` keys are mapped to
the underlying `ElasticsearchService::advancedSearch()` parameters; unknown
keys are ignored. When Elasticsearch is unreachable the endpoint degrades to an
empty result set (`meta.source = "degraded"`) rather than failing.

### `POST /api/discovery/recommend`

Request: `{ "io_id": 1234, "limit": 12 }`

Response: `{ "items": [...], "reason": "...", "source": "qdrant" }`

Reuses the Qdrant vector-similarity path (the same engine behind
`GET /api/search/semantic/similar/{ioId}`). Degrades to an empty list with
`source = "degraded"` when the vector backend is unavailable.

## Optional enhancements (off by default)

Both are gated so production performance is never affected until validated.

### Query expansion (Ollama)

`config/ahg-search.php` -> `discovery.query_expansion`
(`AHG_DISCOVERY_QUERY_EXPANSION`). When on, discovery search runs the query
through `QueryExpansionService`, which asks the local LLM for synonyms and
related terms, falling back to the thesaurus, then to the raw query. Every
failure path degrades silently to the original query.

### History-based re-ranking

`config/ahg-search.php` -> `discovery.history_rerank`
(`AHG_DISCOVERY_HISTORY_RERANK`). When on AND a `user_id` is present (or the
caller is authenticated), the current result page is reordered in memory to
favour records matching the user's recent `ahg_search_query_log` entries. It
never changes the total count or re-queries Elasticsearch.

## WhatsApp Business channel

Disabled by default. The webhook routes 404 until enabled, so the surface does
not exist without explicit provisioning.

Config (`config/ahg-ai-chatbot.php` -> `whatsapp`), all from env:

- `AHG_CHATBOT_WHATSAPP_ENABLED` - master gate (default false).
- `AHG_CHATBOT_WHATSAPP_VERIFY_TOKEN` - echoed in Meta's GET verification.
- `AHG_CHATBOT_WHATSAPP_ACCESS_TOKEN` - Cloud API bearer token for sends.
- `AHG_CHATBOT_WHATSAPP_PHONE_NUMBER_ID` - sender phone-number id.
- `AHG_CHATBOT_WHATSAPP_APP_SECRET` - optional; enables X-Hub-Signature-256
  validation when set.
- `AHG_CHATBOT_WHATSAPP_API_BASE` / `AHG_CHATBOT_WHATSAPP_API_VERSION` - Graph
  API endpoint overrides.

### Endpoints

- `GET /webhooks/whatsapp` - Meta verification handshake. Returns the
  `hub.challenge` only when `hub.verify_token` matches the configured token.
- `POST /webhooks/whatsapp` - inbound delivery. When `app_secret` is set the
  payload signature is validated. Inbound text is routed per sender (stable
  session keyed on the sender's number) through `ChatbotService::dispatch()`,
  and the reply is sent back via the Cloud API. The endpoint always returns
  2xx on a structurally-valid request so Meta does not retry; failures are
  logged.

### Setup outline

1. Provision a WhatsApp Business / Meta Cloud API number and app.
2. Set the env keys above and set `AHG_CHATBOT_WHATSAPP_ENABLED=true`.
3. In the Meta dashboard, point the webhook callback URL at
   `/webhooks/whatsapp` and use your `verify_token` for the handshake.
4. Subscribe to the `messages` field.
5. Send a test message and confirm a reply; check the application log for
   `[whatsapp]` entries if a send fails.

## Operational notes

- Skill replies are stored with model `skill:<intent>` so analytics can
  distinguish deterministic answers from generative ones.
- A skill failure (DB or service hiccup) logs a warning and falls back to RAG -
  it never breaks the turn.
- The discovery endpoints and the WhatsApp channel are read-only with respect
  to the catalogue and never bypass publication-status scoping.
