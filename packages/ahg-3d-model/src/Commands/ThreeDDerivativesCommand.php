<?php

namespace Ahg3dModel\Commands;

use Illuminate\Console\Command;

class ThreeDDerivativesCommand extends Command
{
    protected $signature = 'ahg:3d-derivatives {--id=} {--force} {--dry-run}';
    protected $description = 'Generate 3D model thumbnails via Blender';

    public function handle(): int
    {
        $this->info('Starting 3D model derivative generation...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
