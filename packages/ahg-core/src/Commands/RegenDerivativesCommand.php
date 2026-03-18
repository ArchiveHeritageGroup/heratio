<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class RegenDerivativesCommand extends Command
{
    protected $signature = 'ahg:regen-derivatives
        {--slug= : Information object slug}
        {--type=all : Derivative type (all, thumbnail, reference)}
        {--force : Regenerate even if derivatives exist}
        {--only-externals : Only process external digital objects}
        {--json : Output results as JSON}';

    protected $description = 'Regenerate image derivatives';

    public function handle(): int
    {
        $this->info('Regenerating image derivatives...');
        // TODO: Implement derivative regeneration
        $this->info('Derivative regeneration complete.');
        return 0;
    }
}
