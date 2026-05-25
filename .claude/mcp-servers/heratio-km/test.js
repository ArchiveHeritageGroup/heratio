#!/usr/bin/env node
/**
 * Smoke test - exercises km_health, km_stats, and km_sources directly
 * (without spinning up the MCP transport), so you can verify
 * km.theahg.co.za is reachable before wiring the server into
 * ~/.claude/mcp.json. Skips km_ask by default (slow, needs LLM warm)
 * and skips km_ingest_doc by default (would pollute the corpus).
 *
 * Usage:
 *   node test.js               # health + stats + sources
 *   node test.js --ask         # also runs km_ask with a sample question
 *   node test.js --ingest      # also writes a smoke doc via km_ingest_doc
 */

const KM_BASE_URL = (process.env.KM_BASE_URL || 'https://km.theahg.co.za').replace(/\/$/, '');
const KM_API_KEY = process.env.KM_API_KEY || '';

function authHeaders() {
  const h = { 'Content-Type': 'application/json' };
  if (KM_API_KEY) h['Authorization'] = `Bearer ${KM_API_KEY}`;
  return h;
}

async function get(path) {
  const resp = await fetch(`${KM_BASE_URL}${path}`, { headers: authHeaders() });
  if (!resp.ok) throw new Error(`GET ${path} → HTTP ${resp.status}`);
  return resp.json();
}

async function ask(question) {
  const resp = await fetch(`${KM_BASE_URL}/api/ask`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({ question, stream: true, source: 'all' }),
  });
  if (!resp.ok) throw new Error(`POST /api/ask → HTTP ${resp.status}`);
  const reader = resp.body.getReader();
  const dec = new TextDecoder();
  let answer = '', refs = [], buf = '';
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    buf += dec.decode(value, { stream: true });
    const lines = buf.split('\n');
    buf = lines.pop();
    for (const line of lines) {
      if (!line.startsWith('data: ')) continue;
      let d; try { d = JSON.parse(line.slice(6)); } catch { continue; }
      if (d.type === 'meta' && Array.isArray(d.references)) refs = d.references;
      else if (d.type === 'token' && typeof d.text === 'string') answer += d.text;
      else if (d.type === 'answer' && typeof d.text === 'string') answer = d.text;
    }
  }
  return { answer: answer.trim(), references: refs };
}

async function postJson(path, payload) {
  const resp = await fetch(`${KM_BASE_URL}${path}`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(payload),
  });
  if (!resp.ok) throw new Error(`POST ${path} -> HTTP ${resp.status}: ${await resp.text().catch(() => '')}`);
  return resp.json();
}

const main = async () => {
  console.log(`KM_BASE_URL = ${KM_BASE_URL}`);
  console.log('---health---');
  console.log(await get('/health'));
  console.log('---stats---');
  console.log(await get('/api/stats'));
  console.log('---sources---');
  console.log(await get('/api/sources'));
  if (process.argv.includes('--ask')) {
    console.log('---ask: "what is heratio?"---');
    const r = await ask('What is Heratio?');
    console.log('answer:', r.answer.slice(0, 400) + (r.answer.length > 400 ? '...' : ''));
    console.log(`references: ${r.references.length} sources`);
  } else {
    console.log('(skipping km_ask - pass --ask to include it)');
  }
  if (process.argv.includes('--ingest')) {
    console.log('---ingest: smoke doc---');
    const r = await postJson('/api/ingest', {
      title: `heratio-km test.js smoke ${new Date().toISOString()}`,
      body: 'Smoke test entry from /usr/share/nginx/heratio/.claude/mcp-servers/heratio-km/test.js --ingest. Safe to delete.',
      project: 'general',
      author: 'heratio-km test.js',
      tags: ['smoke-test', 'heratio-km-mcp'],
      visibility: 'external',
    });
    console.log(r);
  } else {
    console.log('(skipping km_ingest_doc - pass --ingest to include it; would write to corpus)');
  }
};

main().catch((e) => { console.error(e); process.exit(1); });
