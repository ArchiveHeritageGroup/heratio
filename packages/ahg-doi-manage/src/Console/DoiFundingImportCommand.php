<?php

/**
 * DoiFundingImportCommand - bulk-import FundingReference rows from CSV
 *
 * Operator path for #654 Phase 2. The IO edit page is locked end-to-end
 * (see memory/feedback_lock_io_show_tree.md + .locked-paths), so funding
 * data is captured out-of-band via:
 *
 *   php artisan doi:funding-import /path/to/funding.csv
 *
 * CSV header (required, in any order):
 *   information_object_id, funder_name, funder_identifier,
 *   funder_identifier_type, award_number, award_uri, award_title
 *
 * Idempotent on (information_object_id, funder_name, award_number) -
 * re-running the same CSV won't duplicate rows.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DoiFundingImportCommand extends Command
{
    protected $signature = 'doi:funding-import
        {path : Absolute path to the CSV file}
        {--dry-run : Parse + validate only, do not insert}
        {--delimiter=, : Field delimiter (default comma)}';

    protected $description = 'Bulk-import FundingReference rows into ahg_io_funding from a CSV file';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('File not found or not readable: '.$path);

            return self::FAILURE;
        }

        if (! Schema::hasTable('ahg_io_funding')) {
            $this->error('ahg_io_funding table is missing. Restart the app to trigger the auto-install in AhgDoiManageServiceProvider.');

            return self::FAILURE;
        }

        $delimiter = (string) ($this->option('delimiter') ?: ',');
        $dryRun = (bool) $this->option('dry-run');

        $fh = fopen($path, 'r');
        if (! $fh) {
            $this->error('Could not open file: '.$path);

            return self::FAILURE;
        }

        $headerRow = fgetcsv($fh, 0, $delimiter);
        if (! $headerRow) {
            $this->error('CSV is empty.');
            fclose($fh);

            return self::FAILURE;
        }
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $headerRow);

        $required = ['information_object_id', 'funder_name'];
        foreach ($required as $col) {
            if (! in_array($col, $header, true)) {
                $this->error('Missing required CSV column: '.$col);
                fclose($fh);

                return self::FAILURE;
            }
        }

        $allowed = ['information_object_id', 'funder_name', 'funder_identifier',
            'funder_identifier_type', 'award_number', 'award_uri', 'award_title'];

        $ok = 0;
        $skipped = 0;
        $errors = 0;
        $lineNo = 1;  // header was line 1
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $lineNo++;
            if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = @array_combine($header, $row);
            if (! is_array($assoc)) {
                $this->warn('Line '.$lineNo.': column count mismatch, skipping');
                $errors++;

                continue;
            }
            $ioId = (int) ($assoc['information_object_id'] ?? 0);
            $funder = trim((string) ($assoc['funder_name'] ?? ''));
            if ($ioId <= 0 || $funder === '') {
                $this->warn('Line '.$lineNo.': missing information_object_id or funder_name, skipping');
                $errors++;

                continue;
            }

            $data = [];
            foreach ($allowed as $col) {
                if (isset($assoc[$col])) {
                    $data[$col] = trim((string) $assoc[$col]) === '' ? null : trim((string) $assoc[$col]);
                }
            }
            $data['information_object_id'] = $ioId;

            // Idempotency check
            $exists = DB::table('ahg_io_funding')
                ->where('information_object_id', $ioId)
                ->where('funder_name', $funder)
                ->where(function ($q) use ($data) {
                    if (! empty($data['award_number'])) {
                        $q->where('award_number', $data['award_number']);
                    } else {
                        $q->whereNull('award_number');
                    }
                })
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                DB::table('ahg_io_funding')->insert($data);
            }
            $ok++;
        }
        fclose($fh);

        $this->info(sprintf('%s: inserted=%d skipped(duplicate)=%d errors=%d',
            $dryRun ? 'DRY RUN' : 'IMPORT', $ok, $skipped, $errors));

        return self::SUCCESS;
    }
}
