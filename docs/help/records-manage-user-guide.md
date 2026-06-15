> Heratio Help Center article. Category: Records Management.

# Records Management User Guide

Heratio's Records Management module governs the full lifecycle of records: a hierarchical file plan, retention schedules and disposal classes, a controlled disposal workflow with recommend / approve / legal-hold / execute / verify stages, periodic review queues, email capture, classification rules, and standards-based compliance assessments. Everything lives under one admin landing page at `/admin/records` and every screen uses the Bootstrap 5 admin theme. The module is disablable: when the `ahg/records-manage` package is not installed, none of these routes are registered.

## Overview

Records Management (RM) sits alongside the archival description layer and adds the apparatus an organisation needs to manage records as evidence over time. It does not replace archival description; instead it links retention rules, disposal decisions, and compliance evidence to the information objects already held in Heratio.

The module is built around eight feature areas, each reachable from the dashboard at `/admin/records`:

- **File plan** - a hierarchical classification structure (function / activity / series / sub-series / class).
- **Retention schedules** - versioned, approvable schedules that group disposal classes.
- **Disposal classes** - the retention rule attached to a part of the file plan.
- **Disposal workflow** - the multi-stage process that takes a record from "pending review" to "verified destroyed/transferred".
- **Reviews** - a queue of records due for periodic review with recorded decisions.
- **Email capture** - ingesting emails as records.
- **Compliance** - standards-based assessments (ISO 15489, ISO 16175, MoReq2010, DoD 5015.2, ISO 30300, ISO 23081).
- **Classification rules** - an engine that auto-assigns records to disposal classes.

All routes are protected by the `admin` middleware, so the whole module is staff-only.

The module is jurisdiction-neutral. Retention schedules carry their own `authority` and `jurisdiction` fields so you can model any national or organisational mandate; the controlled vocabularies (disposal actions, retention triggers, compliance frameworks) are seeded as dropdowns and can be extended through the Dropdown Manager.

## Key features

### File plan
- Hierarchical tree of nodes typed as **Function**, **Activity**, **Series**, **Sub-series**, or **Class** (the `rm_node_type` taxonomy).
- Each node carries a code, title, description, an optional link to a function/information object, an attached disposal class, a retention period, a disposal action, source department / agency code, and a nested-set position (lft / rgt / depth) for tree ordering.
- Nodes can be created, viewed, edited, moved within the tree, and deleted.
- A bulk **import wizard** (upload -> map columns -> preview -> commit) loads an external file plan and can link existing records to the imported structure.

### Retention schedules and disposal classes
- Schedules have a reference, title, description, issuing **authority**, **jurisdiction**, effective / review / expiry dates, a version number with a link to the previous version, and a status (`draft`, `approved`, `effective` / in force, `superseded`, `withdrawn`).
- A schedule must be **approved** before it governs disposal; approval records who approved it and when.
- Disposal classes belong to a schedule and define the retention period, the **retention trigger** (when the clock starts - creation date, file-closure date, last action, project end, end of employment, or a manual event), and the **disposal action** at end of life (destroy, transfer to archives, retain permanently, review, or transfer to an external custodian).
- Records (information objects) are assigned to a disposal class; you can also look up the class currently applied to any record.

### Disposal workflow
A controlled, auditable progression. The statuses (`rm_disposal_status`) run `pending` -> `recommended` -> `approved` -> `executed` -> `verified`, with `legal_hold`, `rejected`, and `cancelled` as side states. The matching actions are:
- **Initiate** a disposal action against a record.
- **Recommend** it for disposal.
- **Approve** the recommendation.
- **Clear legal** (lift a legal hold).
- **Reject** the action.
- **Execute** the disposal (destroy / transfer).
- **Verify** the executed disposal, with a destruction-certificate step.
- A **disposal queue** shows everything in flight and a **history** view shows completed actions.

### Reviews
- A review queue lists records due for periodic review.
- Reviews can be assigned to a user and completed with a recorded decision (`rm_review_decision`): retain and extend, retain and schedule next review, trigger disposal, transfer to archives, or no change.

### Email capture
- Capture emails as records from IMAP polling, an SMTP dropbox, or by uploading `.eml` / `.msg` files (`rm_email_capture_source`).
- Captured emails are listed, viewed, classified against the file plan, and formally **declared** as records.

### Compliance assessments
- Run an assessment against a recognised framework: ISO 15489, ISO 16175, MoReq2010, DoD 5015.2, ISO 30300, or ISO 23081 (`rm_compliance_framework`).
- An assessment carries a reference, title, scope, reporting period, a score (total / max), findings and recommendations, a status, sign-off details, and an optional generated PDF report.
- You can **run checks** to populate findings and **finalize** the assessment.

### Classification rules engine
- Define rules that auto-assign records to disposal classes. Rule types (`rm_classification_rule_type`) match on folder path, workspace, tag, MIME type, metadata, or fall back by department.
- Rules can be tested against a single record, applied to one information object, or run as a batch across the catalogue. Outcomes are written to a classification log.

## How to use

### Open the module
1. Sign in as a staff user with admin rights.
2. Go to **Admin -> Records** (`/admin/records`). This is the single landing page; every feature below is linked from here.

### Build a file plan
1. Go to `/admin/records/fileplan`.
2. Click **Create** (`/admin/records/fileplan/create`) and choose a node type (function / activity / series / sub-series / class), code, and title.
3. Open a node to view it (`/admin/records/fileplan/{id}`), edit it, or use **Move** to reposition it in the tree.
4. To load an existing plan in bulk, choose **Import** (`/admin/records/fileplan/import`): upload the file, map the columns, preview the result, then commit. After import you can link existing records to the new nodes.

### Create and approve a retention schedule
1. Go to `/admin/records/schedules` and click **Create** (`/admin/records/schedules/create`).
2. Enter the reference, title, authority, jurisdiction, and the effective / review / expiry dates. New schedules start in **draft**.
3. Open the schedule (`/admin/records/schedules/{id}`) and add **disposal classes** to it (`.../classes/create`): set the retention period, retention trigger, and disposal action for each.
4. When the schedule is ready, use **Approve** to move it into force. Only approved schedules govern disposal.

### Assign records to a disposal class
1. From a schedule or the records screens, assign an information object to a disposal class (`POST /admin/records/assign-class`).
2. To see what class a record currently has, open `/admin/records/record-class/{ioId}`.

### Run a disposal action
1. Start from a record and choose **Initiate disposal** (`/admin/records/disposal/initiate/{ioId}`), or work the **queue** at `/admin/records/disposal/queue`.
2. Move the action through the stages using the buttons on its detail page (`/admin/records/disposal/{id}`): **Recommend**, then **Approve**.
3. If a record is under legal hold, use **Clear legal** before approval; use **Reject** to stop an action.
4. Use **Execute** to carry out the destroy / transfer, then **Verify** (`/admin/records/disposal/{id}/verify`) to confirm completion and capture the destruction certificate.
5. Review completed actions under **History** (`/admin/records/disposal/history`).

### Work the review queue
1. Go to `/admin/records/reviews`.
2. Open a review (`/admin/records/reviews/{id}`) and optionally **Assign** it to a user.
3. **Complete** the review by recording a decision (extend retention, schedule next review, dispose, transfer, or no change).

### Capture emails as records
1. Go to `/admin/records/emails`.
2. To bring in a file manually, use **Upload** (`/admin/records/emails/upload`) and supply an `.eml` or `.msg` file. IMAP polling and an SMTP dropbox are the other supported sources.
3. Open a captured email (`/admin/records/emails/{id}`), **Classify** it against the file plan, then **Declare** it as a record.

### Run a compliance assessment
1. Go to `/admin/records/compliance` and click **Create** (`/admin/records/compliance/create`).
2. Pick a framework and give the assessment a reference, scope, and reporting period.
3. Open the assessment (`/admin/records/compliance/{id}`), use **Run checks** to gather findings, then **Finalize** to lock it and capture sign-off.

### Automate classification
1. Go to `/admin/records/classification` and **Create** a rule (`/admin/records/classification/create`): choose a rule type (folder path, workspace, tag, MIME type, metadata, or department fallback) and the disposal class it assigns.
2. **Test** a rule against a record (`/admin/records/classification/{id}/test`) before relying on it.
3. Apply classification to a single record (`/admin/records/classification/classify-io/{ioId}`) or run a **batch** across the catalogue (`/admin/records/classification/run-batch`). Results are written to the classification log.

## Configuration

- **Access control:** every route is behind the `admin` middleware. Grant a user admin rights to use the module.
- **Controlled vocabularies:** the seeded dropdown taxonomies are `rm_disposal_action`, `rm_retention_trigger`, `rm_schedule_status`, `rm_disposal_status`, `rm_node_type`, `rm_review_decision`, `rm_compliance_framework`, `rm_classification_rule_type`, and `rm_email_capture_source`. Extend or relabel them in the Dropdown Manager (`/admin/dropdowns`) - do not hardcode values.
- **Enable / disable:** the module is a disablable package (`ahg/records-manage`). It depends on `ahg/integrity` for the integrity / certificate support used by disposal verification. When the package is absent its tables and routes are not present.
- **Tables:** the module owns `rm_retention_schedule`, `rm_disposal_class`, `rm_record_disposal_class`, `rm_disposal_action`, `rm_review_schedule`, `rm_fileplan_node`, `rm_fileplan_import_session`, `rm_email_capture`, `rm_compliance_assessment`, `rm_classification_rule`, and `rm_classification_log`. They are created idempotently (CREATE TABLE IF NOT EXISTS) on first boot when the schedule table is missing.
- **Jurisdiction:** retention schedules carry their own `authority` and `jurisdiction` fields, so the module is not tied to any single regulatory regime.

## References

- Source: packages/ahg-records-manage/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/614
