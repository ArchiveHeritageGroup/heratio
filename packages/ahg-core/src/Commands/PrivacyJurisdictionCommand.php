<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrivacyJurisdictionCommand extends Command
{
    protected $signature = 'ahg:privacy-jurisdiction
        {--code= : Specific jurisdiction code (popia, gdpr, ccpa, cdpa, ...); default=all installed}
        {--format=table : Output (table or json)}';

    protected $description = 'Privacy jurisdiction registry — installed regimes + global counts (DSAR, breach, RoPA)';

    public function handle(): int
    {
        if (! Schema::hasTable('privacy_jurisdiction_registry')) {
            $this->error('privacy_jurisdiction_registry missing.');
            return self::FAILURE;
        }

        $q = DB::table('privacy_jurisdiction_registry');
        if ($code = $this->option('code')) $q->where('code', strtolower((string) $code));
        else $q->where('is_installed', 1);
        $registry = $q->orderBy('sort_order')->orderBy('code')->get();

        // Cross-cutting counts (the privacy_* tables are global, not per-jurisdiction).
        $globals = [
            'lawful_bases'           => Schema::hasTable('privacy_lawful_basis')      ? (int) DB::table('privacy_lawful_basis')->count() : 0,
            'data_inventory'         => Schema::hasTable('privacy_data_inventory')    ? (int) DB::table('privacy_data_inventory')->count() : 0,
            'open_dsar'              => Schema::hasTable('privacy_dsar_request')      ? (int) DB::table('privacy_dsar_request')->whereIn('status', ['pending','in_progress'])->count() : 0,
            'open_breaches'          => Schema::hasTable('privacy_breach_incident')   ? (int) DB::table('privacy_breach_incident')->whereIn('status', ['open','investigating'])->count() : 0,
            'processing_activities'  => Schema::hasTable('privacy_processing_activity')? (int) DB::table('privacy_processing_activity')->count() : 0,
            'consent_records'        => Schema::hasTable('privacy_consent_record')    ? (int) DB::table('privacy_consent_record')->count() : 0,
        ];

        if ($this->option('format') === 'json') {
            $this->line(json_encode(['jurisdictions' => $registry, 'globals' => $globals], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('=== installed privacy jurisdictions ===');
        if ($registry->isEmpty()) {
            $this->warn('  (none installed)');
        }
        foreach ($registry as $r) {
            $marker = ($r->is_active ?? 0) ? 'ACTIVE  ' : 'inactive';
            $this->line(sprintf("  %s  %-6s  %-40s  DSAR=%dd breach=%dh  regulator=%s",
                $marker, $r->code, mb_strimwidth((string) ($r->full_name ?? $r->name), 0, 40, '...'),
                $r->dsar_days, $r->breach_hours, $r->regulator ?? '-'));
        }

        $this->info("\n=== global privacy state ===");
        foreach ($globals as $k => $v) $this->line(sprintf("  %-25s %d", $k, $v));
        return self::SUCCESS;
    }
}
