# Acknowledgments

Heratio is an independent project but stands on substantial work from the
wider archival, museum, and open-source community. This file records that
debt explicitly.

---

## AtoM (Access to Memory) — Artefactual Systems Inc. and the AtoM community

**https://www.accesstomemory.org/  ·  https://github.com/artefactual/atom**

AtoM is the open-source archival description system originally developed
by [Artefactual Systems Inc.](https://www.artefactual.com/) under contract
to the International Council on Archives. Heratio is a re-implementation
on a different stack (Laravel 12 instead of Symfony 1) and ports many
patterns directly. Specifically, Heratio incorporates work from AtoM in
the following areas:

- **Database schema** — the `*_i18n` translation tables, `slug`,
  `information_object`, `actor`, `repository`, `digital_object`,
  `event`, `relation`, MPTT `lft`/`rgt` hierarchy traversal, and 30+
  related core tables. Inherited as the source-of-truth for archival data.
- **Description standards rendering** — ISAD(G), ISAAR(CPF), ISDIAH,
  ISDF section structure and field labelling.
- **UI string translations** — the bulk of `lang/*.json` translations
  (excluding South African languages, which are authored by The AHG)
  derive from XLIFF files at `apps/qubit/i18n/{lang}/messages.xml` in the
  AtoM source tree. **49 locales × thousands of strings imported** during
  Heratio bootstrap. See `lang/_meta.json` for per-locale provenance.

  These translations were contributed to AtoM by hundreds of community
  translators via Artefactual's Transifex / Weblate workflow. We are
  grateful to every one of them. The collective list is too long to
  reproduce here — the canonical record is at
  https://www.transifex.com/artefactual/atom/

  **Per-locale contributor attributions** are preserved in the original
  XLIFF `<note>` elements; consult those for individual credit.

- **Plugin patterns** — `ahgMuseumPlugin`, `ahgUiOverridesPlugin`, and
  several other plugins in `atom-ahg-plugins/` informed the structure of
  Heratio's `packages/ahg-museum`, `packages/ahg-translation`, and
  related modules. The ICA Records in Contexts (RiC) tooling on the
  Heratio side is a separate development under the OpenRiC project but
  was prototyped against AtoM's data model.

AtoM is licensed under the **GNU Affero General Public License v3.0**.
Heratio is also licensed under AGPL v3.0. All AtoM-derived code,
schema, and translation files inherit AGPL terms from upstream, in
addition to The AHG's own contributions which are also AGPL.

---

## International Council on Archives — Records in Contexts (RiC)

**https://www.ica.org/  ·  https://www.ica.org/standards/RiC**

Heratio implements the ICA's Records in Contexts conceptual model and
ontology for archival description. The OpenRiC subproject
(https://ric.theahg.co.za/) extends this with a public reference API.

---

## South African language translations

The 11 official South African language translations (Afrikaans, English,
isiNdebele, isiXhosa, isiZulu, Sepedi, Sesotho, siSwati, Setswana,
Tshivenda, Xitsonga) shipped in `lang/af.json`, `lang/en.json`,
`lang/nr.json`, `lang/xh.json`, `lang/zu.json`, `lang/nso.json`,
`lang/st.json`, `lang/ss.json`, `lang/tn.json`, `lang/ve.json`,
`lang/ts.json` are authored by **The Archive and Heritage Group (Pty)
Ltd** as part of Heratio's commitment to multilingual heritage
preservation in southern Africa.

Where a key also appears in AtoM's XLIFF data, the AHG-authored value
takes precedence — this is recorded in `lang/_meta.json` with the
`source: "ahg"` flag and is preserved across re-imports of AtoM XLIFF
data via the `--mode=prefer-source` flag's hand-edit detection.

---

## Other open-source projects

Heratio bundles or depends on:

- **Laravel** (Taylor Otwell + community) — application framework
- **Bootstrap 5** + **Font Awesome** — UI framework + iconography
- **OpenSeadragon** (CrossRef + contributors) — IIIF deep-zoom viewer
- **Mirador** (Stanford Libraries + Harvard + JISC + other partners) —
  IIIF presentation viewer
- **Cantaloupe** (Alex Dolski + contributors) — IIIF Image Server
- **D3.js** (Mike Bostock + community) — visualisation library
- **Three.js** (Ricardo Cabello + community) — WebGL 3D rendering
- **spatie/laravel-csp** (Spatie + contributors) — Content Security
  Policy middleware
- **Elasticsearch** (Elastic NV + contributors) — search index
- **Ollama** (Ollama Inc. + community) — local LLM runtime for the AI
  pipeline
- **OpenAI/llava/mistral** model weights (respective providers under
  their published terms) — for the AI-services package

Each is used under the terms of its own licence. See `composer.json`,
`package.json`, and the relevant `vendor/` LICENCE files for details.

---

## How to add a new acknowledgment

If Heratio incorporates code, content, or data from a new upstream
source — even a small snippet — add an entry here in the next commit
that lands the code, citing:

1. The project name and primary URL
2. The licence
3. What specifically was incorporated
4. Where in the Heratio tree it lives

This file is the source of truth; per-package READMEs may reference it
but should not duplicate it.
