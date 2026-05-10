<?php

namespace AhgSharePoint\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Print SharePoint integration health.
 *
 * @phase 1
 */
class SharePointStatusCommand extends Command
{
    protected $signature = 'sharepoint:status';
    protected $description = 'Print SharePoint integration health (tenants, drives, subs, queue depth)';

    public function handle(): int
    {
        $this->newLine();
        $this->line('=== Tenants ===');
        foreach (DB::table('sharepoint_tenant')->get() as $t) {
            $this->line(sprintf(
                '  #%d %-30s %-12s last_token=%s%s',
                $t->id, substr($t->name, 0, 30), $t->status, $t->last_token_at ?? 'never',
                $t->last_error ? ' ERROR: ' . substr($t->last_error, 0, 80) : '',
            ));
        }

        $this->newLine();
        $this->line('=== Drives (ingest-enabled) ===');
        foreach (DB::table('sharepoint_drive')->where('ingest_enabled', 1)->get() as $d) {
            $allowlist = $d->auto_ingest_labels ? json_decode($d->auto_ingest_labels, true) : [];
            $labels = is_array($allowlist) ? implode(', ', $allowlist) : 'none';
            $this->line(sprintf(
                '  #%d %s / %s  sector=%s  labels=[%s]',
                $d->id, $d->site_title ?? '?', $d->drive_name ?? '?', $d->sector, $labels,
            ));
        }

        $this->newLine();
        $this->line('=== Sync state ===');
        foreach (DB::table('sharepoint_sync_state')->get() as $s) {
            $this->line(sprintf(
                '  drive=%d  last_run=%s  status=%s  items=%d%s',
                $s->drive_id, $s->last_run_at ?? 'never', $s->last_status ?? '—', $s->items_processed,
                $s->last_error ? ' ERROR: ' . substr($s->last_error, 0, 80) : '',
            ));
        }

        $this->newLine();
        $this->line('=== Active subscriptions ===');
        foreach (DB::table('sharepoint_subscription')->where('status', 'active')->get() as $sub) {
            $diff = strtotime($sub->expires_at) - time();
            $hoursLeft = round($diff / 3600, 1);
            $this->line(sprintf(
                '  drive=%d  resource=%s  expires_in=%sh',
                $sub->drive_id, substr($sub->resource, 0, 60), $hoursLeft,
            ));
        }

        $this->newLine();
        $this->line('=== Event status (last 24h) ===');
        $rows = DB::table('sharepoint_event')
            ->select('status', DB::raw('COUNT(*) as n'))
            ->whereRaw('received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')
            ->groupBy('status')->get();
        foreach ($rows as $r) {
            $this->line(sprintf('  %-25s %d', $r->status, $r->n));
        }
        if ($rows->isEmpty()) {
            $this->line('  (no events in last 24h)');
        }

        return self::SUCCESS;
    }
}
