# Translation Catalog Quality - Afrikaans (af)

**Status:** flagged 2026-05-02 - bulk re-translation pass needed.
**Scope:** `lang/af.json` in the Heratio Laravel app.

## The flag

Hand-review of `/admin/translation/strings?locale=af` through page 6 (~600 keys) shows that a large fraction of the existing `lang/af.json` entries read like Dutch, not Afrikaans. The two languages are close enough that machine translation pipelines often conflate them, especially when the source model has a stronger Dutch corpus than an Afrikaans one. The result on Heratio is pseudo-Dutch shipped under the `af` label, which a native Afrikaans reader will immediately notice.

## Concrete examples

| Key                                 | Current af value                                  | Problem                                                                                                       | Native Afrikaans                  |
| ----------------------------------- | ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | --------------------------------- |
| `Archival Descriptions`             | `Akhilingsbeskrywings`                            | "Akhiling" is not an Afrikaans word - looks like a transliteration error. The af for "archive" is `argief`.   | `Argiefbeskrywings`               |
| `Archival description images carousel` | `Afbeeldingscarrousel van akhilingsbeskrywing` | Dutch syntax (`afbeeldingscarrousel`, `van`-genitive) + the same fake "akhiling" stem.                        | `Afbeeldingsroteerder van argiefbeskrywing` (or similar - native review needed) |
| `Archival Record`                   | `Akhilingsrekord`                                 | Same fake stem.                                                                                               | `Argiefrekord`                    |
| various `image*`                    | `afbeelding*`                                     | "Afbeelding" is Dutch for image; Afrikaans uses `beeld` or `prent`.                                           |                                   |

## Likely cause

The af catalog was bulk-MT'd, probably through a pipeline whose intermediate language was Dutch (en->nl->af) or whose model treated nl/af as interchangeable. The MT-suggest button in `/admin/translation/strings` uses the same backend translator, so its suggestions for af are also suspect - they should be treated as drafts that need a native-speaker pass before approval.

The plural keys `Archival descriptions`, `Authority records`, `Archival institutions`, `Functions` were missing from `lang/af.json` entirely until 2026-05-02; they have been added with corrected (non-Dutch) Afrikaans forms (`Argiefbeskrywings`, `Gesagsrekords`, `Argiefinstellings`, `Funksies`).

## Why this matters

Heratio is a multi-market product. South Africa, Namibia, Botswana, and the rest of SADC have native Afrikaans-speaking GLAM users who will lose trust in the product if the "Afrikaans" UI reads like Google Translate from a Dutch source. This is a quality-of-product issue, not just a localisation bug.

## Recommended fix path

1. Do **not** simply re-run the existing MT pipeline against `lang/en.json` - it will produce more of the same.
2. Pick one of:
   - A Helsinki-NLP / OPUS-MT model with an explicit `en->af` direction (not generic multilingual).
   - A pass through DeepL or Google Translate with the target language pinned to `af` (Afrikaans), then a native-speaker review.
   - Crowdsource via an Afrikaans-speaking archivist on the team.
3. Use `/admin/translation/strings` for the human-review step. The page now defaults its column to the request culture, so a reviewer can step through key-by-key with en + af side-by-side. Inline edits write atomically to `lang/af.json` (under `flock`, `ksort`, `JSON_PRETTY_PRINT`) so the file stays git-friendly.
4. While reviewing, watch for these Dutch tells: `ij`, `het ` (def article), `een ` (indef article), `afbeelding`, `akhiling`, `lijst`, `gebruiker` (af is `gebruiker` too, but in Dutch contexts it often surfaces with Dutch syntax around it).

## Possible scan

A future task is to grep `lang/af.json` for the Dutch-leaning tokens above and produce a worklist file of suspect rows for human review. This avoids re-reading every page of the strings editor by hand.

## Carry-over

Same suspicion applies to other close-pair languages translated through the same pipeline (e.g. `nl`, `fy`). When that work is touched, sample-check before approving en masse.

## Related

- Editor: `/admin/translation/strings` (defaults to request culture as the editing column).
- Per-record translator: "Translate" entry in the More menu on Archival Description / Authority / Repository / etc. show pages - same MT backend, same caveat.
- Workflow: admins auto-apply unless they tick "Request second review"; editors always go through the review queue at `/admin/translation/strings/pending`.
