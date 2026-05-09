# KM ingest `.km-ingest` marker file format

How a project on the `theahg.co.za` host opts itself into the
KM ingest pipeline (watcher + indexer). Drop a `.km-ingest` JSON
file at the project root and both the watcher
(`/usr/local/sbin/km-ingest-watcher.sh`) and the indexer
(`/opt/ai/km/ingest_heratio.py`) discover it on next restart /
ingest tick.

Replaces the previous pattern of hand-editing two files per project.

## Discovery

Both processes glob `/usr/share/nginx/*/.km-ingest`. Any project
directory under `/usr/share/nginx/` with a marker is included; any
without is invisible to the pipeline.

## Format

```json
{
  "watch": {
    "dirs":  ["docs"],
    "files": ["CLAUDE.md"]
  },
  "ingest": {
    "doc_sources": [
      { "path": "docs",                "category": "myproject-docs" }
    ],
    "root_files": [
      { "path": "CLAUDE.md",           "category": "myproject", "title": "MyProject Overview" }
    ]
  }
}
```

All paths are **relative to the project root** (the directory
containing `.km-ingest`).

### `watch` block (consumed by the watcher)

- `dirs` - directories that inotifywait monitors recursively. Any
  `.md` / `.py` write under these triggers a debounced ingest.
- `files` - individual files that inotifywait monitors directly.

### `ingest` block (consumed by `ingest_heratio.py`)

- `doc_sources` - directories the indexer crawls for `.md` files.
  Each chunk gets the declared `category` as metadata.
- `root_files` - specific files (with optional title) to ingest as
  single documents. `title` overrides the heading inferred from the
  file content.

## Categories

`category` is free-form text and becomes a metadata field on every
ingested chunk. KM consumers (e.g. workbench retrieval, /api/search
clients) can filter on it. Convention:

- Use a stable slug per project: `heratio-developer`, `workbench-docs`,
  `registry-spec`.
- Avoid renaming categories after they ship - downstream consumers
  may filter on them.

## Worked examples

### Heratio (Laravel monorepo with many doc sub-trees)

`/usr/share/nginx/heratio/.km-ingest`:

```json
{
  "watch": {
    "dirs":  ["docs", "docker"],
    "files": ["CLAUDE.md", "README.md"]
  },
  "ingest": {
    "doc_sources": [
      { "path": "docs/user-manual",     "category": "heratio-user-manual" },
      { "path": "docs/install",         "category": "heratio-install" },
      { "path": "docs/getting-started", "category": "heratio-getting-started" },
      { "path": "docs/developer",       "category": "heratio-developer" },
      { "path": "docs/technical",       "category": "heratio-technical" },
      { "path": "docs/operations",      "category": "heratio-operations" },
      { "path": "docs/reference",       "category": "heratio-reference" },
      { "path": "docs/adr",             "category": "heratio-adr" }
    ],
    "root_files": [
      { "path": "CLAUDE.md",        "category": "heratio",        "title": "Heratio Project Overview" },
      { "path": "README.md",        "category": "heratio",        "title": "Heratio README" },
      { "path": "docker/README.md", "category": "heratio-docker", "title": "Heratio Docker Stack Reference" }
    ]
  }
}
```

### Workbench (smaller, one docs tree)

`/usr/share/nginx/workbench/.km-ingest`:

```json
{
  "watch": {
    "dirs":  ["docs"],
    "files": ["CLAUDE.md"]
  },
  "ingest": {
    "doc_sources": [
      { "path": "docs", "category": "workbench-docs" }
    ],
    "root_files": [
      { "path": "CLAUDE.md", "category": "workbench", "title": "Workbench Project Overview" }
    ]
  }
}
```

## Operator workflow when adding a new project

1. Drop `.km-ingest` at `/usr/share/nginx/<projectname>/`.
2. `systemctl restart km-ingest-watcher.service` so the watcher
   re-globs and picks up the new dirs/files.
3. Optional: `systemctl start km-ingest.service` to populate KM
   immediately (otherwise the next 2-hour timer tick or any future
   `.md` write under the new project's watched paths triggers it).

That's the entire workflow. No edits to the watcher script, no edits
to the indexer.

## Validation

Before relying on a new marker file, dry-run the discovery from the
indexer side:

```bash
sudo /opt/ai/km/venv/bin/python -c "
import json, glob, os
for m in sorted(glob.glob('/usr/share/nginx/*/.km-ingest')):
    cfg = json.load(open(m))
    root = os.path.dirname(m)
    print(f'{root}:')
    for s in cfg.get('ingest', {}).get('doc_sources', []):
        print(f'  doc {s[\"category\"]:30s} <- {s[\"path\"]}')
    for f in cfg.get('ingest', {}).get('root_files', []):
        print(f'  file {f[\"category\"]:30s} <- {f[\"path\"]}')
"
```

If a marker is malformed or a path is missing, that prints obvious
errors before you wait the 30-minute-ish full ingest cycle.

## Backward-compat / non-project paths

Some hardcoded entries in `ingest_heratio.py` are NOT projects (the
auto-generated `auto_heratio_kb.md`, the `archive/atom-framework`
sub-tree, etc.). Those stay as inline `DOC_SOURCES` / `ROOT_FILES`
entries because they don't fit the one-marker-per-project model.
Migrating them to per-subproject markers under
`/usr/share/nginx/archive/*/.km-ingest` is a follow-up.
