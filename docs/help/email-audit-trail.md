# Email audit trail

Heratio records every email attempt in the `ahg_sent_email` table so administrators can answer "did this person actually get that notification?" without trawling mail-server logs.

## What gets recorded

Every email Heratio dispatches lands in one of four states:

- **queued** - handed off to the mail driver; delivery still pending
- **sent** - the upstream provider accepted the message
- **failed** - the driver reported an error (transport, authentication, mailbox not found, etc.)
- **suppressed** - blocked at dispatch time because the recipient is on the bounce or complaint list

Each row captures the mailable class (e.g. `AhgWorkflow\Mail\WorkflowTaskOverdueMail`), recipient address, subject, locale, tenant, queue job id, and a correlation id that ties the queued row to the later sent/failed update.

## When is a message suppressed?

The `EmailSuppressionGate` checks two sources before allowing a send:

1. The recipient's `user.email_bounced_at` is non-null (hard bounce or auto-promoted soft bounce)
2. Any `complaint` row exists in `ahg_email_bounce` for that address within the last 12 months

If either trips, the dispatch is blocked and the audit table gets a `suppressed` row instead of a `queued` row. The actual mail driver is never invoked.

## Master toggle

The audit listener can be disabled in an emergency by setting `ahg_settings.email_audit_enabled` to `0`. Heratio still sends mail; it just stops writing to `ahg_sent_email`. Re-enable by flipping the value back to `1` (no restart required).

## Operator settings (AHG Settings -> Email)

| Key | Purpose |
|---|---|
| `email_audit_enabled` | Master toggle for the audit listener (default `1`) |
| `workflow_overdue_repeat_days` | How many days between nag emails for the same overdue task (default `7`) |
| `doi_failure_notify` | Comma-separated ops addresses to copy on DOI mint failures (and successes) |
| `sharepoint_ops_email` | Comma-separated ops addresses to alert when a SharePoint sync run fails |

## Nightly overdue sweep

A scheduled command (`workflow:notify-overdue`) runs daily at 09:00 and notifies assignees of workflow tasks past their due date. A task is re-nagged at most once per `workflow_overdue_repeat_days` window. To run it manually:

```
php artisan workflow:notify-overdue --dry-run
php artisan workflow:notify-overdue --repeat-days=14
```

## Common queries

How many emails went out in the last 24 hours?

```sql
SELECT status, COUNT(*) FROM ahg_sent_email
WHERE queued_at >= NOW() - INTERVAL 1 DAY
GROUP BY status;
```

What did Heratio try to send to a specific user?

```sql
SELECT queued_at, status, mailable_class, subject
FROM ahg_sent_email
WHERE recipient_email = 'jane@example.org'
ORDER BY queued_at DESC LIMIT 50;
```

Which mailables have the most failures this month?

```sql
SELECT mailable_class, COUNT(*) FROM ahg_sent_email
WHERE status = 'failed' AND queued_at >= NOW() - INTERVAL 30 DAY
GROUP BY mailable_class ORDER BY 2 DESC;
```

## Clearing a suppression

Once a deliverability issue is fixed (mailbox quota cleared, account reopened) lift the hold via the artisan tinker shell:

```
php artisan tinker
> App\Services\EmailSuppressionGate::clear('jane@example.org');
```

The next dispatch will be queued normally and recorded as `queued` -> `sent` (or whatever the driver returns).
