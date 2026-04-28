<?php

/**
 * LibraryPatronExpiryCommand — mark patrons as 'expired' once membership
 * end-date + grace has passed.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryPatronExpiryCommand extends Command
{
    protected $signature = 'ahg:library-patron-expiry
        {--grace-days=0 : Grace period in days}
        {--dry-run : Simulate without flagging}';

    protected $description = 'Flag expired patron memberships';

    public function handle(): int
    {
        if (! Schema::hasTable('library_patron')) { $this->warn('library_patron missing'); return self::SUCCESS; }
        $grace = max(0, (int) $this->option('grace-days'));
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->copy()->subDays($grace)->toDateString();

        $q = DB::table('library_patron')
            ->where('borrowing_status', 'active')
            ->whereNotNull('membership_expiry')
            ->where('membership_expiry', '<', $cutoff);

        $n = (clone $q)->count();
        $this->info("expiring {$n} patron memberships (cutoff={$cutoff})" . ($dry ? ' (dry-run)' : ''));
        if ($dry || $n === 0) return self::SUCCESS;
        $q->update(['borrowing_status' => 'expired']);
        $this->info("flagged={$n}");
        return self::SUCCESS;
    }
}
