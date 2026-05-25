<?php

/**
 * DoiFailedMail
 *
 * Phase 2 of #674. Sent when a DOI mint attempt fails at the registrar
 * (DataCite / Crossref / etc). Surfaces the registrar's diagnostic so
 * the recipient can correct metadata and retry.
 *
 * Expected $context shape:
 *   - title            (string)
 *   - object_url       (string)
 *   - error_code       (string|null) registrar-supplied code
 *   - error_message    (string)      registrar-supplied detail
 *   - attempted_at     (string)      ISO 8601
 *   - retry_url        (string|null) admin route to retry the mint
 *   - recipient_email  (string)
 *   - recipient_name   (string|null)
 *   - preferred_locale (string|null)
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgDoiManage\Mail;

use App\Mail\Concerns\LocaleAwareMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class DoiFailedMail extends Mailable implements ShouldQueue
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
            subject: __('DOI mint failed: :title', ['title' => $this->context['title'] ?? '']),
        );
    }

    public function content(): Content
    {
        App::setLocale($this->resolveEmailLocale());

        return new Content(
            view: 'ahg-doi-manage::emails.doi-failed',
            text: 'ahg-doi-manage::emails.doi-failed-text',
            with: ['ctx' => $this->context],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
