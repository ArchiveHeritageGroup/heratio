#!/usr/bin/env python3
"""
phase3_stub_fixer.py
====================
Write proper README stubs + docs/help + database + routes stubs for all 100
packages in the Heratio audit. Updates are based on what actually EXISTS now
(not the stale audit data).

For packages with GH# already assigned (538-626): use that number.
For packages still TBD (the 11): use placeholder GH# in README; will be
  updated when GH issues are created.
"""

import json, os, re
from pathlib import Path

HERATIO   = Path("/usr/share/nginx/heratio")
PACKAGES  = HERATIO / "packages"
DOCS_HELP = HERATIO / "docs" / "help"
OUT       = HERATIO / "audit_outputs"

GH_MAP    = json.loads((OUT / "gh_issue_map.json").read_text())
AUDIT_RAW = json.loads((OUT / "full_audit_111_raw.json").read_text())

# Build package → missing list from audit
AUDIT_MISSING = {r["package"]: r.get("missing", []) for r in AUDIT_RAW}

# ── helpers ──────────────────────────────────────────────────────────────────

def short_name(pkg):
    return pkg.replace("ahg-", "").replace("-", "-")

def doc_filename(pkg):
    """Derive docs/help filename from package name."""
    s = pkg.replace("ahg-", "")
    # known explicit mappings
    KNOWN = {
        "ahg-annotations":      "annotations-user-guide.md",
        "ahg-api-plugin":        "api-plugin-user-guide.md",
        "ahg-media-streaming":   "media-streaming-user-guide.md",
        "ahg-3d-model":          "3d-model-user-guide.md",
        "ahg-access-request":    "access-request-user-guide.md",
        "ahg-accession-manage":  "accession-manage-user-guide.md",
        "ahg-actor-manage":      "actor-manage-user-guide.md",
        "ahg-ai-services":       "ai-services-user-guide.md",
        "ahg-annotations":       "annotations-user-guide.md",
        "ahg-api":              "api-user-guide.md",
        "ahg-api-plugin":        "api-plugin-user-guide.md",
        "ahg-audit-trail":      "audit-trail-user-guide.md",
        "ahg-authority-resolution": "authority-resolution-user-guide.md",
        "ahg-backup":            "backup-user-guide.md",
        "ahg-cart":             "cart-user-guide.md",
        "ahg-cdpa":             "cdpa-user-guide.md",
        "ahg-condition":        "condition-user-guide.md",
        "ahg-core":             "core-user-guide.md",
        "ahg-custom-fields":    "custom-fields-user-guide.md",
        "ahg-dacs-manage":      "dacs-manage-user-guide.md",
        "ahg-dam":              "dam-user-guide.md",
        "ahg-data-migration":   "data-migration-user-guide.md",
        "ahg-dc-manage":        "dc-manage-user-guide.md",
        "ahg-dedupe":           "dedupe-user-guide.md",
        "ahg-discovery":       "discovery-user-guide.md",
        "ahg-display":          "display-user-guide.md",
        "ahg-doi":              "doi-user-guide.md",
        "ahg-doi-manage":       "doi-manage-user-guide.md",
        "ahg-donor-manage":     "donor-manage-user-guide.md",
        "ahg-dropdown-manage":  "dropdown-manage-user-guide.md",
        "ahg-exhibition":       "exhibition-user-guide.md",
        "ahg-export":           "export-user-guide.md",
        "ahg-extended-rights":  "extended-rights-user-guide.md",
        "ahg-favorites":        "favorites-user-guide.md",
        "ahg-federation":       "federation-user-guide.md",
        "ahg-feedback":         "feedback-user-guide.md",
        "ahg-forms":            "forms-user-guide.md",
        "ahg-ftp-upload":       "ftp-upload-user-guide.md",
        "ahg-function-manage": "function-manage-user-guide.md",
        "ahg-functions-docs":  "functions-docs-user-guide.md",
        "ahg-gallery":          "gallery-user-guide.md",
        "ahg-gis":              "gis-user-guide.md",
        "ahg-graphql":          "graphql-user-guide.md",
        "ahg-help":             "help-user-guide.md",
        "ahg-heritage-manage":  "heritage-manage-user-guide.md",
        "ahg-icip":             "icip-user-guide.md",
        "ahg-iiif-collection":  "iiif-collection-user-guide.md",
        "ahg-image-ar":         "image-ar-user-guide.md",
        "ahg-information-object-manage": "information-object-manage-user-guide.md",
        "ahg-ingest":           "ingest-user-guide.md",
        "ahg-integrity":        "integrity-user-guide.md",
        "ahg-ipsas":            "ipsas-user-guide.md",
        "ahg-jobs":             "jobs-user-guide.md",
        "ahg-jobs-manage":      "jobs-manage-user-guide.md",
        "ahg-label":            "label-user-guide.md",
        "ahg-landing-page":     "landing-page-user-guide.md",
        "ahg-library":          "library-user-guide.md",
        "ahg-loan":             "loan-user-guide.md",
        "ahg-marketplace":      "marketplace-user-guide.md",
        "ahg-media-processing":"media-processing-user-guide.md",
        "ahg-media-streaming":  "media-streaming-user-guide.md",
        "ahg-menu-manage":      "menu-manage-user-guide.md",
        "ahg-metadata-export":  "metadata-export-user-guide.md",
        "ahg-metadata-extraction": "metadata-extraction-user-guide.md",
        "ahg-mods-manage":      "mods-manage-user-guide.md",
        "ahg-multi-tenant":     "multi-tenant-user-guide.md",
        "ahg-museum":           "museum-user-guide.md",
        "ahg-narssa":           "narssa-user-guide.md",
        "ahg-naz":              "naz-user-guide.md",
        "ahg-nmmz":             "nmmz-user-guide.md",
        "ahg-oai":              "oai-user-guide.md",
        "ahg-pdf-tools":        "pdf-tools-user-guide.md",
        "ahg-portable-export":  "portable-export-user-guide.md",
        "ahg-preservation":     "preservation-user-guide.md",
        "ahg-privacy":         "privacy-user-guide.md",
        "ahg-provenance":      "provenance-user-guide.md",
        "ahg-provenance-ai":    "provenance-ai-user-guide.md",
        "ahg-rad-manage":       "rad-manage-user-guide.md",
        "ahg-records-manage":   "records-manage-user-guide.md",
        "ahg-reports":          "reports-user-guide.md",
        "ahg-repository-manage":"repository-manage-user-guide.md",
        "ahg-request-publish": "request-publish-user-guide.md",
        "ahg-research":        "research-user-guide.md",
        "ahg-researcher-manage":"researcher-manage-user-guide.md",
        "ahg-rights":           "rights-user-guide.md",
        "ahg-rights-holder-manage": "rights-holder-manage-user-guide.md",
        "ahg-scan":             "scan-user-guide.md",
        "ahg-search":           "search-user-guide.md",
        "ahg-security-clearance": "security-clearance-user-guide.md",
        "ahg-semantic-search":  "semantic-search-user-guide.md",
        "ahg-settings":        "settings-user-guide.md",
        "ahg-share-link":       "share-link-user-guide.md",
        "ahg-static-page":      "static-page-user-guide.md",
        "ahg-statistics":        "statistics-user-guide.md",
        "ahg-storage-manage":   "storage-manage-user-guide.md",
        "ahg-term-taxonomy":     "term-taxonomy-user-guide.md",
        "ahg-theme-b5":          "theme-b5-user-guide.md",
        "ahg-translation":       "translation-user-guide.md",
        "ahg-user-manage":       "user-manage-user-guide.md",
        "ahg-vendor":            "vendor-user-guide.md",
        "ahg-version-control":  "version-control-user-guide.md",
        "ahg-workflow":          "workflow-user-guide.md",
    }
    return KNOWN.get(pkg, s + "-user-guide.md")

# ── README stub generator ──────────────────────────────────────────────────────

def build_readme(pkg, gh_num, missing_list):
    short = pkg.replace("ahg-", "")
    doc_fname = doc_filename(pkg)

    # Determine what's actually present now
    pkg_path = PACKAGES / pkg
    present = {
        "Controllers":   os.path.exists(pkg_path / "src" / "Controllers"),
        "Services":      os.path.exists(pkg_path / "src" / "Services"),
        "views":         os.path.exists(pkg_path / "resources" / "views"),
        "routes":        os.path.exists(pkg_path / "routes"),
        "db":            os.path.exists(pkg_path / "database"),
        "docs_help":     (DOCS_HELP / doc_fname).exists(),
    }

    gh_display = str(gh_num) if gh_num else "TBD"
    gh_url = f"https://github.com/ArchiveHeritageGroup/heratio/issues/{gh_display}" if gh_num else "#"

    stub = f"""# {pkg}

> Heratio package — see [GH issue #{gh_display}]({gh_url}).
> Status: **audit-in-progress** — {len(missing_list)} missing item(s): {', '.join(missing_list)}.

## Package overview

> TODO: Describe what `{pkg}` does in Heratio.

## Source structure

| Component | Status |
|-----------|--------|
| `src/Controllers/` | {'✅ Present' if present['Controllers'] else '❌ Missing'} |
| `src/Services/` | {'✅ Present' if present['Services'] else '❌ Missing'} |
| `resources/views/` | {'✅ Present' if present['views'] else '❌ Missing'} |
| `routes/` | {'✅ Present' if present['routes'] else '❌ Missing'} |
| `database/` | {'✅ Present' if present['db'] else '❌ Missing'} |
| `docs/help/{doc_fname}` | {'✅ Present' if present['docs_help'] else '❌ Missing'} |

## Missing items checklist

"""
    for m in missing_list:
        stub += f"- [ ] **{m}**\n"

    stub += f"""
## Next steps

1. Implement the missing items listed above.
2. Run the audit checker to verify: `python audit_checker.py {pkg}`.
3. When all items are present, close [GH issue #{gh_display}]({gh_url}) with a comment
   confirming the package is scaffolded and ready for full implementation.

## References

- Source: `packages/{pkg}/`
- docs/help: `docs/help/{doc_fname}`
- GH issue: [#{gh_display}]({gh_url})
"""
    return stub

# ── docs/help stub generator ──────────────────────────────────────────────────

DOC_HELP_TEMPLATE = """# {pkg_human}

> Auto-generated help page stub — expand with full documentation.
> See [GH issue #{gh}](https://github.com/ArchiveHeritageGroup/heratio/issues/{gh}).

## Overview

> TODO: Write overview of this package's purpose and scope.

## Key features

> TODO: List key features and capabilities.

## Configuration

> TODO: Document configuration options and environment variables.

## Usage

> TODO: Provide step-by-step usage guide.

## Known issues

> TODO: Document known limitations.

## References

- Source: `packages/{pkg}/`
- GH Issue: [#{gh}](https://github.com/ArchiveHeritageGroup/heratio/issues/{gh})
"""

# ── DB stub generator ─────────────────────────────────────────────────────────

def build_db_stub(pkg, gh_num):
    import datetime
    today = datetime.date.today().isoformat()
    return f"""-- ahg-{pkg} / database/install.sql
-- Auto-generated stub — replace with actual schema/migrations.
-- See GH issue #{gh_num} for implementation plan.
-- Generated: {today}
"""

# ── routes stub generator ─────────────────────────────────────────────────────

def build_routes_stub(pkg):
    return f"""<?php
// routes/web.php — {pkg}
// Auto-generated stub. Add actual routes below.
use Illuminate\\Support\\Facades\\Route;

// Route::prefix('{pkg.replace("ahg-","")}')->group(function () {{
//     // TODO: add routes
// }});
"""

# ── MAIN ────────────────────────────────────────────────────────────────────────

written = []
log_lines = []

def note(msg):
    print(msg)
    log_lines.append(msg)

for r in AUDIT_RAW:
    pkg = r["package"]
    missing = r.get("missing", [])

    # Skip the 3 fully implemented ones
    if r.get("status") == "FULLY IMPLEMENTED":
        note(f"[SKIP-FULL] {pkg} — fully implemented")
        continue

    # Get GH# from map
    gh_val = GH_MAP.get(pkg)
    if gh_val is None:
        gh_num = None
    else:
        try:
            gh_num = int(gh_val)
        except (TypeError, ValueError):
            gh_num = None

    pkg_path = PACKAGES / pkg
    doc_fname = doc_filename(pkg)
    doc_path = DOCS_HELP / doc_fname

    stub_count = 0

    # 1. README
    readme_path = pkg_path / "README.md"
    readme_current = open(readme_path).read() if readme_path.exists() else ""
    if "#TBD" in readme_current or gh_num is None or len(readme_current.strip()) < 40:
        content = build_readme(pkg, gh_num, missing)
        with open(readme_path, "w") as f:
            f.write(content)
        note(f"[WROTE] {pkg}/README.md  (GH #{gh_num or 'TBD'})")
        stub_count += 1
    else:
        note(f"[OK   ] {pkg}/README.md  already current")

    # 2. docs/help (only if missing and not already present)
    if "docs_help" in missing and not doc_path.exists():
        content = DOC_HELP_TEMPLATE.format(
            pkg=pkg,
            pkg_human=pkg.replace("ahg-", "").replace("-", " ").title(),
            gh=gh_num or "TBD"
        )
        with open(doc_path, "w") as f:
            f.write(content)
        note(f"[WROTE] docs/help/{doc_fname}")
        stub_count += 1
    elif doc_path.exists():
        note(f"[OK   ] docs/help/{doc_fname}  already exists")

    # 3. database/install.sql (only if missing and not already present)
    if "db" in missing:
        db_dir = pkg_path / "database"
        db_file = db_dir / "install.sql"
        if not db_file.exists():
            db_dir.mkdir(exist_ok=True)
            with open(db_file, "w") as f:
                f.write(build_db_stub(pkg, gh_num or "TBD"))
            note(f"[WROTE] {pkg}/database/install.sql")
            stub_count += 1
        else:
            note(f"[OK   ] {pkg}/database/install.sql  already exists")

    # 4. routes/web.php (only if missing and not already present)
    if "routes" in missing:
        routes_dir = pkg_path / "routes"
        routes_file = routes_dir / "web.php"
        if not routes_file.exists():
            routes_dir.mkdir(exist_ok=True)
            with open(routes_file, "w") as f:
                f.write(build_routes_stub(pkg))
            note(f"[WROTE] {pkg}/routes/web.php")
            stub_count += 1
        else:
            note(f"[OK   ] {pkg}/routes/web.php  already exists")

    if stub_count > 0:
        written.append((pkg, stub_count))

note(f"\nDone. {len(written)} packages updated.")
log_path = OUT / "phase3_stub_log.txt"
open(log_path, "w").write("\n".join(log_lines))
note(f"Log: {log_path}")