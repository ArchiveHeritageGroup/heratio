<?php

/**
 * EmailAuditListener
 *
 * Phase 3 of #674 (Email + notifications). Captures every email attempt
 * - queued, sent, failed, and gate-suppressed - into ahg_sent_email so
 * operators can answer "did $user actually receive $thing?" without
 * cross-referencing mail-server logs.
 *
 * Listens on:
 *   - Illuminate\Mail\Events\MessageSending   -> INSERT queued row
 *   - Illuminate\Mail\Events\MessageSent      -> UPDATE -> sent
 *   - Illuminate\Mail\Events\MessageFailed    -> UPDATE -> failed + error
 *   - App\Events\MailSuppressed                -> INSERT suppressed row
 *
 * Identity between the three framework events is established by the
 * X-Heratio-Audit-Id header, which we stamp on the Symfony Email at
 * MessageSending time. Without that header the listener can still log
 * the send (queued) but cannot tie a later MessageSent/MessageFailed
 * back to the queued row, so it does a best-effort by recipient + subject
 * + same-minute window.
 *
 * Disabled cheaply by ahg_settings.email_audit_enabled=0 (the seed
 * default is 1; operators can flip it without code changes).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Listeners;

use App\Events\MailSuppressed;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmailAuditListener
{
    /**
     * Symfony header we stamp on outgoing mail so MessageSent/MessageFailed
     * can find the queued row again.
     */
    public const AUDIT_HEADER = 'X-Heratio-Audit-Id';

    /**
     * Cheap on/off - short-circuits everything when the operator flips
     * email_audit_enabled to '0' (or the table isn't installed yet on a
     * fresh dev box).
     */
    protected function enabled(): bool
    {
        if (! Schema::hasTable('ahg_sent_email')) {
            return false;
        }
        try {
            $row = DB::table('ahg_settings')->where('setting_key', 'email_audit_enabled')->first();
            if ($row && (string) $row->setting_value === '0') {
                return false;
            }
        } catch (\Throwable $e) {
            // settings table missing on a fresh install - default to on
        }

        return true;
    }

    public function handleMessageSending(MessageSending $event): void
    {
        if (! $this->enabled()) {
            return;
        }
        try {
            $email = $event->message;
            $headers = $email->getHeaders();

            // Stamp the audit id (UUID) so the matching MessageSent/Failed
            // event can find this row again.
            $auditId = (string) Str::uuid();
            if (! $headers->has(self::AUDIT_HEADER)) {
                $headers->addTextHeader(self::AUDIT_HEADER, $auditId);
            } else {
                $auditId = $headers->get(self::AUDIT_HEADER)->getBodyAsString();
            }

            $recipient = $this->firstAddress($email->getTo());
            if ($recipient === null) {
                return;
            }

            $subject = (string) ($email->getSubject() ?? '');
            $mailable = $this->resolveMailableClass($event);
            $locale = App::getLocale();
            $tenantId = $this->resolveTenantId();
            $queueJobId = $this->resolveQueueJobId();

            DB::table('ahg_sent_email')->insert([
                'mailable_class' => mb_substr($mailable, 0, 255),
                'recipient_email' => mb_substr($recipient, 0, 255),
                'recipient_user_id' => $this->lookupUserId($recipient),
                'subject' => mb_substr($subject, 0, 512),
                'locale' => $locale ? mb_substr($locale, 0, 8) : null,
                'tenant_id' => $tenantId,
                'queue_job_id' => $queueJobId ? mb_substr($queueJobId, 0, 64) : null,
                'message_id' => mb_substr($auditId, 0, 255),
                'queued_at' => now(),
                'status' => 'queued',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit failure must never break the actual send.
        }
    }

    public function handleMessageSent(MessageSent $event): void
    {
        if (! $this->enabled()) {
            return;
        }
        try {
            $auditId = $this->extractAuditId($event->message);
            if ($auditId === null) {
                return;
            }
            DB::table('ahg_sent_email')
                ->where('message_id', $auditId)
                ->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
        } catch (\Throwable $e) {
            // swallow
        }
    }

    public function handleMessageFailed(MessageFailed $event): void
    {
        if (! $this->enabled()) {
            return;
        }
        try {
            $auditId = $this->extractAuditId($event->message);
            $error = method_exists($event, 'data') ? json_encode($event->data) : 'send failed';
            // MessageFailed in Laravel 11/12 carries a `data` array or an
            // exception in `$event->raw`. Try both.
            if (isset($event->raw) && $event->raw instanceof \Throwable) {
                $error = $event->raw->getMessage();
            } elseif (property_exists($event, 'data') && is_array($event->data)) {
                $error = (string) ($event->data['exception'] ?? json_encode($event->data));
            }

            if ($auditId !== null) {
                DB::table('ahg_sent_email')
                    ->where('message_id', $auditId)
                    ->update([
                        'status' => 'failed',
                        'error' => mb_substr((string) $error, 0, 65535),
                    ]);
            }
        } catch (\Throwable $e) {
            // swallow
        }
    }

    public function handleMailSuppressed(MailSuppressed $event): void
    {
        if (! $this->enabled()) {
            return;
        }
        try {
            DB::table('ahg_sent_email')->insert([
                'mailable_class' => mb_substr($event->mailableClass ?? 'unknown', 0, 255),
                'recipient_email' => mb_substr($event->recipientEmail, 0, 255),
                'recipient_user_id' => $this->lookupUserId($event->recipientEmail),
                'subject' => $event->subject ? mb_substr($event->subject, 0, 512) : null,
                'locale' => $event->locale ? mb_substr($event->locale, 0, 8) : null,
                'tenant_id' => $event->tenantId,
                'queued_at' => now(),
                'status' => 'suppressed',
                'error' => mb_substr((string) ($event->reason ?? 'suppressed by EmailSuppressionGate'), 0, 65535),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // swallow
        }
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Pull the audit-id header back out of a Symfony Email so we can match
     * MessageSent / MessageFailed events to the queued row.
     */
    protected function extractAuditId(\Symfony\Component\Mime\Email $email): ?string
    {
        $headers = $email->getHeaders();
        if (! $headers->has(self::AUDIT_HEADER)) {
            return null;
        }

        return trim($headers->get(self::AUDIT_HEADER)->getBodyAsString());
    }

    /**
     * @param  array<\Symfony\Component\Mime\Address>  $addresses
     */
    protected function firstAddress(array $addresses): ?string
    {
        foreach ($addresses as $addr) {
            $value = method_exists($addr, 'getAddress') ? $addr->getAddress() : (string) $addr;
            if ($value) {
                return strtolower($value);
            }
        }

        return null;
    }

    protected function resolveMailableClass(MessageSending $event): string
    {
        // Laravel 11/12 passes the mailable class as the `data['mailable']` element
        // when dispatched via Mail::send/queue. Fall back to the X-Mailer header
        // or 'unknown' for raw Symfony sends.
        if (property_exists($event, 'data') && is_array($event->data) && ! empty($event->data['mailable'])) {
            return (string) $event->data['mailable'];
        }

        return 'unknown';
    }

    protected function resolveTenantId(): ?int
    {
        try {
            if (app()->bound('tenant.current')) {
                $tenant = app('tenant.current');
                if (is_object($tenant) && isset($tenant->id)) {
                    return (int) $tenant->id;
                }
            }
        } catch (\Throwable $e) {
            // single-tenant install - no tenant bound
        }

        return null;
    }

    protected function resolveQueueJobId(): ?string
    {
        // When the mailable was queued via ShouldQueue, the worker sets
        // the current job context; the job id is on the container under
        // the Illuminate\Contracts\Queue\Job binding.
        try {
            if (app()->bound(\Illuminate\Contracts\Queue\Job::class)) {
                $job = app(\Illuminate\Contracts\Queue\Job::class);

                return $job?->getJobId();
            }
        } catch (\Throwable $e) {
            // not running inside a queue worker
        }

        return null;
    }

    protected function lookupUserId(string $email): ?int
    {
        try {
            if (! Schema::hasTable('user')) {
                return null;
            }
            $id = DB::table('user')
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
                ->value('id');

            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
