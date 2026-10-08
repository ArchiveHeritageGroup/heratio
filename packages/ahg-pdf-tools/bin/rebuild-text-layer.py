#!/usr/bin/env python3
# AGPL-3.0-or-later - Johan Pieterse / Plain Sailing Information Systems
#
# heratio#1523. Invisible text layer from OCR text that has no word positions
# (a vision-model transcript). Each page's words are aligned (difflib) to
# Tesseract's word boxes from the page's TSV: a matched word takes the
# Tesseract box; where Tesseract read garbage, words are shared out over the
# garbage boxes by character count; a whole line Tesseract never saw gets a
# synthetic box in the gap between its neighbours. Where under 30% of the words
# anchor, the lines are laid out evenly down the page instead, each sized to
# the height its text would have (the fpdf2 renderer silently drops boxes far
# taller than their text). Output: one text-only PDF page per image.
#
#   rebuild-text-layer.py --tsv <dir of NNNN.tsv> --text <dir of NNNN.txt> --out <dir> --dpi 300
#   (a page with no NNNN.txt, or an empty one, keeps Tesseract's own NNNN.pdf text page if present)
#
# Ported from the Brenthurst Library build (vision-layer.py), which aligned per
# newspaper cutting; here the whole page is one region.
import argparse, csv, difflib, html, os, re, shutil, sys
from pathlib import Path
from ocrmypdf.font import MultiFontManager
from ocrmypdf.fpdf_renderer import Fpdf2PdfRenderer
from ocrmypdf.hocrtransform.hocr_parser import HocrParser
import ocrmypdf

DPI = 300
FONTS = MultiFontManager(Path(ocrmypdf.__file__).parent / 'data')

def norm(w): return re.sub(r'[^a-z0-9]', '', w.lower())

def tess_words(tsv):
    """(page width, height, [(left, top, right, bottom, text, line key)]) in Tesseract's reading order."""
    rows = list(csv.DictReader(open(tsv, encoding='utf-8'), delimiter='\t', quoting=csv.QUOTE_NONE))
    page = next(r for r in rows if r['level'] == '1')
    words = [(int(r['left']), int(r['top']), int(r['left']) + int(r['width']), int(r['top']) + int(r['height']),
              r['text'], (r['block_num'], r['par_num'], r['line_num']))
             for r in rows if r['level'] == '5' and r['text'].strip()]
    return int(page['width']), int(page['height']), words

def place(box, tess, vis):
    """Give every vision token a box: [(token, (l, t, r, b), line key)]."""
    x, y, w, h = box
    inside = [t for t in tess if x - 10 <= (t[0] + t[2]) / 2 <= x + w + 10 and y - 10 <= (t[1] + t[3]) / 2 <= y + h + 10]
    toks = vis.split()
    if inside and toks:
        sm0 = difflib.SequenceMatcher(None, [norm(t[4]) for t in inside], [norm(t) for t in toks], autojunk=False)
        # Under 30% of the vision words found among Tesseract's: its boxes are show-through or slivers (spine labels
        # gave 4-15 px wide boxes that the renderer dropped words from), so they are not worth anchoring to.
        if sum(b.size for b in sm0.get_matching_blocks()) < 0.3 * len(toks): inside = []
    if not inside:   # nothing to anchor on: lay the vision lines out evenly down the box
        lines = [l.split() for l in vis.splitlines() if l.split()]
        lh = h / max(len(lines), 1)
        out = []
        for i, toks in enumerate(lines):
            # Give each line the height its text would really have across the box width (glyph ~0.55 em wide),
            # never more than its share of the box: the renderer suppresses boxes far taller than their text.
            th = min(lh * 0.9, w / (0.55 * max(len(' '.join(toks)), 1)))
            top = int(y + i * lh + (lh - th) / 2)
            out += spread(toks, (x, top, x + w, int(top + th)), ('even', i))
        return out
    # Tesseract's page-level block order can put a cutting's body before its headings; a cutting is one
    # column, so top-to-bottom by line, then left to right, is its reading order.
    tops = {}
    for t in inside: tops.setdefault(t[5], []).append(t[1])
    inside.sort(key=lambda t: (sorted(tops[t[5]])[len(tops[t[5]]) // 2], t[5], t[0]))
    toks = vis.split()
    owner = [None] * len(toks)          # index into `inside` (or into `extra`, as -1-k) for each vision token
    extra = []                          # synthetic line boxes for lines Tesseract missed altogether
    sm = difflib.SequenceMatcher(None, [norm(t[4]) for t in inside], [norm(t) for t in toks], autojunk=False)
    for op, i1, i2, j1, j2 in sm.get_opcodes():
        if op == 'equal':
            for k in range(j2 - j1): owner[j1 + k] = i1 + k
        elif op == 'replace':           # spread by character count over the garbage boxes
            tl = [max(len(inside[i][4]), 1) for i in range(i1, i2)]; vl = [max(len(t), 1) for t in toks[j1:j2]]
            T, V, acc = sum(tl), sum(vl), 0
            for k, n in enumerate(vl):
                mid = (acc + n / 2) / V * T; acc += n
                c, i = 0, i1
                while i < i2 - 1 and c + tl[i - i1] < mid: c += tl[i - i1]; i += 1
                owner[j1 + k] = i
        elif op == 'insert':
            prev, nxt = (inside[i1 - 1] if i1 else None), (inside[i1] if i1 < len(inside) else None)
            gap_top = prev[3] if prev else y
            gap_bot = nxt[1] if nxt else y + h
            line_h = (prev or nxt)[3] - (prev or nxt)[1]
            if j2 - j1 >= 3 and gap_bot - gap_top > 0.6 * line_h:   # a whole line Tesseract never saw
                extra.append((x, gap_top, x + w, gap_bot, '', ('gap', len(extra))))
                for k in range(j1, j2): owner[k] = -len(extra)
            else:
                for k in range(j1, j2): owner[k] = max(i1 - 1, 0)
    out, k = [], 0
    while k < len(toks):                # tokens sharing one box split it left to right
        j = k
        while j + 1 < len(toks) and owner[j + 1] == owner[k]: j += 1
        t = inside[owner[k]] if owner[k] >= 0 else extra[-1 - owner[k]]
        out += spread(toks[k:j + 1], t[:4], t[5]); k = j + 1
    return out

def spread(toks, bbox, key):
    l, t, r, b = bbox
    n = sum(len(x) + 1 for x in toks) or 1
    out, acc = [], 0
    for x in toks:
        a = l + (r - l) * acc / n; acc += len(x) + 1
        out.append((x, (int(a), t, max(int(l + (r - l) * (acc - 1) / n), int(a) + 1), b), key))
    return out

def hocr(width, height, cuttings):
    """cuttings: [[(token, bbox, key), ...], ...] -> hOCR, one paragraph per cutting, lines by Tesseract line."""
    body, wid = [], 0
    for c, words in enumerate(cuttings, 1):
        lines = []
        for w in words:
            if not lines or lines[-1][0] != w[2]: lines.append((w[2], []))
            lines[-1][1].append(w)
        if not lines: continue
        body.append(f"<p class='ocr_par' id='par_{c}'>")
        for li, (_, ws) in enumerate(lines):
            lb = (min(w[1][0] for w in ws), min(w[1][1] for w in ws), max(w[1][2] for w in ws), max(w[1][3] for w in ws))
            body.append(f"<span class='ocr_line' id='line_{c}_{li}' title='bbox {lb[0]} {lb[1]} {lb[2]} {lb[3]}; baseline 0 0'>")
            for tok, bb, _ in ws:
                wid += 1
                body.append(f"<span class='ocrx_word' id='word_{wid}' title='bbox {bb[0]} {bb[1]} {bb[2]} {bb[3]}; x_wconf 90'>{html.escape(tok)}</span> ")
            body.append('</span>')
        body.append('</p>')
    return ('<?xml version="1.0" encoding="UTF-8"?>\n<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">'
            '<head><title></title><meta http-equiv="Content-Type" content="text/html;charset=utf-8"/></head><body>'
            f"<div class='ocr_page' id='page_1' title='bbox 0 0 {width} {height}; ppageno 0; scan_res {DPI} {DPI}'>"
            + '\n'.join(body) + '</div></body></html>')

def page(n, tsv_dir, text_dir, out):
    txt, tsv = f'{text_dir}/{n:04d}.txt', f'{tsv_dir}/{n:04d}.tsv'
    vis = open(txt, encoding='utf-8').read().strip() if os.path.exists(txt) else ''
    if not vis:
        if os.path.exists(f'{tsv_dir}/{n:04d}.pdf'):
            shutil.copy(f'{tsv_dir}/{n:04d}.pdf', f'{out}/{n:04d}.pdf'); return 'tesseract'
        return 'none'
    width, height, tess = tess_words(tsv)
    words = place((0, 0, width, height), tess, vis)
    Path(f'{out}/{n:04d}.hocr').write_text(hocr(width, height, [words]), encoding='utf-8')
    p = HocrParser(f'{out}/{n:04d}.hocr').parse()
    Fpdf2PdfRenderer(page=p, dpi=DPI, multi_font_manager=FONTS, invisible_text=True).render(Path(f'{out}/{n:04d}.pdf'))
    return 'vision'

def main():
    global DPI
    ap = argparse.ArgumentParser()
    ap.add_argument('--tsv', required=True); ap.add_argument('--text', required=True); ap.add_argument('--out', required=True)
    ap.add_argument('--dpi', type=int, default=300)
    a = ap.parse_args()
    DPI = a.dpi
    os.makedirs(a.out, exist_ok=True)
    nums = sorted(int(f[:4]) for f in os.listdir(a.tsv) if f.endswith('.tsv'))
    kinds = {}
    for n in nums:
        k = page(n, a.tsv, a.text, a.out); kinds[k] = kinds.get(k, 0) + 1
    print(f'{len(nums)} text pages: {kinds}', file=sys.stderr)
    return 0 if 'none' not in kinds else 2

if __name__ == '__main__':
    sys.exit(main())
