<?php

/**
 * LibraryOverdueCheckCommand — flip 'active' checkouts past due to 'overdue'
 * and optionally enqueue patron notifications.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryOverdueCheckCommand extends Command
{
    protected $signature = 'ahg:library-overdue-check
        {--days=1 : Items overdue by at least N days}
        {--notify : Send notification to patrons}';

    protected $description = 'Scan overdue checkouts and (optionally) notify';

    public function handle(): int
    {
        if (! Schema::hasTable('library_checkout')) { $this->warn('library_checkout missing'); return self::SUCCESS; }
        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->copy()->subDays($days)->toDateString();

        $eligible = DB::table('library_checkout')
            ->where('status', 'active')
            ->whereNull('return_date')
            ->where('due_date', '<', $cutoff);

        $rows = (clone $eligible)->get(['id', 'patron_id', 'copy_id', 'due_date']);
        $this->info("flagging {$rows->count()} overdue checkouts");
        if ($rows->isEmpty()) return self::SUCCESS;

        $eligible->update(['status' => 'overdue']);

        if ($this->option('notify') && Schema::hasTable('research_notification')) {
            $now = now();
            $payload = $rows->map(fn ($r) => [
                'user_id' => $this->resolvePatronUserId($r->patron_id),
                'type' => 'library_overdue',
                'title' => 'Library checkout overdue',
                'message' => "checkout #{$r->id} due {$r->due_date}",
                'data' => json_encode(['checkout_id' => $r->id, 'due_date' => $r->due_date]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->filter(fn ($p) => $p['user_id'] !== null)->values()->toArray();
            if (! empty($payload)) {
                DB::table('research_notification')->insert($payload);
                $this->info('queued ' . count($payload) . ' patron notifications');
            }
        }
        return self::SUCCESS;
    }

    protected function resolvePatronUserId(int $patronId): ?int
    {
        return DB::table('library_patron')
            ->where('id', $patronId)
            ->join('actor', 'actor.id', '=', 'library_patron.actor_id')
            ->join('user', 'user.id', '=', 'actor.id')
            ->value('user.id');
    }
}
