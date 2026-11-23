<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDataMigration\Console;

use AhgDataMigration\SourceMigration\MappingFile;
use AhgDataMigration\SourceMigration\SourceDetector;
use AhgDataMigration\SourceMigration\SourceMigrationPreflight;
use AhgDataMigration\SourceMigration\SourceMigrationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** heratio#1495: preflight, acknowledge and commit a vendor export. */
class SourceMigrateCommand extends Command
{
    protected $signature = 'ahg:source-migrate
        {action : detect, preflight, show, acknowledge or commit}
        {--file= : Export file (detect, preflight)}
        {--mapping= : Mapping name; detected when omitted}
        {--parent= : Parent description id (default: top level)}
        {--repository= : Repository id}
        {--run= : Run id (show, acknowledge, commit)}
        {--user= : Id of the user acknowledging}
        {--decide=* : Answer to an open decision, key=option}
        {--out= : Write the register as JSON to this path}';

    protected $description = 'Migrate a vendor export through the preflight data-loss register';

    public function handle(): int
    {
        try {
            return match ($this->argument('action')) {
                'detect' => $this->detect(),
                'preflight' => $this->runPreflight(),
                'show' => $this->showRun((int) $this->option('run')),
                'acknowledge' => $this->acknowledge(),
                'commit' => $this->commit(),
                default => $this->refuse('Unknown action.'),
            };
        } catch (\Throwable $e) {
            return $this->refuse($e->getMessage());
        }
    }

    private function detect(): int
    {
        $found = $this->detected();
        if ($found === null) {
            return $this->refuse('No known source matches this file.');
        }
        $this->line("{$found['label']} (confidence {$found['confidence']})");

        return self::SUCCESS;
    }

    private function runPreflight(): int
    {
        $mapping = $this->option('mapping') ?: ($this->detected()['source'] ?? null);
        if (! $mapping) {
            return $this->refuse('Could not detect the source. Pass --mapping ('.implode(', ', MappingFile::available()).').');
        }
        $id = (new SourceMigrationPreflight)->run((string) $this->option('file'), (string) $mapping, array_filter([
            'parent_id' => $this->option('parent'),
            'repository_id' => $this->option('repository'),
        ]));

        return $this->showRun($id);
    }

    private function showRun(int $id): int
    {
        $run = DB::table('ahg_source_migration_run')->where('id', $id)->first();
        if (! $run) {
            return $this->refuse("No migration run {$id}.");
        }
        $register = json_decode($run->register_json, true);
        if ($out = $this->option('out')) {
            file_put_contents($out, json_encode($register, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $this->info("Run {$id}: {$run->status}. {$register['records_to_create']} records, ".count($register['synthesised_levels']).' synthesised.');
        $this->table(['Field', 'Action', 'Target', 'Rows', 'Reason'], array_map(
            fn ($f) => [$f['field'], $f['action'], $f['target'] ?? '-', $f['rows_with_value'], $f['reason'] ?? ''],
            array_values(array_filter($register['fields'], fn ($f) => $f['action'] !== 'mapped'))
        ));
        foreach ($register['excluded_record_types'] as $type => $count) {
            $this->warn("Not imported: {$count} {$type} row(s).");
        }
        foreach ($register['open_decisions'] as $d) {
            $this->warn("Open decision {$d['key']}: {$d['question']} [".implode(', ', $d['options']).']');
        }
        foreach ($register['blockers'] as $b) {
            $this->error("{$b['code']}: {$b['ref']} - {$b['detail']}");
        }

        return $register['blockers'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function acknowledge(): int
    {
        $decisions = [];
        foreach ((array) $this->option('decide') as $pair) {
            [$key, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $decisions[trim($key)] = trim($value);
        }
        (new SourceMigrationRunner)->acknowledge((int) $this->option('run'), (int) $this->option('user'), $decisions);
        $this->info('Register acknowledged.');

        return self::SUCCESS;
    }

    private function commit(): int
    {
        $count = (new SourceMigrationRunner)->commit((int) $this->option('run'));
        $this->info("Committed {$count} records as Draft.");

        return self::SUCCESS;
    }

    private function detected(): ?array
    {
        $file = (string) $this->option('file');
        if (! is_file($file)) {
            throw new \InvalidArgumentException('Source file cannot be read.');
        }
        $fh = fopen($file, 'r');
        $line = (string) fgets($fh);
        fclose($fh);
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
        $delimiter = collect([',', ';', "\t", '|'])->sortByDesc(fn ($d) => substr_count($line, $d))->first();

        return (new SourceDetector)->detect(str_getcsv(trim($line), $delimiter));
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
