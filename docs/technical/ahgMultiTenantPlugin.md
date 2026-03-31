# ahgMultiTenantPlugin - Technical Documentation

**Version:** 1.2.0
**Category:** Security
**Dependencies:** ahgCorePlugin
**Status:** Active Development

---

## Overview

Multi-tenancy plugin for AtoM providing dedicated tenant management with domain routing, status control, user hierarchy, and custom branding. Version 1.2.0 introduces automatic tenant resolution from subdomain and custom domains (Issue #85).

---

## What's New in v1.2.0

- **Domain Routing (Issue #85)**: Automatic tenant resolution from HTTP host
- **Subdomain Support**: `{tenant}.heritage.example.com` → tenant context
- **Custom Domain Support**: `archive.institution.org` → tenant context
- **TenantResolver Service**: Domain-to-tenant resolution with caching
- **Unknown Domain Handling**: Error pages for unrecognized domains
- **Nginx Configuration**: Complete examples for wildcard and custom domains
- **Dedicated Database Tables**: `heritage_tenant`, `heritage_tenant_user`, `heritage_tenant_settings_override`
- **Tenant Status Management**: Active, Suspended, and Trial states
- **Extended Role System**: Owner, Super User, Editor, Contributor, Viewer

---

## Architecture

```
+-------------------------------------------------------------------------+
|                       ahgMultiTenantPlugin v1.2.0                        |
+-------------------------------------------------------------------------+
|                                                                          |
|  +-------------------------------------------------------------------+  |
|  |                  Domain Routing (Issue #85)                        |  |
|  +-------------------------------------------------------------------+  |
|  |                                                                    |  |
|  |  HTTP Request                                                      |  |
|  |       |                                                            |  |
|  |       v                                                            |  |
|  |  +----------------+    +------------------+    +----------------+  |  |
|  |  | Custom Domain  |--->| TenantResolver   |--->| Tenant Context |  |  |
|  |  | archive.org    |    | resolveFromHost()|    | initialized    |  |  |
|  |  +----------------+    +------------------+    +----------------+  |  |
|  |       OR                       |                                   |  |
|  |  +----------------+            |                                   |  |
|  |  | Subdomain      |------------+                                   |  |
|  |  | tenant.base.com|                                                |  |
|  |  +----------------+                                                |  |
|  |                                                                    |  |
|  +-------------------------------------------------------------------+  |
|                                                                          |
|  +-------------------------------------------------------------------+  |
|  |                     Database Tables                                |  |
|  +-------------------------------------------------------------------+  |
|  | heritage_tenant           | Tenant organizations with status       |  |
|  | heritage_tenant_user      | User-tenant role assignments          |  |
|  | heritage_tenant_settings  | Per-tenant setting overrides          |  |
|  +-------------------------------------------------------------------+  |
|                                    |                                     |
|           +------------------------+------------------------+            |
|           |                        |                        |            |
|           v                        v                        v            |
|  +----------------+     +------------------+     +------------------+    |
|  |    Models      |     |    Services      |     |    Modules       |    |
|  +----------------+     +------------------+     +------------------+    |
|  | Tenant         |     | TenantResolver   |     | tenantAdmin      |    |
|  | TenantUser     |     | TenantService    |     | tenantUsers      |    |
|  |                |     | TenantContext    |     | tenantBranding   |    |
|  |                |     | TenantAccess     |     | tenantSwitcher   |    |
|  |                |     | TenantBranding   |     | tenantError      |    |
|  +----------------+     +------------------+     +------------------+    |
|                                                                          |
|  +-------------------------------------------------------------------+  |
|  |                      Status Management                             |  |
|  +-------------------------------------------------------------------+  |
|  | active     | Full access to tenant resources                       |  |
|  | trial      | Time-limited access with configurable period         |  |
|  | suspended  | No access, with reason tracking                      |  |
|  +-------------------------------------------------------------------+  |
|                                                                          |
|  +-------------------------------------------------------------------+  |
|  |                       Role Hierarchy                               |  |
|  +-------------------------------------------------------------------+  |
|  | owner (4)      | Full tenant control, all user management         |  |
|  | super_user (3) | User assignment (not owners), branding           |  |
|  | editor (2)     | Content editing                                   |  |
|  | contributor (1)| Content addition                                  |  |
|  | viewer (0)     | Read-only access                                  |  |
|  +-------------------------------------------------------------------+  |
|                                                                          |
+-------------------------------------------------------------------------+
```

---

## Database Schema

### heritage_tenant

Primary table for tenant organizations.

```sql
CREATE TABLE heritage_tenant (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique tenant code/slug',
    name VARCHAR(255) NOT NULL COMMENT 'Display name',
    domain VARCHAR(255) DEFAULT NULL COMMENT 'Custom domain',
    subdomain VARCHAR(100) DEFAULT NULL COMMENT 'Subdomain prefix',
    settings JSON DEFAULT NULL COMMENT 'Tenant-specific settings',
    status ENUM('active', 'suspended', 'trial') NOT NULL DEFAULT 'trial',
    trial_ends_at DATETIME DEFAULT NULL COMMENT 'Trial expiration',
    suspended_at DATETIME DEFAULT NULL COMMENT 'Suspension timestamp',
    suspended_reason VARCHAR(500) DEFAULT NULL COMMENT 'Suspension reason',
    repository_id INT DEFAULT NULL COMMENT 'Link to AtoM repository',
    contact_name VARCHAR(255) DEFAULT NULL,
    contact_email VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,

    INDEX idx_tenant_code (code),
    INDEX idx_tenant_status (status),
    INDEX idx_tenant_domain (domain),
    INDEX idx_tenant_subdomain (subdomain),
    INDEX idx_tenant_repository (repository_id),

    FOREIGN KEY (repository_id) REFERENCES repository(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES user(id) ON DELETE SET NULL
);
```

### heritage_tenant_user

User-tenant assignments with role-based access.

```sql
CREATE TABLE heritage_tenant_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'super_user', 'editor', 'contributor', 'viewer') NOT NULL DEFAULT 'viewer',
    is_primary TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Users primary tenant',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT DEFAULT NULL,

    UNIQUE KEY uk_tenant_user (tenant_id, user_id),
    INDEX idx_tenant_user_tenant (tenant_id),
    INDEX idx_tenant_user_user (user_id),
    INDEX idx_tenant_user_role (role),

    FOREIGN KEY (tenant_id) REFERENCES heritage_tenant(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES user(id) ON DELETE SET NULL
);
```

### heritage_tenant_settings_override

Per-tenant settings that override global defaults.

```sql
CREATE TABLE heritage_tenant_settings_override (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,

    UNIQUE KEY uk_tenant_setting (tenant_id, setting_key),
    FOREIGN KEY (tenant_id) REFERENCES heritage_tenant(id) ON DELETE CASCADE
);
```

### Legacy Settings (ahg_settings - still supported)

| Pattern | Description |
|---------|-------------|
| `tenant_repo_{id}_super_users` | Comma-separated super user IDs |
| `tenant_repo_{id}_users` | Comma-separated user IDs |
| `tenant_repo_{id}_primary_color` | Primary brand color |
| `tenant_repo_{id}_*` | Other branding settings |

---

## Models

### Tenant

```php
namespace AhgMultiTenant\Models;

class Tenant
{
    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TRIAL = 'trial';

    // Finders
    public static function find(int $id): ?self;
    public static function findByCode(string $code): ?self;
    public static function findByRepository(int $repositoryId): ?self;
    public static function findByDomain(string $domain): ?self;
    public static function findBySubdomain(string $subdomain): ?self;
    public static function all(array $filters = []): array;

    // Status checks
    public function isActive(): bool;
    public function isSuspended(): bool;
    public function isTrial(): bool;
    public function isTrialExpired(): bool;
    public function canAccess(): bool; // Active or valid trial

    // Settings
    public function getSetting(string $key, $default = null);
    public function setSetting(string $key, $value): self;

    // Users
    public function getUserCount(): int;
    public function getUsers(?string $role = null): array;

    // Persistence
    public function save(): bool;
    public function delete(): bool;

    // Utilities
    public static function isCodeUnique(string $code, ?int $excludeId = null): bool;
    public static function generateCode(string $name): string;
}
```

### TenantUser

```php
namespace AhgMultiTenant\Models;

class TenantUser
{
    // Role constants
    public const ROLE_OWNER = 'owner';
    public const ROLE_SUPER_USER = 'super_user';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_CONTRIBUTOR = 'contributor';
    public const ROLE_VIEWER = 'viewer';

    // Role hierarchy (higher = more permissions)
    public const ROLE_HIERARCHY = [
        'viewer' => 0, 'contributor' => 1, 'editor' => 2,
        'super_user' => 3, 'owner' => 4
    ];

    // Finders
    public static function findByTenantAndUser(int $tenantId, int $userId): ?self;
    public static function getTenantsForUser(int $userId): array;
    public static function getUsersForTenant(int $tenantId, ?string $role = null): array;

    // Role checks
    public static function hasMinimumRole(int $userId, int $tenantId, string $minimumRole): bool;

    // Assignment
    public static function assign(int $tenantId, int $userId, string $role, ?int $assignedBy): ?self;
    public static function remove(int $tenantId, int $userId): bool;
    public static function updateRole(int $tenantId, int $userId, string $newRole): bool;

    // Primary tenant
    public static function setPrimaryTenant(int $userId, int $tenantId): bool;
    public static function getPrimaryTenant(int $userId): ?object;
}
```

---

## Services

### TenantService

Primary service for tenant CRUD and status management.

```php
namespace AhgMultiTenant\Services;

class TenantService
{
    public const DEFAULT_TRIAL_DAYS = 14;

    // CRUD
    public static function create(array $data, ?int $createdBy = null): array;
    public static function update(int $tenantId, array $data, ?int $updatedBy = null): array;
    public static function delete(int $tenantId, ?int $deletedBy = null): array;

    // Status Management
    public static function activate(int $tenantId, ?int $activatedBy = null): array;
    public static function suspend(int $tenantId, ?string $reason, ?int $suspendedBy = null): array;
    public static function startTrial(int $tenantId, int $trialDays = 14, ?int $startedBy = null): array;
    public static function extendTrial(int $tenantId, int $additionalDays, ?int $extendedBy = null): array;

    // User Management
    public static function assignUser(int $tenantId, int $userId, string $role, ?int $assignedBy): array;
    public static function removeUser(int $tenantId, int $userId, ?int $removedBy): array;
    public static function updateUserRole(int $tenantId, int $userId, string $newRole, ?int $updatedBy): array;

    // Statistics
    public static function getStatistics(): array;
    // Returns: [total, active, suspended, trial, trial_expiring_soon, trial_expired]

    // Settings Override
    public static function getSetting(int $tenantId, string $settingKey, $default = null);
    public static function setSetting(int $tenantId, string $settingKey, $value, ?int $updatedBy): bool;
}
```

### TenantContext

Manages current tenant context and session state.

```php
namespace AhgMultiTenant\Services;

class TenantContext
{
    public const ADMIN_GROUP_ID = 100;

    // Current context (NEW in v1.1.0)
    public static function getCurrentTenantId(): ?int;
    public static function getCurrentTenant(): ?Tenant;
    public static function setCurrentTenant(?int $tenantId): bool;

    // Legacy repository context
    public static function getCurrentRepositoryId(): ?int;
    public static function setCurrentRepository(?int $repositoryId): bool;

    // User access
    public static function canAccessTenant(int $userId, int $tenantId): bool;
    public static function getUserTenantRole(int $userId, int $tenantId): ?string;
    public static function getUserTenants(int $userId): array;
    public static function getUserRepositories(int $userId): array;

    // Role checks
    public static function isAdmin(int $userId): bool;
    public static function isSuperUser(int $userId, int $repositoryId): bool;
    public static function hasMinimumRoleInCurrentTenant(int $userId, string $minimumRole): bool;

    // Query filtering
    public static function applyRepositoryFilter($query, string $repositoryColumn = 'repository_id');

    // Cache
    public static function clearCache(): void;
}
```

### TenantAccess

Access control for tenant operations.

```php
namespace AhgMultiTenant\Services;

class TenantAccess
{
    // NEW in v1.1.0 - Tenant-based access
    public static function canAccessTenant(int $userId, int $tenantId): bool;
    public static function canManageTenantUsers(int $userId, int $tenantId): bool;
    public static function canManageTenantSettings(int $userId, int $tenantId): bool;
    public static function canManageTenantStatus(int $userId): bool; // Admin only

    public static function assignUserToTenant(int $userId, int $tenantId, string $role, int $assignedBy): array;
    public static function removeUserFromTenant(int $userId, int $tenantId, int $removedBy): array;
    public static function updateUserTenantRole(int $userId, int $tenantId, string $newRole, int $updatedBy): array;

    // Legacy repository-based access (still supported)
    public static function canAccessRepository(int $userId, int $repositoryId): bool;
    public static function canAssignUsers(int $userId, int $repositoryId): bool;
    public static function canManageBranding(int $userId, int $repositoryId): bool;
    public static function assignUserToRepository(int $userId, int $repositoryId, int $assignedBy): array;
    public static function removeUserFromRepository(int $userId, int $repositoryId, int $removedBy): array;
}
```

### TenantResolver (NEW in v1.2.0)

Resolves tenant from HTTP host (domain or subdomain). Implements Issue #85.

```php
namespace AhgMultiTenant\Services;

class TenantResolver
{
    // Initialization
    public static function initialize(array $config = []): void;
    // Config: base_domain, enabled, excluded_domains

    // Resolution
    public static function resolveFromHost(?string $host = null): ?Tenant;
    public static function resolveByCustomDomain(string $domain): ?Tenant;
    public static function resolveBySubdomain(string $host): ?Tenant;

    // Subdomain extraction
    public static function extractSubdomain(string $host): ?string;
    public static function getBaseDomain(): ?string;
    public static function setBaseDomain(string $domain): void;

    // Utilities
    public static function normalizeHost(string $host): string;
    public static function isExcludedDomain(string $host): bool;
    public static function isTenantRequest(): bool;
    public static function getResolutionDetails(?string $host = null): array;

    // URL generation
    public static function generateTenantUrl(Tenant $tenant, string $path = '', bool $preferCustomDomain = true): string;

    // Cache
    public static function clearCache(): void;
    public static function setEnabled(bool $enabled): void;
}
```

**Resolution Order:**
1. Custom domain exact match (`archive.institution.org`)
2. Subdomain match (`tenant.heritage.example.com`)
3. Fallback to session-based context

**Configuration (app.yml):**
```yaml
all:
  multi_tenant_base_domain: heritage.example.com
  multi_tenant_domain_routing: true
  multi_tenant_excluded_domains: [localhost, 127.0.0.1]
  multi_tenant_unknown_action: error  # or 'redirect'
  multi_tenant_unknown_redirect: https://heritage.example.com
```

---

## Domain Routing

### Resolution Flow

```
HTTP Request (host: tenant.heritage.example.com)
    |
    v
TenantResolver::resolveFromHost()
    |
    +-- Check custom domain (heritage_tenant.domain)
    |       |
    |       +-- Match found? → Return tenant
    |
    +-- Extract subdomain from host
    |       |
    |       +-- Check subdomain (heritage_tenant.subdomain)
    |               |
    |               +-- Match found? → Return tenant
    |
    +-- No match → Return null → Session fallback
```

### Configuration

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `multi_tenant_base_domain` | string | auto | Base domain for subdomain detection |
| `multi_tenant_domain_routing` | bool | true | Enable/disable domain routing |
| `multi_tenant_excluded_domains` | array | localhost | Domains to skip resolution |
| `multi_tenant_unknown_action` | string | error | Action for unknown domains |
| `multi_tenant_unknown_redirect` | string | - | Redirect URL if action is 'redirect' |

### Nginx Configuration

See `config/nginx/multi-tenant.conf` for:
- Wildcard subdomain configuration
- Custom domain server blocks
- SSL certificate setup (Let's Encrypt)
- Security headers

**Wildcard Certificate:**
```bash
certbot certonly --dns-cloudflare \
  -d heritage.example.com \
  -d "*.heritage.example.com"
```

---

## Routes

### New Routes (v1.1.0)

| Route | URL | Method | Description |
|-------|-----|--------|-------------|
| `tenant_admin_create` | `/admin/tenants/create` | GET | Create tenant form |
| `tenant_admin_store` | `/admin/tenants/store` | POST | Store new tenant |
| `tenant_admin_edit_tenant` | `/admin/tenants/:id/edit-tenant` | GET | Edit tenant form |
| `tenant_admin_update_tenant` | `/admin/tenants/:id/update` | POST | Update tenant |
| `tenant_admin_activate` | `/admin/tenants/:id/activate` | POST | Activate tenant |
| `tenant_admin_suspend` | `/admin/tenants/:id/suspend` | POST | Suspend tenant |
| `tenant_admin_extend_trial` | `/admin/tenants/:id/extend-trial` | POST | Extend trial |
| `tenant_admin_delete` | `/admin/tenants/:id/delete` | POST | Delete tenant |
| `tenant_admin_assign_user` | `/admin/tenants/assign-user` | POST | Assign user |
| `tenant_admin_remove_user` | `/admin/tenants/remove-user` | POST | Remove user |
| `tenant_admin_update_user_role` | `/admin/tenants/update-user-role` | POST | Update role |

### Legacy Routes (still supported)

| Route | URL | Description |
|-------|-----|-------------|
| `tenant_admin` | `/admin/tenants` | Admin dashboard |
| `tenant_admin_super_users` | `/admin/tenants/:id/super-users` | Super users |
| `tenant_users` | `/tenant/:id/users` | User management |
| `tenant_branding` | `/tenant/:id/branding` | Branding |
| `tenant_switch` | `/tenant/switch/:id` | Switch repository |

---

## Data Migration

### From v1.0.x to v1.1.0

```sql
-- 1. Create new tables (via install.sql)

-- 2. Run migration procedure
CALL migrate_tenant_data();

-- 3. Verify
SELECT COUNT(*) FROM heritage_tenant;
SELECT COUNT(*) FROM heritage_tenant_user;

-- 4. Optional cleanup (after verification)
DELETE FROM ahg_settings WHERE setting_key LIKE 'tenant_repo_%';
DROP PROCEDURE IF EXISTS migrate_tenant_data;
```

The migration:
- Creates tenants from repositories with existing settings
- Migrates super users with `super_user` role
- Migrates regular users with `editor` role
- Copies branding settings to tenant's JSON settings field

---

## Integration Examples

### Tenant-Based Access Control

```php
use AhgMultiTenant\Services\TenantContext;
use AhgMultiTenant\Models\Tenant;

// Get current tenant
$tenant = TenantContext::getCurrentTenant();
if ($tenant && $tenant->canAccess()) {
    // Tenant is active or in valid trial
}

// Check role
if (TenantContext::hasMinimumRoleInCurrentTenant($userId, 'editor')) {
    // User has editor or higher role
}
```

### Creating a Tenant Programmatically

```php
use AhgMultiTenant\Services\TenantService;
use AhgMultiTenant\Models\TenantUser;

// Create tenant
$result = TenantService::create([
    'name' => 'New Organization',
    'code' => 'new-org',
    'status' => 'trial',
    'trial_days' => 30,
    'contact_email' => 'admin@example.com',
    'repository_id' => $repoId, // Optional: link to AtoM repository
], $currentUserId);

if ($result['success']) {
    $tenant = $result['tenant'];

    // Assign owner
    TenantService::assignUser($tenant->id, $ownerId, TenantUser::ROLE_OWNER, $currentUserId);
}
```

### Managing Tenant Status

```php
use AhgMultiTenant\Services\TenantService;

// Suspend tenant
TenantService::suspend($tenantId, 'Payment overdue', $adminUserId);

// Activate tenant
TenantService::activate($tenantId, $adminUserId);

// Extend trial by 14 days
TenantService::extendTrial($tenantId, 14, $adminUserId);
```

---

## File Structure

```
ahgMultiTenantPlugin/
├── config/
│   ├── ahgMultiTenantPluginConfiguration.class.php  # Updated for domain routing
│   └── nginx/
│       └── multi-tenant.conf      # NEW in v1.2.0 - Nginx examples
├── database/
│   ├── install.sql
│   └── migrations/
│       └── 001_create_tenant_tables.sql
├── lib/
│   ├── Models/
│   │   ├── Tenant.php
│   │   └── TenantUser.php
│   ├── Filter/
│   │   └── TenantQueryFilter.php
│   └── Services/
│       ├── TenantResolver.php     # NEW in v1.2.0 - Domain resolution
│       ├── TenantService.php
│       ├── TenantContext.php      # Updated for domain routing
│       ├── TenantAccess.php
│       └── TenantBranding.php
├── modules/
│   ├── tenantAdmin/
│   │   ├── actions/actions.class.php
│   │   └── templates/
│   │       ├── indexSuccess.php
│   │       ├── createSuccess.php
│   │       └── editTenantSuccess.php
│   ├── tenantError/               # NEW in v1.2.0 - Error pages
│   │   └── templates/
│   │       ├── unknownTenantSuccess.php
│   │       └── unknownDomainSuccess.php
│   ├── tenantUsers/
│   ├── tenantBranding/
│   └── tenantSwitcher/
└── README.md
```

---

## Security Considerations

1. **Role Validation**: Assignment operations validate assigner's permissions
2. **Owner Protection**: Cannot remove or demote the last owner
3. **Status Enforcement**: Suspended tenants deny all non-admin access
4. **Admin-Only Operations**: Status changes restricted to administrators
5. **Audit Logging**: All operations logged via AhgAuditService if available

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | >= 8.1 |
| AtoM | >= 2.8 |
| MySQL | 8.0+ |
| ahgCorePlugin | >= 1.0.0 |

---

*Part of the AtoM AHG Framework*
