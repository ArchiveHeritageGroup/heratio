# ahgGrapPlugin - Technical Documentation

**Version:** 1.0.0  
**Category:** Financial/Compliance  
**Dependencies:** atom-framework

---

## Overview

GRAP 103 (Generally Recognised Accounting Practice) heritage asset accounting module for South African public sector compliance. Supports asset recognition, valuation, depreciation tracking, and financial reporting.

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────────┐
│          grap_heritage_asset            │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK information_object_id INT UNIQUE    │──────┐
│                                         │      │
│ -- RECOGNITION --                       │      │
│    recognition_status ENUM              │      │
│    recognition_status_reason VARCHAR    │      │
│    recognition_date DATE                │      │
│    measurement_basis ENUM               │      │
│                                         │      │
│ -- ACQUISITION --                       │      │
│    acquisition_method ENUM              │      │
│    acquisition_date DATE                │      │
│    cost_of_acquisition DECIMAL(15,2)    │      │
│    fair_value_at_acquisition DECIMAL    │      │
│    nominal_value DECIMAL(15,2)          │      │
│    donor_name VARCHAR                   │      │
│    donor_restrictions TEXT              │      │
│                                         │      │
│ -- CARRYING AMOUNT --                   │      │
│    current_carrying_amount DECIMAL      │      │
│    accumulated_impairment DECIMAL       │      │
│    last_valuation_date DATE             │      │
│    last_valuation_amount DECIMAL        │      │
│    valuation_method ENUM                │      │
│    valuer_name VARCHAR                  │      │
│    valuer_qualifications VARCHAR        │      │
│    next_valuation_due DATE              │      │
│                                         │      │
│ -- ASSET CLASS --                       │      │
│    asset_class ENUM                     │      │
│    asset_subclass VARCHAR               │      │
│    is_collection TINYINT                │      │
│    collection_id INT                    │      │
│                                         │      │
│ -- INSURANCE --                         │      │
│    insured_value DECIMAL                │      │
│    insurance_policy VARCHAR             │      │
│    insurance_expiry DATE                │      │
│                                         │      │
│ -- STATUS --                            │      │
│    status ENUM                          │      │
│    disposal_date DATE                   │      │
│    disposal_method ENUM                 │      │
│    disposal_proceeds DECIMAL            │      │
│                                         │      │
│    created_at TIMESTAMP                 │      │
│    updated_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
              │                                   │
              │ 1:N                               │
              ▼                                   │
┌─────────────────────────────────────────┐      │
│        grap_valuation_history           │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│ FK heritage_asset_id INT               │──────┤
│    valuation_date DATE                  │      │
│    valuation_type ENUM                  │      │
│    previous_value DECIMAL               │      │
│    new_value DECIMAL                    │      │
│    change_amount DECIMAL                │      │
│    valuation_method ENUM                │      │
│    valuer_name VARCHAR                  │      │
│    valuer_organization VARCHAR          │      │
│    valuer_qualifications VARCHAR        │      │
│    valuation_report_ref VARCHAR         │      │
│    justification TEXT                   │      │
│    approved_by INT                      │      │
│    approved_at TIMESTAMP                │      │
│    created_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
                                                  │
┌─────────────────────────────────────────┐      │
│         grap_impairment_record          │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│ FK heritage_asset_id INT               │──────┤
│    impairment_date DATE                 │      │
│    impairment_type ENUM                 │      │
│    impairment_amount DECIMAL            │      │
│    carrying_before DECIMAL              │      │
│    carrying_after DECIMAL               │      │
│    reason TEXT                          │      │
│    reversible TINYINT                   │      │
│    reversal_date DATE                   │      │
│    reversal_amount DECIMAL              │      │
│    approved_by INT                      │      │
│    created_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
                                                  │
┌─────────────────────────────────────────┐      │
│        grap_movement_record             │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│ FK heritage_asset_id INT               │──────┘
│    movement_date DATE                   │
│    movement_type ENUM                   │
│    from_location VARCHAR                │
│    to_location VARCHAR                  │
│    reason TEXT                          │
│    authorized_by INT                    │
│    condition_before VARCHAR             │
│    condition_after VARCHAR              │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_4acb08c7.png)
```

---

## Asset Classes (GRAP 103)

| Class | Description |
|-------|-------------|
| artwork | Paintings, sculptures, etc. |
| antiquities | Archaeological items |
| museum_collections | Curated collections |
| library_collections | Rare books, manuscripts |
| archival_records | Historical documents |
| natural_heritage | Natural specimens |
| monuments | Buildings, structures |
| memorabilia | Historical objects |

---

## Valuation Methods

| Method | Code | Description |
|--------|------|-------------|
| Cost | cost | Original acquisition cost |
| Fair Value | fair_value | Current market value |
| Replacement Cost | replacement | Cost to replace |
| Insurance Value | insurance | Insured amount |
| Nominal | nominal | R1 token value |

---

## Service Methods

### GrapService

```php
namespace ahgGrapPlugin\Service;

class GrapService
{
    // Asset Management
    public function createAsset(int $objectId, array $data): int
    public function updateAsset(int $id, array $data): bool
    public function getAsset(int $objectId): ?array
    public function listAssets(array $filters): Collection
    
    // Valuations
    public function recordValuation(int $assetId, array $data): int
    public function getValuationHistory(int $assetId): Collection
    public function getAssetsForRevaluation(int $days = 90): Collection
    
    // Impairments
    public function recordImpairment(int $assetId, array $data): int
    public function reverseImpairment(int $impairmentId, array $data): bool
    
    // Movements
    public function recordMovement(int $assetId, array $data): int
    public function getMovementHistory(int $assetId): Collection
    
    // Reporting
    public function getAssetRegister(array $filters): Collection
    public function getBalanceSheet(string $date): array
    public function getMovementReport(string $startDate, string $endDate): array
    public function getValuationReport(): array
    public function getComplianceSummary(): array
    
    // Export
    public function exportAssetRegister(string $format): string
    public function generateAnnualReport(int $year): string
}
```

---

## Reports

| Report | Description |
|--------|-------------|
| Asset Register | Complete list with values |
| Balance Sheet | Financial position |
| Movement Schedule | Additions/disposals |
| Valuation Summary | Current valuations |
| Compliance Report | GRAP 103 checklist |

---

## Views (Database)

```sql
-- Summary view
CREATE VIEW v_grap_103_summary AS
SELECT 
    asset_class,
    COUNT(*) as asset_count,
    SUM(current_carrying_amount) as total_value,
    SUM(CASE WHEN recognition_status = 'recognised' THEN 1 ELSE 0 END) as recognised_count
FROM grap_heritage_asset
WHERE status = 'active'
GROUP BY asset_class;

-- Balance sheet view
CREATE VIEW v_grap_balance_sheet AS
SELECT
    asset_class,
    SUM(cost_of_acquisition) as gross_cost,
    SUM(accumulated_impairment) as impairment,
    SUM(current_carrying_amount) as net_value
FROM grap_heritage_asset
WHERE status = 'active' AND recognition_status = 'recognised'
GROUP BY asset_class;
```

---

## CLI Tasks

### heritageInstallTask

Installs the heritage accounting database schema and optional regional configurations.

```php
// Location: lib/task/heritageInstallTask.class.php

class heritageInstallTask extends sfBaseTask
{
    // Namespace: heritage
    // Command: install
}
```

**Options:**

| Option | Type | Description |
|--------|------|-------------|
| `--application` | optional | Application name (default: qubit) |
| `--env` | required | Environment (default: cli) |
| `--region` | optional | Region code(s) to install (comma-separated) |
| `--all-regions` | none | Install all available regions |

**Usage Examples:**

```bash
# Install core schema only
php symfony heritage:install

# Install core + Africa IPSAS region
php symfony heritage:install --region=africa_ipsas

# Install core + multiple regions
php symfony heritage:install --region=africa_ipsas,south_africa_grap

# Install core + ALL regions
php symfony heritage:install --all-regions
```

**Process Flow:**

```
┌──────────────────────────────────────────────────────────────┐
│                    heritage:install                           │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  Step 1: Install Core Schema                                  │
│  ├── Load core.sql from database/ directory                  │
│  ├── Execute SQL statements                                  │
│  └── Handle "table exists" gracefully                        │
│                                                               │
│  Step 2: Install Regions (if specified)                      │
│  ├── Load RegionManager                                      │
│  ├── For each region:                                        │
│  │   ├── Call RegionManager::installRegion()                 │
│  │   ├── Install compliance rules                            │
│  │   └── Report success/failure                              │
│  └── Skip already-installed regions                          │
│                                                               │
│  Step 3: Summary                                              │
│  ├── Show installed regions                                  │
│  └── Display next steps                                      │
│                                                               │
└──────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_436c9718.png)
```

---

### heritageRegionTask

Manages regional heritage accounting standards.

```php
// Location: lib/task/heritageRegionTask.class.php

class heritageRegionTask extends sfBaseTask
{
    // Namespace: heritage
    // Command: region
}
```

**Options:**

| Option | Type | Description |
|--------|------|-------------|
| `--application` | optional | Application name (default: qubit) |
| `--env` | required | Environment (default: cli) |
| `--install` | optional | Install a region by code |
| `--uninstall` | optional | Uninstall a region by code |
| `--set-active` | optional | Set active region for institution |
| `--info` | optional | Show region details |
| `--repository` | optional | Repository ID for --set-active |
| `--currency` | optional | Currency override for --set-active |

**Usage Examples:**

```bash
# List all regions with status
php symfony heritage:region

# Install a region
php symfony heritage:region --install=africa_ipsas

# Uninstall a region
php symfony heritage:region --uninstall=uk_frs

# Set active region globally
php symfony heritage:region --set-active=africa_ipsas

# Set active region for specific repository
php symfony heritage:region --set-active=africa_ipsas --repository=5

# Set active region with currency override
php symfony heritage:region --set-active=africa_ipsas --currency=USD

# Show region details
php symfony heritage:region --info=africa_ipsas
```

**Available Regions:**

| Code | Standard | Description |
|------|----------|-------------|
| africa_ipsas | IPSAS 45 | Africa: Zimbabwe, Kenya, Nigeria, Ghana, etc. |
| south_africa_grap | GRAP 103 | South Africa: National Treasury compliance |
| uk_frs | FRS 102 | United Kingdom: Charity Commission SORP |
| usa_government | GASB 34 | USA: State and local governments |
| usa_nonprofit | FASB 958 | USA: Museums, galleries, non-profits |
| australia_nz | AASB 116 | Australia/New Zealand: AASB compliance |
| canada_psas | PSAS 3150 | Canada: Public accounts |
| international_private | IAS 16 | International private sector |

**Region Info Output:**

```
=== Region: Africa (IPSAS 45) ===

Code:           africa_ipsas
Status:         INSTALLED
Default Currency: USD
Regulatory Body: IPSASB (International Public Sector Accounting Standards Board)
Countries:      Zimbabwe, Kenya, Nigeria, Ghana, Tanzania, Uganda, Rwanda, Botswana

Installed:      2026-01-15 10:30:45

Accounting Standard:
  Code: IPSAS-45
  Name: IPSAS 45 - Property, Plant, and Equipment
  Description: International standard for heritage asset accounting
  Compliance Rules: 24
```

---

## RegionManager

The RegionManager singleton handles region installation and configuration.

```php
// Location: lib/Regions/RegionManager.php

class RegionManager
{
    // Singleton access
    public static function getInstance(): RegionManager

    // Region operations
    public function getAvailableRegions(): Collection
    public function installRegion(string $regionCode): array
    public function uninstallRegion(string $regionCode): array
    public function setActiveRegion(string $regionCode, ?int $repositoryId = null, array $options = []): array
    public function getActiveRegion(?int $repositoryId = null): ?object
}
```

**installRegion() Return Structure:**

```php
[
    'success' => bool,
    'message' => string,
    'already_installed' => bool,  // if region was already installed
    'standard_name' => string,
    'standard_code' => string,
    'compliance_rules_installed' => int,
    'error' => string  // only on failure
]
```

**setActiveRegion() Return Structure:**

```php
[
    'success' => bool,
    'message' => string,
    'standard_code' => string,
    'error' => string  // only on failure
]
```

---

## Database Tables (Regional)

### heritage_regional_config

```sql
CREATE TABLE heritage_regional_config (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region_code VARCHAR(50) NOT NULL UNIQUE,
    region_name VARCHAR(255) NOT NULL,
    default_currency CHAR(3) NOT NULL,
    regulatory_body VARCHAR(255),
    countries JSON,
    is_installed TINYINT(1) DEFAULT 0,
    installed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### heritage_accounting_standard

```sql
CREATE TABLE heritage_accounting_standard (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region_code VARCHAR(50) NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    effective_date DATE,
    version VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_region (region_code)
);
```

### heritage_compliance_rule

```sql
CREATE TABLE heritage_compliance_rule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    standard_id BIGINT UNSIGNED NOT NULL,
    rule_code VARCHAR(50) NOT NULL,
    rule_name VARCHAR(255) NOT NULL,
    description TEXT,
    requirement_level ENUM('mandatory', 'recommended', 'optional') DEFAULT 'mandatory',
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_standard (standard_id)
);
```

---

## Cron Integration

The heritage region tasks can be integrated with the AHG Settings cron system:

| Job Name | Command | Recommended Schedule |
|----------|---------|---------------------|
| Heritage Region Install | `heritage:region --install=<code>` | One-time |
| Heritage Region Management | `heritage:region` | On-demand |

---

*Part of the AtoM AHG Framework*
