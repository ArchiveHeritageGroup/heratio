<?php

/**
 * AiInstallCommand - create/update the AI plugin database tables by executing
 * the package's database/install.sql (CREATE TABLE IF NOT EXISTS + seed
 * INSERTs). Idempotent: re-running is safe.
 *
 * This is the explicit operator-side counterpart to the auto-install that the
 * service provider performs on first boot.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiInstallCommand extends Command
{
    protected $signature = 'ahg:ai-install {--dry-run : Parse + count statements, execute nothing}';
    protected $description = 'Create/update AI plugin database tables';

    public function handle(): int
    {
        $sqlPath = __DIR__ . '/../../database/install.sql';
        $sql = @file_get_contents($sqlPath);
        if ($sql === false || trim($sql) === '') {
            $this->error("install.sql not found or empty: {$sqlPath}");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info('Installing AI plugin tables from install.sql' . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $ok = 0;
        $errors = 0;
        $skipped = 0;

        // Same statement-splitting + comment-stripping the service provider uses.
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $lines = preg_split("/\r?\n/", trim($stmt));
            while ($lines && (trim($lines[0]) === '' || str_starts_with(ltrim($lines[0]), '--'))) {
                array_shift($lines);
            }
            $stmt = trim(implode("\n", $lines));
            if ($stmt === '') {
                continue;
            }

            if ($dryRun) {
                $ok++;
                continue;
            }

            try {
                DB::statement($stmt);
                $ok++;
            } catch (Throwable $e) {
                // Idempotent re-runs may collide with already-present rows /
                // FKs; report but keep going so the rest of the schema lands.
                $msg = $e->getMessage();
                if (stripos($msg, 'already exists') !== false || stripos($msg, 'Duplicate') !== false) {
                    $skipped++;
                } else {
                    $errors++;
                    $this->warn(mb_substr($msg, 0, 200));
                }
            }
        }

        $this->info(sprintf('Statements ok: %d, skipped (already present): %d, errors: %d', $ok, $skipped, $errors));
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
