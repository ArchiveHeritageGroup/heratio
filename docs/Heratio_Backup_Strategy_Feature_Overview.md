# Heratio - Backup Strategy Feature Overview

**Product:** Heratio Framework v2.8.2
**Component:** Backup & Restore (ahgBackupPlugin)
**Date:** 16 March 2026
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## What It Does

Heratio provides a comprehensive backup and restore system for archival data, digital objects, plugins, and framework code. The system supports full, incremental, and scheduled backups with configurable retention policies and email notifications.

## Key Features

### Backup Types
- **Full Backup** - complete snapshot of all selected components (database, uploads, plugins, framework, Fuseki RiC-O triplestore)
- **Incremental Backup** - captures only files changed since the last full backup; database is always dumped fully
- **Database Only** - fast MySQL dump for daily use
- **5 Presets** - db, atom_base, content, ahg, full - for one-click backup creation

### Scheduled Backups
- **Frequency options** - hourly, daily, weekly, monthly
- **Configurable time** - set the hour and minute for each schedule
- **Day selection** - day of week (weekly) or day of month (monthly)
- **Per-schedule retention** - independent retention period per schedule
- **Component selection** - choose which components each schedule includes
- **Active/paused toggle** - enable or disable schedules without deleting them
- **CLI execution** - `php symfony backup:run-scheduled` checks and runs due schedules
- **Cron integration** - single hourly cron entry drives all schedules

### Restore Capabilities
- **Restore from list** - select any backup from the admin panel
- **Restore from upload** - upload a backup file (.tar.gz, .sql.gz, .zip) and restore
- **Selective restore** - choose which components to restore (database, uploads, plugins, framework)
- **Safety rollback** - original files backed up before restoration; rolled back on failure
- **Fuseki RiC-O triplestore** - RiC-O (Records in Contexts Ontology)/RDF data backup and restore via N-Quads export

### Email Notifications
- **Success/failure alerts** - configurable per event type
- **Server details** - includes backup ID, size, hostname, timestamp
- **Error reporting** - failure notifications include the error message

### Administration
- **Web UI** - full admin panel at Admin > Backup & Restore
- **Schedule management** - create, toggle, delete schedules from the web interface
- **Storage dashboard** - backup count, total size, retention policy
- **Database connection test** - verify MySQL connectivity from the admin panel
- **Download backups** - download individual components or full ZIP archives

## Backup Strategy Recommendations

| Strategy | Schedule | Type | Retention |
|----------|----------|------|-----------|
| **Minimal** | Daily at 02:00 | Database only | 30 days |
| **Standard** | Daily DB + Weekly full | Mixed | 90 days |
| **Production** | Daily incremental + Weekly full + Monthly archive | Mixed | 365 days |

## Technical Requirements

- **Server:** PHP 8.3, MySQL 8, tar, gzip, zip
- **Cron:** System cron for scheduled execution
- **Storage:** Sufficient disk space for retention period (default: `/var/backups/atom`)
- **Email:** PHP `mail()` function for notifications (optional)

## Who Benefits

- **System administrators** - automated backup management without manual intervention
- **Archival institutions** - compliance with data retention and disaster recovery policies
- **IT teams** - predictable backup schedules with email alerting
- **Auditors** - complete backup history with timestamps and component details

---

*For detailed usage instructions, see Admin > Help Center > Backup & Restore.*
*For technical documentation, see the ahgBackupPlugin Technical Reference.*
