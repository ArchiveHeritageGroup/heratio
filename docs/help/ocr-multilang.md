# Multi-language OCR with optional LLM post-correction

Heratio runs Tesseract over every TIFF / JP2 / PDF page you ingest. Phase 4
adds:

- **Multi-language packs.** Each information object can be OCR'd against
  one or more Tesseract language packs at once (English + Afrikaans is
  the default for SA collections; Zulu, Xhosa, Sotho, Tswana, Tsonga,
  Venda, Ndebele, Swati, Pedi, Shona, Portuguese, French, Dutch, German,
  Spanish, Italian, and Latin are all wired in).
- **LLM post-correction (opt-in).** A second pass over the raw Tesseract
  text fixes typical OCR confusions (`rn` -> `m`, `O` <-> `0`, `l` <-> `1`,
  `cl` <-> `d`, broken hyphenation) without paraphrasing. Every edit is
  recorded so a human can audit it.
- **PREMIS events.** Every OCR run and every LLM correction emits a
  `preservation_event` row that surfaces in your PREMIS XML export
  alongside virus checks, fixity, and format identification.

## 1. Check what language packs the server has

Run this once after installing Tesseract:

```
php artisan ahg:tesseract:list-languages
```

You will see something like:

```
Tesseract version: tesseract 5.3.4
Installed language packs (12):
  afr  deu  eng  fra  ita  lat
  nld  osd  por  spa  zul  xho
```

The result is cached into `ahg_ai_settings.ocr_languages` so the rest
of the application can render dropdowns and validate language specs.

Missing a pack you need? On Debian / Ubuntu:

```
sudo apt install tesseract-ocr-afr tesseract-ocr-zul tesseract-ocr-xho
```

Then re-run `ahg:tesseract:list-languages` to refresh the cache.

## 2. Pick the default language spec

Settings live under `ahg_ai_settings` with `feature = 'ocr'`:

| Key | Default | Meaning |
|---|---|---|
| `ocr_default_languages` | `osd+eng+afr` | Tesseract `+`-joined spec used when no per-IO language is detected. `osd` adds orientation + script detection. |
| `ocr_default_psm` | `3` | Page-segmentation mode (3 = fully automatic, 4 = single column of text, 6 = single uniform block). |
| `ocr_default_oem` | `3` | OCR engine mode (3 = default, LSTM if available). |
| `ocr_tesseract_binary` | `tesseract` | Path to the binary. Override when Tesseract is installed somewhere unusual. |

When an information object has a known language (`information_object_i18n.language`),
that language wins over the global default. Heratio maps ISO codes like
`af`, `zu`, `pt` onto the right Tesseract packs automatically.

## 3. Enable LLM post-correction (optional)

Two settings:

| Key | Default | Meaning |
|---|---|---|
| `ocr_llm_correction_enabled` | `0` | Set to `1` to run the LLM pass after Tesseract. |
| `ocr_llm_correction_min_confidence` | `70` | Only post-correct pages whose Tesseract mean confidence falls below this number. High-confidence pages are kept untouched to save tokens. |

The LLM model is whichever provider is configured as default in
`ahg_llm_config` (Ollama, OpenAI, Anthropic, or your AHG cloud
gateway). The prompt is conservative: it only fixes OCR-typical
patterns and refuses to paraphrase. Every correction the model makes
is logged into `security_audit_log` and into a `preservation_event`
row of type `ocr.llm_correction`.

## 4. OCR a single page from the command line

For debugging / spot-checking:

```
php artisan ahg:ocr:page /mnt/nas/heratio/archive/page-001.tif
php artisan ahg:ocr:page page.jpg --lang=eng+afr --psm=4
php artisan ahg:ocr:page page.jpg --io=12345 --do=4242 --persist
php artisan ahg:ocr:page page.jpg --llm-correct --json
```

Flags:

- `--lang=` Tesseract spec (e.g. `eng+afr`, `osd+nld`)
- `--psm=` / `--oem=` per-run overrides
- `--io=` / `--do=` link the PREMIS event to an information_object / digital_object
- `--persist` write the result into `iiif_ocr_text` + `iiif_ocr_block`
  so ALTO / hOCR / PAGE-XML export works for that IO
- `--llm-correct` force the LLM pass on for this run even if the global
  setting is off

## 5. PREMIS chain of custody

Every OCR run and every LLM correction shows up in the per-IO PREMIS
export. To see the audit trail for a record, open it in Heratio and
go to **Preservation -> PREMIS events**, or run:

```
SELECT event_type, event_outcome, event_detail, linking_agent_value, event_datetime
  FROM preservation_event
 WHERE information_object_id = <io_id>
   AND event_type LIKE 'ocr.%'
 ORDER BY event_datetime DESC;
```

You will see one row per OCR run (`ocr.tesseract`) and one row per LLM
post-correction (`ocr.llm_correction`).
