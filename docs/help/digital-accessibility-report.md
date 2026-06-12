# Digital accessibility coverage report

How much of your published collection is reachable by visitors who rely on
alternative text, captions, transcripts, or a language other than the
catalogue's primary one - and where the gaps are.

## What this report is (and is not)

The admin report at **Admin → Digital accessibility** (`/admin/accessibility`)
is a **heuristic coverage report** over the accessibility-relevant metadata
Heratio actually stores. It tells you what proportion of published content
carries each accessibility signal, and recommends how to close each gap.

It is **not a WCAG conformance audit**. A full conformance audit also requires
reviewing the running interface itself - keyboard operability, colour contrast,
focus order, and so on - which a metadata report cannot measure. The report
cites WCAG 2.1 AA success criteria as an international reference grid only.

The report is **read-only**. It never changes a record, never runs a database
migration, and makes no AI calls. It counts only **published** content.

## The areas it measures

Each area shows a coverage level (None yet, Low, Partial, Good, Strong), the
"with vs total" big numbers, a CSS coverage bar, the evidence behind the figure,
and a recommendation.

- **Image alternative text** (WCAG 1.1.1) - published image surrogates that carry
  a stored text description. Heratio has no dedicated alt-text column for images,
  so the report measures the closest stored signal (the embedded IPTC/XMP caption
  in `digital_object_metadata.description`) and recommends adding a true alt-text
  field. Where even that table is unavailable the area reads **Not measured**.
- **Captions and subtitles** (WCAG 1.2.2) - published audio/video surrogates with
  at least one active caption or subtitle track.
- **Transcripts** (WCAG 1.2.3 / 1.2.5) - published audio/video surrogates with a
  text transcript (a recognised media alternative).
- **3D model alternative text** (WCAG 1.1.1) - published 3D models that carry
  alternative text. This is a direct measure: 3D models have a dedicated
  `alt_text` field.
- **Multilingual access** (WCAG 3.1.1 / 3.1.2) - published records readable in
  more than one language (a real title in two or more cultures).

## How the overall level is set

Overall coverage is the **lowest level across the measured areas** - the
collection is only as reachable as its weakest area. Areas with no applicable
content (for example, no published audio-visual material at all), or with no
place in the schema to record the signal, are shown as **Not measured** and are
honestly **excluded** from the overall score rather than counted as a failure.

## Honest absence

Where Heratio cannot evidence a signal, the report says so. A missing column or
table yields a **Not measured** area with a specific recommendation - it never
invents coverage. The image alt-text area is the clearest example: because there
is no dedicated alt-text column, the report measures the embedded-caption proxy
and is explicit that genuine alternative text still needs a schema field.

## Where to find it

**Admin → Digital accessibility** (`/admin/accessibility`). It sits alongside the
Metadata completeness (`/admin/data-quality`) and Preservation maturity
(`/admin/preservation-maturity`) reports, and links across to them.
