# KM Public API (km.theahg.co.za)

Heratio's Knowledge Management service is exposed publicly so that web pages,
browser extensions, and external assistants (claude.ai, ChatGPT, etc.) can ask
factual, source-grounded questions about Heratio / AtoM. Bearer-token auth
guards every read/write endpoint; `/api/stats` and `/health` stay open for
monitoring.

## Endpoints

| Endpoint            | Method | Auth | Notes                                                     |
| ------------------- | ------ | ---- | --------------------------------------------------------- |
| `/health`           | GET    | none | Liveness probe                                            |
| `/api/stats`        | GET    | none | Counts (chunks, sources, audit rows)                      |
| `/api/ask`          | POST   | yes  | LLM-grounded answer. Rate-limited 30 r/min/IP, burst 10.  |
| `/api/search`       | POST   | yes  | Vector-search only (no LLM)                               |
| `/api/feedback`     | POST   | yes  | Submit thumbs-up / thumbs-down                            |
| `/api/audit`        | GET    | yes  | Recent Q&A audit rows (admin)                             |

CORS is wide open (`Access-Control-Allow-Origin: *`) - the bearer token is the
real gate. Allowed methods: `GET, POST, OPTIONS`. Allowed headers:
`Authorization, Content-Type, Accept`.

## API keys

Two keys are configured in
`/etc/systemd/system/ahg-km.service.d/override.conf`:

| Variable          | Use case                                  | Storage                          |
| ----------------- | ----------------------------------------- | -------------------------------- |
| `KM_API_KEY`      | MCP server, internal services, server-to-server | operator runbook            |
| `KM_WEB_API_KEY`  | Browsers, claude.ai, public web frontends | `/root/km-web-api-key.txt` (chmod 600) |

Either key works on every protected endpoint. Rotate independently by editing
the override file and `systemctl restart ahg-km`. Restart is zero-downtime if
nothing is mid-request - the Flask preload takes ~5 minutes (LLM warmup), so
plan rotations in a maintenance window if you can.

If you prefer, you can also accept multiple web keys per consumer by appending
extra `Environment=KM_WEB_API_KEY_<n>=…` lines and extending `_KM_KEYS` in
`/opt/ai/km/app.py`. Today only the two are wired.

## Calling /api/ask

Three auth styles accepted; pick whichever fits your client:

```bash
# 1. Authorization header (preferred - keeps the key out of URLs / access logs)
curl -X POST https://km.theahg.co.za/api/ask \
  -H "Authorization: Bearer $KM_WEB_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"question":"What is AtoM 2.10?","stream":false}'

# 2. ?api=<key> query string (URL-only - ideal for chat-paste / quick links)
curl 'https://km.theahg.co.za/api/ask?api='"$KM_WEB_API_KEY"'&q=What+is+AtoM+2.10'

# 3. X-API-Key header (some HTTP clients prefer this)
curl -X POST https://km.theahg.co.za/api/ask \
  -H "X-API-Key: $KM_WEB_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"question":"What is AtoM 2.10?"}'
```

There is also a shorter `/ask` alias that does the same thing as `/api/ask`:

```
https://km.theahg.co.za/ask?api=KEY&q=help+on+AtoM
```

⚠ The `?api=` form puts the key in URLs, browser history, referer headers,
and nginx access logs. Use it for personal/throwaway keys; rotate the WEB key
if you suspect leakage.

Response shape (truncated):

```json
{
  "answer": "AtoM 2.10.1 (December 2024) is the latest stable release ...",
  "sources": [
    {"title":"…","url":"https://www.accesstomemory.org/...", "score":0.81}
  ],
  "metadata": {
    "model":"qwen2.5:32b",
    "took_ms":12450,
    "audit_id":7423
  }
}
```

Set `"stream": true` for SSE streaming (event-stream). The nginx vhost has
`proxy_buffering off` and `proxy_cache off` so SSE survives end-to-end.

## Anti-hallucination contract

Every response is constrained by the system prompt in `/opt/ai/km/app.py`.
Highlights:

- **Source-grounded.** Every claim must come from an indexed source. The model
  cites them inline (URL or title in parentheses).
- **No fabricated URLs.** Subdomains under `theahg.co.za` are restricted to
  `psis.theahg.co.za`; the model is not allowed to invent paths.
- **Refuses secrets.** Any request for credentials, IPs, hostnames, ports, or
  keys is refused with a fixed message - KM never searches the corpus for
  them.
- **Calls out gaps.** If the indexed sources don't cover the question, the
  model says so explicitly instead of guessing.

## Rate limits

`/api/ask` is rate-limited at the nginx layer:

```nginx
limit_req_zone $binary_remote_addr zone=km_ask:10m rate=30r/m;

location = /api/ask {
    limit_req zone=km_ask burst=10 nodelay;
    proxy_pass http://127.0.0.1:5050;
    include /etc/nginx/snippets/km-proxy.conf;
}
```

That's 30 requests/min steady-state with a 10-burst overflow. Normal
interactive use stays well under it; runaway scrapers will start getting 503s.

Every other endpoint inherits the default proxy snippet; tighten further with
its own `limit_req` directive if abuse appears.

## Adding KM as a tool in claude.ai

claude.ai's custom-MCP / connector flow accepts a remote HTTP MCP server. The
internal MCP bridge already exposes KM at `.claude/mcp-servers/heratio-km/`
(read-only proxy to `https://km.theahg.co.za`, tools: `km_ask`, `km_stats`,
`km_health`). For the web claude.ai client, point a remote MCP connector at
the same base URL; the bearer token is `KM_WEB_API_KEY`.

Minimal browser-side fetch:

```js
async function askKm(question) {
  const r = await fetch("https://km.theahg.co.za/api/ask", {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${KM_WEB_API_KEY}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ question, stream: false }),
  });
  if (!r.ok) throw new Error(`KM ${r.status}: ${await r.text()}`);
  return r.json();
}
```

## Operator notes

- Service unit: `ahg-km.service`. Logs: `journalctl -u ahg-km`.
- App: `/opt/ai/km/app.py` (Flask). Vector store: Qdrant on `:6333`. LLM:
  Ollama (`qwen2.5:32b`) on `:11434`.
- Vhost: `/etc/nginx/sites-enabled/km.theahg.co.za.conf`. Shared proxy
  directives: `/etc/nginx/snippets/km-proxy.conf`.
- Auto-ingest: `km-ingest.timer` runs every 2 hours, sweeping new Heratio
  docs/help/wiki content into Qdrant.
- Audit DB: `/opt/ai/km/audit.db` (SQLite). Every `/api/ask` call writes a row
  with question, answer, sources, score, latency.
