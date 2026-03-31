# ahgAPIPlugin - Technical Documentation

**Version:** 1.2.0
**Category:** Integration
**Dependencies:** atom-framework

---

## Overview

Enhanced REST API v2 providing full CRUD operations, batch processing, search integration, and webhook support for external application integration.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      ahgAPIPlugin v1.2.0                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                   HTTP Request                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│                           ▼                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              AhgApiAction (Base Class)                  │   │
│  │  • Authentication (X-API-Key / Bearer / Session)        │   │
│  │  • Rate Limiting                                        │   │
│  │  • Scope Validation                                     │   │
│  │  • Request Logging                                      │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│     ┌─────────────────────┼─────────────────────┐              │
│     ▼                     ▼                     ▼              │
│  ┌─────────┐       ┌───────────┐       ┌───────────┐          │
│  │ Browse  │       │   CRUD    │       │ Webhook   │          │
│  │ Actions │       │  Actions  │       │  Actions  │          │
│  └─────────┘       └───────────┘       └───────────┘          │
│        │                  │                   │                │
│        │                  │                   ▼                │
│        │                  │         ┌───────────────────┐      │
│        │                  │         │  WebhookService   │      │
│        │                  │         │  • HMAC Signing   │      │
│        │                  │         │  • Retry Logic    │      │
│        │                  │         │  • Delivery Logs  │      │
│        │                  │         └───────────────────┘      │
│        │                  │                   │                │
│        │                  ▼                   │                │
│        │    ┌───────────────────────┐        │                │
│        │    │   WebhookService      │◀───────┘                │
│        │    │   ::trigger()         │────────────────┐        │
│        │    └───────────────────────┘                │        │
│        │                  │                          │        │
│        └──────────────────┼──────────────────────────┘        │
│                           ▼                                    │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              ApiRepository                              │   │
│  │  • Laravel Query Builder                                │   │
│  │  • Data Transformation                                  │   │
│  │  • Sector Mapping                                       │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│                           ▼                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              MySQL Database                             │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│         Webhook Delivery  │                                     │
│                           ▼                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │           External Applications (HTTP POST)             │   │
│  │           • X-Webhook-Signature: sha256=...             │   │
│  │           • Content-Type: application/json              │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_4644c7ce.png)
```

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────┐
│        ahg_api_key          │
├─────────────────────────────┤
│ PK id BIGINT               │
│ FK user_id INT             │──────┐
│    api_key VARCHAR(64)      │      │
│    name VARCHAR(255)        │      │
│    scopes VARCHAR(255)      │      │
│    rate_limit INT           │      │
│    is_active TINYINT        │      │
│    last_used_at TIMESTAMP   │      │
│    expires_at TIMESTAMP     │      │
│    created_at TIMESTAMP     │      │
│    updated_at TIMESTAMP     │      │
└─────────────────────────────┘      │
              │                       │
              │ 1:N                   │
              ▼                       │
┌─────────────────────────────┐      │
│        ahg_api_log          │      │
├─────────────────────────────┤      │
│ PK id BIGINT               │      │
│ FK api_key_id BIGINT       │──────┘
│ FK user_id INT             │
│    method VARCHAR(10)       │
│    endpoint VARCHAR(500)    │
│    status_code INT          │
│    response_time_ms INT     │
│    ip_address VARCHAR(45)   │
│    user_agent VARCHAR(500)  │
│    request_body TEXT        │
│    created_at TIMESTAMP     │
└─────────────────────────────┘

┌─────────────────────────────┐
│        ahg_webhook          │
├─────────────────────────────┤
│ PK id INT                  │
│ FK user_id INT             │
│    name VARCHAR(100)        │
│    url VARCHAR(500)         │
│    secret VARCHAR(64)       │
│    events JSON              │
│    entity_types JSON        │
│    is_active TINYINT        │
│    failure_count INT        │
│    last_triggered_at DATETIME│
│    created_at DATETIME      │
│    updated_at DATETIME      │
└─────────────────────────────┘
              │
              │ 1:N
              ▼
┌─────────────────────────────┐
│    ahg_webhook_delivery     │
├─────────────────────────────┤
│ PK id BIGINT               │
│ FK webhook_id INT          │
│    event_type VARCHAR(50)   │
│    entity_type VARCHAR(50)  │
│    entity_id INT            │
│    payload JSON             │
│    response_code INT        │
│    response_body TEXT       │
│    attempt_count INT        │
│    status ENUM              │
│    next_retry_at DATETIME   │
│    delivered_at DATETIME    │
│    created_at DATETIME      │
└─────────────────────────────┘
![wireframe](./images/wireframes/wireframe_b1e8a2f9.png)
```

### SQL Schema

```sql
CREATE TABLE ahg_api_key (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255),
    scopes VARCHAR(255) DEFAULT 'read',
    rate_limit INT DEFAULT 1000,
    is_active TINYINT(1) DEFAULT 1,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
);

CREATE TABLE ahg_api_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_key_id BIGINT UNSIGNED NULL,
    user_id INT NULL,
    method VARCHAR(10) NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    status_code INT NOT NULL,
    response_time_ms INT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    request_body TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key_id (api_key_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (api_key_id) REFERENCES ahg_api_key(id) ON DELETE SET NULL
);

CREATE TABLE ahg_webhook (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(64) NOT NULL,
    events JSON NOT NULL,
    entity_types JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    failure_count INT DEFAULT 0,
    last_triggered_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
);

CREATE TABLE ahg_webhook_delivery (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    payload JSON NOT NULL,
    response_code INT DEFAULT NULL,
    response_body TEXT DEFAULT NULL,
    attempt_count INT DEFAULT 1,
    status ENUM('pending', 'success', 'failed', 'retrying') DEFAULT 'pending',
    next_retry_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME DEFAULT NULL,
    INDEX idx_webhook_id (webhook_id),
    INDEX idx_status (status)
);
```

---

## Endpoints

### Core Endpoints

| Method | Endpoint | Action | Scope |
|--------|----------|--------|-------|
| GET | /api/v2 | Index | - |
| GET | /api/v2/descriptions | Browse | read |
| GET | /api/v2/descriptions/:slug | Read | read |
| POST | /api/v2/descriptions | Create | write |
| PUT | /api/v2/descriptions/:slug | Update | write |
| DELETE | /api/v2/descriptions/:slug | Delete | delete |
| GET | /api/v2/authorities | Browse | read |
| GET | /api/v2/authorities/:slug | Read | read |
| GET | /api/v2/repositories | Browse | read |
| GET | /api/v2/taxonomies | Browse | read |
| GET | /api/v2/taxonomies/:id/terms | Terms | read |
| POST | /api/v2/search | Search | read |
| POST | /api/v2/batch | Batch | write |
| GET | /api/v2/keys | List Keys | admin |
| POST | /api/v2/keys | Create Key | admin |
| DELETE | /api/v2/keys/:id | Delete Key | admin |

### Webhook Endpoints

| Method | Endpoint | Action | Scope |
|--------|----------|--------|-------|
| GET | /api/v2/webhooks | List webhooks | read |
| POST | /api/v2/webhooks | Create webhook | write |
| GET | /api/v2/webhooks/:id | Get webhook | read |
| PUT | /api/v2/webhooks/:id | Update webhook | write |
| DELETE | /api/v2/webhooks/:id | Delete webhook | delete |
| GET | /api/v2/webhooks/:id/deliveries | Delivery logs | read |
| POST | /api/v2/webhooks/:id/regenerate-secret | New secret | write |

---

## Service Methods

### ApiRepository

```php
namespace ahgAPIPlugin\Repository;

class ApiRepository
{
    // Descriptions
    public function getDescriptions(array $params): array
    public function getDescription(string $slug): ?array
    public function createDescription(array $data): array
    public function updateDescription(string $slug, array $data): array
    public function deleteDescription(string $slug): bool

    // Authorities
    public function getAuthorities(array $params): array
    public function getAuthority(string $slug): ?array

    // Repositories
    public function getRepositories(array $params): array

    // Taxonomies
    public function getTaxonomies(): array
    public function getTaxonomyTerms(int $taxonomyId): array

    // Search
    public function search(array $query): array

    // Batch
    public function processBatch(array $operations): array

    // Helpers
    protected function getSectorCode(int $displayStandardId): string
    protected function transformDescription(object $row, bool $detail = false): array
}
```

### WebhookService

```php
namespace AhgAPI\Services;

class WebhookService
{
    // Constants
    const EVENT_CREATED = 'item.created';
    const EVENT_UPDATED = 'item.updated';
    const EVENT_DELETED = 'item.deleted';
    const EVENT_PUBLISHED = 'item.published';
    const EVENT_UNPUBLISHED = 'item.unpublished';

    const ENTITY_DESCRIPTION = 'informationobject';
    const ENTITY_AUTHORITY = 'actor';
    const ENTITY_REPOSITORY = 'repository';
    const ENTITY_ACCESSION = 'accession';
    const ENTITY_TERM = 'term';

    const MAX_RETRIES = 5;
    const BASE_DELAY = 60; // seconds

    // Webhook CRUD
    public static function create(int $userId, array $data): array
    public static function update(int $webhookId, int $userId, array $data): array
    public static function delete(int $webhookId, int $userId): array
    public static function getById(int $webhookId): ?object
    public static function getByUser(int $userId, bool $activeOnly = false): array
    public static function regenerateSecret(int $webhookId, int $userId): array

    // Event Triggering
    public static function trigger(string $event, string $entityType, int $entityId, array $payload): int
    public static function createDelivery(int $webhookId, string $event, string $entityType, int $entityId, array $payload): int
    public static function deliver(int $deliveryId): bool

    // Signature
    public static function generateSignature(string $payload, string $secret): string
    public static function verifySignature(string $payload, string $signature, string $secret): bool

    // Retry Processing
    public static function processRetries(int $limit = 100): int
    public static function calculateBackoff(int $attempt): int

    // Delivery Logs
    public static function getDeliveryLogs(int $webhookId, int $limit, int $offset): array
    public static function getDeliveryStats(int $webhookId): array
    public static function cleanupOldDeliveries(int $daysToKeep = 30): int
}
```

### WebhookEventListener

```php
namespace AhgAPI\Services;

class WebhookEventListener
{
    // Call these from entity save/delete handlers
    public static function onEntitySaved(string $entityType, int $entityId, bool $isNew, array $data = []): void
    public static function onEntityDeleted(string $entityType, int $entityId, array $data = []): void
    public static function onEntityPublished(string $entityType, int $entityId, array $data = []): void
    public static function onEntityUnpublished(string $entityType, int $entityId, array $data = []): void

    // Payload builders
    public static function buildInformationObjectPayload($object): array
    public static function buildActorPayload($actor): array
    public static function buildRepositoryPayload($repository): array
}
```

---

## Configuration

### Plugin Settings

| Setting | Default | Description |
|---------|---------|-------------|
| api_v2_enabled | true | Enable/disable API v2 |
| default_rate_limit | 1000 | Requests per hour |
| log_requests | true | Log API requests |
| max_batch_size | 100 | Maximum batch operations |

---

## Authentication

### Methods

1. **X-API-Key Header** (Recommended)
   ```
   X-API-Key: your-api-key-here
   ```

2. **Bearer Token**
   ```
   Authorization: Bearer your-api-key-here
   ```

3. **Legacy Header**
   ```
   REST-API-Key: your-api-key-here
   ```

4. **Session** (Web browser)

### Scopes

| Scope | Binary | Permissions |
|-------|--------|-------------|
| read | 0001 | GET operations |
| write | 0010 | POST, PUT operations |
| delete | 0100 | DELETE operations |
| admin | 1000 | Key management |

---

## Webhook Technical Details

### HMAC Signature Verification

Each webhook delivery includes an `X-Webhook-Signature` header containing an HMAC SHA-256 signature. Receiving applications should verify this to ensure the request originated from AtoM.

**Signature Format:**
```
X-Webhook-Signature: sha256=<hex-encoded-hmac>
```

**Verification (PHP Example):**
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$secret = 'your-webhook-secret';

$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

// Process webhook payload
$data = json_decode($payload, true);
```

**Verification (Node.js Example):**
```javascript
const crypto = require('crypto');

function verifySignature(payload, signature, secret) {
    const expected = 'sha256=' + crypto
        .createHmac('sha256', secret)
        .update(payload)
        .digest('hex');
    return crypto.timingSafeEqual(
        Buffer.from(expected),
        Buffer.from(signature)
    );
}
```

### Retry Logic

Failed deliveries use exponential backoff:

| Attempt | Delay | Total Wait |
|---------|-------|------------|
| 1 | Immediate | 0 |
| 2 | 60 seconds | 1 min |
| 3 | 120 seconds | 3 min |
| 4 | 240 seconds | 7 min |
| 5 | 480 seconds | 15 min |

After 5 failures, the delivery is marked as `failed` and the webhook's `failure_count` is incremented.

### Webhook Payload Structure

```json
{
    "event": "item.created",
    "entity_type": "informationobject",
    "entity_id": 12345,
    "timestamp": "2024-01-15T10:30:00+00:00",
    "delivery_id": 1,
    "data": {
        "id": 12345,
        "slug": "my-record",
        "title": "Record Title",
        "action": "created"
    }
}
```

### CLI Commands

**Process pending retries (run via cron):**
```bash
php symfony api:webhook-process-retries [--limit=100] [--cleanup=30]
```

**Recommended cron entry:**
```bash
*/5 * * * * cd /path/to/atom && php symfony api:webhook-process-retries --cleanup=30 >> /var/log/atom/webhooks.log 2>&1
```

---

## Webhook Management UI

A web-based interface for managing webhooks is available in the AHG Settings plugin.

### Access

Navigate to: **Admin > AHG Plugin Settings > Webhooks**

Or directly: `/admin/ahg-settings/webhooks` or `/ahgSettings/webhooks`

### Features

| Feature | Description |
|---------|-------------|
| Create Webhook | Add new webhooks with name, URL, events, and entity types |
| Toggle Active | Enable/disable webhooks without deleting |
| Regenerate Secret | Generate a new HMAC secret (invalidates old signatures) |
| View Delivery Logs | See delivery history, status, and response codes |
| Delivery Statistics | Success/failure/pending counts per webhook |
| Delete Webhook | Remove webhook and all delivery history |

### UI Actions

**Creating a Webhook:**
1. Click "Create Webhook"
2. Enter name and target URL
3. Select user (owner)
4. Choose events to subscribe to
5. Choose entity types to filter
6. Save - **copy the secret immediately** (shown only once)

**Viewing Delivery Logs:**
1. Click "Logs" button on any webhook
2. View recent deliveries with status, response code, and timestamps
3. Failed deliveries show error messages

---

## Files

```
ahgAPIPlugin/
├── config/
│   └── ahgAPIPluginConfiguration.class.php
├── lib/
│   ├── AhgApiAction.class.php
│   ├── repository/
│   │   └── ApiRepository.php
│   ├── service/
│   │   └── ApiKeyService.php
│   ├── Services/
│   │   ├── WebhookService.php
│   │   └── WebhookEventListener.php
│   └── task/
│       └── webhookProcessRetriesTask.class.php
├── modules/
│   └── apiv2/
│       └── actions/
│           ├── indexAction.class.php
│           ├── descriptionsBrowseAction.class.php
│           ├── descriptionsReadAction.class.php
│           ├── descriptionsCreateAction.class.php
│           ├── descriptionsUpdateAction.class.php
│           ├── descriptionsDeleteAction.class.php
│           ├── authoritiesBrowseAction.class.php
│           ├── repositoriesBrowseAction.class.php
│           ├── taxonomiesBrowseAction.class.php
│           ├── searchAction.class.php
│           ├── batchAction.class.php
│           ├── keysBrowseAction.class.php
│           ├── keysCreateAction.class.php
│           ├── keysDeleteAction.class.php
│           ├── webhooksBrowseAction.class.php
│           ├── webhooksCreateAction.class.php
│           ├── webhooksReadAction.class.php
│           ├── webhooksUpdateAction.class.php
│           ├── webhooksDeleteAction.class.php
│           ├── webhookDeliveriesAction.class.php
│           └── webhookRegenerateSecretAction.class.php
├── database/
│   └── install.sql
└── extension.json
```

---

*Part of the AtoM AHG Framework*
