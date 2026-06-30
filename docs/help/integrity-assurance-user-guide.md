> Heratio Help Center article. Category: User Guide.

# Heratio - Integrity Assurance User Guide

**Author:** The Archive and Heritage Group (Pty) Ltd

> Integrity Assurance is a built-in Heratio feature, not a separate add-on. There is nothing to install. It is part of the digital-preservation capability and works alongside fixity, format identification, normalization, PREMIS event logging, and the tamper-evident audit trail. For the full architecture, see the *Content Authenticity in Heratio* reference.

---

## Table of Contents

1. [Overview](#overview)
2. [Getting Started](#getting-started)
3. [Dashboard](#dashboard)
4. [Schedules](#schedules)
5. [Run History](#run-history)
6. [Verification Ledger](#verification-ledger)
7. [Dead Letter Queue](#dead-letter-queue)
8. [Reports](#reports)
9. [Export & Auditor Pack](#export--auditor-pack)
10. [Retention Policies](#retention-policies)
11. [Legal Holds](#legal-holds)
12. [Disposition Queue](#disposition-queue)
13. [Alerts](#alerts)
14. [Running Verifications](#running-verifications)
15. [Troubleshooting](#troubleshooting)

---

## Overview

Integrity Assurance automates the verification of digital object integrity in your Heratio instance. It ensures that archival files have not been corrupted, modified, or lost by comparing stored cryptographic checksums against current file hashes.

**How it works:**

1. Heratio's preservation pipeline generates baseline checksums (SHA-256 or SHA-512) when digital objects are ingested
2. Integrity Assurance runs scheduled or ad-hoc verification passes
3. Each verification is recorded in an append-only ledger with actor, hostname, and previous-hash chain tracking
4. Persistent failures are escalated to a dead-letter queue for manual investigation
5. Retention policies determine when records become eligible for disposition review
6. Legal holds block disposition of records under review
7. Threshold-based alerts notify administrators of integrity issues via email or webhook
8. Reports and dashboards provide visibility into overall collection health

## Getting Started

Integrity Assurance ships with Heratio and is available to administrators at **Admin > Integrity** (`/admin/integrity`). The related fixity and preservation surfaces live at **Admin > Fixity** (`/admin/fixity`) and **Admin > Preservation** (`/admin/preservation`).

Two default schedules are provided out of the box:

- **Daily Sample Check** (enabled): verifies a sample of master objects every day
- **Weekly Full Scan** (disabled): comprehensive scan of all master objects

Baseline checksums are produced automatically by the preservation pipeline when objects are ingested. Integrity Assurance will also generate a baseline on first verification of an object that does not yet have one.

## Dashboard

Access: **Admin > Integrity** or navigate to `/admin/integrity`

The dashboard provides an at-a-glance view of your collection's integrity health:

**Top Row (6 cards):**
- **Master Objects**: Total number of master digital objects in the system
- **Total Verifications**: Cumulative verification count across all runs
- **Pass Rate**: Percentage of verifications that matched baseline checksums
- **Open Dead Letters**: Number of unresolved persistent failures (requires attention)
- **Never Verified** (backlog): Master objects that have never been verified
- **Throughput (7d)**: Verification speed in objects/hour and GB/hour

**Storage Growth KPI:**
- Total storage scanned over the last 30 days (in GB)
- Average GB/day scan rate

**Daily Trend (30 days):**
- Interactive stacked bar chart showing daily pass (green) and fail (red) counts
- Helps identify trends and anomalies at a glance

**Repository Breakdown:**
- Per-repository table with total verifications, passed, failed, and pass rate
- Click a repository name to filter all dashboard statistics to that repository

**Failure Type Breakdown:**
- Distribution of failure types (mismatch, missing, unreadable, etc.) over the last 30 days
- Helps prioritize remediation efforts

**Format Breakdown:**
- Verification results grouped by file format (from the preservation object-format data)
- Shows total, passed, and failed counts per format

**Repository Filter:**
- Dropdown at the top of the dashboard to scope all statistics to a specific repository
- Click "Clear Filter" to return to global view

**Navigation buttons** in the header provide quick access to: Export, Policies, Holds, Alerts, Schedules, Ledger, Report.

## Schedules

Access: **Admin > Integrity > Schedules**

### Creating a Schedule

1. Click **New Schedule**
2. Configure the schedule:
   - **Name**: Descriptive name (e.g., "Monthly Repository X Audit")
   - **Scope**: Global (all objects), Repository (specific institution), or Hierarchy (specific node and descendants)
   - **Algorithm**: SHA-256 (faster) or SHA-512 (more secure)
   - **Frequency**: Daily, Weekly, Monthly, or Ad hoc
   - **Cron Expression**: Optional override for custom schedules (e.g., `0 3 * * 5` for Fridays at 3am)
3. Configure concurrency controls:
   - **Batch Size**: Objects per run (0 = unlimited). Use 200-500 for daily, 0 for weekly full scans
   - **IO Throttle**: Milliseconds pause between objects (0-50ms recommended)
   - **Max Memory**: Memory limit in MB (default 512MB)
   - **Max Runtime**: Time limit in minutes (default 120)
   - **Max Concurrent Runs**: Prevents overlapping executions (default 1)
4. Configure notifications:
   - **Notify on failure**: Alert when a run fails completely
   - **Notify on mismatch**: Alert when hash mismatches are detected
   - **Email**: Notification recipient address

### Managing Schedules

From the schedule list, you can:
- **Toggle** (play/pause icon): Enable or disable a schedule
- **Run Now** (bolt icon): Execute immediately regardless of schedule
- **Edit** (pencil icon): Modify schedule settings
- **Delete** (trash icon): Remove the schedule (blocked if a run is active)

Schedules are executed by Heratio's task scheduler - there is no per-site crontab to edit. As long as the Heratio scheduler is running, due schedules fire automatically.

## Run History

Access: **Admin > Integrity > Runs**

Each verification run records:
- **Status**: Running, Completed, Partial (memory limit), Failed, Timeout, Cancelled
- **Counters**: Objects scanned, passed, failed, missing, error, skipped
- **Trigger**: Scheduler (automated), Manual (web UI), or API
- **Timing**: Start and completion timestamps

Click a run ID to view detailed results including all ledger entries for that run.

## Verification Ledger

Access: **Admin > Integrity > Ledger**

The ledger is an **append-only** audit trail. Entries are never updated or deleted, providing forensic-grade evidence of verification activities.

Each entry records:
- Digital object ID (survives even if the object is later deleted)
- File path, size, existence, and readability status
- Hash algorithm, expected hash, computed hash, and match result
- Outcome: pass, mismatch, missing, unreadable, permission_error, path_drift, no_baseline, error
- **Actor**: Who/what triggered the verification (user, system, scheduler, API)
- **Hostname**: Server that performed the verification
- **Previous Hash**: The computed hash from the most recent successful verification of the same object, enabling chain-of-custody verification and tamper detection
- Verification timestamp and duration

### Database Immutability Advisory

The integrity ledger is designed as an append-only audit trail. To enforce this at the database level, you can grant the application database user only SELECT and INSERT on the ledger table, and revoke UPDATE and DELETE. This ensures that even in the event of a compromised application, the verification audit trail cannot be tampered with. The previous-hash column provides an additional layer of chain verification - any gap or inconsistency in the hash chain indicates potential ledger tampering.

### Filtering

- **Outcome**: Filter by specific outcome type
- **Date range**: Filter by verification date
- **Repository**: Filter by repository

## Dead Letter Queue

Access: **Admin > Integrity > Dead Letter**

Objects that fail verification 3 or more consecutive times are automatically escalated to the dead letter queue. This prevents known-bad objects from consuming verification resources while ensuring they receive attention.

### Workflow States

| State | Meaning | Next Actions |
|-------|---------|-------------|
| **Open** | New failure, needs attention | Acknowledge, Investigate, Resolve, Ignore |
| **Acknowledged** | Someone has seen it | Investigate, Resolve, Ignore |
| **Investigating** | Under active investigation | Resolve, Ignore |
| **Resolved** | Issue has been fixed | Reopen (if it fails again) |
| **Ignored** | Intentionally excluded | Reopen |

### Common Failure Types

| Type | Cause | Resolution |
|------|-------|------------|
| **mismatch** | File hash differs from baseline | Investigate: was the file modified intentionally? If corruption, restore from backup |
| **missing** | File not found at expected path | Check if file was moved, deleted, or if storage mount is offline |
| **unreadable** | File exists but cannot be read | Check file permissions and ownership |
| **permission_error** | Access denied to file path | Fix filesystem permissions |
| **path_drift** | File path has changed | Update digital object record or restore symlinks |

## Reports

Access: **Admin > Integrity > Report**

The report page shows:
- Summary statistics (master objects, verifications, pass rate, dead letters)
- Outcome breakdown with percentage bars
- Monthly trend table (12 months) showing pass rates over time

## Export & Auditor Pack

Access: **Admin > Integrity > Export**

### CSV Export

Download the verification ledger as a CSV file with filters:
- Date range (from/to)
- Repository
- Outcome type

The CSV includes all ledger columns: ID, run ID, digital object ID, file path, hashes, outcome, actor, hostname, and timestamp.

### Auditor Pack (ZIP)

Download a self-contained ZIP archive for compliance audits containing:
- **summary.html**: Standalone HTML report with inline CSS (no external dependencies), showing statistics, schedule configuration, and overall health
- **exceptions.csv**: All non-pass verification entries
- **config-snapshot.json**: Complete schedule configuration, dead letter summary, and current statistics

Both export types support the same filter parameters.

## Retention Policies

Access: **Admin > Integrity > Policies**

Retention policies define how long records should be kept before becoming eligible for disposition review. This does NOT automatically delete records - it only identifies candidates for human review.

### Creating a Policy

1. Click **New Policy**
2. Configure:
   - **Name**: Descriptive name (e.g., "7-Year Financial Records")
   - **Retention Period**: Number of days (0 = indefinite, never eligible)
   - **Trigger Type**: When the clock starts
     - `ingest_date`: From when the record was created in Heratio
     - `last_modified`: From last modification date
     - `closure_date`: From closure/completion date
     - `last_access`: From last access date
   - **Object Format**: Optional MIME type filter (e.g., `image/tiff`, `application/pdf`). Leave empty for all formats. Uses prefix matching, so `image/` matches all image types.
   - **Scope**: Global, per-repository, or per-hierarchy node
   - **Enabled**: Toggle to activate/deactivate

### Managing Policies

From the policy list:
- **Toggle**: Enable/disable the policy
- **Edit**: Modify policy settings
- **Delete**: Remove the policy (also removes its disposition queue entries)

### Scanning for Eligible Records

Use the **Scan for Eligible** button on the Disposition page to identify records that have passed their retention period.

## Legal Holds

Access: **Admin > Integrity > Holds**

Legal holds prevent records from being disposed of, even if they are past their retention period. Use legal holds when records are subject to litigation, investigation, or regulatory review.

### Placing a Hold

1. Click **Place Hold**
2. Enter the Information Object ID
3. Provide a reason (required for audit trail)
4. Click **Place Hold**

When a hold is placed:
- The hold is recorded with the placer's name and timestamp
- Any matching disposition queue entries are moved to "held" status
- A ledger entry is created for audit purposes

### Releasing a Hold

Click the unlock icon next to an active hold. When released:
- The hold is marked as "released" with the releaser's name and timestamp
- If no other active holds exist on the record, disposition queue entries revert to "eligible"
- A ledger entry is created for audit purposes

## Disposition Queue

Access: **Admin > Integrity > Disposition**

The disposition queue shows records that have passed their retention period and are candidates for review.

### Status Flow

```
eligible -> pending_review -> approved -> disposed
                           -> rejected
                           -> held (if legal hold placed)
```

### Reviewing Records

- Click the checkmark to **approve** disposition
- Click the X to **reject** disposition
- Optionally add review notes

**Important**: "Disposed" status only marks the record - it does NOT delete anything. Actual deletion (if required) is a separate manual process.

### Status Summary

The page header shows counts for each status, helping prioritize review work.

## Alerts

Access: **Admin > Integrity > Alerts**

Configure threshold-based alerts to be notified when integrity metrics cross defined boundaries.

### Alert Types

| Type | Description | Example |
|------|-------------|---------|
| **Pass rate below** | Triggers when pass rate drops | Alert if pass rate < 95% |
| **Failure count above** | Triggers when failures exceed threshold | Alert if > 10 failures per run |
| **Dead letter count above** | Triggers when open dead letters exceed threshold | Alert if > 5 open dead letters |
| **Backlog above** | Triggers when unverified objects exceed threshold | Alert if > 1000 never-verified |
| **Run failure** | Triggers on any failed/timeout/partial run | Alert on any run failure |

### Notification Channels

- **Email**: Sent via Heratio's configured mail transport
- **Webhook**: HTTP POST to a URL with JSON payload
  - Optional HMAC-SHA256 signature in `X-Signature` header for verification
  - Useful for integration with Slack, Teams, PagerDuty, etc.

### Creating an Alert

1. Click **New Alert**
2. Select the alert type and comparison operator
3. Set the threshold value
4. Provide email and/or webhook URL
5. Optionally add a webhook secret for HMAC signing
6. Enable/disable the alert

Alerts are evaluated after each batch verification run. Alert failures are non-fatal - they never break the verification process.

## Running Verifications

Day-to-day, verifications run automatically from the schedules you configure (see [Schedules](#schedules)), and you can trigger an immediate pass from the UI:

- **Run Now** on any schedule (**Admin > Integrity > Schedules**) executes it immediately
- **Verify** a single object from its preservation detail page (**Admin > Preservation > object**)
- Scheduled fixity sweeps are managed under **Admin > Preservation > Scheduler** (`/admin/preservation/scheduler`)

Programmatic / external monitoring is available through the Heratio REST API (the `ahg-api` module); see the API documentation for the current endpoint catalogue rather than relying on hard-coded paths.

## Troubleshooting

### "Schedule already has running instances"
A previous run is still active or was interrupted. If the process is no longer running, the lock auto-recovers on the next attempt via stale-PID detection.

### No baseline checksums found
The preservation pipeline must have generated checksums for the digital objects. Baselines are created automatically on ingest; Integrity Assurance will also generate a baseline on first verification of an object that lacks one.

### "Memory limit reached" (partial status)
Increase the schedule's max memory setting or reduce the batch size.

### Objects reported as "missing" but files exist
Confirm the storage path is mounted and reachable (the Heratio uploads/storage location, e.g. the NAS mount). A disconnected mount is the most common cause of false "missing" results.

### Pass rate declining
1. Check the dead letter queue for patterns (same repository, same failure type)
2. Run a targeted verification for the affected repository
3. Check storage health (NAS connectivity, disk errors)

### Alerts not sending
1. Verify Heratio's mail configuration (see the mail/notification settings)
2. Check the webhook URL is reachable from the server
3. Verify the alert is enabled in **Admin > Integrity > Alerts**
4. Check the application logs for alert-related exceptions

### Retention scan finding no eligible records
1. Ensure the retention period is greater than 0 (0 = indefinite, never eligible)
2. Check that the trigger type matches your data (e.g., `ingest_date` requires a creation date)
3. Verify the policy scope matches your records (repository ID, hierarchy node)

---

*For technical support, contact The Archive and Heritage Group (Pty) Ltd at johan@theahg.co.za*
