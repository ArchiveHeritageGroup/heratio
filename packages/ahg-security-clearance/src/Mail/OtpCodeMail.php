<?php

/**
 * OtpCodeMail — locale-aware Mailable that delivers a 6-digit OTP code
 * to the user's enrolled email destination (issue #722).
 *
 * Mirrors PasswordResetMail (#674 Phase 2) for layout + branding so the
 * envelope, header, footer, and locale-fallback chain are all consistent.
 *
 * Templates live under packages/ahg-security-clearance/resources/views/
 * emails/{en,af}/otp-code.blade.php and are surfaced through the
 * 'ahg-security-clearance' view namespace via the localised resolver.
 *
 * The code is treated as ephemeral display data — it must never be logged
 * by Mailable serialisation; it's only embedded in the body of this one
 * email. The challenge row keeps a SHA-256 hash only.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace AhgSecurityClearance\Mail;

use App\Mail\Concerns\LocaleAwareMailable;
use App\Services\TenantEmailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

class OtpCodeMail extends Mailable implements ShouldQueue
{
    use LocaleAwareMailable, Queueable, SerializesModels;

    public string $code;
    public string $label;
    public int $ttlMinutes;

    public function __construct(string $code, string $label, ?string $recipientEmail = null, ?string $locale = null, int $ttlMinutes = 10)
    {
        $this->code = $code;
        $this->label = $label;
        $this->recipientEmail = $recipientEmail;
        $this->locale = $locale;
        $this->ttlMinutes = $ttlMinutes;
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->resolveEmailLocale());

        $branding = app(TenantEmailBranding::class);
        $from = $branding->senderEmail();
        $fromName = $branding->senderName();

        $envelope = new Envelope(
            subject: __('Your verification code'),
        );

        if ($from) {
            $envelope = new Envelope(
                from: new \Illuminate\Mail\Mailables\Address($from, $fromName ?? ''),
                subject: __('Your verification code'),
            );
        }

        return $envelope;
    }

    public function content(): Content
    {
        App::setLocale($this->resolveEmailLocale());

        return new Content(
            view: $this->localisedOtpView(),
        );
    }

    public function attachments(): array
    {
        return [];
    }

    /**
     * Resolve the OTP template under the ahg-security-clearance view
     * namespace. Tries locale-specific then falls back to en, mirroring
     * LocaleAwareMailable::localisedView() but scoped to the package
     * view namespace so we don't collide with the app-level emails.* tree.
     */
    private function localisedOtpView(): string
    {
        $locale = $this->resolveEmailLocale();
        $candidates = [
            'ahg-security-clearance::emails.'.$locale.'.otp-code',
            'ahg-security-clearance::emails.en.otp-code',
        ];

        foreach ($candidates as $candidate) {
            if (View::exists($candidate)) {
                return $candidate;
            }
        }

        return 'ahg-security-clearance::emails.en.otp-code';
    }
}
