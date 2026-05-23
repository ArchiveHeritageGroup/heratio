<?php

/**
 * SpectrumOverdueCommand - Phase C4: scan for overdue Spectrum tasks and drop
 * Workbench notifications into the spool directory.
 *
 * Usage:
 *   php artisan spectrum:overdue                    # 14-day default threshold
 *   php artisan spectrum:overdue --days=30          # custom threshold
 *   php artisan spectrum:overdue --notify=johan     # send notifications to this Workbench user
 *   php artisan spectrum:overdue --dry-run          # log only, no notifications
 *
 * Notification format per CLAUDE.md global instructions:
 *   /var/spool/workbench/notifications/<unique>.json
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgWorkflow\Console\Commands;

use AhgWorkflow\Services\SpectrumComplianceService;
use AhgWorkflow\Services\SpectrumProcedureCatalog;
use Illuminate\Console\Command;

class SpectrumOverdueCommand extends Command
{
    protected $signature = 'spectrum:overdue
        {--days=14 : Overdue threshold in days (default 14)}
        {--notify= : Workbench username to notify (defaults to no notification)}
        {--inbox= : Override the Workbench notification inbox path (default /var/spool/workbench/notifications)}
        {--dry-run : Log overdue items but do not write notifications}';

    protected $description = 'Scan for Spectrum-tagged workflow tasks past the overdue threshold; drop Workbench notifications for the configured user.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $username = (string) ($this->option('notify') ?: '');
        $inbox = (string) ($this->option('inbox') ?: '/var/spool/workbench/notifications');
        $dryRun = (bool) $this->option('dry-run');

        $svc = new SpectrumComplianceService();
        $overdue = $svc->findOverdue($days);

        $this->info(sprintf('Found %d overdue Spectrum task(s) past %d-day threshold.', count($overdue), $days));

        if ($dryRun) {
            foreach ($overdue as $row) {
                $this->line(sprintf(
                    '  task=%d object=%d procedure=%s created=%s',
                    $row->task_id, $row->object_id, $row->spectrum_procedure, $row->created_at
                ));
            }
            $this->warn('DRY RUN — no notifications written.');
            return self::SUCCESS;
        }

        if (count($overdue) === 0) {
            return self::SUCCESS;
        }

        if ($username === '') {
            $this->warn('No --notify=<username> set; skipping notification drop. Run with --notify=<name> to send notifications.');
            return self::SUCCESS;
        }

        if (!is_dir($inbox)) {
            $this->error("Workbench inbox directory does not exist: $inbox");
            return self::FAILURE;
        }

        // Group by procedure for a single summary notification (vs spamming N notifications)
        $byProcedure = [];
        foreach ($overdue as $row) {
            $byProcedure[$row->spectrum_procedure][] = $row;
        }

        $written = 0;
        foreach ($byProcedure as $procedure => $rows) {
            $label = SpectrumProcedureCatalog::label($procedure);
            $count = count($rows);
            $oldest = collect($rows)->min('created_at');

            $payload = [
                'username'     => $username,
                'title'        => sprintf('Spectrum overdue: %s (%d task%s)', $label, $count, $count === 1 ? '' : 's'),
                'message'      => sprintf(
                    'There %s %d %s task%s past the %d-day overdue threshold. Oldest started %s.',
                    $count === 1 ? 'is' : 'are', $count, $label, $count === 1 ? '' : 's', $days, $oldest
                ),
                'eventType'    => 'spectrum_overdue',
                'webLink'      => sprintf('/spectrum/dashboard?overdue_days=%d', $days),
                'deadlineHint' => null,
            ];

            $tmp = $inbox.'/heratio-spectrum-'.$procedure.'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.json';
            if (file_put_contents($tmp, json_encode($payload, JSON_PRETTY_PRINT)) !== false) {
                $written++;
                $this->info("  + wrote notification: $tmp ($count overdue $label)");
            } else {
                $this->error("  ! failed to write: $tmp");
            }
        }

        $this->info("Wrote $written notification(s) to $inbox");
        return self::SUCCESS;
    }
}
