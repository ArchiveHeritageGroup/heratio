<?php

/**
 * LocaleAwareMailable trait
 *
 * Phase 2 of #674 (Email + notifications). Used by every Heratio Mailable
 * that needs to render its blade template in the recipient's preferred
 * locale instead of the global app.locale.
 *
 * Behaviour:
 *  - resolveEmailLocale() picks (in order): an explicit $this->locale,
 *    the recipient user's `preferred_locale` column, the operator-wide
 *    config('app.locale').
 *  - localisedView($base) returns 'emails.<locale>.<base>' if a view at
 *    that path exists, else falls back to 'emails.en.<base>', then to
 *    plain 'emails.<base>' for backward compatibility with the
 *    pre-Phase 2 view layout.
 *  - The mailable's build() or content() method should call
 *    App::setLocale($this->resolveEmailLocale()) before view-resolution
 *    so the layout's __() / @lang() helpers also speak the right tongue.
 *
 * Recipient-locale resolution is best-effort: a free-form $emailAddress
 * is consulted against the user table; if no row exists (e.g. external
 * researcher) we fall through to config('app.locale').
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Mail\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

trait LocaleAwareMailable
{
    /**
     * Explicit override. Set in the constructor or by the dispatcher when
     * the recipient is not a Heratio user (e.g. ad-hoc researcher).
     */
    public ?string $locale = null;

    /**
     * Optional - free-form recipient email used for the user lookup when
     * no explicit $locale is set. Mailables that already serialise a User
     * model should ignore this and let resolveEmailLocale() read the
     * preferred_locale directly.
     */
    public ?string $recipientEmail = null;

    /**
     * Pick the right locale for this email.
     */
    public function resolveEmailLocale(): string
    {
        if (! empty($this->locale)) {
            return $this->locale;
        }

        $user = $this->user ?? null;
        if (is_object($user) && ! empty($user->preferred_locale)) {
            return (string) $user->preferred_locale;
        }
        if (is_array($user) && ! empty($user['preferred_locale'])) {
            return (string) $user['preferred_locale'];
        }

        $email = $this->recipientEmail
            ?? (is_object($user) ? ($user->email ?? null) : null)
            ?? (is_array($user) ? ($user['email'] ?? null) : null);

        if ($email && Schema::hasTable('user') && Schema::hasColumn('user', 'preferred_locale')) {
            try {
                $locale = DB::table('user')
                    ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
                    ->value('preferred_locale');
                if ($locale) {
                    return (string) $locale;
                }
            } catch (\Throwable $e) {
                // fall through to default
            }
        }

        return (string) (config('app.locale') ?: 'en');
    }

    /**
     * Resolve a base view name (e.g. "password-reset") to the most
     * specific localised template that actually exists on disk.
     *
     * Tries (in order):
     *   emails.<locale>.<base>
     *   emails.en.<base>
     *   emails.<base>          (legacy pre-Phase 2 layout)
     */
    public function localisedView(string $base): string
    {
        $locale = $this->resolveEmailLocale();

        foreach ([
            'emails.'.$locale.'.'.$base,
            'emails.en.'.$base,
            'emails.'.$base,
        ] as $candidate) {
            if (View::exists($candidate)) {
                return $candidate;
            }
        }

        // Last-ditch: return the locale path so Laravel raises a clear
        // "view not found" rather than silently swapping for a stale one.
        return 'emails.'.$locale.'.'.$base;
    }
}
