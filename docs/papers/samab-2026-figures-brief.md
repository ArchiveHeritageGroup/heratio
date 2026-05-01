# SAMAB 2026 - Figures Designer Brief

**Companion to:** `samab-2026-paper-full.md`
**Audience:** A graphic designer with academic-figure experience (someone who knows how a journal figure should read at print and at screen size).
**Output formats requested:** SVG (master) + 300 dpi PNG (for editor uploads) + 600 dpi greyscale PNG (in case SAMAB prints monochrome).
**Style guide:**

- Sans-serif typography throughout (e.g. Inter, IBM Plex Sans, or whatever the journal house style suggests).
- Restrained colour palette - primary brand colour for "Heratio active region", neutral grey for context.
- Suggested palette: deep teal `#005A6E` (primary), warm amber `#E07A2C` (accent / AI flag), neutral slate `#3E4A52` (text), light grey `#E9ECEF` (panel fills), white background.
- All arrows are clean, single-headed, 1pt stroke. Avoid clip-art icons; prefer simple geometric glyphs.
- All figures must read at A5 width (~14 cm) without zooming, and reduce cleanly to greyscale.
- Caption fonts ≤8 pt; body labels ≥10 pt.

Three figures follow. Each has an in-paper caption (use as-is or lightly edit) and a structured visual specification.

---

## Figure 1 - Heratio architecture and the four AI capabilities

**Where it goes:** End of Section 1 (Introduction) or beginning of Section 2 (Background). Establishes the system's surface before the four capability sections describe individual modules.

**In-paper caption:**

> *Figure 1.* The Heratio collections-management framework. Open-source Laravel/PHP application reading from a Qubit-schema MySQL database; AI capabilities (shaded amber) operate as opt-in services running on institution-controlled GPU infrastructure. Standards-aligned outputs (ISAD(G), Spectrum 5.0, Dublin Core, RiC-O) are produced by the same data layer that AI populates, ensuring AI-generated metadata is not a parallel data layer. Optional services (Cantaloupe IIIF, Apache Jena Fuseki, Qdrant vector store) extend specific capabilities without being on the critical path. Where two cataloguer figures are shown, the workflow is human-in-the-loop: AI proposes, cataloguer disposes.

### Visual specification

```
┌────────────────────────────────────────────────────────────────┐
│  PUBLIC LAYER                                                  │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────┐   │
│  │  GLAM    │  │  Object  │  │ Search & │  │ Researcher   │   │
│  │  Browse  │  │  Show    │  │ Discovery│  │ Portal       │   │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └──────┬───────┘   │
│       │             │             │                │           │
└───────┼─────────────┼─────────────┼────────────────┼──────────┘
        │             │             │                │
        ▼             ▼             ▼                ▼
┌────────────────────────────────────────────────────────────────┐
│  HERATIO CORE - Laravel 12 / PHP 8.3                           │
│                                                                │
│  ┌─────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐ │
│  │Cataloguing  │Records   │ │Preserva- │ │RiC       │ │Display │ │
│  │ &       │ │ Mgmt     │ │tion      │ │Explorer  │ │& UI    │ │
│  │Authority│ │(ISO 15489│ │(BagIt /  │ │(graph    │ │        │ │
│  │(ISAD,   │ │ ISO 16175│ │ OAIS)    │ │ view)    │ │        │ │
│  │ ISAAR)  │ │ MoReq)   │ │          │ │          │ │        │ │
│  └────┬────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────────┘ │
│       │           │            │            │                  │
└───────┼───────────┼────────────┼────────────┼──────────────────┘
        │           │            │            │
        ▼           ▼            ▼            ▼
┌────────────────────────────────────────────────────────────────┐
│  AI LAYER  ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  (amber shading)  │
│                                                                │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐   │
│  │ §3 AI      │ │ §4 AI      │ │ §5 AI      │ │ §6 AI      │   │
│  │ Description│ │ Condition  │ │ Fixity     │ │ Metadata   │   │
│  │ (LLaVA)    │ │ Assessment │ │ Risk Score │ │ + NER      │   │
│  │            │ │ (Vision-LM)│ │ (PRONOM +  │ │ (NLLB-200, │   │
│  │            │ │            │ │  ML model) │ │  EXIF/IPTC)│   │
│  └─────┬──────┘ └─────┬──────┘ └─────┬──────┘ └─────┬──────┘   │
│        │              │              │              │          │
│        ▼ proposes     ▼ proposes     ▼ prioritises  ▼ suggests │
│        ▲ accept       ▲ accept       (no human-in-  ▲ accept   │
│        │ /edit/reject │ /edit/reject  the-loop -    │ /edit/   │
│        │              │               cron runs     │ reject   │
│        │              │               automatically)│          │
│   👤 Cataloguer  👤 Conservator                  👤 Cataloguer │
└────────────────────────────────────────────────────────────────┘
        │           │            │            │
        ▼           ▼            ▼            ▼
┌────────────────────────────────────────────────────────────────┐
│  DATA LAYER                                                    │
│  ┌──────────────┐  ┌──────────┐  ┌──────────────────────────┐  │
│  │ MySQL 8      │  │ Qdrant   │  │ NAS storage              │  │
│  │ (Qubit       │  │ (vector  │  │ (digital objects + bags) │  │
│  │  schema +    │  │  store)  │  │                          │  │
│  │  rm_* / dm_*)│  │          │  │                          │  │
│  └──────────────┘  └──────────┘  └──────────────────────────┘  │
│                                                                │
│  ╌╌╌ optional ╌╌╌                                              │
│  ┌──────────────┐  ┌──────────────┐                            │
│  │ Apache Jena  │  │ Cantaloupe   │                            │
│  │ Fuseki       │  │ IIIF         │                            │
│  │ (RiC-O graph)│  │ (deep zoom)  │                            │
│  └──────────────┘  └──────────────┘                            │
└────────────────────────────────────────────────────────────────┘
```

### Designer notes for Figure 1

- Three horizontal bands: **Public layer** (top), **Heratio core + AI** (middle, where AI is its own band shaded amber), **Data layer** (bottom). All boxes the same height within a band.
- The four AI modules align directly under the four core modules they feed (description ↔ cataloguing, condition ↔ records management, fixity ↔ preservation, metadata/NER ↔ cataloguing+RiC).
- Show a tiny human silhouette (👤 placeholder; designer to draw clean glyph) under three of the four AI modules, with up-arrows labelled "accept / edit / reject" - the human-in-the-loop signal. The fourth (Fixity) has no human icon - note this asymmetry visually because Section 5 explains why fixity is the only fully-automated AI capability.
- Label the section numbers (§3, §4, §5, §6) on each AI module so readers can cross-reference to the paper text.
- "Optional" tier (Fuseki, Cantaloupe) below the main data row, in lighter grey + dashed boundary, to convey "extends but not on the critical path".
- Background colour: pure white. No drop shadows. Lines: 1pt grey for non-active flow, 1.5pt for active.

---

## Figure 2 - AI Condition Assessment: heatmap output and review interface

**Where it goes:** Section 4.3 (Computer vision for damage typology detection) or 4.4 (Human-in-the-loop). Anchors the discussion of how the conservator interacts with the AI's output.

**In-paper caption:**

> *Figure 2.* AI-assisted condition assessment in Heratio. (a) The conservator's review screen showing the master photograph (left) overlaid with the model's per-defect heatmap (centre); each colour codes a Spectrum 5.0 damage typology (foxing, water damage, mould, support loss). (b) Spectrum-aligned condition record draft, populated by the model and editable inline by the conservator before sign-off. The record carries an overall grade (Stable / Fair / Poor / Unstable), a severity score (0–100), and a treatment recommendation drawn from the institution's `conservation_action` taxonomy. The model's confidence score appears beside each detected defect. Drafts do not auto-publish; the conservator's signed-off record is the system of record under GRAP 103.

### Visual specification

Two-panel figure, side-by-side, total width A5 (~14 cm).

#### Panel (a) - Heatmap overlay (left, ~60 % of width)

```
┌──────────────────────────────┐
│   [photograph: 19th-century   │
│    SA archival document -     │
│    aged paper, brown ink]     │
│                               │
│   ░░░░░░░░░░░░░░░░░  ← red   │
│   ░░░░░░░░░░░░░░░░░    over- │
│   ░░░  foxing region ░░░░    │
│   ░░░░░░░░░░░░░░░░░░░░░      │
│                               │
│   ▓▓▓▓ tear (yellow) ▓▓▓▓     │
│                               │
│   ▒▒▒  water damage  ▒▒▒      │
│   ▒▒▒  (light blue)  ▒▒▒      │
└──────────────────────────────┘
   Legend (small, below):
   ■ red   = foxing
   ■ yellow= tear
   ■ blue  = water damage
   ■ green = mould (if applicable)
```

- Use a real period-photograph or document image as the base (the designer can source a public-domain placeholder; we'll swap in a Heratio-licensed image before submission).
- Colour overlays at ~35 % opacity; the underlying photograph still visible through them.
- Each region carries a tiny confidence label inset (e.g. "0.87"). Position below or beside the region with a thin leader line.

#### Panel (b) - Spectrum-aligned condition record draft (right, ~40 %)

A semi-realistic UI mockup of the Heratio condition-record edit form, in the institution's brand. Approximate fields:

```
┌───────────────────────────────┐
│ Condition Assessment   [DRAFT]│
├───────────────────────────────┤
│ Object: [Rec ID - title]      │
│                               │
│ Overall grade   [Poor      ▾] │ <- model picked Poor; cataloguer can change
│ Severity score  [ 62 ] / 100  │
│ Date            [2026-04-27]  │
│                               │
│ Detected defects:             │
│   ☑ foxing  (conf 0.87)       │
│   ☑ tear    (conf 0.91)       │
│   ☑ water damage (conf 0.66)  │
│   ☐ mould                     │
│   ☐ ink loss                  │
│                               │
│ Recommended action:           │
│ ┌─────────────────────────┐  │
│ │ Consult conservator     │▾ │
│ └─────────────────────────┘  │
│                               │
│ Notes (cataloguer):           │
│ ┌─────────────────────────┐  │
│ │ Re-check after relative │  │
│ │ humidity stabilises…    │  │
│ └─────────────────────────┘  │
│                               │
│ [Reject]   [Save draft]       │
│            [Accept & sign off]│
└───────────────────────────────┘
```

- Show the **DRAFT** badge prominently in amber.
- Confidence scores beside detected defects in monospaced font (signals "machine-generated").
- "Notes (cataloguer)" field highlighted to show that human edit is expected.
- Bottom action bar with "Reject" greyed (less prominent), "Accept & sign off" in primary brand colour (calls attention to the human commitment moment).

### Designer notes for Figure 2

- Vertical alignment: the photograph in Panel (a) and the form in Panel (b) should be the same height; the legend in Panel (a) sits below the photograph.
- The base photograph in Panel (a) needs to be authentic-feeling - aged paper, faint foxing, a small tear, a water stain. Not a contemporary image. SAMAB readers will recognise unrealistic substitutes.
- Heatmap colours readable at greyscale: foxing (red) and water damage (blue) need to differentiate at print. Try varying texture/cross-hatch as well as hue if greyscale is critical for the print edition.
- "DRAFT" badge in Panel (b) is the most important visual element after the photograph itself - it is the paper's argument visualised: AI proposes, conservator disposes.

---

## Figure 3 - Three-layer AI description flow for accessibility

**Where it goes:** Section 3.3 (Multi-modal description). Visualises how the same AI invocation produces three different lengths of description for three different consumption contexts.

**In-paper caption:**

> *Figure 3.* Three-layer AI description workflow. A single invocation of the vision-language model produces (1) a short alt-text (≤120 characters, suitable for screen-reader announcement), (2) a medium-length visual description (3–5 sentences describing what is visually present), and (3) a longer contextual description (1–2 paragraphs grounding what is visible in metadata-supplied context). Each layer is independently reviewable and editable; the cataloguer's accept-edit-reject decision is captured as provenance metadata on each layer. Outputs are written to the corresponding fields in the data model and rendered in the public object page, in JSON-LD structured data, and in the HTML alt-attribute.

### Visual specification

Vertical-flow diagram, A5 portrait or landscape (designer's choice - landscape probably reads better at print).

```
  ┌─────────────────────────────────────────┐
  │  INPUT                                  │
  │                                         │
  │  ┌───────┐    ┌────────────────────┐    │
  │  │ Image │ +  │ Object metadata     │    │
  │  │ (TIFF/│    │ (title, classifica- │    │
  │  │  JP2) │    │  tion, place,       │    │
  │  │       │    │  material, period)  │    │
  │  └───────┘    └────────────────────┘    │
  └─────────────────────────────────────────┘
                       │
                       ▼
  ┌─────────────────────────────────────────┐
  │  VISION-LANGUAGE MODEL                  │
  │  (LLaVA 13B, on-premises, GPU)          │
  │                                         │
  │  Single invocation → three outputs      │
  └─────────────────────────────────────────┘
              │           │           │
              ▼           ▼           ▼
  ┌───────────────────────────────────────────────────────────────────┐
  │ Layer 1     │ Layer 2              │ Layer 3                      │
  │ ALT-TEXT    │ VISUAL DESC.         │ CONTEXTUAL DESC.             │
  │ ≤120 chars  │ 3–5 sentences        │ 1–2 paragraphs               │
  │             │                      │                              │
  │ "A black-   │ "A black-and-white   │ "Photograph from the         │
  │  and-white  │  photograph showing  │  collection's 1985 protest   │
  │  group      │  approximately       │  series, taken at the corner │
  │  photo of   │  twenty people       │  of [..]. The composition    │
  │  20 people  │  gathered outdoors   │  shows protesters with hands │
  │  outdoors"  │  in front of a       │  raised; metadata indicates  │
  │             │  building, several   │  this was during a march    │
  │             │  with arms raised."  │  for [..]"                   │
  │             │                      │                              │
  └─────┬───────┴─────────┬────────────┴──────────────┬───────────────┘
        │                 │                           │
        ▼                 ▼                           ▼
  ┌────────────────────────────────────────────────────────┐
  │  CATALOGUER REVIEW (per-layer)                         │
  │                                                        │
  │     [Accept]   [Edit + accept]   [Reject + rewrite]   │
  │                                                        │
  │  Decision recorded as provenance metadata              │
  │  (human-created / AI-suggested-and-accepted /          │
  │   AI-suggested-and-edited / AI-suggested-and-rejected) │
  └────────────────────────────────────────────────────────┘
                            │
                            ▼
  ┌────────────────────────────────────────────────────────┐
  │  PUBLISH - written to data model, rendered in:         │
  │  · public object page                                  │
  │  · JSON-LD structured data (caption, altText)          │
  │  · HTML <img alt="...">                                │
  │  · API response (with provenance tag)                  │
  └────────────────────────────────────────────────────────┘
```

### Designer notes for Figure 3

- Top to bottom flow with the three-layer split prominent in the middle. The split visualises the paper's accessibility argument: one model run, three appropriately-sized outputs.
- Use the example text exactly as drafted - these are illustrative content the SAMAB editor and audience will recognise as plausible (twentieth-century South African political photography), and they reinforce the bias-discussion in Section 8.1.
- The "CATALOGUER REVIEW" panel is the visual anchor for the human-in-the-loop argument. Make the three buttons (Accept / Edit + accept / Reject) prominent and distinct.
- Provenance-tag callout: small text beneath the buttons spelling out the four provenance values verbatim. This visualises Section 8.3 of the paper.
- The "PUBLISH" panel at the bottom shows the four downstream surfaces. The point: accessibility is not a separable feature for a subset of users - it is the system's standard output.

---

## Optional Figure 4 - Ablation table (post-validation)

If word-count permits and the validation pilots produce clean numbers, a small one-page table of per-strategy contribution would land well in Section 4.6 or in the Conclusion. Sketch:

| Strategy | Recall@10 | Recall@100 | Mean ms | Click-through rate |
| --- | --- | --- | --- | --- |
| Keyword (baseline) | _N_ | _N_ | _N_ | _N_ |
| + Entity (NER) | _N_ | _N_ | _N_ | _N_ |
| + Hierarchical (lft/rgt walk) | _N_ | _N_ | _N_ | _N_ |
| + Vector (Qdrant) | _N_ | _N_ | _N_ | _N_ |
| Full RRF merge | _N_ | _N_ | _N_ | _N_ |

Numbers come from the simulated-query runs described in the discovery work (`ahg_discovery_log.strategy_breakdown`). Hold for the second-pass paper revision after the first ablation run lands.

---

## Production checklist

- [ ] Figure 1 - Heratio architecture (designer)
- [ ] Figure 2 - Condition heatmap + review form (designer; supply real photo placeholder)
- [ ] Figure 3 - Three-layer description flow (designer; example text as drafted)
- [ ] Figure 4 - Ablation table (held for post-validation)
- [ ] Greyscale fallback proofs of Figs 1–3
- [ ] All three figures sized at A5 width, 300 dpi PNG export
- [ ] In-paper captions (above) used verbatim or lightly edited

Submission-ready bundle: `samab-2026-paper-full.md` + `figures/fig1.svg` + `fig2.svg` + `fig3.svg` + 300 dpi PNG renders.
