# ahgReportsPlugin - Technical Documentation

**Version:** 1.1.0
**Category:** Admin
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Central reporting dashboard for AtoM providing comprehensive reports on archival descriptions, authority records, repositories, accessions, physical storage, donors, and user activity. The plugin consolidates reporting functionality into a single module with consistent UI patterns and leverages the Laravel Query Builder through the atom-framework.

---

## Architecture

```
+-----------------------------------------------------------------------+
|                        ahgReportsPlugin                               |
+-----------------------------------------------------------------------+
|                                                                       |
|  +---------------------------------------------------------------+   |
|  |                    Plugin Configuration                        |   |
|  |  ahgReportsPluginConfiguration.class.php                       |   |
|  |  - Registers 'reports' module                                  |   |
|  |  - Defines all report routes                                   |   |
|  +---------------------------------------------------------------+   |
|                              |                                        |
|                              v                                        |
|  +---------------------------------------------------------------+   |
|  |                     Action Classes                             |   |
|  |  BaseReportAction (abstract)                                   |   |
|  |    |-- reportsActions (main controller)                        |   |
|  |    |-- reportAction (dynamic report handler)                   |   |
|  |    |-- reportsReportInformationObjectAction                    |   |
|  |    |-- reportsReportAuthorityRecordAction                      |   |
|  |    |-- reportsReportRepositoryAction                           |   |
|  |    |-- reportsReportAccessionAction                            |   |
|  |    |-- reportsReportPhysicalStorageAction                      |   |
|  |    |-- reportsReportUserAction                                 |   |
|  |    |-- reportsReportUpdatesAction                              |   |
|  |    |-- reportsReportTaxonomyAction                             |   |
|  |    +-- reportsreportSelectAction                               |   |
|  +---------------------------------------------------------------+   |
|                              |                                        |
|                              v                                        |
|  +---------------------------------------------------------------+   |
|  |              Framework Report Services                         |   |
|  |  AtomExtensions\Reports\Services\                              |   |
|  |    - InformationObjectReportService                            |   |
|  |    - AuthorityRecordReportService                              |   |
|  |    - RepositoryReportService                                   |   |
|  |    - AccessionReportService                                    |   |
|  |    - PhysicalObjectReportService                               |   |
|  |    - UpdatesReportService                                      |   |
|  |    - DonorReportService                                        |   |
|  +---------------------------------------------------------------+   |
|                              |                                        |
|                              v                                        |
|  +---------------------------------------------------------------+   |
|  |              Framework Repositories                            |   |
|  |  AtomExtensions\Repositories\                                  |   |
|  |    - InformationObjectRepository                               |   |
|  |    - ActorRepository                                           |   |
|  |    - RepositoryRepository                                      |   |
|  |    - AccessionRepository                                       |   |
|  |    - PhysicalObjectRepository                                  |   |
|  +---------------------------------------------------------------+   |
|                              |                                        |
|                              v                                        |
|  +---------------------------------------------------------------+   |
|  |              Laravel Query Builder (Illuminate\Database)       |   |
|  +---------------------------------------------------------------+   |
|                                                                       |
+-----------------------------------------------------------------------+
```

---

## File Structure

```
ahgReportsPlugin/
+-- config/
|   +-- ahgReportsPluginConfiguration.class.php
+-- extension.json
+-- modules/
    +-- reports/
        +-- actions/
        |   +-- actions.class.php
        |   +-- reportAction.class.php
        |   +-- reportAccessionAction.class.php
        |   +-- reportAuthorityRecordAction.class.php
        |   +-- reportDonorAction.class.php
        |   +-- reportInformationObjectAction.class.php
        |   +-- reportPhysicalStorageAction.class.php
        |   +-- reportRepositoryAction.class.php
        |   +-- reportSelectAction.class.php
        |   +-- reportTaxomomyAction.class.php
        |   +-- reportUpdatesAction.class.php
        |   +-- reportUserAction.class.php
        |   +-- reportsMenuComponent.class.php
        +-- config/
        |   +-- module.yml
        |   +-- security.yml
        |   +-- view.yml
        +-- templates/
            +-- indexSuccess.php
            +-- reportAccessionSuccess.php
            +-- reportAuthorityRecordSuccess.php
            +-- reportDonorSuccess.php
            +-- reportInformationObjectSuccess.php
            +-- reportPhysicalStorageSuccess.php
            +-- reportRepositorySuccess.php
            +-- reportSelectSuccess.php
            +-- reportTaxonomySuccess.php
            +-- reportUpdatesSuccess.php
            +-- reportUserSuccess.php
            +-- _reportsMenu.php
            +-- auditTaxonomySuccess.php
            +-- auditDonorSuccess.php
            +-- auditPhysicalStorageSuccess.php
            +-- auditRepositorySuccess.php
            +-- auditActorSuccess.php
            +-- auditPermissionsSuccess.php
            +-- auditArchivalDescriptionSuccess.php
            +-- browseSuccess.php
            +-- browsePublishSuccess.php
            +-- reportAccessSuccess.php
```

---

## Routes

### Plugin Configuration Routes

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| admin_dashboard | `/admin/dashboard` | index | Central dashboard |
| reports_index | `/reports` | index | Legacy redirect |
| report_view | `/reports/view/:code` | report | Dynamic report by code |
| reports_descriptions | `/reports/descriptions` | descriptions | Archival descriptions |
| reports_authorities | `/reports/authorities` | archival | Authority records |
| reports_repositories | `/reports/repositories` | repositories | Repositories |
| reports_accessions | `/reports/accessions` | accessions | Accessions |
| reports_storage | `/reports/storage` | storage | Physical storage |
| reports_recent | `/reports/recent` | recent | Recent updates |
| reports_activity | `/reports/activity` | activity | User activity |

---

## Available Reports

### Core Reports

| Report | Action | Service Class | Description |
|--------|--------|---------------|-------------|
| Archival Descriptions | reportInformationObject | InformationObjectReportService | Browse/filter information objects by date, level, status |
| Authority Records | reportAuthorityRecord | AuthorityRecordReportService | Browse/filter actors by date, entity type |
| Repositories | reportRepository | RepositoryReportService | Browse repository records |
| Accessions | reportAccession | AccessionReportService | Browse accession records with culture filter |
| Physical Storage | reportPhysicalStorage | PhysicalObjectReportService | Browse storage locations with linked records option |
| Recent Updates | reportUpdates | UpdatesReportService | Track recently modified records |
| User Activity | reportUser | N/A (Propel-based) | Audit trail of user actions |
| Taxonomy | reportTaxomomy | N/A (Propel-based) | Browse editable taxonomies |
| Donors | reportDonor | N/A (inline query) | Browse donor records |

### Dynamic Report System (reportAction)

The `reportAction` class provides a centralized report system that reads report definitions from the `report_definition` table. Report categories include:

**Collection Reports:**
- Collection Overview
- Collection Growth
- Metadata Completeness
- Digital Objects
- Records By Creator

**Acquisition Reports:**
- Accessions By Donor
- Deaccessions
- Acquisitions Value

**Access Reports:**
- Access Statistics
- Popular Records
- Downloads
- Search Analytics

**Researcher Reports:**
- Researcher Registrations
- Researcher Activity
- Reading Room Visits
- Material Requests

**Preservation Reports:**
- Condition Overview
- Conservation Actions
- Preservation Priorities
- Format Inventory

**Compliance Reports:**
- Audit Trail
- Rights Expiry
- PAIA Requests
- Retention Schedule
- Security Clearance

---

## Service Layer

### ReportFilter Class

```php
namespace AtomExtensions\Reports\Filters;

class ReportFilter
{
    public static function fromForm(\sfForm $form): self
    public function get(string $key, mixed $default = null): mixed
    public function has(string $key): bool
    public function all(): array
    public function set(string $key, mixed $value): void
    public function withDefaults(array $defaults): self
}
```

### InformationObjectReportService

```php
namespace AtomExtensions\Reports\Services;

final class InformationObjectReportService
{
    public function __construct(
        InformationObjectRepository $repository,
        TermService $termService,
        LoggerInterface $logger
    )

    public function search(ReportFilter $filter): array
    public function getStatistics(): array
}
```

Returns:
```php
[
    'results' => Collection,
    'total' => int,
    'limit' => int,
    'page' => int
]
```

### AccessionReportService

```php
namespace AtomExtensions\Reports\Services;

final class AccessionReportService
{
    public function search(ReportFilter $filter): array
    public function getStatistics(): array
}
```

### AuthorityRecordReportService

```php
namespace AtomExtensions\Reports\Services;

final class AuthorityRecordReportService
{
    public function search(ReportFilter $filter): AuthorityRecordReportResult
    public function getStatistics(): array
}
```

### RepositoryReportService

```php
namespace AtomExtensions\Reports\Services;

final class RepositoryReportService
{
    public function search(ReportFilter $filter): array
    public function getStatistics(): array
}
```

### PhysicalObjectReportService

```php
namespace AtomExtensions\Reports\Services;

final class PhysicalObjectReportService
{
    public function search(ReportFilter $filter): array
    public function getStatistics(): array
}
```

### UpdatesReportService

```php
namespace AtomExtensions\Reports\Services;

final class UpdatesReportService
{
    public function search(ReportFilter $filter): array
    public function getStatistics(): array
}
```

---

## Base Classes

### BaseReportAction

Located in `ahgThemeB5Plugin/lib/BaseReportAction.class.php`:

```php
abstract class BaseReportAction extends sfAction
{
    protected function hasReportAccess(): bool
    protected function getReportLogger(): \Psr\Log\LoggerInterface
    protected function forwardUnauthorized(): void
    protected function handleError(Exception $e, string $context = 'Report'): void
}
```

---

## Filter Parameters

### Standard Filter Fields

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| dateStart | string | Start date (Y-m-d or d/m/Y) | -1 month |
| dateEnd | string | End date (Y-m-d or d/m/Y) | today |
| dateOf | string | CREATED_AT, UPDATED_AT, or both | CREATED_AT |
| limit | int | Results per page | 10-20 |
| page | int | Current page number | 1 |
| sort | string | Sort field | updatedDown |
| culture | string | Language code | en |

### Report-Specific Filters

**Information Object Report:**
- `levelOfDescription` - Level of description ID
- `publicationStatus` - Publication status ID

**Authority Record Report:**
- `entityType` - Actor entity type

**Physical Storage Report:**
- `repositoryId` - Filter by repository
- `showLinkedIO` - Include linked information objects

**User Activity Report:**
- `userAction` - Action type (insert, update, delete)
- `userActivity` - Object type being audited
- `actionUser` - Filter by username
- `chkSummary` - Show summary view

**Updates Report:**
- `className` - Object class filter (all, QubitInformationObject, etc.)

---

## Export Formats

The dynamic report system (`reportAction`) supports multiple export formats:

| Format | Method | Content-Type |
|--------|--------|--------------|
| HTML | Template rendering | text/html |
| CSV | exportCsv() | text/csv |
| JSON | exportJson() | application/json |
| XLSX | exportXlsx() | (falls back to CSV) |
| PDF | exportPdf() | application/pdf |

### PDF Export Implementation

PDF export uses TCPDF when available, with a printable HTML fallback:

```php
protected function exportPdf($filename)
{
    $tcpdfPath = sfConfig::get('sf_root_dir') . '/vendor/tecnickcom/tcpdf/tcpdf.php';

    if (file_exists($tcpdfPath)) {
        require_once $tcpdfPath;
        return $this->exportPdfWithTcpdf($filename);
    }

    // Fallback to printable HTML
    return $this->exportPdfAsHtml($filename);
}
```

**TCPDF Export Features:**
- Full PDF generation with proper headers/footers
- Auto-sizing columns for tabular data
- UTF-8 support for international characters
- Configurable page orientation (portrait/landscape)
- Report title and date headers

**HTML Fallback Features:**
- Print-optimized CSS styling
- Bootstrap-compatible table formatting
- Print button for browser print dialog
- Proper page break handling

---

## Security

### Credentials Required

From `modules/reports/config/security.yml`:

```yaml
delete:
  credentials: [[ contributor, editor, administrator ]]
  is_secure: true

edit:
  credentials: [[ contributor, editor, administrator, translator ]]
  is_secure: true
```

### Access Control

- Dashboard and reports require authentication
- User Activity report requires administrator role
- Taxonomy report requires administrator role
- Other reports check via `hasReportAccess()` method

---

## Dashboard Statistics

The main dashboard (`indexSuccess.php`) displays:

```php
$stats = [
    'totalDescriptions' => int,    // Count of information_object (excluding root)
    'totalActors' => int,          // Count of actor (excluding root)
    'totalRepositories' => int,    // Count of repository
    'totalDigitalObjects' => int,  // Count of digital_object
    'totalAccessions' => int,      // Count of accession
    'recentUpdates' => int,        // Records updated in last 7 days
    'draftRecords' => int,         // Publication status = Draft (159)
    'publishedRecords' => int      // Publication status = Published (160)
];
```

---

## Menu Structure

The `reportsMenuComponent` provides a mega-menu with sections:

- **Reports:** Standard entity reports
- **Audit:** Data quality and audit reports
- **Dashboards:** Various dashboard links
- **Export:** Export functionality
- **Import:** Import functionality

---

## Integration with Other Plugins

The dashboard dynamically shows sections based on enabled plugins:

| Plugin | Dashboard Section |
|--------|------------------|
| ahgLibraryPlugin | Library Reports/Management |
| ahgMuseumPlugin | Museum Dashboard/Reports |
| ahgGalleryPlugin | Gallery Management |
| ahgDAMPlugin | Digital Asset Management |
| ahgSpectrumPlugin | Spectrum Workflow |
| ahgGrapPlugin | GRAP 103 Dashboard |
| ahgHeritageAccountingPlugin | Heritage Asset Accounting |
| ahgResearchPlugin | Research Services |
| ahgDonorAgreementPlugin | Donor Management |
| ahgConditionPlugin | Condition Management |
| ahgPrivacyPlugin | Privacy & Compliance |
| ahgSecurityClearancePlugin | Security Dashboard |
| ahgAuditTrailPlugin | Audit Trail |
| ahgVendorPlugin | Vendor Management |
| ahgPreservationPlugin | Digital Preservation |
| ahgBackupPlugin | Backup & Maintenance |
| ahgDedupePlugin | Duplicate Detection |
| ahgFormsPlugin | Form Templates |
| ahgDoiPlugin | DOI Management |
| ahgDataMigrationPlugin | Data Migration |
| ahgRicExplorerPlugin | RiC Explorer |
| ahgAccessRequestPlugin | Access Requests |
| ahgExtendedRightsPlugin | Rights & Licensing |

---

## Database Dependencies

The plugin queries these core AtoM tables:

- `information_object` / `information_object_i18n`
- `actor` / `actor_i18n`
- `repository`
- `accession` / `accession_i18n`
- `physical_object` / `physical_object_i18n`
- `digital_object`
- `donor` / `donor_i18n`
- `object`
- `status`
- `term` / `term_i18n`
- `taxonomy`
- `user`
- `audit_object` (for user activity report)

Optional tables (if available):
- `access_log`
- `search_log`
- `researcher`
- `reading_room_visit`
- `material_request`
- `condition_report`
- `conservation_treatment`
- `rights`
- `security_classification`
- `report_definition` (for dynamic reports)

---

## Usage Example

### Accessing Reports

```php
// Via URL
/admin/dashboard           // Central dashboard
/reports/descriptions      // Archival description report
/reports/authorities       // Authority record report
/reports/accessions        // Accession report

// Dynamic report by code
/reports/view/collection_overview
/reports/view/metadata_completeness
```

### Using Report Services

```php
// In custom action
$filter = ReportFilter::fromForm($this->form);
$service = new InformationObjectReportService(
    new InformationObjectRepository(),
    new TermService('en'),
    $logger
);
$results = $service->search($filter);
$statistics = $service->getStatistics();
```

---

## Related Documentation

- User Guide: `reports-dashboard-user-guide.md`
- atom-framework Reports Services: `atom-framework/src/Reports/`
- ahgAuditTrailPlugin (for audit trail integration)

---

*Part of the AtoM AHG Framework*
