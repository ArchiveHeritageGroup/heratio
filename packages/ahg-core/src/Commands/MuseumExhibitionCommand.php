<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MuseumExhibitionCommand extends Command
{
    protected $signature = 'ahg:museum-exhibition
        {--check : Check exhibition schedule (default)}
        {--process : Flip status on exhibitions whose start_date or end_date passed}';

    protected $description = 'Manage museum exhibition schedule - auto-open/auto-close based on dates';

    public function handle(): int
    {
        if (! Schema::hasTable('exhibition')) {
            $this->warn('exhibition table missing.');

            return self::SUCCESS;
        }
        $now = now()->toDateString();

        // opening_date / closing_date, not start_date / end_date: the exhibition
        // table has never had the latter, so this threw "Unknown column
        // 'start_date'" on every run.
        //
        // The pre-opening status is matched against the set actually in use
        // ('preparation' is what the records here carry) rather than 'scheduled'
        // alone, which appears in the code's vocabulary but in none of the data -
        // so even with the right columns nothing would ever have transitioned.
        $preOpening = ['scheduled', 'planning', 'preparation'];

        $startedToday = DB::table('exhibition')
            ->whereIn('status', $preOpening)
            ->whereNotNull('opening_date')
            ->where('opening_date', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('closing_date')->orWhere('closing_date', '>=', $now);
            });
        $finishedToday = DB::table('exhibition')
            ->where('status', 'open')
            ->whereNotNull('closing_date')
            ->where('closing_date', '<', $now);

        $startedCount = (clone $startedToday)->count();
        $finishedCount = (clone $finishedToday)->count();
        $this->info("scheduled → open:  {$startedCount}");
        $this->info("open → closed:     {$finishedCount}");

        if ($this->option('process')) {
            // opened_at / closed_at are not columns on this table either. The
            // status carries the state; actual_closing_date is the real column
            // for recording when an exhibition actually came down.
            $opened = (int) (clone $startedToday)->update(['status' => 'open']);
            $closed = (int) (clone $finishedToday)->update([
                'status' => 'closed',
                'actual_closing_date' => now()->toDateString(),
            ]);
            $this->info("processed: opened={$opened} closed={$closed}");
        }

        return self::SUCCESS;
    }
}
