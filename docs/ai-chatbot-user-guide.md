> Heratio Help Center article. Category: User Guide.

# Heratio - AI Library Assistant: User Manual

**Version:** 1.0.0
**Date:** May 2026
**Author:** The Archive and Heritage Group (Pty) Ltd
**Issue:** heratio#762

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Asking a Question](#2-asking-a-question)
3. [How Grounded Answers Work](#3-how-grounded-answers-work)
4. [Conversation History](#4-conversation-history)
5. [Admin Dashboard & Review](#5-admin-dashboard--review)
6. [Privacy & Compliance](#6-privacy--compliance)
7. [Configuration](#7-configuration)
8. [Limitations](#8-limitations)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Introduction

The AI Library Assistant is a conversational interface over the Heratio archival catalogue. Users can ask natural-language questions ("show me all photographs by John Goldblatt taken in Johannesburg between 1980 and 1986") and the assistant returns grounded answers with citations to the catalogue records that supported each statement.

NLSA LMS Tender §2.5 + §2.10 + §3.1 require chatbot functionality with NLP, multi-language support, secure-API integration, and audit logging.

The current release ships the **session and retrieval backbone**: persistent chat sessions, RAG (Retrieval-Augmented Generation) over the catalogue via Qdrant, and compliance audit receipts via `ahg-ai-compliance`. The floating widget on every OPAC/admin page (toggleable per role), explicit multi-language testing, and the "escalate to a librarian" flow are remaining work tracked in heratio#762.

---

## 2. Asking a Question

Navigate to **/chatbot** (requires authentication). Type your question in plain English (or Afrikaans, isiZulu, isiXhosa, Sesotho sa Leboa where supported). Press Enter or click **Send**.

The assistant responds with:

- A natural-language answer
- A "Sources" panel listing up to five catalogue records that grounded the answer
- A grounding score (0.00 - 1.00) indicating how strongly the retrieved sources support the response

Click a source to open the underlying archival description in a new tab.

---

## 3. How Grounded Answers Work

The assistant follows a Retrieval-Augmented Generation pattern:

1. Your question is embedded as a vector via the AI Gateway (`ai.theahg.co.za`).
2. The vector is queried against the `heratio_library` collection in Qdrant.
3. The top-K matching catalogue records (default K=5) are returned with similarity scores.
4. The records are formatted as a context block.
5. The context block and your question are sent to the LLM (currently `qwen3.6:27b` via the gateway, configurable).
6. The LLM is instructed via system prompt to answer **only** from the supplied context, citing record IDs.

If the grounding score is low (<0.30) the assistant prefaces its answer with a "low confidence" disclaimer and recommends a manual catalogue search.

---

## 4. Conversation History

Every session is persisted to `ahg_ai_chatbot_message`:

| Column | Purpose |
|---|---|
| `session_id` | UUID per browser/user pairing |
| `role` | `user`, `assistant`, or `system` |
| `content` | Message text |
| `sources` | JSON array of supporting record IDs |
| `grounding_score` | Float 0-1 |
| `model` | Which LLM produced the response |
| `tokens_in` / `tokens_out` | Cost tracking |

Click **History** to see the running thread for the current session. Click **Reset** to start a new session (the old one stays in the database for audit).

---

## 5. Admin Dashboard & Review

Admins can visit **/admin/chatbot** to see:

- Active sessions today
- Total messages this week
- Average grounding score
- Cost (tokens, USD)

The review queue (**/admin/chatbot/review**) shows recent low-grounding responses for spot-check. Cataloguers can flag bad answers; flagged messages feed back into the prompt-tuning workflow.

---

## 6. Privacy & Compliance

- All inferences are logged as `ahg-ai-compliance` receipts (EU AI Act Article 12 audit trail).
- The receipt includes the question, retrieved sources, the response, the model, the grounding score, and the user identity.
- No question or response is sent to any third-party service. The LLM call goes only to the AHG AI Gateway, which routes to internal GPU hosts.
- POPIA / GDPR data subject access requests can be satisfied by querying `ahg_ai_chatbot_message` and `ai_inference_log` by user ID.

---

## 7. Configuration

`config/ahg-ai-chatbot.php`:

| Key | Default | Purpose |
|---|---|---|
| `gateway.endpoint` | from `AI_GATEWAY_URL` | AHG AI gateway base URL |
| `gateway.model` | `qwen3.6:27b` | LLM model name |
| `gateway.think` | false | Reasoning mode (off for qwen3) |
| `retriever.collection` | `heratio_library` | Qdrant collection |
| `retriever.top_k` | 5 | Records per query |
| `retriever.min_score` | 0.30 | Below this, mark response low-confidence |
| `session.message_cap` | 50 | Max messages before forced reset |

---

## 8. Limitations

- **No floating widget yet.** Users must navigate to /chatbot. The OPAC/admin floating widget is on the roadmap.
- **Multi-language: backend supports the languages the gateway model speaks (qwen handles en/af/zu well). Targeted af + en regression coverage still pending.**
- **No "escalate to librarian" flow.** Planned: a one-click route from a bad answer to a `support_ticket` row or staff email.
- **No policy page yet.** The plain-language chatbot policy is being drafted.
- **Inference receipts already wired,** but the receipt-search UI is part of `ahg-ai-compliance`, not the chatbot module.

---

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Spinner forever | Gateway model name not loaded | Check `/api/stats` on the gateway; load the model |
| Empty Sources panel | Qdrant collection empty | Run `php artisan ahg:library-qdrant-index` |
| "" empty response | qwen3 + `think:true` ate tokens | Confirm `think:false` in config |
| Low grounding score on factual question | Embedding model drift | Re-embed: `php artisan ahg:embeddings-rebuild` |
| 401 from gateway | API key not provisioned | Check `ahg_settings.ai_gateway_key` |

---

For technical operators, see `docs/reference/ai-chatbot-implementation.md`.
