# AtoM AHG Framework - System Flows

**Version:** 2.8.2
**Last Updated:** February 2026

---

## 0. Heratio Dual-Mode Request Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     HERATIO DUAL-MODE REQUEST FLOW                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌──────────┐                                                                   │
│  │  Client  │                                                                   │
│  │ (Browser)│                                                                   │
│  └────┬─────┘                                                                   │
│       │ HTTP Request                                                            │
│       ▼                                                                         │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │                            NGINX                                          │  │
│  │                                                                          │  │
│  │   ┌──────────────────────────────┐  ┌─────────────────────────────────┐ │  │
│  │   │ heratio.conf (if installed)  │  │ Standard AtoM config            │ │  │
│  │   │                              │  │                                 │ │  │
│  │   │ /ingest/*      → heratio.php │  │ /                → index.php   │ │  │
│  │   │ /admin/ahg-*   → heratio.php │  │ /informationobj  → index.php   │ │  │
│  │   │ /display/*     → heratio.php │  │ /actor           → index.php   │ │  │
│  │   │ /privacy/*     → heratio.php │  │ /repository      → index.php   │ │  │
│  │   │ /research/*    → heratio.php │  │ /user            → index.php   │ │  │
│  │   │ (~40 plugin patterns)        │  │ (all base AtoM)                │ │  │
│  │   └──────────────┬───────────────┘  └──────────────┬──────────────────┘ │  │
│  └──────────────────┼──────────────────────────────────┼─────────────────────┘  │
│                     │                                  │                         │
│       ┌─────────────┘                                  └──────────────┐         │
│       ▼                                                               ▼         │
│  ┌────────────────────────┐                    ┌────────────────────────────┐  │
│  │  HERATIO ENTRY POINT   │                    │  SYMFONY ENTRY POINT       │  │
│  │  heratio.php           │                    │  index.php                 │  │
│  │                        │                    │                            │  │
│  │  1. Check kill-switch  │                    │  sfContext::getInstance()  │  │
│  │  2. Boot Kernel        │                    │  dispatch()                │  │
│  │  3. Middleware stack   │                    │  (unchanged AtoM)          │  │
│  │  4. Route dispatch     │                    │                            │  │
│  └───────────┬────────────┘                    └──────────────┬─────────────┘  │
│              │                                                │                 │
│              ▼                                                ▼                 │
│  ┌────────────────────────┐                    ┌────────────────────────────┐  │
│  │  HERATIO MIDDLEWARE    │                    │  SYMFONY FILTER CHAIN      │  │
│  │                        │                    │                            │  │
│  │  1. SessionMiddleware  │                    │  securityFilter            │  │
│  │  2. AuthMiddleware     │                    │  accessFilter              │  │
│  │  3. SettingsMiddleware │                    │  cacheFilter               │  │
│  │  4. CspMiddleware      │                    │  executionFilter           │  │
│  │  5. MetaMiddleware     │                    │                            │  │
│  │  6. LimitsMiddleware   │                    │                            │  │
│  └───────────┬────────────┘                    └──────────────┬─────────────┘  │
│              │                                                │                 │
│              ▼                                                ▼                 │
│  ┌────────────────────────┐                    ┌────────────────────────────┐  │
│  │  ACTION BRIDGE         │                    │  SYMFONY ACTION            │  │
│  │                        │                    │                            │  │
│  │  Dispatches to one of: │                    │  sfAction->execute()       │  │
│  │  • AhgController       │                    │  Propel ORM               │  │
│  │  • AhgActions (Blade)  │                    │  sfView rendering         │  │
│  │  • sfActions (Bridge)  │                    │                            │  │
│  └───────────┬────────────┘                    └──────────────┬─────────────┘  │
│              │                                                │                 │
│              ▼                                                ▼                 │
│  ┌────────────────────────┐                    ┌────────────────────────────┐  │
│  │  RENDERING             │                    │  RENDERING                 │  │
│  │                        │                    │                            │  │
│  │  BladeRenderer         │                    │  sfPHPView                 │  │
│  │  heratio.blade.php     │                    │  layout.php                │  │
│  │  (master layout)       │                    │  (theme layout)            │  │
│  │  + 8 partials          │                    │                            │  │
│  └───────────┬────────────┘                    └──────────────┬─────────────┘  │
│              │                                                │                 │
│              └────────────────────┬───────────────────────────┘                 │
│                                   ▼                                             │
│                          ┌─────────────┐                                        │
│                          │   Client    │ ← HTML Response                        │
│                          └─────────────┘                                        │
│                                                                                  │
│  KILL-SWITCH: Remove .heratio_enabled file → ALL routes go to index.php         │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_c9f50a6c.png)
```

---

## 1. Request Processing Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           HTTP REQUEST FLOW                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌──────────┐                                                                   │
│  │  Client  │                                                                   │
│  │ (Browser)│                                                                   │
│  └────┬─────┘                                                                   │
│       │ HTTP Request                                                            │
│       │ GET /informationobject/browse                                           │
│       ▼                                                                         │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │                            NGINX                                          │  │
│  │                                                                          │  │
│  │   location / { fastcgi_pass php-fpm; }                                   │  │
│  │   location /plugins/ { alias /path/to/plugins/; }                        │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│       │                                                                         │
│       ▼                                                                         │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │                          PHP-FPM 8.3                                      │  │
│  │                                                                          │  │
│  │   index.php → sfContext::getInstance() → dispatch()                      │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│       │                                                                         │
│       ▼                                                                         │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │                    SYMFONY 1.x FRONT CONTROLLER                           │  │
│  │                                                                          │  │
│  │   1. Load config/ProjectConfiguration.class.php                          │  │
│  │   2. Call setup() → loadPluginsFromDatabase()                            │  │
│  │   3. Bootstrap atom-framework                                            │  │
│  │   4. Query atom_plugin for enabled plugins                               │  │
│  │   5. Enable plugins via $this->enablePlugins()                           │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│       │                                                                         │
│       ▼                                                                         │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │                         ROUTING                                           │  │
│  │                                                                          │  │
│  │   apps/qubit/config/routing.yml                                          │  │
│  │   + plugin routing.yml files                                             │  │
│  │                                                                          │  │
│  │   /informationobject/browse → informationobject/browseAction             │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│       │                                                                         │
│       ▼                                                                         │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │                    FILTER CHAIN                                           │  │
│  │                                                                          │  │
│  │   ┌─────────────────┐                                                    │  │
│  │   │ securityFilter  │ ◄── ahgSecurityClearancePlugin                     │  │
│  │   │ (check access)  │     Verifies user clearance vs record class        │  │
│  │   └────────┬────────┘                                                    │  │
│  │            ▼                                                             │  │
│  │   ┌─────────────────┐                                                    │  │
│  │   │ accessFilter    │ ◄── Core AtoM ACL                                  │  │
│  │   │ (check ACL)     │     Verifies group permissions                     │  │
│  │   └────────┬────────┘                                                    │  │
│  │            ▼                                                             │  │
│  │   ┌─────────────────┐                                                    │  │
│  │   │ cacheFilter     │                                                    │  │
│  │   └────────┬────────┘                                                    │  │
│  │            ▼                                                             │  │
│  │   ┌─────────────────┐                                                    │  │
│  │   │ executionFilter │ ◄── Runs the action                                │  │
│  │   └─────────────────┘                                                    │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│       │                                                                         │
│       ▼                                                                         │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │                         ACTION EXECUTION                                  │  │
│  │                                                                          │  │
│  │   plugins/ahgDisplayPlugin/modules/informationobject/                    │  │
│  │     actions/browseAction.class.php                                       │  │
│  │                                                                          │  │
│  │   class browseAction extends sfAction {                                  │  │
│  │     public function execute($request) {                                  │  │
│  │       // Query via Propel (core AtoM)                                    │  │
│  │       $records = QubitInformationObject::getAll();                       │  │
│  │                                                                          │  │
│  │       // Query via Laravel (extension data)                              │  │
│  │       $conditions = DB::table('condition_assessment')                    │  │
│  │         ->whereIn('object_id', $ids)->get();                             │  │
│  │                                                                          │  │
│  │       // Trigger hooks for panels                                        │  │
│  │       $panels = AhgHooks::trigger('browse.panels');                      │  │
│  │     }                                                                    │  │
│  │   }                                                                      │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│       │                                                                         │
│       ▼                                                                         │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │                       TEMPLATE RENDERING                                  │  │
│  │                                                                          │  │
│  │   Layout: plugins/ahgThemeB5Plugin/templates/layout.php                  │  │
│  │   Template: plugins/ahgDisplayPlugin/modules/.../browseSuccess.php       │  │
│  │                                                                          │  │
│  │   Template includes:                                                     │  │
│  │   • Sector-specific labels via AhgSectorProfile::getLabel()              │  │
│  │   • Registered panels via AhgPanels::forPosition()                       │  │
│  │   • Capability checks via AhgCapabilities::has()                         │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│       │                                                                         │
│       ▼                                                                         │
│  ┌──────────┐                                                                   │
│  │  Client  │ ◄── HTML Response                                                 │
│  └──────────┘                                                                   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_0159ff1f.png)
```

---

## 2. Plugin Installation Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        PLUGIN INSTALLATION FLOW                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ $ php bin/atom extension:install ahgPrivacyPlugin                        │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 1. ExtensionManager::install()                                           │   │
│  │                                                                          │   │
│  │    ┌────────────────────────────────────────────────────────────────┐   │   │
│  │    │ Check if plugin exists locally                                  │   │   │
│  │    │                                                                 │   │   │
│  │    │ if (!file_exists($pluginsPath/$name)) {                        │   │   │
│  │    │     // Fetch from GitHub via PluginFetcher                     │   │   │
│  │    │     $fetcher->clone($repoUrl, $pluginsPath);                   │   │   │
│  │    │ }                                                               │   │   │
│  │    └────────────────────────────────────────────────────────────────┘   │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 2. Read extension.json                                                   │   │
│  │                                                                          │   │
│  │    {                                                                     │   │
│  │      "name": "ahgPrivacyPlugin",                                         │   │
│  │      "version": "1.2.0",                                                 │   │
│  │      "category": "compliance",                                           │   │
│  │      "dependencies": ["ahgCorePlugin"],                                  │   │
│  │      "database": {                                                       │   │
│  │        "install": "database/install.sql",                                │   │
│  │        "migrations": "database/migrations/"                              │   │
│  │      }                                                                   │   │
│  │    }                                                                     │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 3. Check dependencies                                                    │   │
│  │                                                                          │   │
│  │    foreach ($dependencies as $dep) {                                     │   │
│  │      if (!$this->isEnabled($dep)) {                                      │   │
│  │        $this->enable($dep);  // Enable dependency first                  │   │
│  │      }                                                                   │   │
│  │    }                                                                     │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 4. Run database migrations                                               │   │
│  │                                                                          │   │
│  │    MigrationHandler::runInstall($plugin)                                 │   │
│  │                                                                          │   │
│  │    ┌────────────────────────────────────────────────────────────────┐   │   │
│  │    │ // Execute database/install.sql                                 │   │   │
│  │    │ CREATE TABLE privacy_breach (...);                              │   │   │
│  │    │ CREATE TABLE privacy_consent (...);                             │   │   │
│  │    │ CREATE TABLE privacy_sar_request (...);                         │   │   │
│  │    │                                                                 │   │   │
│  │    │ // Run migrations in order                                      │   │   │
│  │    │ 001_initial.sql                                                 │   │   │
│  │    │ 002_add_breach_columns.sql                                      │   │   │
│  │    └────────────────────────────────────────────────────────────────┘   │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 5. Create symlink (if not exists)                                        │   │
│  │                                                                          │   │
│  │    ln -s /path/to/atom-ahg-plugins/ahgPrivacyPlugin                      │   │
│  │           /path/to/atom/plugins/ahgPrivacyPlugin                         │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 6. Register in atom_plugin table                                         │   │
│  │                                                                          │   │
│  │    INSERT INTO atom_plugin (                                             │   │
│  │      name, class_name, version, category,                                │   │
│  │      is_enabled, is_core, is_locked, load_order                          │   │
│  │    ) VALUES (                                                            │   │
│  │      'ahgPrivacyPlugin', 'ahgPrivacyPluginConfiguration',                │   │
│  │      '1.2.0', 'compliance', 1, 0, 0, 50                                  │   │
│  │    );                                                                    │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 7. Clear Symfony cache                                                   │   │
│  │                                                                          │   │
│  │    rm -rf cache/*                                                        │   │
│  │    php symfony cc                                                        │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ ✓ Plugin ahgPrivacyPlugin installed and enabled                          │   │
│  │                                                                          │   │
│  │ Restart PHP-FPM for changes to take effect:                              │   │
│  │ $ sudo systemctl restart php8.3-fpm                                      │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_6966b106.png)
```

---

## 3. Audit Trail Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                            AUDIT TRAIL FLOW                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ User Action: Edit record                                                 │   │
│  │ POST /informationobject/edit/123                                         │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Action: editAction.class.php                                             │   │
│  │                                                                          │   │
│  │   // Get original values                                                 │   │
│  │   $original = $record->toArray();                                        │   │
│  │                                                                          │   │
│  │   // Apply changes                                                       │   │
│  │   $record->title = $request->getParameter('title');                      │   │
│  │   $record->save();                                                       │   │
│  │                                                                          │   │
│  │   // Calculate diff                                                      │   │
│  │   $changes = array_diff_assoc($record->toArray(), $original);            │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ AhgHooks::trigger('record.updated', $record, $changes)                   │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ ahgAuditTrailPlugin listener                                             │   │
│  │                                                                          │   │
│  │   AhgHooks::register('record.updated', function($record, $changes) {     │   │
│  │                                                                          │   │
│  │     // Create audit log entry                                            │   │
│  │     DB::table('audit_log')->insert([                                     │   │
│  │       'user_id'     => sfContext::getInstance()->getUser()->getId(),     │   │
│  │       'object_id'   => $record->id,                                      │   │
│  │       'object_type' => get_class($record),                               │   │
│  │       'action'      => 'update',                                         │   │
│  │       'module'      => 'informationobject',                              │   │
│  │       'changes'     => json_encode($changes),                            │   │
│  │       'ip_address'  => $_SERVER['REMOTE_ADDR'],                          │   │
│  │       'user_agent'  => $_SERVER['HTTP_USER_AGENT'],                      │   │
│  │       'created_at'  => date('Y-m-d H:i:s'),                              │   │
│  │     ]);                                                                  │   │
│  │                                                                          │   │
│  │     // Create detail records for each changed field                      │   │
│  │     foreach ($changes as $field => $value) {                             │   │
│  │       DB::table('audit_log_detail')->insert([...]);                      │   │
│  │     }                                                                    │   │
│  │   });                                                                    │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Database State                                                           │   │
│  │                                                                          │   │
│  │   audit_log                                                              │   │
│  │   ┌────┬─────────┬───────────┬────────┬────────────────────────────┐    │   │
│  │   │ id │ user_id │ object_id │ action │ changes                    │    │   │
│  │   ├────┼─────────┼───────────┼────────┼────────────────────────────┤    │   │
│  │   │ 42 │    5    │    123    │ update │ {"title":{"old":"...      │    │   │
│  │   └────┴─────────┴───────────┴────────┴────────────────────────────┘    │   │
│  │                                                                          │   │
│  │   audit_log_detail                                                       │   │
│  │   ┌────┬──────────────┬────────────┬───────────────┬─────────────┐      │   │
│  │   │ id │ audit_log_id │ field_name │ old_value     │ new_value   │      │   │
│  │   ├────┼──────────────┼────────────┼───────────────┼─────────────┤      │   │
│  │   │ 98 │      42      │ title      │ Old Title     │ New Title   │      │   │
│  │   └────┴──────────────┴────────────┴───────────────┴─────────────┘      │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│  ═══════════════════════════════════════════════════════════════════════════   │
│                                                                                  │
│  Audit Log Viewer: Admin → Audit Trail                                          │
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Filter: [User ▼] [Action ▼] [Module ▼] [Date Range]    [Search]        │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │ Time         │ User      │ Action │ Record              │ Changes      │   │
│  ├──────────────┼───────────┼────────┼─────────────────────┼──────────────┤   │
│  │ 10:45:23     │ jsmith    │ UPDATE │ Document ABC-123    │ title, date  │   │
│  │ 10:42:11     │ admin     │ CREATE │ Collection XYZ      │ -            │   │
│  │ 10:38:05     │ jsmith    │ VIEW   │ Photo Album         │ -            │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_f2dd4e46.png)
```

---

## 4. IIIF Manifest Generation Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        IIIF MANIFEST GENERATION FLOW                             │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Request: GET /iiif/manifest/123                                          │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 1. Check cache                                                           │   │
│  │                                                                          │   │
│  │    $cached = DB::table('iiif_manifest')                                  │   │
│  │      ->where('object_id', 123)                                           │   │
│  │      ->where('updated_at', '>', $record->updated_at)                     │   │
│  │      ->first();                                                          │   │
│  │                                                                          │   │
│  │    if ($cached) return json_decode($cached->manifest_json);              │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │ Cache miss                               │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 2. Load record and digital objects                                       │   │
│  │                                                                          │   │
│  │    $record = QubitInformationObject::getById(123);                       │   │
│  │    $digitalObjects = $record->getDigitalObjects();                       │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 3. Build IIIF Presentation 3.0 manifest                                  │   │
│  │                                                                          │   │
│  │    {                                                                     │   │
│  │      "@context": "http://iiif.io/api/presentation/3/context.json",       │   │
│  │      "id": "https://example.org/iiif/manifest/123",                      │   │
│  │      "type": "Manifest",                                                 │   │
│  │      "label": { "en": ["Record Title"] },                                │   │
│  │      "metadata": [                                                       │   │
│  │        { "label": {"en":["Creator"]}, "value": {"en":["John Doe"]} }    │   │
│  │      ],                                                                  │   │
│  │      "items": [                                                          │   │
│  │        {                                                                 │   │
│  │          "id": "https://example.org/iiif/canvas/123-1",                  │   │
│  │          "type": "Canvas",                                               │   │
│  │          "width": 4000,                                                  │   │
│  │          "height": 3000,                                                 │   │
│  │          "items": [...]                                                  │   │
│  │        }                                                                 │   │
│  │      ]                                                                   │   │
│  │    }                                                                     │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 4. Add annotations (if available)                                        │   │
│  │                                                                          │   │
│  │    // OCR text                                                           │   │
│  │    $ocr = DB::table('iiif_ocr_text')->where('object_id', 123)->first();  │   │
│  │    if ($ocr) {                                                           │   │
│  │      manifest.annotations = OcrService::generateAnnotationPage();        │   │
│  │    }                                                                     │   │
│  │                                                                          │   │
│  │    // Transcriptions                                                     │   │
│  │    $transcription = TranscriptionService::get(123);                      │   │
│  │    if ($transcription) {                                                 │   │
│  │      manifest.supplementing = ...                                        │   │
│  │    }                                                                     │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 5. Apply access restrictions                                             │   │
│  │                                                                          │   │
│  │    if (AhgCapabilities::has('security')) {                               │   │
│  │      $classification = SecurityService::getClassification(123);         │   │
│  │      if ($classification->level > 0) {                                   │   │
│  │        manifest.services = [                                             │   │
│  │          { "@type": "AuthCookieService1", ... }                          │   │
│  │        ];                                                                │   │
│  │      }                                                                   │   │
│  │    }                                                                     │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 6. Cache and return                                                      │   │
│  │                                                                          │   │
│  │    DB::table('iiif_manifest')->updateOrInsert(                           │   │
│  │      ['object_id' => 123],                                               │   │
│  │      ['manifest_json' => json_encode($manifest), ...]                    │   │
│  │    );                                                                    │   │
│  │                                                                          │   │
│  │    return Response::json($manifest)                                      │   │
│  │      ->header('Access-Control-Allow-Origin', '*')                        │   │
│  │      ->header('Content-Type', 'application/ld+json');                    │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│  ═══════════════════════════════════════════════════════════════════════════   │
│                                                                                  │
│  Viewer Integration:                                                            │
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ <div id="mirador">                                                       │   │
│  │   <script>                                                               │   │
│  │     Mirador.viewer({                                                     │   │
│  │       id: 'mirador',                                                     │   │
│  │       windows: [{                                                        │   │
│  │         manifestId: 'https://example.org/iiif/manifest/123'              │   │
│  │       }]                                                                 │   │
│  │     });                                                                  │   │
│  │   </script>                                                              │   │
│  │ </div>                                                                   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_fb611f71.png)
```

---

## 5. AI/NER Processing Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          NER PROCESSING FLOW                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ Trigger: User clicks "Extract Entities" or batch job runs               │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 1. Collect text content                                                  │   │
│  │                                                                          │   │
│  │    $text = $record->getScopeAndContent() .                               │   │
│  │            $record->getArchivalHistory() .                               │   │
│  │            $record->getTitle();                                          │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 2. Send to Python NER service                                            │   │
│  │                                                                          │   │
│  │    // atom-ahg-python/src/atom_ahg/resources/ner.py                      │   │
│  │                                                                          │   │
│  │    import spacy                                                          │   │
│  │    nlp = spacy.load("en_core_web_lg")                                    │   │
│  │                                                                          │   │
│  │    doc = nlp(text)                                                       │   │
│  │    entities = []                                                         │   │
│  │    for ent in doc.ents:                                                  │   │
│  │        entities.append({                                                 │   │
│  │            'text': ent.text,                                             │   │
│  │            'type': ent.label_,  # PERSON, ORG, GPE, DATE                 │   │
│  │            'start': ent.start_char,                                      │   │
│  │            'end': ent.end_char,                                          │   │
│  │            'confidence': ent.kb_id_                                      │   │
│  │        })                                                                │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 3. Process entities                                                      │   │
│  │                                                                          │   │
│  │    Entities found:                                                       │   │
│  │    ┌────────────────────┬──────────┬────────────────────────────────┐   │   │
│  │    │ Text               │ Type     │ Action                         │   │   │
│  │    ├────────────────────┼──────────┼────────────────────────────────┤   │   │
│  │    │ John Smith         │ PERSON   │ Link to/create actor record    │   │   │
│  │    │ Acme Corporation   │ ORG      │ Link to/create actor record    │   │   │
│  │    │ Cape Town          │ GPE      │ Link to place authority        │   │   │
│  │    │ 15 March 1952      │ DATE     │ Parse and validate date        │   │   │
│  │    └────────────────────┴──────────┴────────────────────────────────┘   │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 4. User review (optional)                                                │   │
│  │                                                                          │   │
│  │    ┌──────────────────────────────────────────────────────────────┐     │   │
│  │    │ Entity Suggestions                                           │     │   │
│  │    ├──────────────────────────────────────────────────────────────┤     │   │
│  │    │ ☑ John Smith (PERSON) → Create new actor                     │     │   │
│  │    │ ☑ Acme Corporation (ORG) → Link to existing: Acme Corp Ltd  │     │   │
│  │    │ ☐ Cape Town (PLACE) → [Reject - already linked]             │     │   │
│  │    │ ☑ 15 March 1952 (DATE) → Add to dates field                  │     │   │
│  │    │                                                              │     │   │
│  │    │                              [Apply Selected] [Reject All]   │     │   │
│  │    └──────────────────────────────────────────────────────────────┘     │   │
│  └────────────────────────────────────┬────────────────────────────────────┘   │
│                                       │                                          │
│                                       ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 5. Apply changes                                                         │   │
│  │                                                                          │   │
│  │    // Create actor if not exists                                         │   │
│  │    $actor = QubitActor::getByName('John Smith')                          │   │
│  │      ?? QubitActor::create(['authorized_form_of_name' => 'John Smith']); │   │
│  │                                                                          │   │
│  │    // Link to record                                                     │   │
│  │    QubitRelation::create([                                               │   │
│  │      'subject_id' => $record->id,                                        │   │
│  │      'object_id' => $actor->id,                                          │   │
│  │      'type_id' => QubitTerm::NAME_ACCESS_POINT_ID                        │   │
│  │    ]);                                                                   │   │
│  │                                                                          │   │
│  │    // Log extraction                                                     │   │
│  │    DB::table('ner_extraction_log')->insert([...]);                       │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_49e0ea17.png)
```

---

## 6. Ingest Pipeline Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          INGEST PIPELINE FLOW                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  STEP 1          STEP 2          STEP 3          STEP 4          STEP 5         │
│  CONFIGURE  ───► UPLOAD     ───► MAP & ENRICH ──► VALIDATE   ───► PREVIEW       │
│  (sector,        (CSV/ZIP/       (auto-map,       (required       (tree view,   │
│   standard,       EAD, dir)       metadata,        fields,         approval)    │
│   options)                        profiles)        checksums)                    │
│                                                                                  │
│  ──────────────────────────────────────────────────────────────────► STEP 6     │
│                                                                     COMMIT      │
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ COMMIT FLOW (Background Job)                                            │   │
│  │                                                                         │   │
│  │  Browser ──POST──► Action ──► Create ingest_job (status=queued)         │   │
│  │                      │                                                   │   │
│  │                      ├──► nohup php symfony ingest:commit --job-id=X &   │   │
│  │                      │                                                   │   │
│  │                      └──► Return page (AJAX polling starts)              │   │
│  │                                                                         │   │
│  │  CLI Task ──► Mark running ──► For each row:                            │   │
│  │                                  ├─ Create InformationObject            │   │
│  │                                  ├─ Create DigitalObject (if DO path)   │   │
│  │                                  ├─ Generate derivatives                │   │
│  │                                  └─ Run AI processing (NER/OCR/etc)     │   │
│  │                                                                         │   │
│  │              ──► Build SIP package ──► Build AIP ──► Build DIP          │   │
│  │              ──► Update search index                                     │   │
│  │              ──► Generate manifest CSV                                   │   │
│  │              ──► Mark completed                                          │   │
│  │                                                                         │   │
│  │  Browser ◄──poll every 2s──► /ingest/ajax/job-status?job_id=X          │   │
│  │           ──► Show progress bar ──► On complete: show report card       │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│  ROLLBACK: Deletes created IOs + DOs + packages, restores session state         │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_ccc981ef.png)
```

---

*Part of the AtoM AHG Framework - v2.8.2*
