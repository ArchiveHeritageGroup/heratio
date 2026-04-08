<?php

namespace AhgResearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDropdownsCommand extends Command
{
    protected $signature = 'ahg:seed-research-dropdowns';
    protected $description = 'Seed research plugin dropdown values into ahg_dropdown table (safe to run multiple times)';

    public function handle(): int
    {
        $sqlFile = __DIR__ . '/../../database/seed_dropdowns.sql';

        if (!file_exists($sqlFile)) {
            $this->error('Seed file not found: ' . $sqlFile);
            return 1;
        }

        $sql = file_get_contents($sqlFile);

        // Split by semicolons and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $count = 0;

        foreach ($statements as $stmt) {
            if (empty($stmt) || str_starts_with($stmt, '--')) {
                continue;
            }
            try {
                DB::statement($stmt);
                $count++;
            } catch (\Exception $e) {
                // INSERT IGNORE handles duplicates — other errors should be reported
                if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                    $this->warn('  Skipped: ' . substr($stmt, 0, 80) . '... (' . $e->getMessage() . ')');
                }
            }
        }

        $this->info("Seeded {$count} research dropdown entries.");
        return 0;
    }
}
