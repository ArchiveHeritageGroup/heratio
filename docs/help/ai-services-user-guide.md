> Heratio Help Center article. Category: AI / Tools.

# AI Services

The AI Services module is Heratio's central console for machine-assisted cataloguing. From a single dashboard at **Admin -> AI Services** (`/admin/ai`) you can summarize text, translate descriptions, extract named entities (people, organisations, places, dates), suggest archival descriptions, run a spellcheck, recognise handwritten text (HTR), extract structured fields from document images (Document Understanding), run AI condition assessment on images, and surface non-obvious connections between records. Every request is sent to the configured AI service through the AHG AI gateway, so no model runs inside Heratio itself - the platform is purely a client. The same dashboard also tracks usage quotas, per-call costs, a translation memory, and a custom entity gazetteer.

## Overview

AI Services is delivered by the `ahg-ai-services` package. All routes sit behind login and live under the `/admin/ai` prefix. The landing page (`/admin/ai`) shows live health cards for each configured AI service, named-entity statistics, usage counters, and quick-launch tiles for the specialist tools (HTR and Document Understanding). A built-in **Quick Test** panel lets you paste any text and immediately try Summarize, Translate, Extract Entities, Suggest Description, or Spellcheck without touching a real record.

Results that change a record (named entities, suggested descriptions, translations, spellcheck) are written to review queues rather than applied blindly. A reviewer approves, edits, or rejects each item before it reaches the catalogue, so the AI never silently overwrites curated metadata.

All inference is routed through the configured AI service (the AHG AI gateway). Heratio stores only the connection settings, the prompts, and the results - it does not bundle or host any model.

## Key features

| Feature | What it does | Where |
|---|---|---|
| Summarize | Condenses long text into a short summary (configurable max length) | Quick Test, per-object `/admin/ai/summarize/{id}` |
| Translate | Translates text into a target language; results stored in translation memory | Quick Test, per-object `/admin/ai/translate/{id}` |
| Named Entity Recognition (NER) | Extracts persons, organisations, places and dates; review and link them to actors, places or subjects | `/admin/ai/ner/extract/{id}`, review at `/admin/ai/review` |
| Suggest Description | Drafts an ISAD(G)-style scope and content description from a record's metadata and any OCR text | `/admin/ai/suggest/{id}`, review at `/admin/ai/suggest-review` |
| Spellcheck | Flags spelling issues and suggests corrections | Quick Test, `/admin/ai/spellcheck` |
| HTR (Handwritten Text Recognition) | Reads handwritten documents such as registers and certificates into typed fields | `/admin/ai/htr` |
| Document Understanding | Classifies a document image and extracts typed metadata fields | `/admin/ai/donut` |
| Condition assessment | Scores image-based condition and records damage types | `/admin/ai/condition/dashboard` |
| Suggested connections | Finds record pairs that share access points but are not linked, with an AI-written hypothesis | `/admin/ai/connections` |
| Batch queue | Runs any task type across many records with throttling and progress tracking | `/admin/ai/batch` |
| Quotas / Cost / Translation memory / Custom entities | Operational controls for usage, spend, reuse and a local gazetteer | `/admin/ai/services/*` |

### Review workflow (built into NER, Suggest, Spellcheck, Translate)

```
+------------------+      +------------------+      +------------------+
|  Run AI task on  | ---> |  Result lands in | ---> |  Reviewer        |
|  a record/text   |      |  a review queue  |      |  approves /      |
|                  |      |  (pending)       |      |  edits / rejects |
+------------------+      +------------------+      +------------------+
                                                            |
                                                  +---------+---------+
                                                  |                   |
                                              APPROVED            REJECTED
                                                  |                   |
                                                  v                   v
                                          Written to record   Discarded (logged)
```

## How to use

### Open the dashboard

1. Log in with an account that can reach the admin area.
2. Go to **Admin -> AI Services** (`/admin/ai`).
3. Check the service health cards at the top. A green **Online** badge means the configured AI service responded; **Configured** means a key is set but the service was not reached on this check; **Error/Offline** means it is unreachable.

### Quick Test (try any text)

1. On the dashboard, scroll to the **Quick Test** card.
2. Paste or type text into **Input Text**.
3. For translation, pick a **Target Language** (Afrikaans, French, Dutch, German, Portuguese, Spanish, Zulu, Xhosa are offered out of the box).
4. Click one of: **Summarize**, **Translate**, **Extract Entities**, **Suggest Description**, or **Spellcheck**.
5. The result appears inline, with the processing time shown as a badge. Extracted entities are grouped by type (persons, organisations, places, dates); spellcheck shows original, suggestion, and position.

The Quick Test is non-destructive - nothing is saved to a record.

### Named Entity Recognition on a record

1. Run extraction for a record at `/admin/ai/ner/extract/{id}` (or use the legacy `/ai/ner/extract/{id}` alias).
2. Review extracted entities at **Admin -> AI Services -> Review** (`/admin/ai/review`), or view the entities for one record at `/admin/ai/ner/entities/{id}`.
3. For each entity you can edit its value or type, then promote it: create an **actor**, a **place**, or a **subject** from the entity, or link it to an existing actor.
4. Use **bulk save** to commit a reviewed set in one action. A PDF overlay view is available to see where each entity was found in a source document.

### Suggest a description

1. Open the suggestion form for a record at `/admin/ai/suggest/{id}`.
2. The system builds a prompt from the record's title, identifier, level of description, dates, creator, repository, and any OCR text, using one of the supplied prompt templates (Standard Archival Description, Item-Level with OCR, or Photograph Description).
3. Preview the draft at `/admin/ai/suggest/{id}/preview`.
4. Review pending suggestions at `/admin/ai/suggest-review`, then approve, edit, or reject. Approved text is written to the record's scope and content.

### HTR - read handwritten documents

1. Go to **Admin -> AI Services** and click **Open HTR Dashboard** (`/admin/ai/htr`).
2. Use **Extract** (`/admin/ai/htr/extract`) and upload an image or PDF (JPG, PNG, TIFF, or PDF, up to 20 MB).
3. Optionally choose a document type; leave it on **auto** to let the extractor pick.
4. The page returns recognised fields with their positions. From the dashboard you can also batch-process, manage source folders, annotate documents to teach field positions, run spellcheck on results, and start model fine-tuning. Results can be downloaded in the available formats.

### Document Understanding

1. From the dashboard, click **Open Donut Dashboard** (`/admin/ai/donut`).
2. Use **Extract** to upload a single document image, or **Batch** for many.
3. The tool classifies the document and extracts typed metadata (for example record type, event year, event place). You can prefill a record's form from the extracted values and download batch results.

### Condition assessment

1. Open **Admin -> AI Services -> Condition Dashboard** (`/admin/ai/condition/dashboard`) for aggregate stats: total assessments, confirmed vs pending, average score, grade breakdown, top damage types, and a monthly trend.
2. Use **Assess** (`/admin/ai/condition/assess`) to search for an object and submit an image-based assessment, or **Bulk** for many objects at once.
3. Each assessment records an overall score, a condition grade, and a list of detected damage types, and can be confirmed by a curator.

### Suggested connections

1. Go to `/admin/ai/connections`.
2. Either run a collection-wide scan, or enter an `object_id` to scan one record.
3. The engine lists candidate record pairs that share access points (subjects, places, etc.) but are not directly linked, ranked by how many signals they share.
4. Click to reveal an AI-written hypothesis explaining the possible connection. The explanation is generated through the configured AI service and cached per pair.

### Batch processing

1. Go to **Admin -> AI Services -> Batch** (`/admin/ai/batch`).
2. Create a batch (`/admin/ai/batch/create`), choosing one or more task types (NER, summarize, suggest, translate, spellcheck) and the records to process.
3. Tune throughput with max concurrency, delay between jobs, and max retries.
4. Track progress on the batch view; pause, resume, or cancel from the batch actions. Drill into any single job at `/admin/ai/job/{id}`.

### Operational controls

- **Quotas** (`/admin/ai/services/quotas`) - set daily and monthly limits per tenant and per service (llm, ner, htr, donut, translate, spellcheck, face_detect). A limit of 0 means unlimited.
- **Cost** (`/admin/ai/services/cost`) - per-call cost ledger and totals by service over a chosen window, with an editable per-model pricing table.
- **Translation memory** (`/admin/ai/services/translation-memory`) - browse, search, and delete previously translated segments; reused translations save a round-trip to the service.
- **Custom NER entities** (`/admin/ai/services/ner-entities`) - maintain a local gazetteer of high-value labels (with aliases and definitions) that are matched before the ML model runs, so important local names are never missed.
- **Face detection** (`/admin/ai/services/face-detect`) - status and driver health (disabled by default).

## Configuration

Open **Admin -> AI Services -> Configuration** (`/admin/ai/config`). The page is grouped by feature and reads from the `ahg_ai_settings` table (one section each for general, NER, summarize, translate, spellcheck, and suggest), plus the LLM connection list in `ahg_llm_config`.

### General connection

| Setting | Key | Default | Meaning |
|---|---|---|---|
| Service URL | `general.api_url` | (set per install) | Base URL of the configured AI service / gateway |
| API key | `general.api_key` | empty | Key for the configured AI service |
| Timeout | `general.api_timeout` | `60` | Request timeout in seconds |

### Feature toggles and defaults (selected)

| Feature | Keys |
|---|---|
| NER | `enabled`, `confidence_threshold`, `enabled_entity_types`, `auto_link_exact`, `auto_trigger_on_upload` |
| Summarize | `enabled`, `max_length`, `min_length`, `target_field` |
| Translate | `enabled`, `supported_languages` |
| Spellcheck | `enabled`, `language`, `ignore_capitalized` |
| Suggest | `enabled`, `require_review`, `auto_expire_days`, `default_template` |
| OCR | `ocr_default_languages`, `ocr_default_psm`, `ocr_default_oem`, `ocr_llm_correction_enabled`, `ocr_llm_correction_min_confidence` |
| Job queue | `enabled`, `default_max_concurrent`, `default_delay_ms`, `default_max_retries`, `notify_on_failure` |
| Face detect | `enabled`, `driver`, `min_confidence` |

### LLM connections

Under **LLM Configurations** on the dashboard (and managed at `/admin/ai/config`) you can create, edit, and delete named connections. Each has a provider type, a model name, an endpoint URL, an optional encrypted API key, plus max tokens, temperature, and timeout. Exactly one connection can be the default. Use **Test connection** to verify a connection before relying on it.

### Prompt templates

Description suggestions use prompt templates stored in `ahg_prompt_template`. Three ship by default: Standard Archival Description (ISAD(G), all levels), Item-Level with OCR, and Photograph Description. Templates can be scoped to a level of description and to a repository. Browse them at `/admin/ai/templates`.

### Command line

The package registers artisan commands for headless/scheduled runs, including: `ahg:ner-extract`, `ahg:ai-translate`, `ahg:ai-summarize`, `ahg:ai-suggest-description`, `ahg:ai-spellcheck`, `ahg:ai-htr`, `ahg:ai-process-pending`, `ahg:condition-scan`, `ahg:condition-status`, `ahg:ner-sync`, `ahg:sync-entity-cache`, `ahg:llm-health-check`, `ahg:tesseract:list-languages`, and `ahg:ocr-page`. Run `php artisan list ahg` to see the current signatures.

## References

- Source: packages/ahg-ai-services/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/543
