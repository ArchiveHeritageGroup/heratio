# AHG AI Gateway (ai.theahg.co.za) - Architecture and Functionality

**Summary.** `ai.theahg.co.za` is the single AHG AI gateway: one FastAPI application
(`/opt/ahg-ai/gateway`, run by `ahg-ai-gateway.service` as a local uvicorn behind nginx)
backed by one Postgres database (`ahgai`). It is NOT a thin proxy. It performs three jobs
at once: (1) an authenticated, metered AI **inference gateway**; (2) a GPU **allocation and
preemption scheduler**; and (3) an **admin console**. All AHG AI consumers (Heratio, KM,
ai-demo, the Workbench, and any future agent or service) reach AI through this gateway.

**Standing rule: never bypass the gateway.** Application/production AI calls MUST go through
`https://ai.theahg.co.za/ai/v1/...`. Do not wire any app directly to a GPU node's inference
port. The gateway is the only sanctioned door: it enforces keys, scopes, and quotas, records
every call, and provides node failover and model routing. Direct-to-node access is for
operator diagnostics only, never for an application's configured endpoint.

## Request shape

```
client -> nginx (ai.theahg.co.za) -> FastAPI gateway
                                       |-- /ai/v1/*       inference proxy   -> GPU nodes
                                       |-- /api, /jobs, /allocations         scheduler API
                                       |-- /admin/*       HTML admin console
                                      backed by Postgres `ahgai`
```

Routers: `ai_proxy` (the `/ai/v1` inference surface), `public` / `public_auth` /
`public_jobs` / `public_feedback` (the async job + allocation API), `allocations`,
`desktop`, and the admin routes.

## Plane 1 - inference proxy (`/ai/v1/*`)

This is the surface Heratio, KM, and ai-demo use. Every request passes one auth dependency
(`_validate_and_reserve`):

- **Auth:** `X-API-Key: <key>` OR `Authorization: Bearer <key>` - same `api_keys` table,
  SHA-256 hashed lookup. Joined against `clients` and `admin_users`.
- **Rejected if:** key not active, client inactive, creating admin inactive, key not
  trusted, expired, or out of quota.
- **Scope gate:** the key's `scope` must include `gateway`. A `km`-only key is refused on
  the GPU endpoints, so a leaked KM browser key cannot burn GPU on chat/image work.
- **Metered:** each call atomically decrements `remaining_requests` and bumps
  `requests_used`. Every call is logged to `api_request_logs`. The gateway is the quota and
  billing boundary, not a pass-through.

Endpoints and their backends:

| Endpoint | Backend role |
|---|---|
| `/ner/extract`, `/summarize`, `/translate`, `/translate/batch`, `/htr` | Python inference workers |
| `/ner/extract-pdf`, `/summarize-pdf` | Python workers (multipart, buffered for retry) |
| `/ollama/{path}` | Ollama on the GPU nodes (LLM generate/chat/embeddings) |
| `/nuextract/{path}` | NuExtract 2.0 served by vLLM (structured extraction) |
| `/htr/legacy/{path}` | Legacy HTR service |
| `/health` | Unauthenticated upstream ping |

**Node selection is database-driven.** For a service or model the gateway queries
`gpu_nodes` for rows that are client-enabled, `health_status IN (healthy, unknown)`, and
whose `capabilities.services` (and for Ollama, `capabilities.models_on_disk`) contain what
is needed - ordered by `current_load`. It returns the whole eligible list and **fails over**
node by node on transport errors (a powered-off host moves to the next), with a short connect
timeout for snappy failover and a long overall timeout for slow token streams. A static
fallback upstream is always appended last so consumers survive a transient database blip. A
5xx from a reachable node is relayed as-is (an app error, not a dead host).

**Two routing behaviors on the Ollama path:**
1. **Model affinity** - a model can be pinned to a preferred node (routed there first, with
   the other nodes kept as failover).
2. **Per-client model pin/fallback** - a client may carry a `default_model` plus a
   `model_policy`. `pin` rewrites every generate/chat request to the client's model;
   `fallback` only rewrites when the requested model has no live node, so the client's own
   model resumes once its GPU box returns. This is scoped to generate/chat paths ONLY -
   embeddings pass through untouched, or retrieval embeddings (used by KM) would break.

The proxy does NOT inject a per-request `keep_alive`, so a node's `OLLAMA_KEEP_ALIVE`
setting governs model residency.

## Plane 2 - GPU allocation + preemption scheduler

`services/scheduler.py` exposes `allocate(db, request)`: a strict-ladder priority preemption
system over the GPU nodes. Active tiers, highest first:

```
admin > dedicated > batch > interactive
admin       can preempt dedicated, batch, interactive
dedicated   can preempt batch, interactive
batch       can preempt interactive
interactive can preempt nothing
```

A single `allocate()` pass: reap expired allocations, grant immediately if a capable node is
free, else find the cheapest lower-tier allocation this request may displace (strictly down
the ladder, never sideways), preempt it (victim marked `preempted`, new allocation `pending`
with a grace period), or queue the request if nothing can be displaced. Every decision is
recorded in `routing_decisions`; a regression test guards the strict ladder. This plane
backs the async job API (`.../models/{model}/submit` -> `jobs` -> `output_artifacts`), with
`public_users` as a separate identity space from the API `clients`.

## Plane 3 - admin console (`/admin/*`)

Server-rendered HTML console. Login is gated by `admin_users` plus an IP allow-list and a
login-attempt lockout, and every action is audited. It manages GPU nodes (enable/disable,
set mode, delete), routing policy (activate/edit tier rules), users, clients and their keys,
allocations (create / force-release), jobs, request logs, and audit views.

## Data model (Postgres `ahgai`)

- **Identity / billing:** `clients`, `api_keys`, `api_key_allocations`, `public_users`,
  `public_feedback`
- **GPU fleet:** `gpu_nodes` (capabilities JSON = `services` + `models_on_disk`,
  `health_status`, `current_load`, `is_enabled_for_clients`), `gpu_allocations`,
  `gpu_routing_policy`, `routing_decisions`, `jobs`, `output_artifacts`
- **Admin / security:** `admin_users`, `admin_allowed_ips`, `admin_login_attempts`,
  `admin_audit_log`, `audit_log`, `auth_failures`, `alert_events`, `service_bans`
- **Telemetry:** `api_request_logs`

## Operational notes

- **Health is database state, not live GPU truth.** A node's `health_status` is written by a
  healthcheck updater; a dead GPU can still read `healthy`. The way to remove a node from
  rotation is to set `is_enabled_for_clients = FALSE`.
- **`models_on_disk` is hand-maintained.** It is not auto-synced on model pull. If a model is
  pulled on a node but not added to that node's capabilities, the gateway will not route to it.
- **Scope (`gateway` vs `km`) and `model_policy` (pin vs fallback)** are the two switches that
  most often explain "it worked for one client but not another" (for example, a pinned client
  having a vision model silently swapped to a text model).

## One-line model

`ai.theahg.co.za` is a metered, key-scoped, failover-capable inference gateway AND a
priority-preemption GPU broker, sharing one FastAPI app and one Postgres database, with a
full admin console on top. The `/ai/v1/*` surface that applications use is the synchronous
front door to a larger GPU-scheduling system - and it is the only sanctioned path to AHG AI.
