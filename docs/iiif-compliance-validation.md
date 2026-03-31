# Heratio — IIIF Compliance Hardening

## Overview

The **IIIF Compliance Hardening** enhancement brings Heratio's IIIF implementation into full alignment with IIIF Presentation API 3.0, which is now the default manifest format (with v2.1 fallback via `?format=2`). It adds manifest validation, quality control dashboards, and integrates IIIF readiness checks into the publish gate system.

This feature is part of the Heratio framework v2.8.2 by The Archive and Heritage Group (Pty) Ltd.

## Key Features

### Manifest Enhancements (IIIF Presentation API 3.0)
- **ImageService3**: Declares both `ImageService2` and `ImageService3` types for maximum viewer compatibility
- **Rights**: Extracts Creative Commons and RightsStatements.org URIs from the rights table
- **Required Statement**: Automatically includes institution attribution from repository metadata or IIIF settings
- **Provider**: Adds institution details (name, homepage) from the linked repository
- **seeAlso**: Links back to the AtoM archival description page
- **Thumbnail Dimensions**: Explicit width/height on thumbnail resources (200px scaled)

### IIIF Validation Service
- **Structural Checks**: Validates manifest label, canvases, annotations, and context
- **Rights Presence**: Checks if rights statements are assigned (for manifest `rights` field)
- **Required Statement**: Verifies institution attribution is available
- **Image Service Health**: Probes Cantaloupe for availability
- **Derivative Verification**: Checks that digital object files exist on disk
- **Batch Validation**: Validate multiple objects with summary statistics

### Validation Dashboard
- **QC Overview**: Card-based statistics showing passed, failed, and warning counts
- **Recent Failures**: Table of recent validation failures with details
- **Quick Validate**: One-click validation for any object with digital objects
- **Real-Time Results**: AJAX validation with inline result display

### Publish Gate Integration
- **IIIF Ready Rule**: The `iiif_ready` gate rule type delegates to the IIIF Validation Service
- **Automatic Fallback**: If the IIIF plugin isn't installed, basic derivative checks are used
- **Gate Status**: Failed IIIF checks appear in the publish readiness dashboard

## Validation Checks

| Check | Type | Description |
|-------|------|-------------|
| `label_present` | Required | Manifest has a non-empty label (title) |
| `has_canvases` | Required | At least one canvas with painting annotation |
| `thumbnail_present` | Recommended | Thumbnail can be derived from first canvas |
| `rights_present` | Recommended | Rights statement available for manifest |
| `required_statement` | Recommended | Institution attribution available |
| `image_service` | Health | Cantaloupe image server is responding |
| `derivatives_exist` | Required | Digital object files exist on disk |

## Architecture

### Database Tables
- `iiif_validation_result` — Stores validation results per object per check

### Services
- `IiifValidationService` — Manifest validation, derivative checking, batch QC
- `IiifManifestV3Service` — Enhanced with rights, provider, seeAlso, ImageService3

### Access Points
| URL | Purpose |
|-----|---------|
| `/admin/iiif-validation` | IIIF validation dashboard |
| `/admin/iiif-validation/run/:id` | Run validation for specific object (AJAX) |
| `/iiif/manifest/:slug` | Default manifest endpoint (v3 since M2, ?format=2 for v2.1 fallback) |
| `/iiif/compare` | Side-by-side comparison viewer (Mirador mosaic) |

## Sample Manifest Output (New Fields)

```json
{
  "@context": "http://iiif.io/api/presentation/3/context.json",
  "type": "Manifest",
  "rights": "https://creativecommons.org/licenses/by/4.0/",
  "requiredStatement": {
    "label": {"none": ["Attribution"]},
    "value": {"en": ["National Archives of South Africa"]}
  },
  "provider": [{
    "id": "https://example.org/repository-slug",
    "type": "Agent",
    "label": {"en": ["National Archives of South Africa"]},
    "homepage": [{"id": "https://example.org", "type": "Text"}]
  }],
  "seeAlso": [{
    "id": "https://example.org/record-slug",
    "type": "Dataset",
    "format": "text/html"
  }],
  "thumbnail": [{
    "type": "Image",
    "width": 200,
    "height": 267,
    "service": [
      {"type": "ImageService2", "profile": "level2"},
      {"type": "ImageService3", "profile": "level2"}
    ]
  }]
}
```

## Technical Requirements

- PHP 8.1+
- MySQL 8.0+
- Heratio Framework v2.8.2+
- ahgIiifPlugin (required)
- Cantaloupe IIIF Image Server (recommended for full functionality)
- ahgWorkflowPlugin (optional, for publish gate integration)

## Standards Compliance

- IIIF Presentation API 3.0
- IIIF Image API 2.1 and 3.0 (dual service declaration)
- Creative Commons license URIs
- RightsStatements.org vocabulary
- W3C Web Annotation Data Model (existing annotation support)
- IIIF Authentication API 1.0 (login, clickthrough, kiosk, external profiles)

---

*The Archive and Heritage Group (Pty) Ltd*
*https://github.com/ArchiveHeritageGroup*
