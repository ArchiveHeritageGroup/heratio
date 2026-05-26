<?php

/**
 * cron-monitoring config - issue #673 Phase 2.
 *
 * `high_priority_commands`
 *   Commands whose missed runs escalate to a Workbench notification (spool
 *   drop into /var/spool/workbench/notifications/). Anything not in this
 *   list still lands in ahg_cron_missed_run + heratio_cron_missed_runs_total
 *   but stays quiet.
 *
 * `notification_user`
 *   Username for the Workbench notification drop. Override per-host with
 *   the HERATIO_CRON_NOTIFY_USER env var.
 *
 * `inbox_path`
 *   Spool directory. Mirrors the rule the inboxWatcher service watches
 *   (/var/spool/workbench/notifications/). Override with
 *   WORKBENCH_NOTIFICATIONS_INBOX so test harnesses can point elsewhere.
 *
 * `miss_threshold_multiplier`
 *   Flag a command as missed when the gap between expected_at and the
 *   most recent ahg_cron_run.finished_at exceeds N x the cron interval.
 *   Default 2 keeps the detector tolerant of one slow tick (e.g. a job
 *   that ran 90s into a *5 * * * * window) but catches outright failures.
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

return [
    'high_priority_commands' => [
        // Operator-visible breaks: search/index pipelines, AI queues,
        // SharePoint webhook renewal (12h hard expiry), backups, fixity.
        'ahg:search-update',
        'ahg:search-populate',
        'ahg:preservation-fixity --age=30 --report',
        'ahg:backup --components=database --retention=30',
        'sharepoint:renew-subscriptions',
        'sharepoint:auto-ingest',
        'ahg:services-check',
        'ahg:llm-health',
        'ahg:integrity-schedule --run-due',
    ],

    'notification_user' => env('HERATIO_CRON_NOTIFY_USER', 'johan'),

    'inbox_path' => env(
        'WORKBENCH_NOTIFICATIONS_INBOX',
        '/var/spool/workbench/notifications'
    ),

    'miss_threshold_multiplier' => (float) env('HERATIO_CRON_MISS_MULTIPLIER', 2.0),
];
