<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IcipCheckExpiryCommand extends Command
{
    protected $signature = 'ahg:icip-check-expiry
        {--days=90 : Warn about consents expiring within N days}
        {--auto-restrict : Automatically apply icip_access_restriction for expired consents}';

    protected $description = 'Check ICIP consent expiry — Indigenous Cultural and Intellectual Property consent lifecycle';

    public function handle(): int
    {
        $now = now()->toDateString();
        $window = max(1, (int) $this->option('days'));
        $cutoff = now()->copy()->addDays($window)->toDateString();

        $expired = DB::table('icip_consent')
            ->where('consent_status', 'granted')
            ->whereNotNull('consent_expiry_date')
            ->where('consent_expiry_date', '<', $now)
            ->get(['id', 'information_object_id', 'community_id', 'consent_expiry_date']);
        $expiring = DB::table('icip_consent')
            ->where('consent_status', 'granted')
            ->whereNotNull('consent_expiry_date')
            ->where('consent_expiry_date', '>=', $now)
            ->where('consent_expiry_date', '<', $cutoff)
            ->get(['id', 'information_object_id', 'community_id', 'consent_expiry_date']);

        $this->info("expired ICIP consents: {$expired->count()}");
        $this->info("expiring within {$window} days: {$expiring->count()}");

        foreach ($expired->take(20) as $c) {
            $this->line(sprintf("  EXPIRED  consent=#%-5d obj=%-7d community=%-5s expiry=%s",
                $c->id, $c->information_object_id, $c->community_id ?? '-', $c->consent_expiry_date));
        }
        foreach ($expiring->take(20) as $c) {
            $this->line(sprintf("  EXPIRING consent=#%-5d obj=%-7d community=%-5s expiry=%s",
                $c->id, $c->information_object_id, $c->community_id ?? '-', $c->consent_expiry_date));
        }

        if ($this->option('auto-restrict') && $expired->isNotEmpty()) {
            // Mark consent rows as expired so the IO show pages stop displaying restricted content.
            $flipped = (int) DB::table('icip_consent')
                ->whereIn('id', $expired->pluck('id'))
                ->update(['consent_status' => 'expired']);
            $this->info("flipped {$flipped} consent rows to consent_status=expired");

            // Insert restriction rows so OcapService denies access until renewed.
            $newRestrictions = 0;
            foreach ($expired as $c) {
                $exists = DB::table('icip_access_restriction')
                    ->where('information_object_id', $c->information_object_id)
                    ->where('reason', 'icip_consent_expired')
                    ->exists();
                if (! $exists) {
                    DB::table('icip_access_restriction')->insert([
                        'information_object_id' => $c->information_object_id,
                        'reason'                => 'icip_consent_expired',
                        'created_at'            => now(),
                    ]);
                    $newRestrictions++;
                }
            }
            $this->info("inserted {$newRestrictions} new icip_access_restriction rows");
        }
        return self::SUCCESS;
    }
}
