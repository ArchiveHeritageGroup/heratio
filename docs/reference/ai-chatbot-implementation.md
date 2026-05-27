# AI Chatbot Implementation Reference

**Issue:** heratio#762
**Package:** `packages/ahg-ai-chatbot/`
**Status:** Backend (session + RAG + audit) shipped. Widget UI, multi-language regression, escalation flow, policy page pending.

## Package surface

| Component | Path |
|---|---|
| Service provider | `src/Providers/AhgAiChatbotServiceProvider.php` |
| Controller | `src/Controllers/ChatbotController.php` |
| Service | `src/Services/ChatbotService.php` (395 lines) |
| Retriever | `src/Services/QdrantRetriever.php` |
| Routes | `routes/web.php` |
| Config | `config/ahg-ai-chatbot.php` |
| Views | `resources/views/{index,admin,review}.blade.php` |

Discovered via `extra.laravel.providers`; required as `ahg/ai-chatbot: @dev`. Auto-creates the `ahg_ai_chatbot_message` table on boot (idempotent).

## Routes

```
GET  /chatbot                   -> chatbot.index   (auth)
POST /chatbot/message           -> chatbot.message
GET  /chatbot/history           -> chatbot.history
POST /chatbot/reset             -> chatbot.reset
GET  /admin/chatbot             -> admin.chatbot.index
GET  /admin/chatbot/review      -> admin.chatbot.review
```

## Database schema (`ahg_ai_chatbot_message`)

```sql
id              BIGINT UNSIGNED PK
session_id      VARCHAR(64)
role            ENUM('user','assistant','system')
content         TEXT
sources         JSON
grounding_score FLOAT(5,4)
model           VARCHAR(100)
tokens_in       INT UNSIGNED
tokens_out      INT UNSIGNED
created_at      TIMESTAMP
INDEX ix_session (session_id, created_at)
```

## RAG flow

1. `ChatbotService::dispatch($sessionId, $userMessage, $userId)` is the entry point.
2. `QdrantRetriever::search($message, top_k)` returns the top-K matching catalogue chunks.
3. `buildRagPrompt()` assembles the system prompt + context block + history + user message.
4. Gateway call to `ai.theahg.co.za` with model from config.
5. Response + sources + grounding score persisted via `saveMessage()`.
6. Inference receipt written through `ahg-ai-compliance` (Article 12 audit chain).

## Configuration

`config/ahg-ai-chatbot.php` documented in the help article. Key environment overrides:

- `AI_GATEWAY_URL` - default `https://ai.theahg.co.za`
- `AI_CHATBOT_MODEL` - default `qwen3.6:27b`
- `QDRANT_HOST` - default `localhost:6333`
- `QDRANT_COLLECTION` - default `heratio_library`

## Gaps vs heratio#762 acceptance

- [ ] Floating widget on OPAC + admin with per-role toggle
- [ ] Multi-language explicit testing for af/en
- [ ] Escalation flow to staff (ticket / email)
- [ ] Help article inside in-app /help
- [ ] Chatbot policy page (plain-language, POPIA)
