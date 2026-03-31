# ahgBackupPlugin - Technical Documentation

**Version:** 1.0.0  
**Category:** Administration  
**Dependencies:** atom-framework

---

## Overview

Automated backup and restore functionality for database and uploaded files with scheduling, retention policies, and verification.

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────────┐
│           backup_history                │
├─────────────────────────────────────────┤
│ PK id INT                              │
│    backup_type ENUM                     │
│    backup_name VARCHAR                  │
│    file_path VARCHAR                    │
│    file_size BIGINT                     │
│    checksum VARCHAR                     │
│    status ENUM                          │
│    started_at TIMESTAMP                 │
│    completed_at TIMESTAMP               │
│    duration_seconds INT                 │
│    tables_included JSON                 │
│    files_included JSON                  │
│    compression VARCHAR                  │
│    encryption TINYINT                   │
│    encryption_key_id INT                │
│    verified TINYINT                     │
│    verified_at TIMESTAMP                │
│    retention_days INT                   │
│    expires_at DATE                      │
│    initiated_by INT                     │
│    notes TEXT                           │
│    error_message TEXT                   │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│          backup_schedule                │
├─────────────────────────────────────────┤
│ PK id INT                              │
│    name VARCHAR                         │
│    backup_type ENUM                     │
│    cron_expression VARCHAR              │
│    retention_days INT                   │
│    compression VARCHAR                  │
│    encryption TINYINT                   │
│    include_files TINYINT                │
│    include_tables JSON                  │
│    exclude_tables JSON                  │
│    storage_path VARCHAR                 │
│    remote_storage JSON                  │
│    notification_email VARCHAR           │
│    is_active TINYINT                    │
│    last_run_at TIMESTAMP                │
│    next_run_at TIMESTAMP                │
│    created_at TIMESTAMP                 │
│    updated_at TIMESTAMP                 │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│          backup_restore_log             │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK backup_id INT                       │
│    restore_type ENUM                    │
│    status ENUM                          │
│    started_at TIMESTAMP                 │
│    completed_at TIMESTAMP               │
│    tables_restored JSON                 │
│    files_restored JSON                  │
│    initiated_by INT                     │
│    notes TEXT                           │
│    error_message TEXT                   │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘
```

---

## Backup Types

| Type | Contents |
|------|----------|
| full | Database + files |
| database | Database only |
| files | Uploaded files only |
| incremental | Changes since last backup |
| configuration | Config files only |

---

## Service Methods

### BackupService

```php
namespace ahgBackupPlugin\Service;

class BackupService
{
    // Backup Operations
    public function createBackup(string $type, array $options): int
    public function runScheduledBackups(): array
    public function getBackup(int $id): ?array
    public function listBackups(array $filters): Collection
    public function deleteBackup(int $id): bool
    public function verifyBackup(int $id): bool
    
    // Restore Operations
    public function restore(int $backupId, array $options): bool
    public function previewRestore(int $backupId): array
    public function getRestoreLog(int $backupId): Collection
    
    // Schedules
    public function createSchedule(array $data): int
    public function updateSchedule(int $id, array $data): bool
    public function deleteSchedule(int $id): bool
    public function getSchedules(): Collection
    
    // Maintenance
    public function cleanupExpired(): int
    public function calculateStorageUsed(): int
    public function getBackupStats(): array
}
```

---

## CLI Commands

```bash
# Create backup
php bin/atom backup:create --type=full

# List backups
php bin/atom backup:list

# Restore backup
php bin/atom backup:restore --id=123

# Verify backup
php bin/atom backup:verify --id=123

# Cleanup expired
php bin/atom backup:cleanup
```

---

*Part of the AtoM AHG Framework*
