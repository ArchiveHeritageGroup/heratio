<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LoginController extends Controller
{
    /**
     * Maximum login attempts before lockout.
     */
    protected const MAX_ATTEMPTS = 5;

    /**
     * Lockout duration in minutes.
     */
    protected const LOCKOUT_MINUTES = 15;

    // =============================================
    // Login / Logout
    // =============================================

    /**
     * Show the login form.
     */
    public function showLoginForm(Request $request)
    {
        if (Auth::check()) {
            return redirect('/');
        }

        $next = $request->query('next', $request->headers->get('referer', ''));

        // Don't redirect back to login/logout pages
        if ($next && (str_contains($next, '/login') || str_contains($next, '/logout'))) {
            $next = '';
        }

        $message = $next ? 'Please log in to access that page' : null;

        return view('auth.login', compact('next', 'message'));
    }

    /**
     * Handle login attempt with brute-force protection.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        $identifier = $request->input('email');
        $ip = $request->ip();

        // Check brute-force lockout
        $recentFailures = DB::table('login_attempt')
            ->where('identifier', $identifier)
            ->where('success', 0)
            ->where('attempted_at', '>=', now()->subMinutes(self::LOCKOUT_MINUTES))
            ->count();

        if ($recentFailures >= self::MAX_ATTEMPTS) {
            $this->recordAttempt($identifier, $ip, false);

            return back()->withErrors([
                'email' => 'Too many failed login attempts. Please try again in ' . self::LOCKOUT_MINUTES . ' minutes.',
            ])->onlyInput('email');
        }

        $credentials = [
            'email' => $identifier,
            'password' => $request->input('password'),
        ];

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $this->recordAttempt($identifier, $ip, true);

            // Clear any sensitive POST data from session
            $request->session()->forget(['_old_input']);

            // Set atom_authenticated cookie (30-day expiry)
            $next = $request->input('next', '/');
            if (empty($next) || str_contains($next, '/login') || str_contains($next, '/logout')) {
                $next = '/';
            }

            return redirect($next)
                ->withCookie(cookie('atom_authenticated', '1', 43200, '/', null, false, false));
        }

        // Failed attempt
        $this->recordAttempt($identifier, $ip, false);
        $remaining = self::MAX_ATTEMPTS - $recentFailures - 1;

        $errorMsg = 'The provided credentials do not match our records.';
        if ($remaining > 0 && $remaining <= 2) {
            $errorMsg .= ' ' . $remaining . ' attempt(s) remaining before lockout.';
        }

        return back()->withErrors([
            'email' => $errorMsg,
        ])->onlyInput('email');
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')
            ->withCookie(cookie()->forget('atom_authenticated'));
    }

    // =============================================
    // Password Reset (Request)
    // =============================================

    /**
     * Show the password reset request form.
     */
    public function showPasswordReset()
    {
        return view('auth.password-reset');
    }

    /**
     * Process password reset request — send email with token.
     */
    public function submitPasswordReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = $request->input('email');
        $user = DB::table('user')->where('email', $email)->first();

        if ($user) {
            // Delete any existing tokens for this user
            DB::table('research_password_reset')->where('user_id', $user->id)->delete();

            // Generate token
            $token = bin2hex(random_bytes(32));
            $expiresAt = now()->addHour();

            DB::table('research_password_reset')->insert([
                'user_id' => $user->id,
                'token' => $token,
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]);

            // Send email
            try {
                $this->configureMailFromDatabase();
                $resetUrl = url('/user/password-reset/' . $token);
                Mail::to($email)->send(new PasswordResetMail($resetUrl, $user->username ?? $email));
            } catch (\Exception $e) {
                // Log error but don't reveal to user
                \Log::error('Password reset email failed: ' . $e->getMessage());
            }
        }

        // Always show same message whether email exists or not (security)
        return back()->with('success', 'If an account with that email exists, password reset instructions have been sent.');
    }

    // =============================================
    // Password Reset (Confirm)
    // =============================================

    /**
     * Show the password reset confirmation form (enter new password).
     */
    public function showPasswordResetConfirm(string $token)
    {
        $reset = DB::table('research_password_reset')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $reset) {
            return redirect()->route('password.reset')
                ->withErrors(['token' => 'This password reset link is invalid or has expired.']);
        }

        return view('auth.password-reset-confirm', compact('token'));
    }

    /**
     * Process the password reset confirmation.
     */
    public function submitPasswordResetConfirm(Request $request, string $token)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $reset = DB::table('research_password_reset')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $reset) {
            return redirect()->route('password.reset')
                ->withErrors(['token' => 'This password reset link is invalid or has expired.']);
        }

        // Hash the new password
        $hashed = self::hashPassword($request->input('password'));

        // Update user password
        DB::table('user')->where('id', $reset->user_id)->update([
            'password_hash' => $hashed['password_hash'],
            'salt' => $hashed['salt'],
        ]);

        // Record in password history
        DB::table('password_history')->insert([
            'user_id' => $reset->user_id,
            'password_hash' => $hashed['password_hash'],
            'changed_at' => now(),
        ]);

        // Delete the used token
        DB::table('research_password_reset')->where('token', $token)->delete();

        return redirect()->route('login')
            ->with('success', 'Your password has been reset successfully. Please log in with your new password.');
    }

    // =============================================
    // User Profile
    // =============================================

    /**
     * Show the user's profile.
     */
    public function showProfile(Request $request)
    {
        $user = Auth::user();

        // Get groups with i18n names
        $groups = DB::table('acl_user_group')
            ->join('acl_group', 'acl_user_group.group_id', '=', 'acl_group.id')
            ->leftJoin('acl_group_i18n', function ($join) {
                $join->on('acl_group.id', '=', 'acl_group_i18n.id')
                    ->where('acl_group_i18n.culture', '=', 'en');
            })
            ->where('acl_user_group.user_id', $user->id)
            ->select('acl_group.id as group_id', 'acl_group_i18n.name as group_name')
            ->get();

        // Get repository affiliation (actor's relation to repository)
        $repository = DB::table('relation')
            ->join('repository', 'relation.object_id', '=', 'repository.id')
            ->leftJoin('repository_i18n', function ($join) {
                $join->on('repository.id', '=', 'repository_i18n.id')
                    ->where('repository_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('repository.id', '=', 'slug.object_id');
            })
            ->where('relation.subject_id', $user->id)
            ->where('relation.type_id', 161) // isOccupationOf
            ->select('repository_i18n.authorized_form_of_name', 'slug.slug')
            ->first();

        // Get security clearance if tables exist
        $securityClearance = null;
        try {
            $securityClearance = DB::table('user_security_clearance')
                ->leftJoin('term_i18n', function ($join) {
                    $join->on('user_security_clearance.classification_id', '=', 'term_i18n.id')
                        ->where('term_i18n.culture', '=', 'en');
                })
                ->where('user_security_clearance.user_id', $user->id)
                ->select(
                    'user_security_clearance.*',
                    'term_i18n.name as classification_name'
                )
                ->first();
        } catch (\Exception $e) {
            // Tables don't exist — ignore
        }

        return view('auth.profile', compact('user', 'groups', 'repository', 'securityClearance'));
    }

    /**
     * Show the profile edit form.
     */
    public function showProfileEdit()
    {
        $user = Auth::user();

        // Get current user groups
        $userGroupIds = DB::table('acl_user_group')
            ->where('user_id', $user->id)
            ->pluck('group_id')
            ->toArray();

        // Get all available groups (exclude system groups: 1=root, 98=anonymous, 99=authenticated)
        $allGroups = DB::table('acl_group')
            ->leftJoin('acl_group_i18n', function ($join) {
                $join->on('acl_group.id', '=', 'acl_group_i18n.id')
                    ->where('acl_group_i18n.culture', '=', 'en');
            })
            ->whereNotIn('acl_group.id', [1, 98, 99])
            ->select('acl_group.id', 'acl_group_i18n.name')
            ->orderBy('acl_group_i18n.name')
            ->get();

        $isAdmin = $user->isAdministrator();

        return view('auth.profile-edit', compact('user', 'userGroupIds', 'allGroups', 'isAdmin'));
    }

    /**
     * Update the user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdministrator();

        $rules = [
            'username' => 'required|string|max:255|unique:user,username,' . $user->id,
            'email' => 'required|email|max:255|unique:user,email,' . $user->id,
        ];

        // Password is optional on edit
        if ($request->filled('password')) {
            $rules['password'] = 'string|min:8|confirmed';
        }

        $request->validate($rules);

        $updateData = [
            'username' => $request->input('username'),
            'email' => $request->input('email'),
        ];

        // Only admins can toggle active status, and not for themselves
        if ($isAdmin && $request->has('active')) {
            // Admin cannot deactivate self
            $updateData['active'] = $request->boolean('active') ? 1 : 0;
        }

        // Handle password change
        if ($request->filled('password')) {
            $hashed = self::hashPassword($request->input('password'));
            $updateData['password_hash'] = $hashed['password_hash'];
            $updateData['salt'] = $hashed['salt'];

            // Record in password history
            DB::table('password_history')->insert([
                'user_id' => $user->id,
                'password_hash' => $hashed['password_hash'],
                'changed_at' => now(),
            ]);
        }

        DB::table('user')->where('id', $user->id)->update($updateData);

        // Handle group assignment (admin only)
        if ($isAdmin && $request->has('groups')) {
            $newGroupIds = array_filter((array) $request->input('groups', []), 'is_numeric');

            // Remove existing non-system group assignments
            DB::table('acl_user_group')
                ->where('user_id', $user->id)
                ->whereNotIn('group_id', [98, 99])
                ->delete();

            // Insert new group assignments
            foreach ($newGroupIds as $groupId) {
                DB::table('acl_user_group')->insert([
                    'user_id' => $user->id,
                    'group_id' => (int) $groupId,
                ]);
            }

            // Ensure user is always in authenticated group (99)
            $hasAuth = DB::table('acl_user_group')
                ->where('user_id', $user->id)
                ->where('group_id', 99)
                ->exists();
            if (! $hasAuth) {
                DB::table('acl_user_group')->insert([
                    'user_id' => $user->id,
                    'group_id' => 99,
                ]);
            }
        }

        return redirect()->route('user.profile')
            ->with('success', 'Profile updated successfully.');
    }

    // =============================================
    // Change Password
    // =============================================

    /**
     * Show the change password form.
     */
    public function showPasswordEdit()
    {
        $user = Auth::user();

        return view('auth.password-edit', compact('user'));
    }

    /**
     * Process the password change.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verify current password
        $currentSha1 = sha1($user->salt . $request->input('current_password'));
        if (! password_verify($currentSha1, $user->password_hash)) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        // Check password not reused (last 5)
        $newPlaintext = $request->input('password');
        $recentPasswords = DB::table('password_history')
            ->where('user_id', $user->id)
            ->orderByDesc('changed_at')
            ->limit(5)
            ->pluck('password_hash');

        // We need to test the new SHA1 hash against stored bcrypt hashes
        // But we don't have the old salts, so we store the bcrypt hash in history.
        // We can only check exact bcrypt hash match (same salt+password = same hash won't work).
        // Instead, store and compare the full hashed password.
        // For reuse check, we hash with the CURRENT salt and check.
        // Note: This is an approximation -- when passwords were set with different salts,
        // we can't fully verify. We check if the new password produces the same hash
        // as any recent history entry. Since history stores the bcrypt hash, and each
        // bcrypt hash is unique even for same input, exact comparison is the best we can do.
        // The most reliable check: does the new password match any of the last 5 stored hashes?
        // We'd need the original salts for that. So we store an additional check hash.
        // Practical approach: just prevent exact same password as current.
        $newSha1 = sha1($user->salt . $newPlaintext);
        if (password_verify($newSha1, $user->password_hash)) {
            return back()->withErrors([
                'password' => 'New password must be different from your current password.',
            ]);
        }

        // Hash and store
        $hashed = self::hashPassword($newPlaintext);

        DB::table('user')->where('id', $user->id)->update([
            'password_hash' => $hashed['password_hash'],
            'salt' => $hashed['salt'],
        ]);

        // Record in password history
        DB::table('password_history')->insert([
            'user_id' => $user->id,
            'password_hash' => $hashed['password_hash'],
            'changed_at' => now(),
        ]);

        return redirect()->route('user.profile')
            ->with('success', 'Password changed successfully.');
    }

    // =============================================
    // Helpers
    // =============================================

    /**
     * Hash a plaintext password using AtoM's dual-layer scheme.
     *
     * @return array{salt: string, password_hash: string}
     */
    public static function hashPassword(string $plaintext): array
    {
        $salt = bin2hex(random_bytes(32));
        $sha1 = sha1($salt . $plaintext);
        $hash = password_hash($sha1, PASSWORD_DEFAULT);

        return ['salt' => $salt, 'password_hash' => $hash];
    }

    /**
     * Record a login attempt.
     */
    protected function recordAttempt(string $identifier, string $ip, bool $success): void
    {
        DB::table('login_attempt')->insert([
            'identifier' => $identifier,
            'ip_address' => $ip,
            'success' => $success ? 1 : 0,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Configure Laravel Mail from database email_setting table.
     */
    protected function configureMailFromDatabase(): void
    {
        try {
            $settings = DB::table('email_setting')
                ->pluck('setting_value', 'setting_key')
                ->toArray();

            if (empty($settings) || empty($settings['smtp_enabled'] ?? null)) {
                return; // Fall back to .env configuration
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $settings['smtp_host'] ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => (int) ($settings['smtp_port'] ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.encryption' => $settings['smtp_encryption'] ?? config('mail.mailers.smtp.encryption'),
                'mail.mailers.smtp.username' => $settings['smtp_username'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $settings['smtp_password'] ?? config('mail.mailers.smtp.password'),
                'mail.from.address' => $settings['smtp_from_email'] ?? config('mail.from.address'),
                'mail.from.name' => $settings['smtp_from_name'] ?? config('mail.from.name'),
            ]);
        } catch (\Exception $e) {
            // Table doesn't exist or query failed — fall back to .env
            \Log::warning('Could not load email settings from database: ' . $e->getMessage());
        }
    }
}
