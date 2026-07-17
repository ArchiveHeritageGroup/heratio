#!/usr/bin/env python3
"""
heratio-portable-gen - standalone portable-viewer generator (#1390 enhancement 2).

Rebuilds a Heratio-style offline portable viewer from a CSV export or an OCFL
object, WITH NO HERATIO PRESENT. Pure Python 3 standard library - no pip, no
framework, no server, no network. The goal is reconstructability: anyone, on any
machine, indefinitely, can turn an OCFL preservation bag (or a flat CSV) back
into a browsable, searchable archive that opens by double-clicking index.html.

This is the "rescue of last resort" companion to the in-app portable export:
the app builds the package while it is alive; this tool rebuilds an equivalent
package from the preserved AIP after the app (and its whole stack) is gone.

Usage:
  # From a flat CSV (+ optional folder of digital-object files):
  heratio-portable-gen.py --csv descriptions.csv --assets ./files --out ./bundle

  # From an OCFL object (reads inventory.json, extracts the latest version):
  heratio-portable-gen.py --ocfl ./ocfl-object --out ./bundle

The output bundle contains:
  index.html      self-contained viewer (tree + search + detail; data inlined)
  data/records.json   the records, as machine-readable JSON
  assets/         copied digital-object files (where available)
  SHA256SUMS      per-file checksums
  verify.sh       offline authenticity check
  README.txt

Copyright (C) 2026 The Archive & Heritage Group. Licensed AGPL-3.0-or-later.
"""

import argparse
import csv
import hashlib
import html
import json
import os
import shutil
import sys
from pathlib import Path

# ---------------------------------------------------------------------------
# Field model - a normalised record. Keys mirror the in-app bundle's ios.json
# so a viewer written for one reads the other.
# ---------------------------------------------------------------------------

# Accepted CSV column aliases (case/space/underscore-insensitive) -> our key.
CSV_ALIASES = {
    "identifier": "identifier", "referencecode": "identifier", "refcode": "identifier",
    "legacyid": "identifier", "id": "identifier",
    "title": "title", "name": "title",
    "level": "level", "levelofdescription": "level",
    "parent": "parent", "parentid": "parent", "parentidentifier": "parent",
    "qubitparentslug": "parent",
    "scopeandcontent": "scope", "scope": "scope", "description": "scope", "abstract": "scope",
    "date": "dates", "dates": "dates", "eventdates": "dates",
    "creator": "creator", "creators": "creator", "eventactors": "creator",
    "repository": "repository", "holdingrepository": "repository",
    "extentandmedium": "extent", "extent": "extent",
    "accessconditions": "access_conditions", "conditionsgoverningaccess": "access_conditions",
    "arrangement": "arrangement",
    "archivalhistory": "archival_history", "custodialhistory": "archival_history",
    "digitalobject": "file", "digitalobjectpath": "file", "filename": "file", "file": "file",
    "slug": "slug",
}

FIELD_LABELS = [
    ("identifier", "Reference code"), ("title", "Title"), ("level", "Level of description"),
    ("dates", "Date(s)"), ("creator", "Creator"), ("repository", "Repository"),
    ("extent", "Extent and medium"), ("scope", "Scope and content"),
    ("arrangement", "System of arrangement"), ("archival_history", "Archival history"),
    ("access_conditions", "Conditions governing access"),
]


def norm_key(k):
    return "".join(ch for ch in k.lower() if ch.isalnum())


def load_from_csv(csv_path, assets_dir):
    records = []
    with open(csv_path, newline="", encoding="utf-8-sig") as fh:
        reader = csv.DictReader(fh)
        colmap = {}
        for col in reader.fieldnames or []:
            key = CSV_ALIASES.get(norm_key(col))
            if key:
                colmap[col] = key
        if "title" not in colmap.values() and "identifier" not in colmap.values():
            sys.exit("error: CSV needs at least a 'title' or 'identifier' column. "
                     "Found: " + ", ".join(reader.fieldnames or []))
        for i, row in enumerate(reader):
            rec = {"_id": i}
            for col, key in colmap.items():
                val = (row.get(col) or "").strip()
                if val:
                    rec[key] = val
            if not rec.get("identifier"):
                rec["identifier"] = rec.get("title", f"record-{i}")
            records.append(rec)
    _resolve_parents(records)
    return records


def _resolve_parents(records):
    # Map parent by identifier -> internal _id so the viewer can build a tree.
    by_ident = {r.get("identifier"): r["_id"] for r in records if r.get("identifier")}
    for r in records:
        p = r.get("parent")
        r["_parent"] = by_ident.get(p, None) if p else None


def load_from_ocfl(ocfl_dir, out_assets):
    inv_path = Path(ocfl_dir) / "inventory.json"
    if not inv_path.is_file():
        sys.exit(f"error: no inventory.json in {ocfl_dir} - not an OCFL object root.")
    inv = json.loads(inv_path.read_text(encoding="utf-8"))
    head = inv.get("head")
    versions = inv.get("versions", {})
    manifest = inv.get("manifest", {})
    state = (versions.get(head, {}) or {}).get("state", {})
    # logical path -> content path (from digest)
    logical = {}
    for digest, lpaths in state.items():
        content_paths = manifest.get(digest, [])
        if content_paths:
            for lp in lpaths:
                logical[lp] = content_paths[0]

    # If the object carries a descriptions CSV/JSON, use it for metadata.
    records = []
    meta_lp = next((lp for lp in logical if lp.lower().endswith(("descriptions.csv", "records.json", "metadata.json"))), None)
    out_assets.mkdir(parents=True, exist_ok=True)
    copied = {}
    for lp, cp in sorted(logical.items()):
        src = Path(ocfl_dir) / cp
        if not src.is_file():
            continue
        dest = out_assets / lp
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dest)
        copied[lp] = f"assets/{lp}"

    if meta_lp and (Path(ocfl_dir) / logical[meta_lp]).is_file():
        mp = Path(ocfl_dir) / logical[meta_lp]
        if meta_lp.lower().endswith(".csv"):
            with open(mp, newline="", encoding="utf-8-sig") as fh:
                reader = csv.DictReader(fh)
                colmap = {c: CSV_ALIASES.get(norm_key(c)) for c in (reader.fieldnames or [])}
                for i, row in enumerate(reader):
                    rec = {"_id": i}
                    for col, key in colmap.items():
                        if key and (row.get(col) or "").strip():
                            rec[key] = row[col].strip()
                    rec.setdefault("identifier", rec.get("title", f"record-{i}"))
                    records.append(rec)
            _resolve_parents(records)
        else:
            data = json.loads(mp.read_text(encoding="utf-8"))
            for i, rec in enumerate(data if isinstance(data, list) else []):
                rec["_id"] = i
                records.append(rec)
    else:
        # No descriptive metadata in the bag: one record per content file.
        for i, (lp, rel) in enumerate(sorted(copied.items())):
            records.append({"_id": i, "identifier": lp, "title": Path(lp).name,
                            "level": "Item", "file": rel, "_parent": None})

    # Attach assets to records by matching the 'file' / identifier to a logical path.
    for r in records:
        cand = r.get("file") or r.get("identifier") or ""
        for lp, rel in copied.items():
            if cand and (lp == cand or Path(lp).name == Path(cand).name):
                r["file"] = rel
                break
    return records


def copy_csv_assets(records, assets_dir, out_assets):
    if not assets_dir:
        return 0
    assets_dir = Path(assets_dir)
    out_assets.mkdir(parents=True, exist_ok=True)
    n = 0
    # index files in the assets dir by basename for loose matching
    index = {}
    for p in assets_dir.rglob("*"):
        if p.is_file():
            index.setdefault(p.name, p)
    for r in records:
        want = r.get("file")
        src = None
        if want:
            cand = assets_dir / want
            src = cand if cand.is_file() else index.get(Path(want).name)
        if src:
            dest = out_assets / src.name
            shutil.copy2(src, dest)
            r["file"] = f"assets/{src.name}"
            n += 1
    return n


def sha256(path):
    h = hashlib.sha256()
    with open(path, "rb") as fh:
        for chunk in iter(lambda: fh.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def write_bundle(records, out_dir, title):
    out = Path(out_dir)
    (out / "data").mkdir(parents=True, exist_ok=True)
    (out / "assets").mkdir(parents=True, exist_ok=True)

    # public records.json (strip internal keys for the machine-readable copy)
    pub = [{k: v for k, v in r.items() if not k.startswith("_") or k in ("_id", "_parent")} for r in records]
    (out / "data" / "records.json").write_text(
        json.dumps(pub, indent=2, ensure_ascii=False), encoding="utf-8")

    # index.html with data INLINED (works from file:// - no fetch needed)
    data_js = json.dumps(pub, ensure_ascii=False)
    html_doc = (VIEWER_HTML
                .replace("__TITLE__", html.escape(title))
                .replace("__FIELDS__", json.dumps(FIELD_LABELS))
                .replace("__DATA__", data_js))
    (out / "index.html").write_text(html_doc, encoding="utf-8")

    # SHA256SUMS + verify.sh over everything shipped
    sums = []
    for p in sorted(out.rglob("*")):
        if p.is_file() and p.name != "SHA256SUMS":
            sums.append(f"{sha256(p)}  {p.relative_to(out).as_posix()}")
    (out / "SHA256SUMS").write_text("\n".join(sums) + "\n", encoding="utf-8")
    (out / "verify.sh").write_text(VERIFY_SH, encoding="utf-8")
    os.chmod(out / "verify.sh", 0o755)

    (out / "README.txt").write_text(README_TXT.format(title=title, n=len(records)), encoding="utf-8")
    return len(sums)


VERIFY_SH = """#!/bin/sh
# Offline authenticity check - verifies every file against SHA256SUMS.
cd "$(dirname "$0")" || exit 1
if command -v sha256sum >/dev/null 2>&1; then sha256sum -c SHA256SUMS
elif command -v shasum >/dev/null 2>&1; then shasum -a 256 -c SHA256SUMS
else echo "Need sha256sum or shasum"; exit 1; fi
"""

README_TXT = """{title}
Rebuilt offline archive - {n} record(s).

This package was rebuilt from a preservation source (OCFL bag or CSV) by the
Heratio standalone portable generator, with NO Heratio system present. Open
index.html in any web browser (double-click it) - no server, install, or
internet needed. Run `sh verify.sh` to confirm nothing was tampered with.
"""

VIEWER_HTML = r"""<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>__TITLE__</title>
<style>
 *{box-sizing:border-box} body{margin:0;font-family:system-ui,Arial,sans-serif;color:#1c2733}
 header{background:#1f3a5f;color:#fff;padding:.7rem 1rem;font-size:1.1rem;font-weight:600}
 .wrap{display:flex;height:calc(100vh - 48px)}
 .side{width:38%;max-width:460px;border-right:1px solid #d7dde3;overflow:auto;padding:.5rem}
 .main{flex:1;overflow:auto;padding:1rem 1.4rem}
 input#q{width:100%;padding:.5rem;border:1px solid #c3ccd4;border-radius:4px;margin-bottom:.5rem}
 ul{list-style:none;margin:0;padding:0} li{margin:0}
 .node>a{display:block;padding:.28rem .4rem;border-radius:4px;cursor:pointer;text-decoration:none;color:#1c2733;font-size:.9rem}
 .node>a:hover{background:#eef2f6} .node>a.sel{background:#1f3a5f;color:#fff}
 .children{margin-left:.9rem;border-left:1px solid #e4e9ee;padding-left:.3rem}
 .lvl{font-size:.7rem;color:#7c8791;text-transform:uppercase;letter-spacing:.03em}
 .node>a.sel .lvl{color:#cfe0f3}
 h1{font-size:1.3rem;margin:.2rem 0 .1rem} .ref{color:#6b7680;font-size:.85rem}
 table{border-collapse:collapse;width:100%;margin-top:1rem} th,td{border:1px solid #e2e7ec;padding:.45rem .6rem;text-align:left;vertical-align:top;font-size:.9rem}
 th{background:#f5f7f9;width:210px;font-weight:600} img,embed{max-width:100%;border:1px solid #e2e7ec;border-radius:4px;margin-top:1rem}
 .muted{color:#7c8791} .count{font-weight:400;font-size:.85rem;opacity:.8;margin-left:.4rem}
</style></head><body>
<header>__TITLE__ <span class="count" id="count"></span></header>
<div class="wrap">
 <div class="side"><input id="q" placeholder="Search title, reference, scope..." autocomplete="off">
   <ul id="tree"></ul></div>
 <div class="main" id="detail"><p class="muted">Select a record on the left.</p></div>
</div>
<script>
const RECORDS = __DATA__;
const byId = {}; RECORDS.forEach(r => byId[r._id] = r);
const kids = {}; RECORDS.forEach(r => { const p = (r._parent==null?'root':r._parent); (kids[p]=kids[p]||[]).push(r); });
const FIELDS = __FIELDS__;
document.getElementById('count').textContent = RECORDS.length + ' records';

function esc(s){const d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML;}
function label(r){return (r.identifier?('<span class="ref">'+esc(r.identifier)+'</span> '):'')+esc(r.title||'(untitled)');}

function renderTree(container, parentKey){
  const list = kids[parentKey]; if(!list) return;
  list.forEach(r=>{
    const li=document.createElement('li'); li.className='node';
    const a=document.createElement('a'); a.href='javascript:void 0'; a.dataset.id=r._id;
    a.innerHTML=(r.level?('<span class="lvl">'+esc(r.level)+'</span><br>'):'')+label(r);
    a.onclick=()=>select(r._id); li.appendChild(a);
    if(kids[r._id]){const c=document.createElement('ul');c.className='children';li.appendChild(c);renderTree(c,r._id);}
    container.appendChild(li);
  });
}
function select(id){
  document.querySelectorAll('#tree a.sel').forEach(e=>e.classList.remove('sel'));
  const a=document.querySelector('#tree a[data-id="'+id+'"]'); if(a)a.classList.add('sel');
  const r=byId[id]; let h='<h1>'+esc(r.title||'(untitled)')+'</h1>';
  if(r.identifier)h+='<div class="ref">'+esc(r.identifier)+'</div>';
  h+='<table>'; FIELDS.forEach(([k,lab])=>{ if(r[k]) h+='<tr><th>'+esc(lab)+'</th><td>'+esc(r[k]).replace(/\n/g,'<br>')+'</td></tr>'; });
  h+='</table>';
  if(r.file){ const f=r.file.toLowerCase();
    if(/\.(jpg|jpeg|png|gif|webp|svg)$/.test(f)) h+='<img src="'+encodeURI(r.file)+'" alt="">';
    else if(f.endsWith('.pdf')) h+='<embed src="'+encodeURI(r.file)+'" type="application/pdf" width="100%" height="600">';
    else h+='<p><a href="'+encodeURI(r.file)+'">Download attached file</a></p>';
  }
  document.getElementById('detail').innerHTML=h;
}
function search(term){
  term=term.trim().toLowerCase();
  const tree=document.getElementById('tree'); tree.innerHTML='';
  if(!term){ renderTree(tree,'root'); return; }
  const hits=RECORDS.filter(r=>['identifier','title','scope','creator','dates'].some(k=>r[k]&&String(r[k]).toLowerCase().includes(term)));
  const ul=document.createElement('ul');
  hits.forEach(r=>{const li=document.createElement('li');li.className='node';const a=document.createElement('a');a.href='javascript:void 0';a.dataset.id=r._id;a.innerHTML=label(r);a.onclick=()=>select(r._id);li.appendChild(a);ul.appendChild(li);});
  if(!hits.length)ul.innerHTML='<li class="muted" style="padding:.5rem">No matches.</li>';
  tree.appendChild(ul);
}
document.getElementById('q').addEventListener('input',e=>search(e.target.value));
renderTree(document.getElementById('tree'),'root');
</script></body></html>"""


def main():
    ap = argparse.ArgumentParser(description="Rebuild a Heratio portable viewer from CSV or OCFL, with no Heratio present.")
    src = ap.add_mutually_exclusive_group(required=True)
    src.add_argument("--csv", help="Flat CSV of descriptions")
    src.add_argument("--ocfl", help="OCFL object root (contains inventory.json)")
    ap.add_argument("--assets", help="Folder of digital-object files (CSV mode)")
    ap.add_argument("--out", required=True, help="Output bundle directory")
    ap.add_argument("--title", default="Rebuilt Archive", help="Viewer title")
    args = ap.parse_args()

    out = Path(args.out)
    out_assets = out / "assets"
    if args.csv:
        records = load_from_csv(args.csv, args.assets)
        n_assets = copy_csv_assets(records, args.assets, out_assets)
    else:
        records = load_from_ocfl(args.ocfl, out_assets)
        n_assets = sum(1 for r in records if r.get("file"))

    files = write_bundle(records, out, args.title)
    print(f"Wrote {len(records)} record(s), {n_assets} asset(s), {files} file(s) to {out}/")
    print(f"Open {out}/index.html in a browser, or run: sh {out}/verify.sh")


if __name__ == "__main__":
    main()
