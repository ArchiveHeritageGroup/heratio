<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class PreservationPackageCommand extends Command
{
    protected $signature = 'ahg:preservation-package
        {--type=aip : Package type (sip, aip, dip)}
        {--slug= : Information object slug}
        {--output= : Output directory}
        {--include-derivatives : Include derivative files}';

    protected $description = 'Generate OAIS packages (BagIt)';

    public function handle(): int
    {
        $this->info('Generating OAIS package...');
        // TODO: Implement OAIS package generation (BagIt)
        $this->info('OAIS package generation complete.');
        return 0;
    }
}
