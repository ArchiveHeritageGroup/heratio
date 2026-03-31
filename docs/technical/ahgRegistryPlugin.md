# ahgRegistryPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Community & Registry
**Dependencies:** atom-framework (>=2.8.0), ahgCorePlugin
**License:** GPL-3.0

---

## 1. Overview

The ahgRegistryPlugin implements a complete GLAM Community Hub and Registry -- a standalone or Heratio-integrated directory for institutions, vendors, software products, user groups, threaded discussions, blog, newsletters, reviews, and a sync API. It serves as the central community platform for the AtoM/Heratio ecosystem.

### Purpose

The registry provides:

- **Institution Directory** -- GLAM institutions worldwide with detailed profiles, collection metadata, geolocation, and verification
- **Vendor Directory** -- Service providers (developers, hosting, digitization, consulting) with multi-type support and client tracking
- **Software Catalog** -- Archival and GLAM software with release management, git integration (GitHub/GitLab), and download tracking
- **Community Hub** -- User groups, threaded discussions with nested replies, blog with publishing workflow, and newsletters with SMTP delivery
- **Sync API** -- Token-authenticated heartbeat protocol for remote Heratio/AtoM instances to register and report status
- **OAuth Login** -- Social login via Google, Facebook, GitHub, LinkedIn, Microsoft
- **CRM Features** -- Vendor call log, client relationship tracking, review/rating aggregation

### Standalone Mode

The plugin operates in standalone mode with its own layout (`layout_registry.php`), navbar, footer, CSS, and authentication. It does not require the full AtoM theme stack. All templates use `decorate_with()` to bind to the standalone layout, which includes:

- Bootstrap 5.3.3 (CDN)
- Font Awesome 6.5.1 (CDN)
- Source Sans 3 (Google Fonts)
- Custom CSS variables for Heratio-branded theming
- Responsive navbar with search, auth dropdown, and admin links
- Configurable footer with columns loaded from `registry_settings`

### Architecture

```
+------------------------------------------------------------------+
|                       ahgRegistryPlugin                           |
+------------------------------------------------------------------+
|                                                                    |
|  config/                                                           |
|    ahgRegistryPluginConfiguration.class.php                        |
|      - SPL autoloader for AhgRegistry\ namespace                  |
|      - ~115 route definitions via RouteLoader                      |
|      - Module enablement                                           |
|                                                                    |
|  modules/registry/                                                 |
|    actions/actions.class.php  (4400 lines, ~100 action methods)    |
|    templates/                 (~100 template files)                 |
|    config/module.yml                                               |
|                                                                    |
|  lib/Services/   (17 service classes)                              |
|  lib/Repositories/ (13 repository classes)                         |
|                                                                    |
|  database/install.sql (24 tables + seed data)                      |
|  css/registry.css                                                  |
|  js/registry-map.js, registry-discussions.js                       |
+------------------------------------------------------------------+
```

### Key Technical Decisions

1. **Single actions class** -- All ~100 action methods are in one `registryActions` class extending `AhgController`. This is a pragmatic choice for a plugin-sized codebase where Symfony 1.x routing maps module/action pairs.

2. **Namespace autoloading** -- The plugin registers its own SPL autoloader in the configuration class for the `AhgRegistry\` namespace, mapping to `lib/Services/` and `lib/Repositories/`.

3. **Service lazy loading** -- The `loadService()` helper uses `require_once` to load all repository files, then the requested service file. This is necessary because Symfony 1.x does not autoload namespaced plugin classes reliably.

4. **Laravel Query Builder exclusively** -- All database operations use `Illuminate\Database\Capsule\Manager as DB`. No raw PDO or Propel.

5. **FULLTEXT + LIKE fallback** -- All search operations attempt MySQL FULLTEXT first (`MATCH ... AGAINST ... IN BOOLEAN MODE`), falling back to `LIKE '%term%'` when FULLTEXT returns zero results. This handles short queries and stopwords gracefully.

---

## 2. Installation

### Prerequisites

- AtoM 2.8+ with atom-framework >= 2.8.0
- ahgCorePlugin enabled
- PHP >= 8.1
- MySQL 8.0 (FULLTEXT indexes, JSON columns)

### Steps

```bash
# 1. Plugin is already in atom-ahg-plugins
PLUGIN_DIR="/usr/share/nginx/archive/atom-ahg-plugins/ahgRegistryPlugin"

# 2. Create symlink
ln -sf $PLUGIN_DIR /usr/share/nginx/archive/plugins/ahgRegistryPlugin

# 3. Install database schema (24 tables + seed data)
mysql -u root archive < $PLUGIN_DIR/database/install.sql

# 4. Enable in atom_plugin table
mysql -u root archive -e "
INSERT INTO atom_plugin (name, class_name, version, description, category, is_enabled, load_order)
VALUES ('ahgRegistryPlugin', 'ahgRegistryPluginConfiguration', '1.0.0',
        'GLAM Community Hub & Registry', 'community', 1, 100)
ON DUPLICATE KEY UPDATE is_enabled = 1;
"

# 5. Clear cache
rm -rf /usr/share/nginx/archive/cache/*
php symfony cc
sudo systemctl restart php8.3-fpm
```

### Nginx Configuration

For standalone deployment at a subdomain (e.g., `registry.theahg.co.za`), configure nginx to route `/registry/` paths to the AtoM application. The plugin's routes all begin with `/registry/` so they integrate cleanly with the standard AtoM nginx config without conflicts.

### Seed Data

The `install.sql` seeds the `registry_settings` table with ~40 default settings covering:

- Site branding (`registry_name`, footer text, footer columns as JSON)
- Moderation (`moderation_enabled`, `allow_self_registration`)
- Map defaults (centered on South Africa: lat -30.5595, lng 22.9375, zoom 5)
- Upload limits (`max_upload_size_mb`, `max_attachment_size_mb`, allowed extensions)
- OAuth provider configs (Google, Facebook, GitHub -- disabled by default)
- SMTP email settings (disabled by default)
- Discussion/blog moderation flags

---

## 3. Directory Structure

```
ahgRegistryPlugin/
  config/
    ahgRegistryPluginConfiguration.class.php   -- Routes, autoloader, module enablement
  database/
    install.sql                                 -- 24 tables + seed data (registry_settings)
  lib/
    Repositories/                               -- 13 thin repository classes
      AttachmentRepository.php
      BlogRepository.php
      ContactRepository.php
      DiscussionRepository.php
      InstanceRepository.php
      InstitutionRepository.php
      RelationshipRepository.php
      ReviewRepository.php
      SoftwareRepository.php
      SyncRepository.php
      TagRepository.php
      UserGroupRepository.php
      VendorRepository.php
    Services/                                   -- 17 service classes
      AttachmentService.php
      BlogService.php
      ContactService.php
      DiscussionService.php
      GitIntegrationService.php
      InstanceService.php
      InstitutionService.php
      NewsletterService.php
      OAuthService.php
      RegistryImportService.php
      RegistrySearchService.php
      RelationshipService.php
      ReviewService.php
      SoftwareService.php
      SyncService.php
      UserGroupService.php
      VendorService.php
  modules/
    registry/
      actions/
        actions.class.php                       -- ~4400 lines, ~100 execute methods
      config/
        module.yml                              -- is_internal: false
      templates/
        layout_registry.php                     -- Standalone HTML layout (navbar + footer)
        indexSuccess.php                         -- Homepage with stats + featured items
        searchSuccess.php                        -- Unified search results
        mapSuccess.php                           -- Leaflet map of institutions
        communitySuccess.php                     -- Community hub landing
        loginSuccess.php                         -- Login form (AtoM auth + OAuth)
        registerSuccess.php                      -- User registration
        institutionBrowseSuccess.php             -- Institution browse/filter
        institutionViewSuccess.php               -- Institution detail view
        institutionEditSuccess.php               -- Institution edit form
        institutionRegisterSuccess.php           -- Self-registration form
        vendorBrowseSuccess.php                  -- Vendor browse/filter
        vendorViewSuccess.php                    -- Vendor detail view
        vendorEditSuccess.php                    -- Vendor edit form
        vendorRegisterSuccess.php                -- Vendor self-registration
        softwareBrowseSuccess.php                -- Software catalog browse
        softwareViewSuccess.php                  -- Software detail view
        softwareReleasesSuccess.php              -- Release history
        softwareComponentsSuccess.php            -- Software components/plugins list
        softwareComponentAddSuccess.php          -- Add component form
        groupBrowseSuccess.php                   -- User group browse
        groupViewSuccess.php                     -- Group detail with discussions
        groupEditSuccess.php                     -- Group edit form
        groupCreateSuccess.php                   -- Group creation form
        groupMembersSuccess.php                  -- Public member list
        groupMembersManageSuccess.php            -- Self-service member management
        discussionListSuccess.php                -- Group discussions list
        discussionViewSuccess.php                -- Discussion with replies
        discussionNewSuccess.php                 -- New discussion form
        discussionReplySuccess.php               -- Reply form
        blogListSuccess.php                      -- Blog browse
        blogViewSuccess.php                      -- Blog post detail
        blogFormSuccess.php                      -- Blog post editor
        instanceViewSuccess.php                  -- Instance detail view
        instanceFormSuccess.php                  -- Instance form
        instancesManageSuccess.php               -- Manage instances
        contactsManageSuccess.php                -- Manage contacts
        contactFormSuccess.php                   -- Contact form
        reviewFormSuccess.php                    -- Review/rating form
        myInstitutionDashboardSuccess.php        -- Self-service institution dashboard
        myVendorDashboardSuccess.php             -- Self-service vendor dashboard
        myVendorCallLogSuccess.php               -- CRM call log list
        myVendorCallLogViewSuccess.php           -- Call log entry detail
        vendorCallLogFormSuccess.php             -- Call log form
        vendorClientsSuccess.php                 -- Vendor client list
        vendorClientFormSuccess.php              -- Add client relationship
        vendorSoftwareManageSuccess.php          -- Vendor software management
        vendorSoftwareFormSuccess.php            -- Software add/edit form
        vendorSoftwareUploadSuccess.php          -- Software upload form
        vendorReleaseManageSuccess.php           -- Release management
        vendorReleaseFormSuccess.php             -- Release form
        myGroupsSuccess.php                      -- My group memberships
        myBlogSuccess.php                        -- My blog posts
        myFavoritesSuccess.php                   -- User favorites
        newsletterSubscribeSuccess.php           -- Subscribe form
        newsletterUnsubscribeSuccess.php         -- Unsubscribe confirmation
        newsletterBrowseSuccess.php              -- Newsletter archive
        newsletterViewSuccess.php                -- Newsletter detail
        institutionSoftwareSuccess.php           -- Institution software assignments
        institutionVendorsSuccess.php            -- Institution vendor relationships
        adminDashboardSuccess.php                -- Admin main dashboard
        adminInstitutionsSuccess.php             -- Admin institution list
        adminVendorsSuccess.php                  -- Admin vendor list
        adminSoftwareSuccess.php                 -- Admin software list
        adminGroupsSuccess.php                   -- Admin group list
        adminGroupEditSuccess.php                -- Admin group edit
        adminGroupMembersSuccess.php             -- Admin group members
        adminDiscussionsSuccess.php              -- Admin discussion moderation
        adminBlogSuccess.php                     -- Admin blog moderation
        adminReviewsSuccess.php                  -- Admin review moderation
        adminSyncSuccess.php                     -- Admin sync dashboard
        adminSettingsSuccess.php                 -- Admin settings
        adminFooterSuccess.php                   -- Admin footer editor
        adminEmailSuccess.php                    -- Admin SMTP configuration
        adminImportSuccess.php                   -- Admin WordPress import
        adminUsersSuccess.php                    -- Admin user management
        adminNewslettersSuccess.php              -- Admin newsletter list
        adminNewsletterFormSuccess.php           -- Newsletter compose/edit
        adminSubscribersSuccess.php              -- Subscriber management
        _breadcrumb.php                          -- Breadcrumb partial
        _vendorCard.php                          -- Vendor card partial
        _institutionCard.php                     -- Institution card partial
        _softwareCard.php                        -- Software card partial
        _groupCard.php                           -- Group card partial
        _blogCard.php                            -- Blog post card partial
        _ratingStars.php                         -- Star rating display
        _filterSidebar.php                       -- Filter sidebar partial
        _contactList.php                         -- Contact list partial
        _instanceList.php                        -- Instance list partial
        _discussionRow.php                       -- Discussion row partial
        _replyThread.php                         -- Nested reply thread partial
        _attachmentList.php                      -- Attachment list partial
        _attachmentUpload.php                    -- File upload partial
  css/
    registry.css                                 -- Custom styles with CSS variables
  js/
    registry-map.js                              -- Leaflet map initialization
    registry-discussions.js                      -- Discussion interaction JS
  extension.json                                 -- Plugin metadata
```

---

## 4. Database Schema

The plugin uses 24 tables (the install.sql comment says 18, but additional tables were added). All tables use `InnoDB`, `utf8mb4_unicode_ci`, and `BIGINT UNSIGNED AUTO_INCREMENT` primary keys.

### 4.1 registry_institution

Core institution profiles for GLAM organizations.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED PK | Auto-increment |
| `name` | VARCHAR(255) NOT NULL | Institution name |
| `slug` | VARCHAR(255) UNIQUE | URL-safe slug |
| `institution_type` | ENUM | archive, library, museum, gallery, dam, heritage_site, research_centre, government, university, other |
| `glam_sectors` | JSON | Multi-select GLAM sectors |
| `description` | TEXT | Full description |
| `short_description` | VARCHAR(500) | Card excerpt |
| `logo_path` | VARCHAR(500) | Logo file path |
| `banner_path` | VARCHAR(500) | Banner image path |
| `website` | VARCHAR(255) | Website URL |
| `email` | VARCHAR(255) | Contact email |
| `phone` | VARCHAR(100) | Phone number |
| `fax` | VARCHAR(100) | Fax number |
| `street_address` | TEXT | Street address |
| `city` | VARCHAR(100) | City |
| `province_state` | VARCHAR(100) | Province/state |
| `postal_code` | VARCHAR(20) | Postal code |
| `country` | VARCHAR(100) | Country name |
| `latitude` | DECIMAL(10,7) | Map latitude |
| `longitude` | DECIMAL(10,7) | Map longitude |
| `size` | ENUM | small, medium, large, national |
| `governance` | ENUM | public, private, ngo, academic, government, tribal, community |
| `parent_body` | VARCHAR(255) | Parent organization |
| `established_year` | INT | Year founded |
| `accreditation` | VARCHAR(255) | Accreditation info |
| `collection_summary` | TEXT | Holdings summary |
| `collection_strengths` | JSON | Strengths array |
| `total_holdings` | VARCHAR(100) | Holdings count |
| `digitization_percentage` | INT | % digitized |
| `descriptive_standards` | JSON | Standards array (ISAD(G), DACS, etc.) |
| `management_system` | VARCHAR(100) | Current IMS |
| `uses_atom` | TINYINT(1) | Uses AtoM flag |
| `open_to_public` | TINYINT(1) | Public access |
| `institution_url` | VARCHAR(500) | Main website (separate from AtoM URL) |
| `is_verified` | TINYINT(1) | Admin verified |
| `is_featured` | TINYINT(1) | Featured on homepage |
| `is_active` | TINYINT(1) | Soft delete flag |
| `verification_notes` | TEXT | Admin notes |
| `verified_at` | DATETIME | Verification timestamp |
| `verified_by` | INT | Verifying user ID |
| `created_by` | INT | Creator user ID |
| `created_at` / `updated_at` | DATETIME | Timestamps |

**Indexes:** `institution_type`, `country`, `is_active`, FULLTEXT on `(name, description, collection_summary)`

### 4.2 registry_vendor

Vendor/service provider profiles.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED PK | Auto-increment |
| `name` | VARCHAR(255) NOT NULL | Vendor name |
| `slug` | VARCHAR(255) UNIQUE | URL-safe slug |
| `vendor_type` | ENUM | developer, integrator, consultant, service_provider, hosting, digitization, training, other |
| `specializations` | JSON | Specialization areas |
| `description` | TEXT | Full description |
| `short_description` | VARCHAR(500) | Card excerpt |
| `logo_path` / `banner_path` | VARCHAR(500) | Image paths |
| `website` / `email` / `phone` | VARCHAR | Contact info |
| `street_address` through `country` | Various | Address fields |
| `company_registration` | VARCHAR(100) | Company reg number |
| `vat_number` | VARCHAR(50) | VAT/tax number |
| `established_year` | INT | Year founded |
| `team_size` | ENUM | solo, 2-5, 6-20, 21-50, 50+ |
| `service_regions` | JSON | Geographic regions served |
| `languages` | JSON | Languages supported |
| `certifications` | JSON | Professional certifications |
| `github_url` / `gitlab_url` / `linkedin_url` | VARCHAR(255) | Social/dev links |
| `is_verified` / `is_featured` / `is_active` | TINYINT(1) | Status flags |
| `client_count` | INT | Calculated client count |
| `average_rating` | DECIMAL(3,2) | Aggregated rating |
| `rating_count` | INT | Number of reviews |
| `created_by` | INT | Creator user ID |

**Indexes:** `vendor_type`, `country`, `is_active`, FULLTEXT on `(name, description)`

**Important:** `vendor_type` is defined as ENUM in the schema but the VendorService treats it as a JSON array for multi-select filtering using `JSON_CONTAINS()`. This dual-nature is a known pattern -- the service layer encodes/decodes JSON while the column stores the raw value.

### 4.3 registry_contact (Polymorphic)

Contacts for institutions and vendors.

| Column | Type | Description |
|--------|------|-------------|
| `entity_type` | ENUM('institution','vendor') | Owner type |
| `entity_id` | BIGINT UNSIGNED | Owner ID |
| `first_name` / `last_name` | VARCHAR | Name |
| `email` / `phone` / `mobile` | VARCHAR | Contact methods |
| `job_title` / `department` | VARCHAR | Role info |
| `roles` | JSON | Additional roles |
| `is_primary` | TINYINT(1) | Primary contact flag |
| `is_public` | TINYINT(1) | Public visibility |

**Index:** `(entity_type, entity_id)`

### 4.4 registry_instance

Heratio/AtoM instance tracking with sync support.

| Column | Type | Description |
|--------|------|-------------|
| `institution_id` | BIGINT UNSIGNED NOT NULL | FK to institution |
| `name` | VARCHAR(255) | Instance name |
| `url` | VARCHAR(500) | Instance URL |
| `instance_type` | ENUM | production, staging, development, demo, offline |
| `software` | VARCHAR(100) | Software name (default: 'heratio') |
| `software_version` | VARCHAR(50) | Current version |
| `hosting` | ENUM | self_hosted, cloud, vendor_hosted, saas |
| `hosting_vendor_id` | BIGINT UNSIGNED | FK to vendor (hosting) |
| `maintained_by_vendor_id` | BIGINT UNSIGNED | FK to vendor (maintenance) |
| `sync_token` | VARCHAR(64) | SHA-256 sync token |
| `sync_enabled` | TINYINT(1) | Sync active flag |
| `last_sync_at` / `last_heartbeat_at` | DATETIME | Sync timestamps |
| `sync_data` | JSON | Arbitrary sync payload |
| `status` | ENUM | online, offline, maintenance, decommissioned |
| `record_count` / `digital_object_count` | INT | Instance metrics |
| `storage_gb` | DECIMAL(10,2) | Storage usage |
| `os_environment` | VARCHAR(100) | e.g., "Ubuntu 20.04.6 LTS" |
| `languages` | JSON | Interface languages |
| `descriptive_standard` | VARCHAR(100) | RAD, ISAD(G), DACS, etc. |
| `feature_usage` | JSON | Feature flags (e.g., `{"accessions": true}`) |
| `feature_notes` | JSON | Notes per feature |

### 4.5 registry_software

Software catalog entries with git integration.

| Column | Type | Description |
|--------|------|-------------|
| `vendor_id` | BIGINT UNSIGNED | FK to vendor |
| `category` | ENUM | ams, ims, dam, dams, cms, glam, preservation, digitization, discovery, utility, plugin, integration, theme, other |
| `git_provider` | ENUM | github, gitlab, bitbucket, self_hosted, none |
| `git_url` | VARCHAR(500) | Repository URL |
| `git_default_branch` / `git_latest_tag` / `git_latest_commit` | VARCHAR | Git metadata |
| `git_is_public` | TINYINT(1) | Public repo flag |
| `git_api_token_encrypted` | VARCHAR(500) | Encrypted API token |
| `is_internal` | TINYINT(1) | Internally uploaded (not from git) |
| `upload_path` / `upload_filename` / `upload_size_bytes` / `upload_checksum` | Various | Upload metadata |
| `license` / `license_url` | VARCHAR | License info |
| `latest_version` | VARCHAR(50) | Current version |
| `supported_platforms` / `glam_sectors` / `standards_supported` / `languages` | JSON | Multi-select fields |
| `min_php_version` / `min_mysql_version` | VARCHAR | Requirements |
| `pricing_model` | ENUM | free, open_source, freemium, subscription, one_time, contact |
| `institution_count` | INT | Calculated usage count |
| `average_rating` / `rating_count` / `download_count` | Various | Aggregated metrics |

### 4.6 registry_software_release

Version history for software products.

| Column | Type | Description |
|--------|------|-------------|
| `software_id` | BIGINT UNSIGNED | FK to software |
| `version` | VARCHAR(50) | Semver string |
| `release_type` | ENUM | major, minor, patch, beta, rc, alpha |
| `release_notes` | TEXT | Changelog |
| `git_tag` / `git_commit` / `git_compare_url` | VARCHAR | Git references |
| `file_path` / `file_name` / `file_size_bytes` / `file_checksum` | Various | Download file |
| `download_count` | INT | Download counter |
| `is_stable` / `is_latest` | TINYINT(1) | Status flags |
| `released_at` | DATETIME | Release date |

**Unique:** `(software_id, version)`

### 4.7 registry_vendor_institution

Many-to-many vendor-institution relationships.

| Column | Type | Description |
|--------|------|-------------|
| `vendor_id` / `institution_id` | BIGINT UNSIGNED | FKs |
| `relationship_type` | ENUM | developer, hosting, maintenance, consulting, digitization, training, integration |
| `service_description` | TEXT | Service details |
| `start_date` / `end_date` | DATE | Relationship period |
| `is_active` / `is_public` | TINYINT(1) | Status flags |

**Unique:** `(vendor_id, institution_id, relationship_type)`

### 4.8 registry_institution_software

Which institutions use which software.

| Column | Type | Description |
|--------|------|-------------|
| `institution_id` / `software_id` | BIGINT UNSIGNED | FKs |
| `instance_id` | BIGINT UNSIGNED NULL | Optional FK to specific instance |
| `version_in_use` | VARCHAR(50) | Deployed version |
| `deployment_date` | DATE | When deployed |
| `notes` | TEXT | Usage notes |

**Unique:** `(institution_id, software_id, instance_id)`

### 4.9 registry_review (Polymorphic)

Reviews for vendors and software.

| Column | Type | Description |
|--------|------|-------------|
| `entity_type` | ENUM('vendor','software') | Target type |
| `entity_id` | BIGINT UNSIGNED | Target ID |
| `reviewer_institution_id` | BIGINT UNSIGNED NULL | Reviewing institution |
| `reviewer_name` / `reviewer_email` | VARCHAR | Reviewer info |
| `rating` | INT NOT NULL | 1-5 stars |
| `title` | VARCHAR(255) | Review title |
| `comment` | TEXT | Review body |
| `is_visible` / `is_verified` | TINYINT(1) | Moderation flags |

### 4.10 registry_user_group

Community groups with meeting tracking.

| Column | Type | Description |
|--------|------|-------------|
| `group_type` | ENUM | regional, topic, software, institutional, other |
| `focus_areas` | JSON | Focus area tags |
| `is_virtual` | TINYINT(1) | Virtual-only group |
| `meeting_frequency` | ENUM | weekly, biweekly, monthly, quarterly, annual, adhoc |
| `meeting_format` | ENUM | in_person, virtual, hybrid |
| `meeting_platform` | VARCHAR(100) | Zoom, Teams, etc. |
| `next_meeting_at` | DATETIME | Upcoming meeting date |
| `next_meeting_details` | TEXT | Meeting agenda |
| `mailing_list_url` / `slack_url` / `discord_url` / `forum_url` | VARCHAR(500) | Communication channels |
| `member_count` | INT | Calculated member count |
| `organizer_name` / `organizer_email` | VARCHAR | Group organizer |

### 4.11 registry_user_group_member

Group membership with roles.

| Column | Type | Description |
|--------|------|-------------|
| `group_id` | BIGINT UNSIGNED | FK to group |
| `user_id` | INT NULL | AtoM user ID (if linked) |
| `email` | VARCHAR(255) NOT NULL | Member email |
| `institution_id` | BIGINT UNSIGNED NULL | Member's institution |
| `role` | ENUM | organizer, co_organizer, member, speaker, sponsor |
| `is_active` | TINYINT(1) | Active membership |
| `email_notifications` | TINYINT(1) | Receive notifications |

**Unique:** `(group_id, email)`

### 4.12 registry_discussion

Threaded discussions within groups.

| Column | Type | Description |
|--------|------|-------------|
| `group_id` | BIGINT UNSIGNED | FK to group |
| `author_email` / `author_name` / `author_user_id` | Various | Author info |
| `title` | VARCHAR(500) | Discussion title |
| `content` | TEXT | Body content |
| `topic_type` | ENUM | discussion, question, announcement, event, showcase, help |
| `tags` | JSON | Tag array |
| `is_pinned` / `is_locked` / `is_resolved` | TINYINT(1) | Moderation flags |
| `status` | ENUM | active, closed, hidden, spam |
| `reply_count` / `view_count` | INT | Engagement metrics |
| `last_reply_at` | DATETIME | Latest reply timestamp |
| `last_reply_by` | VARCHAR(255) | Latest replier |

### 4.13 registry_discussion_reply

Nested replies with accepted answer support.

| Column | Type | Description |
|--------|------|-------------|
| `discussion_id` | BIGINT UNSIGNED | FK to discussion |
| `parent_reply_id` | BIGINT UNSIGNED NULL | FK to parent reply (for nesting) |
| `author_email` / `author_name` / `author_user_id` | Various | Author info |
| `content` | TEXT | Reply body |
| `is_accepted_answer` | TINYINT(1) | Accepted answer flag |
| `status` | ENUM | active, hidden, spam |

### 4.14 registry_blog_post (Polymorphic author)

Blog posts with publishing workflow.

| Column | Type | Description |
|--------|------|-------------|
| `title` / `slug` / `content` / `excerpt` | Various | Content fields |
| `featured_image_path` | VARCHAR(500) | Hero image |
| `author_type` | ENUM | admin, vendor, institution, user_group |
| `author_id` | BIGINT UNSIGNED NULL | Author entity ID |
| `author_name` | VARCHAR(255) | Display name |
| `category` | ENUM | news, announcement, event, tutorial, case_study, release, community, other |
| `tags` | JSON | Tag array |
| `status` | ENUM | draft, pending_review, published, archived |
| `is_featured` / `is_pinned` | TINYINT(1) | Display flags |
| `view_count` | INT | View counter |
| `published_at` | DATETIME | Publication timestamp |

### 4.15-4.18 Additional Tables

- **registry_sync_log** -- Event log for sync operations (register, heartbeat, sync, update, error)
- **registry_tag** -- Polymorphic tagging (`entity_type` ENUM institution/vendor/software)
- **registry_attachment** -- File attachments for discussions, replies, blog, institutions, vendors, software
- **registry_settings** -- Key/value plugin settings with types (text, number, boolean, json)
- **registry_oauth_account** -- Social login accounts (Google, Facebook, GitHub, LinkedIn, Microsoft)
- **registry_instance_feature** -- Per-instance feature/module usage tracking
- **registry_software_component** -- Plugins/modules of a software product
- **registry_favorite** -- User favorites/bookmarks (polymorphic: institution, vendor, software, group)
- **registry_newsletter** -- Newsletter content, status, send metrics
- **registry_newsletter_subscriber** -- Subscriber list with confirm/unsubscribe tokens
- **registry_newsletter_send_log** -- Per-subscriber send tracking
- **registry_vendor_call_log** -- CRM interaction tracking (stored inline in actions, no dedicated table in install.sql -- uses `registry_vendor_call_log` table)

---

## 5. Services

All services are in namespace `AhgRegistry\Services`, loaded via the `loadService()` helper in the actions class. Each takes a `$culture` constructor parameter.

### 5.1 InstitutionService

**File:** `lib/Services/InstitutionService.php`

Manages GLAM institution CRUD and queries. Delegates to `InstitutionRepository` for data access.

```php
$svc = new InstitutionService('en');

// Browse with filters
$result = $svc->browse([
    'type' => 'archive',
    'country' => 'South Africa',
    'search' => 'national',
    'page' => 1,
    'limit' => 20,
]);
// Returns: ['items' => Collection, 'total' => int, 'page' => int]

// View with all related data
$data = $svc->view('national-archives-of-south-africa');
// Returns: ['institution' => object, 'contacts' => [], 'instances' => [],
//           'software' => [], 'vendors' => [], 'tags' => []]

// CRUD
$result = $svc->create(['name' => 'Test Archive', 'institution_type' => 'archive']);
$result = $svc->update(1, ['description' => 'Updated']);
$result = $svc->delete(1); // Cascades to contacts, instances, sync_logs, etc.

// Verification
$svc->verify($id, $userId, 'Verified by admin');
$svc->toggleFeatured($id);

// Map data
$markers = $svc->getForMap(['type' => 'archive']);

// Dashboard stats
$stats = $svc->getDashboardStats();
// Returns: ['total', 'verified', 'featured', 'uses_atom', 'by_type', 'by_country']
```

**Cascade delete chain:** contacts -> instances -> sync_logs -> vendor relationships -> software assignments -> tags -> reviews -> group memberships -> attachments -> institution.

### 5.2 VendorService

**File:** `lib/Services/VendorService.php`

Manages vendor profiles. Operates directly on the `registry_vendor` table (no repository layer).

Key patterns:

- **vendor_type filtering:** Uses `JSON_CONTAINS()` for multi-select type matching
- **FULLTEXT + LIKE fallback:** Same dual-search pattern as all browse operations
- **Rating aggregation:** `average_rating` and `rating_count` on the vendor record are recalculated by `ReviewService`

```php
$svc = new VendorService('en');

$result = $svc->browse([
    'type' => 'developer',        // JSON_CONTAINS filter
    'specialization' => 'AtoM',   // JSON_CONTAINS filter
    'country' => 'South Africa',
]);
```

### 5.3 ContactService

**File:** `lib/Services/ContactService.php`

Polymorphic CRUD for contacts belonging to either institutions or vendors.

```php
$svc = new ContactService('en');

$contacts = $svc->findByEntity('institution', 42);
$svc->create(['entity_type' => 'vendor', 'entity_id' => 7, 'first_name' => 'John', ...]);
$svc->setPrimary($contactId, 'institution', 42); // Unsets previous primary, sets this one
```

### 5.4 InstanceService

**File:** `lib/Services/InstanceService.php`

Manages Heratio/AtoM instance records and sync tokens.

```php
$svc = new InstanceService('en');

// Generate cryptographic sync token
$token = $svc->generateSyncToken(); // SHA-256, 64 chars

// Validate token for API auth
$instance = $svc->validateSyncToken($token); // Returns instance or null

// Process heartbeat
$svc->updateFromHeartbeat($instanceId, [
    'software_version' => '2.8.2',
    'record_count' => 150000,
    'digital_object_count' => 45000,
    'storage_gb' => 120.5,
]);

// Mark stale instances offline (no heartbeat for N days)
$count = $svc->markStaleOffline(7); // Returns affected count
```

### 5.5 SoftwareService

**File:** `lib/Services/SoftwareService.php`

Software catalog with release management and file uploads.

```php
$svc = new SoftwareService('en');

// Release management
$svc->createRelease($softwareId, [
    'version' => '2.8.3',
    'release_type' => 'patch',
    'release_notes' => 'Bug fixes...',
    'is_stable' => 1,
]);
// Automatically: unsets previous is_latest, sets this as latest, updates software.latest_version

// File upload with checksum
$result = $svc->handleUpload($softwareId, $_FILES['package']);
// Stores to: uploads/registry/software/{id}/{sanitized_filename}
// Computes: SHA-256 checksum

// Download tracking
$svc->incrementDownloadCount($releaseId); // Increments both release and software totals
```

### 5.6 RelationshipService

**File:** `lib/Services/RelationshipService.php`

Manages the many-to-many relationships between vendors-institutions and institutions-software.

```php
$svc = new RelationshipService('en');

// Vendor-institution
$clients = $svc->getVendorClients($vendorId);
$vendors = $svc->getInstitutionVendors($institutionId);
$svc->createVendorRelationship([
    'vendor_id' => 1, 'institution_id' => 42,
    'relationship_type' => 'hosting',
]);
// Automatically recalculates vendor.client_count

// Institution-software
$software = $svc->getInstitutionSoftware($institutionId);
$institutions = $svc->getSoftwareInstitutions($softwareId);
$svc->assignSoftware([
    'institution_id' => 42, 'software_id' => 5,
    'version_in_use' => '2.8.2',
]);
// Automatically recalculates software.institution_count
```

### 5.7 ReviewService

**File:** `lib/Services/ReviewService.php`

Polymorphic reviews for vendors and software with automatic rating aggregation.

```php
$svc = new ReviewService('en');

$svc->create([
    'entity_type' => 'vendor',
    'entity_id' => 7,
    'rating' => 4,       // Validated: 1-5
    'title' => 'Great service',
    'comment' => '...',
]);
// After create/update/delete: automatically recalculates
// target_table.average_rating and target_table.rating_count

$svc->toggleVisibility($reviewId); // Moderation toggle
```

### 5.8 UserGroupService

**File:** `lib/Services/UserGroupService.php`

Community groups with membership management.

```php
$svc = new UserGroupService('en');

// Join/leave
$svc->join('south-africa-atom-users', 'user@example.com', 'John Doe', $userId, $institutionId);
$svc->leave('south-africa-atom-users', 'user@example.com');
$svc->isMember($groupId, 'user@example.com'); // bool

// Members
$members = $svc->getMembers($groupId);
// Ordered by: FIELD(role, 'organizer', 'co_organizer', 'speaker', 'sponsor', 'member')

$svc->updateMemberRole($groupId, 'user@example.com', 'co_organizer');
$svc->removeMember($memberId);
$svc->toggleMemberActive($memberId);

// User's groups
$myGroups = $svc->getMyGroups('user@example.com');
```

### 5.9 DiscussionService

**File:** `lib/Services/DiscussionService.php`

Threaded discussions within groups with nested reply tree building.

```php
$svc = new DiscussionService('en');

// View discussion (increments view_count)
$data = $svc->view($discussionId);
// Returns: ['discussion' => object, 'replies' => [nested tree], 'reply_count' => int]

// Reply (updates discussion.reply_count, last_reply_at, last_reply_by)
$svc->reply($discussionId, [
    'content' => 'My reply...',
    'author_email' => 'user@example.com',
    'parent_reply_id' => 5,  // For nested reply
]);

// Moderation
$svc->pin($id); $svc->unpin($id);
$svc->lock($id); $svc->unlock($id);
$svc->resolve($id);
$svc->markAcceptedAnswer($replyId); // Also resolves the discussion

// Cross-group feed
$recent = $svc->getRecentAcrossGroups(10);
```

**Reply tree algorithm:** Flat list of replies is built into a nested tree using `parent_reply_id`. The `buildReplyTree()` method indexes all replies by ID, iterates to build parent-child relationships, then converts back to objects for template compatibility.

### 5.10 BlogService

**File:** `lib/Services/BlogService.php`

Blog with publishing workflow (draft -> pending_review -> published -> archived).

```php
$svc = new BlogService('en');

$svc->create([
    'title' => 'New AtoM Release',
    'content' => '<p>HTML content...</p>',
    'author_type' => 'vendor',
    'author_id' => 7,
]);
// Auto-generates slug (truncated to 200 chars) and excerpt (300 chars, word-boundary)

$svc->publish($id);   // Sets published_at
$svc->archive($id);
$svc->unpublish($id); // Back to draft

$svc->toggleFeatured($id);
$svc->togglePinned($id);
```

### 5.11 AttachmentService

**File:** `lib/Services/AttachmentService.php`

File upload and management for discussions, replies, blog posts, institutions, vendors, and software.

```php
$svc = new AttachmentService('en');

$result = $svc->upload('discussion', $discussionId, $_FILES['attachment'], $email, $userId);
// Stores to: uploads/registry/attachments/YYYY/MM/{timestamp}_{sanitized_name}
// Validates: extension against registry_settings, file size against max_attachment_size_mb

$svc->delete($attachmentId); // Removes file from disk + DB record
$svc->incrementDownloadCount($attachmentId);
```

**File type categorization:** Extensions are mapped to enum values -- `image`, `document`, `log`, `archive`, `screenshot`, `other`.

### 5.12 SyncService

**File:** `lib/Services/SyncService.php`

Orchestrates the sync API: registration, heartbeats, updates, and directory.

```php
$svc = new SyncService('en');

// Registration (creates institution + instance + sync token)
$result = $svc->register([
    'institution_name' => 'National Archives',
    'instance_url' => 'https://archives.example.com',
    'software' => 'heratio',
    'software_version' => '2.8.2',
]);
// Returns: ['success', 'institution_id', 'instance_id', 'sync_token']

// Heartbeat
$result = $svc->heartbeat($token, ['software_version' => '2.8.3', 'record_count' => 15000]);
// Returns: ['success', 'instance_id', 'latest_version' => '2.9.0' or null]

// Full update
$svc->update($token, [
    'instance' => ['name' => '...', 'record_count' => 15000],
    'institution' => ['name' => '...', 'description' => '...'],
]);

// Public directory
$directory = $svc->getDirectory(); // All active institutions with public instances
```

### 5.13 RegistrySearchService

**File:** `lib/Services/RegistrySearchService.php`

Unified full-text search across all six entity types: institution, vendor, software, user_group, discussion, blog_post.

```php
$svc = new RegistrySearchService('en');

$results = $svc->search('archival management', [
    'type' => 'software',  // Optional: filter to one entity type
    'page' => 1,
    'limit' => 20,
]);
// Returns normalized results: [
//   'items' => [['entity_type', 'id', 'title', 'excerpt', 'url', 'meta', 'relevance'], ...],
//   'total' => int,
// ]
```

Each entity type is searched independently with FULLTEXT + LIKE fallback. Results are merged and sorted by relevance score descending, then paginated.

### 5.14 GitIntegrationService

**File:** `lib/Services/GitIntegrationService.php`

Fetches latest releases from GitHub and GitLab APIs via cURL.

```php
$svc = new GitIntegrationService('en');

// Fetch latest release for a software entry
$result = $svc->fetchLatestRelease($softwareId);

// Or directly from a provider
$data = $svc->fetchGitHubRelease('artefactual', 'atom', $optionalToken);
$data = $svc->fetchGitLabRelease('group/project', $optionalToken);

// Create release record from fetched data
$svc->updateSoftwareFromGit($softwareId, $data['release']);
// Strips 'v' prefix, detects release type (major/minor/patch/beta/rc/alpha),
// creates registry_software_release row, updates software.latest_version
```

**URL parsing:** Supports GitHub (`github.com/owner/repo`), GitLab (`gitlab.com/group/subgroup/repo`), and Bitbucket URLs.

### 5.15 RegistryImportService

**File:** `lib/Services/RegistryImportService.php`

One-time migration from WordPress data export (Custom Post Types).

```php
$svc = new RegistryImportService('en');

// Preview without inserting
$preview = $svc->preview($data);
// Returns: counts of new vs duplicate for each entity type

// Execute import (skips duplicates by slug)
$results = $svc->execute($data);
// Returns: imported/skipped/errors per entity type
```

Includes flexible field mapping -- accepts WordPress field names (`post_title`, `post_content`, `post_excerpt`) and normalizes them to the registry schema. Maps free-text type strings to ENUM values.

### 5.16 OAuthService

**File:** `lib/Services/OAuthService.php`

Social login via Google, Facebook, GitHub, LinkedIn, Microsoft. Uses `registry_settings` for client credentials.

```php
// Get authorization URL
$url = OAuthService::getAuthUrl('github', $redirectUri);

// Handle callback
$userData = OAuthService::handleCallback('github', $code, $redirectUri);
// Returns: ['provider_user_id', 'email', 'name', 'avatar_url', 'access_token', ...]

// Link to AtoM user
OAuthService::linkAccount($userId, 'github', $userData);

// Find existing linked account
$account = OAuthService::findByProviderAccount('github', $providerUserId);
```

**CSRF protection:** Generates random state parameter, stores in `$_SESSION`, validates on callback with `hash_equals()`.

**GitHub email handling:** If the profile endpoint does not return email, fetches from `/user/emails` API to find the primary verified email.

### 5.17 NewsletterService

**File:** `lib/Services/NewsletterService.php`

Newsletter composition, subscriber management, and SMTP delivery.

```php
$svc = new NewsletterService();

// Subscribe (with double opt-in tokens)
$svc->subscribe(['email' => 'user@example.com', 'name' => 'John']);
$svc->unsubscribe($token);
$svc->confirm($confirmToken);

// Newsletter CRUD
$svc->create(['subject' => 'Monthly Update', 'content' => '<p>HTML...</p>']);
$svc->update($id, ['content' => '...']);

// Send to all active confirmed subscribers
$result = $svc->send($newsletterId);
// Uses PHPMailer via SMTP if enabled, falls back to mail()
// Includes List-Unsubscribe header for each recipient
// Logs per-subscriber send status in registry_newsletter_send_log
```

---

## 6. Repositories

All repositories are in namespace `AhgRegistry\Repositories`, thin wrappers around Laravel Query Builder.

### Common Interface Pattern

```php
namespace AhgRegistry\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

class EntityRepository
{
    protected string $table = 'registry_entity';

    public function findById(int $id): ?object;
    public function findBySlug(string $slug): ?object;
    public function findAll(array $params = []): array;  // Paginated + filtered
    public function search(string $term, array $params = []): array;  // FULLTEXT + LIKE
    public function count(array $filters = []): int;
    public function create(array $data): int;  // Returns insertGetId
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function getFeatured(int $limit = 6): array;
}
```

### Repository List

| Repository | Table | Notes |
|-----------|-------|-------|
| InstitutionRepository | registry_institution | FULLTEXT search on name+description+collection_summary, `getForMap()`, `getByCountry()` |
| VendorRepository | registry_vendor | `JSON_CONTAINS` for vendor_type, `updateRatingStats()` |
| ContactRepository | registry_contact | Polymorphic (institution/vendor) |
| InstanceRepository | registry_instance | Sync token queries |
| SoftwareRepository | registry_software | Category and sector filtering |
| BlogRepository | registry_blog_post | Status-based filtering |
| DiscussionRepository | registry_discussion | Group-scoped queries |
| ReviewRepository | registry_review | Polymorphic (vendor/software) |
| UserGroupRepository | registry_user_group | Group type and region filtering |
| RelationshipRepository | registry_vendor_institution | Join-based queries |
| TagRepository | registry_tag | Polymorphic tag queries |
| SyncRepository | registry_sync_log | Event history queries |
| AttachmentRepository | registry_attachment | Polymorphic file queries |

**Note:** Some services (VendorService, SoftwareService, BlogService, etc.) query the database directly without using a repository, especially for simpler operations. The repository pattern is most fully developed for InstitutionRepository and VendorRepository.

---

## 7. Routes

All routes are defined in `ahgRegistryPluginConfiguration::loadRoutes()` using `AtomFramework\Routing\RouteLoader`. The plugin defines approximately 115 routes organized into categories.

### Route Registration Pattern

```php
$loader = new \AtomFramework\Routing\RouteLoader('registry');

// Methods: get(), post(), any()
$loader->any('route_name', '/registry/path/:param', 'actionMethod', ['param' => '\d+']);
$loader->post('route_name', '/registry/path', 'actionMethod');

$loader->register($routing);
```

### API Routes (6)

| Route | Method | Path | Action |
|-------|--------|------|--------|
| registry_api_sync_register | POST | /registry/api/sync/register | apiSyncRegister |
| registry_api_sync_heartbeat | POST | /registry/api/sync/heartbeat | apiSyncHeartbeat |
| registry_api_sync_update | POST | /registry/api/sync/update | apiSyncUpdate |
| registry_api_sync_status | GET | /registry/api/sync/status | apiSyncStatus |
| registry_api_directory | GET | /registry/api/directory | apiDirectory |
| registry_api_software_latest | GET | /registry/api/software/:slug/latest | apiSoftwareLatest |

### Admin Routes (~20)

All admin routes require `administrator` credential.

| Path Pattern | Action | Purpose |
|-------------|--------|---------|
| /registry/admin | adminDashboard | Statistics dashboard |
| /registry/admin/institutions | adminInstitutions | Institution list + verify |
| /registry/admin/vendors | adminVendors | Vendor list + verify |
| /registry/admin/software | adminSoftware | Software list + verify |
| /registry/admin/groups | adminGroups | Group list + verify |
| /registry/admin/groups/:id/edit | adminGroupEdit | Edit group |
| /registry/admin/groups/:id/members | adminGroupMembers | Manage members |
| /registry/admin/discussions | adminDiscussions | Moderate discussions |
| /registry/admin/blog | adminBlog | Moderate blog posts |
| /registry/admin/reviews | adminReviews | Moderate reviews |
| /registry/admin/sync | adminSync | Sync dashboard |
| /registry/admin/settings | adminSettings | Plugin settings |
| /registry/admin/footer | adminFooter | Footer editor |
| /registry/admin/email | adminEmail | SMTP config |
| /registry/admin/import | adminImport | WordPress import |
| /registry/admin/users | adminUsers | User management |
| /registry/admin/newsletters | adminNewsletters | Newsletter management |
| /registry/admin/subscribers | adminSubscribers | Subscriber management |

### Self-Service Routes (~35)

Authenticated users manage their own entities.

**Institution self-service (`/registry/my/institution/...`):**
dashboard, register, edit, contacts, contact add/edit, instances, instance add/edit, software, vendors, review

**Vendor self-service (`/registry/my/vendor/...`):**
dashboard, register, edit, contacts, contact add/edit, clients, client add, software, software add/edit, software releases, release add, software upload, call-log, call-log add/edit/view

**Groups & Blog self-service:**
my groups, group create/edit, group members manage, my blog, blog new/edit

### Public Routes (~30)

| Path | Action | Description |
|------|--------|-------------|
| /registry | index | Homepage with stats + featured |
| /registry/community | community | Community hub |
| /registry/institutions | institutionBrowse | Browse institutions |
| /registry/institutions/:slug | institutionView | Institution detail |
| /registry/vendors | vendorBrowse | Browse vendors |
| /registry/vendors/:slug | vendorView | Vendor detail |
| /registry/software | softwareBrowse | Browse software |
| /registry/software/:slug | softwareView | Software detail |
| /registry/software/:slug/releases | softwareReleases | Release history |
| /registry/instances/:id | instanceView | Instance detail |
| /registry/groups | groupBrowse | Browse groups |
| /registry/groups/:slug | groupView | Group detail |
| /registry/groups/:slug/discussions | discussionList | Group discussions |
| /registry/groups/:slug/discussions/:id | discussionView | Discussion detail |
| /registry/blog | blogList | Blog archive |
| /registry/blog/:slug | blogView | Blog post |
| /registry/newsletters | newsletterBrowse | Newsletter archive |
| /registry/search | search | Unified search |
| /registry/map | map | Institution map |
| /registry/login | login | Login page |
| /registry/register | register | Registration |
| /registry/newsletter/subscribe | newsletterSubscribe | Subscribe form |

### Auth Routes

| Path | Action | Description |
|------|--------|-------------|
| /registry/login | login | AtoM auth + OAuth buttons |
| /registry/register | register | User registration |
| /registry/logout | logout | Session logout |
| /registry/oauth/:provider | oauthStart | OAuth redirect |
| /registry/oauth/:provider/callback | oauthCallback | OAuth callback |

### Route Ordering

Slug-based catch-all routes (e.g., `/registry/institutions/:slug`) are declared before specific routes in the file. Because `RouteLoader` prepends routes, the last-in-file routes are checked first by the router. This ensures `/registry/institutions` (browse) is matched before `/registry/institutions/:slug` (view).

---

## 8. Actions

The single `registryActions` class contains approximately 100 `execute*` methods in `modules/registry/actions/actions.class.php` (4400 lines). It extends `AhgController` from the atom-framework.

### Base Helpers

```php
class registryActions extends AhgController
{
    // Boot: loads framework bootstrap, sets pluginDir
    public function boot(): void;

    // Load a service by name (lazy loads all repos + specific service)
    protected function loadService(string $name): object;

    // Auth helpers
    protected function requireLogin(): ?object;      // Redirects to /registry/login
    protected function requireAdminUser(): ?object;   // 404 if not admin
    protected function isAdmin(): bool;
    protected function getCurrentUserId(): ?int;
    protected function getCurrentUserEmail(): ?string;

    // Entity ownership
    protected function getMyInstitution(): ?object;   // Admin can switch via ?inst=X
    protected function getMyVendor(): ?object;         // created_by match only

    // Settings
    protected function getRegistrySetting(string $key, $default = null);

    // Tags
    protected function saveTags(string $entityType, int $entityId, string $tagsString): void;

    // Favorites
    protected function isFavorited(string $entityType, int $entityId): bool;

    // JSON response
    protected function jsonResponse(array $data, int $status = 200): string;
}
```

### Action Pattern

Every action follows a consistent pattern:

```php
public function executeEntityAction($request)
{
    // 1. Auth check (if needed)
    $user = $this->requireLogin();
    if (!$user) return;

    // 2. Load service
    $svc = $this->loadService('EntityService');

    // 3. Handle POST (form submission)
    if ($request->isMethod('post')) {
        $data = [/* extract from $request */];
        $result = $svc->create($data);
        if ($result['success']) {
            $this->redirect('/registry/entity/' . $result['slug']);
            return;
        }
        $this->error = $result['error'];
    }

    // 4. Load data for GET display
    $this->entity = $svc->findById($id);
}
```

### Action Categories

| Category | Count | Examples |
|----------|-------|---------|
| Public browse/view | ~15 | index, community, search, map, institutionBrowse, vendorView |
| Self-service institution | ~12 | institutionRegister, institutionEdit, myInstitutionContacts |
| Self-service vendor | ~18 | vendorRegister, vendorEdit, myVendorSoftwareAdd, myVendorCallLog |
| Self-service groups/blog | ~8 | groupCreate, groupEdit, blogNew, blogEdit |
| Admin | ~20 | adminDashboard, adminInstitutions, adminSettings, adminImport |
| API | 6 | apiSyncRegister, apiSyncHeartbeat, apiDirectory |
| Auth | 5 | login, register, logout, oauthStart, oauthCallback |
| Favorites/Newsletter | ~8 | favoriteToggle, myFavorites, newsletterSubscribe |
| Software components | ~4 | softwareComponents, softwareComponentAdd |

---

## 9. Templates

The plugin has approximately 100 template files using Symfony 1.x PHP templates.

### Layout

All templates use the standalone layout:

```php
<?php decorate_with(dirname(__FILE__) . '/layout_registry.php'); ?>
```

The layout (`layout_registry.php`) provides:

- HTML5 boilerplate with CSP nonce on all `<script>` and `<style>` tags
- Responsive Bootstrap 5 navbar with navigation, search, and auth dropdown
- Footer with configurable columns loaded from `registry_settings`
- Slot support: `title`, `head`, `content`, `scripts`

### Partials (Underscore-Prefixed)

| Partial | Purpose |
|---------|---------|
| `_breadcrumb.php` | Breadcrumb navigation |
| `_vendorCard.php` | Vendor card in browse grids |
| `_institutionCard.php` | Institution card |
| `_softwareCard.php` | Software card |
| `_groupCard.php` | User group card |
| `_blogCard.php` | Blog post card |
| `_ratingStars.php` | 1-5 star rating display |
| `_filterSidebar.php` | Reusable filter sidebar |
| `_contactList.php` | Contact listing |
| `_instanceList.php` | Instance listing |
| `_discussionRow.php` | Discussion list row |
| `_replyThread.php` | Recursive nested reply rendering |
| `_attachmentList.php` | File attachment listing |
| `_attachmentUpload.php` | Upload form widget |

### CSP Nonce Pattern

All inline `<script>` and `<style>` tags include the CSP nonce:

```php
<?php
  $n = sfConfig::get('csp_nonce', '');
  $na = $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : '';
?>
<script <?php echo $na; ?>>
  // JavaScript here
</script>
<style <?php echo $na; ?>>
  /* CSS here */
</style>
```

---

## 10. Key Patterns and Gotchas

### JSON Column Handling

Multiple tables use MySQL JSON columns. When accessed via Laravel Query Builder, values come back as raw strings. However, Symfony's `sfOutputEscaper` HTML-encodes them in templates, breaking `json_decode()`.

```php
// In templates - ALWAYS unescape before JSON parsing:
$raw = sfOutputEscaper::unescape($vendor);
$types = json_decode($raw->vendor_type, true) ?: [];

// In services - strings from DB are fine:
$types = json_decode($vendor->vendor_type, true) ?: [];
```

### JSON_CONTAINS for Multi-Select Filtering

```php
// Filter vendors by type (JSON array column)
$query->whereRaw("JSON_CONTAINS(vendor_type, ?)", [json_encode($type)]);

// Filter by specialization (JSON array)
$query->whereRaw("JSON_CONTAINS(specializations, ?)", ['"AtoM"']);
```

### FULLTEXT + LIKE Fallback Pattern

Every browse/search method implements this dual-search:

```php
// 1. Try FULLTEXT
$query->whereRaw("MATCH(name, description) AGAINST(? IN BOOLEAN MODE)", [$term]);
$total = $query->count();

// 2. If zero results, rebuild query with LIKE
if ($total === 0 && !empty($term)) {
    $likeTerm = '%' . $term . '%';
    $query = DB::table($table)->where('is_active', 1);
    // Re-apply all filters...
    $query->where(function ($q) use ($likeTerm) {
        $q->where('name', 'LIKE', $likeTerm)
          ->orWhere('description', 'LIKE', $likeTerm);
    });
}
```

### sfOutputEscaperArrayDecorator

In templates, arrays from the controller are wrapped in `sfOutputEscaperArrayDecorator`. This wrapper does not support `array_filter()`, `array_map()`, or other native array functions directly.

```php
// WRONG - will fail silently or error
$filtered = array_filter($items, function($i) { ... });

// CORRECT - unescape first
$rawItems = sfOutputEscaper::unescape($items);
$filtered = array_filter($rawItems, function($i) { ... });
```

### Service Lazy Loading

Symfony 1.x does not autoload namespaced classes from plugins. The `loadService()` helper loads all repository files via glob, then the specific service:

```php
protected function loadService(string $name): object
{
    $repoDir = $this->pluginDir . '/lib/Repositories/';
    $svcDir = $this->pluginDir . '/lib/Services/';

    foreach (glob($repoDir . '*.php') as $file) {
        require_once $file;
    }
    require_once $svcDir . $name . '.php';

    $class = '\\AhgRegistry\\Services\\' . $name;
    return new $class($this->culture());
}
```

### Entity Ownership

- `getMyInstitution()` returns the institution where `created_by` matches the current user. Admins can override via `?inst=X` parameter.
- `getMyVendor()` returns the vendor where `created_by` matches the current user. **No admin fallback** -- admins must use the admin routes to edit any vendor.
- Vendor edit forms must include the entity ID in the URL (`?id=X`) to prevent wrong-entity saves when multiple vendors exist.

### Slug Generation

All slug-bearing entities use the same generation pattern:

```php
public function generateSlug(string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    $slug = preg_replace('/-+/', '-', $slug);

    $baseSlug = $slug;
    $counter = 1;
    while (DB::table($this->table)->where('slug', $slug)->exists()) {
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    return $slug;
}
```

Slugs are regenerated when the name changes. Blog post slugs are truncated to 200 characters.

---

## 11. Sync API Protocol

The sync API enables remote Heratio/AtoM instances to register with the registry and report their status via periodic heartbeats.

### Registration

```
POST /registry/api/sync/register
Content-Type: application/json

{
    "institution_name": "National Archives of South Africa",
    "institution_type": "archive",
    "instance_url": "https://archives.example.com",
    "instance_name": "Production",
    "software": "heratio",
    "software_version": "2.8.2",
    "country": "South Africa",
    "city": "Pretoria",
    "hosting": "self_hosted",
    "record_count": 150000,
    "digital_object_count": 45000,
    "storage_gb": 120.5
}

Response 200:
{
    "success": true,
    "institution_id": 42,
    "instance_id": 15,
    "sync_token": "a3f8c2d1e4b5..." (64-char SHA-256)
}
```

The response `sync_token` must be stored by the remote instance and included in all subsequent API calls.

### Heartbeat

```
POST /registry/api/sync/heartbeat
X-Sync-Token: a3f8c2d1e4b5...
Content-Type: application/json

{
    "software_version": "2.8.3",
    "record_count": 152000,
    "digital_object_count": 46000,
    "storage_gb": 125.0
}

Response 200:
{
    "success": true,
    "instance_id": 15,
    "latest_version": "2.9.0"  // null if already on latest
}
```

The heartbeat updates `last_heartbeat_at` and `status` to `online`. If a newer version is available in the software catalog, `latest_version` is returned.

### Token Authentication

Sync tokens are:

- 64-character SHA-256 hashes generated from `random_bytes(32) + microtime()`
- Stored in `registry_instance.sync_token`
- Validated via `InstanceService::validateSyncToken()` which checks token length (64), existence, and `sync_enabled = 1`
- Passed via `X-Sync-Token` HTTP header or `sync_token` POST/GET parameter

### Offline Detection

`InstanceService::markStaleOffline()` marks instances as `offline` if no heartbeat has been received within the configured threshold (default: 7 days, configurable via `heartbeat_offline_threshold_days` setting).

### Full Update

```
POST /registry/api/sync/update
X-Sync-Token: a3f8c2d1e4b5...
Content-Type: application/json

{
    "instance": {
        "name": "Production Server",
        "software_version": "2.8.3",
        "record_count": 155000,
        "sync_data": {"plugins": ["ahgDAMPlugin", "ahgLibraryPlugin"]}
    },
    "institution": {
        "description": "Updated description...",
        "country": "South Africa",
        "collection_summary": "..."
    }
}
```

### Public Directory API

```
GET /registry/api/directory

Response 200:
[
    {
        "id": 42,
        "name": "National Archives of South Africa",
        "slug": "national-archives-of-south-africa",
        "institution_type": "archive",
        "city": "Pretoria",
        "country": "South Africa",
        "latitude": "-25.7479",
        "longitude": "28.2293",
        "website": "https://www.national.archives.gov.za",
        "uses_atom": 1,
        "is_verified": 1,
        "instances": [
            {
                "id": 15,
                "name": "Production",
                "url": "https://archives.example.com",
                "instance_type": "production",
                "software": "heratio",
                "software_version": "2.8.3",
                "status": "online"
            }
        ]
    }
]
```

### Software Version API

```
GET /registry/api/software/:slug/latest

Response 200:
{
    "software": "Heratio",
    "slug": "heratio",
    "latest_version": "2.9.0",
    "release": {
        "version": "2.9.0",
        "release_type": "minor",
        "released_at": "2026-02-01 10:00:00",
        "git_tag": "v2.9.0",
        "is_stable": true
    }
}
```

---

## 12. Standalone Mode

The ahgRegistryPlugin operates as a standalone web application within AtoM. It does not depend on the AtoM theme stack (ahgThemeB5Plugin) or GLAM display plugins.

### Layout Architecture

The standalone layout (`layout_registry.php`) provides:

1. **HTML5 document** with CSP nonce support
2. **CDN dependencies:** Bootstrap 5.3.3, Font Awesome 6.5.1, Source Sans 3
3. **Custom CSS variables** for theming (`--atm-primary: #225b7b`, etc.)
4. **Responsive navbar** with:
   - Brand logo + "AtoM Registry" text
   - Main nav: Institutions, Vendors, Software, Community, More (dropdown)
   - Search form
   - Auth section (login/register or user dropdown with My Institution/Vendor/Groups/Admin)
5. **Configurable footer** with columns loaded from `registry_settings.footer_columns` (JSON)
6. **Slot system** for page-specific content, scripts, and head elements

### Authentication

The plugin uses AtoM's session-based authentication (`sfContext::getInstance()->getUser()`) for self-service and admin features. For public access, no authentication is required.

The login page (`loginSuccess.php`) provides:

1. Standard AtoM username/password authentication
2. OAuth social login buttons (Google, Facebook, GitHub, LinkedIn, Microsoft) when configured
3. Link to registration page

### CSS Theming

The plugin includes two CSS sources:

1. **Inline styles in layout** -- CSS variables, navbar, footer, button overrides (~170 lines)
2. **registry.css** -- Entity-specific styles, card layouts, hero banners, filter sidebar, map styles

CSS variables follow the `--atm-*` prefix (layout) and `--reg-*` prefix (plugin CSS).

### JavaScript

| File | Purpose |
|------|---------|
| `registry-map.js` | Leaflet map initialization, marker clustering, popup formatting |
| `registry-discussions.js` | Reply threading, toggle handlers, AJAX interactions |

---

## 13. Entity Relationship Diagram

```
registry_institution ----< registry_contact (entity_type='institution')
       |
       |----< registry_instance ----< registry_sync_log
       |            |----< registry_instance_feature
       |
       |----< registry_vendor_institution >---- registry_vendor
       |                                              |
       |----< registry_institution_software           |----< registry_contact (entity_type='vendor')
       |            |                                 |
       |            >---- registry_software           |----< registry_vendor_call_log
       |                      |
       |                      |----< registry_software_release
       |                      |----< registry_software_component
       |                      |----< registry_review (entity_type='software')
       |
       |----< registry_review (reviewer_institution_id)
       |
       >---- registry_user_group_member >---- registry_user_group
                                                    |
                                                    |----< registry_discussion
                                                               |----< registry_discussion_reply
                                                               |----< registry_attachment

registry_blog_post (polymorphic author: admin/vendor/institution/user_group)
registry_tag (polymorphic: institution/vendor/software)
registry_attachment (polymorphic: discussion/reply/blog_post/institution/vendor/software)
registry_favorite (polymorphic: institution/vendor/software/group)
registry_oauth_account (linked to AtoM user)
registry_newsletter ----< registry_newsletter_send_log >---- registry_newsletter_subscriber
registry_settings (key/value config store)
```

---

## 14. Configuration Reference

### registry_settings Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `registry_name` | text | Heratio Registry | Display name |
| `moderation_enabled` | boolean | 1 | Require admin approval |
| `allow_self_registration` | boolean | 1 | Allow self-registration |
| `featured_count` | number | 6 | Featured items per section |
| `heartbeat_interval_hours` | number | 24 | Expected heartbeat interval |
| `heartbeat_offline_threshold_days` | number | 7 | Days before marking offline |
| `max_upload_size_mb` | number | 100 | Max software upload size |
| `allowed_upload_extensions` | text | zip,tar.gz,deb,rpm | Software upload types |
| `default_country` | text | South Africa | Default country |
| `map_default_lat` | text | -30.5595 | Map center latitude |
| `map_default_lng` | text | 22.9375 | Map center longitude |
| `map_default_zoom` | number | 5 | Map zoom level |
| `max_attachment_size_mb` | number | 10 | Max attachment size |
| `allowed_attachment_types` | text | jpg,jpeg,png,... | Allowed attachment types |
| `discussion_require_approval` | boolean | 0 | Moderate new discussions |
| `blog_require_approval` | boolean | 1 | Moderate non-admin blog posts |
| `max_logo_size_mb` | number | 5 | Max logo upload size |
| `oauth_google_enabled` | boolean | 0 | Enable Google login |
| `oauth_google_client_id` | text | | Google OAuth client ID |
| `oauth_google_client_secret` | text | | Google OAuth secret |
| `oauth_facebook_enabled` | boolean | 0 | Enable Facebook login |
| `oauth_github_enabled` | boolean | 0 | Enable GitHub login |
| `smtp_enabled` | boolean | 0 | Enable SMTP for newsletters |
| `smtp_host` / `smtp_port` / `smtp_encryption` | Various | | SMTP server config |
| `smtp_username` / `smtp_password` | text | | SMTP credentials |
| `smtp_from_email` / `smtp_from_name` | text | | Sender info |
| `footer_description` | text | | Footer description text |
| `footer_copyright` | text | | Copyright line ({year} placeholder) |
| `footer_columns` | json | | Footer link columns (array of objects) |

---

## 15. Development Guide

### Adding a New Entity Type

1. Create table in `database/install.sql`
2. Create repository in `lib/Repositories/`
3. Create service in `lib/Services/`
4. Add routes in `ahgRegistryPluginConfiguration::loadRoutes()`
5. Add action methods in `actions.class.php`
6. Create browse, view, edit, and card templates
7. Add to `RegistrySearchService::searchEntityType()` for unified search
8. Update admin dashboard statistics

### Adding a New Admin Section

1. Add route: `$loader->any('registry_admin_foo', '/registry/admin/foo', 'adminFoo');`
2. Add action: `public function executeAdminFoo($request) { $this->requireAdminUser(); ... }`
3. Create template: `adminFooSuccess.php`
4. Add link in admin dashboard navigation

### Extending the Sync API

1. Add endpoint route in configuration
2. Add action method (validate token, parse payload, call service)
3. Log event via `SyncService::logEvent()`
4. Return JSON response via `$this->jsonResponse()`

### Testing

```bash
# Test sync API registration
curl -X POST https://registry.theahg.co.za/registry/api/sync/register \
  -H "Content-Type: application/json" \
  -d '{"institution_name":"Test Archive","instance_url":"https://test.example.com"}'

# Test heartbeat
curl -X POST https://registry.theahg.co.za/registry/api/sync/heartbeat \
  -H "X-Sync-Token: <token>" \
  -H "Content-Type: application/json" \
  -d '{"software_version":"2.8.3","record_count":1000}'

# Test public directory
curl https://registry.theahg.co.za/registry/api/directory
```

---

## 16. Known Limitations

1. **Single actions file** -- At 4400 lines, the actions file is large. Consider splitting into trait files if it grows further.

2. **No rate limiting** -- API endpoints have no built-in rate limiting. Use nginx rate limiting for production.

3. **No email verification** -- Institution and vendor self-registration does not require email verification. Relies on `moderation_enabled` setting for admin approval.

4. **Vendor type schema mismatch** -- `vendor_type` is defined as ENUM in the schema but treated as JSON array in the service layer. This works because MySQL stores the raw value, but it means the column cannot enforce valid values at the database level for multi-select use cases.

5. **No file cleanup on entity delete** -- When entities are deleted, attachment database records are removed but physical files on disk may not be cleaned up (depending on the delete cascade path).

6. **OAuth tokens stored unencrypted** -- The `access_token_encrypted` and `refresh_token_encrypted` columns accept values directly without actual encryption. The column names suggest encryption should be implemented.

7. **Newsletter sending is synchronous** -- The `NewsletterService::send()` method sends emails inline in a loop. For large subscriber lists, this should be converted to a background job.

---

## 17. File Sizes

| Component | Lines | Description |
|-----------|-------|-------------|
| actions.class.php | 4400 | All action methods |
| install.sql | 728 | Database schema + seed data |
| ahgRegistryPluginConfiguration.class.php | 195 | Routes + autoloader |
| InstitutionService.php | 344 | Institution service |
| VendorService.php | 410 | Vendor service |
| SoftwareService.php | 460 | Software + releases |
| UserGroupService.php | 526 | Groups + membership |
| DiscussionService.php | 479 | Discussions + replies |
| BlogService.php | 390 | Blog + publishing |
| SyncService.php | 360 | Sync API |
| RegistrySearchService.php | 448 | Unified search |
| ReviewService.php | 179 | Reviews + ratings |
| RelationshipService.php | 279 | Vendor-institution + institution-software |
| InstanceService.php | 195 | Instances + sync tokens |
| ContactService.php | 141 | Polymorphic contacts |
| AttachmentService.php | 232 | File uploads |
| GitIntegrationService.php | 397 | GitHub/GitLab integration |
| RegistryImportService.php | 552 | WordPress import |
| OAuthService.php | 497 | Social login (5 providers) |
| NewsletterService.php | 343 | Newsletter + SMTP |
| layout_registry.php | 355 | Standalone layout |
| registry.css | ~500 | Custom styles |
| InstitutionRepository.php | 205 | Institution data access |
| VendorRepository.php | 168 | Vendor data access |
| Templates | ~100 files | PHP templates |
