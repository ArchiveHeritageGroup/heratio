# ahgLabelPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Tools
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Label generation system for GLAM institutions providing customizable labels with linear barcodes (Code 128) and QR codes. Automatically detects record sector (archive, library, museum, gallery) and provides sector-appropriate identifiers for barcode encoding.

---

## Architecture

```
+---------------------------------------------------------------------+
|                        ahgLabelPlugin                                |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                   Route Configuration                          |  |
|  |  /label/:slug -> label/index                                   |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                    labelActions                                |  |
|  |  - executeIndex() - Load resource and parameters               |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                  indexSuccess.php Template                     |  |
|  |  - Sector detection                                            |  |
|  |  - Barcode source aggregation                                  |  |
|  |  - Label preview rendering                                     |  |
|  |  - Print/download functionality                                |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |               External Barcode APIs                            |  |
|  |  - barcodeapi.org (Code 128)                                   |  |
|  |  - api.qrserver.com (QR codes)                                 |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## File Structure

```
ahgLabelPlugin/
|-- config/
|   +-- ahgLabelPluginConfiguration.class.php
|-- modules/
|   +-- label/
|       |-- actions/
|       |   +-- actions.class.php
|       +-- templates/
|           +-- indexSuccess.php
+-- extension.json
```

---

## Plugin Configuration

### ahgLabelPluginConfiguration.class.php

```php
class ahgLabelPluginConfiguration extends sfPluginConfiguration
{
    public static $summary = 'Label generation for archival objects';
    public static $version = '1.0.0';

    public function initialize()
    {
        // Enable label module
        $enabledModules = sfConfig::get('sf_enabled_modules', []);
        $enabledModules[] = 'label';
        sfConfig::set('sf_enabled_modules', $enabledModules);

        // Register routes
        $this->dispatcher->connect('routing.load_configuration', [$this, 'loadRoutes']);
    }

    public function loadRoutes(sfEvent $event)
    {
        $routing = $event->getSubject();
        $routing->prependRoute('label_index', new sfRoute('/label/:slug', [
            'module' => 'label',
            'action' => 'index',
        ]));
    }
}
```

---

## Route Definitions

| Route | URL Pattern | Controller | Action |
|-------|-------------|------------|--------|
| label_index | /label/:slug | label | index |

---

## Controller

### labelActions

```php
class labelActions extends sfActions
{
    public function executeIndex(sfWebRequest $request)
    {
        $slug = $request->getParameter('slug');
        $this->resource = QubitInformationObject::getBySlug($slug);

        if (!$this->resource) {
            $this->forward404();
        }

        $this->labelType = $request->getParameter('type', 'full');
        $this->labelSize = $request->getParameter('size', 'medium');
    }
}
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| slug | string | required | Record URL slug |
| type | string | 'full' | Label type (reserved for future templates) |
| size | string | 'medium' | Label size preset |

---

## Sector Detection

The template automatically detects the sector based on available metadata:

```php
// Detection logic
$sector = 'archive'; // Default

// Check display_object_config table
$sectorConfig = DB::table('display_object_config')
    ->where('object_id', $objectId)
    ->value('object_type');
if ($sectorConfig) $sector = $sectorConfig;

// Override based on available data
if (!empty($isbn)) $sector = 'library';
if (!empty($accession)) $sector = 'museum';
```

### Sector Labels

| Sector | Display Label |
|--------|---------------|
| library | Library Item |
| archive | Archival Record |
| museum | Museum Object |
| gallery | Gallery Artwork |

---

## Barcode Sources

### Data Aggregation

The system queries multiple tables to build available barcode sources:

```php
function safeQuery($table, $objectId, $column) {
    try {
        return DB::table($table)->where('information_object_id', $objectId)->value($column);
    } catch (\Exception $e) {
        return null;
    }
}
```

### Source Hierarchy

| Priority | Source | Table | Column | Sector |
|----------|--------|-------|--------|--------|
| 1 | ISBN | library_item | isbn | Library |
| 2 | ISSN | library_item | issn | Library |
| 3 | Barcode | library_item | barcode | Library |
| 4 | Accession | museum_object | accession_number | Museum |
| 5 | Identifier | information_object | identifier | All |
| 6 | Title | information_object_i18n | title | All |

### Complete Source List

| Key | Label | Source Table | Column |
|-----|-------|--------------|--------|
| identifier | Identifier | information_object | identifier |
| isbn | ISBN | library_item | isbn |
| issn | ISSN | library_item | issn |
| lccn | LCCN | library_item | lccn |
| openlibrary | OpenLibrary ID | library_item | openlibrary_id |
| barcode | Barcode | library_item | barcode |
| call_number | Call Number | library_item | call_number |
| accession | Accession Number | museum_object | accession_number |
| object_number | Object Number | museum_object | object_number |
| title | Title | information_object | title |

---

## External API Integration

### Linear Barcode Generation

Uses barcodeapi.org for Code 128 barcode generation:

```
https://barcodeapi.org/api/128/{encoded_value}
```

### QR Code Generation

Uses api.qrserver.com for QR code generation:

```
https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={encoded_url}
```

### QR Code Content

The QR code encodes the full URL to the record:

```php
$qrUrl = sfContext::getInstance()->getRequest()->getUriPrefix() . '/' . $resource->slug;
```

---

## Label Sizes

| Size | Width (px) | Approximate mm |
|------|------------|----------------|
| Small | 200 | 50mm |
| Medium | 300 | 75mm |
| Large | 400 | 100mm |

---

## Template Structure

### Layout

Uses `layout_3col` decorator with:
- Sidebar: Context menu
- Title slot: Record title
- Context-menu slot: Back link
- Main content: Configuration and preview

### Print Styles

```css
@media print {
    .no-print, #sidebar, #context-menu, nav, header, footer {
        display: none !important;
    }
    body { background: white !important; }
    .label-preview {
        width: fit-content;
        min-width: 200px;
        box-shadow: none !important;
        border: 1px solid #ccc !important;
    }
}
```

---

## JavaScript Functions

### Client-Side Functions

| Function | Description |
|----------|-------------|
| updateBarcodeSource() | Updates barcode image when source selection changes |
| updateLabelSize() | Adjusts label preview container width |
| toggleBarcode() | Shows/hides linear barcode section |
| toggleQR() | Shows/hides QR code section |
| toggleTitle() | Shows/hides title on label |
| toggleRepo() | Shows/hides repository name on label |
| downloadLabel() | Exports label as PNG using html2canvas |

### Download Implementation

```javascript
function downloadLabel() {
    if (typeof html2canvas !== 'undefined') {
        html2canvas(document.getElementById('labelContent')).then(function(canvas) {
            var link = document.createElement('a');
            link.download = 'label-{slug}.png';
            link.href = canvas.toDataURL();
            link.click();
        });
    } else {
        alert('Download requires html2canvas. Use Print instead.');
    }
}
```

---

## CSP Compliance

The template implements Content Security Policy nonce support:

```php
<?php
$n = sfConfig::get('csp_nonce', '');
echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : '';
?>
```

Applied to both `<style>` and `<script>` blocks.

---

## Database Tables Used (Read-Only)

| Table | Purpose |
|-------|---------|
| information_object | Base record data |
| library_item | Library-specific metadata |
| museum_object | Museum-specific metadata |
| display_object_config | Sector configuration |

---

## Dependencies

### Required

| Dependency | Purpose |
|------------|---------|
| atom-framework | Laravel Query Builder (Illuminate\Database) |
| ahgCorePlugin | Core AHG functionality |

### Optional (Client-Side)

| Library | Purpose |
|---------|---------|
| html2canvas | PNG download functionality |

---

## Extension Metadata

```json
{
    "name": "Label Generator",
    "machine_name": "ahgLabelPlugin",
    "version": "1.0.0",
    "description": "Label generation for archival objects with customizable templates",
    "author": "The Archive and Heritage Group",
    "license": "GPL-3.0",
    "requires": {
        "atom_framework": ">=1.0.0",
        "atom": ">=2.8",
        "php": ">=8.1"
    },
    "dependencies": ["ahgCorePlugin"],
    "category": "tools",
    "load_order": 40
}
```

---

## Usage Examples

### Basic Label Access

```
https://your-site.com/label/record-slug
```

### With Parameters

```
https://your-site.com/label/record-slug?type=full&size=large
```

---

## Future Enhancements

Potential roadmap items:

| Feature | Description |
|---------|-------------|
| Batch printing | Generate labels for multiple records |
| Custom templates | User-defined label layouts |
| Local barcode generation | Remove external API dependency |
| Label sheet formats | Support Avery and other sheet formats |
| Spine labels | Specialized library spine label format |
| Asset tags | QR-only compact labels |

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| No barcode displayed | External API blocked | Check firewall/proxy settings |
| Library fields missing | library_item table empty | Ensure ahgLibraryPlugin is installed |
| Museum fields missing | museum_object table empty | Ensure ahgMuseumPlugin is installed |
| Download fails | html2canvas not loaded | Use Print function instead |

### Debug Logging

The template includes debug logging for ISBN lookup:

```php
error_log("DEBUG ISBN for $objectId: " . var_export($isbn, true));
```

Check PHP error log for troubleshooting data retrieval issues.

---

## Security Considerations

- Read-only database access
- No user input stored to database
- External API calls use URL encoding
- CSP nonce support for inline scripts/styles
- HTML special characters escaped in output

---

*Part of the AtoM AHG Framework*
