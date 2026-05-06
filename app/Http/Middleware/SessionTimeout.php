<?php

namespace App\Http\Middleware;

use App\Auth\SecuritySettings;
use Closure;
use Illuminate\Http\Request;

/**
 * Override Laravel's session lifetime from
 * /admin/ahgSettings/security -> security_session_timeout_minutes.
 *
 * Closes audit issue #90 for the session-timeout key. Laravel reads
 * config('session.lifetime') when constructing the session cookie, so we
 * patch the config very early in the middleware chain (before
 * StartSession) so the override takes effect for the current request's
 * cookie issuance. The override is per-request — we don't write to .env
 * or cache config.
 *
 * 0 (or any non-positive value) means "use config/session.php default"
 * so the operator can disable the override and fall back to env-based
 * config without removing the row.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */
class SessionTimeout
{
    public function handle(Request $request, Closure $next)
    {
        $minutes = SecuritySettings::sessionTimeoutMinutes();
        if ($minutes > 0) {
            config(['session.lifetime' => $minutes]);
        }

        return $next($request);
    }
}
