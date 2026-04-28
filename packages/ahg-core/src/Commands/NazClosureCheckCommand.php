<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NazClosureCheckCommand extends Command
{
    protected $signature = 'ahg:naz-closure-check
        {--auto-release : Auto-flip status to released for closure_period rows past their end_date}
        {--system-user-id=1 : User id to credit auto-releases to}';

    protected $description = 'Zimbabwe NAZ — check 25-year closure periods, optionally auto-release expired ones';

    public function handle(): int
    {
        $now = now();

        $expired = DB::table('naz_closure_period')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now->toDateString())
            ->get(['id', 'information_object_id', 'closure_type', 'end_date']);

        $expiringSoon = DB::table('naz_closure_period')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '>=', $now->toDateString())
            ->where('end_date', '<', $now->copy()->addDays(90)->toDateString())
            ->get(['id', 'information_object_id', 'closure_type', 'end_date']);

        $this->info("=== NAZ closure periods ===");
        $this->line("  expired (still active):   {$expired->count()}");
        $this->line("  expiring within 90 days:  {$expiringSoon->count()}");

        foreach ($expired->take(20) as $r) {
            $this->line(sprintf("  EXPIRED  #%-5d obj=%-7d type=%-12s end=%s", $r->id, $r->information_object_id, $r->closure_type, $r->end_date));
        }

        if ($this->option('auto-release') && $expired->isNotEmpty()) {
            $userId = (int) $this->option('system-user-id');
            $released = (int) DB::table('naz_closure_period')
                ->whereIn('id', $expired->pluck('id'))
                ->update([
                    'status'        => 'released',
                    'released_by'   => $userId,
                    'released_at'   => $now,
                    'release_notes' => 'auto-released at end_date',
                ]);
            $this->info("auto-released {$released} closures");
        }
        return self::SUCCESS;
    }
}
