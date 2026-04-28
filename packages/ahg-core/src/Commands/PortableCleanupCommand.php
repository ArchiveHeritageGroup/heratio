<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortableCleanupCommand extends Command
{
    protected $signature = 'ahg:portable-cleanup
        {--dry-run : Report what would be removed without deleting}';

    protected $description = 'Remove expired export packages';

    public function handle(): int
    {
        if (! Schema::hasTable('portable_export')) {
            $this->warn('portable_export table missing — nothing to do.');
            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');

        $expired = DB::table('portable_export')
            ->where(function ($q) {
                $q->whereNotNull('expires_at')
                  ->where('expires_at', '<', now());
            })
            ->orWhere(function ($q) {
                // Also reap completed packages whose retention has elapsed via settings.
                $days = (int) (DB::table('ahg_settings')
                    ->where('setting_key', 'portable_export_retention_days')
                    ->value('setting_value') ?? 30);
                $q->where('status', 'completed')
                  ->where('completed_at', '<', now()->subDays(max(1, $days)));
            })
            ->get(['id','output_path']);

        $this->info("[portable_export] expired_rows=" . $expired->count() . ($dry ? ' (dry-run)' : ''));
        if ($dry || $expired->isEmpty()) return self::SUCCESS;

        $files = 0; $rows = 0;
        foreach ($expired as $r) {
            if (! empty($r->output_path) && is_file($r->output_path)) {
                @unlink($r->output_path); $files++;
            }
            DB::table('portable_export')->where('id', $r->id)->delete();
            // Cascade share/token rows if present.
            if (Schema::hasTable('portable_export_share_token')) {
                DB::table('portable_export_share_token')->where('portable_export_id', $r->id)->delete();
            }
            if (Schema::hasTable('portable_export_token')) {
                DB::table('portable_export_token')->where('portable_export_id', $r->id)->delete();
            }
            $rows++;
        }
        $this->info("deleted_rows={$rows} unlinked_files={$files}");
        return self::SUCCESS;
    }
}
