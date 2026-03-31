# Backup & Restore

## User Guide

Create backups of your archive database and restore from previous backups when needed.

---

## Overview
```
┌─────────────────────────────────────────────────────────────┐
│                    BACKUP & RESTORE                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   BACKUP                          RESTORE                   │
│      │                               │                      │
│      ▼                               ▼                      │
│  Save current    ◄─────────────►  Return to                │
│  database state                   previous state            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## When to Use
```
┌─────────────────────────────────────────────────────────────┐
│                    CREATE BACKUPS BEFORE:                   │
├─────────────────────────────────────────────────────────────┤
│  📥 Large imports           - Bulk data loading             │
│  🔄 System updates          - Software upgrades             │
│  🗑️  Bulk deletions          - Removing many records         │
│  ⚙️  Configuration changes   - System settings              │
│  📋 Major edits             - Large-scale modifications     │
└─────────────────────────────────────────────────────────────┘
```

---

## How to Access
```
  Main Menu
      │
      ▼
   Admin
      │
      ▼
   AHG Settings
      │
      ▼
   Backup & Restore ────────────────────────────────┐
      │                                              │
      ├──▶ Create Backup      (save current state)   │
      │                                              │
      ├──▶ View Backups       (list all backups)     │
      │                                              │
      ├──▶ Restore            (return to backup)     │
      │                                              │
      └──▶ Download           (save to computer)     │
```

---

## Creating a Backup

### Step 1: Open Backup Tool

Go to **Admin** → **AHG Settings** → **Backup & Restore**

### Step 2: Click Create Backup
```
┌─────────────────────────────────────────────────────────────┐
│  CREATE NEW BACKUP                                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Description:  [Pre-import backup January 2026    ]         │
│                                                             │
│  Include:      [✓] Database                                 │
│                [✓] Uploads folder                           │
│                [ ] Search index                             │
│                                                             │
│                    [Create Backup]                          │
└─────────────────────────────────────────────────────────────┘
```

### Step 3: Wait for Completion
```
  Creating backup...
      │
      ├── Exporting database tables...     ✓
      ├── Compressing uploads...           ✓
      └── Generating checksum...           ✓
      
  ✅ Backup complete: backup_2026-01-10_143215.sql.gz
```

---

## Viewing Existing Backups

Your backups are listed with details:
```
┌──────────────────────────────────────────────────────────────┐
│  AVAILABLE BACKUPS                                           │
├──────────────────────────────────────────────────────────────┤
│  Date            │ Description              │ Size   │ Actions│
├──────────────────┼──────────────────────────┼────────┼────────┤
│  10 Jan 2026     │ Pre-import backup        │ 245 MB │ ⬇️ 🔄 🗑️│
│  05 Jan 2026     │ Weekly backup            │ 240 MB │ ⬇️ 🔄 🗑️│
│  01 Jan 2026     │ Monthly backup           │ 235 MB │ ⬇️ 🔄 🗑️│
│  25 Dec 2025     │ Before update            │ 230 MB │ ⬇️ 🔄 🗑️│
└──────────────────┴──────────────────────────┴────────┴────────┘

  ⬇️ Download    🔄 Restore    🗑️ Delete
```

---

## Restoring from Backup

### ⚠️ Warning
```
┌─────────────────────────────────────────────────────────────┐
│  ⚠️  RESTORE WILL REPLACE ALL CURRENT DATA                  │
│                                                             │
│  Any changes made after the backup date will be LOST.       │
│  Consider creating a backup of current state first.         │
└─────────────────────────────────────────────────────────────┘
```

### Steps to Restore

1. Select the backup you want to restore
2. Click the **Restore** button (🔄)
3. Confirm you want to proceed
4. Wait for restoration to complete
```
  Restoring backup...
      │
      ├── Stopping services...             ✓
      ├── Importing database...            ✓
      ├── Restoring uploads...             ✓
      ├── Rebuilding search index...       ✓
      └── Restarting services...           ✓
      
  ✅ Restore complete
```

---

## Downloading Backups

To save a backup to your computer:

1. Find the backup in the list
2. Click the **Download** button (⬇️)
3. Save the file to a secure location

> Store downloaded backups in a separate location for disaster recovery

---

## Best Practices
```
┌────────────────────────────────┬────────────────────────────┐
│  ✓ DO                          │  ✗ DON'T                   │
├────────────────────────────────┼────────────────────────────┤
│  Backup before major changes   │  Skip backups              │
│  Use descriptive names         │  Use generic names         │
│  Download copies offsite       │  Keep all backups on server│
│  Test restores periodically    │  Assume backups work       │
│  Delete old backups            │  Keep unlimited backups    │
│  Schedule regular backups      │  Only backup manually      │
└────────────────────────────────┴────────────────────────────┘
```

---

## Troubleshooting
```
Problem                          Solution
───────────────────────────────────────────────────────────
Backup takes too long         →  Large databases are normal
                                 Run during quiet periods
                                 
Backup fails                  →  Check disk space
                                 Contact administrator
                                 
Restore seems stuck           →  Large restores take time
                                 Do not interrupt
                                 
Missing recent data           →  You restored an old backup
                                 Data after backup is gone
```

---

## Need Help?

Contact your system administrator if you experience issues.

---

*Part of the AtoM AHG Framework*
