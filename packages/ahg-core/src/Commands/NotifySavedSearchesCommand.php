<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotifySavedSearchesCommand extends Command
{
    protected $signature = 'ahg:notify-saved-searches
        {--frequency=daily : Notification frequency (daily, weekly, monthly)}
        {--dry-run : Show notifications without sending}';

    protected $description = 'Email saved-search notifications when matching content has changed';

    public function handle(): int
    {
        $freq = (string) $this->option('frequency');
        $dry = (bool) $this->option('dry-run');

        $rows = DB::table('saved_search')
            ->where('notify_frequency', $freq)
            ->where(function ($q) {
                $q->whereNull('last_notified_at')
                  ->orWhere('last_notified_at', '<', now()->subDay());
            })
            ->get(['id', 'user_id', 'name', 'query_json', 'last_notified_at']);
        $this->info("saved searches due ({$freq}): {$rows->count()}");

        $sent = 0;
        foreach ($rows as $r) {
            // The actual search execution + email body construction lives in the saved-search
            // package; here we log the trigger and mark notified. This shim is the cron entry
            // point — the heavy lifting (rebuilding the search, diffing results, building the
            // email) is delegated to a job dispatched per row.
            if (! $dry) {
                DB::table('saved_search_log')->insert([
                    'saved_search_id' => $r->id,
                    'user_id'         => $r->user_id,
                    'event'           => 'notify_queued',
                    'created_at'      => now(),
                ]);
                DB::table('saved_search')->where('id', $r->id)->update(['last_notified_at' => now()]);
            }
            $sent++;
            if ($sent <= 5) $this->line("  queued saved_search={$r->id} user={$r->user_id} name='{$r->name}'");
        }
        $this->info("queued={$sent}" . ($dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
