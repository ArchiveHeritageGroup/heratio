# OCR Phase 4 - multi-language Tesseract + LLM post-correction (issue #665)

**Status:** shipped 2026-05-26. Builds on Phase 1-3 (production install +
ALTO/hOCR/PAGE-XML export from `OcrExportService`).

**Scope:** turn the inline `exec('tesseract ...')` hack into a properly
instrumented production pipeline with multi-language support, optional
LLM post-correction, full PREMIS event emission, and persistence into
the existing `iiif_ocr_text` / `iiif_ocr_block` tables that
`OcrExportService` already reads.

## What ships in Phase 4

| File | Purpose |
|---|---|
| `packages/ahg-ai-services/src/Services/OcrService.php` | Tesseract wrapper: multi-lang, TSV parsing, persistence, PREMIS emit |
| `packages/ahg-ai-services/src/Services/OcrLlmCorrector.php` | LLM post-correction with inline `[orig->corrected]` markers, audit + PREMIS |
| `packages/ahg-ai-services/src/Commands/TesseractListLanguagesCommand.php` | `php artisan ahg:tesseract:list-languages` |
| `packages/ahg-ai-services/src/Commands/OcrPageCommand.php` | `php artisan ahg:ocr:page <path>` |
| `packages/ahg-ai-services/database/install.sql` | Section 13: seeds `ahg_ai_settings.feature='ocr'` defaults |
| `packages/ahg-ai-services/tests/Unit/OcrServiceTest.php` | TSV parser + ISO-639 mapping coverage |
| `packages/ahg-ai-services/tests/Unit/OcrLlmCorrectorTest.php` | Inline-marker parser + prompt-builder coverage |

## Multi-language resolution order

`OcrService::resolveLanguages(?int $ioId, ?string $lang)` walks four
sources, first non-empty wins:

1. caller-supplied `$lang` (artisan `--lang=eng+afr`, controller param)
2. `ahg_ai_settings.feature='ocr'/setting_key='ocr_default_languages'`
3. `information_object_i18n.language` mapped via `iso639ToTesseract()`
4. hardcoded `OcrService::DEFAULT_LANGS = 'osd+eng+afr'`

ISO 639-1 / -2 -> Tesseract trained-data map covers the SADC core
(`af`, `zu`, `xh`, `st`, `tn`, `ts`, `ve`, `nr`, `ss`, `nso`, `sn`),
the EU core (`en`, `nl`, `pt`, `fr`, `de`, `es`, `it`), and Latin.
Unknown codes pass through unchanged so operators can drop custom
traineddata into `/usr/share/tesseract-ocr/5/tessdata/` and have it
accepted.

## LLM post-correction

Disabled by default (`ocr_llm_correction_enabled = 0`). When enabled,
it only runs for pages whose Tesseract mean confidence is **below**
`ocr_llm_correction_min_confidence` (default 70). The correction prompt
is deliberately conservative:

- Only OCR-typical patterns (`rn` <-> `m`, `O` <-> `0`, `l` <-> `1`, `cl` <-> `d`, broken hyphenation)
- Never paraphrase, never modernise spelling, never restructure
- Preserve archaic spelling + proper nouns exactly
- Mark every correction inline as `[orig->corrected]` so a human reviewer can audit each change

The corrector parses those markers back into a structured corrections
array, strips them from the final text, and writes:

- one inference receipt into the #693 receipt chain (via `LlmService::complete` -> `logInferenceReceipt`)
- one row into `security_audit_log` via `AhgCore\Support\AuditLog::captureSecondaryMutation` (`ocr.llm_correction`)
- one `preservation_event` row of type `ocr.llm_correction` containing the truncated correction list

## PREMIS events

Per `preservation_event` row:

| event_type | linking_agent_value | event_outcome | event_outcome_detail |
|---|---|---|---|
| `ocr.tesseract` | `ahg-ai-services:OcrService tesseract <version>` | `success` / `failure` | `{language, psm, oem, confidence, word_count}` |
| `ocr.llm_correction` | `ahg-ai-services:OcrLlmCorrector <model>` | `success` | `{model, correction_count, corrections[<=40], duration_ms}` |

The PREMIS XML serializer in `packages/ahg-preservation/src/Services/PremisXmlSerializer.php`
already iterates `preservation_event` rows, so the OCR events surface
in the per-IO PREMIS export with no extra wiring (#653 Phase 1).

## Operator preconditions

1. `tesseract` binary on `$PATH` (Debian/Ubuntu: `apt install tesseract-ocr tesseract-ocr-eng tesseract-ocr-afr`).
   Verify with `php artisan ahg:tesseract:list-languages`.
2. An LLM endpoint reachable through the existing `LlmService` (cloud
   override at `ai.theahg.co.za` is fine). If `ocr_llm_correction_enabled = 1`
   but no LLM is configured, the service degrades gracefully -
   `OcrLlmCorrector::correct` returns `skipped: true, reason: llm_call_failed`
   and the Tesseract output is kept unmodified.

## Known gaps (Phase 5+)

- JHOVE format validation on the source image before OCR (#665 / #653)
- Replication PREMIS event into `preservation_event` after backup commit (#671)
- Cantaloupe IIIF tile pre-fetch as a separate `preservation_event`
- Quality scoring against a held-out test corpus
