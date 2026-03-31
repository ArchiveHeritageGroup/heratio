# Duplicate Detection

## User Guide

Identify and manage duplicate archival records to maintain data quality and reduce redundancy in your archive.

---

## Overview
```
+-------------------------------------------------------------+
|                   DUPLICATE DETECTION                        |
+-------------------------------------------------------------+
|                                                              |
|   DETECT          REVIEW          DECIDE          RESOLVE   |
|     |               |               |               |       |
|     v               v               v               v       |
|  Automatic       Compare        Confirm or       Merge or   |
|  Scanning        Side-by-Side   Dismiss          Keep Both  |
|                                                              |
+-------------------------------------------------------------+
```

---

## What Gets Detected
```
+-------------------------------------------------------------+
|                  DUPLICATE DETECTION METHODS                 |
+-------------------------------------------------------------+
|                                                              |
|  Title Similarity   - Records with similar titles           |
|  Identifier Match   - Exact or fuzzy identifier matches     |
|  Date + Creator     - Same date range and creator           |
|  File Checksum      - Identical digital files (SHA-256)     |
|  Combined Analysis  - Multi-factor weighted scoring         |
|                                                              |
+-------------------------------------------------------------+
```

---

## How to Access
```
  Main Menu
      |
      v
   Admin
      |
      v
   Duplicate Detection ---------------------------+
      |                                           |
      +---> Dashboard      (overview & stats)     |
      |                                           |
      +---> Browse         (all detected pairs)   |
      |                                           |
      +---> New Scan       (start detection)      |
      |                                           |
      +---> Rules          (configure detection)  |
      |                                           |
      +---> Reports        (analytics)            |
```

---

## The Dashboard

When you open Duplicate Detection, you will see the main dashboard.

### Statistics Cards
```
+------------+------------+------------+------------+------------+
|   Total    |  Pending   | Confirmed  |  Merged    | Dismissed  |
| Detected   |  Review    |            |            |            |
+------------+------------+------------+------------+------------+
|    156     |     34     |     12     |     89     |     21     |
+------------+------------+------------+------------+------------+
```

### What Each Status Means

| Status | Description |
|--------|-------------|
| Pending | Newly detected, awaiting review |
| Confirmed | Verified as duplicates, not yet merged |
| Merged | Records have been combined |
| Dismissed | Determined to be false positives |

---

## Reviewing Duplicates

### Step 1: Open Browse View

Go to **Admin** > **Duplicate Detection** > **Browse**

### Step 2: Filter the List
```
+-------------------------------------------------------------+
|                      FILTER OPTIONS                          |
+-------------------------------------------------------------+
|                                                              |
|  Status:          [Pending        v]                        |
|                                                              |
|  Method:          [All Methods    v]                        |
|                                                              |
|  Minimum Score:   [0.75           v]                        |
|                                                              |
|                         [Apply Filters]                      |
|                                                              |
+-------------------------------------------------------------+
```

### Step 3: Review Results

You will see a table of detected duplicates:
```
+--------+-------+-------------------+-----------+----------------+
| Score  | Method| Record A          | Record B  | Actions        |
+--------+-------+-------------------+-----------+----------------+
|  95%   | Title | Meeting Minutes   | Minutes   | Compare/Merge  |
|  88%   | ID    | DOC-2024-001      | DOC-24-1  | Compare/Merge  |
|  82%   | Date  | Annual Report '23 | AR 2023   | Compare/Merge  |
+--------+-------+-------------------+-----------+----------------+
```

### Understanding Similarity Scores

| Score Range | Meaning | Action |
|-------------|---------|--------|
| 90-100% | Very likely duplicate | Review and merge |
| 75-89% | Probable duplicate | Compare carefully |
| 50-74% | Possible duplicate | Verify before action |

---

## Comparing Records

### Step 1: Click Compare

From the browse list, click the Compare button for any duplicate pair.

### Step 2: Review Side-by-Side
```
+-----------------------------+-----------------------------+
|         RECORD A            |         RECORD B            |
+-----------------------------+-----------------------------+
| Title:                      | Title:                      |
| Meeting Minutes 1985-1990   | Minutes 1985-90             |
+-----------------------------+-----------------------------+
| Identifier:                 | Identifier:                 |
| MIN-1985-001                | MIN-85-1                    |
+-----------------------------+-----------------------------+
| Level:                      | Level:                      |
| File                        | File                        |
+-----------------------------+-----------------------------+
| Repository:                 | Repository:                 |
| Main Archive                | Main Archive                |
+-----------------------------+-----------------------------+
| Scope & Content:            | Scope & Content:            |
| Contains meeting minutes... | Meeting minutes from...     |
+-----------------------------+-----------------------------+
```

Fields that differ are highlighted in yellow for easy identification.

### Step 3: Make a Decision

- **Merge**: Combine the records if they are true duplicates
- **Dismiss**: Mark as false positive if they are distinct records

---

## Merging Duplicates

### Step 1: Select Primary Record

Choose which record to keep as the primary:
```
+-----------------------------+-----------------------------+
|     RECORD A (Keep)         |     RECORD B (Merge)        |
|                             |                             |
|  ( ) Select as Primary      |  (*) Select as Primary      |
|                             |                             |
|  - More complete data       |  - Newer creation date      |
|  - Better title             |  - Has digital objects      |
+-----------------------------+-----------------------------+
```

### Step 2: Review Merge Actions

The system will show what will happen:
```
+-------------------------------------------------------------+
|                    MERGE ACTIONS                             |
+-------------------------------------------------------------+
|                                                              |
|  - Transfer 3 digital objects from Record B to Record A     |
|  - Move 5 child records from Record B to Record A           |
|  - Redirect slug 'minutes-85' to 'meeting-minutes-1985'     |
|  - Create merge log entry for audit trail                   |
|  - Archive Record B (not deleted)                           |
|                                                              |
+-------------------------------------------------------------+
```

### Step 3: Confirm and Merge

Check the confirmation box and click **Merge Records**.

**Warning**: Merging is permanent. The secondary record will be archived but cannot be easily restored.

---

## Dismissing False Positives

If two records are not actually duplicates:

1. Click **Dismiss** from the browse list or compare view
2. Optionally add a note explaining why
3. The pair will be marked as "dismissed" and won't appear in pending

---

## Running a New Scan

### From the Web Interface

1. Go to **Admin** > **Duplicate Detection** > **New Scan**
2. Select a repository (or scan all)
3. Click **Start Scan**
4. The scan job is queued for background processing

### From the Command Line

```bash
# Scan a specific repository
php symfony dedupe:scan --repository=1

# Scan entire system
php symfony dedupe:scan --all

# Limit records scanned
php symfony dedupe:scan --all --limit=1000
```

### Scan Progress
```
+-------------------------------------------------------------+
|                    SCAN PROGRESS                             |
+-------------------------------------------------------------+
|                                                              |
|  Status:      Running                                        |
|  Progress:    2,450 / 5,000 records (49%)                   |
|  Duplicates:  34 found so far                               |
|  Started:     10 Jan 2026, 14:30                            |
|                                                              |
+-------------------------------------------------------------+
```

---

## Detection Rules

### Viewing Rules

Go to **Admin** > **Duplicate Detection** > **Rules**

### Default Rules

| Rule Name | Type | Threshold | Blocking |
|-----------|------|-----------|----------|
| Title Similarity | title_similarity | 85% | No |
| Identifier Exact Match | identifier_exact | 100% | Yes |
| Identifier Fuzzy Match | identifier_fuzzy | 90% | No |
| Date Range + Creator | date_creator | 90% | No |
| File Checksum Match | checksum | 100% | No |
| Combined Analysis | combined | 75% | No |

### Creating a Custom Rule

1. Click **Create Rule**
2. Configure the rule:
```
+-------------------------------------------------------------+
|                    CREATE DETECTION RULE                     |
+-------------------------------------------------------------+
|                                                              |
|  Name:        [My Custom Rule                    ]          |
|                                                              |
|  Type:        [Title Similarity         v]                  |
|                                                              |
|  Threshold:   [0.80] (0.0 to 1.0)                           |
|                                                              |
|  Repository:  [All Repositories         v]                  |
|                                                              |
|  Priority:    [100]                                          |
|                                                              |
|  [ ] Blocking (prevent save if match found)                 |
|  [x] Enabled                                                 |
|                                                              |
|                    [Save Rule]                               |
|                                                              |
+-------------------------------------------------------------+
```

### Blocking Rules

When a rule is set as "blocking":
- Users cannot save a record if a match is found
- Used for strict identifier uniqueness enforcement
- Shows warning during data entry

---

## Real-Time Duplicate Checking

When creating new records, the system can check for duplicates in real-time:

```
+-------------------------------------------------------------+
|  Title: [Meeting Minutes 1985                    ]          |
|         +-----------------------------------------------+   |
|         | Possible duplicates found:                    |   |
|         |                                               |   |
|         | - Meeting Minutes 1985-1990 (92% match)       |   |
|         | - Minutes 1985 (88% match)                    |   |
|         +-----------------------------------------------+   |
+-------------------------------------------------------------+
```

---

## Reports and Analytics

### Accessing Reports

Go to **Admin** > **Duplicate Detection** > **Reports**

### Available Reports

1. **Monthly Statistics**
   - Duplicates detected per month
   - Merged vs dismissed ratio
   - Detection method effectiveness

2. **Top Duplicate Clusters**
   - Records with most duplicate pairs
   - Helps identify systematic issues

3. **Efficiency Metrics**
   - Total detected vs reviewed
   - False positive rate
   - Average merge score

### Exporting Reports

```bash
# Export to CSV
php symfony dedupe:report --format=csv --output=duplicates.csv

# Export to JSON
php symfony dedupe:report --format=json --output=duplicates.json

# Filter by status
php symfony dedupe:report --status=pending --format=csv

# Filter by minimum score
php symfony dedupe:report --min-score=0.9 --format=table
```

---

## Best Practices
```
+--------------------------------+--------------------------------+
|  DO                            |  DON'T                         |
+--------------------------------+--------------------------------+
|  Review high-score matches     |  Merge without comparing       |
|  first (90%+)                  |                                |
+--------------------------------+--------------------------------+
|  Compare records carefully     |  Dismiss without checking      |
|  before merging                |                                |
+--------------------------------+--------------------------------+
|  Run scans after bulk imports  |  Ignore pending duplicates     |
|                                |                                |
+--------------------------------+--------------------------------+
|  Use blocking rules for        |  Set threshold too low         |
|  critical identifiers          |  (causes false positives)      |
+--------------------------------+--------------------------------+
|  Document dismissal reasons    |  Merge records from different  |
|                                |  repositories carelessly       |
+--------------------------------+--------------------------------+
```

---

## CLI Commands Reference

### Scanning
```bash
# Scan specific repository
php symfony dedupe:scan --repository=1

# Scan entire system
php symfony dedupe:scan --all

# Limit records
php symfony dedupe:scan --all --limit=5000
```

### Merging
```bash
# Merge a duplicate pair (keep record A)
php symfony dedupe:merge 123

# Merge keeping record B
php symfony dedupe:merge 123 --primary=b

# Preview merge without changes
php symfony dedupe:merge 123 --dry-run

# Force merge without confirmation
php symfony dedupe:merge 123 --force
```

### Reporting
```bash
# Show pending duplicates
php symfony dedupe:report --status=pending

# High confidence matches only
php symfony dedupe:report --min-score=0.9

# Export to file
php symfony dedupe:report --format=csv --output=report.csv
```

---

## Troubleshooting

### No Duplicates Found After Scan

- Check if detection rules are enabled
- Verify threshold settings (may be too high)
- Ensure records have titles/identifiers to compare

### Too Many False Positives

- Increase threshold values in rules
- Disable overly broad rules
- Use combined analysis instead of single-factor rules

### Scan Taking Too Long

- Scan specific repositories instead of entire system
- Use --limit option for large archives
- Run scans during off-peak hours

### Merge Failed

- Check if either record has been deleted
- Verify you have administrator permissions
- Check for database errors in logs

---

## Workflow Integration

### After Bulk Import
```
  Import Data
      |
      v
  Run Scan (php symfony dedupe:scan --repository=X)
      |
      v
  Review High-Score Matches (90%+)
      |
      v
  Merge True Duplicates
      |
      v
  Dismiss False Positives
```

### Regular Maintenance
```
  Weekly: Review pending duplicates
  Monthly: Run full system scan
  Quarterly: Review and tune detection rules
```

---

## Need Help?

Contact your system administrator if you experience issues or need to modify detection rules.

---

*Part of the AtoM AHG Framework*
