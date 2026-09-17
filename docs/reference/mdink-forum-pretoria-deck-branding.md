# MD Ink Records Management Forum, Pretoria: reusing the Johannesburg pack and rebuilding the decks in the AHG house look

Date: 2026-09-17
Author: Dr Johan Pieterse
Status: delivered; pack emailed and staged on the host

MD Ink runs the same two-day forum twice. **Records and SharePoint Management
Forum**, Johannesburg, 29-30 July 2026, Protea Balalaika Hotel, Sandton. Then
**Records Management Forum**, Pretoria, 17-18 September 2026, Time Square
Hotel. AHG facilitates **Day 2 only** at both, which is the SharePoint day.

Day 2 of the Pretoria programme is identical to Johannesburg's, session for
session, so the existing delivery pack carried over. Day 1 differs.

## What carried over and what did not

| Slot | Johannesburg, 29 July | Pretoria, 17 September |
|---|---|---|
| 09:00 | The Evolving Role of Records Management | same |
| 09:30 / 10:00 | Legal and Regulatory Frameworks in South Africa | dropped; replaced by Data Security, Risk and Disaster Recovery |
| 11:00 | Retention, Disposal and Archiving Policies | same |
| 12:00 | nothing, straight to lunch | new: Auditing and Monitoring Records Management Programs |
| 14:00 | Designing a Compliant Records Management Policy, on components and templates | reframed as a five-point policy-development lifecycle |

Day 2 is byte-for-byte identical between the two brochures. The event name lost
"and SharePoint" for Pretoria even though Day 2 remains entirely SharePoint.

Three Day 1 sessions had no material in the pack and were drafted for this
event: Data Security, Risk and Disaster Recovery (45 min), Auditing and
Monitoring (60 min), and the reframed policy workshop (120 min). Each has
slides, short slides, a participant handout and a facilitator guide.

## Where things live

| What | Path |
|---|---|
| Johannesburg originals, untouched | `/usr/share/nginx/workbench/workspaces/ems/workshop-30july/` |
| Pretoria Day 2, rebranded | `/usr/share/nginx/workbench/workspaces/ems/forum-18sept-pretoria/` |
| Pretoria Day 1 drafts | `/usr/share/nginx/workbench/workspaces/ems/forum-17sept-pretoria/` |
| Brochures | `/usr/share/nginx/heratio/stuff/` |
| Published conference copy | `/usr/share/nginx/conferences/MDink-Records-SharePoint-Forum-2026/` |

One trap in that list: `Records and SharePoint Management Forum Brochure-
Pretoria.pdf` is mislabelled. Its contents are the Johannesburg July event. The
real Pretoria brochure is `Records Management Forum Brochure- Pretoria (2).pdf`.

## The AHG house look is drawn per slide, not carried in the theme

This is the part worth keeping. The AHG SASA 2026 deck's identity does not live
in its PowerPoint theme, which is the stock Office palette. The cream ground is
a `<p:bg>` element on each individual slide. The spine, the gold rule, the
footer bar and the eyebrow line are ordinary shapes sitting on the slide.

So `pandoc --reference-doc=<the SASA deck>` produces an unbranded white deck.
Pandoc copies masters and layouts, and none of the identity is in either. No
template-reference approach can inherit that look.

The same explains why the July decks were plain. They were built with
`pandoc --reference-doc=ahg-branded-reference.pptx`, which also carries a stock
theme, so those decks were always white with centred Calibri.

### What works instead

`_assets/build-deck.py` in either forum folder, using python-pptx. It opens the
SASA deck, deep-copies every shape from slide 1 as the title prototype and
slide 2 as the content prototype, drops the reference's own slides so its 22MB
of photography does not ride along, and pours markdown text into the copied
placeholders.

Copying shapes rather than rebuilding them is the whole trick. The title's
green, bold, Aptos Display and 24.5pt all live in `<a:pPr><a:defRPr>` on the
slide's own paragraph, not in the layout, so a rebuilt placeholder comes out
black and centred.

```
build-deck.py SLIDES.md OUT.pptx "EYEBROW TEXT" "FOOTER TEXT"
```

Input is the pack's existing markdown: `# Title` then `- bullets`. GFM pipe
tables become PowerPoint tables in the house style, bold white on AHG green
with alternating row fills.

| Value | Hex |
|---|---|
| Ground | `F7F3EC` |
| Green | `073B3A`, `0C4B49` on the spine |
| Gold | `C3A56A` |
| Slide size | 10 x 5.625in, 16:9 |

### Two failures worth not repeating

A first version of the builder parsed only bullets and **silently dropped every
markdown table** - 18 slides across the pack, including every "Session Flow"
slide. Worse, where a slide had no bullets at all, the prototype slide's own
text showed through, so a case-studies slide came out talking about SOCs. Any
markdown-to-deck builder needs a table path, and needs to delete the copied
body placeholder when a slide has no bullet content.

Keep the AHG roundel at about 320px. At 1024px it added roughly 500KB to every
deck, taking each file to 730KB. At 320px the decks are 250KB, which decides
whether a pack ships in four emails or eight.

## Recovering the MD Ink logo

There was no MD Ink logo anywhere on the host. It came out of the Pretoria
brochure with `pdfimages -png`, which yields it as **two** files: an RGB bitmap,
blue on white, and a separate grayscale stencil mask. Compose them with
`rgb.putalpha(mask)` to get a transparent lockup. Thresholding near-white to
transparent on the RGB alone does not work, and produces a dark box.

Both marks are staged at `_assets/brand/` in the forum folders: `ahg-mark.png`,
`ahg-roundel.png`, `mdink.png`.

## Rebuilding the documents to the house standard

The handouts and facilitator guides are pandoc plus the OOXML post-processor at
`/usr/local/share/ahg/fixdocx.py`:

```
pandoc -f gfm -t docx build.md -o raw.docx --reference-doc=ahg-branded-reference.docx
python3 /usr/local/share/ahg/fixdocx.py raw.docx final.docx
```

Two conditions, both easy to get wrong:

- **`-f gfm` matters.** With pandoc's default markdown reader a `: caption`
  line is consumed as a native table caption, so `fixdocx.py` finds no `":"`
  body paragraphs and the List of Tables comes out empty. GFM leaves the line
  as a body paragraph, which is what the post-processor turns into a real
  caption with a `SEQ Table` field.
- **Do not pass `--toc`.** `fixdocx.py` injects the table of contents and the
  list of tables *after* the metadata block, per the house front-matter order.
  Pandoc's own `--toc` puts a contents list at the very top, ahead of the title.

The docx MCP is only `pandoc -f gfm -t docx --toc --toc-depth=3` with a
reference template. It does not run the post-processor, so anything generated
through it alone has the contents list in the wrong place and no list of tables.

## Delivery

Pretoria Day 2 went out as 17 decks, 17 PDFs and 17 documents, emailed in four
parts. Graph rejects a simple send over about 3MB, which works out at two
module zips per email once the decks are slimmed.

One content point left open for the facilitator: session 4, slide 7, "Classic
Records Features Are Retiring". The third bullet, "That is before this forum -
the clock has run out", was written in July when the April 2026 end of In-Place
Records Management was still ahead. By September it is five months past and the
line wants rewording.
