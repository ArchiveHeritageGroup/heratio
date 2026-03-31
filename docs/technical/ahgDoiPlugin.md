# ahgDoiPlugin - Technical Documentation

**Version:** 1.0.0  
**Category:** Identifiers / Integration  
**Dependencies:** atom-framework, DataCite API account

---

## Overview

DOI (Digital Object Identifier) minting and management via DataCite for persistent identifiers on archival records. Supports batch minting, metadata synchronization, queue-based processing, and lifecycle management.

---

## Features

| Feature | Description |
|---------|-------------|
| **DOI Minting** | Single and batch minting via DataCite API |
| **Metadata Sync** | Keep DOI metadata current with record changes |
| **Queue Processing** | Async processing to handle rate limits |
| **Auto-Mint** | Automatic minting on record publish |
| **Deactivation** | Tombstone workflow for deleted records |
| **Export** | CSV/JSON export of all DOIs |
| **Reports** | Statistics and analytics dashboard |

---

## Database Schema

### ERD Diagram

```
+-----------------------------------------------+
|                  ahg_doi                       |
+-----------------------------------------------+
| PK id BIGINT UNSIGNED                         |
| FK object_id INT                              |---+
|    object_type VARCHAR(50)                    |   |
|                                               |   |
| -- DOI DATA --                                |   |
|    doi VARCHAR(255) UNIQUE                    |   |
|    datacite_id VARCHAR(255)                   |   |
|    status ENUM                                |   |
|    prefix VARCHAR(50)                         |   |
|    suffix VARCHAR(100)                        |   |
|                                               |   |
| -- METADATA --                                |   |
|    object_title VARCHAR(500)                  |   |
|    target_url TEXT                            |   |
|    metadata_json JSON                         |   |
|                                               |   |
| -- STATUS --                                  |   |
|    minted_at TIMESTAMP                        |   |
|    synced_at TIMESTAMP                        |   |
|    verified_at TIMESTAMP                      |   |
|    deactivated_at TIMESTAMP                   |   |
|    deactivation_reason TEXT                   |   |
|                                               |   |
| -- AUDIT --                                   |   |
| FK created_by INT                             |   |
| FK repository_id INT                          |   |
|    created_at TIMESTAMP                       |   |
|    updated_at TIMESTAMP                       |   |
+-----------------------------------------------+   |
                    |                               |
                    | 1:N                           |
                    v                               |
+-----------------------------------------------+   |
|              ahg_doi_queue                     |   |
+-----------------------------------------------+   |
| PK id BIGINT UNSIGNED                         |   |
| FK object_id INT                              |---+
|    action ENUM                                |
|    status ENUM                                |
|    priority INT                               |
|    attempts INT                               |
|    max_attempts INT                           |
|    error_message TEXT                         |
|    scheduled_at TIMESTAMP                     |
|    started_at TIMESTAMP                       |
|    completed_at TIMESTAMP                     |
|    created_at TIMESTAMP                       |
|    updated_at TIMESTAMP                       |
+-----------------------------------------------+

+-----------------------------------------------+
|              ahg_doi_config                    |
+-----------------------------------------------+
| PK id BIGINT UNSIGNED                         |
| FK repository_id INT                          |
|    datacite_repository_id VARCHAR(255)        |
|    datacite_password VARCHAR(255) ENCRYPTED   |
|    datacite_prefix VARCHAR(50)                |
|    datacite_shoulder VARCHAR(50)              |
|    environment ENUM('test', 'production')     |
|    auto_mint TINYINT                          |
|    default_state ENUM                         |
|    url_template TEXT                          |
|    is_active TINYINT                          |
|    created_at TIMESTAMP                       |
|    updated_at TIMESTAMP                       |
+-----------------------------------------------+

+-----------------------------------------------+
|              ahg_doi_log                       |
+-----------------------------------------------+
| PK id BIGINT UNSIGNED                         |
| FK doi_id BIGINT UNSIGNED                     |
|    action VARCHAR(50)                         |
|    status VARCHAR(50)                         |
|    request_data JSON                          |
|    response_data JSON                         |
|    error_message TEXT                         |
| FK performed_by INT                           |
|    created_at TIMESTAMP                       |
+-----------------------------------------------+
```

---

## DOI States

| State | DataCite API Value | Description |
|-------|--------------------|-------------|
| draft | `draft` | Reserved, not resolvable |
| registered | `registered` | Resolvable but not indexed |
| findable | `findable` | Fully public and indexed |
| deleted | State change event | Tombstone, was public |
| failed | (internal) | Minting/sync error |

---

## Service Methods

### DoiService

```php
namespace ahgDoiPlugin\Services;

class DoiService
{
    // Configuration
    public function getConfig(?int $repositoryId = null): ?object
    public function saveConfig(array $data): bool
    public function testConnection(?int $repositoryId = null): array

    // Minting
    public function mintDoi(int $objectId): array
    public function queueForMinting(int $objectId, string $action = 'mint'): int
    public function processQueue(int $limit = 50): array
    public function buildMetadata(object $object): array
    
    // Sync
    public function syncDoi(int $doiId): array
    public function bulkSync(array $options = []): array
    public function queueForSync(array $options = []): int
    
    // State Management
    public function updateDoiState(int $doiId, string $state): array
    public function deactivateDoi(int $doiId, string $reason = ''): array
    public function reactivateDoi(int $doiId): array
    
    // Queries
    public function getDoi(int $id): ?object
    public function getDoiByObjectId(int $objectId): ?object
    public function hasDoi(int $objectId): bool
    public function getStats(): array
    public function getRecentDois(int $limit = 10): Collection
    
    // Export
    public function exportToCsv(array $options = []): string
    public function exportToJson(array $options = []): string
    
    // Verification
    public function verifyResolution(int $doiId): array
}
```

---

## DataCite API Integration

### Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/dois` | POST | Create new DOI |
| `/dois/{id}` | PUT | Update DOI metadata |
| `/dois/{id}` | GET | Retrieve DOI data |
| `/dois/{id}` | DELETE | Delete DOI (draft only) |

### Authentication

```php
// Base64 encoded credentials
$auth = base64_encode("{$repositoryId}:{$password}");
$headers = [
    'Authorization' => "Basic {$auth}",
    'Content-Type' => 'application/vnd.api+json',
];
```

### API Environments

| Environment | Base URL |
|-------------|----------|
| Test | `https://api.test.datacite.org` |
| Production | `https://api.datacite.org` |

---

## Metadata Mapping

### DataCite Schema 4.4 Mapping

| DataCite Field | AtoM Source |
|----------------|-------------|
| `titles[0].title` | `informationObject.title` |
| `creators[0].name` | `informationObject.creators[0].name` or repository name |
| `publisher` | Repository name |
| `publicationYear` | `informationObject.dates[0].date` year |
| `types.resourceTypeGeneral` | Based on level of description |
| `descriptions[0].description` | `informationObject.scopeAndContent` |
| `subjects` | `informationObject.subjects` |
| `dates` | `informationObject.dates` |
| `language` | `informationObject.language` |
| `rightsList` | `informationObject.accessConditions` |

### Resource Type Mapping

| Level of Description | ResourceTypeGeneral |
|---------------------|---------------------|
| Fonds | Collection |
| Series | Collection |
| File | Text |
| Item | Text |
| Collection | Collection |
| (Digital) | Image, Audiovisual, Dataset |

---

## CLI Tasks

### doi:mint

```bash
php symfony doi:mint [options]

Options:
  --id=ID              Mint for specific record
  --all                Mint for all eligible records
  --repository=ID      Filter by repository
  --level=LEVEL        Filter by level of description
  --limit=N            Maximum to process (default: 100)
  --dry-run            Preview without minting
```

### doi:sync

```bash
php symfony doi:sync [options]

Options:
  --all                Sync all DOIs
  --id=ID              Sync specific DOI
  --status=STATUS      Filter by status
  --repository=ID      Filter by repository
  --limit=N            Maximum to sync
  --queue              Queue for async processing
  --dry-run            Preview without syncing
```

### doi:deactivate

```bash
php symfony doi:deactivate [options]

Options:
  --id=ID              DOI record ID to deactivate
  --object-id=ID       Object ID whose DOI to deactivate
  --reason=TEXT        Reason for deactivation
  --reactivate         Reactivate instead of deactivate
  --list-deleted       List all deactivated DOIs
  --dry-run            Preview without changes
```

---

## Auto-Mint Hook

```php
// In ahgDoiPluginConfiguration.class.php
$this->dispatcher->connect('QubitInformationObject.postSave', [$this, 'onRecordSave']);

public function onRecordSave(sfEvent $event)
{
    $record = $event->getSubject();
    
    if ($service->shouldAutoMint($record)) {
        $service->queueForMinting($record->id, 'mint');
    }
}
```

### Auto-Mint Conditions

1. Record is published (`publication_status_id` = published)
2. Auto-mint enabled for repository
3. Record level matches configured levels
4. No existing DOI for record

---

## Queue Processing

### Queue Worker

```php
// Process pending queue items
$service->processQueue(50);

// Cron job (every 5 minutes)
*/5 * * * * cd /path/to/atom && php symfony doi:process-queue >> /var/log/atom/doi.log 2>&1
```

### Queue Actions

| Action | Description |
|--------|-------------|
| mint | Create new DOI |
| update | Update DOI metadata |
| sync | Full metadata sync |
| deactivate | Set to deleted state |
| reactivate | Restore to findable |

---

## Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/admin/doi` | index | Dashboard |
| `/admin/doi/config` | config | Configuration |
| `/admin/doi/browse` | browse | List all DOIs |
| `/admin/doi/view/:id` | view | View DOI details |
| `/admin/doi/mint/:id` | mint | Mint DOI for record |
| `/admin/doi/batch-mint` | batchMint | Batch minting |
| `/admin/doi/update/:id` | update | Update DOI metadata |
| `/admin/doi/queue` | queue | Queue management |
| `/admin/doi/sync` | sync | Bulk sync |
| `/admin/doi/deactivate/:id` | deactivate | Deactivate DOI |
| `/admin/doi/reactivate/:id` | reactivate | Reactivate DOI |
| `/admin/doi/verify/:id` | verify | Verify resolution |
| `/admin/doi/export` | export | Export CSV/JSON |
| `/admin/doi/report` | report | Reports |
| `/api/doi/mint/:id` | apiMint | API: Mint |
| `/api/doi/status/:id` | apiStatus | API: Get status |
| `/doi/:doi` | resolve | Public DOI landing |

---

## GlamIdentifierService Integration

```php
// In GlamIdentifierService.php
public function getMintedDoi(int $objectId): ?string
{
    $doi = DB::table('ahg_doi')
        ->where('object_id', $objectId)
        ->whereIn('status', ['findable', 'registered'])
        ->first();
    
    return $doi->doi ?? null;
}

public function hasMintedDoi(int $objectId): bool
{
    return DB::table('ahg_doi')
        ->where('object_id', $objectId)
        ->whereIn('status', ['findable', 'registered', 'draft'])
        ->exists();
}

public function getAllIdentifiers(int $objectId, ?string $sector = null): array
{
    $identifiers = [];
    
    // Add DOI if exists
    if ($doi = $this->getMintedDoi($objectId)) {
        $identifiers['doi'] = [
            'type' => 'doi',
            'value' => $doi,
            'url' => "https://doi.org/{$doi}",
            'display' => "DOI: {$doi}",
        ];
    }
    
    // Add sector-specific identifiers...
    return $identifiers;
}
```

---

## Record Badge Integration

The `_recordBadge.php` partial is included in information object views:

```php
// In sfIsadPlugin/templates/indexSuccess.php
<?php include_partial('doi/recordBadge', ['resource' => $resource]) ?>
```

Features:
- Shows DOI link if exists
- "Mint DOI" button for admins if no DOI
- AJAX minting without page reload
- Status badge (findable, registered, draft)

---

## AHG Settings Integration

### System Info Page

DOI statistics displayed via `getDoiStatistics()`:
- Total DOIs
- Count by status
- Queue pending
- Configuration status

### Cron Jobs Page

DOI commands listed:
- `doi:mint` - Batch minting
- `doi:sync` - Metadata sync
- `doi:deactivate` - Deactivation
- `doi:process-queue` - Queue processing

---

## Error Handling

| Error Code | Meaning | Action |
|------------|---------|--------|
| 401 | Invalid credentials | Check config |
| 404 | DOI not found | Re-mint |
| 422 | Invalid metadata | Check required fields |
| 429 | Rate limited | Queue and retry |
| 500 | DataCite error | Log and retry |

### Retry Logic

```php
$maxAttempts = 3;
$retryDelay = [60, 300, 900]; // 1min, 5min, 15min

if ($attempts < $maxAttempts) {
    $queue->update([
        'status' => 'pending',
        'scheduled_at' => now()->addSeconds($retryDelay[$attempts]),
        'attempts' => $attempts + 1,
    ]);
}
```

---

## Security

- DataCite passwords stored encrypted
- Admin-only access to minting
- Audit log for all DOI operations
- Rate limiting on API endpoints

---

*Part of the AtoM AHG Framework*
