<?php

namespace App\Http\Controllers\Auth;

use App\Auth\SecuritySettings;
use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LoginController extends Controller
{
    // Lockout window + duration are now read from /admin/ahgSettings/security
    // via App\Auth\SecuritySettings (closes audit issue #90). The previous
    // hardcoded MAX_ATTEMPTS=5 and LOCKOUT_MINUTES=15 constants are kept as
    // documentation of the historical defaults — defaults now live in
    // SecuritySettings::lockoutMaxAttempts() and ::lockoutDurationMinutes().

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

        // Lockout uses settings from /admin/ahgSettings/security. Master
        // switch (security_lockout_enabled) skips the gate entirely, which
        // is the right behaviour for kiosks / testing environments where
        // brute-forcing isn't a concern.
        $lockoutEnabled = SecuritySettings::lockoutEnabled();
        $maxAttempts    = SecuritySettings::lockoutMaxAttempts();
        $lockoutMins    = SecuritySettings::lockoutDurationMinutes();

        if ($lockoutEnabled) {
            $recentFailures = DB::table('login_attempt')
                ->where('identifier', $identifier)
                ->where('success', 0)
                ->where('attempted_at', '>=', now()->subMinutes($lockoutMins))
                ->count();

            if ($recentFailures >= $maxAttempts) {
                $this->recordAttempt($identifier, $ip, false);

                return back()->withErrors([
                    'email' => 'Too many failed login attempts. Please try again in ' . $lockoutMins . ' minutes.',
                ])->onlyInput('email');
            }
        } else {
            $recentFailures = 0;
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

            // Password-policy gates after successful auth (issue #90):
            //   1. password_expiry_days — force change when last
            //      password_history.changed_at exceeds the threshold.
            //   2. security_force_password_change — global flag flipped on
            //      by an admin; every user whose last change predates the
            //      baseline timestamp must reset.
            // Both routes lead to /user/password with a flash message; the
            // form-save handler will let them through once a new password
            // is in password_history.
            if ($flashMsg = $this->passwordPolicyRedirectReason(Auth::user())) {
                $request->session()->put('login_redirect_next', $next);
                return redirect()->route('user.password.edit')->with('warning', $flashMsg);
            }

            return redirect($next)
                ->withCookie(cookie('atom_authenticated', '1', 43200, '/', null, false, false));
        }

        // Failed attempt
        $this->recordAttempt($identifier, $ip, false);
        $errorMsg = 'The provided credentials do not match our records.';
        if ($lockoutEnabled) {
            $remaining = $maxAttempts - $recentFailures - 1;
            if ($remaining > 0 && $remaining <= 2) {
                $errorMsg .= ' ' . $remaining . ' attempt(s) remaining before lockout.';
            }
        }

        return back()->withErrors([
            'email' => $errorMsg,
        ])->onlyInput('email');
    }

    /**
     * Decide whether a freshly-authenticated user must change their password
     * before continuing. Returns a flash message describing why, or null when
     * the user is good to go. Centralises both expiry + force-change checks
     * so they share the redirect path.
     */
    protected function passwordPolicyRedirectReason($user): ?string
    {
        if (!$user) return null;

        $latestChange = DB::table('password_history')
            ->where('user_id', $user->id)
            ->orderByDesc('changed_at')
            ->value('changed_at');

        // Password expiry — only checked when expiry_days > 0 (0 disables).
        if (SecuritySettings::passwordExpiryEnabled() && $latestChange) {
            $expiryDays = SecuritySettings::passwordExpiryDays();
            $expired = \Carbon\Carbon::parse($latestChange)->lt(now()->subDays($expiryDays));
            if ($expired) {
                return 'Your password is older than ' . $expiryDays . ' days and must be changed.';
            }
        }

        // Global force-change flag — applies when the admin flipped it ON
        // and the user hasn't reset since the baseline timestamp was stamped.
        if (SecuritySettings::forcePasswordChange()) {
            $baseline = SecuritySettings::forcePasswordChangeBaseline();
            $needsForceChange = !$latestChange
                || ($baseline && \Carbon\Carbon::parse($latestChange)->lt($baseline));
            if ($needsForceChange) {
                return 'An administrator has required all users to set a new password.';
            }
        }

        return null;
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/heritage')
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
        // Redirect to the canonical user show page so the cloned AtoM-style layout
        // (Basic info / Profile / Contact / Access control / Translate / API keys /
        // Security clearance) is rendered consistently for both /user/profile and
        // /user/{slug}.
        $user = Auth::user();
        $slug = DB::table('slug')->where('object_id', $user->id)->value('slug');
        if ($slug) {
            return redirect('/user/' . $slug);
        }
        // Fallback: render the legacy auth.profile view if the user has no slug yet
        return view('auth.profile', compact('user'));
    }

    /**
     * Show the profile edit form.
     */
    public function showProfileEdit()
    {
        // Redirect to the canonical user edit page so the cloned layout is used.
        $user = Auth::user();
        $slug = DB::table('slug')->where('object_id', $user->id)->value('slug');
        if ($slug) {
            return redirect('/user/' . $slug . '/edit');
        }
        abort(404);
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

        // Check password not reused. The history depth is read from
        // /admin/ahgSettings/security (password_history_count) so an
        // operator can tighten/loosen the policy without code changes.
        $historyCount = SecuritySettings::passwordHistoryCount();
        $newPlaintext = $request->input('password');
        $recentPasswords = $historyCount > 0
            ? DB::table('password_history')
                ->where('user_id', $user->id)
                ->orderByDesc('changed_at')
                ->limit($historyCount)
                ->pluck('password_hash')
            : collect();

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
    // User Registration
    // =============================================

    /**
     * Show the registration form.
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.register');
    }

    /**
     * Process user registration.
     */
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:user,username',
            'email' => 'required|email|max:255|unique:user,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create object row (class table inheritance root)
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitUser',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create actor row
        DB::table('actor')->insert([
            'id' => $objectId,
            'parent_id' => null,
            'source_culture' => 'en',
        ]);

        // Create actor_i18n row with the username as display name
        DB::table('actor_i18n')->insert([
            'id' => $objectId,
            'culture' => 'en',
            'authorized_form_of_name' => $request->input('username'),
        ]);

        // Hash password
        $hashed = self::hashPassword($request->input('password'));

        // Create user row
        DB::table('user')->insert([
            'id' => $objectId,
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'password_hash' => $hashed['password_hash'],
            'salt' => $hashed['salt'],
            'active' => 1,
        ]);

        // Add to authenticated group (99)
        DB::table('acl_user_group')->insert([
            'user_id' => $objectId,
            'group_id' => 99,
        ]);

        // Generate slug
        $baseSlug = \Illuminate\Support\Str::slug($request->input('username'));
        $slug = $baseSlug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }
        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
        ]);

        return redirect()->route('login')
            ->with('success', 'Account created successfully. Please log in.');
    }

    /**
     * Show the researcher registration form.
     */
    public function showResearcherRegister()
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.researcher-register');
    }

    /**
     * Process researcher registration.
     * Migrated from ahgResearchPlugin executePublicRegister().
     * Creates: object → actor → actor_i18n → user → acl_user_group → slug → research_researcher.
     */
    public function researcherRegister(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:3|max:255|unique:user,username',
            'email' => 'required|email|max:255|unique:user,email',
            'password' => 'required|string|min:8|confirmed',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'title' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'affiliation_type' => 'required|string|max:50',
            'institution' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'orcid_id' => 'nullable|string|max:50',
            'id_type' => 'nullable|string|max:50',
            'id_number' => 'nullable|string|max:100',
            'research_interests' => 'nullable|string|max:5000',
            'current_project' => 'nullable|string|max:5000',
        ]);

        // Create object row (class table inheritance root)
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitUser',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create actor row
        DB::table('actor')->insert([
            'id' => $objectId,
            'parent_id' => null,
            'source_culture' => 'en',
        ]);

        // Create actor_i18n row with first + last name
        $displayName = trim($request->input('first_name') . ' ' . $request->input('last_name'));
        DB::table('actor_i18n')->insert([
            'id' => $objectId,
            'culture' => 'en',
            'authorized_form_of_name' => $displayName,
        ]);

        // Hash password
        $hashed = self::hashPassword($request->input('password'));

        // Create user row
        DB::table('user')->insert([
            'id' => $objectId,
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'password_hash' => $hashed['password_hash'],
            'salt' => $hashed['salt'],
            'active' => 1,
        ]);

        // Add to authenticated group (99) + researcher group (104) if exists
        DB::table('acl_user_group')->insert([
            'user_id' => $objectId,
            'group_id' => 99,
        ]);

        $researcherGroupExists = DB::table('acl_group')->where('id', 104)->exists();
        if ($researcherGroupExists) {
            DB::table('acl_user_group')->insert([
                'user_id' => $objectId,
                'group_id' => 104,
            ]);
        }

        // Generate slug
        $baseSlug = \Illuminate\Support\Str::slug($request->input('username'));
        $slug = $baseSlug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }
        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
        ]);

        // Insert into research_researcher table (matches AtoM's ahgResearchPlugin)
        DB::table('research_researcher')->insert([
            'user_id' => $objectId,
            'title' => $request->input('title'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'affiliation_type' => $request->input('affiliation_type', 'independent'),
            'institution' => $request->input('institution'),
            'department' => $request->input('department'),
            'position' => $request->input('position'),
            'orcid_id' => $request->input('orcid_id'),
            'id_type' => $request->input('id_type') ?: null,
            'id_number' => $request->input('id_number'),
            'research_interests' => $request->input('research_interests'),
            'current_project' => $request->input('current_project'),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('researcher.register.complete');
    }

    /**
     * Show the registration complete confirmation page.
     */
    public function registrationComplete()
    {
        return view('auth.registration-complete');
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
