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

    protected $description = 'Manage museum exhibition schedule — auto-open/auto-close based on dates';

    public function handle(): int
    {
        if (! Schema::hasTable('exhibition')) {
            $this->warn('exhibition table missing.');
            return self::SUCCESS;
        }
        $now = now()->toDateString();

        $startedToday = DB::table('exhibition')
            ->where('status', 'scheduled')
            ->whereNotNull('start_date')
            ->where('start_date', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            });
        $finishedToday = DB::table('exhibition')
            ->where('status', 'open')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now);

        $startedCount = (clone $startedToday)->count();
        $finishedCount = (clone $finishedToday)->count();
        $this->info("scheduled → open:  {$startedCount}");
        $this->info("open → closed:     {$finishedCount}");

        if ($this->option('process')) {
            $opened = (int) (clone $startedToday)->update(['status' => 'open',   'opened_at'  => now()]);
            $closed = (int) (clone $finishedToday)->update(['status' => 'closed', 'closed_at' => now()]);
            $this->info("processed: opened={$opened} closed={$closed}");
        }
        return self::SUCCESS;
    }
}
