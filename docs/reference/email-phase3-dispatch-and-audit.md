# Email Phase 3 - Dispatch Wiring + Audit Trail

Phase 3 of issue #674 (Email and notifications) closes the loop between the four Phase-2 Mailables and an end-to-end audit trail of every message Heratio sends. As of v1.97.0 every email attempt is recorded in `ahg_sent_email` with one of four statuses: `queued`, `sent`, `failed`, or `suppressed`.

## Schema

`database/migrations/2026_05_25_030000_create_ahg_sent_email.php` adds:

- `ahg_sent_email` - per-message audit row. Indexed on recipient, status+queued_at, mailable_class, tenant, and message_id (the audit correlation id stamped on the Symfony email at dispatch time).
- `ahg_workflow_task.last_overdue_notification_at` - nag-suppression stamp for the nightly overdue sweep. Composite index `idx_overdue_sweep (status, due_date, last_overdue_notification_at)` so the WHERE clause stays an index scan.
- Seed defaults in `ahg_settings`:
  - `workflow_overdue_repeat_days` = `7`
  - `doi_failure_notify` = `` (operator sets a comma-separated ops list)
  - `sharepoint_ops_email` = `` (same; also reads `sharepoint_admin_email`)
  - `email_audit_enabled` = `1` (master kill switch for the audit listener)

## Audit listener

`app/Listeners/EmailAuditListener.php` subscribes to four events:

| Event | Source | Effect |
|---|---|---|
| `Illuminate\Mail\Events\MessageSending` | Framework, just before delivery | Stamps `X-Heratio-Audit-Id` header, inserts a `status=queued` row |
| `Illuminate\Mail\Events\MessageSent` | Framework, after successful delivery | Updates the row to `sent`, sets `sent_at` |
| `Illuminate\Mail\Events\MessageFailed` | Framework, on transport failure | Updates to `failed`, captures `error` |
| `App\Events\MailSuppressed` | `EmailSuppressionGate::canSend()` when the gate trips | Inserts a `status=suppressed` row |

Registration lives in `app/Providers/EventServiceProvider.php` and is wired through `bootstrap/providers.php`. The listener short-circuits when `ahg_settings.email_audit_enabled='0'` or the table isn't installed yet.

The `X-Heratio-Audit-Id` UUID header is the join key between MessageSending (insert) and MessageSent/Failed (update). Cases where Laravel internals don't propagate the header (rare) will leave an orphan queued row - acceptable because the actual send still happened and the operator can correlate by recipient + subject + minute.

## Suppression gate

`app/Services/EmailSuppressionGate.php` gained a `canSend()` wrapper. Existing callers using the raw `isSuppressed()` predicate still work, but new dispatch sites should prefer `canSend()` because it emits the `MailSuppressed` event for free:

```php
if (EmailSuppressionGate::canSend($email, MyMail::class, $subject)) {
    Mail::to($email)->queue($mailable);
}
```

## Dispatch sites wired in Phase 3

| Mailable | Trigger | Site |
|---|---|---|
| `WorkflowTaskOverdueMail` | Nightly sweep at 09:00 | `packages/ahg-workflow/src/Console/Commands/WorkflowNotifyOverdueCommand.php` (scheduled by `AhgWorkflowServiceProvider`) |
| `DoiMintedMail` | DataCite confirms a freshly-minted DOI | `DoiService::mint()` success path -> `dispatchMintedMail()` |
| `DoiFailedMail` | `DoiService::mint()` catch path | `dispatchFailedMail()` (sends to IO owner + `ahg_settings.doi_failure_notify`) |
| `SharePointSyncErrorMail` | `sharepoint:sync` catches a per-drive `Throwable` | `SharePointSyncCommand::dispatchSyncErrorMail()` (config -> `ahg_settings.sharepoint_ops_email` -> `sharepoint_admin_email`) |

All four sites route through `EmailSuppressionGate::canSend()` so a bounced recipient never burns the mail queue.

## Nightly workflow sweep

`workflow:notify-overdue` finds tasks where:

- `status NOT IN (done, completed, cancelled, rejected)`
- `due_date < CURDATE()`
- `assigned_to IS NOT NULL`
- `last_overdue_notification_at IS NULL OR < NOW() - INTERVAL repeat_days DAY`

It dispatches `WorkflowTaskOverdueMail` to the assignee, then stamps `last_overdue_notification_at` regardless of suppression outcome (so a bounce doesn't trigger a daily re-evaluation; the bounce list lift will surface the task again naturally).

CLI flags:
- `--repeat-days=N` - override the setting
- `--dry-run` - list targets, queue nothing
- `--limit=N` - cap per-run dispatch (default 500)

## Operator queries

How many emails actually went out yesterday?

```sql
SELECT status, COUNT(*) FROM ahg_sent_email
WHERE queued_at >= NOW() - INTERVAL 1 DAY GROUP BY status;
```

Who has the most bounces this month?

```sql
SELECT recipient_email, COUNT(*) AS n FROM ahg_sent_email
WHERE status IN ('failed','suppressed') AND queued_at >= NOW() - INTERVAL 30 DAY
GROUP BY recipient_email ORDER BY n DESC LIMIT 20;
```

Did the overdue sweep nag $user about $task?

```sql
SELECT * FROM ahg_sent_email
WHERE mailable_class = 'AhgWorkflow\\Mail\\WorkflowTaskOverdueMail'
AND recipient_email = 'jane@example.org'
ORDER BY queued_at DESC LIMIT 10;
```

## What Phase 4+ still needs

- Admin UI to browse `ahg_sent_email` (filter by status, mailable, recipient, date)
- Resend / re-queue button from the audit row
- Bounce-list lift workflow from the audit table (today it's only via `EmailSuppressionGate::clear()`)
- Per-tenant mailer routing (the `tenant_id` column is populated but no router consumes it yet)
- Email queue depth metric -> Prometheus exporter (would slot into `AhgObservability` from #677 Phase 3)
