<?php

/**
 * LibraryIllOverdueCommand — report ILL items past due_date with no return.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryIllOverdueCommand extends Command
{
    protected $signature = 'ahg:library-ill-overdue
        {--days=1 : Items overdue by at least N days}';

    protected $description = 'Report overdue ILL items';

    public function handle(): int
    {
        if (! Schema::hasTable('library_ill_request')) { $this->warn('library_ill_request missing'); return self::SUCCESS; }
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->copy()->subDays($days)->toDateString();

        $rows = DB::table('library_ill_request')
            ->whereIn('status', ['received', 'shipped'])
            ->whereNull('return_date')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $cutoff)
            ->orderBy('due_date')
            ->get(['id', 'request_number', 'direction', 'partner_library', 'title', 'due_date', 'status']);

        $this->info("=== ILL overdue (>= {$days}d) — {$rows->count()} ===");
        foreach ($rows as $r) {
            $this->line(sprintf('  #%-12s  %-8s  due=%s  partner=%s  %s',
                $r->request_number, $r->direction, $r->due_date,
                mb_strimwidth((string) ($r->partner_library ?? ''), 0, 30, '..'),
                mb_strimwidth((string) ($r->title ?? ''), 0, 50, '..')));
        }
        return self::SUCCESS;
    }
}
