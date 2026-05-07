<?php

/**
 * CheckOverdueDsarsCommand - daily sweep that emails the configured
 *                            dp_notify_email address about overdue DSARs.
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

namespace AhgPrivacy\Console\Commands;

use AhgPrivacy\Support\DataProtectionSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Honours the dp_notify_overdue toggle from issue #72. Runs daily; gated
 * on dp_enabled + dp_notify_overdue + dp_notify_email all being set.
 *
 *   php artisan privacy:check-overdue-dsars
 *   php artisan privacy:check-overdue-dsars --dry-run
 */
class CheckOverdueDsarsCommand extends Command
{
    protected $signature = 'privacy:check-overdue-dsars
        {--dry-run : List overdue DSARs but do not send an email}';

    protected $description = 'Email the data-protection contact about DSARs whose due_date has passed and which are not yet closed.';

    public function handle(): int
    {
        if (!DataProtectionSettings::enabled()) {
            $this->info('Data Protection module disabled (dp_enabled=false). Skipping.');
            return self::SUCCESS;
        }

        if (!Schema::hasTable('privacy_dsar')) {
            $this->warn('privacy_dsar table missing. Skipping.');
            return self::SUCCESS;
        }

        $today = now()->toDateString();
        $overdue = DB::table('privacy_dsar')
            ->where('due_date', '<', $today)
            ->whereNotIn('status', ['closed', 'completed', 'rejected', 'withdrawn'])
            ->orderBy('due_date')
            ->get(['id', 'reference_number', 'jurisdiction', 'request_type', 'requestor_name', 'received_date', 'due_date', 'status']);

        $this->line(sprintf('Found %d overdue DSAR(s).', $overdue->count()));

        if ($overdue->isEmpty()) {
            return self::SUCCESS;
        }

        if (!DataProtectionSettings::notifyOverdue()) {
            $this->info('dp_notify_overdue=false; not emailing. (Listed above for diagnostic only.)');
            return self::SUCCESS;
        }

        $to = DataProtectionSettings::notifyEmail();
        if ($to === '') {
            $this->warn('dp_notify_email not set; cannot dispatch overdue notification.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('[dry-run] Would email %s with %d overdue DSAR(s).', $to, $overdue->count()));
            return self::SUCCESS;
        }

        $body = "The following Data Subject Access Requests are past their due_date and remain open:\n\n";
        foreach ($overdue as $r) {
            $body .= sprintf(
                "- %s (%s, %s) requested %s, due %s, status=%s\n",
                $r->reference_number,
                $r->jurisdiction,
                $r->request_type,
                $r->received_date,
                $r->due_date,
                $r->status
            );
        }
        $body .= "\nReview at /admin/privacy/dsar-list.\n";

        try {
            Mail::raw($body, function ($m) use ($to, $overdue) {
                $m->to($to)->subject(sprintf('[Heratio] %d overdue DSAR(s)', $overdue->count()));
            });
            $this->info(sprintf('Emailed %s.', $to));
        } catch (\Throwable $e) {
            Log::warning('[ahg-privacy] overdue DSAR notification failed: ' . $e->getMessage());
            $this->error('Mail dispatch failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
