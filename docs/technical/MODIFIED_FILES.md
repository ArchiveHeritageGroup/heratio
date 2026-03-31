# Modified AtoM Core Files

This document describes the files that differ from a pure AtoM 2.10 installation when using the AHG Framework.

---

## Summary

| File | Type | Purpose |
|------|------|---------|
| `config/ProjectConfiguration.class.php` | Replaced | Database-driven plugin loading |
| `plugins/sfPluginAdminPlugin/.../themesAction.class.php` | Patched | Theme visibility fix |
| `plugins/qbAclPlugin/lib/QubitAcl.class.php` | Patched | Role 99 duplicate fix |
| `/etc/nginx/sites-available/atom` | Modified | Framework compatibility |

---

## 1. ProjectConfiguration.class.php

**Location:** `{ATOM_ROOT}/config/ProjectConfiguration.class.php`

**Type:** Replaced entirely (from template)

**Template:** `atom-framework/config/ProjectConfiguration.class.php.template`

### What Changed

The install script replaces AtoM's default `ProjectConfiguration.class.php` with a custom version that includes:

1. **Framework Bootstrap Loading** - Initializes Laravel Query Builder
2. **Database-Driven Plugin Loading** - `loadPluginsFromDatabase()` function

### Key Addition: loadPluginsFromDatabase()
```php
/**
 * Load plugins from atom_plugin table instead of setting_i18n
 */
protected function loadPluginsFromDatabase(array $corePlugins = []): array
{
    $plugins = $corePlugins;
    
    try {
        $pdo = new PDO(
            'mysql:host=' . sfConfig::get('app_database_host', 'localhost') . 
            ';dbname=' . sfConfig::get('app_database_name'),
            sfConfig::get('app_database_user'),
            sfConfig::get('app_database_password')
        );
        
        $stmt = $pdo->query('SELECT name FROM atom_plugin WHERE is_enabled = 1 ORDER BY load_order');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!in_array($row['name'], $plugins)) {
                $plugins[] = $row['name'];
            }
        }
    } catch (PDOException $e) {
        // Fallback to core plugins only
    }
    
    return $plugins;
}
```

### Why This Change

- **Before:** AtoM loads plugins from `setting_i18n` table (id=1), a serialized PHP array
- **After:** Plugins loaded from `atom_plugin` table with proper fields (is_enabled, load_order, is_locked)
- **Benefit:** Enables CLI management, plugin auditing, and prevents accidental disabling of critical plugins

### Legacy Compatibility

The `setting_i18n` table (id=1) is still maintained for:
- The sfPluginAdminPlugin UI (Admin → Plugins)
- Backwards compatibility with AtoM core code

When a plugin is enabled via CLI, both tables are updated.

---

## 2. themesAction.class.php

**Location:** `{ATOM_ROOT}/plugins/sfPluginAdminPlugin/modules/sfPluginAdminPlugin/actions/themesAction.class.php`

**Type:** Patched (specific lines modified)

### What Changed

Removed the loop that filters out enabled plugins from the theme list.

### Original Code (lines 43-46)
```php
foreach (sfPluginAdminPluginConfiguration::$pluginNames as $name) {
    unset($pluginPaths[$name]);
}
```

### Patched Code
```php
// Removed: themes should remain visible even when enabled
// foreach (sfPluginAdminPluginConfiguration::$pluginNames as $name) {
//     unset($pluginPaths[$name]);
// }
```

### Why This Change

**Problem:** AtoM's default behavior removes enabled plugins from the themes list. When `ahgThemeB5Plugin` is enabled, it disappears from Admin → Themes, making it impossible to switch or view theme settings.

**Root Cause:** Symlinks from `plugins/` to `atom-ahg-plugins/` resolve to their real path, which doesn't match `sf_plugins_dir`, causing theme detection to fail for enabled plugins.

**Solution:** Don't filter out enabled plugins - themes should always be visible in the list.

### Additional Fixes in This File

1. **Unserialize Fallback** (line ~80):
```php
// Before
$settings = unserialize($setting->getValue(['sourceCulture' => true]));

// After
$settings = unserialize($setting->getValue(['sourceCulture' => true])) ?: [];
```

2. **Try/Catch around Plugin Instantiation** - Prevents fatal errors if a plugin class fails to load

---

## 3. QubitAcl.class.php

**Location:** `{ATOM_ROOT}/plugins/qbAclPlugin/lib/QubitAcl.class.php`

**Type:** Patched (specific method modified)

### What Changed

Added `in_array` checks in `buildUserRoleList()` method to prevent duplicate role registration.

### Original Code
```php
protected function buildUserRoleList($user)
{
    // Don't add user twice
    if (in_array($user->getUserID(), $this->_roles)) {
        return $this;
    }

    $parents = [];

    if ($user->isAuthenticated()) {
        // Add authenticated group
        $this->acl->addRole(QubitAclGroup::getById(QubitAclGroup::AUTHENTICATED_ID));
        $this->_roles[] = QubitAclGroup::AUTHENTICATED_ID;

        // Add groups (if user belongs to any)
        if (0 < count($aclUserGroups = $user->user->getAclUserGroups())) {
            foreach ($aclUserGroups as $aclUserGroup) {
                $aclGroup = $aclUserGroup->group;
                $this->acl->addRole($aclGroup, $aclGroup->parent);
                $this->_roles[] = $aclGroup->id;
                $parents[] = $aclGroup->id;
            }
        }
        // ...
    }
}
```

### Patched Code
```php
protected function buildUserRoleList($user)
{
    // Don't add user twice
    if (in_array($user->getUserID(), $this->_roles)) {
        return $this;
    }

    $parents = [];

    if ($user->isAuthenticated()) {
        // Add authenticated group (check first to avoid duplicate)
        if (!in_array(QubitAclGroup::AUTHENTICATED_ID, $this->_roles)) {
            $this->acl->addRole(QubitAclGroup::getById(QubitAclGroup::AUTHENTICATED_ID));
            $this->_roles[] = QubitAclGroup::AUTHENTICATED_ID;
        }

        // Add groups (if user belongs to any)
        if (0 < count($aclUserGroups = $user->user->getAclUserGroups())) {
            foreach ($aclUserGroups as $aclUserGroup) {
                $aclGroup = $aclUserGroup->group;
                if (!in_array($aclGroup->id, $this->_roles)) {
                    $this->acl->addRole($aclGroup, $aclGroup->parent);
                    $this->_roles[] = $aclGroup->id;
                }
                $parents[] = $aclGroup->id;
            }
        }
        // ...
    }
}
```

### Why This Change

**Problem:** When a user is in `acl_user_group` with `group_id = 99` (Authenticated), the code:
1. First adds role 99 (AUTHENTICATED_ID) for all authenticated users
2. Then loops through user's groups and tries to add 99 again
3. Zend ACL throws: `Role id '99' already exists in the registry`

**Error Message:**
```
500 | Internal Server Error | Zend_Acl_Role_Registry_Exception
Role id '99' already exists in the registry
```

**Solution:** Check `in_array` before calling `addRole` to prevent duplicates.

---

## 4. Nginx Configuration

**Location:** `/etc/nginx/sites-available/atom` (or similar)

**Type:** Modified for framework compatibility

### Standard AtoM Configuration
```nginx
location ~ ^/(index|qubit_dev)\.php(/|$) {
    include /etc/nginx/fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_split_path_info ^(.+\.php)(/.*)$;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

### AHG Framework Configuration

No changes required to nginx for basic framework operation. The framework integrates through PHP, not nginx.

### Optional: PHP-FPM Pool Configuration

If using `auto_prepend_file` (alternative loading method):

**Location:** `/etc/php/8.3/fpm/pool.d/atom.conf`
```ini
; AtoM Framework - auto-prepend (optional, not currently used)
; php_admin_value[auto_prepend_file] = /usr/share/nginx/atom/atom-framework/prepend.php
```

**Current Method:** Framework is loaded via `ProjectConfiguration.class.php` bootstrap, not auto_prepend.

---

## Applying Patches

### During Fresh Install

The `bin/install` script automatically applies all patches:
```bash
cd /usr/share/nginx/atom/atom-framework
bash bin/install
```

### Manual Patch Application

If patches need to be reapplied (e.g., after AtoM upgrade):
```bash
# 1. ProjectConfiguration - just re-run install
cd /usr/share/nginx/atom/atom-framework
bash bin/install

# 2. themesAction.class.php - comment out the unset loop
# Find lines with: foreach (sfPluginAdminPluginConfiguration::$pluginNames
# Comment out the foreach block

# 3. QubitAcl.class.php - add in_array checks
# The install script includes a PHP patcher for this
```

---

## After AtoM Upgrades

When upgrading AtoM core:

1. **ProjectConfiguration.class.php** - Will be overwritten. Re-run `bash bin/install`
2. **themesAction.class.php** - May be overwritten. Check and re-patch if needed
3. **QubitAcl.class.php** - May be overwritten. Re-run install (includes auto-patch)

### Verification Commands
```bash
# Check ProjectConfiguration has loadPluginsFromDatabase
grep -c "loadPluginsFromDatabase" /usr/share/nginx/atom/config/ProjectConfiguration.class.php

# Check themesAction has the fix (should show commented lines)
grep -c "Removed: themes should remain" /usr/share/nginx/atom/plugins/sfPluginAdminPlugin/modules/sfPluginAdminPlugin/actions/themesAction.class.php

# Check QubitAcl has the fix
grep -c "check first to avoid duplicate" /usr/share/nginx/atom/plugins/qbAclPlugin/lib/QubitAcl.class.php
```

---

## File Checksums

For verification after upgrades:
```bash
# Generate checksums of patched files
md5sum /usr/share/nginx/atom/config/ProjectConfiguration.class.php
md5sum /usr/share/nginx/atom/plugins/sfPluginAdminPlugin/modules/sfPluginAdminPlugin/actions/themesAction.class.php
md5sum /usr/share/nginx/atom/plugins/qbAclPlugin/lib/QubitAcl.class.php
```

---

## Troubleshooting

### Plugins Not Loading

1. Check `ProjectConfiguration.class.php` has `loadPluginsFromDatabase()`
2. Verify `atom_plugin` table exists and has enabled plugins
3. Clear cache: `php symfony cc`

### Theme Not Visible in Admin

1. Check `themesAction.class.php` patch is applied
2. Verify symlink exists: `ls -la plugins/ahgThemeB5Plugin`
3. Clear cache and restart PHP-FPM

### Role 99 Error on Login

1. Check `QubitAcl.class.php` patch is applied
2. Verify with: `grep "check first to avoid duplicate" plugins/qbAclPlugin/lib/QubitAcl.class.php`
3. Re-run install script if patch is missing
