<?php

/**
 * EmailSuppressionGate
 *
 * Phase 2 of #674. Single helper for "should I dispatch mail to this
 * address?" - call this immediately before Mail::to($addr)->queue($mailable)
 * to honour the bounce list.
 *
 * Suppression sources (any one trips the gate):
 *   - user.email_bounced_at is non-null (hard bounce or auto-promoted soft)
 *   - ahg_email_bounce has any 'complaint' row for the address within the
 *     last 12 months (we don't track unsubscribe explicitly; complaint
 *     is the strongest signal we have)
 *
 * Returns false (allow) when:
 *   - the tables aren't installed yet (fresh dev box)
 *   - the address isn't a known user AND has no bounce log entries
 *
 * Usage:
 *   if (! EmailSuppressionGate::isSuppressed($email)) {
 *       Mail::to($email)->queue($mailable);
 *   }
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Services;

use App\Events\MailSuppressed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

class EmailSuppressionGate
{
    /**
     * Phase 3 of #674. Audit-friendly wrapper around isSuppressed() -
     * when the gate trips, emit a MailSuppressed event so EmailAuditListener
     * can record a status=suppressed row in ahg_sent_email. Callers that
     * need the audit trail (every Phase-2 Mailable dispatch site) should
     * use this in preference to isSuppressed() directly.
     *
     * Returns true when the dispatch should proceed, false when blocked.
     *
     * Usage:
     *   if (EmailSuppressionGate::canSend($email, MyMail::class, $subject)) {
     *       Mail::to($email)->queue($mail);
     *   }
     */
    public static function canSend(?string $email, ?string $mailableClass = null, ?string $subject = null, ?int $tenantId = null): bool
    {
        if (self::isSuppressed($email)) {
            try {
                Event::dispatch(new MailSuppressed(
                    recipientEmail: (string) $email,
                    mailableClass: $mailableClass,
                    subject: $subject,
                    reason: 'bounce/complaint suppression list',
                    tenantId: $tenantId,
                    locale: (string) app()->getLocale(),
                ));
            } catch (\Throwable $e) {
                // never let audit failure block the caller
            }

            return false;
        }

        return true;
    }

    public static function isSuppressed(?string $email): bool
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return true;
        }

        // user.email_bounced_at gate
        if (Schema::hasTable('user') && Schema::hasColumn('user', 'email_bounced_at')) {
            try {
                $bounced = DB::table('user')
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->whereNotNull('email_bounced_at')
                    ->exists();
                if ($bounced) {
                    return true;
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        // recent complaint gate (12 months)
        if (Schema::hasTable('ahg_email_bounce')) {
            try {
                $cutoff = date('Y-m-d H:i:s', strtotime('-12 months'));
                $hasComplaint = DB::table('ahg_email_bounce')
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->where('bounce_type', 'complaint')
                    ->where('occurred_at', '>=', $cutoff)
                    ->exists();
                if ($hasComplaint) {
                    return true;
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return false;
    }

    /**
     * Clear a bounce hold (admin action). Use after the recipient has
     * confirmed the deliverability issue is fixed (e.g. mailbox quota
     * cleared, account reopened).
     */
    public static function clear(string $email): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }
        if (Schema::hasTable('user') && Schema::hasColumn('user', 'email_bounced_at')) {
            DB::table('user')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->update(['email_bounced_at' => null]);
        }
    }
}
