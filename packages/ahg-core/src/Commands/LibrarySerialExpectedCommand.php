<?php

/**
 * LibrarySerialExpectedCommand — pre-create expected library_serial_issue
 * rows for the next N months on each active subscription.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibrarySerialExpectedCommand extends Command
{
    protected $signature = 'ahg:library-serial-expected
        {--months=3 : Generate expected issues for N months ahead}
        {--dry-run : Simulate without creating records}';

    protected $description = 'Generate expected serial issues';

    public function handle(): int
    {
        if (! Schema::hasTable('library_subscription') || ! Schema::hasTable('library_serial_issue')) {
            $this->warn('library_subscription/library_serial_issue missing'); return self::SUCCESS;
        }
        $months = max(1, (int) $this->option('months'));
        $dry = (bool) $this->option('dry-run');
        $until = Carbon::now()->addMonths($months);

        $subs = DB::table('library_subscription')->where('status', 'active')->get();
        $created = 0;
        foreach ($subs as $s) {
            $stepDays = $this->frequencyDays((string) ($s->frequency ?? 'monthly'));
            if ($stepDays <= 0) continue;
            $last = DB::table('library_serial_issue')->where('subscription_id', $s->id)->orderByDesc('expected_date')->first();
            $next = $last && $last->expected_date
                ? Carbon::parse($last->expected_date)->addDays($stepDays)
                : Carbon::now();
            while ($next->lessThanOrEqualTo($until)) {
                if ($dry) { $created++; }
                else {
                    DB::table('library_serial_issue')->insert([
                        'subscription_id' => $s->id,
                        'library_item_id' => $s->library_item_id,
                        'expected_date' => $next->toDateString(),
                        'status' => 'expected',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
                $next->addDays($stepDays);
            }
        }
        $this->info("expected_issues={$created}" . ($dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }

    protected function frequencyDays(string $freq): int
    {
        return match (strtolower($freq)) {
            'daily'      => 1,
            'weekly'     => 7,
            'biweekly', 'fortnightly' => 14,
            'monthly'    => 30,
            'bimonthly', 'two-monthly' => 60,
            'quarterly'  => 91,
            'biannual', 'semiannual' => 182,
            'annual', 'yearly' => 365,
            default      => 0,
        };
    }
}
