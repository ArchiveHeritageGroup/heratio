# docx MCP server

Word/DOCX tooling for Claude across all workbench projects. Backed by **pandoc**
(Markdown ⇄ DOCX, table-aware) and **LibreOffice headless** (DOCX/MD → PDF).

## Tools

| Tool | Does |
|---|---|
| `docx_from_markdown` | Markdown (inline or `.md` file) → `.docx`. GitHub-flavoured markdown so pipe-tables become real Word tables. |
| `docx_read` | `.docx` → GitHub-flavoured Markdown (headings + tables preserved) for reading/summarising. `to:"plain"` for plain text. |
| `docx_to_pdf` | `.docx` (or `.md`) → PDF via LibreOffice, for tender/submission output. |

Surface as `mcp__docx__docx_from_markdown`, `mcp__docx__docx_read`, `mcp__docx__docx_to_pdf`.

## House formatting standard (default)

`docx_from_markdown` applies the AHG standard automatically:

- **Table of contents** (`--toc`, depth 3) — pass `toc: false` to suppress.
- **Heading styles** (Word Heading 1–N, not bold text).
- **12 pt Arial body**, **1.5 line spacing** — from `templates/ahg-reference.docx`.

Override the template per-call with `referenceDocx`, or globally with the
`DOCX_REFERENCE` env var. The template was generated from pandoc's default
reference doc with `docDefaults` + theme fonts patched to Arial/12/1.5.

## Wiring (`.mcp.json`)

```json
"docx": {
  "command": "node",
  "args": ["/usr/share/nginx/workbench/.claude/mcp-servers/docx/index.js"]
}
```

Requires `pandoc` and `soffice` (LibreOffice) on PATH — both present on this host.

## Replicating in other projects

Copy this folder into the target repo's `.claude/mcp-servers/docx/`, run
`npm install`, add the `.mcp.json` entry. The "create MD → convert to DOCX"
flow is standard across all projects; this is the reference implementation.
