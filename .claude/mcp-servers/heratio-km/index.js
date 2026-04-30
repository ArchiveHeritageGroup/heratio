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
    description: 'Check KM liveness — returns the status of the underlying Ollama + Qdrant services.',
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
