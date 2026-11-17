<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared logins whose credentials are published - a demo admin, a course's
 * training account - must not be changed through the site: one visitor setting
 * a new password locks out everyone else until the demo resets. Accounts named
 * in heratio.shared_accounts (HERATIO_SHARED_ACCOUNTS, emails or usernames)
 * refuse every write to their password, email, profile, groups, permissions or
 * existence, from themselves and from administrators alike. Change them on the
 * command line, or take them off the list first.
 *
 * Route parameter: 'self' (the signed-in account) or 'slug' (the account the
 * route's {slug} names). Only writes are refused; forms still open.
 */
class ProtectSharedAccounts
{
    public function handle(Request $request, Closure $next, string $target = 'self'): Response
    {
        if (! $request->isMethodSafe()) {
            $userId = $target === 'slug'
                ? DB::table('slug')->where('slug', (string) $request->route('slug'))->value('object_id')
                : Auth::id();
            if ($userId && self::isShared((int) $userId)) {
                abort(403, __('This is a shared account. Its password, email and settings cannot be changed here.'));
            }
        }

        return $next($request);
    }

    public static function isShared(int $userId): bool
    {
        $list = array_map('strtolower', (array) config('heratio.shared_accounts', []));
        if ($list === []) {
            return false;
        }
        $u = DB::table('user')->where('id', $userId)->first(['username', 'email']);

        return $u && (in_array(strtolower((string) $u->email), $list, true) || in_array(strtolower((string) $u->username), $list, true));
    }
}
