# ahgWorkflowPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Workflow Management
**Dependencies:** atom-framework, ahgSecurityClearancePlugin

---

## Overview

Configurable approval workflow system for archival submissions, inspired by DSpace's workflow architecture. Supports multi-step review processes, role-based task pools, email notifications, and comprehensive audit trails.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                      ahgWorkflowPlugin                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                    Workflow Definition                        │ │
│  │                                                               │ │
│  │   ahg_workflow ─────────▶ ahg_workflow_step                   │ │
│  │   (workflow config)       (ordered steps)                     │ │
│  │                              │                                │ │
│  │                              │ role_id                        │ │
│  │                              ▼                                │ │
│  │                    acl_permission (AtoM roles)                │ │
│  │                                                               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│                              ▼                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                    Task Management                            │ │
│  │                                                               │ │
│  │   ahg_workflow_task ◀────────────────────────────────────────│ │
│  │   │ object_id (information_object)                           │ │
│  │   │ workflow_id                                               │ │
│  │   │ current_step                                              │ │
│  │   │ claimed_by (user_id)                                      │ │
│  │   │ status (pending/claimed/in_progress/approved/rejected)   │ │
│  │   │                                                           │ │
│  │   └──────▶ ahg_workflow_history                               │ │
│  │            (audit trail of all actions)                       │ │
│  │                                                               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│                              ▼                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                    Notifications                              │ │
│  │                                                               │ │
│  │   ahg_workflow_notification                                   │ │
│  │   │ task_id                                                   │ │
│  │   │ user_id                                                   │ │
│  │   │ notification_type                                         │ │
│  │   │ status (pending/sent/failed)                              │ │
│  │   │                                                           │ │
│  │   └──────▶ Email Queue ──────▶ SMTP                           │ │
│  │                                                               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_a0aa1d1b.png)
```

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────────┐
│            ahg_workflow                 │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│    name VARCHAR(255) NOT NULL           │
│    description TEXT                     │
│    is_active TINYINT(1) DEFAULT 1       │
│ FK repository_id INT NULL               │
│    scope ENUM('global','repository')    │
│    created_at TIMESTAMP                 │
│    updated_at TIMESTAMP                 │
├─────────────────────────────────────────┤
│ IDX idx_workflow_active (is_active)     │
│ IDX idx_workflow_repo (repository_id)   │
└───────────────────┬─────────────────────┘
                    │
                    │ 1:N
                    ▼
┌─────────────────────────────────────────┐
│          ahg_workflow_step              │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│ FK workflow_id BIGINT UNSIGNED NOT NULL │
│    name VARCHAR(255) NOT NULL           │
│    step_order INT NOT NULL              │
│ FK role_id INT NOT NULL                 │
│    instructions TEXT                    │
│    due_days INT DEFAULT 7               │
│    allow_reject TINYINT(1) DEFAULT 1    │
│    allow_return TINYINT(1) DEFAULT 1    │
│    created_at TIMESTAMP                 │
├─────────────────────────────────────────┤
│ UNIQUE KEY (workflow_id, step_order)    │
│ FK REFERENCES ahg_workflow(id) CASCADE  │
└───────────────────┬─────────────────────┘
                    │
                    │ 1:N
                    ▼
┌─────────────────────────────────────────┐
│          ahg_workflow_task              │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│ FK workflow_id BIGINT UNSIGNED NOT NULL │
│ FK object_id INT NOT NULL               │
│    object_type VARCHAR(100) DEFAULT     │
│                'information_object'     │
│    current_step INT NOT NULL DEFAULT 1  │
│    status ENUM('pending','claimed',     │
│           'in_progress','approved',     │
│           'rejected','returned')        │
│ FK submitted_by INT NOT NULL            │
│ FK claimed_by INT NULL                  │
│    claimed_at TIMESTAMP NULL            │
│    due_date DATE NULL                   │
│    priority INT DEFAULT 0               │
│    notes TEXT                           │
│    created_at TIMESTAMP                 │
│    updated_at TIMESTAMP                 │
├─────────────────────────────────────────┤
│ IDX idx_task_status (status)            │
│ IDX idx_task_object (object_id)         │
│ IDX idx_task_claimed (claimed_by)       │
│ IDX idx_task_due (due_date)             │
│ FK REFERENCES ahg_workflow(id)          │
│ FK REFERENCES user(id)                  │
└───────────────────┬─────────────────────┘
                    │
                    │ 1:N
                    ▼
┌─────────────────────────────────────────┐
│        ahg_workflow_history             │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│ FK task_id BIGINT UNSIGNED NOT NULL     │
│    action ENUM('submitted','claimed',   │
│           'released','approved',        │
│           'rejected','returned',        │
│           'escalated','advanced',       │
│           'completed')                  │
│    from_step INT NULL                   │
│    to_step INT NULL                     │
│ FK performed_by INT NOT NULL            │
│    comments TEXT                        │
│    ip_address VARCHAR(45)               │
│    created_at TIMESTAMP                 │
├─────────────────────────────────────────┤
│ IDX idx_history_task (task_id)          │
│ FK REFERENCES ahg_workflow_task(id)     │
│ FK REFERENCES user(id)                  │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│      ahg_workflow_notification          │
├─────────────────────────────────────────┤
│ PK id BIGINT UNSIGNED AUTO_INCREMENT    │
│ FK task_id BIGINT UNSIGNED NOT NULL     │
│ FK user_id INT NOT NULL                 │
│    notification_type ENUM('new_task',   │
│           'task_claimed','approved',    │
│           'rejected','returned',        │
│           'reminder','escalation')      │
│    status ENUM('pending','sent',        │
│           'failed') DEFAULT 'pending'   │
│    sent_at TIMESTAMP NULL               │
│    error_message TEXT                   │
│    created_at TIMESTAMP                 │
├─────────────────────────────────────────┤
│ IDX idx_notif_status (status)           │
│ IDX idx_notif_user (user_id)            │
│ FK REFERENCES ahg_workflow_task(id)     │
└─────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_126ff6c3.png)
```

---

## Service Layer

### WorkflowService

**Location:** `lib/Services/WorkflowService.php`

```php
namespace AtomExtensions\Workflow;

use Illuminate\Database\Capsule\Manager as DB;

class WorkflowService
{
    /**
     * Get applicable workflow for an object
     *
     * @param int $objectId Information object ID
     * @return object|null Workflow configuration
     */
    public function getWorkflowForObject(int $objectId): ?object;

    /**
     * Submit object to workflow
     *
     * @param int $objectId Information object ID
     * @param int $userId Submitting user ID
     * @param int|null $workflowId Specific workflow (null = auto-detect)
     * @return object Created task
     */
    public function submitToWorkflow(
        int $objectId,
        int $userId,
        ?int $workflowId = null
    ): object;

    /**
     * Get available tasks for user's role
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @return array Tasks in pool
     */
    public function getAvailableTasks(int $userId, array $filters = []): array;

    /**
     * Claim a task
     *
     * @param int $taskId Task ID
     * @param int $userId User ID
     * @return bool Success
     */
    public function claimTask(int $taskId, int $userId): bool;

    /**
     * Release a claimed task back to pool
     *
     * @param int $taskId Task ID
     * @param int $userId User ID
     * @return bool Success
     */
    public function releaseTask(int $taskId, int $userId): bool;

    /**
     * Approve task (advance to next step)
     *
     * @param int $taskId Task ID
     * @param int $userId User ID
     * @param string|null $comments Optional comments
     * @return bool Success
     */
    public function approveTask(
        int $taskId,
        int $userId,
        ?string $comments = null
    ): bool;

    /**
     * Reject task (end workflow)
     *
     * @param int $taskId Task ID
     * @param int $userId User ID
     * @param string $reason Rejection reason (required)
     * @return bool Success
     */
    public function rejectTask(
        int $taskId,
        int $userId,
        string $reason
    ): bool;

    /**
     * Return task to submitter for revision
     *
     * @param int $taskId Task ID
     * @param int $userId User ID
     * @param string $reason Return reason (required)
     * @return bool Success
     */
    public function returnTask(
        int $taskId,
        int $userId,
        string $reason
    ): bool;

    /**
     * Get workflow statistics
     *
     * @return array Statistics summary
     */
    public function getStatistics(): array;

    /**
     * Process pending notifications
     *
     * @param int $limit Maximum to process
     * @return array Results [sent, failed]
     */
    public function processNotifications(int $limit = 100): array;

    /**
     * Escalate overdue tasks
     *
     * @return int Number escalated
     */
    public function escalateOverdueTasks(): int;
}
```

---

## Task State Machine

```
                              ┌─────────────┐
                              │  SUBMITTED  │
                              └──────┬──────┘
                                     │
                                     ▼
                              ┌─────────────┐
                    ┌─────────│   PENDING   │◀────────┐
                    │         └──────┬──────┘         │
                    │                │                │
                    │                │ claim          │ release
                    │                ▼                │
                    │         ┌─────────────┐         │
                    │         │   CLAIMED   │─────────┘
                    │         └──────┬──────┘
                    │                │
                    │                │ start work
                    │                ▼
                    │         ┌─────────────┐
                    │         │ IN_PROGRESS │
                    │         └──────┬──────┘
                    │                │
            ┌───────┼────────┬───────┴───────┐
            │       │        │               │
            ▼       │        ▼               ▼
     ┌──────────┐   │  ┌──────────┐   ┌──────────┐
     │ APPROVED │   │  │ REJECTED │   │ RETURNED │
     └────┬─────┘   │  └──────────┘   └─────┬────┘
          │         │                       │
          │         │                       │ resubmit
          │         └───────────────────────┘
          │
          │ more steps?
          │
    ┌─────┴─────┐
    │           │
    │ YES       │ NO
    ▼           ▼
┌─────────┐  ┌──────────┐
│ PENDING │  │ COMPLETED│
│(next)   │  │(publish) │
└─────────┘  └──────────┘
![wireframe](./images/wireframes/wireframe_de43027b.png)
```

---

## Configuration Class

**Location:** `config/ahgWorkflowPluginConfiguration.class.php`

```php
class ahgWorkflowPluginConfiguration extends sfPluginConfiguration
{
    public function initialize()
    {
        // Register routes
        $this->dispatcher->connect(
            'routing.load_configuration',
            [$this, 'addRoutes']
        );
    }

    public function addRoutes(sfEvent $event)
    {
        $routing = $event->getSubject();

        // Dashboard
        $routing->prependRoute('workflow_dashboard',
            new sfRoute('/workflow',
                ['module' => 'workflow', 'action' => 'dashboard']));

        // Task pool
        $routing->prependRoute('workflow_pool',
            new sfRoute('/workflow/pool',
                ['module' => 'workflow', 'action' => 'pool']));

        // My tasks
        $routing->prependRoute('workflow_my_tasks',
            new sfRoute('/workflow/my-tasks',
                ['module' => 'workflow', 'action' => 'myTasks']));

        // Task actions
        $routing->prependRoute('workflow_task',
            new sfRoute('/workflow/task/:id',
                ['module' => 'workflow', 'action' => 'task']));

        $routing->prependRoute('workflow_claim',
            new sfRoute('/workflow/claim/:id',
                ['module' => 'workflow', 'action' => 'claim']));

        $routing->prependRoute('workflow_review',
            new sfRoute('/workflow/review/:id',
                ['module' => 'workflow', 'action' => 'review']));

        // Admin
        $routing->prependRoute('workflow_admin',
            new sfRoute('/workflow/admin',
                ['module' => 'workflow', 'action' => 'admin']));

        $routing->prependRoute('workflow_edit',
            new sfRoute('/workflow/admin/edit/:id',
                ['module' => 'workflow', 'action' => 'edit']));

        $routing->prependRoute('workflow_steps',
            new sfRoute('/workflow/admin/steps/:id',
                ['module' => 'workflow', 'action' => 'steps']));
    }
}
```

---

## CLI Tasks

### workflow:process

**Location:** `lib/task/workflowProcessTask.class.php`

```bash
# Process all workflow operations
php symfony workflow:process

# Options
--notifications    # Only process pending notifications
--escalate         # Only escalate overdue tasks
--cleanup          # Clean up old completed tasks
--days=N           # Days to retain completed tasks (default: 90)
--limit=N          # Max items to process (default: 100)
```

### workflow:status

**Location:** `lib/task/workflowStatusTask.class.php`

```bash
# Show workflow status summary
php symfony workflow:status

# Options
--pending          # Show only pending tasks
--overdue          # Show only overdue tasks
--format=FORMAT    # Output format: table, json, csv
```

---

## Module Structure

```
modules/
└── workflow/
    ├── actions/
    │   └── actions.class.php
    │       ├── executeDashboard()     # Main dashboard
    │       ├── executePool()          # Task pool view
    │       ├── executeMyTasks()       # User's claimed tasks
    │       ├── executeTask()          # Single task view
    │       ├── executeClaim()         # Claim task action
    │       ├── executeRelease()       # Release task action
    │       ├── executeReview()        # Review/decision form
    │       ├── executeApprove()       # Approve action
    │       ├── executeReject()        # Reject action
    │       ├── executeReturn()        # Return action
    │       ├── executeAdmin()         # Admin dashboard
    │       ├── executeEdit()          # Edit workflow
    │       └── executeSteps()         # Manage steps
    │
    └── templates/
        ├── dashboardSuccess.php       # Main dashboard
        ├── poolSuccess.php            # Task pool
        ├── myTasksSuccess.php         # My tasks
        ├── taskSuccess.php            # Task detail
        ├── reviewSuccess.php          # Review form
        ├── adminSuccess.php           # Admin list
        ├── editSuccess.php            # Edit workflow
        ├── stepsSuccess.php           # Manage steps
        └── historySuccess.php         # Task history
```

---

## Security Integration

### Role-Based Access

The plugin integrates with ahgSecurityClearancePlugin for role-based task assignment:

```php
// Get users with role matching step requirement
$step = DB::table('ahg_workflow_step')
    ->where('id', $stepId)
    ->first();

$usersWithRole = DB::table('acl_permission')
    ->join('user', 'user.id', '=', 'acl_permission.user_id')
    ->where('acl_permission.group_id', $step->role_id)
    ->where('user.active', 1)
    ->get();
```

### Permission Checks

```php
// Check if user can claim task
public function canUserClaimTask(int $userId, int $taskId): bool
{
    $task = $this->getTask($taskId);
    $step = $this->getStep($task->workflow_id, $task->current_step);

    // Check user has required role
    $hasRole = DB::table('acl_permission')
        ->where('user_id', $userId)
        ->where('group_id', $step->role_id)
        ->exists();

    return $hasRole && $task->status === 'pending';
}
```

---

## Email Notifications

### Notification Types

| Type | Trigger | Recipients |
|------|---------|------------|
| `new_task` | Task enters pool | All users with step's role |
| `task_claimed` | Task claimed | Claimer (confirmation) |
| `approved` | Task approved | Original submitter |
| `rejected` | Task rejected | Original submitter |
| `returned` | Task returned | Original submitter |
| `reminder` | Task approaching due | Task owner |
| `escalation` | Task overdue | Supervisor + Admin |

### Email Template Structure

```php
// Email templates in lib/email/
WorkflowEmailService::class
├── sendNewTaskNotification()
├── sendClaimConfirmation()
├── sendApprovalNotification()
├── sendRejectionNotification()
├── sendReturnNotification()
├── sendReminderNotification()
└── sendEscalationNotification()
```

---

## API Endpoints

### REST Endpoints (if ahgAPIPlugin enabled)

```
GET    /api/workflow/tasks           # List available tasks
GET    /api/workflow/tasks/:id       # Get task details
POST   /api/workflow/tasks/:id/claim # Claim task
POST   /api/workflow/tasks/:id/release # Release task
POST   /api/workflow/tasks/:id/approve # Approve task
POST   /api/workflow/tasks/:id/reject  # Reject task
POST   /api/workflow/tasks/:id/return  # Return task
GET    /api/workflow/my-tasks        # Get user's tasks
GET    /api/workflow/statistics      # Get statistics
```

---

## Events

### Dispatched Events

```php
// Event: workflow.task.submitted
$this->dispatcher->notify(new sfEvent($this, 'workflow.task.submitted', [
    'task_id' => $taskId,
    'object_id' => $objectId,
    'user_id' => $userId,
]));

// Event: workflow.task.claimed
$this->dispatcher->notify(new sfEvent($this, 'workflow.task.claimed', [
    'task_id' => $taskId,
    'user_id' => $userId,
]));

// Event: workflow.task.approved
$this->dispatcher->notify(new sfEvent($this, 'workflow.task.approved', [
    'task_id' => $taskId,
    'user_id' => $userId,
    'final' => $isFinalStep,
]));

// Event: workflow.task.rejected
// Event: workflow.task.returned
// Event: workflow.task.completed
```

---

## Installation

### Database Migration

```bash
mysql -u root archive < plugins/ahgWorkflowPlugin/database/install.sql
```

### Enable Plugin

```bash
php bin/atom extension:enable ahgWorkflowPlugin
php symfony cc
```

### Configure Cron

```bash
# Add to crontab
*/15 * * * * cd /usr/share/nginx/archive && php symfony workflow:process
```

---

## Default Workflow

The installation creates a default "Standard Review Workflow":

```sql
-- Default workflow
INSERT INTO ahg_workflow (name, description, scope, is_active)
VALUES ('Standard Review Workflow',
        'Two-step review process with editor review and admin approval',
        'global', 1);

-- Step 1: Editor Review
INSERT INTO ahg_workflow_step (workflow_id, name, step_order, role_id, due_days)
VALUES (1, 'Initial Review', 1, 4, 7);  -- role 4 = Editor

-- Step 2: Admin Approval
INSERT INTO ahg_workflow_step (workflow_id, name, step_order, role_id, due_days)
VALUES (1, 'Final Approval', 2, 3, 3);  -- role 3 = Administrator
```

---

## Performance Considerations

### Indexes

```sql
-- Task queries
CREATE INDEX idx_task_status ON ahg_workflow_task(status);
CREATE INDEX idx_task_claimed ON ahg_workflow_task(claimed_by);
CREATE INDEX idx_task_due ON ahg_workflow_task(due_date);
CREATE INDEX idx_task_object ON ahg_workflow_task(object_id, object_type);

-- History queries
CREATE INDEX idx_history_task ON ahg_workflow_history(task_id);
CREATE INDEX idx_history_date ON ahg_workflow_history(created_at);

-- Notification processing
CREATE INDEX idx_notif_status ON ahg_workflow_notification(status);
```

### Query Optimization

```php
// Efficient pool query with role check
$tasks = DB::table('ahg_workflow_task as t')
    ->join('ahg_workflow_step as s', function($join) {
        $join->on('s.workflow_id', '=', 't.workflow_id')
             ->on('s.step_order', '=', 't.current_step');
    })
    ->join('acl_permission as p', 'p.group_id', '=', 's.role_id')
    ->where('t.status', 'pending')
    ->where('p.user_id', $userId)
    ->select('t.*')
    ->distinct()
    ->get();
```

---

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Tasks not appearing | Role mismatch | Verify user has step's required role |
| Notifications not sending | SMTP not configured | Configure AtoM SMTP settings |
| Escalation not working | Cron not running | Enable workflow:process cron job |
| Can't claim task | Already claimed | Task was claimed by another user |
| Workflow not found | Repository mismatch | Check workflow scope settings |

---

## Related Documentation

- [Workflow User Guide](../workflow-user-guide.md)
- [Security Clearance Plugin](ahgSecurityClearancePlugin.md)
- [Audit Trail Plugin](ahgAuditTrailPlugin.md)
