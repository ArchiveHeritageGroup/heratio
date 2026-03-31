# ahgAuditTrailPlugin - Technical Documentation

**Version:** 1.0.0  
**Category:** Compliance  
**Dependencies:** atom-framework

---

## Overview

Comprehensive audit trail system capturing all CRUD operations, authentication events, file operations, and security-related actions for POPIA, NARSSA, and PAIA compliance.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                   ahgAuditTrailPlugin                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                 Symfony Event Dispatcher                │   │
│  │  • component.pre_update    • user.login                 │   │
│  │  • component.post_update   • user.logout                │   │
│  │  • component.pre_delete    • file.download              │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│                           ▼                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                AuditEventListener                       │   │
│  │  • Captures event context                               │   │
│  │  • Extracts changed fields                              │   │
│  │  • Identifies user/IP/session                           │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│                           ▼                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                  AuditService                           │   │
│  │  • Formats audit entry                                  │   │
│  │  • Stores to database                                   │   │
│  │  • Queues for async processing                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│                           ▼                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                   audit_log Table                       │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_08b65ae2.png)
```

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────┐
│            audit_log                │
├─────────────────────────────────────┤
│ PK id BIGINT                       │
│    uuid UUID UNIQUE                 │
│                                     │
│ -- WHO --                           │
│ FK user_id INT                     │
│    username VARCHAR(255)            │
│    user_email VARCHAR(255)          │
│    ip_address VARCHAR(45)           │
│    user_agent VARCHAR(500)          │
│    session_id VARCHAR(128)          │
│                                     │
│ -- WHAT --                          │
│    action VARCHAR(50)               │
│    entity_type VARCHAR(100)         │
│    entity_id INT                    │
│    entity_slug VARCHAR(255)         │
│    entity_title VARCHAR(500)        │
│                                     │
│ -- CHANGES --                       │
│    old_values JSON                  │
│    new_values JSON                  │
│    changed_fields JSON              │
│                                     │
│ -- CONTEXT --                       │
│    module VARCHAR(100)              │
│    controller VARCHAR(100)          │
│    route VARCHAR(255)               │
│    request_method VARCHAR(10)       │
│    request_url VARCHAR(500)         │
│                                     │
│ -- COMPLIANCE --                    │
│    retention_category VARCHAR(50)   │
│    retention_until DATE             │
│    is_sensitive TINYINT             │
│    compliance_flags JSON            │
│                                     │
│    created_at TIMESTAMP             │
└─────────────────────────────────────┘
         │
         │ Indexes
         ▼
┌─────────────────────────────────────┐
│ idx_user_id (user_id)               │
│ idx_action (action)                 │
│ idx_entity (entity_type, entity_id) │
│ idx_created_at (created_at)         │
│ idx_ip_address (ip_address)         │
│ idx_retention (retention_until)     │
└─────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_08714eae.png)
```

### SQL Schema

```sql
CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    
    -- Who
    user_id INT UNSIGNED NULL,
    username VARCHAR(255),
    user_email VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    session_id VARCHAR(128),
    
    -- What
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT UNSIGNED,
    entity_slug VARCHAR(255),
    entity_title VARCHAR(500),
    
    -- Changes
    old_values JSON,
    new_values JSON,
    changed_fields JSON,
    
    -- Context
    module VARCHAR(100),
    controller VARCHAR(100),
    route VARCHAR(255),
    request_method VARCHAR(10),
    request_url VARCHAR(500),
    
    -- Compliance
    retention_category VARCHAR(50) DEFAULT 'standard',
    retention_until DATE,
    is_sensitive TINYINT(1) DEFAULT 0,
    compliance_flags JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address),
    INDEX idx_retention (retention_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Action Types

| Action | Description |
|--------|-------------|
| create | Record created |
| update | Record modified |
| delete | Record deleted |
| view | Record viewed |
| download | File downloaded |
| login | User logged in |
| logout | User logged out |
| login_failed | Failed login attempt |
| permission_change | ACL modified |
| export | Data exported |
| import | Data imported |
| search | Search performed |

---

## Service Methods

### AuditService

```php
namespace ahgAuditTrailPlugin\Service;

class AuditService
{
    public function log(string $action, array $context): void
    public function logCreate(string $entityType, int $entityId, array $data): void
    public function logUpdate(string $entityType, int $entityId, array $old, array $new): void
    public function logDelete(string $entityType, int $entityId, array $data): void
    public function logView(string $entityType, int $entityId): void
    public function logDownload(int $digitalObjectId, int $userId): void
    public function logLogin(int $userId, bool $success): void
    public function logExport(string $format, array $criteria): void
    
    public function getAuditHistory(string $entityType, int $entityId): Collection
    public function getUserActivity(int $userId, array $filters): Collection
    public function getRecentActivity(int $limit = 100): Collection
    public function search(array $criteria): Collection
    
    public function purgeExpired(): int
    public function export(array $criteria, string $format): string
}
```

---

## Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| audit_enabled | true | Enable audit logging |
| log_views | false | Log view actions |
| log_searches | false | Log search queries |
| retention_days | 2555 | 7 years default |
| sensitive_fields | [] | Fields to mask |
| excluded_actions | [] | Actions to skip |

---

## Compliance Mapping

| Standard | Requirement | Implementation |
|----------|-------------|----------------|
| POPIA | Access logging | All view/download logged |
| NARSSA | 7-year retention | retention_until field |
| PAIA | Request tracking | Linked to access_request |
| GDPR | Right to erasure | purge methods |

---

*Part of the AtoM AHG Framework*
