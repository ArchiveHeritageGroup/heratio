# Significant properties, normalisation, and migration in Heratio

**Summary.** A file format will eventually become obsolete - the software that
reads it disappears. Digital preservation responds with two related ideas.
*Significant properties* are the characteristics of a digital object that must be
preserved for it to remain authentic and usable to its designated community (for
an image: its pixels and colour; for a document: its text and layout; not
necessarily its exact byte encoding). *Normalisation / migration* is the act of
converting content into a stable, well-supported preservation format (for example
.doc -> PDF/A, JPEG -> JPEG 2000, WAV -> FLAC, AVI -> FFV1/MKV) while keeping
those significant properties intact and keeping the original. Heratio supports
format-risk-driven migration with the original always retained, and records each
conversion as a PREMIS event so the lineage is auditable.

## The concept

- **Significant properties** answer "what must survive a format migration for this
  to still be the same thing?" They are defined relative to the *designated
  community* (an OAIS concept): a genealogist needs a scan's legibility; a
  forensic analyst might need its exact byte structure. Deciding significant
  properties is a curatorial judgement, not a purely technical one.
- **Normalisation** converts incoming content to a preferred preservation format
  at or near ingest, so the archive holds a smaller set of stable formats.
- **Migration** converts already-held content to a new format when the old one
  becomes risky, driven by the format risk registry (see
  `dp-06-pronom-format-identification`).
- **Golden rule:** keep the original. Migration produces a *new representation*;
  PREMIS records that the new file derives from the old one, so authenticity is
  traceable. Lossless migration paths are preferred where they exist.

## How Heratio addresses this

- **Format risk drives migration.** Identified formats carry a preservation risk
  level (low / medium / high / critical) in the format registry. The at-risk
  formats view is `GET /admin/preservation/formats` (route
  `preservation.formats`); the maturity assessment treats a monitored
  `preservation_format` + `preservation_action` registry as evidence of the
  highest Content maturity (see `dp-08-ndsa-levels`).
- **Conversion / normalisation.** The conversion surface is
  `GET /admin/preservation/conversion` (route `preservation.conversion`).
  Heratio's preservation conversion supports archival-format targets (for
  example image -> TIFF, audio -> WAV, office documents -> PDF, PDF -> PDF/A)
  using standard tools, with the converted derivative stored alongside the
  master.
- **Lineage as PREMIS.** Each normalisation / migration is recorded as a PREMIS
  event (`normalization` / `migration`) so the derivation relationship between
  the new and original representations is auditable (see
  `dp-03-premis-preservation-metadata`). The original is retained, not replaced.
- **Per-record preservation actions.** A record's preservation state, including
  recorded actions on its formats, is visible at
  `GET /admin/preservation/object/{id}` (route `preservation.object`).
- **TIFF/PDF normalisation tooling.** The `ahg-preservation` package also ships a
  TIFF/PDF merge pipeline (under `/admin/preservation/tiffpdfmerge`) used to
  assemble page images into a single access/preservation PDF where that is the
  appropriate normalised form.

## Gaps / not yet

- Heratio does not expose a formal, per-format "significant properties profile"
  editor; significant-properties judgements are made by curators and reflected in
  the choice of migration target rather than stored as a structured ruleset.
- Migration is operator-initiated against the at-risk registry; there is no fully
  automatic "format X crossed a risk threshold, so migrate all instances of it
  tonight" policy engine yet. Conversion availability also depends on the
  relevant tools being installed on the host.
