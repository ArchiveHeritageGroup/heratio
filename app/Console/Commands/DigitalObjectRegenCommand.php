<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DigitalObjectRegenCommand extends Command
{
    protected $signature = 'heratio:digitalobject:regen-derivatives
                            {--slug= : Only regenerate derivatives for this slug}
                            {--force : Force regeneration even if derivatives exist}
                            {--only-externals : Only regenerate external digital objects}';

    protected $description = 'Regenerate digital object derivatives (thumbnails, reference images)';

    public function handle(): int
    {
        $this->info('Regenerating digital object derivatives...');

        $cmd = 'php /usr/share/nginx/archive/symfony digitalobject:regen-derivatives';

        if ($slug = $this->option('slug')) {
            $cmd .= ' --slug=' . escapeshellarg($slug);
        }

        if ($this->option('force')) {
            $cmd .= ' --force';
        }

        if ($this->option('only-externals')) {
            $cmd .= ' --only-externals';
        }

        passthru($cmd, $exitCode);

        if ($exitCode === 0) {
            $this->info('Derivative regeneration completed.');
        } else {
            $this->error('Derivative regeneration failed with exit code: ' . $exitCode);
        }

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
