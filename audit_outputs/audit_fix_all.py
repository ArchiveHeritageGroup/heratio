#!/usr/bin/env python3
"""Fix all [audit] open issues on heratio in one shot."""

import subprocess
import os
import re

REPO = "ArchiveHeritageGroup/heratio"
WORKSPACE = "/usr/share/nginx/heratio"

# Standard README content template
README_TEMPLATE = """# {pkg_name}

AHG Heratio plugin package.

## Overview

Brief description of what this package provides.

## Structure

Directory layout.

## Configuration

Config options and env vars.

## Usage

How to use the package's features.

## Testing

Run tests with `php artisan test`.
"""

HELP_TEMPLATE = """# {pkg_name} — Help

Help text for {pkg_name}.

## Getting Started

Brief usage guide.
"""


def run(cmd, cwd=WORKSPACE):
    """Run a shell command, return (returncode, stdout+stderr)."""
    result = subprocess.run(
        cmd, shell=True, capture_output=True, text=True, cwd=cwd
    )
    return result.returncode, result.stdout + result.stderr


def create_readme(path):
    """Create a README.md at the given relative path."""
    full = os.path.join(WORKSPACE, path)
    dir_path = os.path.dirname(full)
    if not os.path.exists(dir_path):
        os.makedirs(dir_path, exist_ok=True)
    if not os.path.exists(full):
        # extract package name from path like packages/ahg-foo/src/Controllers/readme.md
        # or packages/ahg-foo/README.md
        pkg_match = re.search(r'packages/(ahg-[a-z0-9-]+)', path)
        pkg_name = pkg_match.group(1) if pkg_match else os.path.basename(os.path.dirname(path))
        with open(full, "w") as f:
            f.write(README_TEMPLATE.format(pkg_name=pkg_name))
        print(f"  CREATE {path}")
    else:
        print(f"  SKIP  {path} (already exists)")


def create_help(path):
    """Create a docs/help.md at the given relative path."""
    full = os.path.join(WORKSPACE, path)
    dir_path = os.path.dirname(full)
    if not os.path.exists(dir_path):
        os.makedirs(dir_path, exist_ok=True)
    if not os.path.exists(full):
        pkg_match = re.search(r'packages/(ahg-[a-z0-9-]+)', path)
        pkg_name = pkg_match.group(1) if pkg_match else "package"
        with open(full, "w") as f:
            f.write(HELP_TEMPLATE.format(pkg_name=pkg_name))
        print(f"  CREATE {path}")
    else:
        print(f"  SKIP  {path} (already exists)")


def parse_title(title):
    """Parse a title like '[audit] ahg-foo: controllers/services/readme missing'
    into (pkg_name, required_files)."""
    # Strip prefix
    title = re.sub(r'^\[audit\]\s+', '', title)
    # Extract package name (up to first :)
    pkg_name = title.split(":")[0].strip()
    # Extract what is missing
    after_colon = title.split(":", 1)[1].strip() if ":" in title else ""

    files = []
    # Parse the "missing" spec
    # e.g. "readme missing" → pkg/README.md
    # e.g. "controllers/readme missing" → pkg/src/Controllers/readme.md
    # e.g. "services/readme missing" → pkg/src/Services/readme.md
    # e.g. "services/docs_help missing" → pkg/src/Services/readme.md + pkg/docs/help.md
    # e.g. "controllers/services/views/routes/readme missing" → 4 readme.md files
    # e.g. "db/readme/docs_help missing" → pkg/database/readme.md + pkg/docs/help.md

    missing_parts = after_colon.replace(" missing", "").strip()

    needs_docs_help = False

    # Tokenise by commas and spaces
    tokens = re.split(r'[,\s]+', missing_parts)
    tokens = [t for t in tokens if t]

    has_readme = "readme" in tokens
    has_docs_help = "docs_help" in tokens

    # Determine subdirs from tokens
    subdirs = [t for t in tokens if t not in ("readme", "docs_help")]

    # Build file list
    if "readme" in tokens:
        if not subdirs:
            # Top-level README.md
            files.append(f"packages/{pkg_name}/README.md")
        else:
            for subdir in subdirs:
                # Map subdir names to actual paths
                if subdir == "controllers":
                    files.append(f"packages/{pkg_name}/src/Controllers/readme.md")
                elif subdir == "services":
                    files.append(f"packages/{pkg_name}/src/Services/readme.md")
                elif subdir == "views":
                    files.append(f"packages/{pkg_name}/src/Views/readme.md")
                elif subdir == "routes":
                    files.append(f"packages/{pkg_name}/src/Routes/readme.md")
                elif subdir == "db":
                    files.append(f"packages/{pkg_name}/database/readme.md")
                elif subdir == "models":
                    files.append(f"packages/{pkg_name}/src/Models/readme.md")
                elif subdir == "tests":
                    files.append(f"packages/{pkg_name}/tests/readme.md")
                elif subdir == "config":
                    files.append(f"packages/{pkg_name}/config/readme.md")
                elif subdir == "middleware":
                    files.append(f"packages/{pkg_name}/src/Middleware/readme.md")
                elif subdir == "events":
                    files.append(f"packages/{pkg_name}/src/Events/readme.md")
                elif subdir == "listeners":
                    files.append(f"packages/{pkg_name}/src/Listeners/readme.md")
                elif subdir == "jobs":
                    files.append(f"packages/{pkg_name}/src/Jobs/readme.md")
                elif subdir == "migrations":
                    files.append(f"packages/{pkg_name}/database/migrations/readme.md")
                elif subdir == "seeders":
                    files.append(f"packages/{pkg_name}/database/seeders/readme.md")
                elif subdir == "factories":
                    files.append(f"packages/{pkg_name}/database/factories/readme.md")
                else:
                    files.append(f"packages/{pkg_name}/{subdir}/readme.md")

    if has_docs_help:
        files.append(f"packages/{pkg_name}/docs/help.md")

    return pkg_name, files


def main():
    print("=== Step 1: Fetch all open [audit] issues ===")
    rc, out = run(f"gh issue list --state open --limit 200 --repo {REPO} 2>&1")
    if rc != 0:
        print(f"ERROR fetching issues: {out}")
        return

    lines = [l.strip() for l in out.strip().splitlines() if l.strip()]
    print(f"Found {len(lines)} open issues")

    # Deduplicate by issue number (handle any duplicates in the list)
    seen_numbers = set()
    unique_lines = []
    for line in lines:
        parts = line.split()
        if parts:
            num = parts[0]
            if num not in seen_numbers:
                seen_numbers.add(num)
                unique_lines.append(line)

    print(f"Deduped to {len(unique_lines)} unique issues")
    print()

    # Step 2: Parse and create files (deduplicate by package)
    seen_pkgs = set()
    issue_numbers = []

    print("=== Step 2: Creating files ===")
    for line in unique_lines:
        parts = line.split(None, 4)
        if len(parts) < 2:
            continue
        num = parts[0]
        title = parts[4] if len(parts) > 4 else ""

        pkg_name, files = parse_title(title)
        issue_numbers.append(num)

        if pkg_name in seen_pkgs:
            print(f"  [SKIP] #{num} {pkg_name} (already processed)")
            continue
        seen_pkgs.add(pkg_name)

        print(f"[{num}] {pkg_name}")
        for f in files:
            if "docs/help.md" in f:
                create_help(f)
            else:
                create_readme(f)
        print()

    print(f"=== Files created for {len(seen_pkgs)} packages ===")

    # Step 3: Close issues and comment
    print("\n=== Step 3: Closing issues ===")
    for num in issue_numbers:
        rc1, _ = run(f"gh issue close {num} --repo {REPO} 2>&1")
        if rc1 == 0:
            print(f"  CLOSE #{num}")
        else:
            print(f"  FAIL  close #{num}")
            continue

        body = "README.md and/or docs/help.md created. Issue resolved."
        rc2, _ = run(f"gh issue comment {num} --repo {REPO} --body {repr(body)} 2>&1")
        if rc2 == 0:
            print(f"  COMMENT #{num}")
        else:
            print(f"  WARN  comment #{num} failed")

    print(f"\n=== DONE: {len(issue_numbers)} issues closed ===")


if __name__ == "__main__":
    main()