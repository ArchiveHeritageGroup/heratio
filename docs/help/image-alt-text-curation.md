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

Alternative text here is **authored by people**. It is not generated
automatically.

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

## How it counts toward the accessibility report

A published image counts as having a text alternative in the accessibility report
if **either** it has a curated entry here **or**, as a fallback, an embedded
IPTC/XMP caption. Curated entries are the genuine WCAG 1.1.1 signal and are counted
directly; the caption is only a fallback. As you curate, the report's
**Image alternative text** area improves.

## Scope and safety

- Only **published** images appear in the worklist.
- Saving writes **only** to the dedicated `image_alt_text` store. It never changes
  the original record or the digital object, and makes no AI calls.
- Clearing the text removes the entry, which is a legitimate curation action and
  keeps the coverage figure honest.
