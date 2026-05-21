> Heratio Help Center article. Category: Research / Compliance.

# Ethics Milestones

## Overview

Heratio's Ethics Milestones feature allows institutions to track ethics review requirements for research projects. Each research project can have one or more milestones that represent key compliance checkpoints, from initial application through final approval and completion.

Ethics milestones integrate with the broader research project management module and feed into the compliance dashboard for institutional oversight.

---

## Managing Ethics Milestones

Access ethics milestones from within a research project record:

1. Navigate to the research project
2. Click the **Ethics** tab
3. View existing milestones or click **Add Milestone** to create a new one

---

## Adding a Milestone

Click **Add Milestone** to open the milestone form:

| Field | Type | Description |
|-------|------|-------------|
| Title | Text | Short descriptive title for the milestone (e.g., "Initial Ethics Application", "Annual Review", "Final Report") |
| Description | Textarea | Detailed description of what this milestone requires and any supporting information |
| Due Date | Date picker | The deadline by which this milestone must be completed |
| Status | Dropdown | Current status of the milestone (see Status Workflow below) |
| Assigned To | Dropdown | Staff member responsible for this milestone |
| Attachments | File upload | Supporting documents (application forms, approval letters, correspondence) |
| Notes | Textarea | Internal notes visible to project team members |

Click **Save** to create the milestone. It will appear in the milestones list on the Ethics tab.

---

## Editing a Milestone

Click the **Edit** button on any milestone row to modify its fields. All fields are editable at any time. Changes are logged in the milestone history.

---

## Deleting a Milestone

Click the **Delete** button on a milestone row and confirm the deletion. Deleted milestones are permanently removed. This action is restricted to users with Editor or Administrator roles.

---

## Status Workflow

Each milestone follows a defined status workflow:

- **Pending** --- milestone created but not yet reviewed
- **Approved** --- ethics review body has approved this milestone
- **Rejected** --- ethics review body has rejected; revise and resubmit (returns to Pending)
- **Completed** --- all requirements fulfilled, no further action required

### Changing Status

To change a milestone's status:

1. Click **Edit** on the milestone
2. Select the new status from the dropdown
3. Optionally add a note explaining the status change
4. Click **Save**

Status changes are recorded with a timestamp and the user who made the change.

---

## Compliance Dashboard Integration

Ethics milestones feed into the institutional compliance dashboard, which provides:

- **Overview statistics** --- total milestones, pending, approved, rejected, completed counts
- **Overdue milestones** --- milestones past their due date that are not yet completed or approved
- **Upcoming deadlines** --- milestones due within the next 30 days
- **Project compliance status** --- per-project summary showing whether all ethics requirements are met
- **Export** --- download compliance reports as CSV or PDF

Access the compliance dashboard from **Admin > Research > Compliance Dashboard** or at `/research/compliance`.

### Dashboard Filters

- Filter by project, status, assigned user, or date range
- Sort by due date, status, or project name
- Search by milestone title or project title

---

## Notifications

Heratio sends notifications for ethics milestones:

| Event | Notification |
|-------|-------------|
| Milestone approaching due date (7 days) | Email to assigned user |
| Milestone overdue | Email to assigned user and project lead |
| Status changed to Approved | Email to project team |
| Status changed to Rejected | Email to assigned user with reviewer notes |

Notification preferences can be configured in **User Settings > Notifications**.

---

## Common Ethics Milestones

Typical milestones for a research project include:

| Milestone | Typical Due Date | Description |
|-----------|-----------------|-------------|
| Initial Ethics Application | Before research begins | Submit ethics application to review board |
| Ethics Approval | Before data collection | Receive formal approval from ethics committee |
| Informed Consent Verification | Before participant interaction | Confirm all consent forms are properly signed |
| Progress Report | Annually or as required | Submit progress report to ethics committee |
| Amendment Approval | As needed | Get approval for changes to research protocol |
| Adverse Event Report | Within 48 hours of event | Report any adverse events to ethics committee |
| Annual Review | Annually | Annual renewal of ethics approval |
| Final Ethics Report | At project completion | Submit final report and close ethics file |

---

## Permissions

| Action | Required Role |
|--------|---------------|
| View milestones | Authenticated user (project member) |
| Add milestones | Editor or above |
| Edit milestones | Editor or above (own milestones), Administrator (all) |
| Delete milestones | Editor or Administrator |
| View compliance dashboard | Administrator or Research Lead |

---

## Best Practices

- Create all expected milestones at the start of a research project
- Set realistic due dates that account for review board processing times
- Upload supporting documents directly to the milestone for easy reference
- Monitor the compliance dashboard regularly for overdue items
- Use notes to record reviewer feedback and revision history
- Ensure all team members understand the status workflow

---

*Part of the Heratio AHG Framework*
