# ahgLandingPagePlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Admin
**Dependencies:** ahgCorePlugin, atom-framework

---

## Overview

Visual landing page builder with drag-and-drop blocks for creating custom archive home pages and promotional pages. Enables administrators to build responsive landing pages without coding knowledge.

---

## Architecture

```
+---------------------------------------------------------------------+
|                      ahgLandingPagePlugin                            |
+---------------------------------------------------------------------+
|                                                                      |
|  +---------------------------------+   +--------------------------+  |
|  |     landingPageBuilder Module   |   |    Block Templates       |  |
|  |  +---------------------------+  |   |  +--------------------+  |  |
|  |  | actions.class.php         |  |   |  | _block_hero_banner |  |  |
|  |  | - CRUD operations         |  |   |  | _block_search_box  |  |  |
|  |  | - AJAX endpoints          |  |   |  | _block_statistics  |  |  |
|  |  | - Version management      |  |   |  | _block_browse_*    |  |  |
|  |  +---------------------------+  |   |  | _block_row_2_col   |  |  |
|  +---------------------------------+   |  | (20+ templates)    |  |  |
|                    |                   |  +--------------------+  |  |
|                    v                   +--------------------------+  |
|  +---------------------------------+                                 |
|  |       LandingPageService        |                                 |
|  |  +---------------------------+  |                                 |
|  |  | Page management           |  |                                 |
|  |  | Block management          |  |                                 |
|  |  | Version control           |  |                                 |
|  |  | Dynamic data enrichment   |  |                                 |
|  |  +---------------------------+  |                                 |
|  +---------------------------------+                                 |
|                    |                                                 |
|                    v                                                 |
|  +---------------------------------+                                 |
|  |     LandingPageRepository       |                                 |
|  |  +---------------------------+  |                                 |
|  |  | Database operations       |  |                                 |
|  |  | Query building            |  |                                 |
|  |  | Data retrieval            |  |                                 |
|  |  +---------------------------+  |                                 |
|  +---------------------------------+                                 |
|                    |                                                 |
|                    v                                                 |
|  +-------------------------------------------------------------+    |
|  |                    Database Tables                           |    |
|  |  atom_landing_page | atom_landing_page_block                 |    |
|  |  atom_landing_page_block_type | atom_landing_page_version    |    |
|  +-------------------------------------------------------------+    |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+-----------------------------------+
|        atom_landing_page          |
+-----------------------------------+
| PK id BIGINT UNSIGNED             |
|    name VARCHAR(255)              |
|    slug VARCHAR(255) UNIQUE       |
|    description TEXT               |
|    is_default TINYINT(1)          |
|    is_active TINYINT(1)           |
| FK user_id INT                    |
|    created_at TIMESTAMP           |
|    updated_at TIMESTAMP           |
+-----------------------------------+
         |
         | 1:N
         v
+-----------------------------------+
|     atom_landing_page_block       |
+-----------------------------------+
| PK id BIGINT UNSIGNED             |
| FK page_id BIGINT UNSIGNED        |
| FK block_type_id INT              |
| FK parent_block_id BIGINT NULL    |
|    column_slot VARCHAR(20) NULL   |
|    position INT                   |
|    title VARCHAR(255)             |
|    config JSON                    |
|    css_classes VARCHAR(500)       |
|    container_type VARCHAR(50)     |
|    background_color VARCHAR(20)   |
|    text_color VARCHAR(20)         |
|    padding_top TINYINT            |
|    padding_bottom TINYINT         |
|    col_span TINYINT DEFAULT 12    |
|    is_visible TINYINT(1)          |
|    created_at TIMESTAMP           |
|    updated_at TIMESTAMP           |
+-----------------------------------+
         |
         | N:1
         v
+-----------------------------------+
|   atom_landing_page_block_type    |
+-----------------------------------+
| PK id INT UNSIGNED                |
|    machine_name VARCHAR(100)      |
|    label VARCHAR(255)             |
|    icon VARCHAR(100)              |
|    category VARCHAR(50)           |
|    config_schema JSON             |
|    default_config JSON            |
|    template_file VARCHAR(255)     |
|    is_container TINYINT(1)        |
|    max_children INT               |
+-----------------------------------+

+-----------------------------------+
|    atom_landing_page_version      |
+-----------------------------------+
| PK id BIGINT UNSIGNED             |
| FK page_id BIGINT UNSIGNED        |
|    version_number INT             |
|    status VARCHAR(20)             |
|    snapshot JSON                  |
|    notes TEXT                     |
| FK user_id INT                    |
|    created_at TIMESTAMP           |
+-----------------------------------+

+-----------------------------------+
|     atom_landing_page_audit       |
+-----------------------------------+
| PK id BIGINT UNSIGNED             |
| FK page_id BIGINT UNSIGNED        |
| FK block_id BIGINT NULL           |
|    action VARCHAR(50)             |
|    details JSON                   |
| FK user_id INT                    |
|    created_at TIMESTAMP           |
+-----------------------------------+
```

### SQL Schema

```sql
CREATE TABLE atom_landing_page (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_slug (slug),
    INDEX idx_is_default (is_default),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE atom_landing_page_block_type (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    icon VARCHAR(100) DEFAULT 'bi-square',
    category VARCHAR(50) DEFAULT 'content',
    config_schema JSON,
    default_config JSON,
    template_file VARCHAR(255),
    is_container TINYINT(1) DEFAULT 0,
    max_children INT DEFAULT 0,

    INDEX idx_machine_name (machine_name),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE atom_landing_page_block (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id BIGINT UNSIGNED NOT NULL,
    block_type_id INT UNSIGNED NOT NULL,
    parent_block_id BIGINT UNSIGNED NULL,
    column_slot VARCHAR(20) NULL,
    position INT DEFAULT 0,
    title VARCHAR(255),
    config JSON,
    css_classes VARCHAR(500),
    container_type VARCHAR(50) DEFAULT 'container',
    background_color VARCHAR(20) DEFAULT '#ffffff',
    text_color VARCHAR(20) DEFAULT '#212529',
    padding_top TINYINT DEFAULT 3,
    padding_bottom TINYINT DEFAULT 3,
    col_span TINYINT DEFAULT 12,
    is_visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (page_id) REFERENCES atom_landing_page(id) ON DELETE CASCADE,
    FOREIGN KEY (block_type_id) REFERENCES atom_landing_page_block_type(id),
    FOREIGN KEY (parent_block_id) REFERENCES atom_landing_page_block(id) ON DELETE CASCADE,

    INDEX idx_page_id (page_id),
    INDEX idx_parent_block (parent_block_id),
    INDEX idx_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE atom_landing_page_version (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id BIGINT UNSIGNED NOT NULL,
    version_number INT NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    snapshot JSON NOT NULL,
    notes TEXT,
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (page_id) REFERENCES atom_landing_page(id) ON DELETE CASCADE,
    INDEX idx_page_version (page_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE atom_landing_page_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id BIGINT UNSIGNED NOT NULL,
    block_id BIGINT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    details JSON,
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_page_id (page_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Block Types

| Machine Name | Category | Description | Container |
|-------------|----------|-------------|-----------|
| header_section | layout | Page header with logo and nav | No |
| footer_section | layout | Page footer with columns | No |
| row_1_col | layout | Single column container | Yes |
| row_2_col | layout | Two column layout | Yes |
| row_3_col | layout | Three column layout | Yes |
| divider | layout | Horizontal separator | No |
| spacer | layout | Vertical spacing | No |
| hero_banner | content | Large banner with image | No |
| text_content | content | Rich text with image | No |
| image_carousel | content | IIIF collection slideshow | No |
| search_box | data | Archive search field | No |
| browse_panels | data | Category links with counts | No |
| recent_items | data | Latest records | No |
| featured_items | data | Curated IIIF items | No |
| statistics | data | Entity count display | No |
| holdings_list | data | Top-level holdings | No |
| quick_links | other | Button/link collection | No |
| repository_spotlight | other | Featured repository | No |
| map_block | other | Interactive map | No |
| copyright_bar | other | Copyright notice | No |

---

## Routes

### Admin Routes

| Route Name | URL Pattern | Action |
|------------|-------------|--------|
| landing_page_list | /admin/landing-pages | list |
| landing_page_create | /admin/landing-pages/create | create |
| landing_page_edit | /admin/landing-pages/:id/edit | edit |
| landing_page_preview | /admin/landing-pages/:id/preview | preview |

### AJAX Endpoints

| Route Name | URL Pattern | Action |
|------------|-------------|--------|
| landing_page_ajax_add_block | /admin/landing-pages/ajax/add-block | addBlock |
| landing_page_ajax_update_block | /admin/landing-pages/ajax/update-block | updateBlock |
| landing_page_ajax_delete_block | /admin/landing-pages/ajax/delete-block | deleteBlock |
| landing_page_ajax_duplicate_block | /admin/landing-pages/ajax/duplicate-block | duplicateBlock |
| landing_page_ajax_reorder | /admin/landing-pages/ajax/reorder | reorderBlocks |
| landing_page_ajax_toggle_visibility | /admin/landing-pages/ajax/toggle-visibility | toggleVisibility |
| landing_page_ajax_get_config | /admin/landing-pages/ajax/get-config | getBlockConfig |
| landing_page_ajax_update_settings | /admin/landing-pages/ajax/update-settings | updateSettings |
| landing_page_ajax_delete | /admin/landing-pages/ajax/delete | delete |
| landing_page_ajax_save_draft | /admin/landing-pages/ajax/save-draft | saveDraft |
| landing_page_ajax_publish | /admin/landing-pages/ajax/publish | publish |
| landing_page_ajax_restore_version | /admin/landing-pages/ajax/restore-version | restoreVersion |
| landing_page_ajax_move_to_column | /admin/landing-pages/ajax/move-to-column | moveToColumn |

### Public Routes

| Route Name | URL Pattern | Action |
|------------|-------------|--------|
| landing_page_view | /landing/:slug | index |

---

## Service Methods

### LandingPageService

```php
namespace AhgLandingPage\Services;

class LandingPageService
{
    // Page Management
    public function getLandingPageForDisplay(?string $slug = null): ?array
    public function getPageForEditor(int $pageId): ?array
    public function createPage(array $data, ?int $userId = null): array
    public function updatePage(int $pageId, array $data, ?int $userId = null): array
    public function deletePage(int $pageId, ?int $userId = null): array

    // Block Management
    public function addBlock(int $pageId, int $blockTypeId, ?array $config = null,
                            ?int $userId = null, array $options = []): array
    public function updateBlock(int $blockId, array $data, ?int $userId = null): array
    public function deleteBlock(int $blockId, ?int $userId = null): array
    public function reorderBlocks(int $pageId, array $blockOrder, ?int $userId = null): array
    public function duplicateBlock(int $blockId, ?int $userId = null): array
    public function toggleBlockVisibility(int $blockId, ?int $userId = null): array

    // Version Management
    public function saveDraft(int $pageId, ?int $userId = null, ?string $notes = null): array
    public function publish(int $pageId, ?int $userId = null): array
    public function restoreVersion(int $versionId, ?int $userId = null): array

    // Data Enrichment
    protected function enrichBlockData(object $block): object
    protected function getStatisticsData(array $config): array
    protected function getRecentItemsData(array $config): array
    protected function getBrowsePanelsData(array $config): array
    protected function getHoldingsData(array $config): array
    protected function getFeaturedItemsData(array $config): array
    protected function getRepositorySpotlightData(array $config): ?array
    protected function getMapData(array $config): array

    // Utilities
    public function getBlockTypes(): Collection
    public function getAllPages(bool $activeOnly = false): Collection
}
```

---

## Block Configuration Schemas

### Hero Banner

```json
{
    "title": "string",
    "subtitle": "string",
    "background_image": "string (URL)",
    "height": "string (CSS value)",
    "text_align": "left|center|right",
    "overlay_opacity": "number (0-1)",
    "title_size": "string (CSS value)",
    "subtitle_size": "string (CSS value)",
    "show_button": "boolean",
    "cta_text": "string",
    "cta_url": "string"
}
```

### Browse Panels

```json
{
    "title": "string",
    "style": "list|cards",
    "columns": "number (1-6)",
    "show_counts": "boolean",
    "panels": [
        {
            "label": "string",
            "icon": "string (Bootstrap icon)",
            "url": "string",
            "count_entity": "informationobject|repository|actor|digitalobject|accession|term_subjects|term_places"
        }
    ]
}
```

### Statistics

```json
{
    "title": "string",
    "layout": "horizontal|vertical",
    "animate_numbers": "boolean",
    "stats": [
        {
            "label": "string",
            "icon": "string (Bootstrap icon)",
            "entity": "informationobject|repository|actor|digitalobject|accession|function|term_subjects|term_places"
        }
    ]
}
```

### Recent Items

```json
{
    "title": "string",
    "entity_type": "informationobject|repository",
    "limit": "number (1-20)",
    "layout": "grid|list",
    "columns": "number (1-6)",
    "show_date": "boolean",
    "show_thumbnail": "boolean"
}
```

### Search Box

```json
{
    "placeholder": "string",
    "show_advanced": "boolean",
    "style": "default|large|minimal"
}
```

### Text Content

```json
{
    "title": "string",
    "content": "string (HTML)",
    "image": "string (URL)",
    "image_position": "none|left|right|top|bottom",
    "image_width": "string (percentage)"
}
```

### Quick Links

```json
{
    "title": "string",
    "layout": "inline|grid|list",
    "style": "buttons|links|cards",
    "links": [
        {
            "label": "string",
            "url": "string",
            "icon": "string (Bootstrap icon)",
            "new_window": "boolean"
        }
    ]
}
```

### Holdings List

```json
{
    "title": "string",
    "limit": "number (1-50)",
    "sort": "title|hits",
    "repository_id": "number (optional)",
    "show_level": "boolean",
    "show_dates": "boolean",
    "show_extent": "boolean",
    "show_hits": "boolean"
}
```

### Map Block

```json
{
    "title": "string",
    "height": "string (CSS value)",
    "zoom": "number (1-20)",
    "show_all_repositories": "boolean",
    "repository_ids": ["number array"]
}
```

### Column Layouts (2-col, 3-col)

```json
{
    "gap": "string (CSS value)",
    "stack_mobile": "boolean",
    "col1_width": "string (percentage)",
    "col2_width": "string (percentage)",
    "col3_width": "string (percentage, 3-col only)"
}
```

---

## Dynamic Data Sources

### Entity Count Mapping

| Entity | Database Query |
|--------|---------------|
| informationobject | `information_object WHERE parent_id IS NULL` |
| repository | `repository` |
| actor | `actor` |
| digitalobject | `digital_object` |
| accession | `accession` |
| function | `function_object` |
| term_subjects | `term WHERE taxonomy_id = 35` |
| term_places | `term WHERE taxonomy_id = 42` |

### Recent Items Query

```sql
SELECT io.id, i18n.title, slug.slug, obj.created_at, do.id as has_digital_object
FROM information_object io
JOIN object obj ON io.id = obj.id
JOIN information_object_i18n i18n ON io.id = i18n.id AND i18n.culture = ?
LEFT JOIN digital_object do ON io.id = do.object_id
LEFT JOIN slug ON io.id = slug.object_id
ORDER BY obj.created_at DESC
LIMIT ?
```

### Popular This Week Query

```sql
SELECT object_id, COUNT(object_id) as hits
FROM access_log
WHERE access_date BETWEEN DATE_SUB(NOW(), INTERVAL 1 WEEK) AND NOW()
GROUP BY object_id
ORDER BY hits DESC
LIMIT ?
```

---

## JavaScript Components

### landing-page-builder.js

Main builder functionality:
- Drag and drop handling (Sortable.js)
- Block CRUD operations
- Configuration forms
- Preview updates
- Auto-save

### landing-page-builder-columns.js

Column layout management:
- Nested block handling
- Column drop zones
- Block movement between columns

### Key JavaScript Functions

```javascript
// Initialize builder
window.LandingPageBuilder.init();

// Add block programmatically
LandingPageBuilder.addBlock(blockTypeId, config, position);

// Update block configuration
LandingPageBuilder.updateBlock(blockId, config);

// Reorder blocks
LandingPageBuilder.saveOrder();

// Load block config form
LandingPageBuilder.showConfigPanel(blockId);

// Save and publish
LandingPageBuilder.saveDraft();
LandingPageBuilder.publish();
```

---

## Template Files

### Module Templates

| File | Purpose |
|------|---------|
| listSuccess.php | Page list admin view |
| createSuccess.php | New page form |
| editSuccess.php | Visual builder interface |
| indexSuccess.php | Public page display |
| _blockCard.php | Block card in editor |
| _nestedBlock.php | Nested block display |

### Block Templates

Located in `modules/landingPageBuilder/templates/blocks/`:

| File | Block Type |
|------|------------|
| _block_hero_banner.php | Hero banner |
| _block_search_box.php | Search field |
| _block_browse_panels.php | Browse links |
| _block_statistics.php | Stats counters |
| _block_recent_items.php | Recent records |
| _block_featured_items.php | IIIF carousel |
| _block_text_content.php | Text with image |
| _block_quick_links.php | Link buttons |
| _block_holdings_list.php | Holdings list |
| _block_repository_spotlight.php | Repository feature |
| _block_map.php | Leaflet map |
| _block_header_section.php | Page header |
| _block_footer_section.php | Page footer |
| _block_row_2_col.php | 2-column layout |
| _block_row_3_col.php | 3-column layout |
| _block_divider.php | Separator line |
| _block_spacer.php | Vertical space |
| _block_image_carousel.php | Image slideshow |
| _block_copyright_bar.php | Copyright notice |

---

## Permissions

| Action | Required Role |
|--------|--------------|
| View public pages | None (public) |
| List all pages | Administrator |
| Create/Edit pages | Administrator |
| Delete pages | Administrator |
| Publish pages | Administrator |

---

## Configuration

### Plugin Settings

| Setting | Default | Description |
|---------|---------|-------------|
| landing_page_enabled | true | Enable landing pages |
| default_container_type | container | Default block container |
| max_blocks_per_page | 50 | Maximum blocks allowed |
| auto_save_interval | 30000 | Auto-save interval (ms) |
| enable_versioning | true | Enable version control |
| max_versions | 10 | Maximum versions to keep |

---

## Dependencies

### PHP Dependencies

- Laravel Illuminate/Database (Query Builder)
- atom-framework services

### JavaScript Dependencies

- Sortable.js (drag and drop)
- Bootstrap 5 (UI components)
- Leaflet.js (map block)

### CSS Dependencies

- Bootstrap 5
- Bootstrap Icons
- Leaflet CSS (for map)

---

## Installation

### Database Setup

```bash
php bin/atom extension:enable ahgLandingPagePlugin
php bin/atom landing-page:install
```

### Manual Installation

```sql
-- Run install.sql from plugin database/ directory
SOURCE /path/to/ahgLandingPagePlugin/database/install.sql;

-- Insert default block types
SOURCE /path/to/ahgLandingPagePlugin/database/block_types.sql;
```

---

## API Response Formats

### Success Response

```json
{
    "success": true,
    "page_id": 1,
    "block_id": 15,
    "version_id": 3
}
```

### Error Response

```json
{
    "success": false,
    "error": "Error message description"
}
```

### Block Data Response

```json
{
    "success": true,
    "block": {
        "id": 15,
        "type_label": "Hero Banner",
        "type_icon": "bi-image",
        "machine_name": "hero_banner",
        "config": {},
        "config_schema": {},
        "is_visible": 1,
        "position": 0
    }
}
```

---

## Audit Actions

| Action | Description |
|--------|-------------|
| page_created | New page created |
| page_updated | Page settings changed |
| page_deleted | Page removed |
| page_published | Page made public |
| block_added | Block added to page |
| block_updated | Block configuration changed |
| block_deleted | Block removed |
| block_duplicated | Block copied |
| block_visibility_toggled | Block shown/hidden |
| blocks_reordered | Block order changed |
| version_restored | Previous version restored |

---

## Performance Considerations

### Caching

- Block type definitions are cached
- Dynamic data (stats, recent items) should use short cache TTL
- Page structure can be cached longer

### Query Optimization

- Use eager loading for block relationships
- Limit dynamic data queries (max 20 items)
- Index frequently queried columns

### Best Practices

- Avoid excessive nested columns
- Limit blocks per page (recommended: 20-30)
- Use specific entity types for statistics
- Optimize images before upload

---

*Part of the AtoM AHG Framework*
