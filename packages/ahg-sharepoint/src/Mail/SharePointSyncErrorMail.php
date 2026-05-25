<?php

/**
 * SharePointSyncErrorMail
 *
 * Phase 2 of #674. The audit table flagged "SharePoint sync error" as
 * having no email coverage. Sent to the SharePoint integration admin
 * (ahg_settings.sharepoint_admin_email or first super_user when unset)
 * when a sync job fails terminally or accumulates too many transient
 * errors in a row.
 *
 * Expected $context shape:
 *   - connection_name  (string)
 *   - site_url         (string|null)
 *   - error_kind       (string) auth | network | quota | conflict | other
 *   - error_message    (string)
 *   - failed_items     (int)
 *   - last_success_at  (string|null)
 *   - run_id           (string|null)
 *   - dashboard_url    (string|null)
 *   - recipient_email  (string)
 *   - recipient_name   (string|null)
 *   - preferred_locale (string|null)
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgSharePoint\Mail;

use App\Mail\Concerns\LocaleAwareMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class SharePointSyncErrorMail extends Mailable implements ShouldQueue
{
    use LocaleAwareMailable, Queueable, SerializesModels;

    public function __construct(public array $context)
    {
        $this->recipientEmail = $context['recipient_email'] ?? null;
        $this->locale = $context['preferred_locale'] ?? null;
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->resolveEmailLocale());

        return new Envelope(
            subject: __('SharePoint sync error: :name', [
                'name' => $this->context['connection_name'] ?? 'SharePoint',
            ]),
        );
    }

    public function content(): Content
    {
        App::setLocale($this->resolveEmailLocale());

        return new Content(
            view: 'ahg-sharepoint::emails.sync-error',
            text: 'ahg-sharepoint::emails.sync-error-text',
            with: ['ctx' => $this->context],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
