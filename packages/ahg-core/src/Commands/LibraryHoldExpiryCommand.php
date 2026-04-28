<?php

/**
 * LibraryHoldExpiryCommand — expire unfulfilled holds past expiry_date.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryHoldExpiryCommand extends Command
{
    protected $signature = 'ahg:library-hold-expiry
        {--dry-run : Simulate without expiring}';

    protected $description = 'Expire unfulfilled holds past their expiry_date';

    public function handle(): int
    {
        if (! Schema::hasTable('library_hold')) { $this->warn('library_hold missing'); return self::SUCCESS; }
        $dry = (bool) $this->option('dry-run');

        $eligible = DB::table('library_hold')
            ->where('status', 'pending')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString());

        $n = (clone $eligible)->count();
        $this->info("expiring {$n} unfulfilled holds" . ($dry ? ' (dry-run)' : ''));
        if ($dry || $n === 0) return self::SUCCESS;

        $updated = $eligible->update(['status' => 'expired', 'cancelled_date' => now(), 'cancel_reason' => 'expiry_date passed']);
        $this->info("expired={$updated}");
        return self::SUCCESS;
    }
}
