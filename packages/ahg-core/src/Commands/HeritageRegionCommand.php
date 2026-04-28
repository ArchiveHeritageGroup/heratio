<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HeritageRegionCommand extends Command
{
    protected $signature = 'ahg:heritage-region
        {--install= : Install region by code}
        {--uninstall= : Uninstall region by code}
        {--set-active= : Activate region by code}
        {--set-inactive= : Deactivate region by code}
        {--info= : Show info for region by code}';

    protected $description = 'Manage heritage regions (install/uninstall/activate/deactivate per-jurisdiction modules)';

    public function handle(): int
    {
        if ($code = $this->option('info')) {
            $r = DB::table('heritage_accounting_standard')->where('code', $code)->first();
            if (! $r) { $this->error("region {$code} not found"); return self::FAILURE; }
            foreach ((array) $r as $k => $v) $this->line(sprintf("  %-25s %s", $k, is_scalar($v) ? $v : json_encode($v)));
            return self::SUCCESS;
        }
        if ($code = $this->option('install')) {
            DB::table('heritage_accounting_standard')->where('code', $code)->update(['is_installed' => 1, 'is_active' => 1, 'installed_at' => now()]);
            $this->info("installed region={$code}"); return self::SUCCESS;
        }
        if ($code = $this->option('uninstall')) {
            DB::table('heritage_accounting_standard')->where('code', $code)->update(['is_installed' => 0, 'is_active' => 0]);
            $this->info("uninstalled region={$code}"); return self::SUCCESS;
        }
        if ($code = $this->option('set-active')) {
            DB::table('heritage_accounting_standard')->where('code', $code)->update(['is_active' => 1]);
            $this->info("activated region={$code}"); return self::SUCCESS;
        }
        if ($code = $this->option('set-inactive')) {
            DB::table('heritage_accounting_standard')->where('code', $code)->update(['is_active' => 0]);
            $this->info("deactivated region={$code}"); return self::SUCCESS;
        }

        // Default: list installed regions
        $rows = DB::table('heritage_accounting_standard')->get(['code','name','jurisdiction','is_installed','is_active']);
        $this->info('=== heritage regions ===');
        foreach ($rows as $r) {
            $marker = $r->is_installed ? ($r->is_active ? 'ACTIVE  ' : 'inst.   ') : '-       ';
            $this->line(sprintf("  %s  %-12s  %-30s  %s", $marker, $r->code, $r->name, $r->jurisdiction ?? '-'));
        }
        return self::SUCCESS;
    }
}
