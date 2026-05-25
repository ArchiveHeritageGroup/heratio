<?php

/**
 * PasswordResetMail
 *
 * Sent on password-reset request. Phase 2 of #674 adds:
 *  - locale-aware view resolution (en + af shipped today; falls back to en)
 *  - per-tenant branding via the shared _layout.blade.php (logo, colours,
 *    footer html, sender override)
 *  - explicit subject localisation via __() so the envelope speaks the
 *    same language as the body.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Mail;

use App\Mail\Concerns\LocaleAwareMailable;
use App\Services\TenantEmailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class PasswordResetMail extends Mailable implements ShouldQueue
{
    use LocaleAwareMailable, Queueable, SerializesModels;

    public string $resetUrl;

    public string $username;

    public function __construct(string $resetUrl, string $username, ?string $recipientEmail = null, ?string $locale = null)
    {
        $this->resetUrl = $resetUrl;
        $this->username = $username;
        $this->recipientEmail = $recipientEmail;
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->resolveEmailLocale());

        $branding = app(TenantEmailBranding::class);
        $from = $branding->senderEmail();
        $fromName = $branding->senderName();

        $envelope = new Envelope(
            subject: __('Password Reset Request'),
        );

        if ($from) {
            $envelope = new Envelope(
                from: new \Illuminate\Mail\Mailables\Address($from, $fromName ?? ''),
                subject: __('Password Reset Request'),
            );
        }

        return $envelope;
    }

    public function content(): Content
    {
        App::setLocale($this->resolveEmailLocale());

        return new Content(
            view: $this->localisedView('password-reset'),
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
