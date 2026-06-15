> Heratio Help Center article. Category: Administration / Security.

# Access Control (ACL), Clearances and Security Audit

Heratio's access control layer governs who can read, create, edit, delete, publish and translate the records in your archive, and who may see materials that carry a security classification. It is built around ACL groups (roles), per-entity permission editors, security clearance levels, an access-request workflow with designated approvers, and a security audit log. Administrators reach everything from the group list at **Admin -> ACL** (`/admin/acl`); registered users reach their own clearance and requests from the **My Requests** page (`/security/my-requests`). The feature is provided by the `ahg-acl` package and all permissions are stored centrally in the `acl_permission` table.

## Overview

Access in Heratio is decided by ACL groups and, where finer control is needed, by permissions attached directly to a single user. Each permission row records a group (or user), an action (such as `read` or `update`), an optional object scope, and a grant-or-deny flag. When a permission has no object scope it applies to the whole class of records; when it carries an object scope it applies only to that record, and object-specific permissions win over class-wide ones.

On top of plain permissions, the package adds a security classification model. Records can be assigned a classification level, users can be granted a clearance level, and access to classified materials can be requested, reviewed and approved through a workflow. Security-relevant events are written to the security audit log, which can be filtered, paged and exported.

The four pillars are:

| Pillar | What it controls | Where |
|---|---|---|
| ACL groups | Role-based permissions per entity type and per record | `/admin/acl` |
| Clearances | A user's security clearance level | `/admin/acl/clearances` |
| Access requests | Workflow for requesting access to classified materials | `/admin/acl/access-requests`, `/security/my-requests` |
| Security audit | Log of security events | `/admin/acl/audit-log` |

## Key features

- **ACL group management.** List groups with member counts, edit a group's profile (name, description, translate flag), and add or remove members.
- **Per-entity permission editors.** Each group has four tabbed editors for Archival Description, Authority Record, Archival Institution and Taxonomy, each exposing the actions relevant to that entity type.
- **Per-user permission editors.** The same four entity editors are also available directly on a single user, for cases where a person needs an exception to their group's permissions.
- **Three-state permissions.** Every action can be set to Grant, Deny or Inherit. Inherit removes the explicit row so the group's or class's default applies.
- **Scoped permissions.** A permission can be class-wide (root), scoped to all records in one archival institution, or scoped to a single record by slug.
- **Term and translate permission matrices.** Compact grid pages let you tick taxonomy CRUD permissions per group, and tick which locales each group may translate into.
- **Security classification levels.** Configurable levels (with code, level number, colour, and flags for justification, approval, two-factor and watermarking) classify both records and user clearances.
- **Clearance management.** Grant, update, bulk-grant or revoke a user's security clearance, with every change logged.
- **Access-request workflow.** Users submit access requests; designated approvers review, approve or deny them; statistics summarise pending, approved and denied counts.
- **Approver roster.** Maintain the list of users who may approve requests, each bounded by a minimum and maximum classification level and an email-notification flag.
- **Security audit log.** A filterable, paginated log of security events with CSV and JSON export.
- **Watermark settings.** Configure default watermarking behaviour for digital objects, including per-view and per-download application and custom watermark uploads.

## How to use

### Manage ACL groups

1. Go to **Admin -> ACL** (`/admin/acl`). The page lists every group with its member count and a count of its permissions.
2. Click a group to open its **Profile** tab (`/admin/acl/group/{id}`). Here you can edit the group name and description, and toggle the group-level translate flag.
3. Use the tabs across the top of the group editor to move between the entity-specific permission editors:
   - **Profile** - name, description, translate flag, and member add/remove.
   - **Archival Description** - permissions on archival descriptions (`/admin/acl/group/{id}/information-object-acl`).
   - **Authority Record** - permissions on authority records (`/admin/acl/group/{id}/actor-acl`).
   - **Archival Institution** - permissions on archival institutions (`/admin/acl/group/{id}/repository-acl`).
   - **Taxonomy** - permissions on taxonomy terms (`/admin/acl/group/{id}/term-acl`).

### Add or remove group members

1. Open a group's **Profile** tab.
2. To add a member, choose a user from the member dropdown and submit. The user is added to the group; duplicate memberships are silently ignored.
3. To remove a member, use the remove control next to the member's name.

### Set permissions per entity

1. Open the relevant entity tab for the group (or for a single user, see below).
2. For each action, choose one of three states:
   - **Grant** - allow this action.
   - **Deny** - explicitly block this action.
   - **Inherit** - remove any explicit rule so the default applies.
3. Scope the permission as needed:
   - **Root** - the whole class of records (no object scope).
   - **Per archival institution** - all records under one institution (stored against the institution slug).
   - **Per record** - one specific record, selected by its slug.
4. Save. The editor writes the changes straight to `acl_permission`.

The actions available depend on the entity type:

| Entity tab | Available actions |
|---|---|
| Archival Description | Read, Create, Update, Delete, View draft, Publish, Access master, Access reference, Access thumbnail |
| Authority Record | Read, Create, Update, Delete, View draft, Publish, Access master, Access reference, Access thumbnail |
| Archival Institution | Read, Create, Update, Delete |
| Taxonomy | Create, Update, Delete |

### Set permissions per user

The same four entity editors can be applied to an individual user, which is useful for granting an exception without changing a whole group. These pages are part of the user management area:

- Archival Description: `/user/{slug}/editInformationObjectAcl`
- Authority Record: `/user/{slug}/editActorAcl`
- Archival Institution: `/user/{slug}/editRepositoryAcl`
- Taxonomy: `/user/{slug}/editTermAcl`

Each page behaves like the group editor (Grant / Deny / Inherit, root or scoped), but the resulting permission is attached to the user instead of a group.

### Use the term and translate permission matrices

Two matrix pages give a quick, grid-based way to manage common permissions across many groups at once:

1. **Term permissions** (`/admin/term-permissions`). Rows are ACL groups, columns are the taxonomies in your install, and each cell is one of Create, View, Update or Delete. Ticking a cell grants that permission; clearing it removes the grant. Changes save immediately as you toggle each cell.
2. **Translate permissions** (`/admin/translate-permissions`). Rows are ACL groups, columns are the enabled locales. Ticking a cell grants that group permission to translate content into that locale. The enabled locale list comes from the `i18n_languages` setting; if that is not configured, a built-in default list of common locales is used.

### Manage security classifications and clearances

1. **Classification levels** (`/admin/acl/classifications`) lists the active classification levels and their attributes (code, level number, colour, and the justification, approval, two-factor and watermark flags).
2. **Clearances** (`/admin/acl/clearances`) shows every user with their current clearance, if any, plus summary statistics (total users, users with a clearance, and how many hold a high-level clearance).
   - To set or change one user's clearance, choose a classification level for that user and save.
   - To grant the same clearance to many users at once, use the bulk-grant control: select the users, choose the classification level, and submit.
   - To revoke a user's clearance, use the revoke action, which deletes their clearance row and writes a `clearance_revoked` entry to the clearance log.

### Process access requests

1. Users submit access requests for classified materials and can review their own status at **My Requests** (`/security/my-requests`), which shows their current clearance, any object-level access grants, and the history of their requests.
2. Approvers review pending requests at **Access Requests** (`/admin/acl/access-requests`). The page lists requests by status and adds summary cards for total, pending, approved and denied counts, plus a paged history drawn from the request log.
3. To decide a request, open it and choose **Approved** or **Denied**, optionally adding review notes. The decision, reviewer and timestamp are recorded.

### Maintain the approver roster

1. Go to **Approvers** (`/admin/acl/approvers`, also reachable at `/admin/approvers`). The page lists active approvers with their clearance and the classification range they may approve.
2. To add an approver, choose a user, set the minimum and maximum classification levels they may approve, and choose whether they receive email notifications. A user who is already an active approver cannot be added twice.
3. To remove an approver, use the remove action; the approver is deactivated (their row is marked inactive rather than deleted).

### Review the security audit log

1. Go to **Audit log** (`/admin/acl/audit-log`). The log lists security events newest-first.
2. Filter by action, object (entity) type, username, and a from/to date range. Choose how many rows per page (25, 50, 100 or 250).
3. To export, set the format to CSV or JSON; the export returns all rows matching the current filters (up to a safety cap) as a downloadable file.

There is also a separate **Security audit** area (`/admin/acl/security-audit`) with an index, a dashboard summarising events over a chosen period (for example the last 30 days), and an object-access view.

### Configure watermarking

1. Go to **Watermark settings** (`/admin/acl/watermark-settings`).
2. Set whether watermarking is enabled by default, the default watermark type, whether watermarks are applied on view and on download, whether security classification overrides the default, and the minimum image size to watermark.
3. To add a custom watermark, upload an image with a name, position and opacity. Custom watermarks can also be deleted from this page.

Watermark settings are stored and applied by the media-processing layer, and saving the page refreshes the image-server cache so changes take effect for newly served images.

## Configuration

The package itself has no dedicated config file. The behaviour you configure lives in data and in shared settings:

- **Permissions** are stored in the `acl_permission` table. Each row carries a group or user, an action, an optional object scope, a grant/deny flag, and optional `constants` (used to record repository or language scope).
- **Groups and membership** live in `acl_group`, `acl_group_i18n` and `acl_user_group`. Groups with an id of 99 or below are treated as system groups; assignable application groups have ids above 99.
- **Classification levels** live in `security_classification`, with per-level flags for whether justification, approval or two-factor verification is required, and whether watermarking, download, print and copy are allowed.
- **Clearances** live in `user_security_clearance`, with changes logged to `user_security_clearance_log`.
- **Access requests** use `security_access_request` (plus the request log tables), and approvers use `access_request_approver`.
- **Enabled locales** for the translate-permission matrix come from the `i18n_languages` setting; if it is absent, a built-in default locale list is used.
- **Watermark defaults** are stored as settings read and written by the media-processing watermark service (for example `default_watermark_enabled`, `default_watermark_type`, `apply_watermark_on_view`, `apply_watermark_on_download`, `security_watermark_override` and `watermark_min_size`).

All administrative ACL pages are protected by the `admin` middleware; the self-service security pages (my requests, submitting a request, two-factor setup) require an authenticated session. Several legacy URL aliases (for example `/aclGroup`, `/admin/termPermission` and `/security/audit`) resolve to the current pages so older bookmarks continue to work.

## References

- Source: packages/ahg-acl/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/541
