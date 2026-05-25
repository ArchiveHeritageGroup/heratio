#!/usr/bin/env node
/**
 * heratio-km MCP server
 *
 * Exposes km.theahg.co.za (the AtoM Knowledge Base — Ollama + Qdrant RAG)
 * as Model Context Protocol tools so Claude can query the KM mid-conversation.
 *
 * Tools:
 *   km_ask    — POST /api/ask, consume the SSE stream, return concatenated
 *               answer + sources/references.
 *   km_stats  — GET /api/stats. Doc count, available LLM models, status.
 *   km_health — GET /health. Live status of the underlying Ollama + Qdrant.
 *
 * Wiring (~/.claude/mcp.json or .claude/mcp.json):
 *   {
 *     "mcpServers": {
 *       "heratio-km": {
 *         "command": "node",
 *         "args": ["/usr/share/nginx/heratio/.claude/mcp-servers/heratio-km/index.js"],
 *         "env": { "KM_BASE_URL": "https://km.theahg.co.za", "KM_TIMEOUT_MS": "120000" }
 *       }
 *     }
 *   }
 *
 * Tools surface in Claude as mcp__heratio-km__km_ask, mcp__heratio-km__km_stats,
 * mcp__heratio-km__km_health.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems
 * AGPL-3.0-or-later. See <https://www.gnu.org/licenses/>.
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';

const KM_BASE_URL = (process.env.KM_BASE_URL || 'https://km.theahg.co.za').replace(/\/$/, '');
const KM_TIMEOUT_MS = parseInt(process.env.KM_TIMEOUT_MS || '120000', 10);
const KM_API_KEY = process.env.KM_API_KEY || '';

function authHeaders() {
  const h = { 'Content-Type': 'application/json' };
  if (KM_API_KEY) h['Authorization'] = `Bearer ${KM_API_KEY}`;
  return h;
}

// ---------- tool: km_ask ----------
// POST /api/ask consumes SSE — `data: {json}\n` lines with event types:
//   { type: 'meta', references: [...] }   — sources matched in the corpus
//   { type: 'token', text: '...' }        — incremental answer tokens
//   { type: 'done',  ... }                — stream end
// We accumulate the answer and return it alongside the references list.
async function kmAsk({ question, source }) {
  if (!question || typeof question !== 'string' || question.trim() === '') {
    throw new Error('km_ask requires a non-empty `question` string');
  }

  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), KM_TIMEOUT_MS);

  const resp = await fetch(`${KM_BASE_URL}/api/ask`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({ question, stream: true, source: source || 'all' }),
    signal: ctrl.signal,
  }).catch((err) => {
    clearTimeout(timer);
    throw new Error(`km_ask network error: ${err.message}`);
  });

  if (!resp.ok) {
    clearTimeout(timer);
    throw new Error(`km_ask HTTP ${resp.status}: ${await resp.text().catch(() => '')}`);
  }

  // Consume the SSE stream
  let answer = '';
  let references = [];
  let meta = {};
  const reader = resp.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split('\n');
      buffer = lines.pop();
      for (const line of lines) {
        if (!line.startsWith('data: ')) continue;
        let data;
        try {
          data = JSON.parse(line.slice(6));
        } catch {
          continue;
        }
        if (data.type === 'meta') {
          if (Array.isArray(data.references)) references = data.references;
          meta = { ...meta, ...data };
          delete meta.references;
        } else if (data.type === 'token' && typeof data.text === 'string') {
          answer += data.text;
        } else if (data.type === 'answer' && typeof data.text === 'string') {
          // Some implementations emit the full answer in one event.
          answer = data.text;
        } else if (data.type === 'done') {
          // stream end; loop exits via reader done
        }
      }
    }
  } finally {
    clearTimeout(timer);
  }

  return {
    answer: answer.trim(),
    references,
    meta,
  };
}

// ---------- tool: km_stats ----------
async function kmStats() {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 15000);
  try {
    const resp = await fetch(`${KM_BASE_URL}/api/stats`, { headers: authHeaders(), signal: ctrl.signal });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return await resp.json();
  } finally {
    clearTimeout(timer);
  }
}

// ---------- tool: km_health ----------
async function kmHealth() {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 10000);
  try {
    const resp = await fetch(`${KM_BASE_URL}/health`, { headers: authHeaders(), signal: ctrl.signal });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return await resp.json();
  } finally {
    clearTimeout(timer);
  }
}

// ---------- tool: km_ingest_doc ----------
// Write a doc into the cross-agent KM corpus (km_agent_docs). Used by Claude
// to persist findings/decisions so other agents (Workbench, ahg-ai, future
// MCPs) and future sessions can discover them via km_ask.
async function kmIngestDoc(args) {
  const title = (args.title || '').trim();
  const body = (args.body || '').trim();
  if (!title || !body) {
    throw new Error('km_ingest_doc requires non-empty `title` and `body`');
  }
  const payload = {
    title,
    body,
    project: (args.project || 'general').toLowerCase(),
    source_url: args.source_url || '',
    author: args.author || 'claude',
    authored_at: args.authored_at || '',
    tags: Array.isArray(args.tags) ? args.tags : [],
    visibility: args.visibility || 'external',
  };
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 30000);
  try {
    const resp = await fetch(`${KM_BASE_URL}/api/ingest`, {
      method: 'POST',
      headers: { ...authHeaders(), 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: ctrl.signal,
    });
    if (!resp.ok) {
      throw new Error(`km_ingest_doc HTTP ${resp.status}: ${await resp.text().catch(() => '')}`);
    }
    return await resp.json();
  } finally {
    clearTimeout(timer);
  }
}

// ---------- tool: km_sources ----------
// List the projects + per-project doc counts currently in the cross-agent
// corpus. Useful for "what does KM know about heratio so far?".
async function kmSources() {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 15000);
  try {
    const resp = await fetch(`${KM_BASE_URL}/api/sources`, { headers: authHeaders(), signal: ctrl.signal });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return await resp.json();
  } finally {
    clearTimeout(timer);
  }
}

// ---------- MCP wiring ----------
const TOOLS = [
  {
    name: 'km_ask',
    description: 'Ask the Heratio Knowledge Base (RAG over Heratio docs + AtoM corpus, served by Ollama + Qdrant at km.theahg.co.za). Returns a generated answer with cited references. First request after a cold start can take up to 2 minutes while the LLM loads.',
    inputSchema: {
      type: 'object',
      properties: {
        question: { type: 'string', description: 'The natural-language question to ask.' },
        source: { type: 'string', description: 'Optional KB source filter (e.g. "heratio", "atom", "ric", "all"). Defaults to "all".', default: 'all' },
      },
      required: ['question'],
    },
  },
  {
    name: 'km_stats',
    description: 'Return KM corpus stats: indexed doc count, available LLM models, current default model, status.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
  {
    name: 'km_health',
    description: 'Check KM liveness - returns the status of the underlying Ollama + Qdrant services.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
  {
    name: 'km_ingest_doc',
    description: 'Write a doc into the cross-agent KM corpus (km_agent_docs collection on km.theahg.co.za). Use this to persist findings, decisions, release notes, audit results, or anything else future Claude sessions / Workbench / ahg-ai should be able to discover via km_ask. Required: title, body. Optional: project (e.g. "heratio", "psis", "atom-framework"), source_url, author, authored_at (ISO 8601), tags (array of strings), visibility ("external" default, "internal" for admin-key-only reads). Returns { doc_id, indexed_at, url, collection, project }.',
    inputSchema: {
      type: 'object',
      properties: {
        title: { type: 'string', description: 'Short title (will be embedded for semantic search; aim for descriptive headings).' },
        body: { type: 'string', description: 'Full doc body in markdown. Max 200kb.' },
        project: { type: 'string', description: 'Logical project label. Lowercase a-z 0-9 _- only.', default: 'general' },
        source_url: { type: 'string', description: 'Optional canonical URL for the doc (e.g. GitHub release tag, commit, PR).' },
        author: { type: 'string', description: 'Who is publishing this. Defaults to "claude".' },
        authored_at: { type: 'string', description: 'ISO 8601 timestamp. Defaults to now.' },
        tags: { type: 'array', items: { type: 'string' }, description: 'Up to 32 lowercase tags for filtering.' },
        visibility: { type: 'string', enum: ['external', 'internal'], description: 'external = web key can read; internal = admin only.', default: 'external' },
      },
      required: ['title', 'body'],
    },
  },
  {
    name: 'km_sources',
    description: 'List the projects + per-project doc counts currently in the cross-agent KM corpus (km_agent_docs).',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
];

const server = new Server(
  { name: 'heratio-km', version: '0.1.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const { name, arguments: args } = req.params;
  try {
    let result;
    if (name === 'km_ask') result = await kmAsk(args || {});
    else if (name === 'km_stats') result = await kmStats();
    else if (name === 'km_health') result = await kmHealth();
    else if (name === 'km_ingest_doc') result = await kmIngestDoc(args || {});
    else if (name === 'km_sources') result = await kmSources();
    else throw new Error(`Unknown tool: ${name}`);
    return { content: [{ type: 'text', text: JSON.stringify(result, null, 2) }] };
  } catch (err) {
    return {
      content: [{ type: 'text', text: `Error calling ${name}: ${err.message}` }],
      isError: true,
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
// Keep the process alive — the SDK handles stdin EOF for shutdown.
