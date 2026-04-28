<?php

/**
 * SearchUpdateCommand — incremental ES reindex of records modified since a timestamp.
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
        'actor'             => 'actor',
        'term'              => 'term',
        'repository'        => 'repository',
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
            if (! isset($this->tables[$type])) { $this->warn("unknown type: {$type}"); continue; }
            $table = $this->tables[$type];
            $ids = DB::table($table)->where('updated_at', '>=', $since)->pluck('id');
            $this->info("{$type}: {$ids->count()} records since {$since->toIso8601String()}");
            foreach ($ids as $id) {
                $this->call('ahg:es-reindex', ['--index' => $type, '--id' => $id]);
                $total++;
            }
        }
        $this->info("indexed={$total}");
        return self::SUCCESS;
    }
}
