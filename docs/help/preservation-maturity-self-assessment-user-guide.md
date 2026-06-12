> Heratio Help Center article. Category: Digital Preservation.

# Preservation Maturity Self-Assessment (human-entered)

## A Guide for Repository Administrators

---

## What is the maturity self-assessment?

The **Preservation Maturity Self-Assessment** is where your institution rates its
own digital-preservation practice, section by section, against a recognised
international maturity model, records the ratings with evidence notes, and tracks
how the profile changes over time.

It lives at **Admin -> Preservation maturity self-assessment**
(`/admin/preservation-self-assessment`) and is available to administrators only.

It **complements** the computed Preservation Maturity dashboard
(`/admin/preservation-maturity`). The two are deliberately different things:

| Surface | What it is | Where the numbers come from |
|---|---|---|
| **Computed maturity dashboard** | A read-only score the platform derives automatically | Concrete records in this instance (checksums, formats, events, ...) |
| **Maturity self-assessment** (this) | A human, organisational rating you enter | Your own judgement, section by section, with evidence |

A mature programme uses both: the computed dashboard is an honest mirror of what
the system can evidence, while the self-assessment captures organisational
realities (policy, legal basis, staffing, strategy) that no automated probe can
see.

---

## The maturity models

Two widely used, jurisdiction-neutral models are supported (no country
assumptions are made; both are international).

### NDSA Levels of Digital Preservation

Five functional areas, each self-rated on a 0 (not yet) to 4 scale:

1. **Storage** - multiple copies with geographic and provider diversity
2. **Integrity (fixity)** - checksums recorded, verified on a cadence, content protected
3. **Control (security)** - who can read or change content is restricted and logged
4. **Metadata** - descriptive, administrative, technical and preservation (PREMIS) metadata
5. **Content (file formats)** - format identification (PRONOM/PUID), diversity, obsolescence monitoring

### DPC Rapid Assessment Model (DPC RAM)

Eleven sections (three organisational, eight service-capability), each rated 0 to 4:

- Organisational viability
- Policy and strategy
- Legal basis
- IT capability
- Continuous improvement
- Acquisition, transfer and ingest
- Bitstream preservation
- Content preservation
- Metadata management
- Discovery and access
- Reuse

### The 0 to 4 maturity scale

The shared scale labels are: **0 Minimal awareness**, **1 Awareness**,
**2 Basic**, **3 Managed**, **4 Optimised**. These labels (and the list of
models) come from the Dropdown Manager (groups `assessment_model` and
`maturity_level`), so an administrator can rename or extend them under
**Admin -> Dropdowns** without touching code.

---

## Running an assessment

1. Open **Admin -> Preservation maturity self-assessment**.
2. In **Start a new assessment**, choose the model (NDSA or DPC RAM), optionally
   give it a title, an assessor and a date, then **Begin assessment**.
3. On the rating form, for each section pick the level that best describes your
   practice. Each level shows a short descriptor so you know what it means. Add
   an **Evidence / notes** entry to record the justification.
4. **Save draft** to keep working, or **Save and mark complete** when finished.
5. You land on the **maturity profile** - a radar of section levels plus
   horizontal bars and your overall (average) maturity.

---

## The profile and progress over time

- The **profile** page shows one assessment as a CSS radar and per-section bars,
  with each section's evidence note and the overall average maturity.
- The **landing page** lists every past assessment and draws a small trend per
  model, so you can see whether your maturity is improving release over release.
- **Export JSON** downloads a self-contained snapshot of an assessment (model,
  metadata, every section with its level, label and evidence, plus the overall
  average) for reporting, sharing with funders, or feeding into a wider
  dashboard.

---

## Notes

- The self-assessment is entirely separate from your records: it only ever writes
  to its own two tables and never changes a catalogue description.
- It is admin-only and resilient - a fresh or mid-migration install shows a calm
  "being set up" state rather than an error.
- The models are seeded as data; the level descriptors are taken from the
  published NDSA Levels v2.0 and the DPC Rapid Assessment Model.
