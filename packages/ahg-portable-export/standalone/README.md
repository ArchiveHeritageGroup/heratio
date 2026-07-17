# Heratio standalone portable generator (#1390 #2)

`heratio-portable-gen.py` rebuilds a Heratio-style **offline portable viewer** from a
**CSV export** or an **OCFL object** - with **no Heratio system present**.

The in-app portable export (the `ahg-portable-export` package) builds a package
while Heratio is running. This tool solves the inverse, reconstructability problem:
turn a preserved **OCFL/BagIt AIP** (or a plain CSV) back into a browsable,
searchable archive **after the whole Heratio stack is gone** - the true "rescue of
last resort" (see #1390 #1 rescue framing).

## Why it exists

- **Zero dependencies.** Pure Python 3 standard library. No pip, no framework, no
  server, no network. Runs on any machine that has Python 3 - today or in 20 years.
- **Preservation-aligned.** Reads an OCFL object's `inventory.json`, extracts the
  latest version's content, and emits an access/DIP viewer. OCFL is what you
  preserve; this regenerates something a human can actually open from it.
- **Self-contained output.** The generated `index.html` inlines its data, so it
  opens by double-click from `file://` - no local web server needed.

## Usage

```sh
# From a flat CSV (+ optional folder of digital-object files):
python3 heratio-portable-gen.py --csv descriptions.csv --assets ./files \
    --out ./bundle --title "Smith Family Collection"

# From an OCFL object root (contains inventory.json):
python3 heratio-portable-gen.py --ocfl ./ocfl-object --out ./bundle
```

Then open `bundle/index.html` in any browser, or run `sh bundle/verify.sh` to
confirm the checksums.

### CSV columns

Column names are matched case/space/underscore-insensitively. Recognised aliases
include: `identifier`/`referenceCode`/`legacyId`, `title`, `level`,
`parent`/`parentId` (a parent's identifier - builds the tree), `scopeAndContent`,
`date(s)`, `creator`, `repository`, `extentAndMedium`, `accessConditions`,
`arrangement`, `archivalHistory`, and `digitalObject`/`filename`/`file` (a path or
filename under `--assets`). At minimum a `title` or `identifier` column is required.
This is broadly compatible with AtoM/ISAD(G) CSV exports.

### OCFL mode

Reads `inventory.json`, resolves the `head` version's `state` through the
`manifest` to the content files, and copies them into `assets/`. If the object
carries a descriptive `descriptions.csv` / `records.json` / `metadata.json` as a
logical path, it is used for the record metadata; otherwise one record is created
per content file (so nothing is lost). Assets are matched back to records by
logical path / filename.

## Output

```
bundle/
  index.html          self-contained viewer (tree + search + detail; data inlined)
  data/records.json   machine-readable records
  assets/             copied digital objects
  SHA256SUMS          per-file checksums
  verify.sh           offline authenticity check (sha256sum / shasum)
  README.txt
```

## Scope (prototype, #1390 #2)

This is the reconstructability prototype the issue asks for. It deliberately keeps
the viewer compact (tree navigation, client-side search, ISAD(G) detail, inline
image/PDF) rather than reproducing every feature of the in-app viewer. It does not
require - and never calls - any part of Heratio.
