#!/usr/bin/env node
/**
 * docx MCP server
 *
 * Word/DOCX tooling for Claude across all workbench projects. Backed by
 * pandoc (Markdown<->DOCX, table-aware) and LibreOffice headless (DOCX->PDF).
 *
 * Tools:
 *   docx_from_markdown — Markdown (inline or file) -> .docx. GitHub-flavoured
 *                        markdown, so the pipe-tables in our tender/costing
 *                        docs render as real Word tables. Optional reference
 *                        .docx for house styling.
 *   docx_read          — .docx -> GitHub-flavoured Markdown text (headings +
 *                        tables preserved) for the model to read.
 *   docx_to_pdf        — .docx (or .md) -> .pdf via LibreOffice headless, for
 *                        tender/submission output.
 *
 * Wiring (.mcp.json):
 *   {
 *     "mcpServers": {
 *       "docx": {
 *         "command": "node",
 *         "args": ["/usr/share/nginx/workbench/.claude/mcp-servers/docx/index.js"]
 *       }
 *     }
 *   }
 *
 * Tools surface as mcp__docx__docx_from_markdown, mcp__docx__docx_read,
 * mcp__docx__docx_to_pdf.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems
 * AGPL-3.0-or-later. See <https://www.gnu.org/licenses/>.
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const PANDOC = process.env.PANDOC_BIN || 'pandoc';
const SOFFICE = process.env.SOFFICE_BIN || 'soffice';
const SOFFICE_TIMEOUT_MS = parseInt(process.env.SOFFICE_TIMEOUT_MS || '90000', 10);

// House standard (Johan, 2026-06-20): every generated doc gets a table of
// contents, heading styles, 12pt Arial body, 1.5 line spacing. The Arial/12/
// 1.5 styling lives in the reference template; --toc adds the contents.
const HERE = path.dirname(fileURLToPath(import.meta.url));
const DEFAULT_REFERENCE = process.env.DOCX_REFERENCE || path.join(HERE, 'templates', 'ahg-reference.docx');
const TOC_DEPTH = process.env.DOCX_TOC_DEPTH || '3';

/** Run a command with args (no shell — args passed as array). Optional stdin. */
function run(cmd, args, { input, timeoutMs = 60000 } = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(cmd, args, { stdio: ['pipe', 'pipe', 'pipe'] });
    let out = '', err = '';
    const timer = setTimeout(() => { try { child.kill('SIGKILL'); } catch {} reject(new Error(`${cmd} timed out after ${timeoutMs}ms`)); }, timeoutMs);
    child.stdout.on('data', (d) => { out += d.toString('utf-8'); });
    child.stderr.on('data', (d) => { err += d.toString('utf-8'); });
    child.on('error', (e) => { clearTimeout(timer); reject(new Error(`${cmd} failed to start: ${e.message}`)); });
    child.on('close', (code) => {
      clearTimeout(timer);
      if (code === 0) resolve(out);
      else reject(new Error(`${cmd} exited ${code}: ${err.slice(0, 600) || out.slice(0, 600)}`));
    });
    if (input !== undefined) { child.stdin.write(input); }
    child.stdin.end();
  });
}

function fileSize(p) { try { return fs.statSync(p).size; } catch { return 0; } }

// ---------- tool: docx_from_markdown ----------
async function docxFromMarkdown({ markdown, markdownPath, outputPath, referenceDocx, toc }) {
  if (!markdown && !markdownPath) throw new Error('Provide `markdown` (inline) or `markdownPath` (file).');
  if (markdownPath && !fs.existsSync(markdownPath)) throw new Error(`markdownPath not found: ${markdownPath}`);

  // Resolve output path.
  let out = outputPath;
  if (!out) {
    if (markdownPath) out = markdownPath.replace(/\.(md|markdown|txt)$/i, '') + '.docx';
    else out = path.join(os.tmpdir(), `docx-${Date.now()}-${crypto.randomBytes(3).toString('hex')}.docx`);
  }
  if (!/\.docx$/i.test(out)) out += '.docx';
  fs.mkdirSync(path.dirname(out), { recursive: true });

  const args = ['-f', 'gfm', '-t', 'docx'];
  // House standard: TOC on by default (pass toc:false to suppress).
  if (toc !== false) args.push('--toc', `--toc-depth=${TOC_DEPTH}`);
  args.push('-o', out);
  // House standard: Arial 12 / 1.5 spacing via the reference template, unless
  // the caller supplies their own.
  const ref = referenceDocx || (fs.existsSync(DEFAULT_REFERENCE) ? DEFAULT_REFERENCE : null);
  if (ref) {
    if (!fs.existsSync(ref)) throw new Error(`referenceDocx not found: ${ref}`);
    args.push(`--reference-doc=${ref}`);
  }
  if (markdownPath) args.push(markdownPath);

  await run(PANDOC, args, markdownPath ? {} : { input: markdown });
  const bytes = fileSize(out);
  if (bytes < 200) throw new Error(`pandoc produced an empty/too-small file (${bytes} bytes)`);
  return { outputPath: out, bytes, engine: 'pandoc', format: 'docx', toc: toc !== false, reference: ref || null };
}

// ---------- tool: docx_read ----------
async function docxRead({ path: docxPath, to }) {
  if (!docxPath) throw new Error('`path` (the .docx to read) is required.');
  if (!fs.existsSync(docxPath)) throw new Error(`path not found: ${docxPath}`);
  const target = (to === 'plain') ? 'plain' : 'gfm';
  const text = await run(PANDOC, ['-f', 'docx', '-t', target, docxPath]);
  return { path: docxPath, format: target, chars: text.length, text };
}

// ---------- tool: docx_to_pdf ----------
async function docxToPdf({ path: srcPath, outputDir }) {
  if (!srcPath) throw new Error('`path` (the .docx or .md to convert) is required.');
  if (!fs.existsSync(srcPath)) throw new Error(`path not found: ${srcPath}`);
  const dir = outputDir || path.dirname(srcPath);
  fs.mkdirSync(dir, { recursive: true });

  // Markdown source: convert to docx first so LibreOffice gets clean styling.
  let workPath = srcPath, tmpDocx = null;
  if (/\.(md|markdown)$/i.test(srcPath)) {
    tmpDocx = path.join(os.tmpdir(), `pdfsrc-${Date.now()}.docx`);
    await run(PANDOC, ['-f', 'gfm', '-t', 'docx', '-o', tmpDocx, srcPath]);
    workPath = tmpDocx;
  }
  await run(SOFFICE, ['--headless', '--nologo', '--norestore', '--convert-to', 'pdf', '--outdir', dir, workPath], { timeoutMs: SOFFICE_TIMEOUT_MS });
  const pdf = path.join(dir, path.basename(workPath).replace(/\.[^.]+$/, '') + '.pdf');
  if (tmpDocx) { try { fs.unlinkSync(tmpDocx); } catch {} }
  const bytes = fileSize(pdf);
  if (bytes < 200) throw new Error(`PDF not produced (${bytes} bytes)`);
  return { outputPath: pdf, bytes, engine: 'libreoffice', format: 'pdf' };
}

// ---------- MCP wiring ----------
const TOOLS = [
  {
    name: 'docx_from_markdown',
    description: 'Convert Markdown (inline text or a .md file) into a Word .docx. GitHub-flavoured markdown so pipe-tables become real Word tables. Applies the AHG house standard by default: table of contents, heading styles, 12pt Arial body, 1.5 line spacing. Returns the output path.',
    inputSchema: {
      type: 'object',
      properties: {
        markdown: { type: 'string', description: 'Inline Markdown content (use this OR markdownPath).' },
        markdownPath: { type: 'string', description: 'Absolute path to a .md file (use this OR markdown).' },
        outputPath: { type: 'string', description: 'Absolute output .docx path. Defaults next to the .md, or a temp file for inline input.' },
        referenceDocx: { type: 'string', description: 'Optional .docx whose styles override the default AHG template (Arial 12 / 1.5 spacing).' },
        toc: { type: 'boolean', description: 'Include a table of contents. Default true (house standard).', default: true },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'docx_read',
    description: 'Extract the text of a .docx as GitHub-flavoured Markdown (headings + tables preserved) so it can be read/summarised. Set to:"plain" for plain text.',
    inputSchema: {
      type: 'object',
      properties: {
        path: { type: 'string', description: 'Absolute path to the .docx file.' },
        to: { type: 'string', enum: ['gfm', 'plain'], description: 'Output format. Default "gfm".', default: 'gfm' },
      },
      required: ['path'],
      additionalProperties: false,
    },
  },
  {
    name: 'docx_to_pdf',
    description: 'Convert a .docx (or .md) to PDF via LibreOffice headless — for tender/submission output. Returns the PDF path.',
    inputSchema: {
      type: 'object',
      properties: {
        path: { type: 'string', description: 'Absolute path to the .docx or .md to convert.' },
        outputDir: { type: 'string', description: 'Optional output directory. Defaults to the source file directory.' },
      },
      required: ['path'],
      additionalProperties: false,
    },
  },
];

const server = new Server({ name: 'docx', version: '0.1.0' }, { capabilities: { tools: {} } });

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const { name, arguments: args } = req.params;
  try {
    let result;
    if (name === 'docx_from_markdown') result = await docxFromMarkdown(args || {});
    else if (name === 'docx_read') result = await docxRead(args || {});
    else if (name === 'docx_to_pdf') result = await docxToPdf(args || {});
    else throw new Error(`Unknown tool: ${name}`);
    return { content: [{ type: 'text', text: JSON.stringify(result, null, 2) }] };
  } catch (err) {
    return { content: [{ type: 'text', text: `Error calling ${name}: ${err.message}` }], isError: true };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
