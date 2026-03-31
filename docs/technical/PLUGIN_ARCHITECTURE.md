# AtoM AHG Plugin Architecture

**Version:** 2.8.2
**Last Updated:** February 2026

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              AtoM AHG STACK                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                        PRESENTATION LAYER                                   │ │
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────────┐ │ │
│  │  │ ahgThemeB5Plugin│  │ Templates (PHP) │  │ Assets (CSS/JS via Webpack) │ │ │
│  │  │ (Bootstrap 5)   │  │ Symfony 1.x     │  │ web/dist/                   │ │ │
│  │  └─────────────────┘  └─────────────────┘  └─────────────────────────────┘ │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                         MODULE LAYER                                        │ │
│  │                                                                             │ │
│  │  ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐  │ │
│  │  │ahgDisplayPlugin│ │ahgSettingsPlug│ │ahgSecurityPlug│ │ Sector Plugins │  │ │
│  │  │(informationobj,│ │(settings      │ │(accessFilter, │ │(museum,library,│  │ │
│  │  │ digitalobject) │ │ module)       │ │ securityClear)│ │ gallery, dam)  │  │ │
│  │  └───────────────┘ └───────────────┘ └───────────────┘ └───────────────┘  │ │
│  │                                                                             │ │
│  │  RULE: Each module has exactly ONE owner plugin for actions/*.php          │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                       EXTENSION SURFACE                                     │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────────┐  │ │
│  │  │  AhgHooks    │ │AhgCapabilities│ │  AhgPanels   │ │ AhgSectorProfile │  │ │
│  │  │  (events)    │ │  (features)   │ │  (UI slots)  │ │  (labels/vocab)  │  │ │
│  │  └──────────────┘ └──────────────┘ └──────────────┘ └──────────────────┘  │ │
│  │                         ahgCorePlugin/lib/                                  │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                        SERVICE LAYER                                        │ │
│  │  ┌────────────────────────────────────────────────────────────────────┐   │ │
│  │  │                      atom-framework                                 │   │ │
│  │  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌────────────┐ │   │ │
│  │  │  │ExtensionMgr  │ │MigrationHndlr│ │PluginFetcher │ │DataHandler │ │   │ │
│  │  │  └──────────────┘ └──────────────┘ └──────────────┘ └────────────┘ │   │ │
│  │  │                                                                     │   │ │
│  │  │  ┌──────────────────────────────────────────────────────────────┐  │   │ │
│  │  │  │          Laravel Query Builder (Illuminate\Database)         │  │   │ │
│  │  │  └──────────────────────────────────────────────────────────────┘  │   │ │
│  │  └────────────────────────────────────────────────────────────────────┘   │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                         DATA LAYER                                          │ │
│  │  ┌─────────────────────────┐    ┌─────────────────────────────────────┐   │ │
│  │  │   AtoM Core Tables      │    │       AHG Extension Tables          │   │ │
│  │  │   (Propel ORM)          │    │       (Laravel Query Builder)       │   │ │
│  │  │                         │    │                                     │   │ │
│  │  │   information_object    │    │   atom_plugin                       │   │ │
│  │  │   actor                 │    │   audit_log                         │   │ │
│  │  │   digital_object        │    │   privacy_breach                    │   │ │
│  │  │   term, taxonomy        │    │   condition_assessment              │   │ │
│  │  │   repository            │    │   heritage_asset                    │   │ │
│  │  │   user, acl_*           │    │   loan_*, research_*                │   │ │
│  │  └─────────────────────────┘    └─────────────────────────────────────┘   │ │
│  │                                                                             │ │
│  │                            MySQL 8 Database                                 │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_e6d7e227.png)
```

### Heratio Dual-Mode

The Heratio migration adds an alternative rendering path. When `.heratio_enabled` exists, AHG plugin routes are served by `heratio.php` (HTTP Kernel + Blade rendering) instead of Symfony. Base AtoM routes continue through `index.php` unchanged. See [HERATIO_MIGRATION.md](HERATIO_MIGRATION.md) for full details.

---

## 2. Plugin Types and Responsibilities

### 2.1 Plugin Categories

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           PLUGIN CLASSIFICATION                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  CORE (Required, Locked)                                                         │
│  ├── ahgThemeB5Plugin ────────── UI theme, templates, assets (NO actions)       │
│  └── ahgSecurityClearancePlugin ─ Security classification (owns: accessFilter)  │
│                                                                                  │
│  INFRASTRUCTURE                                                                  │
│  ├── ahgCorePlugin ──────────── Extension surface (hooks, capabilities, panels) │
│  ├── ahgSettingsPlugin ──────── Settings management (owns: settings module)     │
│  └── ahgDisplayPlugin ───────── Browse/display (owns: informationobject, etc.)  │
│                                                                                  │
│  SECTOR (Profile-only, no module actions)                                        │
│  ├── ahgMuseumPlugin ─────────── Museum profiles, CCO, Spectrum                 │
│  ├── ahgLibraryPlugin ────────── Library profiles, MARC, ISBN                   │
│  ├── ahgGalleryPlugin ────────── Gallery profiles, artwork metadata             │
│  └── ahgDAMPlugin ────────────── DAM profiles, media management                 │
│                                                                                  │
│  CAPABILITY (Feature plugins, integrate via hooks)                               │
│  ├── ahgIiifPlugin ──────────── IIIF manifests, viewers                         │
│  ├── ahgPrivacyPlugin ────────── POPIA/GDPR compliance                          │
│  ├── ahgConditionPlugin ──────── Condition assessment                           │
│  ├── ahgLoanPlugin ──────────── Loan management                                 │
│  ├── ahgAIPlugin ────────────── NER, translation, summarization                 │
│  └── ... (30+ capability plugins)                                               │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_b58a0be4.png)
```

### 2.2 Module Ownership Rules

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         MODULE OWNERSHIP MAP                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Module Name              │ Owner Plugin            │ Action Files               │
│  ─────────────────────────┼─────────────────────────┼──────────────────────────  │
│  informationobject        │ ahgDisplayPlugin        │ 10 actions (browse, cart,  │
│                           │                         │ favorites, rename, etc.)   │
│  digitalobject            │ ahgDisplayPlugin        │ 5 actions                  │
│  settings                 │ ahgSettingsPlugin       │ 42 actions                 │
│  accessFilter             │ ahgSecurityClearancePlugin │ security actions        │
│  securityClearance        │ ahgSecurityClearancePlugin │ clearance management    │
│  user                     │ ahgCorePlugin           │ password reset, etc.       │
│  api                      │ ahgAPIPlugin            │ REST endpoints             │
│  condition                │ ahgConditionPlugin      │ assessments                │
│  loan                     │ ahgLoanPlugin           │ loan workflows             │
│  ─────────────────────────┴─────────────────────────┴──────────────────────────  │
│                                                                                  │
│  RULE: For any module X, only ONE plugin may contain                            │
│        modules/X/actions/*.php files.                                           │
│                                                                                  │
│  Other plugins integrate via:                                                   │
│    • AhgHooks::register('record.view.panels', ...)                              │
│    • AhgPanels::register('informationobject', ...)                              │
│    • Templates in modules/X/templates/                                          │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_d8a0264e.png)
```

---

## 3. Extension Loading Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        PLUGIN LOADING SEQUENCE                                   │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  1. PHP Request                                                                  │
│     │                                                                            │
│     ▼                                                                            │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ config/ProjectConfiguration.class.php                                    │   │
│  │                                                                          │   │
│  │   setup() {                                                              │   │
│  │     $corePlugins = ['sfWebBrowserPlugin', 'sfThumbnailPlugin', ...];    │   │
│  │     $this->loadPluginsFromDatabase($corePlugins);  ◄─── Entry point     │   │
│  │   }                                                                      │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│     │                                                                            │
│     ▼                                                                            │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 2. Bootstrap atom-framework                                              │   │
│  │    require_once 'atom-framework/bootstrap.php';                          │   │
│  │                                                                          │   │
│  │    • Initialize Laravel Query Builder                                    │   │
│  │    • Connect to MySQL using PDO                                          │   │
│  │    • Register Illuminate\Database\Capsule\Manager                        │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│     │                                                                            │
│     ▼                                                                            │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 3. Query atom_plugin table                                               │   │
│  │                                                                          │   │
│  │    SELECT name FROM atom_plugin                                          │   │
│  │    WHERE is_enabled = 1                                                  │   │
│  │    ORDER BY load_order ASC;                                              │   │
│  │                                                                          │   │
│  │    Result: ['ahgThemeB5Plugin', 'ahgSecurityClearancePlugin', ...]       │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│     │                                                                            │
│     ▼                                                                            │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 4. Enable plugins via Symfony                                            │   │
│  │                                                                          │   │
│  │    foreach ($plugins as $plugin) {                                       │   │
│  │      $this->enablePlugins([$plugin]);                                    │   │
│  │    }                                                                     │   │
│  │                                                                          │   │
│  │    Symfony auto-discovers:                                               │   │
│  │      • modules/<module>/actions/*.php                                    │   │
│  │      • modules/<module>/templates/*.php                                  │   │
│  │      • config/<plugin>Configuration.class.php                            │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│     │                                                                            │
│     ▼                                                                            │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 5. Plugin initialization                                                 │   │
│  │                                                                          │   │
│  │    Each plugin's Configuration class initialize() is called:            │   │
│  │                                                                          │   │
│  │    • Register hooks via AhgHooks::register(...)                          │   │
│  │    • Register capabilities via AhgCapabilities::register(...)            │   │
│  │    • Register panels via AhgPanels::register(...)                        │   │
│  │    • Set sector profile via AhgSectorProfile::register(...)              │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│     │                                                                            │
│     ▼                                                                            │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 6. Request handled                                                       │   │
│  │                                                                          │   │
│  │    Symfony routes request to appropriate module/action                   │   │
│  │    Action can query extension data via Laravel Query Builder             │   │
│  │    Templates render with hook/panel integrations                         │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_1ea85dc2.png)
```

---

## 4. Extension Surface API

### 4.1 AhgHooks - Event System

```php
// Register a hook (in plugin Configuration)
AhgHooks::register('record.view.sidebar', function($record) {
    return ['html' => '<div>My Panel</div>'];
}, priority: 10);

// Trigger hooks (in action/template)
$panels = AhgHooks::trigger('record.view.sidebar', $record);

// Filter hooks (modify value through chain)
$title = AhgHooks::filter('record.title', $record->title, $record);
```

### 4.2 AhgCapabilities - Feature Detection

```php
// Register capability (in plugin Configuration)
AhgCapabilities::register('iiif', 'ahgIiifPlugin', [
    'version' => '3.0',
    'auth' => true
]);

// Check capability (anywhere)
if (AhgCapabilities::has('iiif')) {
    // Show IIIF viewer
}
```

### 4.3 AhgPanels - UI Slots

```php
// Register panel (in plugin Configuration)
AhgPanels::register('informationobject', 'condition-status', [
    'title' => 'Condition',
    'position' => 'sidebar',
    'component' => 'condition/statusComponent'
]);

// Get panels for position (in template)
$panels = AhgPanels::forPosition('informationobject', 'sidebar', $record);
```

### 4.4 AhgSectorProfile - Sector Configuration

```php
// Register sector (in Museum plugin)
AhgSectorProfile::register('museum', [
    'name' => 'Museum',
    'standard' => 'Spectrum 5.0',
    'labels' => [
        'extent' => 'Dimensions',
        'scopeAndContent' => 'Object Description'
    ],
    'vocabularies' => [
        'materialType' => ['Oil paint', 'Bronze', 'Marble']
    ]
]);

// Get label for current sector
$label = AhgSectorProfile::getLabel('extent', 'Extent');
```

---

## 5. Directory Structure Standards

```
plugin-name/
├── config/
│   └── pluginNameConfiguration.class.php    # Plugin initialization
├── database/
│   ├── install.sql                          # Initial schema
│   └── migrations/                          # Version migrations
├── lib/
│   ├── Services/                            # Business logic
│   ├── Repositories/                        # Data access
│   └── Extensions/                          # Extension implementations
├── modules/
│   └── moduleName/
│       ├── actions/                         # ONLY if this plugin OWNS the module
│       │   └── actionName.class.php
│       ├── templates/                       # Can exist without owning module
│       │   └── _partial.php
│       └── config/
│           └── module.yml
├── web/
│   ├── css/                                 # Plugin stylesheets
│   ├── js/                                  # Plugin scripts
│   ├── images/                              # Plugin images
│   └── vendor/                              # Third-party assets
└── extension.json                           # Plugin metadata
```

---

## 6. Cross-Plugin Link Guards

### 6.1 The Problem

Sector plugins (Gallery, Museum, DAM) and shared menus (theme, reports) contain links to features provided by optional capability plugins (Spectrum, Provenance, GRAP, etc.). When those plugins are disabled, the links become broken (404 errors or blank pages).

### 6.2 The `ahg_is_plugin_enabled()` Helper

A cached helper function checks whether a plugin is enabled at runtime:

```php
// Located in: ahgUiOverridesPlugin/lib/helper/AhgLaravelHelper.php

function ahg_is_plugin_enabled(string $pluginName): bool
{
    static $cache = [];
    if (isset($cache[$pluginName])) {
        return $cache[$pluginName];
    }
    try {
        $cache[$pluginName] = DB::table('atom_plugin')
            ->where('name', $pluginName)
            ->where('is_enabled', 1)
            ->exists();
    } catch (\Exception $e) {
        $cache[$pluginName] = false;
    }
    return $cache[$pluginName];
}
```

**Performance:** Uses a static cache. Each plugin name is queried only once per request.

### 6.3 Template Usage Pattern

Every template that uses `ahg_is_plugin_enabled()` must include the helper:

```php
<?php require_once sfConfig::get('sf_plugins_dir')
    . '/ahgUiOverridesPlugin/lib/helper/AhgLaravelHelper.php'; ?>
```

Then wrap optional plugin links:

```php
<?php if (ahg_is_plugin_enabled('ahgSpectrumPlugin')): ?>
    <a href="<?php echo url_for(['module' => 'spectrum', 'action' => 'index']); ?>">
        SPECTRUM Procedures
    </a>
<?php endif; ?>
```

### 6.4 Plugin Name Reference

Templates use module names for routing but plugin names for enable checks:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         MODULE → PLUGIN MAPPING                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Module Name     │ Plugin Name               │ Check Expression                 │
│  ─────────────────┼───────────────────────────┼──────────────────────────────── │
│  spectrum        │ ahgSpectrumPlugin          │ ahg_is_plugin_enabled(           │
│                  │                            │   'ahgSpectrumPlugin')           │
│  cco             │ ahgProvenancePlugin        │ ahg_is_plugin_enabled(           │
│                  │                            │   'ahgProvenancePlugin')         │
│  grap            │ ahgHeritageAccountingPlugin│ ahg_is_plugin_enabled(           │
│                  │                            │   'ahgHeritageAccountingPlugin') │
│  oais            │ ahgPreservationPlugin      │ ahg_is_plugin_enabled(           │
│                  │                            │   'ahgPreservationPlugin')       │
│  condition       │ ahgConditionPlugin         │ ahg_is_plugin_enabled(           │
│                  │                            │   'ahgConditionPlugin')          │
│  loan            │ ahgLoanPlugin              │ ahg_is_plugin_enabled(           │
│                  │                            │   'ahgLoanPlugin')               │
│  research        │ ahgResearchPlugin          │ ahg_is_plugin_enabled(           │
│                  │                            │   'ahgResearchPlugin')           │
│  3dmodel         │ ahg3DModelPlugin           │ ahg_is_plugin_enabled(           │
│                  │                            │   'ahg3DModelPlugin')            │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_6a784bf1.png)
```

### 6.5 Guarded Templates

| Plugin | Templates with Guards |
|--------|----------------------|
| ahgGalleryPlugin | `gallery/templates/indexSuccess.php` (sidebar + admin dropdown) |
| ahgMuseumPlugin | `museum/templates/indexSuccess.php`, `museum/templates/conditionReportSuccess.php`, `cco/templates/conditionReportSuccess.php`, `cco/templates/_actions.php` |
| ahgDAMPlugin | `dam/templates/dashboardSuccess.php` |
| ahgThemeB5Plugin | `templates/_glamDamMenu.php` |
| ahgReportsPlugin | `reports/templates/_reportsMenu.php` |

### 6.6 Rules for Developers

1. **Always guard cross-plugin links** - If a template links to a module owned by another optional plugin, wrap the link with `ahg_is_plugin_enabled()`.
2. **Use the correct plugin name** - Module names differ from plugin names (see mapping above).
3. **Include the helper** - Every template using the function must `require_once` the AhgLaravelHelper.
4. **Never guard links to core/infrastructure plugins** - ahgDisplayPlugin, ahgThemeB5Plugin, ahgSettingsPlugin, and ahgSecurityClearancePlugin are always enabled.

---

## 7. CI Architecture Checks

The `bin/check-architecture.sh` script enforces:

| Check | Rule | Severity |
|-------|------|----------|
| Cross-Plugin Guards | Links to optional plugins use `ahg_is_plugin_enabled()` | WARNING |

| Check | Rule | Severity |
|-------|------|----------|
| Theme Actions | ahgThemeB5Plugin has no actions/*.php | ERROR |
| Module Ownership | Each module has exactly one action owner | ERROR |
| Absolute Paths | No /usr/share/nginx in PHP files | ERROR |
| Asset Location | Assets only under web/ | ERROR |
| DB Location | SQL only under database/ | ERROR |
| Zero-byte Files | No empty files (except .gitkeep) | ERROR |
| Sector Plugins | No core module actions in sector plugins | ERROR |

---

*Part of the AtoM AHG Framework - v2.8.2*
