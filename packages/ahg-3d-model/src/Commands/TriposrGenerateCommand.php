<?php

namespace Ahg3dModel\Commands;

use Illuminate\Console\Command;

class TriposrGenerateCommand extends Command
{
    protected $signature = 'ahg:triposr-generate {--image=} {--object-id=} {--import} {--remove-bg=true} {--resolution=256} {--texture} {--health} {--preload} {--stats} {--jobs}';
    protected $description = 'Generate 3D models from 2D images';

    public function handle(): int
    {
        $this->info('Starting TripoSR 3D model generation...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
