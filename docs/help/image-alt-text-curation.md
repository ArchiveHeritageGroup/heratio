# Image alt-text curation

Author a real text alternative for every published image, so visitors who use a
screen reader can understand what each image shows (WCAG 2.1 - 1.1.1 Non-text
Content).

## Why this exists

The digital accessibility coverage report (`/admin/accessibility`) found that
published images carry essentially no genuine alternative text. The catalogue had
no dedicated place to record alt text, so the report could only fall back to the
embedded IPTC/XMP caption. This worklist closes that gap: it gives cataloguers and
contributors a real place to write and curate a text alternative for each image.

Alternative text here is **authored by people**. Nothing is ever saved
automatically. To speed the work you can optionally ask an AI vision model (through
the AHG gateway) for a **draft** description to start from - you remain the author
and must review and edit it before saving. See **AI-assisted suggestions** below.

## Where to find it

**Admin → Image alt-text curation** (`/admin/alt-text`). It links across to the
**Digital accessibility** report (`/admin/accessibility`), which it complements.

## What you see

- **Curated alt-text coverage** - how many published images now carry a genuine,
  human-authored text alternative in the working language, out of the total
  published images.
- **Working language** - alternative text is authored per language, so the same
  image can carry a description in English, Afrikaans, and any other language.
  Switch the language to curate another language; the worklist then shows the
  images still missing alt text in that language.
- **Worklist** - the published images that still have no curated alternative text
  in the working language. Each row shows the parent record (linked), the surrogate
  filename, and - where present - the embedded caption, which you can adapt as a
  starting point. Type the alternative text and **Save**.

## Writing good alternative text

- Describe what the image shows, concisely, for someone who cannot see it.
- Convey the meaning the image carries in its context, not every visual detail.
- Do not start with "image of" or "picture of" - the screen reader already says
  it is an image.
- If an embedded caption is shown, you can adapt it, but make sure it reads as a
  description rather than a title.

## AI-assisted suggestions (optional)

When the AHG AI gateway is configured for this instance, each image in the worklist
shows a **Suggest alt text** button next to **Save**. Pressing it sends that one
image to a vision model **through the sanctioned AHG AI gateway**
(`https://ai.theahg.co.za/ai/v1`) and drops a **draft** description into the text
box, labelled:

> **AI-suggested - review and edit before saving.**

The draft is a starting point only:

- It is **never saved automatically**. Nothing reaches the `image_alt_text` store
  until you review the text and press **Save** yourself, through the same human save
  path as a hand-written entry. **You are the author.**
- Treat every draft critically. A vision model can misread a scene or invent detail
  it cannot actually see - correct anything wrong and remove anything guessed (names,
  dates, places).
- The draft is requested in the **working language**, so you can draft in English,
  Afrikaans, or any other language you have selected.

### When the button is missing or says "unavailable"

The button only appears when a gateway endpoint and key are configured. If the
gateway is unreachable, no vision model is available, or the image file cannot be
read, the suggestion simply fails with a calm "suggestion unavailable" message and
the box is left untouched - manual curation always still works. The AI assist is a
convenience layered on top of the human workflow, never a dependency.

### How the image reaches the model

- AI is reached **only** through the AHG AI gateway - never a direct GPU node port.
- One image at a time, with a size cap on what is sent. Raster images (JPEG, PNG,
  GIF, WebP, BMP) are eligible; large TIFF/JP2 masters are skipped.
- The request carries the image plus a short instruction to describe what is
  visibly shown for a screen-reader user, in the working language.

## How it counts toward the accessibility report

A published image counts as having a text alternative in the accessibility report
if **either** it has a curated entry here **or**, as a fallback, an embedded
IPTC/XMP caption. Curated entries are the genuine WCAG 1.1.1 signal and are counted
directly; the caption is only a fallback. As you curate, the report's
**Image alternative text** area improves.

## Scope and safety

- Only **published** images appear in the worklist.
- Saving writes **only** to the dedicated `image_alt_text` store. It never changes
  the original record or the digital object.
- The optional **Suggest alt text** assist only reads the image and returns a draft
  for you to edit; it writes nothing. The single write path remains your **Save**.
- Clearing the text removes the entry, which is a legitimate curation action and
  keeps the coverage figure honest.
