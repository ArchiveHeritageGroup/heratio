# KM operator scripts (issues #49 + #58)

Reference copies of the KM-side ingest, audit, and catalogue-build scripts.
**The live copies are at `/opt/ai/km/`** - these in-repo copies exist so the
files are version-tracked alongside the Heratio code that produces the
output they reference.

## What's here

### #49 - Qdrant credential audit + shared redactor

Daily defence-in-depth scan + a single source of truth for the redaction
regex set every ingest script consults inbound.

| File | Live path |
|---|---|
| `audit-qdrant.py` | `/opt/ai/km/audit-qdrant.py` |
| `km-audit-qdrant.service` | `/etc/systemd/system/km-audit-qdrant.service` |
| `km-audit-qdrant.timer` | `/etc/systemd/system/km-audit-qdrant.timer` (daily 03:30) |
| `redact.py` | `/opt/ai/km/redact.py` (shared module - all ingest scripts import from here) |
| `redact_existing.py` | `/opt/ai/km/redact_existing.py` (one-shot in-place re-redaction over existing chunks) |
| `ingest.py` | `/opt/ai/km/ingest.py` (km_threads) - redacts via redact.py |
| `ingest_qa.py` | `/opt/ai/km/ingest_qa.py` (km_qa) - redacts via redact.py |
| `ingest_atom_docs.py` | `/opt/ai/km/ingest_atom_docs.py` (km_heratio - AtoM docs path) - redacts |
| `ingest_ric.py` | `/opt/ai/km/ingest_ric.py` (km_ric) - redacts |
| `ingest_v2101.py` | `/opt/ai/km/ingest_v2101.py` (km_heratio - 2.10.1 docs path) - redacts |
| `ingest_upgrade.py` | `/opt/ai/km/ingest_upgrade.py` (km_heratio - upgrade docs path) - redacts |
| `ingest_heratio.py` | `/opt/ai/km/ingest_heratio.py` (km_heratio - main pass) - redacts |

Exit codes for `audit-qdrant.py`: `0` clean, `2` leaks found (systemd marks unit FAILED), `3` Qdrant unreachable.

audit-qdrant.py hardening notes:
- `PLACEHOLDER_HINTS` skip filter drops docs-style examples (`your-*`, `${VAR}`, `<API_KEY>`, `xxxxxxxx`, `process.env.X`, `ak_live_x*`, truncation suffix `...`) before they count as hits.
- `COMMON_WORDS_AFTER_LABEL` skip set drops field-label-followed-by-whitespace-word false positives (`Password: chosen`, `password: correct`, etc.).
- 4-octet RFC1918 enforcement so date-shaped 3-octet matches (`10.04.2026`) do not trigger.
- Does not strip `<REDACTED>`/`<INTERNAL_IP>`/`<SSH_KEY>` placeholders before scanning - leaves them in so the regex matches them as the value (PLACEHOLDER_HINTS then skips), instead of stripping them and falsely matching the next paragraph word.

### #58 - Function/method/route catalogues for KM retrieval

Five generators that produce the markdown files KM ingests, plus the wrapper
+ timer that re-runs them every 10 minutes.

| File | Live path | Output |
|---|---|---|
| `build_functions_kb.py` | `/opt/ai/km/build_functions_kb.py` | `auto_functions_kb.md` (PHP) |
| `build_functions_kb_js.py` | `/opt/ai/km/build_functions_kb_js.py` | `auto_functions_kb_js.md` (JS) |
| `build_functions_kb_blade.py` | `/opt/ai/km/build_functions_kb_blade.py` | `auto_functions_kb_blade.md` (Blade) |
| `build_functions_kb_py.py` | `/opt/ai/km/build_functions_kb_py.py` | `auto_functions_kb_py.md` (KM Python) |
| `build_functions_kb_routes.py` | `/opt/ai/km/build_functions_kb_routes.py` | `auto_functions_kb_routes.md` (Routes via `php artisan route:list --json`) |
| `build_functions_kb_all.sh` | `/opt/ai/km/build_functions_kb_all.sh` | wrapper |
| `km-build-functions.service` | `/etc/systemd/system/km-build-functions.service` | |
| `km-build-functions.timer` | `/etc/systemd/system/km-build-functions.timer` | every 10 min |

Outputs are referenced from `ingest_heratio.py:ROOT_FILES` so the
`km-ingest-watcher.service` (inotify) picks them up on the next debounce
cycle (~30s after the last write).

## Promoting a change in this repo to the live KM host

```bash
# After editing a script under docs/ops/km/:
sudo cp docs/ops/km/build_functions_kb.py /opt/ai/km/build_functions_kb.py
sudo chmod +x /opt/ai/km/build_functions_kb.py

# For systemd unit changes:
sudo cp docs/ops/km/km-audit-qdrant.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl restart km-audit-qdrant.timer
```

The repo copies are **reference**; the live `/opt/ai/km/` and `/etc/systemd/`
copies are what runs.
