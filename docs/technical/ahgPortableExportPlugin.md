# ahgPortableExportPlugin - Technical Documentation

**Version:** 1.1.0
**Category:** Export
**Dependencies:** atom-framework, ahgCorePlugin
**Load Order:** 100

---

## Overview

Standalone portable catalogue viewer plugin that exports AtoM catalogue data as a self-contained HTML/JS application for offline access on CD, USB, or downloadable ZIP. The generated viewer runs entirely client-side in any modern browser with zero server dependency.

Key capabilities: MPPT hierarchy extraction, digital object collection with checksums, FlexSearch client-side indexing, Bootstrap 5 viewer with tree navigation, edit mode with researcher exchange format (v1.0), clipboard integration, quick export from description pages, admin settings, auto-retention/cleanup.

---

## Architecture

```
+---------------------------------------------------------------------+
|                      ahgPortableExportPlugin                         |
+---------------------------------------------------------------------+
|                                                                      |
|  +---------------------------------------------------------------+  |
|  |                   Plugin Configuration                         |  |
|  |  ahgPortableExportPluginConfiguration.class.php                |  |
|  |  - Route registration (9 routes via RouteLoader)               |  |
|  |  - Module initialization                                       |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Action Methods (11)                         |  |
|  |  index | apiStartExport | apiQuickStart | apiClipboardExport  |  |
|  |  apiProgress | apiList | download | apiDelete | apiToken      |  |
|  |  + 3 helper methods (calculateExpiresAt, getSettingsDefaults, |  |
|  |    launchBackground)                                           |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Service Layer (5 classes)                   |  |
|  |                                                                |  |
|  |  ExportPipelineService                                         |  |
|  |  - Orchestrates full pipeline                                  |  |
|  |  - Progress tracking (0-100%)                                  |  |
|  |  - Error handling + cleanup                                    |  |
|  |  - Completion notification (audit trail)                       |  |
|  |       |                                                        |  |
|  |       +-> CatalogueExtractor                                   |  |
|  |       |   - MPPT hierarchy queries                              |  |
|  |       |   - Access points, events, creators                     |  |
|  |       |   - Item-level scope (clipboard support)                |  |
|  |       |   - Taxonomy extraction                                 |  |
|  |       |                                                        |  |
|  |       +-> AssetCollector                                        |  |
|  |       |   - Digital object file copying                         |  |
|  |       |   - Derivative resolution (thumb/ref/master)            |  |
|  |       |   - SHA-256 checksums                                   |  |
|  |       |                                                        |  |
|  |       +-> SearchIndexBuilder                                    |  |
|  |       |   - FlexSearch-compatible index                         |  |
|  |       |   - Multi-field indexing                                |  |
|  |       |                                                        |  |
|  |       +-> ViewerPackager                                        |  |
|  |           - Copy viewer template files                          |  |
|  |           - Write config.json                                   |  |
|  |           - Create ZIP archive                                  |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    CLI Task Layer                              |  |
|  |  portableExportTask.class.php                                  |  |
|  |  - php symfony portable:export                                 |  |
|  |  - Background job processing via nohup                         |  |
|  |                                                                |  |
|  |  portableCleanupTask.class.php                                 |  |
|  |  - php symfony portable:cleanup                                |  |
|  |  - Delete expired exports (cron-friendly)                      |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Client-Side Viewer                          |  |
|  |  index.html + app.js + tree.js + search.js + import.js        |  |
|  |  + Bootstrap 5 + FlexSearch (all bundled locally)              |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Database Tables                             |  |
|  |  portable_export | portable_export_token                      |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Settings Integration                       |  |
|  |  ahg_settings table (setting_group = 'portable_export')       |  |
|  |  11 configurable defaults via Admin > AHG Settings             |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Theme Integration                           |  |
|  |  _actionIcons.php - "Portable Viewer" on description pages    |  |
|  |  exportSuccess.php - "Portable Catalogue" on clipboard page   |  |
|  +---------------------------------------------------------------+  |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## File Structure

```
ahgPortableExportPlugin/
+-- config/
|   +-- ahgPortableExportPluginConfiguration.class.php
|   +-- routing.yml (reference only - routes via RouteLoader)
+-- database/
|   +-- install.sql (2 tables + admin menu + settings seeds)
+-- extension.json
+-- lib/
|   +-- Services/
|   |   +-- ExportPipelineService.php
|   |   +-- CatalogueExtractor.php
|   |   +-- AssetCollector.php
|   |   +-- SearchIndexBuilder.php
|   |   +-- ViewerPackager.php
|   +-- task/
|       +-- portableExportTask.class.php
|       +-- portableCleanupTask.class.php
+-- modules/
|   +-- portableExport/
|       +-- actions/
|       |   +-- actions.class.php (11 methods)
|       +-- templates/
|           +-- indexSuccess.php (4-step wizard)
+-- web/
    +-- viewer/
        +-- index.html
        +-- js/
        |   +-- app.js
        |   +-- search.js
        |   +-- tree.js
        |   +-- import.js
        +-- css/
        |   +-- viewer.css
        +-- lib/
            +-- bootstrap.bundle.min.js (~80KB)
            +-- bootstrap.min.css (~230KB)
            +-- bootstrap-icons.min.css (~86KB)
            +-- flexsearch.min.js (~16KB)
            +-- fonts/
                +-- bootstrap-icons.woff2
                +-- bootstrap-icons.woff
```

---

## Database Schema

### portable_export
```sql
CREATE TABLE IF NOT EXISTS portable_export (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    scope_type ENUM('all','fonds','repository','custom') NOT NULL DEFAULT 'all',
    scope_slug VARCHAR(255) DEFAULT NULL,
    scope_repository_id INT DEFAULT NULL,
    scope_items JSON DEFAULT NULL,                -- v1.1: Item IDs for clipboard/custom exports
    mode ENUM('read_only','editable') DEFAULT 'read_only',
    include_objects TINYINT(1) DEFAULT 1,
    include_masters TINYINT(1) DEFAULT 0,
    include_thumbnails TINYINT(1) DEFAULT 1,
    include_references TINYINT(1) DEFAULT 1,
    branding JSON DEFAULT NULL,
    culture VARCHAR(16) DEFAULT 'en',
    status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
    progress INT DEFAULT 0,
    total_descriptions INT DEFAULT 0,
    total_objects INT DEFAULT 0,
    output_path VARCHAR(1024) DEFAULT NULL,
    output_size BIGINT UNSIGNED DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,             -- v1.1: Retention expiry
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_portable_export_user (user_id),
    INDEX idx_portable_export_status (status)
);
```

### portable_export_token
```sql
CREATE TABLE IF NOT EXISTS portable_export_token (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    export_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    download_count INT DEFAULT 0,
    max_downloads INT DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (export_id) REFERENCES portable_export(id) ON DELETE CASCADE,
    INDEX idx_portable_export_token (token)
);
```

### Settings (ahg_settings)
```sql
-- 11 settings in setting_group = 'portable_export'
INSERT IGNORE INTO ahg_settings (setting_key, setting_value, setting_group, created_at, updated_at)
VALUES
('portable_export_enabled', 'true', 'portable_export', NOW(), NOW()),
('portable_export_retention_days', '30', 'portable_export', NOW(), NOW()),
('portable_export_max_size_mb', '2048', 'portable_export', NOW(), NOW()),
('portable_export_default_mode', 'read_only', 'portable_export', NOW(), NOW()),
('portable_export_include_objects', 'true', 'portable_export', NOW(), NOW()),
('portable_export_include_thumbnails', 'true', 'portable_export', NOW(), NOW()),
('portable_export_include_references', 'true', 'portable_export', NOW(), NOW()),
('portable_export_include_masters', 'false', 'portable_export', NOW(), NOW()),
('portable_export_default_culture', 'en', 'portable_export', NOW(), NOW()),
('portable_export_description_button', 'true', 'portable_export', NOW(), NOW()),
('portable_export_clipboard_button', 'true', 'portable_export', NOW(), NOW());
```

---

## Routes

| Route Name | URL | Action | Purpose |
|-----------|-----|--------|---------|
| portable_export_index | /portable-export | index | 4-step wizard + past exports |
| portable_export_api_start | /portable-export/api/start | apiStartExport | Create export from wizard, launch background job |
| portable_export_api_quick_start | /portable-export/api/quick-start | apiQuickStart | v1.1: Quick export from description page (POST slug) |
| portable_export_api_clipboard | /portable-export/api/clipboard-export | apiClipboardExport | v1.1: Export clipboard items (POST slugs) |
| portable_export_api_progress | /portable-export/api/progress | apiProgress | Poll progress (AJAX) |
| portable_export_api_list | /portable-export/api/list | apiList | List past exports (JSON) |
| portable_export_download | /portable-export/download | download | Download ZIP (admin or token) |
| portable_export_api_delete | /portable-export/api/delete | apiDelete | Delete export + files |
| portable_export_api_token | /portable-export/api/token | apiToken | Generate share token |

---

## Action Methods

### Core Actions (v1.0)
| Method | Auth | Description |
|--------|------|-------------|
| `executeIndex` | Admin | Renders 4-step wizard + past exports table |
| `executeApiStartExport` | Admin | Creates export record from wizard form data, sets expires_at, launches background job |
| `executeApiProgress` | Admin | Returns JSON with status, progress %, error message |
| `executeApiList` | Admin | Returns JSON array of past exports |
| `executeDownload` | Admin/Token | Streams ZIP download, supports token-based access |
| `executeApiDelete` | Admin | Deletes export record, files, and ZIP |
| `executeApiToken` | Admin | Creates share token with max_downloads + expires_at |

### v1.1 Actions
| Method | Auth | Description |
|--------|------|-------------|
| `executeApiQuickStart` | Admin | Accepts slug via POST, resolves IO title, creates fonds-scoped export with settings defaults |
| `executeApiClipboardExport` | Admin | Accepts comma-separated slugs via POST, resolves to IDs, creates custom-scoped export with scope_items JSON |

### Helper Methods (v1.1)
| Method | Description |
|--------|-------------|
| `calculateExpiresAt()` | Reads `portable_export_retention_days` from ahg_settings, returns DateTime |
| `getSettingsDefaults()` | Loads all 11 portable_export settings, returns associative array |
| `launchBackground($exportId)` | Extracted nohup launch logic: `nohup php symfony portable:export --export-id=N` |

---

## Service Details

### ExportPipelineService
- Entry point: `runExport(int $exportId)`
- Steps: validate → extract catalogue → collect assets → build index → package → ZIP
- Updates `portable_export.progress` at each step (0-100) for AJAX polling
- Output: `{ATOM_ROOT}/downloads/portable-exports/export-{id}/` + `.zip`
- On failure: sets status='failed' with error message
- v1.1: Parses `scope_items` JSON and passes item IDs to CatalogueExtractor
- v1.1: Calls `notifyCompletion()` on success - inserts into `audit_trail` if available

### CatalogueExtractor
- Entry point: `extract(scopeType, scopeSlug, repositoryId, ?itemIds)`
- Queries: information_object (MPPT-ordered), information_object_i18n, slug, digital_object, term, term_i18n, object_term_relation, relation, event, event_i18n, actor_i18n, repository
- Access points: subjects (taxonomy 35), places (taxonomy 42), genres (taxonomy 78)
- Creators: from events (type 111) and relations (type 161)
- Chunked queries (500 IDs per batch) for memory efficiency
- v1.1: Custom scope with `$itemIds` - queries lft/rgt ranges for each item, includes items AND descendants
- Output: `{ descriptions: [], hierarchy: [], taxonomies: {}, repositories: {} }`

### AssetCollector
- Entry point: `collect(descriptions, outputDir, options)`
- Resolves: `uploads/{path}/{name}` for masters, derivatives for thumbs/refs
- SHA-256 checksums for all copied files
- Updates description objects with relative file paths (thumbnail_file, reference_file, etc.)
- Output: `{ files: [manifest], total_size: int, descriptions: [updated] }`

### SearchIndexBuilder
- Entry point: `buildIndex(descriptions)`
- Indexed fields: title, identifier, content, level, creators, subjects, places, dates, extent
- HTML stripping + whitespace normalization
- Output: FlexSearch-compatible `{ documents: [], stats: {} }`

### ViewerPackager
- Entry point: `package(exportDir, config)` + `createZip(exportDir, zipPath)`
- Copies viewer files from `web/viewer/` to export directory
- Writes `data/config.json` with branding, mode, counts, hierarchy, repositories
- Creates ZIP with `ZipArchive` class

---

## CLI Commands

### portable:export
```
Namespace: portable
Task:      export
Class:     portableExportTask extends arBaseTask

Options:
  --scope=all|fonds|repository|custom
  --slug=<fonds-slug>
  --repository-id=<int>
  --mode=read_only|editable
  --culture=en|fr|af|pt
  --title=<string>
  --output=<path>
  --zip
  --no-objects
  --no-thumbnails
  --no-references
  --include-masters
  --export-id=<int>
```

### portable:cleanup (v1.1)
```
Namespace: portable
Task:      cleanup
Class:     portableCleanupTask extends arBaseTask

Options:
  --dry-run         Preview what would be deleted (no actual deletion)
  --older-than=<N>  Override retention period (delete exports older than N days)

Behavior:
  1. Reads retention_days from ahg_settings (default: 30)
  2. Finds exports where expires_at has passed
  3. Finds completed/failed exports older than retention period
  4. For each: deletes ZIP file, output directory, and database record
  5. Logs count of deleted exports
```

---

## Theme Integration (v1.1)

### Description Page - _actionIcons.php (ahgThemeB5Plugin)

When `ahgPortableExportPlugin` is enabled, a "Portable Viewer" link appears in the Export section of the information object sidebar. The link:
1. Posts the description's slug to `/portable-export/api/quick-start`
2. API creates a fonds-scoped export with default settings
3. Shows spinner while starting, then redirects to `/portable-export`
4. Visibility controlled by `portable_export_description_button` setting

### Clipboard Page - exportSuccess.php (ahgThemeB5Plugin)

When `ahgPortableExportPlugin` is enabled, a "Portable Catalogue" button appears next to the Export/Cancel buttons. The button:
1. Reads clipboard slugs from `localStorage['atom-clipboard-informationObject']`
2. Falls back to hidden form fields if localStorage is empty
3. Posts comma-separated slugs to `/portable-export/api/clipboard-export`
4. API resolves slugs → IDs, creates custom-scoped export with `scope_items` JSON
5. Shows spinner, then redirects to `/portable-export`
6. Visibility controlled by `portable_export_clipboard_button` setting

---

## Settings Integration (v1.1)

### Registration in ahgSettingsPlugin

Plugin registered in `sectionAction.class.php`:
- **Section:** `portable_export` (label: "Portable Export", icon: fa-compact-disc)
- **Plugin Map:** `'portable_export' => 'ahgPortableExportPlugin'` (section hidden if plugin not enabled)
- **Checkbox Fields:** portable_export_enabled, portable_export_include_objects, portable_export_include_thumbnails, portable_export_include_references, portable_export_include_masters, portable_export_description_button, portable_export_clipboard_button
- **Templates:** Both `section.blade.php` and `sectionSuccess.php` have the portable_export case

### Settings Used By Actions

| Setting Key | Used By | Purpose |
|-------------|---------|---------|
| portable_export_retention_days | calculateExpiresAt() | Sets expires_at on new exports |
| portable_export_default_mode | apiQuickStart, apiClipboardExport | Default viewer mode |
| portable_export_include_objects | apiQuickStart, apiClipboardExport | Default include objects |
| portable_export_include_thumbnails | apiQuickStart, apiClipboardExport | Default include thumbs |
| portable_export_include_references | apiQuickStart, apiClipboardExport | Default include refs |
| portable_export_include_masters | apiQuickStart, apiClipboardExport | Default include masters |
| portable_export_default_culture | apiQuickStart, apiClipboardExport | Default language |
| portable_export_description_button | _actionIcons.php (visual only) | Show/hide sidebar link |
| portable_export_clipboard_button | exportSuccess.php (visual only) | Show/hide clipboard button |

---

## Client-Side Viewer

### app.js
- Main application: data loading, routing, state management, rendering
- Loads catalogue.json, config.json, search-index.json via XHR
- Renders ISAD(G) description detail views with breadcrumbs
- Supports digital object inline viewing (images, PDFs)
- Edit mode: research notes textarea per description

### tree.js
- Hierarchical tree navigation from config.hierarchy
- Expand/collapse with MPPT ordering preserved
- Level-specific icons (fonds=archive, series=folder, file=document, item=text)
- Ancestor expansion for deep linking
- Expand All / Collapse All buttons

### search.js
- FlexSearch Document index with multi-field search
- Fields: title, identifier, content, creators, subjects, places, dates
- Auto-search with 300ms debounce
- Snippet generation with query highlighting
- Fallback: simple substring match if FlexSearch unavailable

### import.js (edit mode only)
- Drag-drop / file picker for importing files
- Files stored as base64 data URLs in memory
- Caption field per imported file
- Notes summary panel
- Export as researcher-exchange.json (v1.0 format)

---

## Exchange Format (v1.0)

```json
{
  "format_version": "1.0",
  "source": "portable-viewer",
  "exported_at": "2026-02-14T10:30:00Z",
  "source_config": {
    "title": "Portable Catalogue",
    "scope_type": "all",
    "culture": "en"
  },
  "collections": [
    {
      "title": "Research Notes",
      "type": "notes",
      "items": [
        {
          "reference_id": 123,
          "reference_slug": "example-description",
          "reference_identifier": "REF-001",
          "title": "Description Title",
          "level_of_description": "file",
          "note": "User-added research note text"
        }
      ]
    },
    {
      "title": "Imported Files",
      "type": "files",
      "items": [
        {
          "title": "Site A Overview",
          "level_of_description": "item",
          "scope_and_content": "Photo caption",
          "files": [
            {
              "filename": "photo.jpg",
              "caption": "Overview",
              "mime_type": "image/jpeg",
              "size": 234567
            }
          ]
        }
      ]
    }
  ]
}
```

This format is produced by ahgPortableExportPlugin (viewer edit mode) and consumed by ahgResearcherPlugin (import).

---

## Background Job Pattern

Web UI launches export via nohup:
```bash
nohup php {ATOM_ROOT}/symfony portable:export --export-id={ID} > {log} 2>&1 &
```

Progress polling via AJAX every 2 seconds to `/portable-export/api/progress?id={ID}`.

---

## Notification (v1.1)

On successful export completion, `ExportPipelineService::notifyCompletion()`:
1. Formats a message: `Portable export "Title" completed: N descriptions, N objects (X MB)`
2. If `audit_trail` table exists (ahgAuditTrailPlugin enabled): inserts record with `action='export_completed'`, `object_type='portable_export'`
3. If audit_trail not available: logs to PHP `error_log`

---

## Security

- All actions require admin authentication (except token-based download)
- Download tokens: 64-byte random hex, optional max_downloads + expires_at
- Token-based downloads bypass admin auth but are scoped to a single export
- No user data exposed in the static viewer (only catalogue metadata)
- Quick-start and clipboard APIs require admin session (CSRF-free POST endpoints)

---

## Changelog

### v1.1.0
- **Quick export** from description pages (sidebar "Portable Viewer" link)
- **Clipboard export** ("Portable Catalogue" button on clipboard export page)
- **4-step wizard UI** (Scope → Content → Configure → Review & Generate)
- **Auto-retention** with `expires_at` column and configurable retention period
- **Cleanup CLI** (`php symfony portable:cleanup` with --dry-run support)
- **Admin settings** (11 configurable defaults at Admin > AHG Settings > Portable Export)
- **Completion notification** (audit trail integration)
- **scope_items** column for item-level custom scope (clipboard support)
- CatalogueExtractor updated for item ID-based scope with MPPT descendant inclusion

### v1.0.0
- Initial release with full export pipeline and static viewer
- CLI command: `php symfony portable:export`
- Web UI with progress tracking
- FlexSearch client-side search
- Edit mode with researcher exchange format v1.0
- Secure download tokens with expiry and limits
