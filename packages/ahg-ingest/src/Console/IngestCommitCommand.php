<?php

/**
 * IngestCommitCommand — Heratio ingest
 *
 * Kicks the wizard commit runner for one session. Usable from a queue
 * worker or cron; also invoked directly by the web controller when an
 * admin clicks "Start commit" on the commit page.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgIngest\Console;

use AhgIngest\Services\IngestCommitRunner;
use Illuminate\Console\Command;

class IngestCommitCommand extends Command
{
    protected $signature = 'ahg:ingest-commit
        {session : ingest_session.id to commit}';

    protected $description = 'Run the commit step for a wizard ingest session: create IOs, attach files, build packages';

    public function handle(IngestCommitRunner $runner): int
    {
        $sessionId = (int) $this->argument('session');
        if ($sessionId < 1) {
            $this->error('session id must be > 0');
            return self::FAILURE;
        }
        $this->info("Committing ingest session #{$sessionId}...");
        $result = $runner->run($sessionId);
        $this->info(sprintf(
            '  → job #%d: %d row(s) processed, %d IO(s) created, %d error(s)',
            $result['job_id'], $result['processed'], $result['created'], $result['errors']
        ));
        return self::SUCCESS;
    }
}
