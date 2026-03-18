<?php

namespace Ahg3dModel\Commands;

use Illuminate\Console\Command;

class ThreeDMultiangleCommand extends Command
{
    protected $signature = 'ahg:3d-multiangle {--id=} {--force} {--describe} {--dry-run}';
    protected $description = 'Generate 6 multi-angle renders';

    public function handle(): int
    {
        $this->info('Starting 3D multi-angle render generation...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
