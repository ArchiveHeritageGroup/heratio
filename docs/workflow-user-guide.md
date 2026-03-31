# Approval Workflow

## User Guide

Manage configurable approval workflows for archival submissions with role-based review, task claiming, and email notifications.

---

## Overview
```
┌─────────────────────────────────────────────────────────────────────┐
│                     APPROVAL WORKFLOW                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│    SUBMIT ──▶ REVIEW ──▶ APPROVE ──▶ PUBLISH                       │
│       │          │          │           │                           │
│       ▼          ▼          ▼           ▼                           │
│    Creator    Reviewer   Approver    System                         │
│    submits    claims &   gives       publishes                      │
│    record     reviews    approval    record                         │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Key Features
```
┌─────────────────────────────────────────────────────────────────────┐
│                      WORKFLOW FEATURES                              │
├─────────────────────────────────────────────────────────────────────┤
│  📋 CONFIGURABLE WORKFLOWS                                          │
│     • Create custom approval stages                                 │
│     • Assign roles to each step                                     │
│     • Set global or per-repository workflows                        │
│                                                                     │
│  👥 TASK POOL MODEL                                                 │
│     • Tasks go to role-based pool                                   │
│     • Reviewers claim tasks from pool                               │
│     • Prevents duplicate work                                       │
│                                                                     │
│  📧 EMAIL NOTIFICATIONS                                             │
│     • Automatic alerts for new tasks                                │
│     • Reminders for pending reviews                                 │
│     • Escalation for overdue items                                  │
│                                                                     │
│  📊 DASHBOARD & TRACKING                                            │
│     • View all pending tasks                                        │
│     • Track workflow progress                                       │
│     • Monitor team performance                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## How It Works

### Workflow Process Flow
```
                          ┌─────────────┐
                          │   Creator   │
                          │  Submits    │
                          │   Record    │
                          └──────┬──────┘
                                 │
                                 ▼
                    ┌────────────────────────┐
                    │   Task Created in      │
                    │   Review Pool          │
                    │   (Step 1)             │
                    └───────────┬────────────┘
                                │
          ┌─────────────────────┼─────────────────────┐
          │                     │                     │
          ▼                     ▼                     ▼
   ┌─────────────┐       ┌─────────────┐       ┌─────────────┐
   │  Reviewer A │       │  Reviewer B │       │  Reviewer C │
   │   (Editor)  │       │   (Editor)  │       │   (Editor)  │
   └──────┬──────┘       └─────────────┘       └─────────────┘
          │
          │ Claims Task
          ▼
   ┌─────────────────────────────────────────────────┐
   │                REVIEW STAGE                      │
   │                                                  │
   │   ┌─────────┐    ┌───────────┐    ┌──────────┐ │
   │   │ Approve │ OR │  Reject   │ OR │  Return  │ │
   │   └────┬────┘    └─────┬─────┘    └────┬─────┘ │
   └────────┼───────────────┼───────────────┼───────┘
            │               │               │
            ▼               ▼               ▼
     ┌────────────┐  ┌────────────┐  ┌────────────┐
     │ Next Step  │  │  Workflow  │  │  Return to │
     │ (Approve)  │  │  Rejected  │  │  Creator   │
     └──────┬─────┘  └────────────┘  └────────────┘
            │
            ▼
     ┌────────────────────────────────────────────┐
     │              APPROVAL STAGE                 │
     │                                             │
     │   Senior Reviewer claims and approves      │
     │                                             │
     └─────────────────────┬───────────────────────┘
                           │
                           ▼
                  ┌─────────────────┐
                  │    PUBLISHED    │
                  │    Record is    │
                  │    now public   │
                  └─────────────────┘
```

---

## How to Access

### Main Navigation
```
  Main Menu
      │
      ▼
   Admin
      │
      ▼
   Workflow ─────────────────────────────────────────┐
      │                                              │
      ├──▶ Dashboard      (your tasks & pending)     │
      │                                              │
      ├──▶ Task Pool      (available tasks)          │
      │                                              │
      ├──▶ My Tasks       (claimed tasks)            │
      │                                              │
      └──▶ Admin          (manage workflows)         │
```

---

## Dashboard

### Your Workflow Dashboard
```
┌─────────────────────────────────────────────────────────────────────┐
│                    WORKFLOW DASHBOARD                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐              │
│   │   Pending   │   │   Claimed   │   │  Completed  │              │
│   │     12      │   │      3      │   │     45      │              │
│   │   tasks     │   │    tasks    │   │   this week │              │
│   └─────────────┘   └─────────────┘   └─────────────┘              │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│   MY CLAIMED TASKS                                                  │
├─────────────────────────────────────────────────────────────────────┤
│   ┌────────┬─────────────────────┬───────────┬──────────┐          │
│   │ Status │ Record              │ Step      │ Due      │          │
│   ├────────┼─────────────────────┼───────────┼──────────┤          │
│   │ ⏳     │ Photo Collection A  │ Review    │ 2 days   │          │
│   │ ⏳     │ Maps Series 1920    │ Review    │ 5 days   │          │
│   │ 🔴     │ Letters Box 15      │ Review    │ OVERDUE  │          │
│   └────────┴─────────────────────┴───────────┴──────────┘          │
│                                                                     │
│   [View All Tasks]                                                  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Working with Tasks

### Step 1: View Available Tasks (Task Pool)

Go to **Workflow** → **Task Pool**

```
┌─────────────────────────────────────────────────────────────────────┐
│                      TASK POOL                                      │
├─────────────────────────────────────────────────────────────────────┤
│   Filter: [All Workflows ▼]  [All Steps ▼]  [All Repositories ▼]   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   ┌──────────────────────────────────────────────────────────────┐ │
│   │ 📋 Photo Collection - Smith Family                           │ │
│   │    Repository: City Archives                                 │ │
│   │    Workflow: Standard Review                                 │ │
│   │    Step: Initial Review                                      │ │
│   │    Submitted: 2 hours ago by J. Archivist                    │ │
│   │                                                              │ │
│   │    [Claim Task]  [View Record]                               │ │
│   └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
│   ┌──────────────────────────────────────────────────────────────┐ │
│   │ 📋 Council Minutes 1985-1990                                 │ │
│   │    Repository: Municipal Archives                            │ │
│   │    Workflow: Standard Review                                 │ │
│   │    Step: Initial Review                                      │ │
│   │    Submitted: 1 day ago by M. Cataloger                      │ │
│   │                                                              │ │
│   │    [Claim Task]  [View Record]                               │ │
│   └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Step 2: Claim a Task

Click **[Claim Task]** to assign the task to yourself.

```
   BEFORE CLAIMING                    AFTER CLAIMING
   ───────────────                    ──────────────

   Task in Pool ──────────────▶ Task in Your Queue
   (Anyone can see)              (Only you work on it)
```

### Step 3: Review the Record

After claiming, click **[Review]** to open the review interface:

```
┌─────────────────────────────────────────────────────────────────────┐
│                     REVIEW TASK                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   Record: Photo Collection - Smith Family                           │
│   Submitted by: J. Archivist                                        │
│   Workflow: Standard Review → Step: Initial Review                  │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   RECORD PREVIEW                                                    │
│   ┌─────────────────────────────────────────────────────────────┐  │
│   │  Title: Photo Collection - Smith Family                     │  │
│   │  Dates: 1920-1945                                           │  │
│   │  Extent: 3 boxes (150 photographs)                          │  │
│   │  Scope: Family photographs documenting...                   │  │
│   │                                                             │  │
│   │  [View Full Record]  [Edit Record]                          │  │
│   └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   YOUR DECISION:                                                    │
│                                                                     │
│   ┌───────────────┐  ┌───────────────┐  ┌───────────────┐          │
│   │   ✓ APPROVE   │  │   ✗ REJECT    │  │   ↩ RETURN    │          │
│   │               │  │               │  │               │          │
│   │  Send to next │  │  End workflow │  │  Send back to │          │
│   │  step         │  │  with reason  │  │  submitter    │          │
│   └───────────────┘  └───────────────┘  └───────────────┘          │
│                                                                     │
│   Comments: ________________________________________________        │
│             ________________________________________________        │
│                                                                     │
│   [Submit Decision]                                                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Step 4: Make Your Decision

| Decision | What Happens |
|----------|--------------|
| **Approve** | Task moves to the next workflow step |
| **Reject** | Workflow ends, submitter is notified |
| **Return** | Task goes back to submitter for corrections |

---

## Task Statuses
```
┌─────────────────────────────────────────────────────────────────────┐
│                      TASK STATUSES                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   🟡 PENDING      Task is in pool, waiting to be claimed            │
│                                                                     │
│   🔵 CLAIMED      Task has been claimed by a reviewer               │
│                                                                     │
│   🟢 IN_PROGRESS  Reviewer is actively working on task              │
│                                                                     │
│   ✅ APPROVED     Task was approved, moved to next step             │
│                                                                     │
│   ❌ REJECTED     Task was rejected, workflow ended                 │
│                                                                     │
│   ↩️  RETURNED     Task was returned to submitter                    │
│                                                                     │
│   🔴 OVERDUE      Task has exceeded its due date                    │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Email Notifications

### Notification Types
```
┌─────────────────────────────────────────────────────────────────────┐
│                    EMAIL NOTIFICATIONS                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   📩 NEW TASK AVAILABLE                                             │
│      Sent when: New task enters pool for your role                  │
│      To: All users with matching role                               │
│                                                                     │
│   📩 TASK ASSIGNED                                                  │
│      Sent when: Someone claims a task                               │
│      To: Task claimer (confirmation)                                │
│                                                                     │
│   📩 REVIEW COMPLETE                                                │
│      Sent when: Reviewer makes decision                             │
│      To: Original submitter                                         │
│                                                                     │
│   📩 TASK RETURNED                                                  │
│      Sent when: Task returned for corrections                       │
│      To: Original submitter                                         │
│                                                                     │
│   ⚠️  OVERDUE REMINDER                                               │
│      Sent when: Task exceeds due date                               │
│      To: Task owner + supervisor                                    │
│                                                                     │
│   🚨 ESCALATION NOTICE                                              │
│      Sent when: Task severely overdue                               │
│      To: Department head / Admin                                    │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Administration

### Creating a Workflow

Go to **Workflow** → **Admin** → **New Workflow**

```
┌─────────────────────────────────────────────────────────────────────┐
│                    CREATE NEW WORKFLOW                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   Workflow Name: ___________________________________                │
│                                                                     │
│   Description: _____________________________________                │
│                _____________________________________                │
│                                                                     │
│   Scope:  ○ Global (applies to all repositories)                    │
│           ○ Repository-specific                                     │
│                                                                     │
│   Repository: [Select Repository ▼]                                 │
│                                                                     │
│   Status:  ☑ Active                                                 │
│                                                                     │
│   [Save Workflow]                                                   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Adding Workflow Steps

```
┌─────────────────────────────────────────────────────────────────────┐
│                    WORKFLOW STEPS                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   Workflow: Standard Review Workflow                                │
│                                                                     │
│   ┌─────────────────────────────────────────────────────────────┐  │
│   │ Step 1: Initial Review                                      │  │
│   │ Role: Editor                                                │  │
│   │ Due: 7 days                                                 │  │
│   │ [Edit] [Delete]                                             │  │
│   └─────────────────────────────────────────────────────────────┘  │
│                           │                                         │
│                           ▼                                         │
│   ┌─────────────────────────────────────────────────────────────┐  │
│   │ Step 2: Final Approval                                      │  │
│   │ Role: Administrator                                         │  │
│   │ Due: 3 days                                                 │  │
│   │ [Edit] [Delete]                                             │  │
│   └─────────────────────────────────────────────────────────────┘  │
│                           │                                         │
│                           ▼                                         │
│                    ┌─────────────┐                                  │
│                    │  PUBLISHED  │                                  │
│                    └─────────────┘                                  │
│                                                                     │
│   [+ Add Step]                                                      │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Step Configuration
```
┌─────────────────────────────────────────────────────────────────────┐
│                    CONFIGURE STEP                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   Step Name: ___________________________________                    │
│                                                                     │
│   Order:     [2 ▼]   (sequence in workflow)                         │
│                                                                     │
│   Required Role: [Editor ▼]                                         │
│                  • Administrator                                    │
│                  • Editor                                           │
│                  • Contributor                                      │
│                                                                     │
│   Due Within:  [7] days                                             │
│                                                                     │
│   Instructions: ____________________________________                │
│                 ____________________________________                │
│                 (shown to reviewer)                                 │
│                                                                     │
│   Allow Actions:                                                    │
│     ☑ Approve (send to next step)                                   │
│     ☑ Reject (end workflow)                                         │
│     ☑ Return (send back for revision)                               │
│                                                                     │
│   [Save Step]                                                       │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Workflow History

### View Task History
```
┌─────────────────────────────────────────────────────────────────────┐
│                    WORKFLOW HISTORY                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   Record: Photo Collection - Smith Family                           │
│   Workflow: Standard Review                                         │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│   DATE/TIME         ACTION              USER           NOTES        │
├─────────────────────────────────────────────────────────────────────┤
│   10 Jan 09:15     Submitted           J. Archivist   Initial sub   │
│   10 Jan 10:30     Claimed             M. Editor      Step 1        │
│   10 Jan 14:45     Approved            M. Editor      Looks good    │
│   10 Jan 14:45     Advanced            System         → Step 2      │
│   11 Jan 09:00     Claimed             A. Admin       Step 2        │
│   11 Jan 11:30     Approved            A. Admin       Published     │
│   11 Jan 11:30     Completed           System         Record live   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## CLI Commands

### Process Workflow Tasks
```bash
# Process notifications and escalations
php symfony workflow:process

# Only send pending notifications
php symfony workflow:process --notifications

# Only escalate overdue tasks
php symfony workflow:process --escalate

# Cleanup old completed tasks
php symfony workflow:process --cleanup --days=90
```

### View Workflow Status
```bash
# Show summary statistics
php symfony workflow:status

# Show only pending tasks
php symfony workflow:status --pending

# Show only overdue tasks
php symfony workflow:status --overdue

# Output as JSON
php symfony workflow:status --format=json
```

---

## Best Practices

### For Reviewers
```
┌─────────────────────────────────────────────────────────────────────┐
│                    REVIEWER TIPS                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ✓ Claim only tasks you can complete promptly                       │
│                                                                     │
│  ✓ Add clear comments explaining your decision                      │
│                                                                     │
│  ✓ Use "Return" instead of "Reject" for fixable issues              │
│                                                                     │
│  ✓ Check your email for new task notifications                      │
│                                                                     │
│  ✓ Release unclaimed tasks if you can't complete them               │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### For Administrators
```
┌─────────────────────────────────────────────────────────────────────┐
│                    ADMIN TIPS                                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ✓ Keep workflows simple (2-3 steps maximum)                        │
│                                                                     │
│  ✓ Set realistic due dates for each step                            │
│                                                                     │
│  ✓ Ensure multiple users have required roles                        │
│                                                                     │
│  ✓ Monitor overdue tasks regularly                                  │
│                                                                     │
│  ✓ Run workflow:process via cron for automation                     │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Tasks not appearing | Check your user role matches the step's required role |
| No email notifications | Verify SMTP settings in AtoM configuration |
| Can't claim task | Another user may have claimed it - refresh the page |
| Workflow stuck | Check Admin panel for misconfigured steps |

---

## Related Features

- **Security Clearance** - Role-based access control
- **Audit Trail** - Track all workflow actions
- **Email Settings** - Configure notification delivery
