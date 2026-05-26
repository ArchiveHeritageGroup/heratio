<?php

/**
 * metrics:back-fill - pivot a per-collection accuracy CSV into ai_model_registry.
 *
 * Issue #654 Phase 3 (last-mile of the original acceptance list - "per-collection
 * accuracy metrics back-fill"). CSV columns are:
 *
 *     service,collection,accuracy
 *
 * Each row updates ai_model_registry.accuracy_metrics_json on the current
 * (retired_at IS NULL) row for that service, merging a 'per_collection'
 * map of collection-slug => accuracy into the existing JSON without
 * disturbing any other keys. Use --dry-run to print the diff without
 * committing.
 *
 * Note: this command lives in ahg-doi-manage because Phase 3 was tightly
 * scoped to this package (per #654 task brief). The data it writes is
 * owned by ahg-ai-compliance; that package keeps full schema ownership.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MetricsBackfillCommand extends Command
{
    protected $signature = 'metrics:back-fill
        {file : path to the CSV (header row required: service,collection,accuracy)}
        {--dry-run : print the merge without writing to ai_model_registry}';

    protected $description = 'Issue #654 - back-fill per-collection accuracy metrics into ai_model_registry.accuracy_metrics_json';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSV not readable: {$path}");
            return self::FAILURE;
        }

        if (! Schema::hasTable('ai_model_registry')) {
            $this->error('ai_model_registry table is missing - install ahg-ai-compliance first');
            return self::FAILURE;
        }

        $fh = fopen($path, 'rb');
        if (! $fh) {
            $this->error("Failed to open: {$path}");
            return self::FAILURE;
        }

        $header = fgetcsv($fh);
        if (! $header || count($header) < 3) {
            fclose($fh);
            $this->error('CSV must have a header row with at least service,collection,accuracy');
            return self::FAILURE;
        }
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);
        $iSvc = array_search('service', $header, true);
        $iCol = array_search('collection', $header, true);
        $iAcc = array_search('accuracy', $header, true);
        if ($iSvc === false || $iCol === false || $iAcc === false) {
            fclose($fh);
            $this->error('CSV header must include columns: service, collection, accuracy');
            return self::FAILURE;
        }

        $perService = [];
        $rowNo = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $rowNo++;
            if (count($row) < 3) {
                continue;
            }
            $service = trim((string) $row[$iSvc]);
            $collection = trim((string) $row[$iCol]);
            $accuracy = (float) $row[$iAcc];
            if ($service === '' || $collection === '') {
                $this->warn("row {$rowNo}: skipped (missing service/collection)");
                continue;
            }
            $perService[$service][$collection] = $accuracy;
        }
        fclose($fh);

        if (empty($perService)) {
            $this->warn('No rows parsed from CSV.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $touched = 0;
        $missing = 0;

        foreach ($perService as $service => $perCollection) {
            $model = DB::table('ai_model_registry')
                ->where('service', $service)
                ->whereNull('retired_at')
                ->orderByDesc('deployed_at')
                ->first();
            if (! $model) {
                $this->warn("service '{$service}': no active ai_model_registry row");
                $missing++;
                continue;
            }

            $existing = [];
            if (! empty($model->accuracy_metrics_json)) {
                $decoded = is_string($model->accuracy_metrics_json)
                    ? json_decode($model->accuracy_metrics_json, true)
                    : $model->accuracy_metrics_json;
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }

            $existing['per_collection'] = array_merge(
                $existing['per_collection'] ?? [],
                $perCollection,
            );
            $existing['per_collection_last_updated'] = now()->toIso8601String();

            $this->line(sprintf(
                '  service=%s id=%d collections=%d',
                $service,
                (int) $model->id,
                count($perCollection),
            ));

            if ($dry) {
                continue;
            }

            try {
                DB::table('ai_model_registry')
                    ->where('id', $model->id)
                    ->update([
                        'accuracy_metrics_json' => json_encode($existing),
                        'updated_at' => now(),
                    ]);
                $touched++;
            } catch (Throwable $e) {
                $this->error("service '{$service}': update failed - ".$e->getMessage());
            }
        }

        $this->info(sprintf(
            'Done. services_updated=%d services_missing=%d%s',
            $touched, $missing, $dry ? ' [dry-run]' : '',
        ));

        return self::SUCCESS;
    }
}
