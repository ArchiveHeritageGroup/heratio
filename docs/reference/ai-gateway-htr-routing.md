# AI Gateway and HTR Routing

All AI traffic from Heratio flows through a single AI gateway. As of the HTR consolidation work (heratio#131), handwriting transcription is no longer an exception: every HTR call path resolves its endpoint from settings, routes through the gateway, and authenticates with a bearer token. There are no hardcoded AI hostnames left in the HTR consumers.

## The AI gateway

Heratio is an AI *client*, never an AI host. It calls a gateway published at `https://ai.theahg.co.za/ai/v1/...`. The gateway:

- authenticates every request with an `Authorization: Bearer` token,
- routes the request to whichever GPU worker is currently healthy,
- logs the call for the audit trail,
- relays the upstream response back unchanged.

Services reached through the gateway include NER (`/ai/v1/ner/...`), summarisation, translation, generic Ollama calls, and HTR (`/ai/v1/htr/...`).

## The HTR consolidation (heratio#131)

Before this work, HTR calls were scattered: some used a hardcoded host, some used a double-prefixed URL that never resolved, some used the `HTR_SERVICE_URL` environment variable directly, and they authenticated with an `X-API-Key` header the gateway did not expect. The consolidation fixed all of it.

### Gateway side

A catch-all proxy route was added to the gateway:

```
/ai/v1/htr/legacy/{subpath:path}   (GET, POST)
```

This makes the entire legacy HTR API - the older standalone HTR service - reachable *through* the gateway instead of by direct connection. The gateway forwards method, query string, body and content-type to the legacy service and relays the response. The legacy service's base URL is a gateway-side configuration value; it is never referenced by Heratio directly.

The primary HTR extraction endpoint remains `/ai/v1/htr` on the gateway, backed by the current HTR worker.

### Consumer side

Every HTR caller in Heratio now resolves its endpoint from a setting and sends a bearer token:

- **`htr_url` setting** - the HTR endpoint, default `https://ai.theahg.co.za/ai/v1/htr`. Resolved from `ahg_ner_settings`, falling back to `ahg_ai_settings` (feature `general`). No hostname is hardcoded.
- **Legacy operations** - crop-OCR and fine-tuned OCR post to `{htr_url}/legacy/...`, which the gateway proxies to the legacy HTR API.
- **Bearer auth** - all HTR calls send `Authorization: Bearer <key>`. The old `X-API-Key` header is gone.
- **`HTR_SERVICE_URL`** - retained only as a developer override (for pointing at a local HTR server). It is no longer the production source of truth; the `htr_url` setting is.

The consumers touched were the HTR controller actions (`htrForObject`, crop-OCR, fine-tuned OCR), the `HtrService` client class, and the page-index OCR service in the discovery package. The AtoM-side mirror (`executeHtr`) was updated the same way, routing through the gateway via an `app_htr_url` config value with a bearer header.

## Why it matters for governance

Consolidating HTR onto the gateway means HTR requests are authenticated and appear in the gateway's audit trail next to every other AI service. There is no longer a side channel that bypasses oversight. This is a precondition for the AI Inventory & Governance dashboard to give a complete picture of AI activity.

## Configuration summary

| Setting | Where | Purpose |
|---|---|---|
| `htr_url` | `ahg_ner_settings` / `ahg_ai_settings` general | HTR endpoint; default is the gateway |
| `api_key` | `ahg_ner_settings` / `ahg_ai_settings` general | Bearer token for AI gateway calls |
| `HTR_SERVICE_URL` | environment | Developer-only override of the HTR base URL |

## See also

- `ai-governance-signing.md` - the governance dashboard, model manifest and inference signing.
- `ai-inference-provenance-discipline.md` - the inference and override provenance chain.
