<?php

/**
 * WorkflowNotifyOverdueCommand
 *
 * Phase 3 of #674. Nightly sweep: find workflow tasks past their due_date
 * that aren't done/cancelled and either have never been nagged or were
 * nagged more than `ahg_settings.workflow_overdue_repeat_days` ago.
 * Dispatches WorkflowTaskOverdueMail to the task assignee, stamps
 * ahg_workflow_task.last_overdue_notification_at so we don't re-nag
 * tomorrow.
 *
 * Honours EmailSuppressionGate::canSend() so a bounced assignee won't
 * keep the queue worker busy. A skip still stamps last_overdue_notification_at
 * so the next sweep doesn't immediately retry - the bounce list will lift
 * naturally once the gate is cleared.
 *
 * Schedule: daily 09:00 (registered in AhgWorkflowServiceProvider).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgWorkflow\Console\Commands;

use AhgWorkflow\Mail\WorkflowTaskOverdueMail;
use App\Services\EmailSuppressionGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class WorkflowNotifyOverdueCommand extends Command
{
    protected $signature = 'workflow:notify-overdue
        {--repeat-days= : Override ahg_settings.workflow_overdue_repeat_days (default 7)}
        {--dry-run : Log dispatch targets but do not queue mail}
        {--limit=500 : Hard cap on tasks notified per run}';

    protected $description = 'Notify assignees of workflow tasks past due_date that have not been nagged in the configured repeat window.';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_workflow_task')) {
            $this->warn('ahg_workflow_task table not installed; nothing to do.');

            return self::SUCCESS;
        }
        if (! Schema::hasColumn('ahg_workflow_task', 'last_overdue_notification_at')) {
            $this->error('Migration for last_overdue_notification_at has not run. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $repeatDays = (int) ($this->option('repeat-days') ?: $this->readSetting('workflow_overdue_repeat_days', '7'));
        if ($repeatDays < 1) {
            $repeatDays = 7;
        }
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $cutoff = now()->subDays($repeatDays);
        $today = now()->toDateString();

        $rows = DB::table('ahg_workflow_task as t')
            ->leftJoin('ahg_workflow as w', 'w.id', '=', 't.workflow_id')
            ->whereNotIn('t.status', ['done', 'completed', 'cancelled', 'rejected'])
            ->whereNotNull('t.due_date')
            ->whereDate('t.due_date', '<', $today)
            ->whereNotNull('t.assigned_to')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('t.last_overdue_notification_at')
                    ->orWhere('t.last_overdue_notification_at', '<', $cutoff);
            })
            ->select(
                't.id as task_id',
                't.assigned_to',
                't.due_date',
                't.workflow_id',
                'w.name as workflow_name'
            )
            ->limit($limit)
            ->get();

        $this->info(sprintf(
            'Found %d overdue task(s) eligible for notification (repeat=%dd, limit=%d).',
            $rows->count(), $repeatDays, $limit
        ));

        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        $dispatched = 0;
        $suppressed = 0;
        $missingEmail = 0;

        foreach ($rows as $row) {
            $user = $this->loadUser((int) $row->assigned_to);
            if (! $user || empty($user->email)) {
                $missingEmail++;
                // Still stamp so we don't re-evaluate this orphan row every night.
                $this->stamp((int) $row->task_id);

                continue;
            }

            $overdueDays = (int) max(0, now()->diffInDays($row->due_date, false) * -1);
            $context = [
                'task_id' => (int) $row->task_id,
                'workflow_name' => (string) ($row->workflow_name ?? 'Workflow'),
                'assignee_name' => $this->displayName($user),
                'assignee_email' => (string) $user->email,
                'due_at' => (string) $row->due_date,
                'overdue_days' => $overdueDays,
                'task_url' => rtrim((string) config('app.url', ''), '/').'/admin/workflow/task/'.((int) $row->task_id),
                'preferred_locale' => $user->preferred_locale ?? null,
            ];

            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] task=%d -> %s (overdue %dd, workflow="%s")',
                    $row->task_id, $user->email, $overdueDays, $context['workflow_name']
                ));

                continue;
            }

            if (! EmailSuppressionGate::canSend(
                $user->email,
                WorkflowTaskOverdueMail::class,
                'Overdue: '.$context['workflow_name']
            )) {
                $suppressed++;
                $this->stamp((int) $row->task_id);

                continue;
            }

            try {
                Mail::to($user->email)->queue(new WorkflowTaskOverdueMail($context));
                $this->stamp((int) $row->task_id);
                $dispatched++;
            } catch (\Throwable $e) {
                $this->error(sprintf('  task=%d dispatch failed: %s', $row->task_id, $e->getMessage()));
            }
        }

        $this->info(sprintf(
            'Dispatched: %d, suppressed: %d, missing-email: %d.',
            $dispatched, $suppressed, $missingEmail
        ));

        return self::SUCCESS;
    }

    protected function loadUser(int $userId): ?object
    {
        if (! Schema::hasTable('user')) {
            return null;
        }

        return DB::table('user')->where('id', $userId)->first();
    }

    protected function displayName(object $user): string
    {
        foreach (['username', 'name', 'first_name', 'email'] as $col) {
            if (! empty($user->{$col})) {
                return (string) $user->{$col};
            }
        }

        return 'assignee';
    }

    protected function stamp(int $taskId): void
    {
        try {
            DB::table('ahg_workflow_task')
                ->where('id', $taskId)
                ->update(['last_overdue_notification_at' => now()]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function readSetting(string $key, string $default): string
    {
        try {
            if (! Schema::hasTable('ahg_settings')) {
                return $default;
            }
            $row = DB::table('ahg_settings')->where('setting_key', $key)->first();

            return $row ? (string) $row->setting_value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
