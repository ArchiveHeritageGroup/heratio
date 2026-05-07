<?php

/**
 * SpectrumConditionCheckReminderCommand - Heratio
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
 * Emails curators when an object's most-recent condition check is older
 * than spectrum_condition_check_interval days. Closes #91 phase 2.
 *
 * Mirrors SpectrumValuationReminderCommand: same dedup window, same
 * recipient-lookup fallback, same dependency on
 * SpectrumNotificationService (which gates on spectrum_email_notifications).
 */
class SpectrumConditionCheckReminderCommand extends Command
{
    protected $signature = 'ahg:spectrum-condition-check-reminder';
    protected $description = 'Email curators when condition checks are overdue per spectrum_condition_check_interval.';

    public function handle(): int
    {
        $settings = new SpectrumSettings();
        $intervalDays = $settings->conditionCheckIntervalDays();
        if ($intervalDays === 0) {
            $this->line('[spectrum-condition-check-reminder] interval=0 - reminders disabled.');
            return self::SUCCESS;
        }

        if (!Schema::hasTable('spectrum_condition_check') || !Schema::hasTable('object')) {
            $this->line('[spectrum-condition-check-reminder] required tables missing - skipping.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($intervalDays)->toDateString();

        // Objects whose most-recent check_date is on or before the cutoff. The
        // schema has a separate next_check_date column - if populated, we
        // honour that instead of the interval-from-last-check fallback.
        $rows = DB::table('spectrum_condition_check as c1')
            ->select('c1.object_id', 'c1.check_date', 'c1.next_check_date')
            ->whereRaw('c1.check_date = (SELECT MAX(c2.check_date) FROM spectrum_condition_check c2 WHERE c2.object_id = c1.object_id)')
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($q2) use ($cutoff) {
                    $q2->whereNull('c1.next_check_date')->where('c1.check_date', '<=', $cutoff);
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('c1.next_check_date')->where('c1.next_check_date', '<=', now()->toDateString());
                });
            })
            ->get();

        if ($rows->isEmpty()) {
            $this->line('[spectrum-condition-check-reminder] interval_days=' . $intervalDays . ' overdue=0');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (Schema::hasTable('spectrum_workflow_notification')) {
                $recent = DB::table('spectrum_workflow_notification')
                    ->where('record_id', $row->object_id)
                    ->where('transition_key', 'condition_check_reminder')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->exists();
                if ($recent) { $skipped++; continue; }
            }

            $userId = $this->getRecipientForObject((int) $row->object_id);
            if (!$userId) { $skipped++; continue; }

            $subject = 'Condition check overdue - object #' . $row->object_id;
            $message = sprintf(
                'The condition check for object #%d is overdue (last checked %s%s). Please schedule a new check.',
                $row->object_id,
                $row->check_date,
                $row->next_check_date ? ', next check was due ' . $row->next_check_date : '',
            );

            try {
                if (SpectrumNotificationService::sendEmailNotification($userId, $subject, $message)) {
                    $sent++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                Log::warning('[spectrum-condition-check-reminder] send failed for obj #' . $row->object_id . ': ' . $e->getMessage());
                $skipped++;
            }
        }

        $this->line('[spectrum-condition-check-reminder] interval_days=' . $intervalDays
            . ' overdue=' . $rows->count()
            . ' sent=' . $sent
            . ' skipped=' . $skipped);

        return self::SUCCESS;
    }

    private function getRecipientForObject(int $objectId): ?int
    {
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
        $admin = DB::table('user as u')
            ->join('user_group as ug', 'u.id', '=', 'ug.user_id')
            ->join('aclgroup as g', 'ug.group_id', '=', 'g.id')
            ->where('g.name', 'administrator')
            ->whereNotNull('u.email')
            ->value('u.id');
        return $admin ? (int) $admin : null;
    }
}
