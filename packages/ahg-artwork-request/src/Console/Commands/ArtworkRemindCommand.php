<?php

/*
 * Overdue + due-soon reminders for artwork placements (#1459, ports #278).
 *
 * Scheduled through CronSchedulerService. No-ops cleanly when the tables are
 * absent (a minimal install without the package having booted its migration),
 * so registering it in the default schedule is harmless everywhere.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgArtworkRequest\Console\Commands;

use AhgArtworkRequest\Services\ArtworkRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ArtworkRemindCommand extends Command
{
    protected $signature = 'artwork:remind
        {--every=7 : Do not chase the same overdue placement more often than this many days}
        {--within=7 : How far ahead a due-soon courtesy reminder looks (days)}';

    protected $description = 'Send overdue and due-soon reminders for artwork placements';

    public function handle(): int
    {
        if (! Schema::hasTable('artwork_request')) {
            $this->info('artwork_request table absent; nothing to do.');

            return self::SUCCESS;
        }

        $every = (int) $this->option('every');
        $within = (int) $this->option('within');

        $overdue = ArtworkRequestService::sendOverdueReminders($every);
        $dueSoon = ArtworkRequestService::sendDueSoonReminders($within, $every);

        $this->info("Artwork reminders sent: {$overdue} overdue, {$dueSoon} due-soon.");

        return self::SUCCESS;
    }
}
