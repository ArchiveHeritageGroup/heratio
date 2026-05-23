#!/usr/bin/env python3
"""Generate README.md stubs for all packages from audit data."""

import json, os, sys

AUDIT_JSON = "/usr/share/nginx/heratio/audit_outputs/full_audit_111_raw.json"
PACKAGES_DIR = "/usr/share/nginx/heratio/packages"
OUT_STUBS = "/usr/share/nginx/heratio/audit_outputs/readme_stubs"
LOG = "/usr/share/nginx/heratio/audit_outputs/readme_gen_log.txt"

with open(AUDIT_JSON) as f:
    audit_data = json.load(f)

os.makedirs(OUT_STUBS, exist_ok=True)
log_lines = []

def missing_label(missing_list):
    if not missing_list:
        return "✅ ALL ITEMS PRESENT"
    return f"⚠️  {len(missing_list)} MISSING: {', '.join(missing_list)}"

def scan_dir(package, subdirs):
    """Scan package for existing dirs; return dict of found/missing."""
    pkg_path = os.path.join(PACKAGES_DIR, package)
    results = {}
    for sub in subdirs:
        path = os.path.join(pkg_path, sub)
        if os.path.exists(path):
            results[sub] = True
        else:
            results[sub] = False
    return results

subdirs_to_check = ["src/Controllers", "src/Services", "resources/views",
                    "routes", "database", "src/Jobs", "src/Console", "src/Events",
                    "src/Notifications", "tests", "src/Middleware"]

for pkg in audit_data:
    package = pkg["package"]
    missing = pkg.get("missing", [])
    check = pkg.get("check_details", {})

    found = scan_dir(package, subdirs_to_check)

    readme_path = os.path.join(PACKAGES_DIR, package, "README.md")
    readme_exists = os.path.exists(readme_path)

    if readme_exists:
        log_lines.append(f"[SKIP] {package} — README.md already exists")
        continue

    # Build presence table
    present_items = [k for k, v in found.items() if v]
    missing_items = [k for k, v in found.items() if not v]

    stub = f"""# {package}

> Auto-generated stub — see [heratio/issues](https://github.com/ArchiveHeritageGroup/heratio/issues)
> for implementation checklist.

## Package Overview

**Package:** `{package}`  
**Status:** {"Implemented" if not missing else "Needs implementation"}  
**GH Issue:** See [heratio issues](https://github.com/ArchiveHeritageGroup/heratio/issues?q={package})

## Current Presence

| Component | Status |
|-----------|--------|
| Controllers | {"✅ Present" if found.get("src/Controllers") else "❌ Missing"} |
| Services | {"✅ Present" if found.get("src/Services") else "❌ Missing"} |
| Views | {"✅ Present" if found.get("resources/views") else "❌ Missing"} |
| Routes | {"✅ Present" if found.get("routes") else "❌ Missing"} |
| Database | {"✅ Present" if found.get("database") else "❌ Missing"} |
| Jobs | {"✅ Present" if found.get("src/Jobs") else "❌ Missing"} |
| Console | {"✅ Present" if found.get("src/Console") else "❌ Missing"} |
| Events | {"✅ Present" if found.get("src/Events") else "❌ Missing"} |
| Notifications | {"✅ Present" if found.get("src/Notifications") else "❌ Missing"} |
| Tests | {"✅ Present" if found.get("tests") else "❌ Missing"} |
| Middleware | {"✅ Present" if found.get("src/Middleware") else "❌ Missing"} |
| README.md | {"✅ Present" if readme_exists else "❌ Missing"} |
| docs/help | {"✅ Present" if pkg.get("docs_help_files") else "❌ Missing"} |

## Missing Items

"""
    if missing:
        for m in missing:
            stub += f"- [ ] {m}\n"
    else:
        stub += "All items present — no action needed.\n"

    stub += f"""
## Docs / Help Articles

"""
    docs = pkg.get("docs_help_files", [])
    if docs:
        for doc in docs[:10]:
            stub += f"- `{doc}`\n"
        if len(docs) > 10:
            stub += f"- _...and {len(docs)-10} more (see full audit JSON)_\n"
    else:
        stub += "_No help docs found in `docs/help/` for this package._\n"

    stub += f"""
## Installer Checklist

```bash
# 1. Check package exists
ls packages/{package}/

# 2. Verify core structure
ls packages/{package}/src/Controllers/
ls packages/{package}/src/Services/
ls packages/{package}/resources/views/
ls packages/{package}/routes/

# 3. Check database install SQL
ls packages/{package}/database/install.sql

# 4. Add to composer.json + providers config
# 5. Run: php artisan migrate
# 6. Verify routes registered: php artisan route:list | grep {package.replace('ahg-','')}
```
"""

    # Write stub to packages dir
    with open(readme_path, "w") as f:
        f.write(stub)

    log_lines.append(f"[CREATE] {package} — README.md written")

log_lines.append(f"\nTotal: {len(log_lines)} actions")

with open(LOG, "w") as f:
    f.write("\n".join(log_lines))

print(f"Done. Log: {LOG}")
print("\n".join(log_lines))