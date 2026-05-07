#!/usr/bin/env python3
"""
build_functions_kb.py - PHP function/method catalogue for KM. Closes part A
of #58.

Walks the Heratio source tree (app, packages, bootstrap, routes, config)
and extracts every public/protected function + method (and private if it
has a docblock) into a markdown catalogue at
/opt/ai/km/auto_functions_kb.md.

Why regex, not nikic/php-parser: speed. The full repo has ~3500 PHP files;
nikic forks PHP per parse and the round-trip is slow. Regex extraction is
70%-correct and good enough for retrieval - the issue body itself
acknowledges this is acceptable. Pathological cases (nested anonymous
functions, attributes on multiline param lists) are skipped, not crashed.

Output shape per method:
    ## <Class FQN>::<methodName>
    **Visibility:** public|protected|private[ static][ abstract][ final]
    **Signature:** (Type $param, ...) [: ReturnType]
    **File:** <path>:<line-from>-<line-to>
    **Calls:** Class::method, $this->method, $obj->method, ...
    **Doc:** First sentence of docblock, ~500 chars max.

@copyright  Johan Pieterse / Plain Sailing
@license    AGPL-3.0-or-later
"""

from __future__ import annotations
import os
import re
import sys
from collections import defaultdict
from pathlib import Path

HERATIO = "/usr/share/nginx/heratio"
ROOTS   = ["app", "packages", "bootstrap", "routes", "config"]
SKIPS   = {"vendor", "node_modules", "cache", "tests", "Tests", "dist", "build"}

OUT_PATH = "/opt/ai/km/auto_functions_kb.md"

# Regexes - kept loose; PHP is hard to lex with regex but methods follow
# a consistent shape in this codebase.
RE_NAMESPACE = re.compile(r'^\s*namespace\s+([A-Za-z0-9_\\]+)\s*;', re.M)
RE_CLASS     = re.compile(r'^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+([A-Za-z0-9_]+)', re.M)
RE_METHOD    = re.compile(
    r'(?P<doc>/\*\*(?:[^*]|\*(?!/))*\*/)?\s*'
    r'(?P<mods>(?:public|protected|private|static|abstract|final|\s)+)\s+'
    r'function\s+(?P<name>[A-Za-z0-9_]+)\s*'
    r'\((?P<params>[^)]*)\)\s*'
    r'(?P<ret>:\s*[A-Za-z0-9_\\\?\|\&\s]+)?\s*'
    r'(?:\{|;)',
    re.S,
)
# Standalone functions (rare in this codebase but route closures count too).
RE_FUNCTION  = re.compile(
    r'(?P<doc>/\*\*(?:[^*]|\*(?!/))*\*/)?\s*'
    r'function\s+(?P<name>[A-Za-z0-9_]+)\s*'
    r'\((?P<params>[^)]*)\)\s*'
    r'(?P<ret>:\s*[A-Za-z0-9_\\\?\|\&\s]+)?\s*\{',
    re.S,
)
RE_CALL_OBJ  = re.compile(r'\$\w+->([A-Za-z0-9_]+)\s*\(')
RE_CALL_STAT = re.compile(r'([A-Z][A-Za-z0-9_]*)::([A-Za-z0-9_]+)\s*\(')


def first_sentence(docblock: str | None) -> str:
    """Return the first sentence of a docblock, normalised to one line."""
    if not docblock:
        return ''
    # Strip /** */ and leading * on each line
    lines = [re.sub(r'^\s*\*\s?', '', l) for l in docblock.split('\n')]
    text = '\n'.join(lines).strip().strip('/').strip('*').strip()
    # First @-tag ends the prose section
    m = re.split(r'\n\s*@', text, maxsplit=1)
    text = m[0].strip()
    # First sentence
    m = re.split(r'(?<=[.!?])\s+', text, maxsplit=1)
    sentence = m[0].strip()
    sentence = re.sub(r'\s+', ' ', sentence)
    return sentence[:500]


def normalise_mods(mods: str) -> str:
    parts = mods.split()
    seen = []
    order = ['final', 'abstract', 'public', 'protected', 'private', 'static']
    for o in order:
        if o in parts:
            seen.append(o)
    return ' '.join(seen) if seen else 'public'


def extract_calls(body: str) -> list[str]:
    """Best-effort - regex over the method body for ->method() and Class::method()."""
    calls = set()
    for m in RE_CALL_OBJ.finditer(body):
        n = m.group(1)
        if n not in {'__construct', 'getMessage', 'getCode'}:
            calls.add(f'->{n}()')
    for m in RE_CALL_STAT.finditer(body):
        cls, n = m.group(1), m.group(2)
        if cls not in {'self', 'static', 'parent'}:
            calls.add(f'{cls}::{n}()')
    return sorted(calls)[:30]  # cap to keep chunks small


def find_method_body(source: str, end_decl: int) -> str:
    """Given the index just after the opening { of a method, balance braces
    to find the body. Returns the body string (possibly empty if abstract)."""
    if end_decl >= len(source) or source[end_decl] != '{':
        return ''
    depth = 0
    i = end_decl
    n = len(source)
    while i < n:
        c = source[i]
        if c == '{':
            depth += 1
        elif c == '}':
            depth -= 1
            if depth == 0:
                return source[end_decl + 1: i]
        elif c in ('"', "'"):
            # Skip string literal
            quote = c
            i += 1
            while i < n and source[i] != quote:
                if source[i] == '\\':
                    i += 2
                    continue
                i += 1
        elif c == '/' and i + 1 < n and source[i + 1] in '/*':
            # Skip comment
            if source[i + 1] == '/':
                while i < n and source[i] != '\n':
                    i += 1
            else:
                i += 2
                while i < n - 1 and not (source[i] == '*' and source[i + 1] == '/'):
                    i += 1
                i += 1
        i += 1
    return ''


def line_of(source: str, idx: int) -> int:
    return source.count('\n', 0, idx) + 1


def parse_php(path: Path, source: str) -> list[dict]:
    """Walk one PHP file, return a list of method records."""
    ns_match = RE_NAMESPACE.search(source)
    namespace = ns_match.group(1) if ns_match else ''
    cls_match = RE_CLASS.search(source)
    class_short = cls_match.group(1) if cls_match else ''
    fqn = f'{namespace}\\{class_short}' if namespace and class_short else (class_short or '')

    out: list[dict] = []
    seen = set()  # dedupe: regex can over-match in trait-using files

    for m in RE_METHOD.finditer(source):
        name = m.group('name')
        if (fqn, name) in seen:
            continue
        seen.add((fqn, name))
        mods = normalise_mods(m.group('mods'))
        # Private without docblock - skip per #58 scope
        if 'private' in mods and not m.group('doc'):
            continue
        params = re.sub(r'\s+', ' ', (m.group('params') or '').strip())
        ret    = (m.group('ret') or '').strip().lstrip(':').strip()
        body   = find_method_body(source, m.end() - 1)
        calls  = extract_calls(body) if body else []
        line_from = line_of(source, m.start())
        line_to   = line_of(source, m.end() + len(body))
        out.append({
            'fqn':       fqn or '<global>',
            'name':      name,
            'mods':      mods,
            'params':    params,
            'returns':   ret,
            'doc':       first_sentence(m.group('doc')),
            'file':      str(path),
            'line_from': line_from,
            'line_to':   line_to,
            'calls':     calls,
        })

    # Standalone functions (route closures, helpers etc.)
    if not class_short:
        for m in RE_FUNCTION.finditer(source):
            name = m.group('name')
            if (fqn, name) in seen:
                continue
            seen.add((fqn, name))
            params = re.sub(r'\s+', ' ', (m.group('params') or '').strip())
            ret    = (m.group('ret') or '').strip().lstrip(':').strip()
            body   = find_method_body(source, m.end() - 1)
            calls  = extract_calls(body) if body else []
            line_from = line_of(source, m.start())
            line_to   = line_of(source, m.end() + len(body))
            out.append({
                'fqn':       '<global>',
                'name':      name,
                'mods':      'function',
                'params':    params,
                'returns':   ret,
                'doc':       first_sentence(m.group('doc')),
                'file':      str(path),
                'line_from': line_from,
                'line_to':   line_to,
                'calls':     calls,
            })

    return out


def walk_php(root: str) -> list[Path]:
    out = []
    for r in ROOTS:
        base = Path(root) / r
        if not base.exists():
            continue
        for dirpath, dirnames, filenames in os.walk(base):
            dirnames[:] = [d for d in dirnames if d not in SKIPS]
            for f in filenames:
                # Skip blade.php - those are templates, handled by build_functions_kb_blade.py
                if f.endswith('.php') and not f.endswith('.blade.php') and not f.endswith('.min.php'):
                    out.append(Path(dirpath) / f)
    return out


def render(records: list[dict]) -> str:
    """Markdown rendering - one section per method, grouped by class FQN."""
    by_class: dict[str, list[dict]] = defaultdict(list)
    for r in records:
        by_class[r['fqn']].append(r)

    # Build inverse call graph (best-effort)
    called_by: dict[str, list[str]] = defaultdict(list)
    for r in records:
        anchor = f"{r['fqn']}::{r['name']}"
        for c in r['calls']:
            # only useful for static-style references that name the class
            if '::' in c:
                called_by[c.replace('()', '')].append(anchor)

    out = []
    out.append('# Heratio PHP function / method catalogue')
    out.append('')
    out.append('Auto-generated by `/opt/ai/km/build_functions_kb.py` from the live')
    out.append(f'Heratio source tree under `{HERATIO}`. Closes #58 part A.')
    out.append('')
    out.append(f'**Coverage:** {len(records)} methods across {len(by_class)} classes/files.')
    out.append('')

    for fqn in sorted(by_class):
        out.append(f'# {fqn}')
        out.append('')
        for r in sorted(by_class[fqn], key=lambda x: x['name']):
            anchor = f"{fqn}::{r['name']}"
            out.append(f"## {anchor}")
            out.append('')
            out.append(f"**Visibility:** `{r['mods']}`  ")
            sig = f"({r['params']})"
            if r['returns']:
                sig += f": {r['returns']}"
            out.append(f"**Signature:** `{sig}`  ")
            out.append(f"**File:** `{r['file']}:{r['line_from']}-{r['line_to']}`  ")
            if r['calls']:
                out.append(f"**Calls:** " + ', '.join(f'`{c}`' for c in r['calls']))
            cb = called_by.get(anchor, [])
            if cb:
                out.append(f"**Called by:** " + ', '.join(f'`{c}`' for c in sorted(set(cb))[:20]))
            if r['doc']:
                out.append('')
                out.append(r['doc'])
            out.append('')
    return '\n'.join(out)


def main() -> int:
    files = walk_php(HERATIO)
    print(f"[build_functions_kb] {len(files)} PHP files to scan", file=sys.stderr)
    records: list[dict] = []
    skipped = 0
    for path in files:
        try:
            source = path.read_text(encoding='utf-8', errors='replace')
            records.extend(parse_php(path, source))
        except Exception as e:
            skipped += 1
            print(f"[build_functions_kb] WARN: skipped {path}: {e}", file=sys.stderr)
    print(f"[build_functions_kb] extracted {len(records)} methods; {skipped} files skipped", file=sys.stderr)
    md = render(records)
    Path(OUT_PATH).write_text(md, encoding='utf-8')
    print(f"[build_functions_kb] wrote {OUT_PATH} ({len(md):,} bytes)", file=sys.stderr)
    return 0


if __name__ == '__main__':
    sys.exit(main())
