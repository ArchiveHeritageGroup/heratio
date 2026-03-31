# AtoM AHG Framework - Technical Architecture

## For Technical Discussion with Artifactual
**Prepared by:** The Archive and Heritage Group  
**Date:** January 2026

---

## 1. Executive Summary

The AtoM AHG Framework is a **non-invasive modernization layer** that:
- Integrates Laravel Query Builder into AtoM 2.10 without modifying core files
- Provides database-driven plugin management replacing hardcoded configurations
- Maintains 100% backward compatibility with existing AtoM functionality
- Targets GLAM institutions with South African regulatory compliance (POPIA, NARSSA, PAIA, GRAP 103)

**Key Principle:** We enhance AtoM, we don't fork it.

---

## 2. Architectural Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         USER INTERFACE                                   │
│                    (Bootstrap 5 via ahgThemeB5Plugin)                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │              AtoM 2.10 PRESENTATION LAYER                        │   │
│  │         (Symfony 1.x Controllers, Actions, Templates)            │   │
│  │                    ↓ UNCHANGED ↓                                 │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                              │                                          │
│                              ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                    INTEGRATION POINT                             │   │
│  │           ProjectConfiguration.class.php                         │   │
│  │         loadPluginsFromDatabase() ← NEW FUNCTION                 │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                              │                                          │
│         ┌────────────────────┴────────────────────┐                    │
│         ▼                                          ▼                    │
│  ┌──────────────────────┐                 ┌──────────────────────┐     │
│  │   SYMFONY 1.x ORM    │                 │   atom-framework     │     │
│  │      (Propel)        │                 │  (Laravel Query      │     │
│  │   Core AtoM Data     │                 │    Builder)          │     │
│  │                      │                 │  Extension Data      │     │
│  └──────────┬───────────┘                 └──────────┬───────────┘     │
│             │                                        │                  │
│             └────────────────┬───────────────────────┘                  │
│                              ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                       MySQL 8 DATABASE                           │   │
│  │  ┌─────────────────┐              ┌─────────────────────────┐   │   │
│  │  │  Core AtoM      │              │  AHG Extensions         │   │   │
│  │  │  Tables         │              │  Tables (atom_*)        │   │   │
│  │  │  (unchanged)    │              │                         │   │   │
│  │  └─────────────────┘              └─────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 3. How the Framework Connects to AtoM

### 3.1 Bootstrap Integration Point

The framework bootstraps via a single function added to AtoM's config:

```php
// /config/ProjectConfiguration.class.php

class ProjectConfiguration extends sfProjectConfiguration
{
    public function setup()
    {
        // 1. Load core Symfony plugins (unchanged)
        $corePlugins = array(
            'sfWebBrowserPlugin',
            'sfThumbnailPlugin',
            // ... standard AtoM plugins
        );
        
        // 2. NEW: Load database-driven plugins
        $this->loadPluginsFromDatabase($corePlugins);
    }
    
    // NEW FUNCTION - Only addition to AtoM config
    protected function loadPluginsFromDatabase($corePlugins)
    {
        // Initialize Laravel Query Builder
        require_once dirname(__FILE__) . '/../atom-framework/bootstrap.php';
        
        // Query atom_plugin table
        $dbPlugins = DB::table('atom_plugin')
            ->where('is_enabled', 1)
            ->orderBy('load_order')
            ->pluck('name')
            ->toArray();
        
        // Merge and enable
        $allPlugins = array_unique(array_merge($corePlugins, $dbPlugins));
        $this->enablePlugins($allPlugins);
    }
}
```

### 3.2 Framework Bootstrap Sequence

```
Request → index.php → ProjectConfiguration::setup()
                              │
                              ├── 1. Standard Symfony init
                              │
                              ├── 2. loadPluginsFromDatabase()
                              │       │
                              │       ├── require atom-framework/bootstrap.php
                              │       │       │
                              │       │       ├── Composer autoload
                              │       │       ├── Initialize Capsule (Laravel DB)
                              │       │       └── Connect to MySQL
                              │       │
                              │       ├── Query atom_plugin table
                              │       └── Return enabled plugins
                              │
                              ├── 3. enablePlugins($merged)
                              │
                              └── 4. Continue Symfony lifecycle
```

---

## 4. Database Architecture

### 4.1 Plugin Management Table

```sql
CREATE TABLE atom_plugin (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,        -- Plugin class name
    class_name VARCHAR(255) NOT NULL,          -- Configuration class
    version VARCHAR(50),
    description TEXT,
    category VARCHAR(100) DEFAULT 'general',   -- theme|ahg|sector|utility
    is_enabled TINYINT(1) DEFAULT 0,
    is_core TINYINT(1) DEFAULT 0,              -- Cannot be disabled
    is_locked TINYINT(1) DEFAULT 0,            -- Cannot be modified
    load_order INT DEFAULT 100,                -- Plugin loading sequence
    plugin_path VARCHAR(500),
    settings JSON,                             -- Per-plugin configuration
    enabled_at TIMESTAMP,
    disabled_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 4.2 Dual-ORM Strategy

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        DATA ACCESS PATTERNS                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  PROPEL (Symfony 1.x ORM)           │  LARAVEL QUERY BUILDER           │
│  ─────────────────────────          │  ───────────────────────         │
│                                     │                                   │
│  • Core AtoM entities:              │  • Extension tables:              │
│    - QubitInformationObject         │    - atom_plugin                  │
│    - QubitActor                     │    - atom_landing_page            │
│    - QubitTerm                      │    - atom_security_clearance      │
│    - QubitRepository                │    - atom_researcher              │
│    - QubitDigitalObject             │    - atom_audit_log               │
│                                     │                                   │
│  • Why Propel for core:             │  • Why Laravel for extensions:    │
│    - Maintains AtoM compatibility   │    - Modern fluent syntax         │
│    - Existing relationships work    │    - Easier testing               │
│    - No migration risk              │    - Repository pattern support   │
│                                     │    - Collection helpers           │
│                                     │                                   │
│  EXCEPTION: Plugin Manager uses     │                                   │
│  PDO directly due to autoloader     │                                   │
│  conflicts during Symfony boot      │                                   │
│                                     │                                   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Plugin Architecture

### 5.1 Directory Structure

```
/usr/share/nginx/archive/
├── atom-framework/                    # LAYER 1: Core Framework
│   ├── bootstrap.php                  # Laravel initialization
│   ├── composer.json                  # Dependencies
│   ├── bin/
│   │   ├── install                    # Installation script
│   │   ├── atom                       # CLI entry point
│   │   └── release                    # Version management
│   ├── config/
│   │   └── ProjectConfiguration.class.php.template
│   ├── database/
│   │   └── install.sql                # Schema definitions
│   └── src/
│       ├── Extensions/
│       │   ├── ExtensionManager.php   # Plugin management service
│       │   └── ExtensionProtection.php
│       ├── Repositories/              # Base repository classes
│       ├── Services/                  # Business logic services
│       └── Helpers/                   # Utility classes
│
├── atom-ahg-plugins/                  # LAYER 2: Plugin Source
│   ├── ahgThemeB5Plugin/              # Bootstrap 5 theme (LOCKED)
│   ├── ahgSecurityClearancePlugin/    # Security system (LOCKED)
│   ├── ahgLibraryPlugin/              # Library sector features
│   ├── ahgLandingPagePlugin/          # Landing page builder
│   └── [26+ additional plugins]
│
├── plugins/                           # Symlinks for Symfony
│   ├── ahgThemeB5Plugin -> ../atom-ahg-plugins/ahgThemeB5Plugin
│   ├── ahgLibraryPlugin -> ../atom-ahg-plugins/ahgLibraryPlugin
│   └── [symlinks for all enabled plugins]
│
└── config/
    └── ProjectConfiguration.class.php # Modified with loadPluginsFromDatabase()
```

### 5.2 Plugin Loading Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     PLUGIN LOADING SEQUENCE                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  1. Symfony Initialization                                              │
│     └── ProjectConfiguration::setup()                                   │
│                                                                         │
│  2. Core Plugins (Hardcoded)                                            │
│     └── $corePlugins = ['sfWebBrowserPlugin', 'sfThumbnailPlugin', ...] │
│                                                                         │
│  3. Database Query (NEW)                                                │
│     └── SELECT name FROM atom_plugin                                    │
│         WHERE is_enabled = 1                                            │
│         ORDER BY load_order                                             │
│                                                                         │
│  4. Merge Arrays                                                        │
│     └── $allPlugins = array_unique(merge($core, $db))                   │
│                                                                         │
│  5. Enable Plugins                                                      │
│     └── $this->enablePlugins($allPlugins)                               │
│         └── Symfony loads each plugin's Configuration class             │
│             └── Routes registered                                       │
│             └── Templates available                                     │
│             └── Assets accessible                                       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 6. CLI Interface

### 6.1 Command Structure

```bash
# Extension Management
php bin/atom extension:discover      # Scan and list available extensions
php bin/atom extension:enable <name> # Enable a plugin
php bin/atom extension:disable <name># Disable a plugin (if not locked)
php bin/atom extension:list          # Show enabled/disabled status

# Framework Management  
php bin/atom framework:install       # Initial setup
php bin/atom framework:update        # Pull latest changes
php bin/atom framework:version       # Display version info

# Version Release (Development)
./bin/release patch "message"        # Bump patch version
./bin/release minor "message"        # Bump minor version
```

### 6.2 Extension Enable Process

```
php bin/atom extension:enable ahgLibraryPlugin
                │
                ├── 1. Validate plugin exists in atom-ahg-plugins/
                │
                ├── 2. Check dependencies (extension.json)
                │
                ├── 3. Create symlink: plugins/ -> atom-ahg-plugins/
                │
                ├── 4. UPDATE atom_plugin SET is_enabled = 1
                │
                ├── 5. Run plugin's install task (if exists)
                │
                ├── 6. Load data files (taxonomy terms, etc.)
                │
                ├── 7. Clear Symfony cache
                │
                └── 8. Output success message
```

---

## 7. What We Don't Modify

### 7.1 Core AtoM Integrity

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    UNTOUCHED COMPONENTS                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  DATABASE SCHEMA (Never Modified)                                       │
│  ───────────────────────────────                                        │
│  • object                    • slug                                     │
│  • information_object        • information_object_i18n                  │
│  • actor                     • actor_i18n                               │
│  • term                      • term_i18n                                │
│  • taxonomy                  • repository                               │
│  • digital_object            • physical_object                          │
│  • relation                  • event                                    │
│  • user                      • acl_*                                    │
│                                                                         │
│  PHP FILES (Never Patched)                                              │
│  ────────────────────────                                               │
│  • lib/QubitInformationObject.php                                       │
│  • lib/QubitActor.php                                                   │
│  • lib/model/map/*.php                                                  │
│  • lib/model/om/*.php                                                   │
│  • apps/qubit/modules/* (core modules)                                  │
│                                                                         │
│  CONFIGURATION (Template Replacement Only)                              │
│  ─────────────────────────────────────────                              │
│  • ProjectConfiguration.class.php                                       │
│    └── Template REPLACES entire file (not patched)                      │
│    └── Contains loadPluginsFromDatabase() addition                      │
│                                                                         │
│  ROUTING (Preserved)                                                    │
│  ─────────────────                                                      │
│  • All core AtoM routes work unchanged                                  │
│  • Extensions add NEW routes only                                       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 8. Repository Pattern

### 8.1 Extension Repository Example

```php
<?php
// atom-framework/src/Repositories/BaseRepository.php

namespace AtomExtensions\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

abstract class BaseRepository
{
    protected string $table;
    protected string $primaryKey = 'id';
    
    public function find(int $id): ?array
    {
        return DB::table($this->table)
            ->where($this->primaryKey, $id)
            ->first();
    }
    
    public function findWhere(array $conditions): Collection
    {
        $query = DB::table($this->table);
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        return $query->get();
    }
    
    public function create(array $data): int
    {
        return DB::table($this->table)->insertGetId($data);
    }
    
    public function update(int $id, array $data): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKey, $id)
            ->update($data) > 0;
    }
}
```

### 8.2 Plugin-Specific Repository

```php
<?php
// atom-ahg-plugins/ahgLibraryPlugin/lib/Repositories/LibraryItemRepository.php

namespace ahgLibraryPlugin\Repositories;

use AtomExtensions\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class LibraryItemRepository extends BaseRepository
{
    protected string $table = 'atom_library_item';
    
    public function findByCallNumber(string $callNumber): ?array
    {
        return DB::table($this->table)
            ->where('call_number', $callNumber)
            ->first();
    }
    
    public function getCheckedOutItems(): Collection
    {
        return DB::table($this->table)
            ->where('status', 'checked_out')
            ->orderBy('due_date')
            ->get();
    }
}
```

---

## 9. Theme Integration

### 9.1 Bootstrap 5 Implementation

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    THEME ARCHITECTURE                                    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ahgThemeB5Plugin/                                                      │
│  ├── css/                                                               │
│  │   ├── main.css              # Bootstrap 5 core                       │
│  │   ├── _variables.css        # CSS custom properties                  │
│  │   └── components/           # Component-specific styles              │
│  │                                                                      │
│  ├── js/                                                                │
│  │   ├── bootstrap.bundle.min.js                                        │
│  │   └── ahg-theme.js          # Theme JavaScript                       │
│  │                                                                      │
│  ├── templates/                                                         │
│  │   ├── layout.php            # Main layout (overrides core)           │
│  │   └── _partials/            # Reusable components                    │
│  │                                                                      │
│  └── config/                                                            │
│      └── ahgThemeB5PluginConfiguration.class.php                        │
│                                                                         │
│  CSS Variable Convention (BEM + Namespace)                              │
│  ─────────────────────────────────────────                              │
│  --ahg-primary: #0d6efd;                                                │
│  --ahg-card__header--bg: var(--ahg-primary);                            │
│  --ahg-landing__hero--height: 400px;                                    │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 10. Deployment Model

### 10.1 Installation Process

```bash
# Step 1: Clone repositories
git clone https://github.com/ArchiveHeritageGroup/atom-framework.git
git clone https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git

# Step 2: Install dependencies
cd atom-framework && composer install

# Step 3: Run installer
bash bin/install

# What bin/install does:
# ├── Creates symlinks for plugins
# ├── Runs database migrations (install.sql)
# ├── Copies ProjectConfiguration.class.php.template
# ├── Loads plugin data files
# ├── Enables required plugins (ahgThemeB5Plugin, ahgSecurityClearancePlugin)
# ├── Copies dist assets
# └── Clears cache
```

### 10.2 Update Process

```bash
# Framework updates
cd atom-framework
git pull origin main
bash bin/install --update

# Plugin updates  
cd atom-ahg-plugins
git pull origin main

# Individual plugin enable
php bin/atom extension:enable ahgNewPlugin
```

---

## 11. Technical Specifications

| Component | Technology |
|-----------|------------|
| Base Platform | AtoM 2.10 |
| PHP Version | 8.3 |
| Framework ORM | Laravel Query Builder (Illuminate\Database) |
| Core ORM | Propel (Symfony 1.x) - unchanged |
| Database | MySQL 8 |
| Search | Elasticsearch 7.10 |
| Web Server | nginx |
| Theme | Bootstrap 5 |
| CLI | Symfony Tasks + Custom Commands |

---

## 12. ProjectConfiguration.class.php.template

```php
<?php
class ProjectConfiguration extends sfProjectConfiguration
{
    protected $pluginsLoaded = false;
    
    public function setup()
    {
        $corePlugins = array(
            'sfWebBrowserPlugin',
            'sfThumbnailPlugin',
            'sfJSONPlugin',
            'sfInstallPlugin',
            'qtAccessionPlugin',
            // ... other core plugins
        );
        
        $this->loadPluginsFromDatabase($corePlugins);
    }
    
    protected function loadPluginsFromDatabase($corePlugins)
    {
        if ($this->pluginsLoaded) {
            return;
        }
        
        $frameworkPath = dirname(__FILE__) . '/../atom-framework';
        
        if (!file_exists($frameworkPath . '/bootstrap.php')) {
            $this->enablePlugins($corePlugins);
            return;
        }
        
        require_once $frameworkPath . '/bootstrap.php';
        
        try {
            $dbPlugins = \Illuminate\Database\Capsule\Manager::table('atom_plugin')
                ->where('is_enabled', 1)
                ->orderBy('load_order')
                ->pluck('name')
                ->toArray();
                
            $allPlugins = array_unique(array_merge($corePlugins, $dbPlugins));
            $this->enablePlugins($allPlugins);
        } catch (\Exception $e) {
            $this->enablePlugins($corePlugins);
        }
        
        $this->pluginsLoaded = true;
    }
}
```

---

**Document Version:** 1.0.0  
**Last Updated:** January 2026  
**The Archive and Heritage Group**
