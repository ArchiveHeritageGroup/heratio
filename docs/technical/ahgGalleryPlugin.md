# ahgGalleryPlugin - Technical Documentation

**Version:** 1.2.10
**Category:** Sector (GLAM - Galleries)
**Dependencies:** atom-framework, ahgCorePlugin
**Optional Integration:** ahgConditionPlugin, ahgGrapPlugin, ahgExhibitionPlugin

---

## Overview

The ahgGalleryPlugin provides comprehensive gallery and art museum management functionality for AtoM, implementing the **Cataloguing Cultural Objects (CCO)** and **VRA Core** metadata standards. It manages artists, loans (incoming/outgoing), valuations, insurance policies, venues, gallery spaces, and facility reports.

**Key Features:**
- CCO-compliant cataloguing with 14 field categories
- Artist management with biography, bibliography, and exhibition history
- Loan workflow management (inquiry through return)
- Valuation tracking (insurance, market, replacement values)
- Facility report management for loan compliance
- Venue and gallery space administration
- Integration with ahgExhibitionPlugin for unified exhibition management

---

## Architecture

```
+-----------------------------------------------------------------------+
|                        ahgGalleryPlugin                                |
+-----------------------------------------------------------------------+
|                                                                        |
|  +---------------------------+    +---------------------------+        |
|  |   Plugin Configuration    |    |      Routing System       |        |
|  |  ahgGalleryPlugin         |    |  (Symfony Event-based)    |        |
|  |  Configuration.class.php  |    |                           |        |
|  +---------------------------+    +---------------------------+        |
|              |                               |                         |
|              v                               v                         |
|  +---------------------------------------------------------------+    |
|  |                      GalleryService                            |    |
|  |  lib/Services/GalleryService.php                               |    |
|  |                                                                |    |
|  |  +------------+  +------------+  +------------+  +-----------+ |    |
|  |  |   Loans    |  | Valuations |  |  Artists   |  | Dashboard | |    |
|  |  +------------+  +------------+  +------------+  +-----------+ |    |
|  +---------------------------------------------------------------+    |
|              |                                                         |
|              v                                                         |
|  +---------------------------------------------------------------+    |
|  |                    CCO Field System                            |    |
|  |  +---------------------------+  +---------------------------+  |    |
|  |  | ahgCCOFieldDefinitions   |  | ahgCCOTemplates           |  |    |
|  |  | (14 CCO Categories)       |  | (14 Object Templates)     |  |    |
|  |  +---------------------------+  +---------------------------+  |    |
|  +---------------------------------------------------------------+    |
|              |                                                         |
|              v                                                         |
|  +---------------------------------------------------------------+    |
|  |                      Modules                                   |    |
|  |  +---------------------------+  +---------------------------+  |    |
|  |  |     gallery Module        |  |  galleryReports Module    |  |    |
|  |  |  - Dashboard              |  |  - Exhibition Reports     |  |    |
|  |  |  - Artists CRUD           |  |  - Artist Reports         |  |    |
|  |  |  - Loans CRUD             |  |  - Loan Reports           |  |    |
|  |  |  - Valuations CRUD        |  |  - Valuation Reports      |  |    |
|  |  |  - Venues/Spaces CRUD     |  |  - Facility Reports       |  |    |
|  |  |  - Facility Reports       |  |  - CSV Export             |  |    |
|  |  +---------------------------+  +---------------------------+  |    |
|  +---------------------------------------------------------------+    |
|              |                                                         |
|              v                                                         |
|  +---------------------------------------------------------------+    |
|  |               Database (Laravel Query Builder)                 |    |
|  |                                                                |    |
|  |  gallery_artist          gallery_loan           gallery_venue  |    |
|  |  gallery_artist_         gallery_loan_object    gallery_space  |    |
|  |    bibliography          gallery_facility_      gallery_       |    |
|  |  gallery_artist_           report                 valuation    |    |
|  |    exhibition_history    gallery_insurance_                    |    |
|  |                            policy                              |    |
|  +---------------------------------------------------------------+    |
|                                                                        |
+-----------------------------------------------------------------------+
                              |
                              v
+-----------------------------------------------------------------------+
|                    External Integration                                |
|  +-------------------+  +-------------------+  +-------------------+   |
|  | ahgExhibitionPlugin|  | ahgConditionPlugin|  | ahgGrapPlugin     |   |
|  | (Exhibitions)      |  | (Condition Report)|  | (Heritage Acct.)  |   |
|  +-------------------+  +-------------------+  +-------------------+   |
+-----------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+---------------------------+       +---------------------------+
|     gallery_artist        |       |  gallery_artist_          |
+---------------------------+       |    bibliography           |
| PK id INT                 |<---+  +---------------------------+
|    actor_id INT (FK)      |    |  | PK id INT                 |
|    display_name VARCHAR   |    +--| FK artist_id INT          |
|    sort_name VARCHAR      |       |    entry_type ENUM        |
|    birth_date DATE        |       |    title VARCHAR          |
|    birth_place VARCHAR    |       |    author VARCHAR         |
|    death_date DATE        |       |    publication VARCHAR    |
|    death_place VARCHAR    |       |    publisher VARCHAR      |
|    nationality VARCHAR    |       |    publication_date DATE  |
|    artist_type ENUM       |       |    volume VARCHAR         |
|    medium_specialty TEXT  |       |    issue VARCHAR          |
|    movement_style TEXT    |       |    pages VARCHAR          |
|    active_period VARCHAR  |       |    url VARCHAR            |
|    represented TINYINT    |       |    isbn VARCHAR           |
|    representation_start   |       |    notes TEXT             |
|    representation_end     |       |    created_at DATETIME    |
|    representation_terms   |       +---------------------------+
|    commission_rate DECIMAL|
|    exclusivity TINYINT    |       +---------------------------+
|    biography TEXT         |       |  gallery_artist_          |
|    artist_statement TEXT  |       |    exhibition_history     |
|    cv TEXT                |       +---------------------------+
|    email VARCHAR          |       | PK id INT                 |
|    phone VARCHAR          |    +--| FK artist_id INT          |
|    website VARCHAR        |    |  |    exhibition_type ENUM   |
|    studio_address TEXT    |<---+  |    title VARCHAR          |
|    instagram VARCHAR      |       |    venue VARCHAR          |
|    twitter VARCHAR        |       |    city VARCHAR           |
|    facebook VARCHAR       |       |    country VARCHAR        |
|    notes TEXT             |       |    start_date DATE        |
|    is_active TINYINT      |       |    end_date DATE          |
|    created_at DATETIME    |       |    curator VARCHAR        |
|    updated_at DATETIME    |       |    catalog_published      |
+---------------------------+       |    notes TEXT             |
                                    |    created_at DATETIME    |
                                    +---------------------------+

+---------------------------+       +---------------------------+
|      gallery_loan         |       |    gallery_loan_object    |
+---------------------------+       +---------------------------+
| PK id INT                 |<---+  | PK id INT                 |
|    loan_number VARCHAR UK |    +--| FK loan_id INT            |
|    loan_type ENUM         |       | FK object_id INT          |
|    status ENUM            |       |    insurance_value DECIMAL|
|    purpose VARCHAR        |       |    condition_out TEXT     |
| FK exhibition_id INT      |       |    condition_out_date DATE|
|    institution_name       |       |    condition_out_by INT   |
|    institution_address    |       |    condition_return TEXT  |
|    contact_name VARCHAR   |       |    condition_return_date  |
|    contact_email VARCHAR  |       |    condition_return_by INT|
|    contact_phone VARCHAR  |       |    packing_instructions   |
|    request_date DATE      |       |    display_requirements   |
|    approval_date DATE     |       |    notes TEXT             |
|    loan_start_date DATE   |       |    created_at DATETIME    |
|    loan_end_date DATE     |       +---------------------------+
|    actual_return_date     |
|    loan_fee DECIMAL       |       +---------------------------+
|    insurance_value DECIMAL|       |  gallery_facility_report  |
|    insurance_provider     |       +---------------------------+
|    insurance_policy_number|       | PK id INT                 |
|    special_conditions TEXT|    +--| FK loan_id INT            |
|    agreement_signed       |    |  |    report_type ENUM       |
|    agreement_date DATE    |<---+  |    institution_name       |
|    facility_report_recvd  |       |    building_age INT       |
|    notes TEXT             |       |    construction_type      |
| FK created_by INT         |       |    fire_detection TINYINT |
|    created_at DATETIME    |       |    fire_suppression       |
|    updated_at DATETIME    |       |    security_24hr TINYINT  |
+---------------------------+       |    security_guards TINYINT|
                                    |    cctv TINYINT           |
+---------------------------+       |    intrusion_detection    |
|    gallery_valuation      |       |    climate_controlled     |
+---------------------------+       |    temperature_range      |
| PK id INT                 |       |    humidity_range VARCHAR |
| FK object_id INT          |       |    light_levels VARCHAR   |
|    valuation_type ENUM    |       |    uv_filtering TINYINT   |
|    value_amount DECIMAL   |       |    trained_handlers       |
|    currency VARCHAR(3)    |       |    loading_dock TINYINT   |
|    valuation_date DATE    |       |    freight_elevator       |
|    valid_until DATE       |       |    storage_available      |
|    appraiser_name VARCHAR |       |    insurance_coverage     |
|    appraiser_credentials  |       |    completed_by VARCHAR   |
|    appraiser_organization |       |    completed_date DATE    |
|    methodology TEXT       |       |    approved TINYINT       |
|    comparables TEXT       |       |    approved_by INT        |
|    notes TEXT             |       |    approved_date DATE     |
|    document_path VARCHAR  |       |    notes TEXT             |
|    is_current TINYINT     |       |    created_at DATETIME    |
| FK created_by INT         |       +---------------------------+
|    created_at DATETIME    |
+---------------------------+       +---------------------------+
                                    | gallery_insurance_policy  |
+---------------------------+       +---------------------------+
|      gallery_venue        |       | PK id INT                 |
+---------------------------+       |    policy_number VARCHAR  |
| PK id INT                 |       |    provider VARCHAR       |
|    name VARCHAR           |       |    policy_type ENUM       |
|    description TEXT       |       |    coverage_amount DECIMAL|
|    address TEXT           |       |    deductible DECIMAL     |
|    total_area_sqm DECIMAL |       |    premium DECIMAL        |
|    max_capacity INT       |       |    start_date DATE        |
|    climate_controlled     |       |    end_date DATE          |
|    security_level VARCHAR |       |    contact_name VARCHAR   |
|    contact_name VARCHAR   |       |    contact_email VARCHAR  |
|    contact_email VARCHAR  |       |    contact_phone VARCHAR  |
|    contact_phone VARCHAR  |       |    notes TEXT             |
|    is_active TINYINT      |       |    is_active TINYINT      |
|    created_at DATETIME    |       |    created_at DATETIME    |
|    updated_at DATETIME    |       +---------------------------+
+---------------------------+
         |
         | 1:N
         v
+---------------------------+
|      gallery_space        |
+---------------------------+
| PK id INT                 |
| FK venue_id INT           |
|    name VARCHAR           |
|    description TEXT       |
|    area_sqm DECIMAL       |
|    wall_length_m DECIMAL  |
|    height_m DECIMAL       |
|    lighting_type VARCHAR  |
|    climate_controlled     |
|    max_weight_kg DECIMAL  |
|    is_active TINYINT      |
|    created_at DATETIME    |
+---------------------------+
```

### Table Definitions

#### gallery_artist
```sql
CREATE TABLE gallery_artist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT DEFAULT NULL,                    -- Links to AtoM actor
  display_name VARCHAR(255) NOT NULL,
  sort_name VARCHAR(255),
  birth_date DATE,
  birth_place VARCHAR(255),
  death_date DATE,
  death_place VARCHAR(255),
  nationality VARCHAR(100),
  artist_type ENUM('individual','collective','studio','anonymous') DEFAULT 'individual',
  medium_specialty TEXT,
  movement_style TEXT,
  active_period VARCHAR(100),
  represented TINYINT(1) DEFAULT 0,             -- Gallery represents artist
  representation_start DATE,
  representation_end DATE,
  representation_terms TEXT,
  commission_rate DECIMAL(5,2),
  exclusivity TINYINT(1) DEFAULT 0,
  biography TEXT,
  artist_statement TEXT,
  cv TEXT,
  email VARCHAR(255),
  phone VARCHAR(50),
  website VARCHAR(255),
  studio_address TEXT,
  instagram VARCHAR(100),
  twitter VARCHAR(100),
  facebook VARCHAR(255),
  notes TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_actor (actor_id),
  INDEX idx_name (display_name),
  INDEX idx_represented (represented)
);
```

#### gallery_loan
```sql
CREATE TABLE gallery_loan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_number VARCHAR(50) NOT NULL UNIQUE,      -- Auto-generated: LI-/LO-YYYYMMDD-XXXX
  loan_type ENUM('incoming','outgoing') NOT NULL,
  status ENUM('inquiry','requested','approved','agreed',
              'in_transit_out','on_loan','in_transit_return',
              'returned','cancelled','declined') DEFAULT 'inquiry',
  purpose VARCHAR(255),
  exhibition_id INT,                             -- Links to exhibition table
  institution_name VARCHAR(255) NOT NULL,
  institution_address TEXT,
  contact_name VARCHAR(255),
  contact_email VARCHAR(255),
  contact_phone VARCHAR(50),
  request_date DATE,
  approval_date DATE,
  loan_start_date DATE,
  loan_end_date DATE,
  actual_return_date DATE,
  loan_fee DECIMAL(12,2),
  insurance_value DECIMAL(12,2),
  insurance_provider VARCHAR(255),
  insurance_policy_number VARCHAR(100),
  special_conditions TEXT,
  agreement_signed TINYINT(1) DEFAULT 0,
  agreement_date DATE,
  facility_report_received TINYINT(1) DEFAULT 0,
  notes TEXT,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_type (loan_type),
  INDEX idx_status (status),
  INDEX idx_dates (loan_start_date, loan_end_date)
);
```

#### gallery_valuation
```sql
CREATE TABLE gallery_valuation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  object_id INT NOT NULL,                       -- Links to information_object
  valuation_type ENUM('insurance','market','replacement',
                      'auction_estimate','probate','donation') DEFAULT 'insurance',
  value_amount DECIMAL(14,2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'ZAR',
  valuation_date DATE NOT NULL,
  valid_until DATE,
  appraiser_name VARCHAR(255),
  appraiser_credentials VARCHAR(255),
  appraiser_organization VARCHAR(255),
  methodology TEXT,
  comparables TEXT,
  notes TEXT,
  document_path VARCHAR(500),
  is_current TINYINT(1) DEFAULT 1,              -- Only one current per type/object
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_object (object_id),
  INDEX idx_type (valuation_type),
  INDEX idx_current (is_current)
);
```

---

## CCO Field Categories

The plugin implements all 14 CCO (Cataloguing Cultural Objects) categories:

| Chapter | Category | Description | Key Fields |
|---------|----------|-------------|------------|
| 2 | Object/Work | Object identification | work_type, object_number |
| 3 | Titles/Names | Title information | title, title_type, alternate_titles |
| 4 | Creation | Creator and date | creator, creator_role, creation_date |
| 5 | Styles/Periods | Artistic classification | style, period, school_group |
| 6 | Measurements | Physical dimensions | height, width, depth, weight |
| 7 | Materials/Techniques | Physical composition | materials, techniques, support |
| 8 | Subject Matter | Iconography | subjects_depicted, iconography |
| 9 | Inscriptions | Marks and signatures | inscriptions, signature, marks |
| 10 | State/Edition | For prints/multiples | edition_number, edition_size, state |
| 11 | Description | Narrative text | description, physical_description |
| 12 | Condition | Physical condition | condition_summary, condition_notes |
| 13 | Current Location | Repository info | repository, credit_line |
| 14 | Related Works | Work relationships | related_works, relationship_type |
| 15 | Rights | Copyright/reproduction | rights_statement, copyright_holder |

### Object Type Templates

The plugin provides 14 pre-configured templates with field requirement levels:

| Template | Icon | Default Work Type | Special Fields |
|----------|------|-------------------|----------------|
| Painting | brush | painting | support (required) |
| Sculpture | box | sculpture | depth, weight (required) |
| Photograph | camera | photograph | edition fields (optional) |
| Print | layers | print | edition, state (required) |
| Drawing | pencil | drawing | (standard painting fields) |
| Textile | grid | textile | culture (required) |
| Ceramic | cup | ceramic | depth (required) |
| Furniture | lamp | furniture | marks (recommended) |
| Decorative Art | gem | decorative art | marks (recommended) |
| Archival Object | file | document | subject (required) |
| Book/Manuscript | book | book | components (recommended) |
| Numismatic | coin | coin | weight, inscriptions (required) |
| Natural Specimen | flower | specimen | creator fields (hidden) |
| Generic | archive | object | (all fields available) |

---

## Service Methods

### GalleryService

```php
class GalleryService
{
    // LOANS
    public function getLoans(array $filters = []): array
    public function getLoan(int $id): ?object
    public function createLoan(array $data): int
    public function updateLoan(int $id, array $data): bool
    public function addLoanObject(int $loanId, int $objectId, array $data = []): int
    public function createFacilityReport(int $loanId, array $data): int

    // VALUATIONS
    public function getValuations(int $objectId): array
    public function getCurrentValuation(int $objectId, string $type = 'insurance'): ?object
    public function createValuation(array $data): int
    public function getInsurancePolicies(): array

    // ARTISTS
    public function getArtists(array $filters = []): array
    public function getArtist(int $id): ?object
    public function createArtist(array $data): int
    public function updateArtist(int $id, array $data): bool
    public function addBibliography(int $artistId, array $data): int
    public function addExhibitionHistory(int $artistId, array $data): int

    // DASHBOARD
    public function getDashboardStats(): array
}
```

### ahgCCOFieldDefinitions

```php
class ahgCCOFieldDefinitions
{
    // Category constants
    const CATEGORY_OBJECT_WORK = 'object_work';
    const CATEGORY_TITLES = 'titles';
    const CATEGORY_CREATION = 'creation';
    // ... (14 categories total)

    // Methods
    public static function getAllCategories(): array
    public static function getObjectWorkFields(): array
    public static function getTitleFields(): array
    public static function getCreationFields(): array
    public static function getStylePeriodFields(): array
    public static function getMeasurementFields(): array
    public static function getMaterialsTechniquesFields(): array
    public static function getSubjectFields(): array
    public static function getInscriptionFields(): array
    public static function getStateEditionFields(): array
    public static function getDescriptionFields(): array
    public static function getConditionFields(): array
    public static function getLocationFields(): array
    public static function getRelatedWorksFields(): array
    public static function getRightsFields(): array
}
```

### ahgCCOTemplates

```php
class ahgCCOTemplates
{
    // Field requirement levels
    const REQUIRED = 'required';
    const RECOMMENDED = 'recommended';
    const OPTIONAL = 'optional';
    const HIDDEN = 'hidden';

    // Template constants
    const TEMPLATE_PAINTING = 'painting';
    const TEMPLATE_SCULPTURE = 'sculpture';
    // ... (14 templates total)

    // Methods
    public static function getTemplates(): array
    public static function getTemplate(string $templateId): array
    public static function getRequiredFields(string $templateId): array
    public static function getFieldLevel(string $templateId, string $fieldName): string
    public static function isFieldVisible(string $templateId, string $fieldName): bool
    public static function validateRecord(string $templateId, array $recordData): array
    public static function calculateCompleteness(string $templateId, array $recordData): int
}
```

---

## Routes

All routes are defined programmatically in `ahgGalleryPluginConfiguration.class.php`:

### Core Routes

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| gallery_dashboard | /gallery/dashboard | dashboard | Main dashboard |
| gallery_browse | /gallery/browse | browse | Browse all items |
| gallery_add | /gallery/add | add | Add new gallery item |
| gallery_view | /gallery/:slug | index | View gallery item |
| gallery_edit | /gallery/edit/:slug | edit | Edit gallery item |

### Loan Routes

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| gallery_loans | /gallery/loans | loans | List all loans |
| gallery_create_loan | /gallery/loans/create | createLoan | Create new loan |
| gallery_view_loan | /gallery/loans/:id | viewLoan | View loan details |
| gallery_facility_report | /gallery/loans/:loan_id/facility-report | facilityReport | Manage facility report |

### Valuation Routes

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| gallery_valuations | /gallery/valuations | valuations | List valuations |
| gallery_create_valuation | /gallery/valuations/create | createValuation | Create valuation |

### Artist Routes

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| gallery_artists | /gallery/artists | artists | List all artists |
| gallery_create_artist | /gallery/artists/create | createArtist | Create artist |
| gallery_view_artist | /gallery/artists/:id | viewArtist | View/edit artist |

### Venue Routes

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| gallery_venues | /gallery/venues | venues | List venues |
| gallery_create_venue | /gallery/venues/create | createVenue | Create venue |
| gallery_view_venue | /gallery/venues/:id | viewVenue | View/edit venue |

---

## Modules

### gallery Module

Primary module for gallery management operations.

**Actions:**
- `index` - Redirects to dashboard
- `dashboard` - Main dashboard with statistics
- `browse` - Browse gallery items
- `add` - Add new gallery item
- `edit` - Edit gallery item
- `loans` - Manage loans
- `createLoan` - Create new loan
- `viewLoan` - View/update loan
- `facilityReport` - Facility report form
- `valuations` - Manage valuations
- `createValuation` - Create valuation
- `artists` - List artists
- `createArtist` - Create artist
- `viewArtist` - View/edit artist
- `venues` - List venues
- `createVenue` - Create venue
- `viewVenue` - View/edit venue

### galleryReports Module

Reporting and analytics module.

**Actions:**
- `index` - Reports dashboard with statistics
- `exhibitions` - Exhibition reports (via ahgExhibitionPlugin)
- `artists` - Artist reports
- `loans` - Loan reports
- `valuations` - Valuation reports
- `facilityReports` - Facility report summary
- `spaces` - Gallery space reports
- `exportCsv` - CSV export for any report type

---

## Loan Workflow

```
+----------+     +----------+     +----------+     +----------+
| inquiry  |---->| requested|---->| approved |---->| agreed   |
+----------+     +----------+     +----------+     +----------+
                                                        |
                                                        v
+----------+     +----------+     +----------+     +----------+
| returned |<----| in_transit|<---| on_loan  |<----| in_transit|
|          |     | _return   |    |          |     | _out      |
+----------+     +----------+     +----------+     +----------+

Alternative paths:
  inquiry/requested --> declined
  any state --> cancelled
```

### Loan Number Format
- **Incoming loans:** `LI-YYYYMMDD-XXXX` (e.g., LI-20260130-A3F2)
- **Outgoing loans:** `LO-YYYYMMDD-XXXX` (e.g., LO-20260130-B7C1)

---

## Valuation Types

| Type | Use Case | Description |
|------|----------|-------------|
| insurance | Insurance coverage | Value for insurance purposes |
| market | Sales/trading | Current fair market value |
| replacement | Loss/damage | Cost to replace with similar |
| auction_estimate | Pre-sale | Estimated auction price range |
| probate | Estate | Value for estate purposes |
| donation | Tax | Fair market value for donations |

---

## Integration Points

### ahgExhibitionPlugin Integration
- Exhibition functionality delegated to unified exhibition system
- Gallery actions redirect to exhibition module
- Dashboard stats pull from `exhibition` table
- Reports integrate exhibition data

### ahgConditionPlugin Integration (Optional)
- Condition reports for loan objects
- Links to `condition_out` and `condition_return` fields

### ahgGrapPlugin Integration (Optional)
- Heritage accounting for artwork assets
- Valuation data integration

### AtoM Core Integration
- `gallery_artist.actor_id` links to `actor` table
- `gallery_valuation.object_id` links to `information_object`
- Artist works retrieved via `event` table (type_id=111 for creation)

---

## Configuration

### Plugin Registration
```php
// ahgGalleryPluginConfiguration.class.php
public static $summary = 'Gallery Management - Artists, Loans, Valuations';
public static $version = '1.1.0';

public function initialize()
{
    $this->dispatcher->connect('routing.load_configuration', [$this, 'addRoutes']);
    $enabledModules = sfConfig::get('sf_enabled_modules', []);
    $enabledModules[] = 'gallery';
    $enabledModules[] = 'galleryReports';
    sfConfig::set('sf_enabled_modules', $enabledModules);
}
```

### Security
```yaml
# modules/gallery/config/security.yml
all:
  is_secure: false
```

Authentication is handled at action level via `$this->getUser()->isAuthenticated()` checks.

---

## Vocabulary Integration

The CCO field definitions reference several controlled vocabularies:

| Vocabulary | Source | Used For |
|------------|--------|----------|
| AAT | Getty Art & Architecture Thesaurus | Object types, materials, styles |
| ULAN | Getty Union List of Artist Names | Creator authority |
| TGN | Getty Thesaurus of Geographic Names | Place of creation |
| ICONCLASS | Iconographic Classification | Iconography subjects |
| ISO 639-2 | Language codes | Title language |

---

## Record Validation

```php
// Validate a painting record
$validation = ahgCCOTemplates::validateRecord('painting', $recordData);

// Returns:
[
    'valid' => true/false,
    'errors' => [
        ['field' => 'title', 'label' => 'Title', 'message' => 'Title is required']
    ],
    'warnings' => [
        ['field' => 'style', 'label' => 'Style', 'message' => 'Style is recommended']
    ],
    'completeness' => 75  // percentage
]
```

---

## Reports Summary Statistics

### Dashboard Statistics
```php
$service->getDashboardStats();
// Returns:
[
    'exhibitions_open' => int,
    'exhibitions_planning' => int,
    'loans_active' => int,
    'loans_pending' => int,
    'artists_represented' => int,
    'artists_total' => int,
    'upcoming_exhibitions' => array,
    'expiring_loans' => array
]
```

### Report Module Statistics
- **Exhibitions:** total, open, planning, upcoming
- **Artists:** total, represented, active, by nationality
- **Loans:** total, active, incoming/outgoing, pending, overdue
- **Valuations:** total, current, total value, expiring soon
- **Facility Reports:** compliance metrics (fire, security, climate)
- **Spaces:** total area, wall length, climate-controlled count

---

## CSV Export

The `galleryReports/exportCsv` action supports export of:
- Exhibitions
- Artists
- Loans
- Valuations

Usage: `/galleryReports/exportCsv?report=artists`

---

## Related Documentation

- [Gallery Module User Guide](../gallery-module-user-guide.md)
- [ahgExhibitionPlugin Technical Documentation](./ahgExhibitionPlugin.md)
- [ahgConditionPlugin Technical Documentation](./ahgConditionPlugin.md)
- [CCO Standard Reference](http://cco.vrafoundation.org/)
- [VRA Core Reference](https://www.loc.gov/standards/vracore/)

---

*Part of the AtoM AHG Framework - Version 1.2.10*
