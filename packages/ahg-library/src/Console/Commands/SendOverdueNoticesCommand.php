<?php

/**
 * SendOverdueNoticesCommand - render + send tiered overdue notices (#1093).
 *
 * Scheduled daily. Honours library_overdue_notices_enabled; renders the
 * library_notice_template ladder and logs each send to
 * library_overdue_notice_log. --dry-run renders + logs without sending mail.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\LibraryOverdueNoticeService;
use AhgLibrary\Support\LibrarySettings;
use Illuminate\Console\Command;

class SendOverdueNoticesCommand extends Command
{
    protected $signature = 'ahg:library-overdue-notices {--dry-run : Render and log without sending email}';

    protected $description = 'Send tiered overdue notices to patrons with past-due loans and log each send.';

    public function handle(LibraryOverdueNoticeService $notices): int
    {
        if (!LibrarySettings::overdueNoticesEnabled()) {
            $this->info('library_overdue_notices_enabled is off; nothing to do.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $stats = $notices->processOverdue($dryRun);

        $this->info(sprintf(
            '%sOverdue notices: %d candidate(s), %d sent, %d skipped, %d failed.',
            $dryRun ? '[dry-run] ' : '',
            $stats['candidates'],
            $stats['sent'],
            $stats['skipped'],
            $stats['failed'],
        ));

        return self::SUCCESS;
    }
}
