# ahgThemeB5Plugin - Technical Documentation

**Version:** 1.14.21
**Category:** Theme
**Dependencies:** atom-framework, ahgDisplayPlugin

---

## Overview

The ahgThemeB5Plugin is a modern Bootstrap 5 theme for Access to Memory (AtoM), providing a contemporary UI framework that replaces the default arDominionB5Plugin theme. It features CSS custom properties for dynamic theming, an enhanced media player, multiple display modes, and a landing page builder.

---

## Architecture

```
+---------------------------------------------------------------------------+
|                        ahgThemeB5Plugin                                    |
+---------------------------------------------------------------------------+
|                                                                           |
|  +-------------------------------------------------------------------+   |
|  |                    arDominionB5Plugin (Base)                       |   |
|  |    * Bootstrap 5 core                                              |   |
|  |    * jQuery, Popper.js                                             |   |
|  |    * Font Awesome                                                  |   |
|  |    * Accessible-Slick carousel                                     |   |
|  +-------------------------------------------------------------------+   |
|                                  |                                         |
|                                  v                                         |
|  +-------------------------------------------------------------------+   |
|  |                      AHG Theme Layer                               |   |
|  |    * CSS Custom Properties                                         |   |
|  |    * AHG Color Scheme                                              |   |
|  |    * Enhanced Media Player                                         |   |
|  |    * Display Mode Switching                                        |   |
|  |    * Landing Page Builder                                          |   |
|  +-------------------------------------------------------------------+   |
|                                  |                                         |
|                                  v                                         |
|  +-------------------------------------------------------------------+   |
|  |                   Template Overrides (40+ modules)                 |   |
|  |    * informationobject, actor, repository                          |   |
|  |    * digitalobject, search, browse                                 |   |
|  |    * sfIsadPlugin, sfRadPlugin, sfDcPlugin                         |   |
|  +-------------------------------------------------------------------+   |
|                                                                           |
+---------------------------------------------------------------------------+
```

---

## File Structure

```
ahgThemeB5Plugin/
+-- config/
|   +-- app.yml                      # Theme feature flags
|   +-- settings.yml                 # Enabled modules list
|   +-- metadata.yml                 # Metadata extraction config
|   +-- display_mode_assets.yml      # Display mode CSS/JS assets
|
+-- database/
|   +-- install.sql                  # Default settings data
|
+-- modules/                         # Template overrides (40+ modules)
|   +-- informationobject/
|   +-- digitalobject/
|   +-- search/
|   +-- actor/
|   +-- repository/
|   +-- user/
|   +-- clipboard/
|   +-- browse/
|   +-- sfIsadPlugin/
|   +-- sfRadPlugin/
|   +-- sfDcPlugin/
|   +-- sfIsaarPlugin/
|   +-- sfIsdfPlugin/
|   +-- ... (more modules)
|
+-- scss/
|   +-- main.scss                    # Main SCSS entry (imports arDominionB5Plugin)
|   +-- _variables.scss              # Bootstrap/theme variables
|   +-- _layout.scss                 # Layout overrides
|   +-- _typography.scss             # Typography styles
|   +-- _forms.scss                  # Form element styles
|   +-- _buttonsandlinks.scss        # Button and link styles
|   +-- _treeview.scss               # Hierarchy tree view
|   +-- _fullwidthtreeview.scss      # Full-width tree view
|   +-- _carousel.scss               # Carousel styles
|   +-- _utilities.scss              # Utility classes
|
+-- web/
|   +-- css/
|   |   +-- display-modes.css        # Display mode styles
|   |   +-- ahg-settings.css         # Settings panel styles
|   |   +-- access-badges.css        # Access level badges
|   |   +-- donor-dashboard.css      # Donor dashboard styles
|   |   +-- landing-page-builder.css # Page builder styles
|   |   +-- leaflet.min.css          # Map styles
|   |
|   +-- js/
|   |   +-- main.js                  # Main JS entry point
|   |   +-- display-mode.js          # Display mode switching
|   |   +-- ahg-media-player.js      # Enhanced media player
|   |   +-- landing-page-builder.js  # Drag-drop page builder
|   |   +-- landing-page-columns.js  # Column layout support
|   |   +-- ahg-helpers.js           # Utility functions
|   |   +-- plugin-filter.js         # Plugin filtering
|   |   +-- searchable-record-select.js  # Record picker
|   |   +-- getty-autocomplete.js    # Getty vocabulary lookup
|   |   +-- standardSwitcher.js      # Descriptive standard switch
|   |   +-- cytoscape.min.js         # Network visualization
|   |   +-- leaflet.min.js           # Mapping library
|   |   +-- 3d-force-graph.min.js    # 3D visualization
|   |
|   +-- images/
|   |   +-- logo.png                 # Theme logo
|   |   +-- homepage-bg.jpg          # Background image
|   |   +-- no-cover.svg             # Placeholder image
|   |
|   +-- dist/                        # Webpack compiled assets
|       +-- js/
|       |   +-- ahgThemeB5Plugin.bundle.*.js
|       |   +-- vendor.bundle.*.js
|       +-- css/
|       |   +-- ahgThemeB5Plugin.bundle.*.css
|       +-- icons/                   # Compiled SVG icons
|       +-- webfonts/                # Font files
|
+-- webpack.entry.js                 # Webpack entry point
+-- extension.json                   # Plugin metadata
+-- README.md                        # Plugin readme
```

---

## Build System

### Webpack Configuration

The theme uses Webpack via arDominionB5Plugin's build system.

**Entry Point:** `webpack.entry.js`
```javascript
import "./js/main";
import "./scss/main.scss";
```

**Build Commands:**
```bash
cd /usr/share/nginx/archive/plugins/arDominionB5Plugin
npm install
npm run build
```

**Output Files:**
| File | Size | Description |
|------|------|-------------|
| `ahgThemeB5Plugin.bundle.*.js` | ~329KB | Theme JavaScript |
| `vendor.bundle.*.js` | ~916KB | Third-party libraries |
| `ahgThemeB5Plugin.bundle.*.css` | ~469KB | Compiled CSS |

---

## CSS Architecture

### CSS Custom Properties

The theme uses CSS custom properties for dynamic theming, allowing runtime color changes without rebuild.

```scss
// Default values in main.scss
:root {
  --ahg-primary-color: #005837;
  --ahg-primary-dark: #003d25;
  --ahg-accent-color: #37A07F;
  --ahg-accent-dark: #2a7d63;
  --ahg-background-light: #F3EFD4;
  --ahg-background-white: #FFFFFF;
  --ahg-text-primary: #58585B;
  --ahg-text-light: #939597;
  --ahg-text-white: #FFFFFF;
  --ahg-border-color: #99c6b3;

  // Extended theming
  --ahg-card-header-bg: #005837;
  --ahg-card-header-text: #FFFFFF;
  --ahg-button-bg: #005837;
  --ahg-button-text: #FFFFFF;
  --ahg-link-color: #005837;
  --ahg-sidebar-bg: #f8f9fa;
  --ahg-sidebar-text: #333333;
}
```

### SCSS Partials

| File | Purpose |
|------|---------|
| `_variables.scss` | Bootstrap variable overrides |
| `_layout.scss` | Page layout, sidebar, content areas |
| `_typography.scss` | Headings, body text, links |
| `_forms.scss` | Form inputs, date pickers |
| `_buttonsandlinks.scss` | Button variants, link styles |
| `_treeview.scss` | Hierarchy navigation tree |
| `_fullwidthtreeview.scss` | Full-width tree display |
| `_carousel.scss` | Image carousel customization |
| `_utilities.scss` | Helper classes |

### Bootstrap Variable Overrides

```scss
// Color palette
$primary: #f60 !default;
$secondary: $gray-700 !default;
$body-bg: $gray-100 !default;
$link-color: #0a58ca !default;

// Typography
$font-weight-bold: 700 !default;
$headings-font-weight: 400 !default;
$h1-font-size: 2rem !default;

// Components
$dropdown-min-width: 15rem !default;
$breadcrumb-divider: quote(">>") !default;
$accordion-padding-x: 1rem !default;
```

---

## JavaScript Modules

### Main Entry (`main.js`)

Imports from arDominionB5Plugin while excluding MediaElement player:

```javascript
// Base imports from arDominionB5Plugin
import "../../arDominionB5Plugin/js/qubit";
import "../../arDominionB5Plugin/js/treeView";
import "../../arDominionB5Plugin/js/clipboard";
import "../../arDominionB5Plugin/js/advancedSearch";
// ... additional imports

// SKIP mediaelement - using native HTML5 player
// import "../../arDominionB5Plugin/js/mediaelement";
```

### Display Mode Module (`display-mode.js`)

Handles switching between display layouts with localStorage caching and server sync.

```javascript
const DisplayMode = {
    init(options),           // Initialize with module detection
    switchMode(mode),        // Apply mode visually
    getCurrentMode(module),  // Get current mode
    setPreference(module, mode),  // Save to localStorage
    getPreference(module),   // Load from localStorage
    resetToDefault(module)   // Reset to global default
};

// Supported modes
const modes = ['tree', 'grid', 'gallery', 'list', 'timeline'];
```

### Enhanced Media Player (`ahg-media-player.js`)

Native HTML5 media player with streaming fallback for unsupported formats.

```javascript
class AhgMediaPlayer {
    constructor(containerId, options) {
        this.options = {
            mediaUrl: '',
            streamUrl: '',
            mediaType: 'video',
            digitalObjectId: 0,
            mimeType: '',
            autoplay: false,
            controls: true,
            onReady: null,
            onError: null
        };
    }

    // Automatic transcoding for unsupported formats
    needsTranscoding(mimeType) {
        const transcodingMimes = [
            'video/x-ms-asf', 'video/x-msvideo', 'video/quicktime',
            'video/x-ms-wmv', 'video/x-flv', 'video/x-matroska',
            'audio/aiff', 'audio/x-aiff', 'audio/flac'
        ];
        return transcodingMimes.includes(mimeType);
    }

    switchToStreaming() {
        // Falls back to /media/stream/{id} for transcoded playback
    }
}
```

### Landing Page Builder (`landing-page-builder.js`)

Drag-and-drop page builder using Sortable.js.

```javascript
const Builder = {
    init(),                  // Initialize builder
    addBlock(blockTypeId),   // Add new block
    deleteBlock(blockId),    // Remove block
    duplicateBlock(blockId), // Copy block
    saveOrder(),             // Persist block order
    openConfig(blockId),     // Open config panel
    saveBlockConfig(formData), // Save block settings
    publish(),               // Publish page
    restoreVersion(versionId)  // Restore version
};
```

---

## Display Modes

### Available Modes

| Mode | Description | CSS Class |
|------|-------------|-----------|
| list | Traditional list view | `display-list-view` |
| grid | Card grid (4 columns) | `display-grid-view` |
| gallery | Large image gallery | `display-gallery-view` |
| tree | Hierarchical tree | `display-tree-view` |
| timeline | Chronological view | `display-timeline-view` |

### Mode Switching

```html
<div class="display-mode-toggle" data-module="informationobject" data-ajax="true">
    <button data-mode="list" class="btn btn-sm active">
        <i class="fas fa-list"></i>
    </button>
    <button data-mode="grid" class="btn btn-sm">
        <i class="fas fa-th"></i>
    </button>
    <button data-mode="gallery" class="btn btn-sm">
        <i class="fas fa-images"></i>
    </button>
</div>
```

### Masonry Gallery Layout

```css
.display-gallery-view.masonry-layout {
    column-count: 1;
    max-width: 1200px;
    margin: 0 auto;
}

.display-gallery-view.masonry-layout .masonry-item img {
    width: 100%;
    max-height: 85vh;
    object-fit: contain;
    background: #1a1a1a;
}
```

---

## Template Overrides

### Module Count

The theme overrides templates in 40+ modules:

| Category | Modules |
|----------|---------|
| Core | informationobject, digitalobject, actor, repository |
| Search | search, browse, clipboard |
| Descriptive Standards | sfIsadPlugin, sfRadPlugin, sfDcPlugin, sfIsaarPlugin |
| Administration | admin, user, jobs, taxonomy |
| Specialized | physicalobject, accession, deaccession |

### Template Override Examples

**Digital Object Video Player:**
```php
// modules/digitalobject/templates/_showVideo.php
<div class="ahg-media-player" data-do-id="<?php echo $resource->id ?>">
    <video controls preload="metadata" class="w-100">
        <source src="<?php echo $mediaUrl ?>" type="<?php echo $mimeType ?>">
    </video>
    <div id="player-status-<?php echo $resource->id ?>" class="mt-2"></div>
</div>
```

**Search Results with Display Mode:**
```php
// modules/search/templates/_searchResults.php
<div class="search-results <?php echo $displayModeClass ?>"
     data-display-container>
    <?php foreach ($pager->getResults() as $hit): ?>
        <?php include_partial('search/searchResult', ['hit' => $hit]) ?>
    <?php endforeach ?>
</div>
```

---

## Configuration

### app.yml

```yaml
all:
  b5_theme: true

  enhanced_media_player:
    enabled: true
    allow_snippets: true
    allow_transcription: true
```

### settings.yml

```yaml
all:
  .settings:
    enabled_modules:
      - informationobject
      - accession
      - actor
      - admin
      - browse
      - clipboard
      - digitalobject
      - repository
      - search
      - user
      # ... additional modules
```

### Default Settings (install.sql)

```sql
INSERT IGNORE INTO ahg_settings
(setting_key, setting_value, setting_type, setting_group) VALUES
('default_sector', 'archive', 'string', 'general'),
('enable_glam_browse', '1', 'boolean', 'general'),
('enable_3d_viewer', '1', 'boolean', 'features'),
('enable_iiif', '1', 'boolean', 'features'),
('research_booking_enabled', '1', 'boolean', 'features'),
('audit_retention_days', '365', 'integer', 'compliance');
```

---

## Third-Party Libraries

| Library | Version | Purpose |
|---------|---------|---------|
| Bootstrap | 5.x | CSS framework |
| jQuery | 3.x | DOM manipulation |
| Font Awesome | 6.x | Icons |
| Sortable.js | 1.15.x | Drag-and-drop |
| Cytoscape.js | - | Network visualization |
| Leaflet | - | Maps |
| 3d-force-graph | - | 3D visualization |
| Accessible-Slick | - | Carousel |

---

## Customization

### Color Scheme Override

Override CSS custom properties in a custom stylesheet:

```css
:root {
  --ahg-primary-color: #1a5276;
  --ahg-primary-dark: #154360;
  --ahg-accent-color: #2980b9;
  --ahg-card-header-bg: #1a5276;
}
```

### Adding Custom SCSS

Create overrides in `scss/main.scss` after the import:

```scss
@import "../../../plugins/arDominionB5Plugin/scss/main.scss";

// Custom styles
.custom-component {
    background: var(--ahg-primary-color);
    color: var(--ahg-text-white);
}
```

### Template Override

Copy templates from arDominionB5Plugin or apps/qubit:

```bash
# Copy from arDominionB5Plugin
cp plugins/arDominionB5Plugin/modules/search/templates/_searchResult.php \
   plugins/ahgThemeB5Plugin/modules/search/templates/

# Modify the copied template
# Theme templates take precedence
```

---

## Integration Points

### With ahgDisplayPlugin

Required dependency for display mode management:

- ahgDisplayPlugin provides backend display mode storage
- Theme provides frontend switching UI
- Preferences sync between localStorage and database

### With ahgSettingsPlugin

Theme settings are managed via AHG Settings:

```php
// Get theme color from settings
$primaryColor = sfConfig::get('app_ahg_primary_color', '#005837');
```

### With Descriptive Standard Plugins

The theme provides custom templates for:

- ISAD(G) via sfIsadPlugin
- RAD via sfRadPlugin
- Dublin Core via sfDcPlugin
- ISAAR(CPF) via sfIsaarPlugin
- ISDF via sfIsdfPlugin
- MODS via sfModsPlugin

---

## Performance Considerations

### Asset Loading

- Webpack bundles minimize HTTP requests
- Vendor bundle cached separately from theme bundle
- Source maps available for debugging (development only)

### CSS Custom Properties

- Browser renders custom properties efficiently
- Changes apply without page reload
- Fallback values ensure compatibility

### Media Player

- Native HTML5 player for supported formats
- On-demand transcoding for legacy formats
- Streaming reduces initial load time

---

## Browser Support

| Browser | Minimum Version |
|---------|-----------------|
| Chrome | 80+ |
| Firefox | 75+ |
| Safari | 13+ |
| Edge | 80+ |

CSS custom properties require modern browsers. Legacy browsers may see fallback colors.

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Styles not updating | Clear cache: `php symfony cc` |
| Webpack build fails | Check node_modules in arDominionB5Plugin |
| Display mode not saving | Verify ahgDisplayPlugin is enabled |
| Media player error | Check browser console for MIME type issues |
| Custom properties not working | Ensure no CSS syntax errors in overrides |

---

*Part of the AtoM AHG Framework*
