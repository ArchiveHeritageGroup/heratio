<?php

/**
 * ahg:office-pdfs - heratio#1627: give every Word, Excel, PowerPoint,
 * OpenDocument and RTF master a PDF display copy, so the record viewer shows
 * its pages and its text is searchable. Masters without one are queued for
 * GenerateOfficePdfJob, or converted straight away with --now.
 *
 * Runs nightly (01:45) with --missing. A master whose conversion failed is
 * left out for a week, so it cannot hold up the rest.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgCore\Commands;

use AhgCore\Jobs\GenerateOfficePdfJob;
use AhgCore\Services\OfficeDisplayCopyService;
use Illuminate\Console\Command;

class OfficePdfsCommand extends Command
{
    protected $signature = 'ahg:office-pdfs
        {--missing : Only office masters with no PDF display copy (the default)}
        {--limit=500 : At most this many masters per run}
        {--now : Convert straight away instead of queueing the job}
        {--dry-run : List them, change nothing}';

    protected $description = 'Give every office document master a PDF display copy';

    public function handle(): int
    {
        $masters = OfficeDisplayCopyService::missing((int) $this->option('limit'));
        $this->info($masters->count().' office master(s) without a PDF display copy');
        foreach ($masters as $m) {
            if ($this->option('dry-run')) {
                $this->line("  {$m->id} {$m->mime_type} {$m->name}");

                continue;
            }
            if ($this->option('now')) {
                $id = OfficeDisplayCopyService::make((int) $m->id);
                $this->line("  {$m->id} {$m->name}: ".($id ? 'PDF copy made' : 'no PDF copy'));
            } else {
                GenerateOfficePdfJob::dispatch((int) $m->id);
            }
        }
        if (! $this->option('dry-run') && ! $this->option('now') && $masters->count()) {
            $this->info('Queued for the worker.');
        }

        return self::SUCCESS;
    }
}
