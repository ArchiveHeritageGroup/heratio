# Media Processing & Asset Services - AtoM vs Heratio

Generated: 2026-03-17

## Summary

AtoM has 18 media/asset processing features across multiple plugins and framework services. Heratio has 3 partially ported. **15 features are NOT PORTED.**

---

## Status Table

| # | Feature | AtoM Source | Heratio Status | Priority |
|---|---------|-------------|----------------|----------|
| 1 | 3D Model Handling (viewer, thumbnails, AR, TripoSR) | ahg3DModelPlugin | NOT PORTED | High |
| 2 | IIIF Manifests v3 + Auth + Annotations | ahgIiifPlugin | PARTIAL (collection only) | High |
| 3 | Metadata Extraction (EXIF/IPTC/XMP/PDF) | ahgMetadataExtractionPlugin | NOT PORTED | High |
| 4 | AI/LLM Services (NER, summarize, translate, spellcheck) | ahgAIPlugin | NOT PORTED | High |
| 5 | Face Detection (OpenCV, Rekognition, Azure) | ahgAIPlugin | NOT PORTED | Medium |
| 6 | AI Condition Assessment | ahgAiConditionPlugin | NOT PORTED | Medium |
| 7 | Image Derivatives (thumbnails, reference) | DigitalObjectService | PARTIAL (basic) | High |
| 8 | Watermarking (position, opacity, classification) | WatermarkService | NOT PORTED | Medium |
| 9 | TIFF/PDF Merge (PDF/A archival) | TiffPdfMergeService | NOT PORTED | Medium |
| 10 | 3D Thumbnails (Blender rendering) | ThreeDThumbnailService | NOT PORTED | Medium |
| 11 | Media Streaming/Transcoding (FFmpeg) | ahgIiifPlugin/media | NOT PORTED | High |
| 12 | PDF Text Extraction (search indexing) | DigitalObjectExtractTextCommand | NOT PORTED | High |
| 13 | Derivative Regeneration (batch CLI) | DigitalObjectRegenDerivativesCommand | NOT PORTED | Medium |
| 14 | Digital Asset Management (DAM) | ahgDAMPlugin | NOT PORTED | Low |
| 15 | Preservation Events (PREMIS, migration) | ahgPreservationPlugin | PARTIAL | Medium |
| 16 | Integrity/Fixity Checking | ahgIntegrityPlugin | NOT PORTED | Medium |
| 17 | File Encryption (at-rest) | FileEncryptionService | NOT PORTED | Low |
| 18 | Metadata Export (12+ formats: BIBFRAME, LIDO, RiCO, etc.) | ahgMetadataExportPlugin | NOT PORTED | Medium |

---

## Packages to Create

### Priority 1 (Core media pipeline)
- **ahg-3d-model** - 3D viewer, Blender thumbnails, multi-angle, AR.js, TripoSR
- **ahg-media-processing** - Image derivatives (thumbnail/reference), watermarking, format conversion
- **ahg-media-streaming** - FFmpeg transcoding, HTTP range streaming for video/audio
- **ahg-metadata-extraction** - EXIF/IPTC/XMP/PDF metadata via ExifTool
- **ahg-pdf-tools** - Text extraction (pdftotext), TIFF/PDF merge, PDF/A generation

### Priority 2 (AI & intelligence)
- **ahg-ai-services** - LLM integration (OpenAI/Anthropic/Ollama), NER, summarization, translation, spellcheck, transcription
- **ahg-face-detection** - Face detection via OpenCV/cloud APIs, person linking
- **ahg-ai-condition** - AI condition assessment with computer vision

### Priority 3 (Standards & compliance)
- **ahg-metadata-export** - Export to BIBFRAME, LIDO, RiCO, VRA Core, Schema.org, PREMIS, EAD3, PBCore, EBUCore, MODS
- **ahg-file-encryption** - Transparent file encryption at rest
- **ahg-dam** - Full digital asset management features

---

## External Dependencies

| Tool | Used For | Install |
|------|----------|---------|
| Blender | 3D thumbnails, multi-angle renders | `snap install blender --classic` |
| FFmpeg/FFprobe | Video/audio transcoding, duration detection | `apt install ffmpeg` |
| ImageMagick | Image derivatives, watermarks, PDF generation | `apt install imagemagick` |
| Ghostscript | PDF/A conversion | `apt install ghostscript` |
| ExifTool | Metadata extraction | `apt install libimage-exiftool-perl` |
| pdftotext | PDF text extraction | `apt install poppler-utils` |
| Tesseract | OCR (optional) | `apt install tesseract-ocr` |
| OpenCV | Face detection (local) | Python package |

---

## AtoM Source Locations

- 3D: `/usr/share/nginx/archive/atom-ahg-plugins/ahg3DModelPlugin/`
- IIIF: `/usr/share/nginx/archive/atom-ahg-plugins/ahgIiifPlugin/`
- Metadata: `/usr/share/nginx/archive/atom-ahg-plugins/ahgMetadataExtractionPlugin/`
- AI: `/usr/share/nginx/archive/atom-ahg-plugins/ahgAIPlugin/`
- AI Condition: `/usr/share/nginx/archive/atom-ahg-plugins/ahgAiConditionPlugin/`
- Framework services: `/usr/share/nginx/archive/atom-framework/src/Services/`
- Framework commands: `/usr/share/nginx/archive/atom-framework/src/Console/Commands/`
- Preservation: `/usr/share/nginx/archive/atom-ahg-plugins/ahgPreservationPlugin/`
- Integrity: `/usr/share/nginx/archive/atom-ahg-plugins/ahgIntegrityPlugin/`
- DAM: `/usr/share/nginx/archive/atom-ahg-plugins/ahgDAMPlugin/`
- Export: `/usr/share/nginx/archive/atom-ahg-plugins/ahgMetadataExportPlugin/`
