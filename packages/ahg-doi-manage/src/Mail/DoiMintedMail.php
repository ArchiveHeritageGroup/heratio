<?php

/**
 * DoiMintedMail
 *
 * Phase 2 of #674. The audit table flagged "DOI minted/failed" as having
 * no email coverage. This Mailable fires when DataCite (or another DOI
 * registrar) confirms a freshly-minted DOI.
 *
 * Expected $context shape:
 *   - doi              (string) e.g. "10.1234/abcd-efgh"
 *   - title            (string) human-readable title of the resource
 *   - object_url       (string) link back to the IO in Heratio
 *   - resolver_url     (string) public https://doi.org/<doi>
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

class DoiMintedMail extends Mailable implements ShouldQueue
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
            subject: __('DOI minted: :doi', ['doi' => $this->context['doi'] ?? '']),
        );
    }

    public function content(): Content
    {
        App::setLocale($this->resolveEmailLocale());

        return new Content(
            view: 'ahg-doi-manage::emails.doi-minted',
            text: 'ahg-doi-manage::emails.doi-minted-text',
            with: ['ctx' => $this->context],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
