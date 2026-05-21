> Heratio Help Center article. Category: Admin & Settings.

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

*Part of the Heratio AHG Framework*


---

## Scheduled Backups

Backups can be scheduled to run automatically at regular intervals.

### Creating a Schedule

1. Go to **Admin > Backup & Restore**
2. In the **Schedules** card (left sidebar), click the **+** button
3. Configure:
   - **Name** — descriptive label (e.g. "Daily DB Backup")
   - **Frequency** — Hourly, Daily, Weekly, or Monthly
   - **Time** — when to run (24-hour format)
   - **Day of Week** — for weekly schedules (Sunday–Saturday)
   - **Day of Month** — for monthly schedules (1–28)
   - **Retention** — how many days to keep old backups
   - **Components** — which parts to include (Database, Uploads, Plugins, Framework)
4. Click **Create Schedule**

### Managing Schedules

- **Toggle active/paused** — click the green check / grey pause button
- **Delete** — click the red trash button (with confirmation)
- Schedules show last run time and next run time

### Cron Setup (Required)

Scheduled backups require a cron job to check and execute due schedules:



This runs every hour and checks if any schedules are due.

### CLI Options



---

## Incremental Backups

An incremental backup saves only files that changed since the last full backup. The database dump is always complete.

### How It Works

1. System finds the most recent completed full backup
2. Database is dumped fully (always)
3. File components (uploads, plugins, framework) use tar --newer to capture only modified files
4. If no full backup exists, falls back to a full backup automatically

### Creating an Incremental Backup

1. Go to **Admin > Backup & Restore**
2. Click **Incremental Backup** in the Quick Actions card
3. Confirm the operation

Incremental backups are smaller and faster than full backups, making them ideal for daily runs between weekly full backups.

---

## Email Notifications

Backup notifications can be sent on success and/or failure.

### Configuration

1. Go to **Admin > Backup & Restore > Settings**
2. Set **Notification Email** to the recipient address
3. Enable **Notify on Success** and/or **Notify on Failure**
4. Save settings

Notifications include: backup name, ID, size, server hostname, and timestamp. Failure notifications include the error message.

---

## Backup Strategy Recommendations

| Strategy | Schedule | Type | Retention |
|----------|----------|------|-----------|
| **Minimal** | Daily at 02:00 | Database only | 30 days |
| **Standard** | Daily DB + Weekly full | Full weekly, DB daily | 90 days |
| **Production** | Daily incremental + Weekly full + Monthly archive | Mixed | 365 days |

### Example Production Setup

Create 3 schedules:
1. **Daily DB** — frequency: daily, time: 02:00, components: DB only, retention: 30 days
2. **Weekly Full** — frequency: weekly, day: Sunday, time: 03:00, all components, retention: 90 days
3. **Monthly Archive** — frequency: monthly, day: 1, time: 04:00, all components, retention: 365 days


---

## Scheduled Backups

Backups can be scheduled to run automatically at regular intervals.

### Creating a Schedule

1. Go to **Admin > Backup & Restore**
2. In the **Schedules** card (left sidebar), click the **+** button
3. Configure:
   - **Name** — descriptive label (e.g. "Daily DB Backup")
   - **Frequency** — Hourly, Daily, Weekly, or Monthly
   - **Time** — when to run (24-hour format)
   - **Day of Week** — for weekly schedules (Sunday-Saturday)
   - **Day of Month** — for monthly schedules (1-28)
   - **Retention** — how many days to keep old backups
   - **Components** — which parts to include (Database, Uploads, Plugins, Framework)
4. Click **Create Schedule**

### Managing Schedules

- **Toggle active/paused** — click the green check / grey pause button
- **Delete** — click the red trash button (with confirmation)

### Cron Setup (Required)

Scheduled backups require a cron job:



### CLI Options

-  — Run all due schedules
-  — Show what would run
-  — Run all active schedules now

---

## Incremental Backups

An incremental backup saves only files changed since the last full backup. The database dump is always complete.

1. Go to **Admin > Backup & Restore**
2. Click **Incremental Backup** in Quick Actions
3. Confirm the operation

If no full backup exists, it falls back to a full backup automatically.

---

## Email Notifications

1. Go to **Admin > Backup & Restore > Settings**
2. Set **Notification Email**
3. Enable **Notify on Success** and/or **Notify on Failure**
4. Save

---

## Backup Strategy Recommendations

| Strategy | Schedule | Type | Retention |
|----------|----------|------|-----------|
| Minimal | Daily 02:00 | DB only | 30 days |
| Standard | Daily DB + Weekly full | Mixed | 90 days |
| Production | Daily incremental + Weekly full + Monthly archive | Mixed | 365 days |
