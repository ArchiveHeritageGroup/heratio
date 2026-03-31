# ahgProvenancePlugin - Technical Documentation

**Version:** 1.0.3
**Category:** Provenance
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Chain of custody and provenance tracking plugin for archival records, museum objects, and library materials. Provides comprehensive ownership history documentation with support for certainty levels, Nazi-era provenance checking, cultural property status, and visual timeline visualization.

---

## Architecture

```
+---------------------------------------------------------------------+
|                      ahgProvenancePlugin                             |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                     Symfony Module                             |  |
|  |  provenance/                                                   |  |
|  |    actions.class.php     - Controller actions                  |  |
|  |    components.class.php  - Reusable components                 |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                   Service Layer                                |  |
|  |  ProvenanceService                                             |  |
|  |    - getProvenanceForObject()  - buildTimeline()               |  |
|  |    - generateSummary()         - createRecord()                |  |
|  |    - addEvent()                - getStatistics()               |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                  Repository Layer                              |  |
|  |  ProvenanceRepository                                          |  |
|  |    - getByInformationObjectId() - getEvents()                  |  |
|  |    - saveRecord()               - saveEvent()                  |  |
|  |    - saveAgent()                - getStatistics()              |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Database Tables                             |  |
|  |  provenance_record   provenance_event   provenance_agent       |  |
|  |  provenance_record_i18n  provenance_event_i18n                 |  |
|  |  provenance_agent_i18n   provenance_document                   |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+----------------------------------+
|        provenance_agent          |
+----------------------------------+
| PK id INT                        |
|    actor_id INT (FK)             |
|    agent_type ENUM               |
|    name VARCHAR(500)             |
|    contact_info TEXT             |
|    location VARCHAR(500)         |
|    country_code VARCHAR(3)       |
|    verified TINYINT(1)           |
|    verified_by INT               |
|    verified_at TIMESTAMP         |
|    notes TEXT                    |
|    created_by INT                |
|    created_at TIMESTAMP          |
|    updated_at TIMESTAMP          |
+----------------------------------+
         |
         | 1:N (provenance_agent_i18n)
         v
+----------------------------------+
|     provenance_agent_i18n        |
+----------------------------------+
| PK id INT                        |
| PK culture VARCHAR(16)           |
|    name VARCHAR(500)             |
|    biographical_note TEXT        |
|    notes TEXT                    |
+----------------------------------+

+----------------------------------+
|       provenance_record          |
+----------------------------------+
| PK id INT                        |
| FK information_object_id INT     |
| FK provenance_agent_id INT       |
|    donor_id INT                  |
|    donor_agreement_id INT        |
|                                  |
|  -- Status --                    |
|    current_status ENUM           |
|    custody_type ENUM             |
|                                  |
|  -- Acquisition --               |
|    acquisition_type ENUM         |
|    acquisition_date DATE         |
|    acquisition_date_text VARCHAR |
|    acquisition_price DECIMAL     |
|    acquisition_currency VARCHAR  |
|                                  |
|  -- Certainty --                 |
|    certainty_level ENUM          |
|    has_gaps TINYINT(1)           |
|    gap_description TEXT          |
|                                  |
|  -- Research --                  |
|    research_status ENUM          |
|    research_notes TEXT           |
|                                  |
|  -- Nazi-era --                  |
|    nazi_era_provenance_checked   |
|    nazi_era_provenance_clear     |
|    nazi_era_notes TEXT           |
|                                  |
|  -- Cultural Property --         |
|    cultural_property_status ENUM |
|    cultural_property_notes TEXT  |
|                                  |
|    provenance_summary TEXT       |
|    is_complete TINYINT(1)        |
|    is_public TINYINT(1)          |
|    created_by INT                |
|    created_at TIMESTAMP          |
|    updated_at TIMESTAMP          |
+----------------------------------+
         |
         | 1:N (events)
         v
+----------------------------------+
|       provenance_event           |
+----------------------------------+
| PK id INT                        |
| FK provenance_record_id INT      |
| FK from_agent_id INT             |
| FK to_agent_id INT               |
|                                  |
|  -- Event Details --             |
|    event_type ENUM               |
|    event_date DATE               |
|    event_date_start DATE         |
|    event_date_end DATE           |
|    event_date_text VARCHAR       |
|    date_certainty ENUM           |
|                                  |
|  -- Location --                  |
|    event_location VARCHAR        |
|    event_city VARCHAR            |
|    event_country VARCHAR(3)      |
|                                  |
|  -- Financial --                 |
|    price DECIMAL(15,2)           |
|    currency VARCHAR(3)           |
|    sale_reference VARCHAR        |
|                                  |
|  -- Evidence --                  |
|    evidence_type ENUM            |
|    evidence_description TEXT     |
|    source_reference TEXT         |
|                                  |
|    certainty ENUM                |
|    sequence_number INT           |
|    notes TEXT                    |
|    is_public TINYINT(1)          |
|    created_by INT                |
|    created_at TIMESTAMP          |
|    updated_at TIMESTAMP          |
+----------------------------------+
         |
         | 1:N (documents)
         v
+----------------------------------+
|      provenance_document         |
+----------------------------------+
| PK id INT                        |
| FK provenance_record_id INT      |
| FK provenance_event_id INT       |
|                                  |
|    document_type ENUM            |
|    title VARCHAR(500)            |
|    description TEXT              |
|    document_date DATE            |
|    document_date_text VARCHAR    |
|                                  |
|  -- File Storage --              |
|    filename VARCHAR(500)         |
|    original_filename VARCHAR     |
|    file_path VARCHAR(1000)       |
|    mime_type VARCHAR(100)        |
|    file_size INT                 |
|                                  |
|  -- External Reference --        |
|    external_url VARCHAR(1000)    |
|    archive_reference VARCHAR     |
|                                  |
|    is_public TINYINT(1)          |
|    created_by INT                |
|    created_at TIMESTAMP          |
|    updated_at TIMESTAMP          |
+----------------------------------+
```

### SQL Schema

```sql
-- Provenance Agent (who owned/held the item)
CREATE TABLE IF NOT EXISTS provenance_agent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL COMMENT 'Link to AtoM actor if exists',
    agent_type ENUM('person', 'organization', 'family', 'unknown') DEFAULT 'person',
    name VARCHAR(500) NOT NULL,
    contact_info TEXT NULL,
    location VARCHAR(500) NULL,
    country_code VARCHAR(3) NULL,
    verified TINYINT(1) DEFAULT 0,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_id),
    INDEX idx_agent_type (agent_type),
    INDEX idx_name (name(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provenance Agent i18n
CREATE TABLE IF NOT EXISTS provenance_agent_i18n (
    id INT NOT NULL,
    culture VARCHAR(16) NOT NULL DEFAULT 'en',
    name VARCHAR(500) NULL,
    biographical_note TEXT NULL,
    notes TEXT NULL,
    PRIMARY KEY (id, culture),
    FOREIGN KEY (id) REFERENCES provenance_agent(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Main Provenance Record
CREATE TABLE IF NOT EXISTS provenance_record (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    provenance_agent_id INT NULL,
    donor_id INT NULL,
    donor_agreement_id INT NULL,

    current_status ENUM('owned', 'on_loan', 'deposited', 'unknown', 'disputed') DEFAULT 'owned',
    custody_type ENUM('permanent', 'temporary', 'loan', 'deposit') DEFAULT 'permanent',

    acquisition_type ENUM('donation', 'purchase', 'bequest', 'transfer', 'loan',
                          'deposit', 'exchange', 'field_collection', 'unknown') DEFAULT 'unknown',
    acquisition_date DATE NULL,
    acquisition_date_text VARCHAR(255) NULL,
    acquisition_price DECIMAL(15,2) NULL,
    acquisition_currency VARCHAR(3) NULL,

    certainty_level ENUM('certain', 'probable', 'possible', 'uncertain', 'unknown') DEFAULT 'unknown',
    has_gaps TINYINT(1) DEFAULT 0,
    gap_description TEXT NULL,

    research_status ENUM('not_started', 'in_progress', 'complete', 'inconclusive') DEFAULT 'not_started',
    research_notes TEXT NULL,

    nazi_era_provenance_checked TINYINT(1) DEFAULT 0,
    nazi_era_provenance_clear TINYINT(1) NULL,
    nazi_era_notes TEXT NULL,

    cultural_property_status ENUM('none', 'claimed', 'disputed', 'repatriated', 'cleared') DEFAULT 'none',
    cultural_property_notes TEXT NULL,

    provenance_summary TEXT NULL,
    is_complete TINYINT(1) DEFAULT 0,
    is_public TINYINT(1) DEFAULT 1,

    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_info_object (information_object_id),
    INDEX idx_agent (provenance_agent_id),
    INDEX idx_donor (donor_id),
    INDEX idx_status (current_status),
    INDEX idx_acquisition_type (acquisition_type),
    INDEX idx_certainty (certainty_level),
    INDEX idx_nazi_era (nazi_era_provenance_checked, nazi_era_provenance_clear),
    FOREIGN KEY (provenance_agent_id) REFERENCES provenance_agent(id) ON DELETE SET NULL,
    CONSTRAINT fk_provenance_record_info_object
        FOREIGN KEY (information_object_id)
        REFERENCES information_object(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provenance Event
CREATE TABLE IF NOT EXISTS provenance_event (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provenance_record_id INT NOT NULL,
    from_agent_id INT NULL,
    to_agent_id INT NULL,

    event_type ENUM(
        'creation', 'commission',
        'sale', 'purchase', 'auction',
        'gift', 'donation', 'bequest',
        'inheritance', 'descent',
        'loan_out', 'loan_return',
        'deposit', 'withdrawal',
        'transfer', 'exchange',
        'theft', 'recovery',
        'confiscation', 'restitution', 'repatriation',
        'discovery', 'excavation',
        'import', 'export',
        'authentication', 'appraisal',
        'conservation', 'restoration',
        'accessioning', 'deaccessioning',
        'unknown', 'other'
    ) NOT NULL DEFAULT 'unknown',

    event_date DATE NULL,
    event_date_start DATE NULL,
    event_date_end DATE NULL,
    event_date_text VARCHAR(255) NULL,
    date_certainty ENUM('exact', 'approximate', 'estimated', 'unknown') DEFAULT 'unknown',

    event_location VARCHAR(500) NULL,
    event_city VARCHAR(255) NULL,
    event_country VARCHAR(3) NULL,

    price DECIMAL(15,2) NULL,
    currency VARCHAR(3) NULL,
    sale_reference VARCHAR(255) NULL,

    evidence_type ENUM('documentary', 'physical', 'oral', 'circumstantial', 'none') DEFAULT 'none',
    evidence_description TEXT NULL,
    source_reference TEXT NULL,

    certainty ENUM('certain', 'probable', 'possible', 'uncertain') DEFAULT 'uncertain',
    sequence_number INT DEFAULT 0,
    notes TEXT NULL,
    is_public TINYINT(1) DEFAULT 1,

    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_record (provenance_record_id),
    INDEX idx_from_agent (from_agent_id),
    INDEX idx_to_agent (to_agent_id),
    INDEX idx_event_type (event_type),
    INDEX idx_event_date (event_date),
    INDEX idx_sequence (provenance_record_id, sequence_number),
    FOREIGN KEY (provenance_record_id) REFERENCES provenance_record(id) ON DELETE CASCADE,
    FOREIGN KEY (from_agent_id) REFERENCES provenance_agent(id) ON DELETE SET NULL,
    FOREIGN KEY (to_agent_id) REFERENCES provenance_agent(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provenance Document
CREATE TABLE IF NOT EXISTS provenance_document (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provenance_record_id INT NULL,
    provenance_event_id INT NULL,

    document_type ENUM(
        'deed_of_gift', 'bill_of_sale', 'invoice', 'receipt',
        'auction_catalog', 'exhibition_catalog',
        'inventory', 'insurance_record',
        'photograph', 'correspondence', 'certificate',
        'customs_document', 'export_license', 'import_permit',
        'appraisal', 'condition_report',
        'newspaper_clipping', 'publication',
        'oral_history', 'affidavit', 'legal_document',
        'other'
    ) NOT NULL DEFAULT 'other',

    title VARCHAR(500) NULL,
    description TEXT NULL,
    document_date DATE NULL,
    document_date_text VARCHAR(255) NULL,

    filename VARCHAR(500) NULL,
    original_filename VARCHAR(500) NULL,
    file_path VARCHAR(1000) NULL,
    mime_type VARCHAR(100) NULL,
    file_size INT NULL,

    external_url VARCHAR(1000) NULL,
    archive_reference VARCHAR(500) NULL,

    is_public TINYINT(1) DEFAULT 0,

    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_record (provenance_record_id),
    INDEX idx_event (provenance_event_id),
    INDEX idx_doc_type (document_type),
    FOREIGN KEY (provenance_record_id) REFERENCES provenance_record(id) ON DELETE CASCADE,
    FOREIGN KEY (provenance_event_id) REFERENCES provenance_event(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Enumeration Values

### Event Types

| Category | Values |
|----------|--------|
| Ownership Changes | sale, purchase, auction, gift, donation, bequest, inheritance, descent, transfer, exchange |
| Loans & Deposits | loan_out, loan_return, deposit, withdrawal |
| Creation & Discovery | creation, commission, discovery, excavation |
| Loss & Recovery | theft, recovery, confiscation, restitution, repatriation |
| Movement | import, export |
| Documentation | authentication, appraisal, conservation, restoration |
| Institutional | accessioning, deaccessioning |
| Other | unknown, other |

### Status Types

| Field | Values |
|-------|--------|
| current_status | owned, on_loan, deposited, unknown, disputed |
| custody_type | permanent, temporary, loan, deposit |
| acquisition_type | donation, purchase, bequest, transfer, loan, deposit, exchange, field_collection, unknown |
| certainty_level | certain, probable, possible, uncertain, unknown |
| research_status | not_started, in_progress, complete, inconclusive |
| cultural_property_status | none, claimed, disputed, repatriated, cleared |
| date_certainty | exact, approximate, estimated, unknown |
| evidence_type | documentary, physical, oral, circumstantial, none |
| document_type | deed_of_gift, bill_of_sale, invoice, receipt, auction_catalog, exhibition_catalog, inventory, insurance_record, photograph, correspondence, certificate, customs_document, export_license, import_permit, appraisal, condition_report, newspaper_clipping, publication, oral_history, affidavit, legal_document, other |
| agent_type | person, organization, family, unknown |

---

## Service Methods

### ProvenanceService

```php
namespace AhgProvenancePlugin\Service;

class ProvenanceService
{
    /**
     * Get complete provenance data for an information object
     * @return array{exists: bool, record: ?object, events: array, documents: array, timeline: array, summary: string}
     */
    public function getProvenanceForObject(int $objectId, string $culture = 'en'): array;

    /**
     * Build a timeline from events
     * @return array Timeline items with date, type, from/to agents, location
     */
    public function buildTimeline(array $events): array;

    /**
     * Generate human-readable provenance summary from events
     */
    public function generateSummary(?object $record, array $events): string;

    /**
     * Get event type label
     */
    public function getEventTypeLabel(string $type): string;

    /**
     * Get all event types organized by category (for dropdowns)
     */
    public function getEventTypes(): array;

    /**
     * Get acquisition types (for dropdowns)
     */
    public function getAcquisitionTypes(): array;

    /**
     * Get certainty levels (for dropdowns)
     */
    public function getCertaintyLevels(): array;

    /**
     * Create or update provenance record
     * @return int Record ID
     */
    public function createRecord(int $objectId, array $data, string $culture = 'en'): int;

    /**
     * Add event to provenance chain
     * @return int Event ID
     */
    public function addEvent(int $recordId, array $data, string $culture = 'en'): int;

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(): array;

    /**
     * Search agents by name
     */
    public function searchAgents(string $term): array;

    /**
     * Find or create agent
     * @return int Agent ID
     */
    public function findOrCreateAgent(string $name, string $type = 'person', ?int $actorId = null): int;
}
```

### ProvenanceRepository

```php
namespace AhgProvenancePlugin\Repository;

use Illuminate\Database\Capsule\Manager as DB;

class ProvenanceRepository
{
    /**
     * Get provenance record for an information object
     */
    public function getByInformationObjectId(int $objectId, string $culture = 'en'): ?object;

    /**
     * Get all provenance events for a record (chain of custody)
     */
    public function getEvents(int $recordId, string $culture = 'en'): array;

    /**
     * Get documents for a provenance record or event
     */
    public function getDocuments(int $recordId = null, int $eventId = null): array;

    /**
     * Get all agents (for dropdowns)
     */
    public function getAllAgents(string $culture = 'en'): array;

    /**
     * Search agents by name
     */
    public function searchAgents(string $term, int $limit = 20): array;

    /**
     * Create or update provenance record
     */
    public function saveRecord(array $data): int;

    /**
     * Save provenance record i18n
     */
    public function saveRecordI18n(int $id, string $culture, array $data): void;

    /**
     * Create or update event
     */
    public function saveEvent(array $data): int;

    /**
     * Create or update agent
     */
    public function saveAgent(array $data): int;

    /**
     * Delete event
     */
    public function deleteEvent(int $eventId): bool;

    /**
     * Get records with incomplete provenance
     */
    public function getIncompleteRecords(int $limit = 50): array;

    /**
     * Get records needing Nazi-era provenance check
     */
    public function getNaziEraUnchecked(int $limit = 50): array;

    /**
     * Get provenance statistics
     */
    public function getStatistics(): array;
}
```

---

## Routes

| Route | URL Pattern | Action | Description |
|-------|-------------|--------|-------------|
| provenance_index | `/provenance` | index | Dashboard with statistics |
| provenance_view | `/provenance/:slug` | view | View provenance for object |
| provenance_edit | `/provenance/:slug/edit` | edit | Edit provenance form |
| provenance_timeline | `/provenance/:slug/timeline` | timeline | Visual D3.js timeline |
| provenance_search_agents | `/provenance/searchAgents` | searchAgents | AJAX agent autocomplete |
| provenance_add_event | `/provenance/addEvent` | addEvent | AJAX add event |
| provenance_delete_event | `/provenance/deleteEvent` | deleteEvent | AJAX delete event |
| provenance_delete_document | `/provenance/deleteDocument/:id` | deleteDocument | AJAX delete document |

---

## Controller Actions

### provenanceActions

| Action | Description | Auth Required |
|--------|-------------|---------------|
| executeIndex | Dashboard with statistics | Yes |
| executeView | Display provenance for object | No |
| executeEdit | Edit form with processing | Yes |
| executeTimeline | D3.js visual timeline | No |
| executeSearchAgents | AJAX agent autocomplete | No |
| executeAddEvent | AJAX add event to chain | Yes |
| executeDeleteEvent | AJAX delete event | Yes |
| executeDeleteDocument | AJAX delete document | Yes |
| executeProvenanceDisplay | Component for embedding | No |

### provenanceComponents

| Component | Description |
|-----------|-------------|
| executeProvenanceDisplay | Embeddable summary for information object views |

---

## Templates

| Template | Description |
|----------|-------------|
| indexSuccess.php | Dashboard with statistics cards |
| viewSuccess.php | Full provenance view with timeline |
| editSuccess.php | Edit form with dynamic events/documents |
| timelineSuccess.php | D3.js visual timeline |
| _provenanceDisplay.php | Embeddable summary component |

---

## JavaScript

### provenance.js

Agent autocomplete functionality:
- Initializes autocomplete on `.agent-autocomplete` inputs
- Debounced search (300ms delay)
- Minimum 2 characters to trigger search
- Shows dropdown with agent name and type

### provenance-timeline.js

D3.js-based timeline visualization class:
- `ProvenanceTimeline(selector, options)` - Constructor
- Configurable colors for owner types
- Transfer event icons
- Interactive tooltips
- Date range scaling
- Certainty indicators (dashed borders)
- Gap highlighting

---

## CSS Classes

| Class | Description |
|-------|-------------|
| .timeline | Vertical timeline container |
| .timeline-item | Individual timeline entry |
| .timeline-marker | Circular marker with certainty color |
| .timeline-content | Event content card |
| .event-entry | Chain of custody event card |
| .certainty-certain | Green badge |
| .certainty-probable | Blue badge |
| .certainty-possible | Yellow badge |
| .certainty-uncertain | Red badge |
| .certainty-unknown | Gray badge |
| .agent-autocomplete-results | Autocomplete dropdown |
| .status-card | Status indicator with colored left border |

---

## Configuration

### Plugin Configuration

```php
class ahgProvenancePluginConfiguration extends sfPluginConfiguration
{
    public static $summary = 'Chain of custody and provenance tracking plugin';
    public static $version = '1.0.0';

    public function initialize()
    {
        // Connect to context.load_factories event
        $this->dispatcher->connect('context.load_factories', [$this, 'contextLoadFactories']);

        // Enable module
        $enabledModules = sfConfig::get('sf_enabled_modules', []);
        $enabledModules[] = 'provenance';
        sfConfig::set('sf_enabled_modules', array_unique($enabledModules));
    }

    public function contextLoadFactories(sfEvent $event)
    {
        // Add CSS and JS assets
        $context->response->addStylesheet('/plugins/ahgProvenancePlugin/web/css/provenance.css');
        $context->response->addJavaScript('/plugins/ahgProvenancePlugin/web/js/provenance.js');
    }
}
```

### extension.json

```json
{
    "name": "Provenance Tracking",
    "machine_name": "ahgProvenancePlugin",
    "version": "1.0.3",
    "description": "Chain of custody and provenance tracking for archival records, museum objects, and library materials",
    "author": "The Archive and Heritage Group",
    "license": "GPL-3.0",
    "requires": {
        "atom_framework": ">=1.0.0",
        "atom": ">=2.8",
        "php": ">=8.1"
    },
    "dependencies": [
        "ahgCorePlugin"
    ],
    "optional": {
        "extensions": [
            "ahgDonorAgreementPlugin",
            "ahgRightsPlugin"
        ]
    },
    "tables": [
        "provenance_record",
        "provenance_record_i18n",
        "provenance_event",
        "provenance_event_i18n",
        "provenance_agent",
        "provenance_agent_i18n",
        "provenance_document"
    ],
    "category": "provenance",
    "load_order": 45
}
```

---

## File Structure

```
ahgProvenancePlugin/
+-- config/
|   +-- ahgProvenancePluginConfiguration.class.php
|   +-- routing.yml
+-- database/
|   +-- install.sql
+-- data/
|   +-- sample_provenance_import.csv
+-- extension.json
+-- lib/
|   +-- Repository/
|   |   +-- ProvenanceRepository.php
|   +-- Service/
|       +-- ProvenanceService.php
+-- modules/
|   +-- provenance/
|       +-- actions/
|       |   +-- actions.class.php
|       |   +-- components.class.php
|       +-- templates/
|           +-- indexSuccess.php
|           +-- viewSuccess.php
|           +-- editSuccess.php
|           +-- timelineSuccess.php
|           +-- _provenanceDisplay.php
+-- web/
    +-- css/
    |   +-- provenance.css
    +-- js/
        +-- provenance.js
        +-- provenance-timeline.js
```

---

## Integration Points

### Embedding Provenance Display

Include the provenance summary component in other views:

```php
<?php include_component('provenance', 'provenanceDisplay', ['objectId' => $resource->id]) ?>
```

### Linking to Provenance

```php
// Link to view provenance
url_for(['module' => 'provenance', 'action' => 'view', 'slug' => $resource->slug])

// Link to edit provenance
url_for(['module' => 'provenance', 'action' => 'edit', 'slug' => $resource->slug])

// Link to timeline
url_for(['module' => 'provenance', 'action' => 'timeline', 'slug' => $resource->slug])
```

### AJAX Agent Search

```javascript
fetch('/provenance/searchAgents?term=' + encodeURIComponent(searchTerm))
  .then(r => r.json())
  .then(agents => {
    // agents = [{id, name, agent_type}, ...]
  });
```

---

## Data Flow

### Creating/Updating Provenance

```
1. User submits edit form
        |
        v
2. provenanceActions::executeEdit()
        |
        v
3. processForm()
   - Extract form data
   - Find/create current agent
   - Create/update provenance_record
   - Save i18n data
        |
        v
4. processEvents()
   - Delete existing events
   - Create new events from form arrays
   - Find/create from/to agents
        |
        v
5. processDocuments()
   - Handle file uploads
   - Create provenance_document records
        |
        v
6. Redirect to view page
```

### Timeline Generation

```
1. ProvenanceService::getProvenanceForObject()
        |
        v
2. Repository::getEvents()
   - Join with agents
   - Order by sequence_number, event_date
        |
        v
3. ProvenanceService::buildTimeline()
   - Format each event for display
   - Include date, type, from/to, location, certainty
        |
        v
4. ProvenanceService::generateSummary()
   - If manual summary exists, use it
   - Otherwise generate from events
```

---

## Statistics

The `getStatistics()` method returns:

```php
[
    'total' => int,           // Total provenance records
    'complete' => int,        // Records marked complete
    'incomplete' => int,      // Records not complete
    'has_gaps' => int,        // Records with gaps
    'nazi_era_checked' => int,
    'nazi_era_unchecked' => int,
    'disputed' => int,        // Disputed cultural property
    'by_acquisition_type' => ['donation' => 45, 'purchase' => 38, ...],
    'by_certainty' => ['certain' => 65, 'probable' => 42, ...]
]
```

---

## Security

- Edit actions require authentication
- View actions are public (respects `is_public` flag)
- AJAX endpoints check authentication for write operations
- File uploads stored in `/uploads/provenance/` with unique filenames
- No direct database access from templates

---

## Optional Dependencies

| Plugin | Integration |
|--------|-------------|
| ahgDonorAgreementPlugin | Link `donor_agreement_id` to donor agreements |
| ahgRightsPlugin | Connect provenance to rights information |

---

## Performance Considerations

- Events ordered by `sequence_number` then `event_date`
- Indexes on frequently queried columns
- I18n data retrieved via LEFT JOIN
- Cascade delete on `information_object` deletion
- Autocomplete debounced to 300ms

---

*Part of the AtoM AHG Framework - Version 1.0.3*
