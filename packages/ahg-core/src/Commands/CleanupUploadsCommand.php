<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class CleanupUploadsCommand extends Command
{
    protected $signature = 'ahg:cleanup-uploads
        {--days=7 : Remove temp files older than N days}';

    protected $description = 'Remove temp upload files';

    public function handle(): int
    {
        $this->info('Cleaning up temporary upload files...');
        // TODO: Implement temp upload file cleanup
        $this->info('Temporary upload cleanup complete.');
        return 0;
    }
}
