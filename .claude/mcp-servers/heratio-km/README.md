# heratio-km MCP server

Exposes the Heratio Knowledge Base at https://km.theahg.co.za (an Ollama + Qdrant RAG over Heratio + AtoM corpus, currently ~10,000 indexed docs, 2,200 QA pairs) as Model Context Protocol tools so Claude Code can query the KM mid-conversation.

## Tools

| Tool | What it does |
|---|---|
| `km_ask` | POST to `/api/ask`, consume the SSE stream, return concatenated answer + cited references. Cold-start can take up to 2 minutes while Ollama loads the LLM. |
| `km_stats` | Doc count, available LLM models, current default model, status. |
| `km_health` | Live status of the underlying Ollama + Qdrant. |
| `km_ingest_doc` | POST to `/api/ingest`, write a doc into the cross-agent KM corpus (`km_agent_docs`). Required: `title`, `body`. Optional: `project`, `source_url`, `author`, `authored_at`, `tags[]`, `visibility`. Use to persist findings, decisions, release notes, audit results so future sessions + other agents (Workbench, ahg-ai) can discover them via `km_ask`. |
| `km_sources` | GET `/api/sources`. List projects + per-project doc counts in the cross-agent corpus. |

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

## Why these tools and not "memory get/put"?

The original brief was a key-value memory store. km.theahg.co.za turned out to be a Q&A interface instead - RAG over a corpus, not a KV store. So the read shape is question/answer rather than get/put.

The cross-agent write path (`km_ingest_doc` + `km_sources`) was added later via Phase 3 of heratio#716 so Claude can persist non-trivial findings into a shared `km_agent_docs` corpus that Workbench (`set_km_doc` agent tool), ahg-ai (`km_client.py`), and GitHub Actions (`km-publish-on-close.yml`) all read+write to. This makes KM the durable cross-agent knowledge bus, not just a per-session KV scratch.

The local file-based memory at `/root/.claude/projects/.../memory/` still plays the per-session role - facts about *this* user / project that don't belong in a shared corpus.
