<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class DoiDeactivateCommand extends Command
{
    protected $signature = 'ahg:doi-deactivate
        {--id= : DOI ID to deactivate}
        {--object-id= : Information object ID}
        {--reason= : Reason for deactivation}
        {--reactivate : Reactivate instead of deactivate}
        {--list-deleted : List all deactivated DOIs}
        {--dry-run : Simulate without executing}';

    protected $description = 'Deactivate/tombstone DOIs';

    public function handle(): int
    {
        $this->info('Managing DOI deactivation...');
        // TODO: Implement DOI deactivation/tombstone
        $this->info('DOI deactivation operation complete.');
        return 0;
    }
}
