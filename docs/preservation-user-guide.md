> Heratio Help Center article. Category: User Guide.

# Digital Preservation User Guide

> Digital Preservation is a built-in Heratio capability, not a separate add-on. There is nothing to install. CLI tasks run through Heratio's `php artisan` console and the in-app Scheduler. For the wider integrity and authenticity picture, see the *Content Authenticity in Heratio* reference and the *Integrity Assurance* user guide.

## Overview

Digital Preservation in Heratio helps you protect your digital collections by ensuring files remain unchanged over time, tracking all preservation activities, identifying file formats that may require attention, scanning for viruses, converting files to preservation-safe formats, and replicating backups to multiple locations.

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
3. Or go directly to: `/admin/preservation`

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
3. Or navigate to: `/admin/preservation/policies`

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
# Verify up to 100 objects whose last check is older than 90 days (defaults)
php artisan ahg:preservation-fixity

# Verify objects not checked in 7+ days, up to 500
php artisan ahg:preservation-fixity --age=7 --limit=500

# Use SHA-512 instead of the SHA-256 default
php artisan ahg:preservation-fixity --algorithm=sha512 --limit=200

# Verify a single digital object
php artisan ahg:preservation-fixity --digital-object-id=123

# Print a summary report and exit
php artisan ahg:preservation-fixity --report
```

**Restoring from backup after a failure:**

When fixity detects corruption or a missing file, restore the affected object from a verified backup copy. Backups are managed by the Backup & Replication module - replicas are independently checksummed (see [Backup Replication](#6-backup-replication)). Use the backup restore commands to recover a specific object:

```bash
# Re-verify replicated backups against their recorded checksums
php artisan backup:verify-integrity

# Restore a single information object from backup
php artisan backup:restore-io
```

Every restore is logged as a PREMIS event, preserving the audit trail.

**Scheduled verification:**
Day-to-day, fixity runs from the in-app **Scheduler** (see [Workflow Scheduler](#9-workflow-scheduler)) - no per-task crontab editing is required once Heratio's task scheduler is active.

---

### 3. Virus Scanning

Heratio integrates with ClamAV to scan digital objects for malware.

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
# Scan up to 200 objects (default)
php artisan ahg:preservation-virus-scan

# Only scan objects with no prior scan record
php artisan ahg:preservation-virus-scan --unscanned --limit=100

# Show recent virus-scan history and exit
php artisan ahg:preservation-virus-scan --report

# Point at a specific ClamAV binary
php artisan ahg:preservation-virus-scan --clamav-binary=/usr/bin/clamscan
```

#### Quarantine

Infected files are moved to a quarantine area within the configured Heratio storage location, renamed with a timestamp and a `.quarantine` extension. Review quarantined files before permanent deletion.

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

Conversion is driven by the Format Policy Registry (FPR). Each rule maps a source format to a target preservation or access format and the tool that performs it. Run normalization with `ahg:normalize-existing`:

```bash
# Normalize existing objects to their preservation master (queued)
php artisan ahg:normalize-existing

# Produce access (reference) derivatives instead
php artisan ahg:normalize-existing --purpose=access

# Only objects of a given MIME type, run inline rather than queued
php artisan ahg:normalize-existing --mime=image/jpeg --sync

# Cap the number of objects processed
php artisan ahg:normalize-existing --limit=50
```

For planned, approval-gated format migrations across a collection, use the migration runner:

```bash
# Run all active migration plans (or a specific plan)
php artisan ahg:preservation-migrate --plan-id=1 --limit=50

# Simulate without converting
php artisan ahg:preservation-migrate --dry-run
```

#### Output Location

Derivatives are attached as child digital objects of the original, each with its own SHA-256 checksum, and stored within the configured Heratio storage location. Originals are always preserved.

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
# Re-verify replicated backups against their recorded SHA-256 checksums
php artisan backup:verify-integrity
```

Backup integrity is tracked in the replication ledger - each replica records its SHA-256, target driver, and verification status. See the Backup & Replication settings for target configuration.

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
# Replicate unreplicated packages to all enabled targets (default limit 20/target)
php artisan ahg:preservation-replicate

# Replicate to a specific named target
php artisan ahg:preservation-replicate --target=offsite-s3 --limit=100

# Replicate a specific package
php artisan ahg:preservation-replicate --package-id=42

# Simulate without transferring
php artisan ahg:preservation-replicate --dry-run
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

Heratio integrates with Siegfried to provide PRONOM-based format identification, the same identification method used by The National Archives (UK) and DROID.

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
# Identify up to 1000 unidentified objects (default)
php artisan ahg:preservation-identify

# Identify a single digital object
php artisan ahg:preservation-identify --digital-object-id=123

# Print the PRONOM risk distribution and exit
php artisan ahg:preservation-identify --risk
```

#### Benefits of PRONOM Identification

- **Standardized** - Uses international format registry
- **Accurate** - Signature-based detection, not just extensions
- **Risk Assessment** - Automatically assigns preservation risk levels
- **Interoperability** - Compatible with DROID, Archivematica, Preservica

#### Format Registry Population

The PRONOM format registry is populated automatically as objects are identified: each new PUID encountered during `ahg:preservation-identify` is recorded in the format registry with its name, version, MIME type, risk level, and preservation action. Run identification across your collection to keep the registry current; review the results under **Preservation -> Format Registry**.

**Format data captured per PUID:**
- Official format name and version
- MIME type
- Risk level (low / medium / high / unknown)
- Whether it is a preservation format, and the recommended action (retain or monitor)

---

### 9. Workflow Scheduler

The Workflow Scheduler allows you to configure and monitor automated preservation tasks through a visual interface, without requiring direct server access.

#### Accessing the Scheduler

1. Navigate to **Preservation** -> **Scheduler**
2. Or go directly to: `/admin/preservation/scheduler`

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

Due preservation workflows are dispatched by `ahg:preservation-scheduler`, which Heratio's task scheduler invokes for you. You can also run it manually:

```bash
# Run all due schedules
php artisan ahg:preservation-scheduler

# Force every schedule to run now, regardless of last run time
php artisan ahg:preservation-scheduler --force

# Simulate without executing
php artisan ahg:preservation-scheduler --dry-run
```

As long as Heratio's task scheduler is active (the standard single `php artisan schedule:run` cron entry), there is no need to add a crontab line per workflow.

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
| `php artisan ahg:preservation-scheduler` | Run due preservation workflows |
| `php artisan ahg:preservation-identify` | Identify file formats using Siegfried (PRONOM) |
| `php artisan ahg:preservation-fixity` | Run fixity verification checks |
| `php artisan ahg:preservation-virus-scan` | Scan files for viruses with ClamAV |
| `php artisan ahg:normalize-existing` | Generate preservation/access derivatives (FPR) |
| `php artisan ahg:preservation-migrate` | Execute format migration plans |
| `php artisan ahg:preservation-replicate` | Replicate packages to backup targets |
| `php artisan ahg:preservation-package` | Build OAIS packages (SIP/AIP/DIP) |
| `php artisan ahg:preservation-obsolescence` | List high-risk / obsolete formats |
| `php artisan ahg:preservation-stats` | Show preservation statistics |
| `php artisan backup:verify-integrity` | Verify replicated backups against checksums |

### Common Options

| Option | Description |
|--------|-------------|
| `--limit=N` | Maximum objects to process |
| `--digital-object-id=N` | Process a specific digital object |
| `--dry-run` | Preview without making changes (where supported) |
| `--report` / `--risk` | Show statistics and exit (where supported) |

### Scheduling

The recommended approach is the in-app **Workflow Scheduler** (**Preservation -> Scheduler**), which runs all configured workflows automatically via Heratio's task scheduler. You only need the standard Laravel scheduler cron entry on the server:

```bash
# The single cron line that drives ALL Heratio scheduled tasks
* * * * * cd /usr/share/nginx/heratio && php artisan schedule:run >> /dev/null 2>&1
```

Individual commands from the table above can also be run manually on demand.

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

The full draft -> build -> validate -> export workflow is driven from the **OAIS Packages** UI. The CLI builds a package directly for a given information object:

```bash
# Build an AIP for an information object
php artisan ahg:preservation-package --type=aip --object-id=123

# Build a SIP
php artisan ahg:preservation-package --type=sip --object-id=123

# Build a DIP from an existing AIP
php artisan ahg:preservation-package --type=dip --from-aip=45 --object-id=123

# Include derivative files, and record who built it
php artisan ahg:preservation-package --type=aip --object-id=123 --include-derivatives --created-by="archivist@example.org"
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
- **CLI Help:** Run any command with `--help`, e.g. `php artisan ahg:preservation-fixity --help`

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

*Last Updated: June 2026*
