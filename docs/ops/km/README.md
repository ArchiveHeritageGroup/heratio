# KM operator scripts (issues #49 + #58)

Reference copies of the KM-side ingest, audit, and catalogue-build scripts.
**The live copies are at `/opt/ai/km/`** - these in-repo copies exist so the
files are version-tracked alongside the Heratio code that produces the
output they reference.

## What's here

### #49 - Qdrant credential audit

Daily defence-in-depth scan over every Qdrant collection.

| File | Live path |
|---|---|
| `audit-qdrant.py` | `/opt/ai/km/audit-qdrant.py` |
| `km-audit-qdrant.service` | `/etc/systemd/system/km-audit-qdrant.service` |
| `km-audit-qdrant.timer` | `/etc/systemd/system/km-audit-qdrant.timer` (daily 03:30) |

Exit codes: `0` clean, `2` leaks found (systemd marks unit FAILED), `3` Qdrant unreachable.

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
