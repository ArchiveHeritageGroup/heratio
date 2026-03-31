# REST API v2 - Technical Reference

**Plugin:** ahgAPIPlugin  
**Version:** 1.0.0  
**Base URL:** `/api/v2`

---

## Table of Contents

1. [Architecture](#architecture)
2. [Authentication](#authentication)
3. [Endpoints Reference](#endpoints-reference)
4. [Request/Response Format](#requestresponse-format)
5. [Data Models](#data-models)
6. [Database Schema](#database-schema)
7. [Error Codes](#error-codes)
8. [Rate Limiting](#rate-limiting)
9. [Batch Operations](#batch-operations)
10. [Webhooks](#webhooks)
11. [Integration Examples](#integration-examples)
12. [Troubleshooting](#troubleshooting)

---

## Architecture

### Plugin Structure

```
ahgAPIPlugin/
├── config/
│   └── ahgAPIPluginConfiguration.class.php    # Routes & initialization
├── lib/
│   ├── AhgApiAction.class.php                 # Base action class
│   ├── repository/
│   │   └── ApiRepository.php                  # Data access layer
│   └── service/
│       └── ApiService.php                     # Business logic
├── modules/
│   └── apiv2/
│       ├── actions/
│       │   ├── indexAction.class.php          # GET /api/v2
│       │   ├── descriptionsBrowseAction.class.php
│       │   ├── descriptionsReadAction.class.php
│       │   ├── descriptionsCreateAction.class.php
│       │   ├── descriptionsUpdateAction.class.php
│       │   ├── descriptionsDeleteAction.class.php
│       │   ├── authoritiesBrowseAction.class.php
│       │   ├── repositoriesBrowseAction.class.php
│       │   ├── taxonomiesBrowseAction.class.php
│       │   ├── searchAction.class.php
│       │   ├── batchAction.class.php
│       │   └── keysAction.class.php
│       └── templates/
├── data/
│   └── install.sql                            # Database tables
└── extension.json                             # Plugin metadata
```

### Request Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                      REQUEST FLOW                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   HTTP Request                                                  │
│       │                                                         │
│       ▼                                                         │
│   ┌─────────────────────────────────────────────────────────┐  │
│   │  Nginx → PHP-FPM → Symfony Front Controller             │  │
│   └─────────────────────────────────────────────────────────┘  │
│       │                                                         │
│       ▼                                                         │
│   ┌─────────────────────────────────────────────────────────┐  │
│   │  ahgAPIPluginConfiguration::routingLoadConfiguration()  │  │
│   │  Route matching: /api/v2/* → apiv2 module               │  │
│   └─────────────────────────────────────────────────────────┘  │
│       │                                                         │
│       ▼                                                         │
│   ┌─────────────────────────────────────────────────────────┐  │
│   │  AhgApiAction (Base Class)                              │  │
│   │  • Authentication check                                 │  │
│   │  • Rate limit check                                     │  │
│   │  • Scope validation                                     │  │
│   └─────────────────────────────────────────────────────────┘  │
│       │                                                         │
│       ▼                                                         │
│   ┌─────────────────────────────────────────────────────────┐  │
│   │  Specific Action (e.g., descriptionsBrowseAction)       │  │
│   │  • Parameter validation                                 │  │
│   │  • Call Repository/Service                              │  │
│   │  • Format response                                      │  │
│   └─────────────────────────────────────────────────────────┘  │
│       │                                                         │
│       ▼                                                         │
│   ┌─────────────────────────────────────────────────────────┐  │
│   │  ApiRepository (Laravel Query Builder)                  │  │
│   │  • Database queries                                     │  │
│   │  • Data transformation                                  │  │
│   └─────────────────────────────────────────────────────────┘  │
│       │                                                         │
│       ▼                                                         │
│   JSON Response                                                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_271de83d.png)
```

### Technology Stack

| Component | Technology |
|-----------|------------|
| Framework | Symfony 1.4 (AtoM base) |
| Data Layer | Laravel Query Builder (Illuminate\Database) |
| Database | MySQL 8.0 |
| Search | Elasticsearch |
| Authentication | API Key (header-based) |
| Response Format | JSON |

---

## Authentication

### Supported Methods

| Method | Header | Example |
|--------|--------|---------|
| X-API-Key | `X-API-Key` | `X-API-Key: abc123...` |
| Bearer Token | `Authorization` | `Authorization: Bearer abc123...` |
| Legacy | `REST-API-Key` | `REST-API-Key: abc123...` |
| Session | Cookie | Logged-in user session |

### Authentication Flow

```php
// AhgApiAction.class.php - authenticate()

protected function authenticate()
{
    // 1. Check X-API-Key header (preferred)
    $apiKey = $this->request->getHttpHeader('X-API-Key');
    
    // 2. Check Authorization: Bearer header
    if (!$apiKey) {
        $auth = $this->request->getHttpHeader('Authorization');
        if (preg_match('/Bearer\s+(.+)/', $auth, $matches)) {
            $apiKey = $matches[1];
        }
    }
    
    // 3. Check legacy REST-API-Key header
    if (!$apiKey) {
        $apiKey = $this->request->getHttpHeader('REST-API-Key');
    }
    
    // 4. Validate key against database
    if ($apiKey) {
        return $this->validateApiKey($apiKey);
    }
    
    // 5. Fall back to session authentication
    return $this->context->user->isAuthenticated();
}
```

### API Key Scopes

| Scope | Binary | Permissions |
|-------|--------|-------------|
| `read` | 0001 | GET operations |
| `write` | 0010 | POST, PUT operations |
| `delete` | 0100 | DELETE operations |
| `admin` | 1000 | Key management, full access |

### API Key Table

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
```

---

## Endpoints Reference

### Index

```
GET /api/v2
```

Returns API information and available endpoints.

**Response:**
```json
{
    "success": true,
    "data": {
        "name": "AtoM AHG REST API",
        "version": "v2.0.0",
        "endpoints": {
            "descriptions": "/api/v2/descriptions",
            "authorities": "/api/v2/authorities",
            "repositories": "/api/v2/repositories",
            "taxonomies": "/api/v2/taxonomies",
            "search": "/api/v2/search",
            "batch": "/api/v2/batch",
            "keys": "/api/v2/keys"
        },
        "authentication": {
            "header": "X-API-Key: your-api-key",
            "bearer": "Authorization: Bearer your-api-key"
        }
    }
}
```

---

### Descriptions

#### List Descriptions

```
GET /api/v2/descriptions
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | 10 | Results per page (1-100) |
| `skip` | int | 0 | Offset for pagination |
| `sort` | string | `updated` | Sort field: `title`, `updated`, `created`, `reference_code` |
| `sort_direction` | string | `desc` | `asc` or `desc` |
| `sector` | string | - | Filter: `archive`, `library`, `museum`, `gallery`, `dam` |
| `level` | string | - | Filter: `fonds`, `subfonds`, `series`, `file`, `item` |
| `repository` | string | - | Repository slug |
| `parent` | string | - | Parent record slug |
| `culture` | string | `en` | Language code |

**Response:**
```json
{
    "success": true,
    "data": {
        "total": 1567,
        "limit": 10,
        "skip": 0,
        "results": [
            {
                "id": 1234,
                "slug": "company-records",
                "title": "Company Records",
                "level_of_description": "Fonds",
                "sector": "archive",
                "reference_code": "ZA-ARC-001",
                "dates": "1920-1985",
                "repository_slug": "main-archive",
                "has_digital_objects": true,
                "child_count": 45,
                "created_at": "2025-01-15T10:00:00Z",
                "updated_at": "2026-01-10T14:30:00Z"
            }
        ]
    }
}
```

#### Get Single Description

```
GET /api/v2/descriptions/:slug
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1234,
        "slug": "meeting-minutes-1965",
        "title": "Board Meeting Minutes 1965",
        "level_of_description": "File",
        "sector": "archive",
        "reference_code": "ZA-ARC-001-003-045",
        "dates": {
            "display": "1965",
            "start": "1965-01-01",
            "end": "1965-12-31",
            "type": "Creation"
        },
        "extent_and_medium": "1 file (45 pages)",
        "scope_and_content": "Minutes of monthly board meetings including...",
        "arrangement": "Chronological",
        "conditions_governing_access": "Open",
        "conditions_governing_reproduction": "Permission required",
        "language_of_material": ["en"],
        "physical_characteristics": "Good condition",
        "finding_aids": "Detailed inventory available",
        "related_units_of_description": "See also Personnel Files",
        "publication_note": "Cited in Smith (2010)",
        "archivists_note": "Arranged and described 2025",
        "rules_or_conventions": "ISAD(G)",
        "parent": {
            "id": 1200,
            "slug": "administrative-records",
            "title": "Administrative Records"
        },
        "repository": {
            "id": 12,
            "slug": "main-archive",
            "name": "Main Archive",
            "identifier": "ZA-ARC"
        },
        "creators": [
            {
                "id": 890,
                "slug": "acme-corporation",
                "name": "ACME Corporation",
                "type": "Corporate body"
            }
        ],
        "subjects": [
            {"id": 101, "name": "Board meetings"},
            {"id": 102, "name": "Corporate governance"}
        ],
        "places": [
            {"id": 201, "name": "Johannesburg"}
        ],
        "names": [
            {"id": 301, "slug": "john-smith", "name": "Smith, John"}
        ],
        "digital_objects": [
            {
                "id": 567,
                "filename": "minutes_1965.pdf",
                "mime_type": "application/pdf",
                "byte_size": 2456789,
                "checksum": "abc123def456...",
                "url": "/uploads/r/main-archive/minutes_1965.pdf",
                "thumbnail_url": "/uploads/r/main-archive/minutes_1965_142.jpg"
            }
        ],
        "children": [
            {"slug": "january-1965", "title": "January 1965"},
            {"slug": "february-1965", "title": "February 1965"}
        ],
        "created_at": "2025-06-15T10:00:00Z",
        "updated_at": "2026-01-10T14:30:00Z"
    }
}
```

#### Create Description

```
POST /api/v2/descriptions
Content-Type: application/json
```

**Required Scope:** `write`

**Request Body:**
```json
{
    "parent": "parent-record-slug",
    "repository": "main-archive",
    "level_of_description": "File",
    "title": "Meeting Minutes 1966",
    "dates": {
        "start": "1966-01-01",
        "end": "1966-12-31",
        "type": "Creation"
    },
    "extent_and_medium": "1 file (80 pages)",
    "scope_and_content": "Minutes of board meetings for 1966...",
    "creators": ["acme-corporation"],
    "subjects": ["Board meetings"],
    "culture": "en"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1235,
        "slug": "meeting-minutes-1966",
        "message": "Description created successfully"
    }
}
```

#### Update Description

```
PUT /api/v2/descriptions/:slug
Content-Type: application/json
```

**Required Scope:** `write`

**Request Body:** (partial update supported)
```json
{
    "title": "Updated Title",
    "scope_and_content": "Updated description text..."
}
```

#### Delete Description

```
DELETE /api/v2/descriptions/:slug
```

**Required Scope:** `delete`

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "Description deleted successfully"
    }
}
```

---

### Authorities

#### List Authorities

```
GET /api/v2/authorities
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | 10 | Results per page |
| `skip` | int | 0 | Offset |
| `type` | string | - | `person`, `corporate`, `family` |
| `sort` | string | `name` | Sort field |

#### Get Single Authority

```
GET /api/v2/authorities/:slug
```

---

### Repositories

#### List Repositories

```
GET /api/v2/repositories
```

#### Get Single Repository

```
GET /api/v2/repositories/:slug
```

---

### Taxonomies

#### List Taxonomies

```
GET /api/v2/taxonomies
```

#### Get Terms

```
GET /api/v2/taxonomies/:id/terms
```

---

### Search

```
POST /api/v2/search
Content-Type: application/json
```

**Request Body:**
```json
{
    "query": "meeting minutes",
    "filters": {
        "sector": "archive",
        "repository": "main-archive",
        "level": "file",
        "date_start": "1960-01-01",
        "date_end": "1970-12-31",
        "has_digital_object": true
    },
    "facets": ["sector", "repository", "level"],
    "highlight": true,
    "limit": 20,
    "skip": 0,
    "sort": "_score"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "total": 45,
        "results": [
            {
                "slug": "meeting-minutes-1965",
                "title": "Board Meeting Minutes 1965",
                "level_of_description": "File",
                "sector": "archive",
                "score": 12.5,
                "highlight": {
                    "title": ["Board <em>Meeting</em> <em>Minutes</em> 1965"],
                    "scope_and_content": ["...the <em>meeting</em> <em>minutes</em> record..."]
                }
            }
        ],
        "facets": {
            "sector": [
                {"value": "archive", "count": 40},
                {"value": "library", "count": 5}
            ],
            "repository": [
                {"value": "main-archive", "count": 45}
            ]
        }
    }
}
```

---

### Batch Operations

```
POST /api/v2/batch
Content-Type: application/json
```

**Required Scope:** `write` (and `delete` for delete operations)

**Request Body:**
```json
{
    "operations": [
        {
            "action": "create",
            "type": "description",
            "data": {
                "parent": "parent-slug",
                "level_of_description": "File",
                "title": "New Record 1"
            }
        },
        {
            "action": "update",
            "type": "description",
            "slug": "existing-record",
            "data": {
                "title": "Updated Title"
            }
        },
        {
            "action": "delete",
            "type": "description",
            "slug": "record-to-delete"
        }
    ],
    "stop_on_error": false
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "processed": 3,
        "succeeded": 2,
        "failed": 1,
        "results": [
            {"index": 0, "success": true, "slug": "new-record-1"},
            {"index": 1, "success": true, "slug": "existing-record"},
            {"index": 2, "success": false, "error": "Record not found"}
        ]
    }
}
```

---

### API Keys Management

#### List Keys

```
GET /api/v2/keys
```

**Required Scope:** `admin`

#### Create Key

```
POST /api/v2/keys
Content-Type: application/json
```

**Request Body:**
```json
{
    "name": "Website Integration",
    "scopes": ["read"],
    "rate_limit": 500,
    "expires_at": "2027-01-01T00:00:00Z"
}
```

#### Delete Key

```
DELETE /api/v2/keys/:id
```

---

## Request/Response Format

### Request Headers

| Header | Required | Description |
|--------|----------|-------------|
| `X-API-Key` | Yes* | API authentication key |
| `Content-Type` | For POST/PUT | `application/json` |
| `Accept` | No | `application/json` (default) |

### Response Format

All responses follow this structure:

**Success:**
```json
{
    "success": true,
    "data": { ... }
}
```

**Error:**
```json
{
    "success": false,
    "error": "Error Type",
    "message": "Detailed error message",
    "code": "ERROR_CODE"
}
```

---

## Data Models

### Description Transform

```php
// ApiRepository.php - transformDescription()

public function transformDescription($row, $detail = false)
{
    $data = [
        'id' => (int) $row->id,
        'slug' => $row->slug,
        'title' => $row->title,
        'level_of_description' => $this->getLevelName($row->level_of_description_id),
        'sector' => $this->getSectorCode($row->display_standard_id),
        'reference_code' => $row->identifier,
        'dates' => $row->date_display,
        'repository_slug' => $row->repository_slug,
        'updated_at' => $row->updated_at
    ];
    
    if ($detail) {
        // Add full ISAD(G) fields
        $data['extent_and_medium'] = $row->extent_and_medium;
        $data['scope_and_content'] = $row->scope_and_content;
        // ... additional fields
    }
    
    return $data;
}
```

### Sector Mapping

| display_standard_id | Sector Code | Standard |
|---------------------|-------------|----------|
| 353 | `archive` | ISAD(G) |
| 449 | `museum` | CCO |
| 1691 | `dam` | IPTC/XMP |
| 1696 | `gallery` | Spectrum 5.0 |
| 1705 | `library` | MARC-inspired |

---

## Database Schema

### API Tables

```sql
-- API Keys
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
    INDEX idx_user_id (user_id)
);

-- Request Logging
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
    INDEX idx_created_at (created_at)
);

-- Webhooks (Future)
CREATE TABLE ahg_webhook (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    events JSON NOT NULL,
    secret VARCHAR(64),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Error Codes

| HTTP Code | Error | Description |
|-----------|-------|-------------|
| 400 | `BAD_REQUEST` | Invalid request parameters |
| 401 | `UNAUTHORIZED` | Missing or invalid API key |
| 403 | `FORBIDDEN` | Insufficient permissions (scope) |
| 404 | `NOT_FOUND` | Resource not found |
| 405 | `METHOD_NOT_ALLOWED` | HTTP method not supported |
| 422 | `VALIDATION_ERROR` | Request data validation failed |
| 429 | `RATE_LIMIT_EXCEEDED` | Too many requests |
| 500 | `SERVER_ERROR` | Internal server error |

---

## Rate Limiting

### Default Limits

| Scope | Requests/Hour |
|-------|---------------|
| Default | 1000 |
| Custom | Per API key setting |

### Headers

| Header | Description |
|--------|-------------|
| `X-RateLimit-Limit` | Maximum requests allowed |
| `X-RateLimit-Remaining` | Requests remaining |
| `X-RateLimit-Reset` | Unix timestamp when limit resets |

### Implementation

```php
protected function checkRateLimit()
{
    $key = 'api_rate_' . $this->apiKeyInfo['id'];
    $limit = $this->apiKeyInfo['rate_limit'] ?? 1000;
    $window = 3600; // 1 hour
    
    $current = apcu_fetch($key) ?: 0;
    
    if ($current >= $limit) {
        $this->response->setHttpHeader('X-RateLimit-Limit', $limit);
        $this->response->setHttpHeader('X-RateLimit-Remaining', 0);
        return false;
    }
    
    apcu_inc($key, 1, $success, $window);
    
    $this->response->setHttpHeader('X-RateLimit-Limit', $limit);
    $this->response->setHttpHeader('X-RateLimit-Remaining', $limit - $current - 1);
    
    return true;
}
```

---

## Integration Examples

### PHP (cURL)

```php
<?php
$apiKey = 'your-api-key';
$baseUrl = 'https://your-site.com/api/v2';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $baseUrl . '/descriptions?sector=archive&limit=10',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $apiKey,
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);
$data = json_decode($response, true);

foreach ($data['data']['results'] as $record) {
    echo $record['title'] . "\n";
}
```

### Python (requests)

```python
import requests

API_KEY = 'your-api-key'
BASE_URL = 'https://your-site.com/api/v2'

headers = {
    'X-API-Key': API_KEY,
    'Accept': 'application/json'
}

# Get descriptions
response = requests.get(
    f'{BASE_URL}/descriptions',
    headers=headers,
    params={'sector': 'archive', 'limit': 10}
)

data = response.json()
for record in data['data']['results']:
    print(record['title'])

# Create description
new_record = {
    'parent': 'parent-slug',
    'level_of_description': 'File',
    'title': 'New Record',
    'scope_and_content': 'Description text...'
}

response = requests.post(
    f'{BASE_URL}/descriptions',
    headers={**headers, 'Content-Type': 'application/json'},
    json=new_record
)
```

### JavaScript (fetch)

```javascript
const API_KEY = 'your-api-key';
const BASE_URL = 'https://your-site.com/api/v2';

async function getDescriptions() {
    const response = await fetch(`${BASE_URL}/descriptions?sector=archive`, {
        headers: {
            'X-API-Key': API_KEY,
            'Accept': 'application/json'
        }
    });
    
    const data = await response.json();
    return data.data.results;
}

async function createDescription(record) {
    const response = await fetch(`${BASE_URL}/descriptions`, {
        method: 'POST',
        headers: {
            'X-API-Key': API_KEY,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(record)
    });
    
    return await response.json();
}
```

### Bash (curl)

```bash
#!/bin/bash
API_KEY="your-api-key"
BASE_URL="https://your-site.com/api/v2"

# Get descriptions
curl -s -H "X-API-Key: $API_KEY" \
    "$BASE_URL/descriptions?sector=archive&limit=10" | jq '.data.results[].title'

# Create description
curl -s -X POST \
    -H "X-API-Key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d '{"parent":"parent-slug","title":"New Record","level_of_description":"File"}' \
    "$BASE_URL/descriptions"
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| 401 Unauthorized | Invalid API key | Check key is correct and active |
| 403 Forbidden | Insufficient scope | Request key with required scopes |
| 404 Not Found | Wrong endpoint/slug | Verify URL and record exists |
| 429 Rate Limited | Too many requests | Implement request throttling |
| 500 Server Error | Server issue | Check AtoM logs |

### Debug Mode

Enable debug logging in `apps/qubit/config/settings.yml`:

```yaml
prod:
  .settings:
    logging_enabled: true
```

Check logs at:
```
/usr/share/nginx/archive/log/qubit_prod.log
```

### Testing Endpoints

```bash
# Test authentication
curl -I -H "X-API-Key: your-key" https://your-site.com/api/v2

# Test with verbose output
curl -v -H "X-API-Key: your-key" https://your-site.com/api/v2/descriptions

# Test POST with data
curl -X POST \
    -H "X-API-Key: your-key" \
    -H "Content-Type: application/json" \
    -d '{"query":"test"}' \
    https://your-site.com/api/v2/search
```

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-10 | Initial release |

---

*Part of the AtoM AHG Framework - Technical Documentation*
