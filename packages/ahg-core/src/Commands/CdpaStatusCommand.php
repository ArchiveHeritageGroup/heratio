<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CdpaStatusCommand extends Command
{
    protected $signature = 'ahg:cdpa-status
        {--format=table : Output (table or json)}';

    protected $description = 'Zimbabwe CDPA — overall compliance status (license, DPO, DPIA, breach, request stats)';

    public function handle(): int
    {
        $now = now();
        $stats = [
            'license_active'        => (int) DB::table('cdpa_controller_license')->where('status', 'active')->where('expiry_date', '>', $now)->count(),
            'license_expired'       => (int) DB::table('cdpa_controller_license')->where('expiry_date', '<', $now)->count(),
            'license_expiring_60d'  => (int) DB::table('cdpa_controller_license')->where('expiry_date', '>=', $now)->where('expiry_date', '<', $now->copy()->addDays(60))->count(),
            'dpo_count'             => (int) DB::table('cdpa_dpo')->count(),
            'dpia_total'            => (int) DB::table('cdpa_dpia')->count(),
            'open_breaches'         => (int) DB::table('cdpa_breach')->whereIn('status', ['open','investigating'])->count(),
            'open_dsr'              => (int) DB::table('cdpa_data_subject_request')->whereIn('status', ['pending','in_progress'])->count(),
            'processing_activities' => (int) DB::table('cdpa_processing_activity')->count(),
        ];

        if ($this->option('format') === 'json') {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('=== Zimbabwe CDPA status ===');
        foreach ($stats as $k => $v) $this->line(sprintf("  %-25s %d", $k, $v));

        if ($stats['license_expired'] > 0 || $stats['open_breaches'] > 0) {
            $this->warn('attention required: expired licenses or open breach incidents');
        }
        return self::SUCCESS;
    }
}
