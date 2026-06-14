<?php

/**
 * DonorRemindersCommand — dispatch due donor agreement reminders.
 *
 * Issue #1262. Previously this command marked reminders as sent and wrote
 * a reminder-log row with a hardcoded outcome, but never dispatched any
 * mail. It now actually sends a DonorAgreementReminderMail to the resolved
 * recipient(s) BEFORE flipping the reminder to sent, records the real
 * per-recipient outcome in donor_agreement_reminder_log, and leaves the
 * reminder due (status unchanged, is_sent=0) when delivery fails so the
 * next run retries.
 *
 * Recipient resolution (first non-empty wins):
 *   1. reminder.notification_recipients  — explicit comma/newline/semicolon
 *      separated email list set when the reminder was created.
 *   2. donor actor contact email          — contact_information.email for the
 *      agreement's actor_id (else donor_id), most-recent primary contact.
 *   3. ahg_settings.dp_notify_email / jobs_notify_email — internal staff
 *      fallback so a review-due reminder still reaches someone.
 *
 * Schema note: the live tables differ from the original command's
 * assumptions. donor_agreement_reminder uses reminder_date (not due_date)
 * and status active/snoozed/completed/cancelled (not pending/sent) plus an
 * is_sent flag; donor_agreement_reminder_log uses
 * donor_agreement_reminder_id / sent_to / notification_method / status /
 * error_message. This command targets the real columns.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use AhgCore\Mail\DonorAgreementReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class DonorRemindersCommand extends Command
{
    protected $signature = 'ahg:donor-reminders
        {--dry-run : Show reminders without sending or logging}';

    protected $description = 'Send due donor_agreement_reminder rows (reminder_date passed) by email and log each attempt to donor_agreement_reminder_log';

    public function handle(): int
    {
        if (! Schema::hasTable('donor_agreement_reminder')) {
            $this->warn('donor_agreement_reminder table not installed; nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $today = $now->toDateString();

        $due = DB::table('donor_agreement_reminder as r')
            ->leftJoin('donor_agreement as da', 'r.donor_agreement_id', '=', 'da.id')
            ->where('r.notify_email', 1)
            ->where('r.is_sent', 0)
            ->whereIn('r.status', ['active', 'snoozed'])
            ->whereDate('r.reminder_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('r.snooze_until')
                    ->orWhereDate('r.snooze_until', '<=', $today);
            })
            ->select([
                'r.*',
                'da.title as agreement_title',
                'da.agreement_number',
                'da.expiry_date',
                'da.actor_id',
                'da.donor_id',
            ])
            ->get();

        $this->info("due reminders: {$due->count()}".($dryRun ? ' (dry-run)' : ''));

        $sent = 0;
        $failed = 0;

        foreach ($due as $r) {
            try {
                $recipients = $this->resolveRecipients($r);

                if ($dryRun) {
                    $to = $recipients ? implode(', ', $recipients) : '(no recipient)';
                    $this->line("  would send agreement={$r->donor_agreement_id} type={$r->reminder_type} due={$r->reminder_date} -> {$to}");

                    continue;
                }

                if (empty($recipients)) {
                    $this->failReminder(
                        $r,
                        $now,
                        null,
                        'no recipient could be resolved (notification_recipients empty, no donor contact email, no staff fallback configured)'
                    );
                    $failed++;

                    continue;
                }

                $context = $this->buildContext($r);
                Mail::to($recipients)->send(new DonorAgreementReminderMail($context));

                DB::table('donor_agreement_reminder_log')->insert([
                    'donor_agreement_reminder_id' => $r->id,
                    'sent_at' => $now,
                    'sent_to' => implode(', ', $recipients),
                    'notification_method' => 'email',
                    'status' => 'sent',
                    'error_message' => null,
                    'created_at' => $now,
                ]);

                DB::table('donor_agreement_reminder')->where('id', $r->id)->update([
                    'status' => 'completed',
                    'is_sent' => 1,
                    'sent_at' => $now,
                ]);

                $sent++;
            } catch (\Throwable $e) {
                $this->failReminder($r, $now, $this->safeRecipients($r), $e->getMessage());
                $failed++;
            }
        }

        $this->info("sent={$sent} failed={$failed}".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    /**
     * Record a failed attempt without marking the reminder sent, so the next
     * run retries it. Logs to the application log for operator visibility.
     */
    private function failReminder(object $r, $now, ?array $recipients, string $error): void
    {
        Log::error('ahg:donor-reminders failed to send reminder', [
            'reminder_id' => $r->id,
            'donor_agreement_id' => $r->donor_agreement_id ?? null,
            'recipients' => $recipients,
            'error' => $error,
        ]);

        $this->error("  reminder={$r->id} agreement=".($r->donor_agreement_id ?? '?')." failed: {$error}");

        try {
            DB::table('donor_agreement_reminder_log')->insert([
                'donor_agreement_reminder_id' => $r->id,
                'sent_at' => $now,
                // sent_to is NOT NULL in the schema; use empty string when no
                // recipient could be resolved so the failure is still logged.
                'sent_to' => $recipients ? implode(', ', $recipients) : '',
                'notification_method' => 'email',
                'status' => 'failed',
                'error_message' => mb_substr($error, 0, 2000),
                'created_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // Logging the failure must never abort the batch.
            Log::error('ahg:donor-reminders could not write reminder log', [
                'reminder_id' => $r->id,
                'error' => $e->getMessage(),
            ]);
        }
        // Intentionally do NOT touch reminder.status / is_sent on failure.
    }

    private function safeRecipients(object $r): ?array
    {
        try {
            $rec = $this->resolveRecipients($r);

            return $rec ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildContext(object $r): array
    {
        return [
            'subject' => (string) ($r->subject ?? ''),
            'reminder_type' => (string) ($r->reminder_type ?? ''),
            'description' => $r->description ?? null,
            'agreement_title' => (string) ($r->agreement_title ?? ''),
            'agreement_number' => $r->agreement_number ?? null,
            'reminder_date' => $r->reminder_date ?? null,
            'expiry_date' => $r->expiry_date ?? null,
            'priority' => $r->priority ?? null,
        ];
    }

    /**
     * @return string[] de-duplicated, validated recipient email addresses
     */
    private function resolveRecipients(object $r): array
    {
        // 1. Explicit recipients on the reminder.
        $explicit = $this->parseEmails($r->notification_recipients ?? null);
        if (! empty($explicit)) {
            return $explicit;
        }

        // 2. Donor actor contact email.
        $actorId = $r->actor_id ?? $r->donor_id ?? null;
        if ($actorId && Schema::hasTable('contact_information')) {
            $contact = DB::table('contact_information')
                ->where('actor_id', $actorId)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->orderByDesc('primary_contact')
                ->orderByDesc('id')
                ->first();
            if ($contact && filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
                return [strtolower(trim($contact->email))];
            }
        }

        // 3. Internal staff fallback from settings.
        foreach (['dp_notify_email', 'jobs_notify_email'] as $key) {
            $staff = $this->parseEmails($this->readSetting($key));
            if (! empty($staff)) {
                return $staff;
            }
        }

        return [];
    }

    /**
     * @return string[] valid, lowercased, de-duplicated emails
     */
    private function parseEmails(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[,;\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = strtolower(trim($p));
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $out[$p] = $p;
            }
        }

        return array_values($out);
    }

    private function readSetting(string $key): ?string
    {
        try {
            if (! Schema::hasTable('ahg_settings')) {
                return null;
            }
            $row = DB::table('ahg_settings')->where('setting_key', $key)->first();

            return $row ? (string) $row->setting_value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
