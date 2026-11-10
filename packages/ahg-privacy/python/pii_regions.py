#!/usr/bin/env python3
"""
pii_regions.py - page text and value locations in a PDF, for PII redaction
suggestions (PiiRedactionSuggester).

    python3 pii_regions.py <file.pdf>  < request.json  > response.json

request:  {"values": [{"page": 1, "value": "7804125123081"}, ...]}
          An empty list asks for the text of every page.
response: {"pages": [{"page": 1, "text": "...", "ocr": false}, ...]}   (no values)
          {"regions": [{"page": 1, "value": "...", "x": 0.1, "y": 0.2,
                        "width": 0.05, "height": 0.01}, ...],
           "missing": [{"page": 1, "value": "..."}]}

A page with no text layer (a scan) is read through Tesseract (PyMuPDF's
OCR text page), so a scanned form is handled like a born-digital one.
Coordinates are normalised 0-1 to the page, as privacy_visual_redaction stores
them. A value OCR spaced differently ("082 5550193") is found word by word
along one line.

Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
License: AGPL-3.0-or-later
"""
import glob
import json
import sys

import fitz  # PyMuPDF

TESSDATA = (glob.glob('/usr/share/tesseract-ocr/*/tessdata') or [None])[0]


def textpage(page):
    """The page's own text, or an OCR text page when it has none."""
    if page.get_text().strip():
        return page.get_textpage(), False
    return page.get_textpage_ocr(dpi=300, full=True, tessdata=TESSDATA), True


def word_by_word(page, tp, value):
    """Rects for a value whose spacing differs: its words in order on one line."""
    words = [w for w in value.split() if w]
    if len(words) < 2:
        return []
    hits = [page.search_for(w, textpage=tp) for w in words]
    for first in hits[0]:
        chain, last = [first], first
        for rects in hits[1:]:
            nxt = next((r for r in rects
                        if abs(r.y0 - last.y0) < last.height * 0.6 and 0 <= r.x0 - last.x1 < last.height * 4), None)
            if nxt is None:
                break
            chain.append(nxt)
            last = nxt
        if len(chain) == len(words):
            u = fitz.Rect(chain[0])
            for r in chain[1:]:
                u |= r
            return [u]
    # The value's digits run together in the OCR text: try that too.
    joined = ''.join(words)
    return page.search_for(joined, textpage=tp) if joined != value else []


def main():
    doc = fitz.open(sys.argv[1])
    req = json.loads(sys.stdin.read() or '{}')
    values = req.get('values') or []
    cache = {}

    def tp_for(i):
        # The text page holds only a weak reference to its page, so the page
        # object is kept with it: a fresh doc[i] would orphan the text page.
        if i not in cache:
            page = doc[i]
            cache[i] = (page,) + textpage(page)
        return cache[i]

    if not values:
        pages = []
        for i in range(len(doc)):
            page, tp, ocr = tp_for(i)
            pages.append({'page': i + 1, 'text': page.get_text(textpage=tp), 'ocr': ocr})
        print(json.dumps({'pages': pages}))
        return

    regions, missing = [], []
    for v in values:
        i = int(v['page']) - 1
        if i < 0 or i >= len(doc):
            continue
        page, tp, _ = tp_for(i)
        rects = page.search_for(v['value'], textpage=tp) or word_by_word(page, tp, v['value'])
        if not rects:
            missing.append({'page': i + 1, 'value': v['value']})
            continue
        w, h = page.rect.width, page.rect.height
        for r in rects:
            pad = 1.5  # a little margin so glyph edges do not show
            regions.append({
                'page': i + 1, 'value': v['value'],
                'x': max(0.0, (r.x0 - pad) / w), 'y': max(0.0, (r.y0 - pad) / h),
                'width': min(1.0, (r.width + 2 * pad) / w), 'height': min(1.0, (r.height + 2 * pad) / h),
            })
    print(json.dumps({'regions': regions, 'missing': missing}))


if __name__ == '__main__':
    main()
