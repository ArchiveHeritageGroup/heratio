# ahgFederationPlugin Technical Documentation

## Overview

The Federation Plugin enables inter-institutional data sharing for Heratio instances. It implements:

- **OAI-PMH Harvesting** - Periodic import of records from peer repositories
- **Federated Search** (#88) - Real-time search across multiple institutions
- **Vocabulary Synchronization** (#89) - Taxonomy sharing with conflict resolution

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     ahgFederationPlugin                          │
├─────────────────────────────────────────────────────────────────┤
│  Services                                                        │
│  ├── FederatedSearchService    Real-time cross-peer search      │
│  ├── VocabSyncService          Taxonomy synchronization         │
│  └── HarvestClient             OAI-PMH harvesting (existing)    │
├─────────────────────────────────────────────────────────────────┤
│  Database Tables                                                 │
│  ├── federation_peer           Peer registry                    │
│  ├── federation_peer_search    Search API configuration         │
│  ├── federation_search_cache   Result caching                   │
│  ├── federation_search_log     Search analytics                 │
│  ├── federation_vocab_sync     Vocabulary sync config           │
│  ├── federation_term_mapping   Term mappings between peers      │
│  ├── federation_vocab_change   Change tracking                  │
│  └── federation_vocab_sync_log Sync session logs                │
├─────────────────────────────────────────────────────────────────┤
│  Dropdown Integration (ahgCorePlugin)                            │
│  └── AhgTaxonomyService        Status/config values             │
└─────────────────────────────────────────────────────────────────┘
```

## File Structure

```
ahgFederationPlugin/
├── config/
│   └── ahgFederationPluginConfiguration.class.php
├── database/
│   └── install.sql              # All table definitions
├── lib/
│   ├── FederatedSearchService.php
│   ├── VocabSyncService.php
│   └── HarvestClient.php        # Existing OAI-PMH client
└── modules/
    └── federation/
        ├── actions/
        └── templates/
```

## Database Schema

### Core Tables

#### federation_peer
```sql
CREATE TABLE federation_peer (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    base_url VARCHAR(500) NOT NULL,
    oai_identifier VARCHAR(255) NULL,
    api_key VARCHAR(255) NULL,
    description TEXT NULL,
    contact_email VARCHAR(255) NULL,
    default_metadata_prefix VARCHAR(50) DEFAULT 'oai_dc',
    default_set VARCHAR(255) NULL,
    harvest_interval_hours INT DEFAULT 24,
    is_active TINYINT(1) DEFAULT 1,
    last_harvest_at DATETIME NULL,
    last_harvest_status VARCHAR(50) NULL,
    last_harvest_records INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### federation_peer_search
```sql
CREATE TABLE federation_peer_search (
    id INT PRIMARY KEY AUTO_INCREMENT,
    peer_id INT NOT NULL,
    search_api_url VARCHAR(500) NULL,
    search_api_key VARCHAR(255) NULL,
    search_enabled TINYINT(1) DEFAULT 1,
    search_timeout_ms INT DEFAULT 5000,
    search_max_results INT DEFAULT 50,
    search_priority INT DEFAULT 100,
    last_search_at DATETIME NULL,
    last_search_status VARCHAR(50) NULL,  -- ahg_dropdown: federation_search_status
    avg_response_time_ms INT DEFAULT 0,
    FOREIGN KEY (peer_id) REFERENCES federation_peer(id)
);
```

#### federation_vocab_sync
```sql
CREATE TABLE federation_vocab_sync (
    id INT PRIMARY KEY AUTO_INCREMENT,
    peer_id INT NOT NULL,
    taxonomy_id INT NOT NULL,
    sync_direction VARCHAR(50) DEFAULT 'pull',      -- ahg_dropdown: federation_sync_direction
    sync_enabled TINYINT(1) DEFAULT 1,
    conflict_resolution VARCHAR(50) DEFAULT 'skip', -- ahg_dropdown: federation_conflict_resolution
    sync_interval_hours INT DEFAULT 24,
    last_sync_at DATETIME NULL,
    last_sync_status VARCHAR(50) NULL,              -- ahg_dropdown: federation_session_status
    FOREIGN KEY (peer_id) REFERENCES federation_peer(id),
    FOREIGN KEY (taxonomy_id) REFERENCES taxonomy(id)
);
```

### Dropdown Integration

All ENUM-style values use `VARCHAR(50)` with values managed in `ahg_dropdown`:

| Column | Taxonomy Code | Values |
|--------|---------------|--------|
| `sync_direction` | `federation_sync_direction` | pull, push, bidirectional |
| `conflict_resolution` | `federation_conflict_resolution` | prefer_local, prefer_remote, skip, merge |
| `status` (sessions) | `federation_session_status` | running, completed, failed, cancelled |
| `mapping_status` | `federation_mapping_status` | matched, created, conflict, skipped |
| `change_type` | `federation_change_type` | term_added, term_updated, term_deleted, term_moved, relation_added, relation_removed |
| `search_status` | `federation_search_status` | success, timeout, error |

## Services

### FederatedSearchService

Executes parallel searches across federation peers.

```php
namespace AhgFederation;

use ahgCorePlugin\Services\AhgTaxonomyService;

class FederatedSearchService
{
    // Status constants matching ahg_dropdown codes
    public const STATUS_SUCCESS = 'success';
    public const STATUS_TIMEOUT = 'timeout';
    public const STATUS_ERROR = 'error';

    /**
     * Execute federated search
     */
    public function search(string $query, array $options = []): FederatedSearchResult
    {
        // 1. Get active search peers
        // 2. Check cache
        // 3. Execute parallel cURL requests
        // 4. Merge and rank results
        // 5. Cache results
        // 6. Log search
    }

    /**
     * Get dropdown choices for forms
     */
    public static function getSearchStatusChoices(bool $includeEmpty = true): array
    {
        $service = new AhgTaxonomyService();
        return $service->getFederationSearchStatuses($includeEmpty);
    }
}
```

#### Usage Example

```php
$searchService = new FederatedSearchService();

// Execute search
$result = $searchService->search('archival records', [
    'type' => 'informationobject',
    'limit' => 50,
    'cache' => true,
]);

// Access results
foreach ($result->results as $item) {
    echo $item['title'] . ' from ' . $item['source']['peerName'];
}

// Get statistics
$stats = $result->peerStats;
echo "Queried: {$stats['queried']}, Responded: {$stats['responded']}";
```

### VocabSyncService

Manages vocabulary synchronization between peers.

```php
namespace AhgFederation;

class VocabSyncService
{
    // Direction constants
    public const DIRECTION_PULL = 'pull';
    public const DIRECTION_PUSH = 'push';
    public const DIRECTION_BIDIRECTIONAL = 'bidirectional';

    // Conflict resolution constants
    public const CONFLICT_PREFER_LOCAL = 'prefer_local';
    public const CONFLICT_PREFER_REMOTE = 'prefer_remote';
    public const CONFLICT_SKIP = 'skip';
    public const CONFLICT_MERGE = 'merge';

    /**
     * Export taxonomy as JSON
     */
    public function exportTaxonomy(int $taxonomyId, array $options = []): array

    /**
     * Import taxonomy from JSON
     */
    public function importTaxonomy(array $data, array $options = []): VocabSyncResult

    /**
     * Sync with a peer
     */
    public function syncWithPeer(int $peerId, int $taxonomyId, string $direction = 'pull'): VocabSyncResult

    /**
     * Get dropdown choices for forms
     */
    public static function getSyncDirectionChoices(bool $includeEmpty = true): array
    public static function getConflictResolutionChoices(bool $includeEmpty = true): array
}
```

#### Usage Example

```php
$vocabService = new VocabSyncService();

// Export a taxonomy
$export = $vocabService->exportTaxonomy($taxonomyId);
// Returns: ['taxonomy' => [...], 'terms' => [...], 'termCount' => 150]

// Import a taxonomy
$result = $vocabService->importTaxonomy($data, [
    'conflictResolution' => VocabSyncService::CONFLICT_MERGE,
    'targetTaxonomyId' => $localTaxonomyId,
]);

// Sync with peer
$result = $vocabService->syncWithPeer($peerId, $taxonomyId, VocabSyncService::DIRECTION_PULL);
echo $result->getSummary();
// Output: "Pull sync of 'Subjects': 5 added, 2 updated, 0 skipped, 1 conflicts"
```

## AhgTaxonomyService Integration

Federation constants and methods in `ahgCorePlugin/lib/Services/AhgTaxonomyService.php`:

```php
// Constants
public const FEDERATION_SYNC_DIRECTION = 'federation_sync_direction';
public const FEDERATION_CONFLICT_RESOLUTION = 'federation_conflict_resolution';
public const FEDERATION_HARVEST_ACTION = 'federation_harvest_action';
public const FEDERATION_SESSION_STATUS = 'federation_session_status';
public const FEDERATION_MAPPING_STATUS = 'federation_mapping_status';
public const FEDERATION_CHANGE_TYPE = 'federation_change_type';
public const FEDERATION_SEARCH_STATUS = 'federation_search_status';

// Convenience methods
public function getFederationSyncDirections(bool $includeEmpty = true): array
public function getFederationConflictResolutions(bool $includeEmpty = true): array
public function getFederationSessionStatuses(bool $includeEmpty = true): array
public function getFederationSessionStatusesWithColors(): array
public function getFederationMappingStatuses(bool $includeEmpty = true): array
public function getFederationMappingStatusesWithColors(): array
public function getFederationChangeTypes(bool $includeEmpty = true): array
public function getFederationSearchStatuses(bool $includeEmpty = true): array
public function getFederationSearchStatusesWithColors(): array
```

### Using in Templates

```php
<?php
use ahgCorePlugin\Services\AhgTaxonomyService;
$taxonomy = new AhgTaxonomyService();
?>

<select name="sync_direction">
  <?php foreach ($taxonomy->getFederationSyncDirections() as $code => $label): ?>
    <option value="<?php echo $code ?>"><?php echo $label ?></option>
  <?php endforeach ?>
</select>

<!-- With colors for status badges -->
<?php
$statuses = $taxonomy->getFederationSessionStatusesWithColors();
$status = $statuses[$record->status] ?? null;
?>
<span class="badge" style="background-color: <?php echo $status->color ?? '#666' ?>">
  <?php echo $status->name ?? $record->status ?>
</span>
```

## Seed Data

Federation dropdown values in `ahgCorePlugin/database/install.sql`:

```sql
-- Sync Direction
INSERT INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, sort_order, is_default) VALUES
('federation_sync_direction', 'Federation Sync Direction', 'pull', 'Pull (from remote)', 10, 1),
('federation_sync_direction', 'Federation Sync Direction', 'push', 'Push (to remote)', 20, 0),
('federation_sync_direction', 'Federation Sync Direction', 'bidirectional', 'Bidirectional', 30, 0);

-- Conflict Resolution
INSERT INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, sort_order, is_default) VALUES
('federation_conflict_resolution', 'Federation Conflict Resolution', 'prefer_local', 'Prefer Local', 10, 0),
('federation_conflict_resolution', 'Federation Conflict Resolution', 'prefer_remote', 'Prefer Remote', 20, 0),
('federation_conflict_resolution', 'Federation Conflict Resolution', 'skip', 'Skip Conflicts', 30, 1),
('federation_conflict_resolution', 'Federation Conflict Resolution', 'merge', 'Merge', 40, 0);

-- Session Status (with colors)
INSERT INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_default) VALUES
('federation_session_status', 'Federation Session Status', 'running', 'Running', '#2196f3', 10, 1),
('federation_session_status', 'Federation Session Status', 'completed', 'Completed', '#4caf50', 20, 0),
('federation_session_status', 'Federation Session Status', 'failed', 'Failed', '#f44336', 30, 0),
('federation_session_status', 'Federation Session Status', 'cancelled', 'Cancelled', '#9e9e9e', 40, 0);

-- Search Status (with colors)
INSERT INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_default) VALUES
('federation_search_status', 'Federation Search Status', 'success', 'Success', '#4caf50', 10, 1),
('federation_search_status', 'Federation Search Status', 'timeout', 'Timeout', '#ff9800', 20, 0),
('federation_search_status', 'Federation Search Status', 'error', 'Error', '#f44336', 30, 0);
```

## API Endpoints

### Search API

```
GET /api/federation/search?q={query}&limit={n}
```

Response:
```json
{
  "success": true,
  "data": {
    "query": "archival records",
    "total": 45,
    "duration_ms": 234.5,
    "cached": false,
    "peers": {
      "queried": 3,
      "responded": 2,
      "timeout": 1,
      "error": 0
    },
    "results": [
      {
        "id": "123",
        "title": "Record Title",
        "description": "...",
        "source": {
          "peerId": 1,
          "peerName": "National Archives",
          "peerUrl": "https://archives.example.org",
          "originalUrl": "https://archives.example.org/record/123"
        },
        "score": 0.95
      }
    ]
  }
}
```

### Vocabulary Export API

```
GET /api/federation/vocab/{taxonomyId}
```

Response:
```json
{
  "taxonomy": {
    "id": 35,
    "name": "Subjects",
    "usage": "Subject access points"
  },
  "terms": [
    {
      "id": 100,
      "code": "ART",
      "name": "Art",
      "parentId": null,
      "translations": {
        "en": "Art",
        "af": "Kuns"
      },
      "children": [...]
    }
  ],
  "termCount": 150,
  "exportedAt": "2024-02-04T10:30:00Z",
  "exportFormat": "heritage-vocab-1.0"
}
```

### Vocabulary Import API

```
POST /api/federation/vocab/import
Content-Type: application/json

{
  "taxonomy": {...},
  "terms": [...]
}
```

Response:
```json
{
  "success": true,
  "stats": {
    "added": 5,
    "updated": 2,
    "skipped": 10,
    "conflicts": 1,
    "errors": []
  }
}
```

## Installation

### Database Setup

```bash
# Run federation install script
mysql -u root archive < /usr/share/nginx/archive/atom-ahg-plugins/ahgFederationPlugin/database/install.sql

# Ensure dropdown values exist (in ahgCorePlugin install.sql)
mysql -u root archive < /usr/share/nginx/archive/atom-ahg-plugins/ahgCorePlugin/database/install.sql
```

### Enable Plugin

```bash
php bin/atom extension:enable ahgFederationPlugin
php symfony cc
```

## Dependencies

- **ahgCorePlugin** - Required for AhgTaxonomyService (dropdown management)
- **Laravel Query Builder** - Database access via Illuminate\Database
- **cURL** - Parallel HTTP requests for federated search

## Related Issues

- #88 - Federated search across institutions
- #89 - Vocabulary synchronization between peers

## Related Documentation

- [Federation User Guide](../federation-user-guide.md)
- [ahgCorePlugin Technical Docs](ahgCorePlugin.md)
- [AHG Dropdown User Guide](../ahg-settings-user-guide.md)
