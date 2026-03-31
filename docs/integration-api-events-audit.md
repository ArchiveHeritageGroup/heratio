# Heratio — Integration API: Events, Audit & Publish

## Overview

The **Integration API** extends Heratio's REST API v2 with endpoints for browsing workflow events, querying the audit trail, and managing publication status programmatically. These endpoints enable external systems to integrate with AtoM's workflow and compliance infrastructure.

This feature is part of the Heratio framework v2.8.2 by The Archive and Heritage Group (Pty) Ltd.

## Key Features

### Events API
- **Browse Events**: Filter workflow events by object, action type, user, date range, and correlation ID
- **Event Detail**: Retrieve a single event with related events from the same bulk operation
- **Correlation Tracking**: Group related events using correlation IDs for bulk operations

### Audit API
- **Browse Audit Log**: Filter audit entries by entity type, entity ID, action, user, and date range
- **PII Protection**: Old/new values are excluded by default and require `audit:admin` scope to access
- **Full Detail**: Single audit entry view with complete change history (admin only)

### Publish API
- **Readiness Check**: Evaluate publish gate rules for any record via API
- **Programmatic Publishing**: Publish records via API with full gate enforcement
- **Force Publishing**: Administrators can override blockers (audited)

## API Endpoints

### Events
| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| GET | `/api/v2/events` | events:read | Browse workflow events |
| GET | `/api/v2/events/:id` | events:read | Single event with related |
| GET | `/api/v2/events/correlation/:id` | events:read | Events by correlation ID |

### Audit
| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| GET | `/api/v2/audit` | audit:read | Browse audit log |
| GET | `/api/v2/audit/:id` | audit:admin | Single audit entry (with values) |

### Publish
| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| GET | `/api/v2/publish/readiness/:slug` | publish:read | Check publish gate status |
| POST | `/api/v2/publish/execute/:slug` | write + publish:write | Execute publish |

## Authentication

All endpoints use Heratio's existing API key authentication:
- **Header**: `Authorization: Bearer <key>` or `X-API-Key: <key>`
- **Session**: Authenticated browser sessions also work (all scopes granted)
- **Rate Limiting**: Hourly windows per API key (configurable)

## API Scopes

| Scope | Purpose |
|-------|---------|
| `events:read` | Browse and read workflow events |
| `audit:read` | Browse audit log (without old/new values) |
| `audit:admin` | Read audit entries with full old/new values |
| `publish:read` | Check publish readiness status |
| `publish:write` | Execute publish operations |

## Response Format

All responses follow the standard format:

```json
{
  "success": true,
  "data": {
    "results": [...],
    "total": 150,
    "limit": 50,
    "skip": 0
  }
}
```

### Response Headers
- `X-Correlation-Id` — Unique correlation ID for the request
- `X-Rate-Limit-Remaining` — Remaining rate limit (for API key auth)

## Query Parameters

### Events Browse (`GET /api/v2/events`)
| Parameter | Type | Description |
|-----------|------|-------------|
| `object_id` | int | Filter by object |
| `action` | string | Comma-separated action types |
| `user_id` | int | Filter by user |
| `date_from` | date | Start date (YYYY-MM-DD) |
| `date_to` | date | End date (YYYY-MM-DD) |
| `correlation_id` | string | Filter by correlation ID |
| `limit` | int | Results per page (max 200, default 50) |
| `skip` | int | Offset for pagination |

### Audit Browse (`GET /api/v2/audit`)
| Parameter | Type | Description |
|-----------|------|-------------|
| `entity_type` | string | Filter by entity type |
| `entity_id` | int | Filter by entity ID |
| `action` | string | Comma-separated action types |
| `user_id` | int | Filter by user |
| `date_from` | date | Start date |
| `date_to` | date | End date |
| `include_values` | bool | Include old/new values (requires audit:admin) |
| `limit` | int | Results per page |
| `skip` | int | Offset |

## Technical Requirements

- PHP 8.1+
- MySQL 8.0+
- Heratio Framework v2.8.2+
- ahgAPIPlugin (required)
- ahgWorkflowPlugin (required for events and publish endpoints)
- ahgAuditTrailPlugin (required for audit endpoints)

## Standards Compliance

- RESTful API design following HTTP method conventions
- JSON response format with pagination
- Correlation ID tracking for distributed operations
- PII-safe audit browsing (values excluded by default)
- Rate limiting for API abuse prevention

---

*The Archive and Heritage Group (Pty) Ltd*
*https://github.com/ArchiveHeritageGroup*
