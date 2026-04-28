<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HeritageInstallCommand extends Command
{
    protected $signature = 'ahg:heritage-install
        {--region= : Install region(s) (comma-separated codes: za, zw, ke, ng, lgpd-br, ...)}
        {--all-regions : Install all available regional add-ons}
        {--list : List available regions and exit}';

    protected $description = 'Install heritage schema add-ons (regional compliance modules: GRAP103, NMMZ, NAZ, CDPA, etc.)';

    public function handle(): int
    {
        if (! Schema::hasTable('heritage_accounting_standard')) {
            $this->error('heritage_accounting_standard missing — run base ahg-heritage-manage migration first.');
            return self::FAILURE;
        }
        if ($this->option('list')) {
            $rows = DB::table('heritage_accounting_standard')->get(['id','code','name','jurisdiction','is_installed','is_active']);
            foreach ($rows as $r) {
                $marker = $r->is_installed ? ($r->is_active ? 'ACTIVE  ' : 'inst.   ') : '-       ';
                $this->line(sprintf("  %s  %-12s  %-30s  %s", $marker, $r->code, $r->name, $r->jurisdiction));
            }
            return self::SUCCESS;
        }

        $regions = [];
        if ($this->option('all-regions')) {
            $regions = DB::table('heritage_accounting_standard')->pluck('code')->toArray();
        } elseif ($r = $this->option('region')) {
            $regions = array_map('trim', explode(',', $r));
        }
        if (empty($regions)) { $this->error('--region or --all-regions required'); return self::FAILURE; }

        $installed = (int) DB::table('heritage_accounting_standard')
            ->whereIn('code', $regions)
            ->update(['is_installed' => 1, 'is_active' => 1, 'installed_at' => now()]);
        $this->info("installed/activated {$installed} region(s)");
        return self::SUCCESS;
    }
}
