# ahgStatisticsPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Analytics & Reporting
**Dependencies:** atom-framework

---

## Overview

Usage statistics tracking system for AtoM, providing page view and download analytics with geographic data, bot filtering, pre-aggregation for performance, and comprehensive reporting dashboards. Inspired by DSpace's SOLR statistics architecture.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                     ahgStatisticsPlugin                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                   Event Collection                            │ │
│  │                                                               │ │
│  │   HTTP Request ──▶ response.filter_content event              │ │
│  │                           │                                   │ │
│  │                           ▼                                   │ │
│  │                   ┌───────────────┐                           │ │
│  │                   │  Bot Filter   │─── Bot? ──▶ Mark as bot   │ │
│  │                   └───────┬───────┘                           │ │
│  │                           │ Human                             │ │
│  │                           ▼                                   │ │
│  │                   ┌───────────────┐                           │ │
│  │                   │  GeoIP Lookup │──▶ Country/City           │ │
│  │                   └───────┬───────┘                           │ │
│  │                           │                                   │ │
│  │                           ▼                                   │ │
│  │                   ┌───────────────┐                           │ │
│  │                   │ Log to        │                           │ │
│  │                   │ ahg_usage_event│                          │ │
│  │                   └───────────────┘                           │ │
│  │                                                               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│                              │ Nightly cron                         │
│                              ▼                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                   Aggregation Layer                           │ │
│  │                                                               │ │
│  │   ahg_usage_event ──▶ ahg_statistics_daily                    │ │
│  │                   ──▶ ahg_statistics_monthly                  │ │
│  │                                                               │ │
│  │   • Group by date, object, repository                         │ │
│  │   • Calculate totals and unique visitors                      │ │
│  │   • Country breakdown                                         │ │
│  │                                                               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│                              ▼                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                   Reporting Layer                             │ │
│  │                                                               │ │
│  │   Dashboard ◀── Query aggregates for fast response            │ │
│  │   Reports   ◀── Date filtering, grouping, export              │ │
│  │   CLI       ◀── statistics:report command                     │ │
│  │                                                               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_b48be555.png)
```

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────────┐
│          ahg_usage_event                │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│    event_type ENUM('view','download')   │
│    object_id INT NOT NULL               │
│    object_type VARCHAR(100) DEFAULT     │
│                'information_object'     │
│ FK repository_id INT NULL               │
│    ip_address VARCHAR(45)               │
│    ip_hash VARCHAR(64) NULL             │
│    user_agent VARCHAR(500)              │
│    session_id VARCHAR(128)              │
│    country_code CHAR(2) NULL            │
│    city VARCHAR(100) NULL               │
│    is_bot TINYINT(1) DEFAULT 0          │
│    bot_name VARCHAR(100) NULL           │
│ FK user_id INT NULL                     │
│    referrer VARCHAR(500) NULL           │
│    created_at TIMESTAMP DEFAULT NOW()   │
├─────────────────────────────────────────┤
│ IDX idx_event_date (created_at)         │
│ IDX idx_event_object (object_id, type)  │
│ IDX idx_event_repo (repository_id)      │
│ IDX idx_event_bot (is_bot)              │
│ IDX idx_event_country (country_code)    │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│        ahg_statistics_daily             │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│    stat_date DATE NOT NULL              │
│    object_id INT NULL                   │
│    object_type VARCHAR(100) DEFAULT     │
│                'information_object'     │
│ FK repository_id INT NULL               │
│    event_type ENUM('view','download')   │
│    total_count INT DEFAULT 0            │
│    unique_visitors INT DEFAULT 0        │
│    bot_count INT DEFAULT 0              │
│    country_data JSON NULL               │
│    created_at TIMESTAMP                 │
├─────────────────────────────────────────┤
│ UNIQUE KEY (stat_date, object_id,       │
│             object_type, event_type,    │
│             repository_id)              │
│ IDX idx_daily_date (stat_date)          │
│ IDX idx_daily_object (object_id)        │
│ IDX idx_daily_repo (repository_id)      │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│       ahg_statistics_monthly            │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│    stat_month CHAR(7) NOT NULL          │
│    object_id INT NULL                   │
│    object_type VARCHAR(100) DEFAULT     │
│                'information_object'     │
│ FK repository_id INT NULL               │
│    event_type ENUM('view','download')   │
│    total_count INT DEFAULT 0            │
│    unique_visitors INT DEFAULT 0        │
│    country_data JSON NULL               │
│    created_at TIMESTAMP                 │
├─────────────────────────────────────────┤
│ UNIQUE KEY (stat_month, object_id,      │
│             object_type, event_type,    │
│             repository_id)              │
│ IDX idx_monthly_month (stat_month)      │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│            ahg_bot_list                 │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│    name VARCHAR(100) NOT NULL           │
│    pattern VARCHAR(255) NOT NULL        │
│    category ENUM('search_engine',       │
│             'social','monitoring',      │
│             'crawler','spam','other')   │
│    is_active TINYINT(1) DEFAULT 1       │
│    created_at TIMESTAMP                 │
├─────────────────────────────────────────┤
│ IDX idx_bot_active (is_active)          │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│        ahg_statistics_config            │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│    config_key VARCHAR(100) NOT NULL     │
│    config_value TEXT                    │
│    updated_at TIMESTAMP                 │
├─────────────────────────────────────────┤
│ UNIQUE KEY (config_key)                 │
└─────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_0e89852f.png)
```

---

## Service Layer

### StatisticsService

**Location:** `lib/Services/StatisticsService.php`

```php
namespace AtomExtensions\Statistics;

use Illuminate\Database\Capsule\Manager as DB;

class StatisticsService
{
    /**
     * Log a page view event
     *
     * @param string $objectType Object type
     * @param int $objectId Object ID
     * @param array $context Additional context
     * @return bool Success
     */
    public function logView(
        string $objectType,
        int $objectId,
        array $context = []
    ): bool;

    /**
     * Log a download event
     *
     * @param string $objectType Object type
     * @param int $objectId Object ID
     * @param int|null $digitalObjectId Digital object ID
     * @param array $context Additional context
     * @return bool Success
     */
    public function logDownload(
        string $objectType,
        int $objectId,
        ?int $digitalObjectId = null,
        array $context = []
    ): bool;

    /**
     * Check if user agent is a bot
     *
     * @param string $userAgent User agent string
     * @return array|null Bot info or null if not a bot
     */
    public function detectBot(string $userAgent): ?array;

    /**
     * Perform GeoIP lookup
     *
     * @param string $ipAddress IP address
     * @return array|null Location data
     */
    public function geoipLookup(string $ipAddress): ?array;

    /**
     * Get dashboard statistics
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Statistics
     */
    public function getDashboardStats(
        string $startDate,
        string $endDate
    ): array;

    /**
     * Get view statistics over time
     *
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param string $groupBy Grouping (day, month)
     * @return array Time series data
     */
    public function getViewsOverTime(
        string $startDate,
        string $endDate,
        string $groupBy = 'day'
    ): array;

    /**
     * Get top items by views or downloads
     *
     * @param string $eventType Event type (view, download)
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param int $limit Maximum results
     * @return array Top items
     */
    public function getTopItems(
        string $eventType,
        string $startDate,
        string $endDate,
        int $limit = 50
    ): array;

    /**
     * Get geographic breakdown
     *
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Country statistics
     */
    public function getGeographicStats(
        string $startDate,
        string $endDate
    ): array;

    /**
     * Run daily aggregation
     *
     * @param string|null $date Date to aggregate (default: yesterday)
     * @return int Records created
     */
    public function aggregateDaily(?string $date = null): int;

    /**
     * Run monthly aggregation
     *
     * @param string|null $month Month to aggregate (Y-m)
     * @return int Records created
     */
    public function aggregateMonthly(?string $month = null): int;

    /**
     * Cleanup old raw events
     *
     * @param int $days Retention period
     * @return int Records deleted
     */
    public function cleanupOldEvents(int $days = 90): int;

    /**
     * Export statistics to CSV
     *
     * @param string $type Report type
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param string $outputPath Output file path
     * @return bool Success
     */
    public function exportToCsv(
        string $type,
        string $startDate,
        string $endDate,
        string $outputPath
    ): bool;
}
```

---

## Event Collection

### Automatic Tracking

The plugin uses Symfony's `response.filter_content` event to automatically track page views:

```php
// In ahgStatisticsPluginConfiguration::initialize()
$this->dispatcher->connect(
    'response.filter_content',
    [$this, 'trackPageView']
);

public function trackPageView(sfEvent $event, $content)
{
    $context = sfContext::getInstance();
    $module = $context->getModuleName();
    $action = $context->getActionName();

    // Only track information object views
    if ($module === 'informationobject' && $action === 'index') {
        $request = $context->getRequest();
        $slug = $request->getParameter('slug');

        if ($slug) {
            $object = QubitInformationObject::getBySlug($slug);
            if ($object) {
                $service = new StatisticsService();
                $service->logView('information_object', $object->id);
            }
        }
    }

    return $content;
}
```

### Manual Tracking

```php
// Programmatic view tracking
$service = new StatisticsService();
$service->logView('information_object', $objectId, [
    'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
]);

// Download tracking
$service->logDownload('digital_object', $objectId, $digitalObjectId);
```

---

## Bot Detection

### Detection Algorithm

```php
public function detectBot(string $userAgent): ?array
{
    // Load active bot patterns
    $patterns = DB::table('ahg_bot_list')
        ->where('is_active', 1)
        ->get();

    foreach ($patterns as $bot) {
        if (preg_match('/' . $bot->pattern . '/i', $userAgent)) {
            return [
                'name' => $bot->name,
                'category' => $bot->category,
            ];
        }
    }

    return null;
}
```

### Default Bot Patterns

The plugin installs 30 default bot patterns:

| Category | Examples |
|----------|----------|
| Search Engines | Googlebot, Bingbot, Yandex, Baidu, DuckDuckBot |
| Social Media | Facebook, Twitter, LinkedIn, Pinterest |
| Monitoring | Pingdom, UptimeRobot, Site24x7 |
| SEO Tools | SEMrush, Ahrefs, Moz, Screaming Frog |
| Archivers | Archive.org, Wayback Machine |
| General | curl, wget, Python requests, libwww |

---

## GeoIP Integration

### MaxMind GeoLite2

```php
public function geoipLookup(string $ipAddress): ?array
{
    $dbPath = sfConfig::get(
        'app_geoip_database',
        '/usr/share/GeoIP/GeoLite2-City.mmdb'
    );

    if (!file_exists($dbPath)) {
        return null;
    }

    try {
        // Using MaxMind's GeoIP2 PHP library
        $reader = new \GeoIp2\Database\Reader($dbPath);
        $record = $reader->city($ipAddress);

        return [
            'country_code' => $record->country->isoCode,
            'country_name' => $record->country->name,
            'city' => $record->city->name,
            'latitude' => $record->location->latitude,
            'longitude' => $record->location->longitude,
        ];
    } catch (\Exception $e) {
        return null;
    }
}
```

### Fallback (No GeoIP)

```php
// If GeoIP not available, use IP-based country API
public function geoipFallback(string $ipAddress): ?array
{
    // Only called if MaxMind DB not installed
    // Uses free ip-api.com as fallback
    $response = @file_get_contents(
        "http://ip-api.com/json/{$ipAddress}?fields=countryCode,country,city"
    );

    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['countryCode'])) {
            return [
                'country_code' => $data['countryCode'],
                'country_name' => $data['country'],
                'city' => $data['city'] ?? null,
            ];
        }
    }

    return null;
}
```

---

## Aggregation System

### Daily Aggregation

```php
public function aggregateDaily(?string $date = null): int
{
    $date = $date ?? date('Y-m-d', strtotime('-1 day'));

    // Delete existing aggregates for this date (re-aggregation)
    DB::table('ahg_statistics_daily')
        ->where('stat_date', $date)
        ->delete();

    // Aggregate views
    $sql = "
        INSERT INTO ahg_statistics_daily
            (stat_date, object_id, object_type, repository_id,
             event_type, total_count, unique_visitors, bot_count, country_data)
        SELECT
            DATE(created_at) as stat_date,
            object_id,
            object_type,
            repository_id,
            event_type,
            COUNT(*) as total_count,
            COUNT(DISTINCT COALESCE(ip_hash, ip_address)) as unique_visitors,
            SUM(is_bot) as bot_count,
            JSON_OBJECTAGG(
                COALESCE(country_code, 'XX'),
                country_count
            ) as country_data
        FROM ahg_usage_event
        WHERE DATE(created_at) = ?
        GROUP BY stat_date, object_id, object_type, repository_id, event_type
    ";

    return DB::affectingStatement($sql, [$date]);
}
```

### Monthly Aggregation

```php
public function aggregateMonthly(?string $month = null): int
{
    $month = $month ?? date('Y-m', strtotime('-1 month'));

    // Aggregate from daily stats
    $sql = "
        INSERT INTO ahg_statistics_monthly
            (stat_month, object_id, object_type, repository_id,
             event_type, total_count, unique_visitors, country_data)
        SELECT
            ?,
            object_id,
            object_type,
            repository_id,
            event_type,
            SUM(total_count),
            SUM(unique_visitors),
            -- Merge country JSON
            JSON_OBJECTAGG(k, v)
        FROM ahg_statistics_daily
        WHERE stat_date BETWEEN ? AND ?
        GROUP BY object_id, object_type, repository_id, event_type
        ON DUPLICATE KEY UPDATE
            total_count = VALUES(total_count),
            unique_visitors = VALUES(unique_visitors)
    ";

    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));

    return DB::affectingStatement($sql, [$month, $startDate, $endDate]);
}
```

---

## CLI Tasks

### statistics:aggregate

**Location:** `lib/task/statisticsAggregateTask.class.php`

```bash
# Run all aggregations
php symfony statistics:aggregate --all

# Options
--daily            # Run daily aggregation only
--monthly          # Run monthly aggregation only
--cleanup          # Cleanup old raw events
--days=N           # Retention days for cleanup (default: 90)
--backfill=N       # Backfill N days of daily aggregates
--date=YYYY-MM-DD  # Specific date for daily aggregation
--month=YYYY-MM    # Specific month for monthly aggregation
```

### statistics:report

**Location:** `lib/task/statisticsReportTask.class.php`

```bash
# Summary report
php symfony statistics:report

# Options
--type=TYPE        # Report type: summary, views, downloads, top_items, geographic
--start=DATE       # Start date (YYYY-MM-DD)
--end=DATE         # End date (YYYY-MM-DD)
--limit=N          # Limit results (for top_items)
--format=FORMAT    # Output: table, csv, json
--output=FILE      # Output file path
```

---

## Module Structure

```
modules/
└── statistics/
    ├── actions/
    │   └── actions.class.php
    │       ├── executeDashboard()     # Main dashboard
    │       ├── executeViews()         # Views report
    │       ├── executeDownloads()     # Downloads report
    │       ├── executeTopItems()      # Top items report
    │       ├── executeGeographic()    # Geographic report
    │       ├── executeRepository()    # Per-repository stats
    │       ├── executeItem()          # Per-item stats
    │       ├── executeExport()        # CSV export
    │       ├── executeAdmin()         # Settings
    │       └── executeBots()          # Bot list management
    │
    └── templates/
        ├── dashboardSuccess.php       # Main dashboard
        ├── viewsSuccess.php           # Views over time
        ├── downloadsSuccess.php       # Downloads report
        ├── topItemsSuccess.php        # Top items
        ├── geographicSuccess.php      # Geographic data
        ├── repositorySuccess.php      # Repository stats
        ├── itemSuccess.php            # Item detail
        ├── adminSuccess.php           # Settings
        └── botsSuccess.php            # Bot management
```

---

## Configuration Class

**Location:** `config/ahgStatisticsPluginConfiguration.class.php`

```php
class ahgStatisticsPluginConfiguration extends sfPluginConfiguration
{
    public function initialize()
    {
        // Register routes
        $this->dispatcher->connect(
            'routing.load_configuration',
            [$this, 'addRoutes']
        );

        // Register event tracking
        $this->dispatcher->connect(
            'response.filter_content',
            [$this, 'trackPageView']
        );
    }

    public function addRoutes(sfEvent $event)
    {
        $routing = $event->getSubject();

        // Dashboard
        $routing->prependRoute('statistics_dashboard',
            new sfRoute('/statistics',
                ['module' => 'statistics', 'action' => 'dashboard']));

        // Reports
        $routing->prependRoute('statistics_views',
            new sfRoute('/statistics/views',
                ['module' => 'statistics', 'action' => 'views']));

        $routing->prependRoute('statistics_downloads',
            new sfRoute('/statistics/downloads',
                ['module' => 'statistics', 'action' => 'downloads']));

        $routing->prependRoute('statistics_top_items',
            new sfRoute('/statistics/top-items',
                ['module' => 'statistics', 'action' => 'topItems']));

        $routing->prependRoute('statistics_geographic',
            new sfRoute('/statistics/geographic',
                ['module' => 'statistics', 'action' => 'geographic']));

        // Per-entity
        $routing->prependRoute('statistics_repository',
            new sfRoute('/statistics/repository/:id',
                ['module' => 'statistics', 'action' => 'repository']));

        $routing->prependRoute('statistics_item',
            new sfRoute('/statistics/item/:id',
                ['module' => 'statistics', 'action' => 'item']));

        // Export
        $routing->prependRoute('statistics_export',
            new sfRoute('/statistics/export',
                ['module' => 'statistics', 'action' => 'export']));

        // Admin
        $routing->prependRoute('statistics_admin',
            new sfRoute('/statistics/admin',
                ['module' => 'statistics', 'action' => 'admin']));

        $routing->prependRoute('statistics_bots',
            new sfRoute('/statistics/admin/bots',
                ['module' => 'statistics', 'action' => 'bots']));
    }
}
```

---

## Privacy Features

### IP Anonymization

```php
public function anonymizeIp(string $ipAddress): string
{
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // IPv4: Zero last octet
        return preg_replace('/\.\d+$/', '.0', $ipAddress);
    } elseif (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6: Zero last 80 bits
        return preg_replace('/:[\da-f]+:[\da-f]+:[\da-f]+:[\da-f]+:[\da-f]+$/i',
            ':0:0:0:0:0', $ipAddress);
    }
    return $ipAddress;
}

public function hashIp(string $ipAddress): string
{
    $salt = sfConfig::get('app_statistics_ip_salt', 'default_salt');
    return hash('sha256', $ipAddress . $salt);
}
```

### Data Retention

```php
public function cleanupOldEvents(int $days = 90): int
{
    $cutoff = date('Y-m-d', strtotime("-{$days} days"));

    return DB::table('ahg_usage_event')
        ->where('created_at', '<', $cutoff)
        ->delete();
}
```

---

## Performance Considerations

### Query Strategy

```
┌─────────────────────────────────────────────────────────────────────┐
│                    QUERY STRATEGY                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   Dashboard (< 31 days)                                             │
│   └── Query: ahg_statistics_daily                                   │
│   └── Fast: Indexed by date                                         │
│                                                                     │
│   Reports (31-365 days)                                             │
│   └── Query: ahg_statistics_daily with aggregation                  │
│   └── Medium: May need pagination                                   │
│                                                                     │
│   Historical (> 365 days)                                           │
│   └── Query: ahg_statistics_monthly                                 │
│   └── Fast: Already aggregated by month                             │
│                                                                     │
│   Real-time (today)                                                 │
│   └── Query: ahg_usage_event (filtered)                             │
│   └── Slower but accurate                                           │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_c1b5b4e6.png)
```

### Indexes

```sql
-- Event table indexes
CREATE INDEX idx_event_date ON ahg_usage_event(created_at);
CREATE INDEX idx_event_object ON ahg_usage_event(object_id, object_type);
CREATE INDEX idx_event_repo ON ahg_usage_event(repository_id);
CREATE INDEX idx_event_bot ON ahg_usage_event(is_bot);
CREATE INDEX idx_event_type ON ahg_usage_event(event_type);

-- Daily aggregate indexes
CREATE INDEX idx_daily_date ON ahg_statistics_daily(stat_date);
CREATE INDEX idx_daily_object ON ahg_statistics_daily(object_id);
CREATE INDEX idx_daily_repo ON ahg_statistics_daily(repository_id);

-- Monthly aggregate indexes
CREATE INDEX idx_monthly_month ON ahg_statistics_monthly(stat_month);
```

### Estimated Table Sizes

| Table | Retention | Est. Rows/Year | Size |
|-------|-----------|----------------|------|
| ahg_usage_event | 90 days | 500K-5M | 50-500MB |
| ahg_statistics_daily | Permanent | 365 × objects | 10-50MB |
| ahg_statistics_monthly | Permanent | 12 × objects | 1-5MB |
| ahg_bot_list | Permanent | ~50 | <1MB |

---

## Installation

### Database Migration

```bash
mysql -u root archive < plugins/ahgStatisticsPlugin/database/install.sql
```

### Enable Plugin

```bash
php bin/atom extension:enable ahgStatisticsPlugin
php symfony cc
```

### GeoIP Setup (Optional)

```bash
# Download MaxMind GeoLite2 database
# Place at: /usr/share/GeoIP/GeoLite2-City.mmdb
```

### Configure Cron

```bash
# Daily aggregation at 2am
0 2 * * * cd /usr/share/nginx/archive && php symfony statistics:aggregate --daily

# Monthly aggregation on 1st
0 3 1 * * cd /usr/share/nginx/archive && php symfony statistics:aggregate --monthly

# Weekly cleanup
0 4 * * 0 cd /usr/share/nginx/archive && php symfony statistics:aggregate --cleanup
```

---

## Configuration Options

### Plugin Settings (ahg_statistics_config)

| Key | Default | Description |
|-----|---------|-------------|
| `tracking_enabled` | `1` | Enable/disable tracking |
| `track_views` | `1` | Track page views |
| `track_downloads` | `1` | Track downloads |
| `bot_filtering` | `1` | Enable bot filtering |
| `anonymize_ip` | `0` | Anonymize IP addresses |
| `retention_days` | `90` | Raw event retention |
| `geoip_enabled` | `1` | Enable GeoIP lookup |
| `geoip_database` | `/usr/share/GeoIP/GeoLite2-City.mmdb` | GeoIP DB path |

---

## API Endpoints

### REST Endpoints (if ahgAPIPlugin enabled)

```
GET /api/statistics/dashboard       # Dashboard summary
GET /api/statistics/views           # Views data
GET /api/statistics/downloads       # Downloads data
GET /api/statistics/top-items       # Top items
GET /api/statistics/geographic      # Geographic data
GET /api/statistics/repository/:id  # Repository stats
GET /api/statistics/item/:id        # Item stats

# Query parameters for all:
?start=YYYY-MM-DD
&end=YYYY-MM-DD
&group=day|month
&limit=N
```

---

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| No statistics | Tracking disabled | Enable in Settings |
| Bot traffic high | Missing patterns | Update bot list |
| No GeoIP data | Database missing | Install MaxMind GeoLite2 |
| Slow dashboard | Missing aggregates | Run statistics:aggregate --all |
| Stale data | Cron not running | Enable daily cron job |
| High disk usage | Old events | Run cleanup job |

---

## Related Documentation

- [Statistics User Guide](../statistics-user-guide.md)
- [Privacy Plugin](ahgPrivacyPlugin.md)
- [API Plugin](ahgAPIPlugin.md)
