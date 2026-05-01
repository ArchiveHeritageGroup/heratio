# Heratio - IIIF AI Extraction

## Feature Overview

**Component:** IiifAiService (ahgAIPlugin)
**Version:** 2.8.2
**Category:** AI & Automation / Digital Preservation
**Publisher:** The Archive and Heritage Group (Pty) Ltd

---

## Summary

The IIIF AI Extraction module bridges the IIIF Viewer plugin (ahgIiifPlugin) with the AI Services plugin (ahgAIPlugin), enabling automated metadata extraction from digitized archival images. It provides a unified pipeline that orchestrates OCR, Named Entity Recognition (NER), translation, summarization, and face detection against IIIF-served images via an external AI server.

## Key Features

### Automated Extraction Pipeline
- **OCR** - Extracts text from digitized documents (manuscripts, typed records, printed material) using Tesseract via the external AI server
- **Named Entity Recognition** - Identifies persons, organizations, places, and dates from OCR text using spaCy NLP models
- **Translation** - Translates extracted text between language pairs using Argos Translate
- **Summarization** - Generates concise summaries of extracted text content
- **Face Detection** - Detects human faces in photographic material for potential authority record matching

### Architecture
- **External AI Server**: All AI processing is performed by a dedicated server (`http://<host>:5004/ai/v1`), keeping AtoM's web server lightweight
- **Bridge Pattern**: IiifAiService composes existing services (NerService, IIIF annotation services) rather than duplicating functionality
- **Configurable Pipeline**: Each extraction step can be enabled/disabled independently per request
- **Batch Processing**: Supports processing all digital objects for an information object in a single call

### Data Storage
- OCR text stored in `iiif_ocr_text` and `iiif_ocr_block` tables (existing IIIF infrastructure)
- NER entities stored in `ahg_ner_entity` table (existing AI infrastructure)
- Annotations stored in `iiif_annotation` and `iiif_annotation_body` tables (W3C Web Annotation model)
- Extraction history tracked in `ai_iiif_extraction` table (new)

### Settings Integration
- AI server URL, API key, and timeout configurable via **Admin > AHG Settings > AI Services**
- Settings consolidated into `ahg_ai_settings` table (single source of truth)
- Backward-compatible fallback to legacy `ahg_ner_settings` table during migration period
- Per-feature toggles for OCR, NER, translation, summarization, and face detection

### Extraction Tracking
- Every extraction operation logged with status, duration, error messages, and result counts
- Status dashboard shows extraction coverage across the collection
- Statistics API for reporting on AI processing activity

## Database Tables

| Table | Purpose |
|-------|---------|
| `ai_iiif_extraction` | Extraction job log - tracks each pipeline run per digital object |
| `ahg_ai_settings` | Consolidated AI settings (feature + setting_key + value) |
| `iiif_ocr_text` | Full OCR text per digital object (existing) |
| `iiif_ocr_block` | OCR text blocks with coordinates (existing) |
| `ahg_ner_entity` | Extracted named entities (existing) |
| `iiif_annotation` | W3C Web Annotations (existing) |

## Technical Requirements

| Requirement | Details |
|-------------|---------|
| Heratio | v2.8.2+ with atom-framework |
| Plugins Required | ahgAIPlugin, ahgIiifPlugin |
| External AI Server | Python server with spaCy, Argos Translate, Tesseract |
| AI Server Endpoint | `http://<host>:5004/ai/v1` |
| PHP | 8.3+ |
| MySQL | 8.0+ |

## Standards Compliance

- **W3C Web Annotation Data Model** - NER and face detection results stored as IIIF annotations
- **IIIF Presentation API 3.0** - Compatible with IIIF manifests and canvases
- **OAIS** - Extracted metadata enriches Archival Information Packages (AIPs)

## Use Cases

1. **Bulk OCR of digitized collections** - Process thousands of scanned documents to make them full-text searchable
2. **Automated entity extraction** - Identify people, places, and organizations mentioned in archival documents
3. **Multi-language collections** - Translate extracted text for cross-language discovery
4. **Photographic archives** - Detect faces in historical photographs for authority record linking
5. **Collection summarization** - Generate concise descriptions of lengthy archival documents

## Related Components

- [AI Tools User Guide](ai-tools-user-guide.md)
- [IIIF Integration User Guide](iiif-integration-user-guide.md)
- [Data Ingest User Guide](data-ingest-user-guide.md) - Ingest pipeline can trigger AI extraction

---

*Heratio is developed by The Archive and Heritage Group (Pty) Ltd for GLAM and DAM institutions worldwide.*
