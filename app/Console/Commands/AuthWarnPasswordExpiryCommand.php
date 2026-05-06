<?php

namespace App\Console\Commands;

use App\Auth\SecuritySettings;
use App\Mail\PasswordResetMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * auth:warn-password-expiry — email users whose password expires in the
 * next security_password_expiry_warn_days days.
 *
 * Closes the expiry-notify half of audit issue #90. Skips silently when
 * security_password_expiry_notify is false. Bails when password_expiry_days
 * is 0 (expiry disabled). Scheduled daily from AppServiceProvider::boot.
 *
 * The "warn email" content is a reuse of PasswordResetMail (it already
 * carries a reset URL + username) — the user receives a password-reset
 * link they can use immediately rather than waiting until lockout.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */
class AuthWarnPasswordExpiryCommand extends Command
{
    protected $signature = 'auth:warn-password-expiry {--dry-run}';
    protected $description = 'Email users whose password is within security_password_expiry_warn_days of expiring';

    public function handle(): int
    {
        if (!SecuritySettings::passwordExpiryNotify()) {
            $this->info('security_password_expiry_notify is false — skipping.');
            return self::SUCCESS;
        }

        if (!SecuritySettings::passwordExpiryEnabled()) {
            $this->info('Password expiry disabled (password_expiry_days <= 0) — skipping.');
            return self::SUCCESS;
        }

        $expiryDays = SecuritySettings::passwordExpiryDays();
        $warnDays   = SecuritySettings::passwordExpiryWarnDays();

        // Window: passwords whose changed_at falls between
        // (now - expiryDays) and (now - expiryDays + warnDays).
        // Anything older than the lower bound is already expired (login
        // gate handles those); anything younger than the upper bound has
        // more than warnDays left.
        $expireFrom = now()->subDays($expiryDays);
        $warnFrom   = now()->subDays($expiryDays - $warnDays);

        // Find each user's most recent password change and pick those
        // landing in the window.
        $candidates = DB::table('password_history as ph')
            ->select('ph.user_id', DB::raw('MAX(ph.changed_at) as latest_change'))
            ->groupBy('ph.user_id')
            ->havingRaw('MAX(ph.changed_at) BETWEEN ? AND ?', [$expireFrom, $warnFrom])
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No users in the warn window (expires in <= {$warnDays}d).");
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($candidates as $row) {
            $user = DB::table('user')->where('id', $row->user_id)->first();
            if (!$user || empty($user->email) || empty($user->active)) continue;

            $daysLeft = (int) ceil(\Carbon\Carbon::parse($row->latest_change)
                ->addDays($expiryDays)
                ->diffInDays(now(), false) * -1);

            if ($this->option('dry-run')) {
                $this->line("[DRY RUN] would warn {$user->email} ({$daysLeft}d left)");
                continue;
            }

            try {
                $token = bin2hex(random_bytes(32));
                DB::table('research_password_reset')->where('user_id', $user->id)->delete();
                DB::table('research_password_reset')->insert([
                    'user_id'    => $user->id,
                    'token'      => $token,
                    'expires_at' => now()->addHour(),
                    'created_at' => now(),
                ]);
                $resetUrl = url('/user/password-reset/' . $token);
                Mail::to($user->email)->send(new PasswordResetMail($resetUrl, $user->username ?? $user->email));
                $sent++;
            } catch (\Throwable $e) {
                $this->warn("Failed to warn {$user->email}: " . $e->getMessage());
            }
        }

        $this->info("Sent {$sent} expiry-warning email(s).");
        return self::SUCCESS;
    }
}
