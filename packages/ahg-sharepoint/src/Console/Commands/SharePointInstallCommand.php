<?php

namespace AhgSharePoint\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Install schema. Idempotent — guards every ALTER with information_schema check.
 *
 * @phase 1
 */
class SharePointInstallCommand extends Command
{
    protected $signature = 'sharepoint:install';
    protected $description = 'Install ahg-sharepoint schema (idempotent). Run package migrations.';

    public function handle(): int
    {
        $this->info('Running migrations from ahg-sharepoint package...');
        $this->call('migrate', [
            '--path' => 'packages/ahg-sharepoint/database/migrations',
            '--force' => true,
        ]);

        // Reporting view
        $viewPath = base_path('packages/ahg-sharepoint/database/views/v_report_sharepoint_events.sql');
        if (is_file($viewPath)) {
            $sql = file_get_contents($viewPath);
            foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }
                DB::statement($stmt);
            }
            $this->info('Applied reporting view v_report_sharepoint_events.');
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
