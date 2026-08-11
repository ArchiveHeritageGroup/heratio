<?php

/**
 * SearchUpdateCommand - incremental ES reindex of records modified since a timestamp.
 *
 * Walks information_object / actor / term / repository for rows whose
 * updated_at is newer than --since, then re-indexes each by delegating
 * to ahg:es-reindex --index=… --id=…. Cheap because the rich command
 * already knows the mapping; this one only schedules.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SearchUpdateCommand extends Command
{
    protected $signature = 'ahg:search-update
        {--since= : Only update records modified since this datetime}
        {--type= : Only update a specific document type}';

    protected $description = 'Incremental search index update (delegates to ahg:es-reindex)';

    protected array $tables = [
        'informationobject' => 'information_object',
        'actor' => 'actor',
        'term' => 'term',
        'repository' => 'repository',
    ];

    public function handle(): int
    {
        $since = $this->option('since')
            ? Carbon::parse((string) $this->option('since'))
            : Carbon::now()->subDay();

        $only = (string) ($this->option('type') ?? '');
        $types = $only ? [$only] : array_keys($this->tables);

        $total = 0;
        foreach ($types as $type) {
            if (! isset($this->tables[$type])) {
                $this->warn("unknown type: {$type}");

                continue;
            }
            $table = $this->tables[$type];
            // Timestamps live on `object`, not on the per-class tables - all
            // four of these are QubitObject subclasses sharing its id, so the
            // modified-since filter has to join across. Querying
            // information_object.updated_at (etc.) directly threw
            // "Unknown column 'updated_at'" and every --since run died here.
            $ids = DB::table($table)
                ->join('object', 'object.id', '=', $table . '.id')
                ->where('object.updated_at', '>=', $since)
                ->orderBy($table . '.id')
                ->pluck($table . '.id');
            $this->info("{$type}: {$ids->count()} records since {$since->toIso8601String()}");
            foreach ($ids as $id) {
                // --no-progress: the delegate draws a progress bar per record,
                // which is pure noise when the caller is a loop.
                $this->call('ahg:es-reindex', [
                    '--index' => $type,
                    '--id' => $id,
                    '--no-progress' => true,
                ]);
                $total++;
            }
        }
        $this->info("indexed={$total}");

        return self::SUCCESS;
    }
}
