# heratio-km MCP server

Exposes the Heratio Knowledge Base at https://km.theahg.co.za (an Ollama + Qdrant RAG over Heratio + AtoM corpus, currently ~10,000 indexed docs, 2,200 QA pairs) as Model Context Protocol tools so Claude Code can query the KM mid-conversation.

## Tools

| Tool | What it does |
|---|---|
| `km_ask` | POST to `/api/ask`, consume the SSE stream, return concatenated answer + cited references. Cold-start can take up to 2 minutes while Ollama loads the LLM. |
| `km_stats` | Doc count, available LLM models, current default model, status. |
| `km_health` | Live status of the underlying Ollama + Qdrant. |

In Claude they appear as `mcp__heratio-km__km_ask`, etc.

## Wiring

Project-scoped MCP config at `/usr/share/nginx/heratio/.mcp.json`:

```json
{
  "mcpServers": {
    "heratio-km": {
      "command": "node",
      "args": ["/usr/share/nginx/heratio/.claude/mcp-servers/heratio-km/index.js"],
      "env": {
        "KM_BASE_URL": "https://km.theahg.co.za",
        "KM_TIMEOUT_MS": "180000"
      }
    }
  }
}
```

Restart Claude Code (or run `/mcp` in the chat) to pick up the config. The server stays alive for the duration of the Claude session and is killed on exit.

## Local sanity check

```bash
cd /usr/share/nginx/heratio/.claude/mcp-servers/heratio-km
node test.js                 # health + stats only
node test.js --ask           # also runs a sample km_ask query (slow)
```

## Env vars

| Var | Default | Purpose |
|---|---|---|
| `KM_BASE_URL` | `https://km.theahg.co.za` | KM endpoint root. Override for staging/local KM. |
| `KM_TIMEOUT_MS` | `120000` | Per-request timeout for `km_ask`. Cold start is ~2 min so 180s is recommended. |
| `KM_API_KEY` | _(none)_ | Optional bearer token if you put auth in front of the KM endpoint. |

## Why three tools and not "memory get/put"?

The original brief was a key-value memory store. km.theahg.co.za turned out to be a Q&A interface instead — RAG over a corpus, not a KV store. So the right shape is question/answer rather than get/put. The corpus is updated on the KM side (Heratio docs, AHG plugins, AtoM core code, RiC spec) — Claude reads via `km_ask`, doesn't write.

If a true KV memory layer is needed alongside, that's a separate MCP server with `memory_get` / `memory_put` tools backed by a real KV store. The local file-based memory at `/root/.claude/projects/.../memory/` already plays that role per-session.
