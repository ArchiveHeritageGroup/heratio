# Multi-Tenant System User Guide

**Version:** 1.2.0

Manage multiple organizations (tenants) with isolated user access, domain routing, status control, and custom branding.

---

## Overview

The Multi-Tenant System allows a single AtoM installation to serve multiple organizations with:

- **Isolated Access**: Users only see data from their assigned tenants
- **Status Control**: Tenants can be Active, on Trial, or Suspended
- **Custom Branding**: Per-tenant colors, logos, and CSS
- **Role-Based Access**: Owner, Super User, Editor, Contributor, Viewer roles

```
+---------------------------------------------------------------------+
|                      MULTI-TENANCY SYSTEM v1.1.0                    |
+---------------------------------------------------------------------+
|                                                                     |
|  +-------------+      +---------------+      +-----------------+    |
|  |   ADMIN     |      |    OWNER      |      |  SUPER USER     |    |
|  +-------------+      +---------------+      +-----------------+    |
|        |                     |                       |              |
|        v                     v                       v              |
|   All Tenants          Full Control            Assigned Tenants    |
|   Create/Delete        Assign Users            Assign Users        |
|   Status Control       Manage Settings         Manage Branding     |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## What's New in v1.2.0

- **Domain Routing (Issue #85)**: Automatic tenant resolution from subdomain/custom domain
- **Subdomain Support**: Access tenants via `tenant.heritage.example.com`
- **Custom Domain Support**: Use institutional domains like `archive.institution.org`
- **Unknown Domain Handling**: Friendly error pages for unrecognized domains
- **Tenant Status Management**: Activate, suspend, and manage trial periods
- **Extended Roles**: Owner, Super User, Editor, Contributor, Viewer
- **Admin Dashboard**: Statistics and comprehensive tenant management
- **Trial Management**: Configurable trial periods with extension capability

---

## User Roles

```
+---------------------------------------------------------------------+
|                        USER ROLE HIERARCHY                          |
+---------------------------------------------------------------------+
|                                                                     |
|  1. ADMINISTRATOR (AtoM Admin)                                      |
|     - Full access to all tenants                                    |
|     - Create, edit, suspend, delete tenants                         |
|     - Assign any role including Owner                               |
|     - Manage tenant status and settings                             |
|                                                                     |
|  2. OWNER                                                           |
|     - Full control over their tenant                                |
|     - Assign Super Users and below                                  |
|     - Manage all tenant settings                                    |
|     - Cannot be demoted if last owner                               |
|                                                                     |
|  3. SUPER USER                                                      |
|     - Assign Editors, Contributors, Viewers                         |
|     - Manage tenant branding                                        |
|     - Cannot assign Owners or other Super Users                     |
|                                                                     |
|  4. EDITOR                                                          |
|     - Edit content within tenant                                    |
|     - Cannot manage users                                           |
|                                                                     |
|  5. CONTRIBUTOR                                                     |
|     - Add content within tenant                                     |
|     - Limited editing                                               |
|                                                                     |
|  6. VIEWER                                                          |
|     - Read-only access                                              |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Tenant Status

Tenants can be in one of three states:

| Status | Badge | Description |
|--------|-------|-------------|
| **Active** | Green | Full access to all features |
| **Trial** | Blue | Time-limited access (default 14 days) |
| **Suspended** | Red | No access - users cannot log in |

### Trial Period

- New tenants start with a configurable trial period
- Trial end date is shown in the admin dashboard
- Administrators can extend trials or activate tenants
- Expired trials show an "Expired" badge but remain accessible until suspended

---

## Domain Routing

Tenants can be accessed directly via subdomain or custom domain, without requiring users to manually switch tenants.

### How Domain Resolution Works

```
+-----------------------------------------------------------------------+
|                      DOMAIN RESOLUTION ORDER                           |
+-----------------------------------------------------------------------+
|                                                                        |
|  1. CUSTOM DOMAIN CHECK                                                |
|     archive.institution.org → Match tenant with this domain           |
|                                                                        |
|  2. SUBDOMAIN CHECK                                                    |
|     tenant.heritage.example.com → Extract "tenant" subdomain          |
|                                  → Find tenant with this subdomain    |
|                                                                        |
|  3. SESSION FALLBACK                                                   |
|     Use previously selected tenant from session                        |
|                                                                        |
|  4. UNKNOWN DOMAIN                                                     |
|     Show error page or redirect to main site                           |
|                                                                        |
+-----------------------------------------------------------------------+
```

### Subdomain Access

Access tenants using subdomains of your main domain:

| Subdomain | Resolves To |
|-----------|-------------|
| `national-archives.heritage.example.com` | National Archives tenant |
| `city-library.heritage.example.com` | City Library tenant |
| `museum.heritage.example.com` | Museum tenant |

**Setup (Administrator):**
1. Create wildcard DNS: `*.heritage.example.com → server IP`
2. Obtain wildcard SSL certificate
3. In Admin > Tenants, set the subdomain field for each tenant

### Custom Domain Access

Allow tenants to use their own institutional domains:

| Custom Domain | Resolves To |
|---------------|-------------|
| `archive.nationalarchives.gov` | National Archives tenant |
| `collections.citymuseum.org` | City Museum tenant |

**Setup (Administrator):**
1. Tenant points their domain to your server (DNS)
2. Obtain SSL certificate for the domain
3. Configure nginx server block
4. In Admin > Tenants, set the custom domain field

### Unknown Domain Handling

When a request comes from an unrecognized domain:

**For Unknown Subdomains:**
```
+------------------------------------------+
|         TENANT NOT FOUND                 |
+------------------------------------------+
|                                          |
|  The tenant "unknown-tenant" does not    |
|  exist or may have been removed.         |
|                                          |
|  [Go to Main Site]  [Go Back]            |
+------------------------------------------+
```

**For Unknown Custom Domains:**
```
+------------------------------------------+
|       DOMAIN NOT CONFIGURED              |
+------------------------------------------+
|                                          |
|  This domain is not configured for       |
|  any tenant in our system.               |
|                                          |
|  Contact administrator for setup.        |
|                                          |
|  [Go to Main Site]  [Go Back]            |
+------------------------------------------+
```

---

## Administrator Functions

### Accessing Tenant Administration

1. Log in as an administrator
2. Navigate to **Admin > Tenants** or `/admin/tenants`

### Dashboard Overview

The admin dashboard shows:

```
+------------------------------------------------------------------+
|                    TENANT ADMINISTRATION                          |
+------------------------------------------------------------------+
|                                                                   |
|  [+] Create Tenant                                                |
|                                                                   |
|  +--------+  +--------+  +--------+  +--------+  +--------+       |
|  | TOTAL  |  | ACTIVE |  | TRIAL  |  |SUSPEND |  |EXPIRING|       |
|  |   12   |  |    8   |  |   3    |  |   1    |  |   2    |       |
|  +--------+  +--------+  +--------+  +--------+  +--------+       |
|                                                                   |
|  [Filter: All Status v] [Search...      ] [Search]                |
|                                                                   |
+------------------------------------------------------------------+
```

### Creating a New Tenant

1. Click **Create Tenant** button
2. Fill in the form:

```
+-----------------------------------------------+
|           CREATE TENANT                       |
+-----------------------------------------------+
|                                               |
|  Tenant Name: [My Organization           ]    |
|  Code:        [my-organization           ]    |  (auto-generated)
|  Domain:      [myorg.example.com         ]    |  (optional)
|  Subdomain:   [myorg                     ]    |  (optional)
|                                               |
|  Link to Repository: [Select...         v]   |
|  Initial Status:     [Trial             v]   |
|  Trial Period:       [14] days                |
|                                               |
|  Contact Name:  [John Smith              ]    |
|  Contact Email: [john@example.com        ]    |
|                                               |
|  Assign Owner:  [Select User...         v]   |
|                                               |
|  [Cancel]                    [Create Tenant]  |
+-----------------------------------------------+
```

3. Click **Create Tenant**

### Managing Tenant Status

From the tenant list, use the action buttons:

| Button | Action |
|--------|--------|
| Green Check | Activate tenant (end trial/unsuspend) |
| Clock | Extend trial period |
| Yellow Ban | Suspend tenant |
| Trash | Delete tenant |

### Suspending a Tenant

1. Click the yellow ban icon next to the tenant
2. Enter an optional reason for suspension
3. Click **Suspend Tenant**

Users of a suspended tenant will no longer be able to access the system.

### Extending a Trial

1. Click the clock icon next to a trial tenant
2. Enter the number of additional days
3. Click **Extend Trial**

### Editing a Tenant

1. Click the edit icon next to a tenant
2. Modify the tenant details
3. Manage users in the right panel:
   - Change roles using the dropdown
   - Remove users with the X button
   - Add users using the form at the bottom
4. Click **Save Changes**

---

## Managing Tenant Users

### Adding a User to a Tenant

1. Navigate to the tenant edit page
2. In the "Add User" section:
   - Select a user from the dropdown
   - Select their role
   - Click **Add**

### Changing a User's Role

1. Navigate to the tenant edit page
2. Find the user in the list
3. Use the role dropdown to change their role
4. The change is saved automatically

### Removing a User

1. Navigate to the tenant edit page
2. Click the X button next to the user
3. Confirm the removal

**Note:** You cannot remove the last Owner from a tenant.

---

## Branding Your Tenant

Super Users and above can customize the appearance of their tenant.

### Accessing Branding Settings

1. Use the Tenant Switcher dropdown
2. Click **Branding**
3. Or navigate to `/tenant/{id}/branding`

### Logo Upload

1. Click **Choose File** in the Logo section
2. Select an image file
3. Click **Upload**

**Supported formats:** PNG, JPEG, GIF, SVG, WebP
**Maximum size:** 2MB

### Color Configuration

| Setting | Description |
|---------|-------------|
| Primary Color | Main brand color |
| Secondary Color | Secondary brand color |
| Header Background | Navigation bar background |
| Header Text | Navigation text color |
| Link Color | Text link color |
| Button Color | Action button color |

### Custom CSS

For advanced customization:

```css
/* Example custom CSS */
.tenant-header {
  border-bottom: 3px solid var(--tenant-primary-color);
}
```

**Maximum:** 10,000 characters

### Save and Preview

1. Review changes in the preview section
2. Click **Save Branding**

---

## Switching Between Tenants

### Using the Tenant Switcher

1. Look for the building icon in the navigation bar
2. Click to open the dropdown
3. Select a tenant to switch

```
+---------------------------------------+
|  [Building] My Organization      v    |
+---------------------------------------+
|  [Globe] All Tenants                  |  <-- Admin only
|  ------------------------------------ |
|  [Star] My Organization               |  <-- Current
|  [Building] Partner Archive           |
|  [Building] City Library              |
|  ------------------------------------ |
|  [Cog] Manage Tenants                 |  <-- Admin only
|  [Users] Manage Users                 |
|  [Palette] Branding                   |
+---------------------------------------+
```

### View All Mode (Administrators Only)

Administrators can view all records across tenants by selecting **All Tenants**.

---

## URL Reference

| Function | URL |
|----------|-----|
| Tenant Dashboard | `/admin/tenants` |
| Create Tenant | `/admin/tenants/create` |
| Edit Tenant | `/admin/tenants/{id}/edit-tenant` |
| User Management (Legacy) | `/tenant/{id}/users` |
| Branding | `/tenant/{id}/branding` |
| Switch Tenant | `/tenant/switch/{id}` |
| View All (Admin) | `/tenant/switch/all` |

### Domain-Based Access

| Access Method | URL Pattern |
|--------------|-------------|
| Subdomain Access | `https://{subdomain}.heritage.example.com/` |
| Custom Domain | `https://archive.institution.org/` |
| Main Site | `https://heritage.example.com/` |

---

## Common Tasks Quick Reference

### For Administrators

| Task | Steps |
|------|-------|
| Create tenant | Admin > Tenants > Create Tenant |
| Activate tenant | Admin > Tenants > Green check icon |
| Suspend tenant | Admin > Tenants > Yellow ban icon |
| Extend trial | Admin > Tenants > Clock icon |
| Delete tenant | Admin > Tenants > Trash icon |
| Assign owner | Edit Tenant > Add User > Role: Owner |

### For Owners/Super Users

| Task | Steps |
|------|-------|
| Add user | Edit Tenant > Add User section |
| Change role | Edit Tenant > User list > Role dropdown |
| Remove user | Edit Tenant > User list > X button |
| Update branding | Tenant Switcher > Branding |
| Upload logo | Branding > Logo section > Upload |

### For All Users

| Task | Steps |
|------|-------|
| Switch tenant | Tenant Switcher dropdown > Select |
| View current tenant | Check navigation bar |

---

## Troubleshooting

### Cannot access tenant

1. Your tenant may be suspended - contact administrator
2. Your trial may have expired - contact administrator
3. You may not be assigned - contact your tenant owner

### Cannot see other users to assign

1. Only active users appear in the list
2. Users already assigned to the tenant won't appear

### Branding not appearing

1. Clear browser cache
2. Hard refresh (Ctrl+Shift+R)
3. Wait a few seconds for regeneration

### Cannot delete tenant

1. Remove all users first
2. Only administrators can delete tenants

---

## Need Help?

Contact your system administrator for assistance.

---

*Part of the AtoM AHG Framework*
