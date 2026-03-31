# Digital Preservation User Guide

## Overview

The Digital Preservation plugin helps you protect your digital collections by ensuring files remain unchanged over time, tracking all preservation activities, identifying file formats that may require attention, scanning for viruses, converting files to preservation-safe formats, and replicating backups to multiple locations.

**Key Benefits:**
- **Integrity Assurance** - Detect if files have been corrupted or modified
- **Audit Trail** - Complete history of all preservation activities
- **Format Awareness** - Know which files may need migration to safer formats
- **Virus Protection** - Scan files for malware using ClamAV
- **Format Conversion** - Convert files to archival-quality formats
- **Backup Replication** - Replicate files to multiple backup targets
- **Compliance** - Meet OAIS and PREMIS preservation standards

---

## Getting Started

### Accessing the Preservation Dashboard

1. Log in as an administrator
2. Navigate to **Central Dashboard** -> **Digital Preservation**
3. Or go directly to: `/preservation`

```
+-------------------------------------------------------------+
|                   CENTRAL DASHBOARD                          |
+-------------------------------------------------------------+
|  +------------+  +------------+  +------------+              |
|  |  Digital   |  |   Format   |  | Checksums  |              |
|  |Preservation|  |  Registry  |  | & Integrity|              |
|  +------------+  +------------+  +------------+              |
|  +------------+  +------------+  +------------+              |
|  |   Virus    |  |   Format   |  |   Backup   |              |
|  |  Scanning  |  | Conversion |  |Verification|              |
|  +------------+  +------------+  +------------+              |
+-------------------------------------------------------------+
```

### Accessing Preservation Settings

1. Go to **Admin** -> **AHG Settings**
2. Click **Preservation & Backup** card
3. Or navigate to: `/ahgSettings/preservation`

---

## Understanding the Dashboard

### Statistics Overview

The dashboard displays key metrics at a glance:

| Metric | Description |
|--------|-------------|
| **Total Digital Objects** | Number of files in your repository |
| **Storage Used** | Total size of all digital objects |
| **Checksum Coverage** | Percentage of files with checksums |
| **Pending Verification** | Files due for fixity check |
| **Failed Checks (30d)** | Verification failures in past month |
| **At-Risk Formats** | Files in formats needing attention |
| **Virus Scans (30d)** | Files scanned for viruses |
| **Conversions (30d)** | Format conversions performed |
| **Backups Verified (30d)** | Backup verifications completed |

---

## Core Features

### 1. Checksums

Checksums are digital fingerprints that uniquely identify file contents. If a file changes (even one bit), the checksum changes.

#### How Checksums Work

```
+-------------------------------------------------------------+
|                    CHECKSUM WORKFLOW                         |
+-------------------------------------------------------------+
|                                                              |
|    +----------+      +------------+      +-------------+     |
|    |  Digital | ---> |  Generate  | ---> |   Store     |     |
|    |  Object  |      |  Checksum  |      |  Checksum   |     |
|    +----------+      +------------+      +-------------+     |
|         |                                       |            |
|         |              LATER                    |            |
|         v                                       v            |
|    +----------+      +------------+      +-------------+     |
|    |  Same    | ---> | Recalculate| ---> |   Compare   |     |
|    |  File    |      |  Checksum  |      |  Checksums  |     |
|    +----------+      +------------+      +-------------+     |
|                                                 |            |
|                              +------------------+---+        |
|                              v                      v        |
|                        +---------+           +---------+     |
|                        |  MATCH  |           |  DIFFER |     |
|                        |    OK   |           |  ALERT! |     |
|                        +---------+           +---------+     |
|                                                              |
+-------------------------------------------------------------+
```

#### Supported Algorithms

| Algorithm | Security | Speed | Recommended Use |
|-----------|----------|-------|-----------------|
| **SHA-256** | High | Good | Default - best balance |
| **SHA-512** | Very High | Moderate | High-security archives |
| **SHA-1** | Medium | Fast | Legacy compatibility |
| **MD5** | Low | Very Fast | Quick checks only |

#### Generating Checksums

**Automatic:** Checksums are generated when files are uploaded (if enabled in settings).

**Manual:**
1. Go to a digital object's page
2. Click **Preservation** tab
3. Click **Generate Checksums**

---

### 2. Fixity Verification

Fixity checks verify that files haven't changed since checksums were created.

#### Running Fixity Checks

**Single Object:**
1. Navigate to the digital object
2. Click **Preservation** -> **Verify Fixity**

**Batch Verification (CLI):**
```bash
# Check objects not verified in 30+ days
php symfony preservation:fixity --limit=100

# Check objects not verified in 7+ days
php symfony preservation:fixity --stale-days=7

# Check all objects regardless of age
php symfony preservation:fixity --all --limit=500

# Check with self-healing auto-repair enabled
php symfony preservation:fixity --auto-repair --limit=100
```

**Self-Healing Auto-Repair:**

The plugin includes self-healing storage capabilities that automatically restore corrupted or missing files from backups:

```bash
# Run fixity check with auto-repair
php symfony preservation:fixity --auto-repair

# View self-healing statistics
php symfony preservation:fixity --repair-stats
```

When `--auto-repair` is enabled:
1. Failed fixity checks trigger automatic backup search
2. The system searches all configured replication targets
3. Valid backup copies are verified against stored checksums
4. Corrupted files are restored from verified backups
5. All repair operations are logged as PREMIS events

**Supported Backup Targets for Self-Healing:**
- Local file system paths
- Rsync targets
- SFTP servers
- Amazon S3 buckets
- Azure Blob Storage
- Google Cloud Storage

**Scheduled Verification with Auto-Repair:**
Add to crontab for automatic daily checks with self-healing:
```bash
0 2 * * * cd /usr/share/nginx/archive && php symfony preservation:fixity --auto-repair --limit=500 >> /var/log/fixity.log 2>&1
```

---

### 3. Virus Scanning

The plugin integrates with ClamAV to scan digital objects for malware.

#### Prerequisites

Install ClamAV on your server:
```bash
sudo apt install clamav clamav-daemon
sudo freshclam  # Update virus definitions
```

#### How Virus Scanning Works

```
+-------------------------------------------------------------+
|                    VIRUS SCAN WORKFLOW                       |
+-------------------------------------------------------------+
|                                                              |
|    +----------+      +------------+      +-------------+     |
|    |  Digital | ---> |   ClamAV   | ---> |   Result    |     |
|    |  Object  |      |    Scan    |      |   Status    |     |
|    +----------+      +------------+      +-------------+     |
|                                                 |            |
|                      +----------+---------------+            |
|                      |          |               |            |
|                      v          v               v            |
|                 +-------+  +--------+     +-----------+      |
|                 | CLEAN |  |INFECTED|     |   ERROR   |      |
|                 +-------+  +--------+     +-----------+      |
|                               |                              |
|                               v                              |
|                         +------------+                       |
|                         | Quarantine |                       |
|                         |    File    |                       |
|                         +------------+                       |
|                                                              |
+-------------------------------------------------------------+
```

#### Running Virus Scans

**Via Web UI:**
1. Navigate to **Preservation** -> **Virus Scanning**
2. View scan statistics and recent scan results
3. Run scans using the CLI commands shown

**Via CLI:**
```bash
# Check ClamAV status and statistics
php symfony preservation:virus-scan --status

# Preview what would be scanned
php symfony preservation:virus-scan --dry-run

# Scan new/unscanned objects
php symfony preservation:virus-scan --limit=100

# Scan specific object
php symfony preservation:virus-scan --object-id=123

# Scan all objects (rescan previously scanned)
php symfony preservation:virus-scan --all --limit=500
```

#### Quarantine

Infected files are automatically moved to a quarantine directory:
- **Location:** `/uploads/quarantine/`
- Files are renamed with a timestamp and `.quarantine` extension
- Review quarantined files before permanent deletion

---

### 4. Format Conversion

Convert files to archival-quality preservation formats using industry-standard tools.

#### Supported Conversions

| Source Format | Target Format | Tool Used |
|---------------|---------------|-----------|
| JPEG, PNG, BMP, GIF | TIFF | ImageMagick |
| MP3, AAC, OGG | WAV | FFmpeg |
| Various video formats | MKV/FFV1 | FFmpeg |
| DOC, XLS, PPT | PDF | LibreOffice |
| DOCX, XLSX, PPTX | PDF | LibreOffice |
| PDF | PDF/A | Ghostscript |

#### How Format Conversion Works

```
+-------------------------------------------------------------+
|                  FORMAT CONVERSION FLOW                      |
+-------------------------------------------------------------+
|                                                              |
|  +----------+    +----------+    +----------+                |
|  | Identify | -> | Select   | -> | Convert  |                |
|  | Source   |    |  Tool    |    |  File    |                |
|  | Format   |    |          |    |          |                |
|  +----------+    +----------+    +----------+                |
|                                        |                     |
|                                        v                     |
|  +----------+    +----------+    +----------+                |
|  |  Update  | <- |  Verify  | <- |  Store   |                |
|  | Metadata |    |  Output  |    | Converted|                |
|  +----------+    +----------+    +----------+                |
|       |                                                      |
|       v                                                      |
|  +----------+    +----------+                                |
|  | Generate | -> |   Log    |                                |
|  | Checksum |    |  Event   |                                |
|  +----------+    +----------+                                |
|                                                              |
+-------------------------------------------------------------+
```

#### Running Format Conversions

**Via Web UI:**
1. Navigate to **Preservation** -> **Format Conversion**
2. View conversion tools status and statistics
3. See recent conversions and any failures

**Via CLI:**
```bash
# Check available tools and statistics
php symfony preservation:convert --status

# Preview what would be converted
php symfony preservation:convert --dry-run

# Convert objects needing conversion
php symfony preservation:convert --limit=50

# Convert specific object
php symfony preservation:convert --object-id=123 --format=tiff

# Convert by MIME type
php symfony preservation:convert --mime-type=image/jpeg --format=tiff --limit=100

# Adjust quality (for applicable conversions)
php symfony preservation:convert --quality=90 --limit=50
```

#### Output Location

Converted files are stored in: `/uploads/conversions/`

Files are named: `{original_name}_{object_id}.{format}`

---

### 5. Backup Verification

Verify the integrity of backup files and archives.

#### How Backup Verification Works

```
+-------------------------------------------------------------+
|                BACKUP VERIFICATION FLOW                      |
+-------------------------------------------------------------+
|                                                              |
|    +----------+      +------------+      +-------------+     |
|    |  Backup  | ---> |  Verify    | ---> |   Status    |     |
|    |   File   |      | Integrity  |      |   Report    |     |
|    +----------+      +------------+      +-------------+     |
|                            |                    |            |
|         +------------------+--------------------+            |
|         |                  |                    |            |
|         v                  v                    v            |
|    +----------+      +----------+       +------------+       |
|    | Checksum |      | Archive  |       |   Sample   |       |
|    |  Verify  |      | Integrity|       |   Files    |       |
|    +----------+      +----------+       +------------+       |
|         |                  |                    |            |
|         v                  v                    v            |
|    +---------+       +---------+        +---------+          |
|    |  VALID  |       |CORRUPTED|        | WARNING |          |
|    +---------+       +---------+        +---------+          |
|                                                              |
+-------------------------------------------------------------+
```

#### Running Backup Verification

**Via Web UI:**
1. Navigate to **Preservation** -> **Backup Verification**
2. View verification statistics
3. See recent verification results

**Via CLI:**
```bash
# Verify backups in default directory
php symfony preservation:verify-backup

# Verify specific backup file
php symfony preservation:verify-backup --path=/backups/atom-backup-2026-01-15.tar.gz

# Verify with known checksum
php symfony preservation:verify-backup --path=/backups/backup.tar.gz --checksum=abc123...

# Verify all backups in directory
php symfony preservation:verify-backup --all --backup-dir=/backups
```

---

### 6. Backup Replication

Replicate files to multiple backup targets for disaster recovery.

#### Supported Target Types

| Type | Description | Use Case |
|------|-------------|----------|
| **Local** | Local filesystem path | Secondary disk, NAS mount |
| **SFTP** | Secure FTP transfer | Remote server |
| **Rsync** | Efficient sync protocol | Linux servers |
| **S3** | Amazon S3 or compatible | Cloud storage |

#### Managing Replication Targets

**Via Web UI:**
1. Go to **Admin** -> **AHG Settings** -> **Preservation & Backup**
2. Add, edit, or delete replication targets
3. Enable/disable targets as needed
4. View replication logs

#### Running Replication

**Via CLI:**
```bash
# Show replication status
php symfony preservation:replicate --status

# Preview what would be replicated
php symfony preservation:replicate --dry-run

# Replicate to all active targets
php symfony preservation:replicate --limit=100

# Replicate to specific target
php symfony preservation:replicate --target-id=1 --limit=100

# Force re-sync of already synced files
php symfony preservation:replicate --force --limit=50
```

#### Replication Workflow

```
+-------------------------------------------------------------+
|                   REPLICATION WORKFLOW                       |
+-------------------------------------------------------------+
|                                                              |
|    +----------+      +------------+      +-------------+     |
|    |  Source  | ---> |  Generate  | ---> |  Transfer   |     |
|    |   File   |      |  Checksum  |      |  to Target  |     |
|    +----------+      +------------+      +-------------+     |
|                                                 |            |
|                                                 v            |
|                                          +------------+      |
|                                          |   Verify   |      |
|                                          | at Target  |      |
|                                          +------------+      |
|                                                 |            |
|                              +------------------+            |
|                              |                  |            |
|                              v                  v            |
|                        +---------+        +---------+        |
|                        | SUCCESS |        |  FAILED |        |
|                        |   Log   |        |  Retry  |        |
|                        +---------+        +---------+        |
|                                                              |
+-------------------------------------------------------------+
```

---

### 7. PREMIS Events

Every preservation action is logged as a PREMIS event, creating a complete audit trail.

#### Event Types

| Event | Description |
|-------|-------------|
| **Ingestion** | File was added to the system |
| **Fixity Check** | Checksum was verified |
| **Virus Check** | File was scanned for malware |
| **Format Identification** | File format was determined |
| **Normalization** | File was converted to preservation format |
| **Replication** | File was copied to backup target |
| **Validation** | File was validated |
| **Migration** | File was migrated to new format |

#### Viewing Events

1. Go to **Preservation** -> **PREMIS Events**
2. Filter by:
   - Event type
   - Date range
   - Outcome (success/failure)
   - Object

---

### 8. Format Identification (PRONOM)

The plugin integrates with Siegfried to provide PRONOM-based format identification, the same identification method used by The National Archives (UK) and DROID.

#### What is PRONOM?

PRONOM is a technical registry maintained by The National Archives that contains information about file formats. Each format has a unique identifier called a **PUID** (PRONOM Unique Identifier), such as:

- `fmt/43` - JPEG File Interchange Format 1.02
- `fmt/353` - Tagged Image File Format (TIFF)
- `fmt/276` - PDF 1.7
- `fmt/96` - Hypertext Markup Language 4.0

#### Prerequisites

Install Siegfried on your server:
```bash
curl -sL "https://github.com/richardlehane/siegfried/releases/download/v1.11.1/siegfried_1.11.1-1_amd64.deb" -o /tmp/sf.deb
sudo dpkg -i /tmp/sf.deb
```

#### How Format Identification Works

```
+-------------------------------------------------------------+
|               FORMAT IDENTIFICATION WORKFLOW                 |
+-------------------------------------------------------------+
|                                                              |
|    +----------+      +------------+      +-------------+     |
|    |  Digital | ---> | Siegfried  | ---> |   PRONOM    |     |
|    |  Object  |      |   Analyze  |      |   Lookup    |     |
|    +----------+      +------------+      +-------------+     |
|                                                 |            |
|                      +----------+---------------+            |
|                      |          |               |            |
|                      v          v               v            |
|                 +--------+ +--------+    +-----------+       |
|                 |Certain | | High/  |    | Unknown/  |       |
|                 | Match  | | Medium |    |   Low     |       |
|                 +--------+ +--------+    +-----------+       |
|                      |          |               |            |
|                      +----------+---------------+            |
|                                 |                            |
|                                 v                            |
|                        +----------------+                    |
|                        | Store PUID,    |                    |
|                        | Format Name,   |                    |
|                        | MIME Type,     |                    |
|                        | Confidence     |                    |
|                        +----------------+                    |
|                                                              |
+-------------------------------------------------------------+
```

#### Confidence Levels

| Level | Description | Identification Basis |
|-------|-------------|---------------------|
| **Certain** | Positive match | Byte signature match |
| **High** | Very likely match | Container analysis, byte match |
| **Medium** | Probable match | Extension match with signature |
| **Low** | Tentative match | Extension only, possible mismatch |

#### Running Format Identification

**Via Web UI:**
1. Navigate to **Preservation** -> **Format Identification**
2. View Siegfried status and statistics
3. See identification coverage and confidence distribution
4. Review top identified formats and recent identifications

**Via CLI:**
```bash
# Check Siegfried status and statistics
php symfony preservation:identify --status

# Preview what would be identified
php symfony preservation:identify --dry-run

# Identify unidentified objects
php symfony preservation:identify --limit=500

# Identify specific object
php symfony preservation:identify --object-id=123

# Re-identify all objects (update existing)
php symfony preservation:identify --all --limit=1000

# Force re-identify specific object
php symfony preservation:identify --object-id=123 --reidentify
```

#### Benefits of PRONOM Identification

- **Standardized** - Uses international format registry
- **Accurate** - Signature-based detection, not just extensions
- **Risk Assessment** - Automatically assigns preservation risk levels
- **Interoperability** - Compatible with DROID, Archivematica, Preservica

#### PRONOM Registry Sync

The plugin can sync format information directly from the UK National Archives' PRONOM registry, keeping your format data current with the latest official information.

**Via CLI:**
```bash
# View PRONOM sync status
php symfony preservation:pronom-sync --status

# Sync a specific PUID
php symfony preservation:pronom-sync --puid=fmt/18

# Look up PUID information without syncing
php symfony preservation:pronom-sync --lookup=fmt/43

# Sync all unregistered PUIDs found in your collection
php symfony preservation:pronom-sync --unregistered

# Sync common archival formats (PDF/A, TIFF, JPEG2000, WAV, etc.)
php symfony preservation:pronom-sync --common

# Sync all known PUIDs
php symfony preservation:pronom-sync --all
```

**PRONOM Data Retrieved:**
- Official format names and versions
- MIME types and file extensions
- Binary signature availability
- Format risk information
- Preservation recommendations

**Scheduled Sync (recommended monthly):**
```bash
0 4 1 * * cd /usr/share/nginx/archive && php symfony preservation:pronom-sync --all >> /var/log/pronom-sync.log 2>&1
```

---

### 9. Workflow Scheduler

The Workflow Scheduler allows you to configure and monitor automated preservation tasks through a visual interface, without requiring direct server access.

#### Accessing the Scheduler

1. Navigate to **Preservation** -> **Scheduler**
2. Or go directly to: `/preservation/scheduler`

#### Default Schedules

| Schedule | Type | Default Time | Status |
|----------|------|--------------|--------|
| Daily Format Identification | format_identification | 1:00 AM | Enabled |
| Daily Fixity Check | fixity_check | 2:00 AM | Enabled |
| Daily Virus Scan | virus_scan | 3:00 AM | Enabled |
| Weekly Format Conversion | format_conversion | Sunday 4:00 AM | Disabled |
| Weekly Backup Verification | backup_verification | Saturday 6:00 AM | Enabled |
| Daily Replication | replication | 5:00 AM | Disabled |

#### Creating/Editing Schedules

1. Click **New Schedule** or **Edit** on an existing schedule
2. Configure:
   - **Name** - Descriptive name for the schedule
   - **Workflow Type** - What task to run
   - **Cron Expression** - When to run (e.g., `0 2 * * *` for daily at 2 AM)
   - **Batch Limit** - Maximum objects to process per run
   - **Timeout** - Maximum runtime before abort
   - **Notifications** - Email alerts on failure

#### Running Workflows Manually

You can manually trigger any workflow from the UI:
1. Go to **Preservation** -> **Scheduler**
2. Click the **Play** button next to any schedule
3. View results in the **Recent Runs** table

#### CLI Scheduler Command

The scheduler should be run via cron to execute due workflows:

```bash
# Run every minute (recommended)
* * * * * cd /usr/share/nginx/archive && php symfony preservation:scheduler >> /var/log/atom/scheduler.log 2>&1

# Or run specific schedule
php symfony preservation:scheduler --run-id=1

# Show scheduler status
php symfony preservation:scheduler --status

# List all schedules
php symfony preservation:scheduler --list
```

---

### 10. Format Registry

The format registry tracks file formats and their preservation risk level. It is automatically populated during format identification with PRONOM PUIDs.

#### Risk Levels

| Level | Formats | Action |
|-------|---------|--------|
| **Low Risk** | TIFF, PNG, PDF/A, WAV, FLAC, TXT, XML | No action needed |
| **Medium Risk** | JPEG, MP3, MP4, PDF, DOCX, XLSX | Monitor for obsolescence |
| **High Risk** | Proprietary formats, legacy formats | Consider migration |
| **Critical Risk** | Obsolete formats, encrypted content | Migrate urgently |

#### Viewing Format Information

1. Go to **Preservation** -> **Format Registry**
2. View all known formats with PUIDs
3. Filter by risk level
4. See objects using each format
5. Click PUID to view details on PRONOM website

---

## CLI Command Reference

### All Preservation Commands

| Command | Description |
|---------|-------------|
| `preservation:scheduler` | Run scheduled workflows or view scheduler status |
| `preservation:identify` | Identify file formats using Siegfried (PRONOM) |
| `preservation:fixity` | Run fixity verification checks |
| `preservation:virus-scan` | Scan files for viruses with ClamAV |
| `preservation:convert` | Convert files to preservation formats |
| `preservation:verify-backup` | Verify backup file integrity |
| `preservation:replicate` | Replicate files to backup targets |

### Common Options

| Option | Description |
|--------|-------------|
| `--status` | Show tool status and statistics |
| `--dry-run` | Preview without making changes |
| `--limit=N` | Maximum objects to process |
| `--object-id=N` | Process specific object |

### Cron Schedule Examples

**Recommended: Use the Workflow Scheduler** (runs all configured workflows automatically):

```bash
# Run scheduler every minute (recommended)
* * * * * cd /usr/share/nginx/archive && php symfony preservation:scheduler >> /var/log/atom/scheduler.log 2>&1
```

**Alternative: Individual task scheduling** (manual control):

```bash
# Daily format identification at 1am
0 1 * * * cd /usr/share/nginx/archive && php symfony preservation:identify --limit=500

# Daily fixity check at 2am
0 2 * * * cd /usr/share/nginx/archive && php symfony preservation:fixity --limit=500

# Daily virus scan at 3am
0 3 * * * cd /usr/share/nginx/archive && php symfony preservation:virus-scan --limit=200

# Weekly format conversion on Sunday at 4am
0 4 * * 0 cd /usr/share/nginx/archive && php symfony preservation:convert --limit=100

# Daily replication at 5am
0 5 * * * cd /usr/share/nginx/archive && php symfony preservation:replicate --limit=500

# Weekly backup verification on Saturday at 6am
0 6 * * 6 cd /usr/share/nginx/archive && php symfony preservation:verify-backup --all
```

---

### 10. OAIS Packages (SIP/AIP/DIP)

The OAIS Packages feature allows you to create, manage, and export archival packages following the OAIS (Open Archival Information System) standard. Packages are created in BagIt format for maximum interoperability.

#### Package Types

```
+-------------------------------------------------------------+
|                    OAIS INFORMATION FLOW                     |
+-------------------------------------------------------------+
|                                                              |
|    PRODUCER              ARCHIVE              CONSUMER       |
|        |                    |                    ^           |
|        v                    v                    |           |
|    +-------+           +-------+            +-------+        |
|    |  SIP  | -------> |  AIP  | --------> |  DIP  |        |
|    +-------+           +-------+            +-------+        |
|                                                              |
|    Submission          Archival            Dissemination    |
|    Information         Information         Information      |
|    Package             Package             Package          |
+-------------------------------------------------------------+
```

| Type | Purpose | Description |
|------|---------|-------------|
| **SIP** | Submission | Package for ingesting content into the archive |
| **AIP** | Archival | Package for long-term storage and preservation |
| **DIP** | Dissemination | Package for providing access to users |

#### Creating a Package via Web UI

1. Navigate to **Digital Preservation** -> **OAIS Packages**
2. Click **Create Package**
3. Fill in the package details:
   - **Name** - Descriptive name (e.g., "Annual Reports 2024 SIP")
   - **Description** - Brief explanation of contents
   - **Package Type** - SIP, AIP, or DIP
   - **Originator** - Creating organization
4. Click **Create Package**
5. Add digital objects to the package
6. **Build** the BagIt package
7. **Validate** the package checksums
8. **Export** to ZIP, TAR, or TAR.GZ

#### Package Workflow

```
+-------------------------------------------------------------+
|                    PACKAGE WORKFLOW                          |
+-------------------------------------------------------------+
|                                                              |
|    +-------+     +-------+     +----------+     +--------+   |
|    | DRAFT | --> | BUILD | --> | VALIDATE | --> | EXPORT |   |
|    +-------+     +-------+     +----------+     +--------+   |
|        |             |              |               |        |
|        v             v              v               v        |
|    Add/Remove    Create       Verify all      Create ZIP    |
|    Objects       BagIt        checksums       or TAR file   |
|                  Structure                                   |
+-------------------------------------------------------------+
```

#### CLI Commands

```bash
# List all packages
php symfony preservation:package list

# Create a new SIP
php symfony preservation:package create --type=sip --name="My Collection"

# Add objects to package
php symfony preservation:package add-objects --id=1 --objects=100,101,102

# Add objects by query
php symfony preservation:package add-objects --id=1 --query="mime_type:application/pdf"

# Build BagIt package
php symfony preservation:package build --id=1

# Validate package
php symfony preservation:package validate --id=1

# Export to ZIP
php symfony preservation:package export --id=1 --format=zip

# Convert SIP to AIP
php symfony preservation:package convert --id=1 --type=aip
```

#### BagIt Structure

Each package follows the BagIt specification:

```
<uuid>/
  bagit.txt           # BagIt declaration
  bag-info.txt        # Package metadata
  manifest-sha256.txt # Payload checksums
  tagmanifest-sha256.txt # Tag file checksums
  data/
    file1.pdf         # Payload files
    file2.tiff
    ...
```

#### Package Conversions

| From | To | Description |
|------|----|-------------|
| SIP | AIP | Convert submission package to archival package |
| AIP | DIP | Create access package from archival package |

**Note:** SIP must be validated before converting to AIP. AIP must be validated before creating DIP.

---

## Best Practices

### Recommended Settings

1. **Algorithm:** Use SHA-256 for all new checksums
2. **Coverage:** Aim for 100% checksum coverage
3. **Verification Frequency:** Verify all files at least monthly
4. **Virus Scanning:** Scan all new uploads immediately
5. **Format Conversion:** Convert at-risk formats proactively
6. **Replication:** Maintain at least 2 backup copies in different locations

### Preservation Strategy

```
+-------------------------------------------------------------+
|              PRESERVATION STRATEGY PYRAMID                   |
+-------------------------------------------------------------+
|                                                              |
|                        +-------+                             |
|                       /|MONITOR|\                            |
|                      / | Risk  | \                           |
|                     /  +-------+  \                          |
|                    /               \                         |
|                   /    +-------+    \                        |
|                  /    /|CONVERT|\    \                       |
|                 /    / |Formats| \    \                      |
|                /    /  +-------+  \    \                     |
|               /    /               \    \                    |
|              /    /     +------+    \    \                   |
|             /    /     /| SCAN |\    \    \                  |
|            /    /     / |Virus | \    \    \                 |
|           /    /     /  +------+  \    \    \                |
|          /    /     /              \    \    \               |
|         /    /     /    +------+    \    \    \              |
|        /    /     /    /|VERIFY|\    \    \    \             |
|       /    /     /    / |Fixity| \    \    \    \            |
|      /    /     /    /  +------+  \    \    \    \           |
|     /    /     /    /              \    \    \    \          |
|    /    /     /    /   +--------+   \    \    \    \         |
|   /    /     /    /   /|REPLICATE\   \    \    \    \        |
|  /    /     /    /   / | Backups | \  \    \    \    \       |
| +----+-----+----+---+--+--------+--+--+----+-----+----+      |
|                                                              |
+-------------------------------------------------------------+
```

---

## Format Migration

Plan and execute format conversions to ensure long-term accessibility.

### Understanding Format Risk

```
┌─────────────────────────────────────────────────────────────┐
│  FORMAT RISK LEVELS                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🔴 Critical   - Format obsolete, software unavailable      │
│  🟠 High       - Format deprecated, limited support         │
│  🟡 Medium     - Proprietary, future uncertain              │
│  🟢 Low        - Open standard, well-supported              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Obsolescence Report

View formats at risk in your repository:

```
┌─────────────────────────────────────────────────────────────┐
│  OBSOLESCENCE REPORT                                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Format              Risk     Count    Recommended Action   │
│  ─────────────────────────────────────────────────────────  │
│  WordPerfect 5.1     🔴       45       → PDF/A-3            │
│  Lotus 1-2-3         🔴       23       → ODS / XLSX         │
│  TIFF LZW            🟡       1,234    → TIFF uncompressed  │
│  JPEG 2000           🟡       567      → Monitor            │
│  PDF/A-3             🟢       8,901    → No action          │
│                                                             │
│  [Generate Migration Plan]                                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Migration Pathways

Pre-defined conversion routes:

```
┌─────────────────────────────────────────────────────────────┐
│  MIGRATION PATHWAYS                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Source            Target            Quality    Automated   │
│  ─────────────────────────────────────────────────────────  │
│  WordPerfect   →   PDF/A-3          Lossless     ✓         │
│  Word DOC      →   PDF/A-3          Lossless     ✓         │
│  TIFF (any)    →   TIFF Uncomp.     Lossless     ✓         │
│  JPEG          →   JPEG 2000        Lossless     ✓         │
│  WAV           →   FLAC             Lossless     ✓         │
│  AVI           →   FFV1/MKV         Lossless     ✓         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Creating a Migration Plan

```
Step 1: Review Obsolescence Report
        ↓
Step 2: Select formats to migrate
        ↓
Step 3: Choose target formats
        ↓
Step 4: Create migration plan
        ↓
Step 5: Get approval (if required)
        ↓
Step 6: Execute migration
        ↓
Step 7: Verify results
```

### Migration Plan Status

```
┌─────────────────────────────────────────────────────────────┐
│  MIGRATION PLAN: TIFF Migration 2026                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Status:     In Progress                                    │
│  Created:    2026-01-15                                     │
│  Approved:   2026-01-16                                     │
│                                                             │
│  Progress:   ████████████░░░░░░░░ 60%                       │
│                                                             │
│  Total:      1,234 items                                    │
│  Completed:  740 items                                      │
│  Failed:     3 items                                        │
│  Pending:    491 items                                      │
│                                                             │
│  [Pause]  [Resume]  [View Details]                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Migration Best Practices

```
┌────────────────────────────────┬────────────────────────────┐
│  ✓ DO                          │  ✗ DON'T                   │
├────────────────────────────────┼────────────────────────────┤
│  Keep original files           │  Delete originals          │
│  Verify checksums after        │  Skip verification         │
│  Test with samples first       │  Migrate everything at once│
│  Document decisions            │  Migrate without approval  │
│  Schedule during low-usage     │  Run during peak hours     │
│  Monitor progress              │  Leave unattended          │
└────────────────────────────────┴────────────────────────────┘
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| "Siegfried not installed" | sf command not found | Install: `curl -sL https://github.com/richardlehane/siegfried/releases/download/v1.11.1/siegfried_1.11.1-1_amd64.deb -o /tmp/sf.deb && sudo dpkg -i /tmp/sf.deb` |
| "ClamAV not installed" | ClamAV not found | Install: `sudo apt install clamav clamav-daemon` |
| "ImageMagick not found" | convert command missing | Install: `sudo apt install imagemagick` |
| "LibreOffice not found" | libreoffice command missing | Install: `sudo apt install libreoffice` |
| "File not found" during fixity | File moved or deleted | Check storage paths, restore from backup |
| Checksum mismatch | File corruption/modification | Investigate cause, restore if needed |
| Conversion failed | Tool error or unsupported format | Check tool logs, verify file format |
| Replication failed | Network or permission issue | Check target connectivity and permissions |
| PUID showing as UNKNOWN | Format not in PRONOM registry | File may have non-standard format |

### Getting Help

- **Documentation:** Check the technical documentation for advanced configuration
- **Support:** Contact your system administrator
- **Logs:** Review preservation events for detailed error information
- **CLI Help:** Run `php symfony help preservation:convert` for command help

---

## Glossary

| Term | Definition |
|------|------------|
| **Checksum** | Mathematical fingerprint uniquely identifying file contents |
| **Fixity** | The property of a file being unchanged over time |
| **PREMIS** | Preservation Metadata standard (PREservation Metadata Implementation Strategies) |
| **OAIS** | Open Archival Information System - ISO 14721 standard for archives |
| **SIP** | Submission Information Package - OAIS package for content ingest |
| **AIP** | Archival Information Package - OAIS package for long-term storage |
| **DIP** | Dissemination Information Package - OAIS package for user access |
| **BagIt** | File packaging format specification (RFC 8493) for data transfer |
| **PRONOM** | Technical format registry maintained by The National Archives (UK) |
| **PUID** | PRONOM Unique Identifier - standardized format identifier (e.g., fmt/43) |
| **Siegfried** | Format identification tool using PRONOM signatures |
| **DROID** | Digital Record Object Identification - format ID tool from The National Archives |
| **ClamAV** | Open-source antivirus engine for detecting malware |
| **ImageMagick** | Image processing tool for format conversion |
| **FFmpeg** | Audio/video processing tool |
| **LibreOffice** | Office suite for document conversion |
| **Ghostscript** | PDF processing tool |
| **Quarantine** | Isolated storage for infected files |
| **Replication** | Copying files to backup locations |
| **Normalization** | Converting files to a standard preservation format |

---

*Last Updated: January 2026*
*Plugin Version: 1.5.0*
