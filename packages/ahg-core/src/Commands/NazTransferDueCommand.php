<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NazTransferDueCommand extends Command
{
    protected $signature = 'ahg:naz-transfer-due
        {--days=90 : Show transfers proposed for the next N days}';

    protected $description = 'Zimbabwe NAZ — list transfers proposed for the upcoming window (status=proposed/scheduled)';

    public function handle(): int
    {
        $now = now()->toDateString();
        $window = (int) $this->option('days');
        $cutoff = now()->copy()->addDays($window)->toDateString();

        $overdue = DB::table('naz_transfer')
            ->whereIn('status', ['proposed','scheduled','approved'])
            ->whereNotNull('proposed_date')
            ->where('proposed_date', '<', $now)
            ->whereNull('actual_date')
            ->orderBy('proposed_date')
            ->get(['id','transfer_number','transferring_agency','proposed_date','status']);

        $upcoming = DB::table('naz_transfer')
            ->whereIn('status', ['proposed','scheduled','approved'])
            ->whereNotNull('proposed_date')
            ->where('proposed_date', '>=', $now)
            ->where('proposed_date', '<', $cutoff)
            ->whereNull('actual_date')
            ->orderBy('proposed_date')
            ->get(['id','transfer_number','transferring_agency','proposed_date','status']);

        $this->info('=== NAZ transfers ===');
        $this->line("  overdue (proposed before today, not yet received): {$overdue->count()}");
        $this->line("  upcoming next {$window} days:                      {$upcoming->count()}");

        $rows = $overdue->concat($upcoming)->take(50);
        foreach ($rows as $r) {
            $marker = $r->proposed_date < $now ? 'OVERDUE ' : 'UPCOMING';
            $this->line(sprintf("  %s  #%-12s  %s  proposed=%s status=%s",
                $marker, $r->transfer_number, mb_strimwidth((string) $r->transferring_agency, 0, 35, '...'),
                $r->proposed_date, $r->status));
        }
        return self::SUCCESS;
    }
}
