<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VersionCommand extends Command
{
    protected $signature = 'heratio:version';

    protected $description = 'Show the current Heratio version';

    public function handle(): int
    {
        $versionFile = base_path('version.json');

        if (! file_exists($versionFile)) {
            $this->error('version.json not found at: ' . $versionFile);

            return self::FAILURE;
        }

        $version = json_decode(file_get_contents($versionFile), true);

        if (! $version || ! isset($version['version'])) {
            $this->error('Invalid version.json format.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Heratio v{$version['version']}");

        if (isset($version['release_date'])) {
            $this->line("  Release date: {$version['release_date']}");
        }

        if (isset($version['description'])) {
            $this->line("  {$version['description']}");
        }

        if (isset($version['name'])) {
            $this->line("  Package: {$version['name']}");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
