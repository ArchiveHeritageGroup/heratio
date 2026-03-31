# ahgDAMPlugin - Technical Documentation

> **Version:** 1.3.14
> **Last Updated:** 2026-01-20
> **Category:** Sector-Specific
> **Dependencies:** ahgThemeB5Plugin (required)

---

## Overview

The ahgDAMPlugin provides Digital Asset Management functionality for born-digital and digitized materials including photographs, videos, audio files, documents, and 3D models. It includes specialized metadata fields for film/video heritage materials.

---

## Database Schema

### Core Tables

#### dam_iptc_metadata
Stores IPTC and technical metadata for digital assets.

```sql
CREATE TABLE dam_iptc_metadata (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL UNIQUE,

    -- IPTC Core
    headline VARCHAR(255),
    description TEXT,
    keywords TEXT,
    creator VARCHAR(255),
    credit_line VARCHAR(255),

    -- Location
    city VARCHAR(100),
    province_state VARCHAR(100),
    country VARCHAR(100),
    country_code CHAR(3),
    gps_latitude DECIMAL(10, 8),
    gps_longitude DECIMAL(11, 8),
    gps_altitude DECIMAL(10, 2),

    -- Production (Film/Video)
    duration_minutes INT UNSIGNED,
    production_country VARCHAR(100),
    production_country_code CHAR(3),

    -- Rights
    copyright_notice TEXT,
    rights_usage_terms TEXT,
    license_type VARCHAR(50),
    license_url VARCHAR(500),
    license_expiry DATE,

    -- Technical
    asset_type VARCHAR(50),
    file_format VARCHAR(50),
    mime_type VARCHAR(100),
    file_size BIGINT UNSIGNED,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dam_iptc_object (object_id),
    INDEX idx_dam_iptc_asset_type (asset_type)
);
```

#### dam_version_links
Tracks alternative language versions, formats, restorations, and edits.

```sql
CREATE TABLE dam_version_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    related_object_id INT NULL,
    version_type ENUM('language', 'format', 'restoration',
                      'directors_cut', 'censored', 'other') NOT NULL DEFAULT 'language',
    title VARCHAR(255) NOT NULL,
    language_code CHAR(3) NULL,
    language_name VARCHAR(50) NULL,
    year VARCHAR(10) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dam_version_object (object_id),
    INDEX idx_dam_version_related (related_object_id),
    INDEX idx_dam_version_type (version_type)
);
```

#### dam_format_holdings
Documents physical formats held at institutions.

```sql
CREATE TABLE dam_format_holdings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    format_type ENUM(
        '35mm', '16mm', '8mm', 'Super8',
        'VHS', 'Betacam', 'U-matic', 'DV',
        'DVD', 'Blu-ray', 'LaserDisc',
        'Digital_File', 'DCP', 'ProRes',
        'Nitrate', 'Safety', 'Polyester',
        'Audio_Reel', 'Audio_Cassette', 'Vinyl', 'CD',
        'Other'
    ) NOT NULL,
    format_details VARCHAR(255) NULL,
    holding_institution VARCHAR(255) NOT NULL,
    holding_location VARCHAR(255) NULL,
    accession_number VARCHAR(100) NULL,
    condition_status ENUM('excellent', 'good', 'fair',
                          'poor', 'deteriorating', 'unknown') DEFAULT 'unknown',
    access_status ENUM('available', 'restricted', 'preservation_only',
                       'digitized_available', 'on_request',
                       'staff_only', 'unknown') DEFAULT 'unknown',
    access_url VARCHAR(500) NULL,
    access_notes TEXT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    verified_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dam_holdings_object (object_id),
    INDEX idx_dam_holdings_institution (holding_institution),
    INDEX idx_dam_holdings_format (format_type),
    INDEX idx_dam_holdings_access (access_status)
);
```

#### dam_external_links
Stores links to ESAT, IMDb, Wikipedia, and other external databases.

```sql
CREATE TABLE dam_external_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    link_type ENUM(
        'ESAT', 'IMDb', 'SAFILM', 'NFVSA',
        'Wikipedia', 'Wikidata', 'VIAF',
        'YouTube', 'Vimeo', 'Archive_org',
        'BFI', 'AFI', 'Letterboxd', 'MUBI',
        'Filmography', 'Review', 'Academic', 'Press',
        'Other'
    ) NOT NULL,
    url VARCHAR(500) NOT NULL,
    title VARCHAR(255) NULL,
    description TEXT NULL,
    person_name VARCHAR(255) NULL,
    person_role VARCHAR(100) NULL,
    verified_date DATE NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dam_links_object (object_id),
    INDEX idx_dam_links_type (link_type),
    INDEX idx_dam_links_person (person_name)
);
```

---

## Module Structure

```
ahgDAMPlugin/
├── config/
│   └── ahgDAMPluginConfiguration.class.php
├── data/
│   ├── install.sql
│   └── migrations/
│       └── 2026_01_20_dam_film_metadata.sql
├── lib/
│   └── model/
│       └── DamIptcMetadata.class.php
└── modules/
    ├── ahgDAMPlugin/           # Sector view/edit module
    │   ├── actions/
    │   │   ├── indexAction.class.php
    │   │   └── editAction.class.php
    │   └── templates/
    │       ├── indexSuccess.php
    │       └── editSuccess.php
    └── ahgDam/                  # Dashboard and admin
        ├── actions/
        │   └── actions.class.php
        └── templates/
            └── dashboardSuccess.php
```

---

## Key Classes

### editAction.class.php

Main edit action for DAM assets. Handles:

- Core AtoM fields (title, dates, creators, subjects)
- IPTC metadata
- Version links (saveVersionLinks)
- Format holdings (saveFormatHoldings)
- External links (saveExternalLinks)
- Location data (saveItemLocation)

**Key Methods:**

```php
protected function saveIptcMetadataDirectly()
// Saves IPTC fields including duration_minutes, production_country

protected function saveVersionLinks()
// Saves to dam_version_links table

protected function saveFormatHoldings()
// Saves to dam_format_holdings table with all fields:
// format_type, format_details, holding_institution, holding_location,
// accession_number, condition_status, access_status, access_url,
// access_notes, verified_date, is_primary, notes

protected function saveExternalLinks()
// Saves to dam_external_links table with all fields:
// link_type, url, title, description, person_name, person_role,
// verified_date, is_primary
```

---

## Routes

| Route | Module | Action | Description |
|-------|--------|--------|-------------|
| `/dam/:slug` | ahgDAMPlugin | index | View DAM asset |
| `/dam/:slug/edit` | ahgDAMPlugin | edit | Edit DAM asset |
| `/dam/dashboard` | ahgDam | dashboard | DAM dashboard |
| `/dam/browse` | ahgDam | browse | Browse DAM assets |

---

## Template Variables

### indexSuccess.php (View)

| Variable | Type | Description |
|----------|------|-------------|
| `$resource` | QubitInformationObject | The AtoM resource |
| `$rawResource` | stdClass | Raw database record |
| `$iptc` | stdClass | IPTC metadata record |
| `$versionLinks` | Collection | Alternative versions |
| `$formatHoldings` | Collection | Format holdings |
| `$externalLinks` | Collection | External links |

### editSuccess.php (Edit)

Same variables plus:

| Variable | Type | Description |
|----------|------|-------------|
| `$form` | sfForm | Symfony form object |

---

## Asset Types

Supported asset type values:

| Value | Label |
|-------|-------|
| `photograph` | Photograph |
| `film` | Film |
| `video` | Video |
| `documentary` | Documentary |
| `audio` | Audio Recording |
| `podcast` | Podcast |
| `speech` | Speech/Lecture |
| `document` | Document |
| `manuscript` | Manuscript |
| `artwork` | Artwork |
| `map` | Map |
| `model_3d` | 3D Model |
| `dataset` | Dataset |
| `software` | Software |
| `website` | Website |

---

## Integration Points

### Loan Plugin Integration

The DAM module integrates with ahgLoanPlugin if enabled:

```php
<?php if (in_array('ahgLoanPlugin', sfProjectConfiguration::getActive()->getPlugins())): ?>
  <a href="<?php echo url_for(['module' => 'loan', 'action' => 'add',
      'type' => 'out', 'sector' => 'dam', 'object_id' => $rawResource->id]); ?>">
    New Loan
  </a>
<?php endif; ?>
```

### Preservation Plugin Integration

Compatible with ahgPreservationPlugin for:
- Checksum verification
- Format identification (PRONOM)
- SIP/AIP/DIP workflows

### Cart Plugin Integration

Integrates with ahgCartPlugin for ordering copies.

---

## Form Field Names

### IPTC Fields (Single Values)
- `duration_minutes`
- `production_country`
- `production_country_code`

### Version Links (Arrays)
- `version_id[]`
- `version_title[]`
- `version_type[]`
- `version_language[]`
- `version_language_code[]`
- `version_year[]`
- `version_notes[]`

### Format Holdings (Arrays)
- `holding_id[]`
- `holding_format[]`
- `holding_format_details[]`
- `holding_institution[]`
- `holding_location[]`
- `holding_accession[]`
- `holding_condition[]`
- `holding_access[]`
- `holding_url[]`
- `holding_access_notes[]`
- `holding_verified[]`
- `holding_primary[]`
- `holding_notes[]`

### External Links (Arrays)
- `link_id[]`
- `link_type[]`
- `link_url[]`
- `link_title[]`
- `link_description[]`
- `link_person[]`
- `link_role[]`
- `link_verified[]`
- `link_primary[]`

---

## CLI Commands

```bash
# Import DAM assets from CSV
php symfony dam:import /path/to/file.csv

# Export DAM metadata
php symfony dam:export --format=csv

# Regenerate thumbnails
php symfony dam:regenerate-derivatives
```

---

## Changelog

### v1.3.14 (2026-01-20)
- Added film/video metadata fields:
  - Running time (duration_minutes)
  - Production country
  - Alternative versions (dam_version_links)
  - Format holdings (dam_format_holdings)
  - External links (dam_external_links)
- Fixed "New License" to "New Loan" terminology
- Updated user guide with film metadata documentation

### v1.3.13
- Initial stable release
- IPTC metadata support
- GPS coordinates
- Asset type classification

---

*Part of the AtoM AHG Framework*
