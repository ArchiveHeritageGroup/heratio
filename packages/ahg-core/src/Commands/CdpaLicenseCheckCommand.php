<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CdpaLicenseCheckCommand extends Command
{
    protected $signature = 'ahg:cdpa-license-check
        {--reminder-window=60 : Days before expiry to mark renewal_reminder_sent}
        {--mark-sent : Set renewal_reminder_sent=1 on rows in the window}
        {--report : Print expiring + expired list}';

    protected $description = 'Zimbabwe CDPA — flag licenses approaching expiry, optionally mark renewal_reminder_sent';

    public function handle(): int
    {
        $now = now()->toDateString();
        $window = (int) $this->option('reminder-window');
        $cutoff = now()->copy()->addDays($window)->toDateString();

        $expired = DB::table('cdpa_controller_license')
            ->where('expiry_date', '<', $now)
            ->where('status', '!=', 'expired')
            ->get(['id', 'license_number', 'organization_name', 'expiry_date']);

        $expiring = DB::table('cdpa_controller_license')
            ->where('expiry_date', '>=', $now)
            ->where('expiry_date', '<', $cutoff)
            ->where('status', 'active')
            ->where('renewal_reminder_sent', 0)
            ->get(['id', 'license_number', 'organization_name', 'expiry_date']);

        $this->info("expired (status not yet flipped): {$expired->count()}");
        $this->info("expiring within {$window} days (no reminder sent yet): {$expiring->count()}");

        if ($this->option('report') || (!$this->option('mark-sent'))) {
            foreach ($expired as $r) $this->line(sprintf("  EXPIRED  #%-5d  %s  %s  expiry=%s", $r->id, $r->license_number, mb_strimwidth((string)$r->organization_name, 0, 30, '...'), $r->expiry_date));
            foreach ($expiring as $r) $this->line(sprintf("  EXPIRING #%-5d  %s  %s  expiry=%s", $r->id, $r->license_number, mb_strimwidth((string)$r->organization_name, 0, 30, '...'), $r->expiry_date));
        }

        if ($this->option('mark-sent')) {
            $marked = (int) DB::table('cdpa_controller_license')
                ->whereIn('id', $expiring->pluck('id'))
                ->update(['renewal_reminder_sent' => 1]);
            $this->info("marked {$marked} rows as renewal_reminder_sent=1");
        }

        // Auto-flip status of past-expiry rows.
        if ($expired->isNotEmpty()) {
            $flipped = (int) DB::table('cdpa_controller_license')
                ->whereIn('id', $expired->pluck('id'))
                ->update(['status' => 'expired']);
            $this->info("flipped {$flipped} licenses to status=expired");
        }
        return self::SUCCESS;
    }
}
