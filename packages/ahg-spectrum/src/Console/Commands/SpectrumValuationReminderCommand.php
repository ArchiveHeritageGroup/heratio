<?php

/**
 * SpectrumValuationReminderCommand - Heratio
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

namespace AhgSpectrum\Console\Commands;

use AhgSpectrum\Services\SpectrumNotificationService;
use AhgSpectrum\Services\SpectrumSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Emails curators when an object's most-recent valuation is older than
 * spectrum_valuation_reminder_days. Closes #91 phase 2.
 *
 * Idempotent: tracks the last reminder per object via the
 * spectrum_workflow_notification table's transition_key='valuation_reminder'
 * row so the same object isn't re-notified daily for the same lapse.
 *
 * The notification dispatcher (SpectrumNotificationService) already gates
 * on spectrum_email_notifications, so when that master toggle is off the
 * command runs silently with zero send attempts.
 */
class SpectrumValuationReminderCommand extends Command
{
    protected $signature = 'ahg:spectrum-valuation-reminder';
    protected $description = 'Email curators when valuations are older than spectrum_valuation_reminder_days.';

    public function handle(): int
    {
        $settings = new SpectrumSettings();
        $thresholdDays = $settings->valuationReminderDays();
        if ($thresholdDays === 0) {
            $this->line('[spectrum-valuation-reminder] threshold=0 - reminders disabled.');
            return self::SUCCESS;
        }

        if (!Schema::hasTable('spectrum_valuation') || !Schema::hasTable('object')) {
            $this->line('[spectrum-valuation-reminder] required tables missing - skipping.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($thresholdDays)->toDateString();

        // Objects whose most-recent valuation_date is on or before the cutoff.
        // Uses a subquery to get the latest valuation per object.
        $rows = DB::table('spectrum_valuation as v1')
            ->select('v1.object_id', 'v1.valuation_date', 'v1.valuation_reference')
            ->whereRaw('v1.valuation_date = (SELECT MAX(v2.valuation_date) FROM spectrum_valuation v2 WHERE v2.object_id = v1.object_id)')
            ->where('v1.valuation_date', '<=', $cutoff)
            ->get();

        if ($rows->isEmpty()) {
            $this->line('[spectrum-valuation-reminder] threshold_days=' . $thresholdDays . ' overdue=0');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            // Skip if a valuation_reminder notification was already queued for
            // this object in the last 7 days - avoids re-sending daily.
            if (Schema::hasTable('spectrum_workflow_notification')) {
                $recent = DB::table('spectrum_workflow_notification')
                    ->where('record_id', $row->object_id)
                    ->where('transition_key', 'valuation_reminder')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->exists();
                if ($recent) { $skipped++; continue; }
            }

            $userId = $this->getRecipientForObject((int) $row->object_id);
            if (!$userId) { $skipped++; continue; }

            $subject = 'Valuation overdue - object #' . $row->object_id;
            $message = sprintf(
                'The valuation for object #%d (last valued %s) is now older than %d days. Please schedule a new valuation.',
                $row->object_id,
                $row->valuation_date,
                $thresholdDays,
            );

            try {
                if (SpectrumNotificationService::sendEmailNotification($userId, $subject, $message)) {
                    $sent++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                Log::warning('[spectrum-valuation-reminder] send failed for obj #' . $row->object_id . ': ' . $e->getMessage());
                $skipped++;
            }
        }

        $this->line('[spectrum-valuation-reminder] threshold_days=' . $thresholdDays
            . ' overdue=' . $rows->count()
            . ' sent=' . $sent
            . ' skipped=' . $skipped);

        return self::SUCCESS;
    }

    /**
     * Best-effort recipient lookup: the object's repository's primary contact,
     * or fall back to the first admin user. Returns null when no candidate
     * exists - the caller skips silently in that case.
     */
    private function getRecipientForObject(int $objectId): ?int
    {
        // Repository contact (information_object.repository_id -> ?)
        $repoId = DB::table('information_object')->where('id', $objectId)->value('repository_id');
        if ($repoId) {
            $contact = DB::table('contact_information')
                ->where('actor_id', $repoId)
                ->where('primary_contact', 1)
                ->value('actor_id');
            if ($contact) {
                $userId = DB::table('user')->whereNotNull('email')->value('id');
                if ($userId) return (int) $userId;
            }
        }
        // Fallback: first user with an admin role.
        $admin = DB::table('user as u')
            ->join('user_group as ug', 'u.id', '=', 'ug.user_id')
            ->join('aclgroup as g', 'ug.group_id', '=', 'g.id')
            ->where('g.name', 'administrator')
            ->whereNotNull('u.email')
            ->value('u.id');
        return $admin ? (int) $admin : null;
    }
}
