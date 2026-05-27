# AHG AI Chatbot

Conversational LLM interface over the Heratio archival catalogue.

## Overview

Provides a RAG-grounded chatbot that lets users query descriptive metadata,
access-point authority data, custody provenance, and archival-domain knowledge
through natural language. Responses are grounded in the catalogue - the bot
retrieves relevant records, exposes which sources informed the answer, and
records every inference to the EU AI Act tamper-evident chain.

## Features

- **RAG-grounded responses** — retrieves catalogue records via Elasticsearch
  keyword/KNN hybrid search, then synthesises an answer with the retrieved
  snippets injected as context into the LLM prompt.
- **Grounding transparency** — every answer lists the source records (title,
  identifier, link) so users can verify and trace provenance.
- **EU AI Act inference logging** — every RAG round is recorded to
  `ahg_ai_inference_log` via the InferenceLogger chain.
- **Per-conversation history** — `ahg_ai_chatbot_message` stores message
  history per session (in-memory within a tab session; server-side persistence
  on login).
- **Guardrail enforcement** — GuardrailService inspects every dispatch:
  purpose-limitation, data-scope, PII masking, grounding threshold.
- **Quota gating** — `QuotaService::consume('chatbot')` limits usage.

## Architecture

```
ChatbotController ← ChatbotService ← LlmService (ahg-ai-services)
                        ↓
                  QdrantService ← Elasticsearch catalogue
                        ↓
                  InferenceLogger (ahg-ai-compliance)
                                        ← InferenceContextService
```

## Configuration

Stored in `ahg_settings` under the `chatbot` feature (see AhgAiChatbotServiceProvider).

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Master switch | `true` |
| `max_history` | Max messages to include in context | `20` |
| `max_context_records` | Max catalogue records to retrieve per turn | `5` |
| `grounding_threshold` | Minimum grounding score (0–1) | `0.5` |
| `system_prompt` | Custom system prompt | _(built-in)_ |
| `model_fallback` | Model name when no user preference | _(LLM default)_ |
| `temperature` | LLM sampling temperature | `0.3` |

## Routes

```
GET  /chatbot          — Chat UI
POST /chatbot/message  — Send a message (JSON)
GET  /chatbot/history  — Load session history
POST /chatbot/reset    — Clear session
```

## Database Tables

```sql
CREATE TABLE ahg_ai_chatbot_message (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id    VARCHAR(64) NOT NULL,
  role          ENUM('user','assistant','system') DEFAULT 'user',
  content       TEXT NOT NULL,
  sources       JSON DEFAULT NULL,       -- [{title, identifier, url}]
  grounding_score FLOAT(5,4) DEFAULT NULL,
  model         VARCHAR(100) DEFAULT NULL,
  tokens_in     INT UNSIGNED DEFAULT NULL,
  tokens_out    INT UNSIGNED DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_session (session_id, created_at)
);
```

## Privacy

PII is never sent to cloud providers — GuardrailService masks email addresses
and long numeric identifiers (phone / ID numbers) on cloud-dispatched prompts.
Chatbot data stays inside the local trust domain.
