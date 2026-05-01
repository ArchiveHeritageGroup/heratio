# Heratio - Accession Management V2: User Manual

**Version:** 2.0.0
**Date:** February 2026
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Intake Queue](#3-intake-queue)
4. [Checklists](#4-checklists)
5. [Attachments](#5-attachments)
6. [Timeline](#6-timeline)
7. [Appraisal](#7-appraisal)
8. [Valuation](#8-valuation)
9. [Containers](#9-containers)
10. [Rights Management](#10-rights-management)
11. [Numbering](#11-numbering)
12. [Configuration](#12-configuration)
13. [CLI Commands](#13-cli-commands)
14. [Troubleshooting](#14-troubleshooting)

---

## 1. Introduction

Accession Management V2 provides a comprehensive accession lifecycle management system for GLAM institutions. It extends the standard AtoM accession records with:

- A structured intake queue with status-based workflow
- Formal appraisal with weighted scoring criteria
- Heritage asset valuation tracking (GRAP 103/IPSAS 45)
- Physical container tracking with barcode support
- PREMIS-aligned rights management with inheritance to child records
- Full audit trail via immutable timeline

---

## 2. Getting Started

### Accessing the Intake Queue

Navigate to **Manage > Accessions** and click the **Intake Queue** button, or go directly to:
```
/admin/accessions/queue
```

### Accessing the Dashboard

The accession dashboard provides KPIs and recent activity:
```
/admin/accessions/dashboard
```

### Permissions

- **View** queue and timeline: Any authenticated user
- **Submit/Review/Accept/Reject**: Users with Editor or Administrator credentials
- **Configuration**: Administrator only

---

## 3. Intake Queue

### Overview

The intake queue is the central hub for managing incoming accessions. Each accession progresses through a defined workflow:

```
draft → submitted → under_review → accepted
                                  → rejected
                  → returned (→ submitted again)
```

### Queue Dashboard

The queue dashboard displays:
- **Filter bar**: Filter by status, priority, assigned user, or search text
- **Statistics cards**: Count of accessions by status, overdue items
- **Queue table**: Sortable table with identifier, title, status, priority, assignee, and action buttons

### Status Transitions

| Current Status | Available Actions |
|---------------|-------------------|
| Draft | Submit for review |
| Submitted | Begin review, Return for revision |
| Under Review | Accept, Reject, Return for revision |
| Returned | Resubmit |
| Accepted | (Final state) |
| Rejected | (Final state) |

### Assigning Accessions

Click the **user icon** in the actions column to assign an accession to a staff member. The assignee will appear in the queue table and timeline.

---

## 4. Checklists

### Intake Checklists

Each accession can have a configurable checklist of items that must be completed before submission. Checklists are populated from reusable templates.

### Using Checklists

1. Open an accession's intake detail page
2. Go to the **Checklist** tab
3. Click checkboxes to toggle completion (saves automatically)
4. View progress bar showing completion percentage

### Applying Templates

Select a template from the dropdown and click **Apply Template** to populate the checklist. This replaces any existing checklist items.

### Default Template

A default "Standard Intake" template is provided with common items:
- Donor agreement signed
- Preliminary inventory completed
- Physical condition assessed
- Restrictions identified
- Storage location assigned
- Accession number assigned

---

## 5. Attachments

### Uploading Files

1. Open an accession's intake detail page
2. Go to the **Attachments** tab
3. Select a file, choose a category, and click **Upload**

### Categories

- General
- Deed of Gift
- Photo
- Correspondence
- Inventory
- Other

### Storage

Files are stored on the filesystem at:
```
uploads/accessions/{tenant}/{accession_id}/
```

---

## 6. Timeline

The timeline provides a complete, immutable audit trail of every action taken on an accession. Events include:

- Created, Submitted, Assigned, Reviewed
- Accepted, Rejected, Returned
- Appraised, Containerized, Rights Assigned
- Notes and attachment uploads

Each event records the actor, timestamp, description, and optional metadata.

---

## 7. Appraisal

### Creating an Appraisal

1. Navigate to an accession and click **Appraisal**
2. Select an appraisal template (optional) to pre-populate criteria
3. Choose type, significance, and recommendation
4. Click **Create Appraisal**

### Scoring Criteria

When a template is applied, the scoring grid appears with:
- **Criterion name**: What is being evaluated
- **Weight**: Relative importance (0.00–1.00)
- **Score**: 1–5 radio buttons (click to score)
- **Weighted score**: Automatically calculated

Scores update in real-time via AJAX. The weighted average is displayed in the sidebar.

### Appraisal Types

| Type | Description |
|------|-------------|
| Archival | Standard archival value assessment |
| Monetary | Financial value assessment |
| Insurance | Insurance valuation |
| Historical | Historical significance assessment |
| Research | Research potential assessment |

### Recommendations

| Recommendation | Meaning |
|---------------|---------|
| Pending | Not yet decided |
| Accept | Recommend acceptance |
| Reject | Recommend rejection |
| Partial | Accept portion of accession |
| Defer | Defer decision |

---

## 8. Valuation

### Recording Valuations

Navigate to an accession and click **Valuation** to:
1. View the current (most recent) valuation
2. See the full valuation history
3. Add a new valuation entry

### Valuation Types

| Type | Use Case |
|------|----------|
| Initial | First valuation at accession |
| Revaluation | Periodic reassessment |
| Impairment | Value reduction (damage, loss) |
| Disposal | Value at deaccession/disposal |

### Valuation Methods

- Cost, Market, Income, Replacement, Nominal

### Portfolio Report

The **Valuation Report** (accessible from the browse page) shows:
- Total portfolio value by currency
- Number of valued accessions
- Recent valuations with links to accession records

---

## 9. Containers

### Adding Containers

1. Navigate to an accession and click **Containers**
2. Click **Add Container**
3. Fill in type, label, barcode, location, dimensions, weight, condition
4. Click **Save Container**

### Container Types

Box, Folder, Envelope, Crate, Tube, Flat File, Digital Media, Other

### Managing Items

Expand a container to view and manage its items. Each item has:
- Title, quantity, format, date range
- Optional link to an information object (created during arrangement)

### Barcode Lookup

Enter a barcode in the lookup field to find its container across all accessions.

---

## 10. Rights Management

### Adding Rights

1. Navigate to an accession and click **Rights**
2. Click **Add Right**
3. Select rights basis, holder, dates, restriction type, conditions
4. Toggle **Inherit to children** if rights should cascade to linked IOs

### Rights Inheritance

Click **Inherit to IOs** on a right to push it to all information objects linked to this accession (via container items or the relation table). Inheritance is tracked in the `accession_rights_inherited` table.

### Rights Basis Options

Copyright, License, Statute, Policy, Donor, Other

### Restriction Types

None, Restricted, Conditional, Closed, Partial

---

## 11. Numbering

### Auto-Numbering

When enabled, accession numbers are automatically generated using a configurable mask pattern:

| Token | Description | Example |
|-------|-------------|---------|
| {YEAR} | Current 4-digit year | 2026 |
| {MONTH} | Current 2-digit month | 02 |
| {DAY} | Current 2-digit day | 28 |
| {SEQ:n} | Zero-padded sequence (n digits) | 00042 |
| {REPO} | Repository identifier code | PSIS |

**Example mask:** `{YEAR}-{SEQ:5}` produces `2026-00001`, `2026-00002`, etc.

Sequences reset annually and are tracked per-repository.

---

## 12. Configuration

Navigate to **Admin > AHG Settings > Accession Management** to configure:

| Setting | Description |
|---------|-------------|
| Numbering Mask | Pattern for auto-generated accession numbers |
| Auto-Assign | Automatically assign new accessions |
| Require Donor Agreement | Enforce donor agreement before submission |
| Require Appraisal | Require appraisal before acceptance |
| Default Priority | Default priority for new accessions |
| Default Checklist Template | Template applied to new accessions |
| Default Appraisal Template | Template applied to new appraisals |
| Allow Container Barcodes | Enable barcode field on containers |
| Rights Inheritance | Enable rights inheritance to child IOs |

---

## 13. CLI Commands

### Intake Management

```bash
# View the intake queue
php symfony accession:intake --queue

# Filter by status
php symfony accession:intake --queue --status=submitted

# View statistics
php symfony accession:intake --stats

# Assign accession 123 to user 5
php symfony accession:intake --assign=123 --user=5

# Accept accession
php symfony accession:intake --accept=123

# Reject accession
php symfony accession:intake --reject=123 --reason="Incomplete documentation"

# View checklist
php symfony accession:intake --checklist=123

# View timeline
php symfony accession:intake --timeline=123
```

### Reporting

```bash
# Status summary
php symfony accession:report --status

# Portfolio valuation report
php symfony accession:report --valuation

# Export to CSV
php symfony accession:report --export-csv

# Export with date filter
php symfony accession:report --export-csv --date-from=2024-01-01 --date-to=2025-12-31

# Export for specific repository
php symfony accession:report --export-csv --repository=12345
```

---

## 14. Troubleshooting

| Issue | Solution |
|-------|----------|
| Queue shows no accessions | Run `install.sql` to create V2 tables. Existing accessions won't have V2 records until opened or created anew. |
| Checklist not appearing | Apply a checklist template from the dropdown on the checklist tab. |
| Cannot submit accession | Check that the current status is "draft" or "returned". |
| Rights inheritance shows 0 | Ensure container items are linked to information objects first. |
| Numbering not working | Check that `auto_assign_enabled` is set to `1` in configuration. |
| Settings section not visible | Ensure ahgAccessionManagePlugin is enabled in the plugin manager. |

---

*The Archive and Heritage Group (Pty) Ltd*
*https://theahg.co.za*
