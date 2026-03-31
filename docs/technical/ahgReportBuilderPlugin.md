# ahgReportBuilderPlugin - Technical Documentation

**Version:** 1.0.1
**Category:** Reporting
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Custom report builder providing a drag-and-drop designer interface for creating reports from 40+ data sources. Supports column selection, filtering, sorting, chart visualization, multi-format export (PDF, XLSX, CSV), and automated scheduling with email delivery.

---

## Architecture

```
+---------------------------------------------------------------------+
|                       ahgReportBuilderPlugin                         |
+---------------------------------------------------------------------+
|                                                                      |
|  +---------------------------------------------------------------+  |
|  |                    Symfony Actions Layer                       |  |
|  |  reportBuilderActions.class.php                                |  |
|  |  - CRUD operations for reports                                 |  |
|  |  - Export handlers (CSV, XLSX, PDF)                            |  |
|  |  - API endpoints for designer                                  |  |
|  |  - Schedule management                                         |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Service Layer                               |  |
|  |  +-------------------+  +-------------------+                   |  |
|  |  | ReportBuilder     |  | ReportScheduler   |                   |  |
|  |  | Service           |  |                   |                   |  |
|  |  | - Execute reports |  | - Run due jobs    |                   |  |
|  |  | - Build queries   |  | - Generate files  |                   |  |
|  |  | - Resolve FKs     |  | - Send emails     |                   |  |
|  |  +-------------------+  +-------------------+                   |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Registry Layer                              |  |
|  |  +-------------------+  +-------------------+                   |  |
|  |  | DataSource        |  | ColumnDiscovery   |                   |  |
|  |  | Registry          |  |                   |                   |  |
|  |  | - 40+ sources     |  | - Column metadata |                   |  |
|  |  | - Table configs   |  | - Type mapping    |                   |  |
|  |  +-------------------+  +-------------------+                   |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Database Layer                              |  |
|  |  Laravel Query Builder (Illuminate\Database)                   |  |
|  |  - Dynamic query construction                                  |  |
|  |  - i18n table joins                                            |  |
|  |  - Foreign key resolution                                      |  |
|  +---------------------------------------------------------------+  |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+-----------------------------------+       +-----------------------------------+
|          custom_report            |       |         report_schedule           |
+-----------------------------------+       +-----------------------------------+
| PK id BIGINT                      |<------| PK id BIGINT                      |
|    name VARCHAR(255)              |       | FK custom_report_id BIGINT        |
|    description TEXT               |       |    frequency ENUM                 |
| FK user_id INT                    |       |    day_of_week TINYINT            |
|    is_shared TINYINT              |       |    day_of_month TINYINT           |
|    is_public TINYINT              |       |    time_of_day TIME               |
|    layout JSON                    |       |    output_format ENUM             |
|    data_source VARCHAR(100)       |       |    email_recipients TEXT          |
|    columns JSON                   |       |    last_run DATETIME              |
|    filters JSON                   |       |    next_run DATETIME              |
|    charts JSON                    |       |    is_active TINYINT              |
|    sort_config JSON               |       |    created_at DATETIME            |
|    category VARCHAR(100)          |       |    updated_at DATETIME            |
|    sort_order INT                 |       +-----------------------------------+
|    created_at DATETIME            |                      |
|    updated_at DATETIME            |                      |
+-----------------------------------+                      |
         |                                                 |
         |                                                 v
         |                           +-----------------------------------+
         |                           |          report_archive           |
         |                           +-----------------------------------+
         +-------------------------->| PK id BIGINT                      |
                                     | FK custom_report_id BIGINT        |
                                     | FK schedule_id BIGINT             |
                                     |    file_path VARCHAR(500)         |
                                     |    file_format VARCHAR(10)        |
                                     |    file_size INT                  |
                                     |    generated_at DATETIME          |
                                     | FK generated_by INT               |
                                     |    parameters JSON                |
                                     +-----------------------------------+

+-----------------------------------+
|         dashboard_widget          |
+-----------------------------------+
| PK id BIGINT                      |
| FK user_id INT                    |
| FK custom_report_id BIGINT        |
|    widget_type ENUM               |
|    title VARCHAR(255)             |
|    position_x INT                 |
|    position_y INT                 |
|    width INT                      |
|    height INT                     |
|    config JSON                    |
|    is_active TINYINT              |
|    created_at DATETIME            |
|    updated_at DATETIME            |
+-----------------------------------+
```

### SQL Schema

```sql
-- Custom report templates
CREATE TABLE IF NOT EXISTS custom_report (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    user_id INT,
    is_shared TINYINT(1) DEFAULT 0,
    is_public TINYINT(1) DEFAULT 0,
    layout JSON NOT NULL,
    data_source VARCHAR(100) NOT NULL,
    columns JSON NOT NULL,
    filters JSON,
    charts JSON,
    sort_config JSON,
    category VARCHAR(100) DEFAULT 'General',
    sort_order INT DEFAULT 100,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_custom_report_user (user_id),
    INDEX idx_custom_report_shared (is_shared),
    INDEX idx_custom_report_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scheduled report jobs
CREATE TABLE IF NOT EXISTS report_schedule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    custom_report_id BIGINT UNSIGNED NOT NULL,
    frequency ENUM('daily','weekly','monthly','quarterly') NOT NULL,
    day_of_week TINYINT,
    day_of_month TINYINT,
    time_of_day TIME DEFAULT '08:00:00',
    output_format ENUM('pdf','xlsx','csv') DEFAULT 'pdf',
    email_recipients TEXT,
    last_run DATETIME,
    next_run DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (custom_report_id) REFERENCES custom_report(id) ON DELETE CASCADE,
    INDEX idx_report_schedule_next_run (next_run),
    INDEX idx_report_schedule_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Generated report archive
CREATE TABLE IF NOT EXISTS report_archive (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    custom_report_id BIGINT UNSIGNED,
    schedule_id BIGINT UNSIGNED,
    file_path VARCHAR(500) NOT NULL,
    file_format VARCHAR(10) NOT NULL,
    file_size INT UNSIGNED,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    generated_by INT,
    parameters JSON,
    INDEX idx_report_archive_report (custom_report_id),
    INDEX idx_report_archive_schedule (schedule_id),
    INDEX idx_report_archive_generated (generated_at),
    FOREIGN KEY (custom_report_id) REFERENCES custom_report(id) ON DELETE SET NULL,
    FOREIGN KEY (schedule_id) REFERENCES report_schedule(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dashboard widgets
CREATE TABLE IF NOT EXISTS dashboard_widget (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    custom_report_id BIGINT UNSIGNED,
    widget_type ENUM('table','chart','stat','count') NOT NULL,
    title VARCHAR(255),
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    width INT DEFAULT 4,
    height INT DEFAULT 2,
    config JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (custom_report_id) REFERENCES custom_report(id) ON DELETE CASCADE,
    INDEX idx_dashboard_widget_user (user_id),
    INDEX idx_dashboard_widget_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Data Sources

The plugin supports 40+ data sources organized by category:

### Core Archives
| Key | Label | Table | Has i18n |
|-----|-------|-------|----------|
| information_object | Archival Descriptions | information_object | Yes |
| actor | Authority Records | actor | Yes |
| repository | Repositories | repository | Yes |
| accession | Accessions | accession | Yes |
| physical_object | Physical Storage | physical_object | Yes |
| digital_object | Digital Objects | digital_object | No |
| donor | Donors | donor | No (joins actor_i18n) |
| function | Functions | function_object | Yes |

### GLAM Sector
| Key | Label | Table | Category |
|-----|-------|-------|----------|
| library_item | Library Items | library_item | GLAM |
| museum_object | Museum Objects | information_object | GLAM |
| gallery_artist | Gallery Artists | gallery_artist | GLAM |
| gallery_exhibition | Gallery Exhibitions | gallery_exhibition | GLAM |
| gallery_loan | Gallery Loans | gallery_loan | GLAM |

### Privacy & Compliance
| Key | Label | Table |
|-----|-------|-------|
| privacy_consent | Privacy Consent Records | privacy_consent_record |
| privacy_dsar | Data Subject Requests | privacy_dsar_request |
| privacy_breach | Privacy Breaches | privacy_breach |
| privacy_ropa | Processing Activities | privacy_processing_activity |
| privacy_paia | PAIA Requests | privacy_paia_request |

### Security
| Key | Label | Table |
|-----|-------|-------|
| security_classification | Security Classifications | object_security_classification |
| user_clearance | User Clearances | user_security_clearance |
| security_access_log | Access Logs | security_access_log |

### Heritage & Spectrum
| Key | Label | Table |
|-----|-------|-------|
| heritage_asset | Heritage Assets | heritage_asset |
| heritage_valuation | Heritage Valuations | heritage_valuation_history |
| spectrum_valuation | Spectrum Valuations | spectrum_valuation |
| spectrum_loan_in | Loans In | spectrum_loan_in |
| spectrum_loan_out | Loans Out | spectrum_loan_out |
| spectrum_condition | Condition Checks | spectrum_condition_check |
| spectrum_movement | Object Movements | spectrum_movement |

---

## Service Methods

### ReportBuilderService

```php
class ReportBuilderService
{
    // Report CRUD
    public function getReports(?int $userId = null): array
    public function getReport(int $id): ?object
    public function createReport(array $data): int
    public function updateReport(int $id, array $data): bool
    public function deleteReport(int $id): bool
    public function cloneReport(int $id, int $userId): int

    // Report Execution
    public function executeReport(
        int $reportId,
        array $runtimeFilters = [],
        int $page = 1,
        int $limit = 50
    ): array

    public function executeReportDefinition(
        string $dataSource,
        array $columns,
        array $filters = [],
        array $sortConfig = [],
        int $page = 1,
        int $limit = 50
    ): array

    // Chart Data
    public function getChartData(int $reportId, array $chartConfig): array

    // Statistics
    public function getStatistics(?int $userId = null): array
}
```

### ReportScheduler

```php
class ReportScheduler
{
    public function runDueReports(): array
    public function runSchedule(object $schedule): array
    public function getArchivePath(): string
}
```

---

## Column Discovery

Columns are discovered from predefined configurations with metadata:

```php
// Example column definition
'information_object' => [
    'main' => [
        'id' => ['label' => 'ID', 'type' => 'integer', 'sortable' => true],
        'identifier' => ['label' => 'Identifier', 'type' => 'string', 'sortable' => true],
        'level_of_description_id' => ['label' => 'Level', 'type' => 'term', 'sortable' => true],
        'repository_id' => ['label' => 'Repository', 'type' => 'repository', 'sortable' => true],
    ],
    'i18n' => [
        'title' => ['label' => 'Title', 'type' => 'string', 'sortable' => true, 'searchable' => true],
        'scope_and_content' => ['label' => 'Scope', 'type' => 'text', 'searchable' => true],
    ],
    'object' => [
        'created_at' => ['label' => 'Created At', 'type' => 'datetime', 'sortable' => true],
        'updated_at' => ['label' => 'Updated At', 'type' => 'datetime', 'sortable' => true],
    ],
    'computed' => [
        'publication_status' => ['label' => 'Publication Status', 'type' => 'status'],
        'has_digital_object' => ['label' => 'Has Digital Object', 'type' => 'boolean'],
        'child_count' => ['label' => 'Children', 'type' => 'integer', 'sortable' => true],
    ],
]
```

### Column Types

| Type | Description | Resolution |
|------|-------------|------------|
| integer | Numeric ID | Direct value |
| string | Text field | Direct value |
| text | Long text | Direct value, truncated in preview |
| datetime | Timestamp | Formatted as Y-m-d H:i |
| date | Date only | Formatted as Y-m-d |
| boolean | Yes/No | Converted to Yes/No text |
| term | Taxonomy term ID | Resolved via term_i18n |
| repository | Repository ID | Resolved via actor_i18n |
| actor | Actor ID | Resolved via actor_i18n |
| information_object | IO ID | Resolved via information_object_i18n |
| user | User ID | Resolved via user table |
| enum | Enumeration | Formatted from snake_case to Title Case |

---

## Filter Operators

| Operator | SQL | Description |
|----------|-----|-------------|
| equals | = | Exact match |
| not_equals | != | Not equal |
| contains | LIKE %...% | Contains substring |
| starts_with | LIKE ...% | Starts with |
| ends_with | LIKE %... | Ends with |
| greater_than | > | Greater than |
| less_than | < | Less than |
| between | BETWEEN | Range (requires value2) |
| is_null | IS NULL | Empty value |
| is_not_null | IS NOT NULL | Has value |
| in | IN (...) | Multiple values |

---

## API Endpoints

### Report Management
| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | /admin/report-builder | index | List all reports |
| GET | /admin/report-builder/create | create | Create form |
| POST | /admin/report-builder/create | create | Save new report |
| GET | /admin/report-builder/:id/edit | edit | Designer view |
| GET | /admin/report-builder/:id/preview | preview | Preview with data |
| GET | /admin/report-builder/:id/export/:format | export | Export (csv/xlsx/pdf) |
| GET | /admin/report-builder/:id/schedule | schedule | Schedule management |
| POST | /admin/report-builder/:id/schedule | schedule | Create schedule |
| GET | /admin/report-builder/:id/clone | cloneReport | Clone report |
| POST | /admin/report-builder/:id/delete | delete | Delete report |
| GET | /admin/report-builder/archive | archive | View archives |

### API (JSON)
| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| POST | /api/report-builder/save | apiSave | Save report definition |
| POST | /api/report-builder/data | apiData | Fetch report data |
| POST | /api/report-builder/chart-data | apiChartData | Get chart aggregation |
| GET | /api/report-builder/columns/:source | apiColumns | Get source columns |
| POST | /api/report-builder/delete/:id | apiDelete | Delete via API |

### Widget API
| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | /api/report-builder/widgets | apiWidgets | Get user widgets |
| POST | /api/report-builder/widget/save | apiWidgetSave | Save widget |
| DELETE | /api/report-builder/widget/:id | apiWidgetDelete | Delete widget |

---

## Export Formats

### CSV Export
- UTF-8 BOM for Excel compatibility
- Header row with column labels
- Standard comma-separated format

### XLSX Export (requires PhpSpreadsheet)
- Formatted headers with bold text
- Auto-sized columns
- Worksheet named after report

### PDF Export (requires Dompdf)
- Landscape A4 orientation
- Report title and metadata
- Styled table with zebra striping
- Long text truncated to 100 characters

---

## Scheduling

### Cron Setup

Add to system crontab:
```bash
0 * * * * cd /usr/share/nginx/archive && php plugins/ahgReportBuilderPlugin/bin/run-scheduled-reports.php >> /var/log/atom-reports.log 2>&1
```

### Frequency Options
| Frequency | Description |
|-----------|-------------|
| daily | Every day at specified time |
| weekly | Specific day of week |
| monthly | Specific day of month (1-28) |
| quarterly | Every 3 months |

### Email Configuration

Uses system SMTP settings from config:
- app_smtp_host
- app_smtp_port
- app_smtp_user
- app_smtp_password
- app_smtp_from_email
- app_smtp_from_name

Supports PHPMailer (preferred) or falls back to PHP mail().

---

## JavaScript Designer

### Key Files
- `/web/js/designer.js` - Main designer logic
- `/web/css/report-builder.css` - Styles

### Dependencies (CDN)
- SortableJS 1.15.0 - Drag-drop functionality
- Chart.js 4.4.0 - Chart visualization

### Designer Configuration

Passed to JavaScript via window object:
```javascript
window.reportBuilder = {
    reportId: 123,
    dataSource: 'information_object',
    columns: ['id', 'title', 'identifier'],
    filters: [{column: 'repository_id', operator: 'equals', value: '1'}],
    charts: [],
    sortConfig: [{column: 'updated_at', direction: 'desc'}],
    layout: {blocks: [{type: 'table', id: 'main-table'}]},
    allColumns: {...},
    apiSaveUrl: '/admin/report-builder/api-save',
    apiDataUrl: '/admin/report-builder/api-data',
    apiChartUrl: '/admin/report-builder/api-chart-data'
};
```

---

## Query Building

### Dynamic Query Construction

```php
private function buildQuery(array $source, array $columns, array $filters, array $sortConfig)
{
    $query = DB::table("{$source['table']} as {$alias}");

    // Join object table if needed
    if (isset($source['object_table'])) {
        $query->join('object as o', "{$alias}.id", '=', 'o.id');
    }

    // Join i18n table
    if (isset($source['i18n_table'])) {
        $query->leftJoin("{$i18nTable} as i18n", function ($join) {
            $join->on("{$alias}.id", '=', 'i18n.id')
                 ->where('i18n.culture', $this->culture);
        });
    }

    // Apply filters and sorting
    $this->applyFilters($query, $alias, $filters, $source);
    $this->applySorting($query, $alias, $source, $sortConfig);

    return $query;
}
```

### Foreign Key Resolution

IDs are batch-resolved to human-readable text:
```php
private function resolveColumnValues(array $results, array $columns, string $dataSource): array
{
    // Collect IDs by type
    $termIds = [];
    $repositoryIds = [];
    $actorIds = [];

    // Batch lookup
    $termNames = $this->lookupTermNames(array_keys($termIds));
    $repoNames = $this->lookupRepositoryNames(array_keys($repositoryIds));

    // Replace IDs with text
    foreach ($results as $row) {
        // ... resolution logic
    }

    return $resolvedResults;
}
```

---

## Configuration

### Plugin Settings

Located in `config/settings.yml`:
```yaml
all:
  ahgReportBuilderPlugin:
    max_export_rows: 10000
    archive_retention_days: 90
    default_page_size: 50
```

### Dependencies

Optional libraries for export:
- **PhpSpreadsheet** - XLSX export
- **Dompdf** - PDF export
- **PHPMailer** - Email delivery

Install via Composer:
```bash
composer require phpoffice/phpspreadsheet dompdf/dompdf phpmailer/phpmailer
```

---

## File Structure

```
ahgReportBuilderPlugin/
+-- bin/
|   +-- run-scheduled-reports.php    # Cron entry point
+-- config/
|   +-- ahgReportBuilderPluginConfiguration.class.php
|   +-- routing.yml                   # Route documentation
|   +-- settings.yml                  # Plugin settings
+-- database/
|   +-- install.sql                   # Schema creation
+-- lib/
|   +-- ReportBuilderService.php      # Main service
|   +-- ReportScheduler.php           # Scheduling service
|   +-- DataSourceRegistry.php        # Data source definitions
|   +-- ColumnDiscovery.php           # Column metadata
|   +-- Forms/
|   |   +-- ReportFormHandler.php
|   +-- Reports/
|       +-- Filters/
|       |   +-- ReportFilter.php
|       +-- Results/
|       |   +-- AuthorityRecordReportResult.php
|       +-- Services/
|           +-- AccessionReportService.php
|           +-- AuthorityRecordReportService.php
|           +-- DonorReportService.php
|           +-- InformationObjectReportService.php
|           +-- PhysicalObjectReportService.php
|           +-- RepositoryReportService.php
|           +-- UpdatesReportService.php
+-- modules/
|   +-- reportBuilder/
|       +-- actions/
|       |   +-- actions.class.php     # Controller actions
|       +-- templates/
|           +-- indexSuccess.php       # Report list
|           +-- createSuccess.php      # Create form
|           +-- editSuccess.php        # Designer
|           +-- previewSuccess.php     # Preview with data
|           +-- viewSuccess.php        # Public view
|           +-- scheduleSuccess.php    # Schedule management
|           +-- archiveSuccess.php     # Archive list
|           +-- widgetSuccess.php      # Dashboard widget
+-- web/
    +-- css/
    |   +-- report-builder.css        # Custom styles
    +-- js/
        +-- designer.js               # Designer JavaScript
```

---

## Security

### Access Control

- **Admin/Editor required**: Create, edit, delete reports
- **Authenticated users**: View shared reports
- **Public**: View public reports only

```php
protected function checkAdminAccess(): void
{
    if (!$this->getUser()->isAuthenticated()) {
        $this->redirect('user/login');
    }
    if (!$this->getUser()->hasCredential('administrator') &&
        !$this->getUser()->hasCredential('editor')) {
        $this->forward404('Access denied');
    }
}
```

### Report Ownership

- Users can only edit/delete their own reports
- Administrators can manage all reports
- Shared reports are read-only for non-owners

---

## Performance Considerations

- **Pagination**: Default 50 rows, max 100 per page
- **Export limit**: 10,000 rows for scheduled exports
- **Batch FK resolution**: IDs collected and resolved in batches
- **Indexed columns**: All foreign keys and filter columns indexed

---

## Extension Points

### Registering Custom Data Sources

```php
// In plugin configuration
DataSourceRegistry::register('custom_table', [
    'label' => 'Custom Records',
    'table' => 'my_custom_table',
    'i18n_table' => 'my_custom_table_i18n',
    'icon' => 'bi-star',
    'description' => 'Custom data records',
    'category' => 'Custom',
]);
```

### Adding Custom Column Types

Extend ColumnDiscovery with additional type mappings and resolution logic.

---

*Part of the AtoM AHG Framework*
