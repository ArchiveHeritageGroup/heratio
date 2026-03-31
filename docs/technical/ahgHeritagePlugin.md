# ahgHeritagePlugin - Technical Documentation

**Version:** 1.2.0
**Category:** Public Discovery
**Dependencies:** atom-framework, ahgThemeB5Plugin

---

## Overview

A comprehensive heritage discovery platform providing a visually engaging public interface with community contributions, access mediation, custodian management, and analytics. Designed for GLAM institutions seeking to increase public engagement while maintaining institutional control.

---

## Architecture

```
+---------------------------------------------------------------------+
|                       ahgHeritagePlugin                              |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------+   +---------------------------+      |
|  |    Public Interface       |   |    Admin Interface        |      |
|  +---------------------------+   +---------------------------+      |
|  | Landing Page              |   | Dashboard                 |      |
|  | Search/Discovery          |   | Configuration             |      |
|  | Collections               |   | Feature Toggles           |      |
|  | Timeline                  |   | Branding                  |      |
|  | Contributions             |   | User Management           |      |
|  +---------------------------+   +---------------------------+      |
|              |                              |                       |
|              v                              v                       |
|  +---------------------------+   +---------------------------+      |
|  |   Contributor System      |   |   Access Control          |      |
|  +---------------------------+   +---------------------------+      |
|  | Registration/Auth         |   | Access Requests           |      |
|  | Trust Levels              |   | Embargoes                 |      |
|  | Points/Badges             |   | POPIA Flags               |      |
|  | Contribution Workflow     |   | Trust Levels              |      |
|  +---------------------------+   +---------------------------+      |
|              |                              |                       |
|              v                              v                       |
|  +---------------------------+   +---------------------------+      |
|  |   Custodian Tools         |   |   Analytics Engine        |      |
|  +---------------------------+   +---------------------------+      |
|  | Batch Operations          |   | Search Analytics          |      |
|  | Audit Trail               |   | Content Performance       |      |
|  | Review Queue              |   | Trend Analysis            |      |
|  | Item Management           |   | Alerts System             |      |
|  +---------------------------+   +---------------------------+      |
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                    Database Layer (58 Tables)                 |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Overview

```
+---------------------------------------------------------------------+
|                    HERITAGE PLUGIN TABLES                            |
+---------------------------------------------------------------------+
|                                                                     |
|  LANDING PAGE CONFIGURATION                                         |
|  +-------------------------+    +-------------------------+         |
|  | heritage_landing_config |    | heritage_hero_slide     |         |
|  | heritage_filter_type    |    | heritage_hero_image     |         |
|  | heritage_institution_   |    | heritage_featured_      |         |
|  |   filter                |    |   collection            |         |
|  | heritage_filter_value   |    | heritage_curated_story  |         |
|  +-------------------------+    +-------------------------+         |
|                                                                     |
|  DISCOVERY ENGINE                                                   |
|  +-------------------------+    +-------------------------+         |
|  | heritage_discovery_log  |    | heritage_learned_term   |         |
|  | heritage_discovery_     |    | heritage_search_        |         |
|  |   click                 |    |   suggestion            |         |
|  | heritage_ranking_config |    | heritage_entity_cache   |         |
|  +-------------------------+    +-------------------------+         |
|                                                                     |
|  EXPLORE & TIMELINE                                                 |
|  +-------------------------+    +-------------------------+         |
|  | heritage_explore_       |    | heritage_timeline_      |         |
|  |   category              |    |   period                |         |
|  +-------------------------+    +-------------------------+         |
|                                                                     |
|  CONTRIBUTOR SYSTEM                                                 |
|  +-------------------------+    +-------------------------+         |
|  | heritage_contributor    |    | heritage_contribution   |         |
|  | heritage_contribution_  |    | heritage_contribution_  |         |
|  |   type                  |    |   version               |         |
|  | heritage_contributor_   |    | heritage_contributor_   |         |
|  |   session               |    |   badge                 |         |
|  | heritage_contributor_   |                                        |
|  |   badge_award           |                                        |
|  +-------------------------+                                        |
|                                                                     |
|  ACCESS CONTROL                                                     |
|  +-------------------------+    +-------------------------+         |
|  | heritage_trust_level    |    | heritage_access_request |         |
|  | heritage_user_trust     |    | heritage_access_rule    |         |
|  | heritage_purpose        |    | heritage_popia_flag     |         |
|  | heritage_embargo        |                                        |
|  +-------------------------+                                        |
|                                                                     |
|  ADMINISTRATION                                                     |
|  +-------------------------+    +-------------------------+         |
|  | heritage_feature_toggle |    | heritage_batch_job      |         |
|  | heritage_branding_      |    | heritage_batch_item     |         |
|  |   config                |    | heritage_audit_log      |         |
|  +-------------------------+    +-------------------------+         |
|                                                                     |
|  ANALYTICS                                                          |
|  +-------------------------+    +-------------------------+         |
|  | heritage_analytics_     |    | heritage_analytics_     |         |
|  |   daily                 |    |   search                |         |
|  | heritage_analytics_     |    | heritage_analytics_     |         |
|  |   content               |    |   alert                 |         |
|  | heritage_content_       |                                        |
|  |   quality               |                                        |
|  +-------------------------+                                        |
|                                                                     |
+---------------------------------------------------------------------+
```

### Core Tables

#### heritage_landing_config
Institution landing page configuration.

```sql
CREATE TABLE heritage_landing_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Hero section
    hero_tagline VARCHAR(500) DEFAULT 'Discover our collections',
    hero_subtext VARCHAR(500) DEFAULT NULL,
    hero_search_placeholder VARCHAR(255) DEFAULT 'What are you looking for?',
    suggested_searches JSON DEFAULT NULL,

    -- Hero media
    hero_media JSON DEFAULT NULL,
    hero_rotation_seconds INT DEFAULT 8,
    hero_effect ENUM('kenburns', 'fade', 'none') DEFAULT 'kenburns',

    -- Sections enabled
    show_curated_stories TINYINT(1) DEFAULT 1,
    show_community_activity TINYINT(1) DEFAULT 1,
    show_filters TINYINT(1) DEFAULT 1,
    show_stats TINYINT(1) DEFAULT 1,
    show_recent_additions TINYINT(1) DEFAULT 1,

    -- Stats and styling
    stats_config JSON DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#0d6efd',
    secondary_color VARCHAR(7) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id)
);
```

#### heritage_contributor
Public user accounts for community contributors.

```sql
CREATE TABLE heritage_contributor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    bio TEXT DEFAULT NULL,

    -- Trust and verification
    trust_level ENUM('new', 'contributor', 'trusted', 'expert') DEFAULT 'new',
    email_verified TINYINT(1) DEFAULT 0,
    email_verify_token VARCHAR(100) DEFAULT NULL,
    email_verify_expires TIMESTAMP NULL,

    -- Password reset
    password_reset_token VARCHAR(100) DEFAULT NULL,
    password_reset_expires TIMESTAMP NULL,

    -- Statistics
    total_contributions INT DEFAULT 0,
    approved_contributions INT DEFAULT 0,
    rejected_contributions INT DEFAULT 0,
    points INT DEFAULT 0,
    badges JSON DEFAULT NULL,

    -- Preferences and status
    preferences JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    last_contribution_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_trust_level (trust_level),
    INDEX idx_points (points DESC)
);
```

#### heritage_contribution
Individual contributions from community users.

```sql
CREATE TABLE heritage_contribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    information_object_id INT NOT NULL,
    contribution_type_id INT NOT NULL,
    content JSON NOT NULL,

    -- Review workflow
    status ENUM('pending', 'approved', 'rejected', 'superseded') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT DEFAULT NULL,

    -- Points and versioning
    points_awarded INT DEFAULT 0,
    version_number INT DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_contributor (contributor_id),
    INDEX idx_object (information_object_id),
    INDEX idx_status (status),

    CONSTRAINT fk_heritage_contribution_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_heritage_contribution_type
        FOREIGN KEY (contribution_type_id) REFERENCES heritage_contribution_type(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);
```

#### heritage_access_request
User access requests for restricted items.

```sql
CREATE TABLE heritage_access_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    object_id INT NOT NULL,
    purpose_id INT DEFAULT NULL,
    purpose_text VARCHAR(255) DEFAULT NULL,
    justification TEXT DEFAULT NULL,
    research_description TEXT DEFAULT NULL,
    institution_affiliation VARCHAR(255) DEFAULT NULL,

    -- Decision workflow
    status ENUM('pending', 'approved', 'denied', 'expired', 'withdrawn') DEFAULT 'pending',
    decision_by INT DEFAULT NULL,
    decision_at TIMESTAMP NULL,
    decision_notes TEXT DEFAULT NULL,

    -- Access validity
    valid_from DATE DEFAULT NULL,
    valid_until DATE DEFAULT NULL,
    access_granted JSON DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_object (object_id),
    INDEX idx_status (status)
);
```

#### heritage_embargo
Embargo restrictions on objects.

```sql
CREATE TABLE heritage_embargo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    embargo_type ENUM('full', 'digital_only', 'metadata_hidden') DEFAULT 'full',
    reason TEXT DEFAULT NULL,
    legal_basis VARCHAR(255) DEFAULT NULL,

    -- Date range
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    auto_release TINYINT(1) DEFAULT 1,
    notify_on_release TINYINT(1) DEFAULT 1,

    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_end_date (end_date),
    INDEX idx_auto_release (auto_release, end_date)
);
```

#### heritage_popia_flag
POPIA/GDPR privacy flags on objects.

```sql
CREATE TABLE heritage_popia_flag (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    flag_type ENUM(
        'personal_info', 'sensitive', 'children', 'health',
        'biometric', 'criminal', 'financial', 'political',
        'religious', 'sexual'
    ) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    description TEXT DEFAULT NULL,
    affected_fields JSON DEFAULT NULL,

    -- Detection and resolution
    detected_by ENUM('automatic', 'manual', 'review') DEFAULT 'manual',
    is_resolved TINYINT(1) DEFAULT 0,
    resolution_notes TEXT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_at TIMESTAMP NULL,

    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_flag_type (flag_type),
    INDEX idx_severity (severity),
    INDEX idx_resolved (is_resolved)
);
```

#### heritage_discovery_log
Search analytics and logging.

```sql
CREATE TABLE heritage_discovery_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Query details
    query_text VARCHAR(500) DEFAULT NULL,
    detected_language VARCHAR(10) DEFAULT 'en',
    query_intent VARCHAR(50) DEFAULT NULL,
    parsed_entities JSON DEFAULT NULL,
    expanded_terms JSON DEFAULT NULL,
    filters_applied JSON DEFAULT NULL,

    -- Results
    result_count INT DEFAULT 0,
    click_count INT DEFAULT 0,
    first_click_position INT DEFAULT NULL,

    -- Session info
    user_id INT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    search_duration_ms INT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_created (created_at),
    INDEX idx_query (query_text(100))
);
```

#### heritage_analytics_daily
Daily aggregate metrics.

```sql
CREATE TABLE heritage_analytics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    date DATE NOT NULL,
    metric_type VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2) DEFAULT 0,
    previous_value DECIMAL(15,2) DEFAULT NULL,
    change_percent DECIMAL(10,2) DEFAULT NULL,
    metadata JSON DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_date_metric (institution_id, date, metric_type),
    INDEX idx_date (date),
    INDEX idx_metric_type (metric_type)
);
```

---

## Module Structure

```
ahgHeritagePlugin/
+-- config/
|   +-- ahgHeritagePluginConfiguration.class.php
|   +-- routing.yml
+-- css/
|   +-- heritage.css
+-- data/
|   +-- install.sql
+-- extension.json
+-- modules/
    +-- heritage/
        +-- actions/
        |   +-- actions.class.php
        +-- config/
        |   +-- module.yml
        |   +-- view.yml
        +-- templates/
            +-- _activitySection.php
            +-- _adminSidebar.php
            +-- _contributeButton.php
            +-- _filterCards.php
            +-- _heroSection.php
            +-- _recentAdditions.php
            +-- _statsSection.php
            +-- _storiesSection.php
            +-- adminAccessRequestsSuccess.php
            +-- adminBrandingSuccess.php
            +-- adminConfigSuccess.php
            +-- adminDashboardSuccess.php
            +-- adminEmbargoesSuccess.php
            +-- adminFeaturedCollectionsSuccess.php
            +-- adminFeaturesSuccess.php
            +-- adminHeroSlidesSuccess.php
            +-- adminPopiaSuccess.php
            +-- adminUsersSuccess.php
            +-- analyticsAlertsSuccess.php
            +-- analyticsContentSuccess.php
            +-- analyticsDashboardSuccess.php
            +-- analyticsSearchSuccess.php
            +-- collectionsSuccess.php
            +-- contributeSuccess.php
            +-- contributorLoginSuccess.php
            +-- contributorProfileSuccess.php
            +-- contributorRegisterSuccess.php
            +-- contributorVerifySuccess.php
            +-- creatorsSuccess.php
            +-- custodianBatchSuccess.php
            +-- custodianDashboardSuccess.php
            +-- custodianHistorySuccess.php
            +-- custodianItemSuccess.php
            +-- exploreSuccess.php
            +-- landingSuccess.php
            +-- leaderboardSuccess.php
            +-- myAccessRequestsSuccess.php
            +-- myContributionsSuccess.php
            +-- requestAccessSuccess.php
            +-- reviewContributionSuccess.php
            +-- reviewQueueSuccess.php
            +-- searchSuccess.php
            +-- timelineSuccess.php
            +-- trendingSuccess.php
```

---

## Routes

### Public Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/heritage` | landing | Public landing page |
| `/heritage/search` | search | Search interface |
| `/heritage/explore` | explore | Browse categories |
| `/heritage/explore/:category` | explore | Category browse |
| `/heritage/timeline` | timeline | Timeline navigation |
| `/heritage/timeline/:period_id` | timeline | Period items |
| `/heritage/creators` | creators | Browse by creator |
| `/heritage/collections` | collections | Featured collections |
| `/heritage/trending` | trending | Trending items |

### Contributor Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/heritage/login` | contributorLogin | Contributor login |
| `/heritage/register` | contributorRegister | Registration |
| `/heritage/logout` | contributorLogout | Logout |
| `/heritage/verify/:token` | contributorVerify | Email verification |
| `/heritage/contribute/:slug` | contribute | Contribution form |
| `/heritage/my/contributions` | myContributions | My contributions |
| `/heritage/my/access-requests` | myAccessRequests | My requests |
| `/heritage/contributor/:id` | contributorProfile | Profile page |
| `/heritage/leaderboard` | leaderboard | Leaderboard |

### Access Control Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/heritage/access/request/:slug` | requestAccess | Request access form |

### Admin Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/heritage/admin` | adminDashboard | Admin dashboard |
| `/heritage/admin/config` | adminConfig | Landing config |
| `/heritage/admin/features` | adminFeatures | Feature toggles |
| `/heritage/admin/branding` | adminBranding | Branding settings |
| `/heritage/admin/users` | adminUsers | User management |
| `/heritage/admin/hero-slides` | adminHeroSlides | Hero image management |
| `/heritage/admin/featured-collections` | adminFeaturedCollections | Collection curation |
| `/heritage/admin/access-requests` | adminAccessRequests | Request review |
| `/heritage/admin/embargoes` | adminEmbargoes | Embargo management |
| `/heritage/admin/popia` | adminPopia | POPIA flags |

### Custodian Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/heritage/custodian` | custodianDashboard | Custodian dashboard |
| `/heritage/custodian/:slug` | custodianItem | Item management |
| `/heritage/custodian/batch` | custodianBatch | Batch operations |
| `/heritage/custodian/history` | custodianHistory | Audit trail |
| `/heritage/review` | reviewQueue | Review queue |
| `/heritage/review/:id` | reviewContribution | Review contribution |

### Analytics Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/heritage/analytics` | analyticsDashboard | Analytics overview |
| `/heritage/analytics/search` | analyticsSearch | Search insights |
| `/heritage/analytics/content` | analyticsContent | Content metrics |
| `/heritage/analytics/alerts` | analyticsAlerts | System alerts |

### API Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/heritage/api/landing` | GET | Landing page data |
| `/heritage/api/discover` | GET/POST | Search API |
| `/heritage/api/autocomplete` | GET | Search autocomplete |
| `/heritage/api/click` | POST | Click tracking |
| `/heritage/api/dwell` | POST | Dwell time tracking |
| `/heritage/api/analytics` | GET | Analytics data |
| `/heritage/api/contribution/submit` | POST | Submit contribution |
| `/heritage/api/contribution/:id` | GET | Contribution status |
| `/heritage/api/suggest-tags` | GET | Tag suggestions |
| `/heritage/api/hero-slides` | GET | Hero slides data |
| `/heritage/api/featured-collections` | GET | Collections data |
| `/heritage/api/explore-categories` | GET | Categories data |
| `/heritage/api/explore/:category/items` | GET | Category items |
| `/heritage/api/timeline-periods` | GET | Timeline periods |
| `/heritage/api/timeline/:period_id/items` | GET | Period items |

---

## Configuration

### Plugin Configuration

Located in `ahgHeritagePluginConfiguration.class.php`:

```php
class ahgHeritagePluginConfiguration extends sfPluginConfiguration
{
    public static $summary = 'Heritage discovery platform with contributor system, custodian management, and analytics';
    public static $version = '1.1.0';

    public function initialize()
    {
        // Load helpers
        $this->dispatcher->connect('context.load_factories', [$this, 'contextLoadFactories']);

        // Homepage redirect for unauthenticated users
        $this->dispatcher->connect('controller.change_action', [$this, 'redirectHomepageToHeritage']);

        // Enable heritage module
        $enabledModules = sfConfig::get('sf_enabled_modules', []);
        $enabledModules[] = 'heritage';
        sfConfig::set('sf_enabled_modules', array_unique($enabledModules));
    }
}
```

### Feature Toggles

Default features configured in `heritage_feature_toggle`:

| Feature Code | Default | Description |
|--------------|---------|-------------|
| community_contributions | true | Enable contributions |
| user_registration | true | Allow registration |
| social_sharing | true | Share buttons |
| downloads | true | File downloads |
| citations | true | Citation generation |
| analytics | true | Usage tracking |
| access_requests | true | Access requests |
| embargoes | true | Embargo system |
| batch_operations | true | Bulk operations |
| audit_trail | true | Change tracking |

### Ranking Configuration

Configurable search ranking weights in `heritage_ranking_config`:

```sql
-- Relevance weights
weight_title_match       DECIMAL(4,3) DEFAULT 1.000
weight_content_match     DECIMAL(4,3) DEFAULT 0.700
weight_identifier_match  DECIMAL(4,3) DEFAULT 0.900
weight_subject_match     DECIMAL(4,3) DEFAULT 0.800
weight_creator_match     DECIMAL(4,3) DEFAULT 0.800

-- Quality weights
weight_has_digital_object   DECIMAL(4,3) DEFAULT 0.300
weight_description_length   DECIMAL(4,3) DEFAULT 0.200
weight_has_dates           DECIMAL(4,3) DEFAULT 0.150
weight_has_subjects        DECIMAL(4,3) DEFAULT 0.150

-- Engagement weights
weight_view_count       DECIMAL(4,3) DEFAULT 0.100
weight_download_count   DECIMAL(4,3) DEFAULT 0.150
weight_citation_count   DECIMAL(4,3) DEFAULT 0.200

-- Boost/penalty
boost_featured      DECIMAL(4,3) DEFAULT 1.500
boost_recent        DECIMAL(4,3) DEFAULT 1.100
penalty_incomplete  DECIMAL(4,3) DEFAULT 0.800
```

---

## Contribution Types

Default contribution types in `heritage_contribution_type`:

| Code | Points | Min Trust | Description |
|------|--------|-----------|-------------|
| transcription | 25 | new | Transcribe documents |
| identification | 15 | new | Identify people/places |
| context | 20 | new | Add historical context |
| correction | 10 | new | Suggest corrections |
| translation | 30 | new | Translate content |
| tag | 5 | new | Add keywords |

---

## Trust Levels

Default trust levels in `heritage_trust_level`:

| Code | Level | Can View Restricted | Can Download | Bulk Download |
|------|-------|---------------------|--------------|---------------|
| anonymous | 0 | No | No | No |
| registered | 1 | No | Yes | No |
| contributor | 2 | No | Yes | No |
| trusted | 3 | Yes | Yes | No |
| moderator | 4 | Yes | Yes | Yes |
| custodian | 5 | Yes | Yes | Yes |

---

## Access Purposes

Default purposes in `heritage_purpose`:

| Code | Requires Approval | Min Trust |
|------|-------------------|-----------|
| personal | No | 0 |
| academic | No | 0 |
| education | No | 0 |
| commercial | Yes | 1 |
| media | Yes | 1 |
| legal | Yes | 1 |
| government | Yes | 1 |
| preservation | No | 2 |

---

## Badge System

Default badges in `heritage_contributor_badge`:

| Code | Criteria Type | Value | Description |
|------|--------------|-------|-------------|
| first_contribution | contribution_count | 1 | First contribution |
| contributor_10 | contribution_count | 10 | 10 contributions |
| contributor_50 | contribution_count | 50 | 50 contributions |
| contributor_100 | contribution_count | 100 | 100 contributions |
| transcriber | type_specific | 25 | 25 transcriptions |
| identifier | type_specific | 25 | 25 identifications |
| historian | type_specific | 25 | 25 context additions |
| perfectionist | approval_rate | 95 | 95% approval rate |

---

## Timeline Periods

Default South African periods in `heritage_timeline_period`:

| Period | Years | Description |
|--------|-------|-------------|
| Pre-Colonial Era | Pre-1652 | San, Khoi, early settlements |
| Dutch Colonial Period | 1652-1795 | VOC settlement |
| British Colonial Era | 1795-1910 | British rule, Great Trek |
| Union of South Africa | 1910-1948 | Union formation, WWI/WWII |
| Apartheid Era | 1948-1994 | Formal apartheid |
| Democratic Era | 1994-Present | Post-apartheid |

---

## Explore Categories

Default categories in `heritage_explore_category`:

| Code | Source Type | Display Style |
|------|-------------|---------------|
| time | field (dates) | timeline |
| place | authority (place) | map |
| people | authority (actor) | grid |
| theme | taxonomy (subject) | grid |
| format | taxonomy (contentType) | grid |
| trending | custom | carousel |

---

## Learned Terms

The system learns term relationships from user behavior:

| Relationship Type | Description |
|-------------------|-------------|
| synonym | Equivalent terms |
| broader | More general term |
| narrower | More specific term |
| related | Associated term |
| spelling | Spelling correction |

Default synonyms include: photo/photograph, doc/document, memo/memorandum, etc.

---

## Compliance Mapping

| Standard | Requirement | Implementation |
|----------|-------------|----------------|
| POPIA | Personal data protection | POPIA flags, access control |
| GDPR | Data subject rights | Access requests, embargoes |
| PAIA | Access to information | Access request workflow |
| NARSSA | Records management | Audit trail, retention |

---

## Integration Points

### Framework Integration

Uses `atom-framework` for:
- Laravel Query Builder
- Service layer
- Controllers (LandingController)

### Theme Integration

Requires `ahgThemeB5Plugin` for:
- Bootstrap 5 styling
- CSS framework
- Responsive layout

### Database Integration

Direct integration with AtoM core tables:
- `information_object` / `information_object_i18n`
- `actor` / `actor_i18n`
- `digital_object`
- `slug`
- `object`
- `relation`

---

## Installation

### SQL Installation

```bash
mysql -u root archive < /path/to/ahgHeritagePlugin/data/install.sql
```

### Enable Plugin

```bash
php bin/atom extension:enable ahgHeritagePlugin
```

### Clear Cache

```bash
php symfony cc
```

---

## Performance Considerations

### Indexes

The plugin creates comprehensive indexes for:
- Search queries (query_text)
- Date filtering (created_at, start_year, end_year)
- Status filtering (status, is_enabled)
- Foreign key relationships

### Caching

Recommended caching strategies:
- Entity cache for frequently accessed data
- Search suggestion cache
- Analytics aggregation cache

### Background Jobs

Batch operations support async processing via `heritage_batch_job`:
- Status: pending, queued, processing, completed, failed
- Progress tracking with item counts
- Error logging

---

## Security Considerations

### Authentication

- Separate contributor authentication from AtoM users
- Password hashing (bcrypt)
- Session token management
- Email verification with token-based confirmation

### Email Verification

Email verification is handled by the `sendVerificationEmail()` method in `actions.class.php`:

```php
protected function sendVerificationEmail(string $email, string $displayName, string $token): bool
{
    $siteName = sfConfig::get('app_siteTitle', 'Heritage Portal');
    $baseUrl = sfConfig::get('app_siteBaseUrl', $this->getRequest()->getUriPrefix());
    $verifyUrl = $baseUrl . url_for([
        'module' => 'heritage',
        'action' => 'contributorVerify',
        'token' => $token,
    ]);

    // Uses sfMail if available, falls back to PHP mail()
    if (class_exists('sfMail')) {
        $mail = new sfMail();
        $mail->initialize();
        $mail->setFrom($fromEmail, $siteName);
        $mail->addAddress($email, $displayName);
        $mail->setSubject('Verify your email - ' . $siteName);
        $mail->setBody($body);
        $mail->send();
    } else {
        // PHP mail() fallback
        mail($email, $subject, $body, implode("\r\n", $headers));
    }
}
```

The email includes:
- Welcome message with display name
- Verification link with unique token
- Link expiry notice (24 hours)
- Site branding

### Authorization

- Trust level-based access control
- Purpose-based request evaluation
- Embargo enforcement
- POPIA flag handling

### Audit Trail

Complete change tracking in `heritage_audit_log`:
- User identification
- Action categorization
- Old/new value capture
- IP address logging
- Session tracking

---

*Part of the AtoM AHG Framework*
