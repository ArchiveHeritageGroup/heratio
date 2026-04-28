<?php

namespace AhgCore\Commands;

use AhgPreservation\Services\PronomIdentificationService;
use Illuminate\Console\Command;

class PreservationIdentifyCommand extends Command
{
    protected $signature = 'ahg:preservation-identify
        {--limit=1000 : Maximum files to identify in this run}
        {--digital-object-id= : Identify a single digital_object}
        {--risk : Print PRONOM risk distribution and exit}';

    protected $description = 'Identify file formats via Siegfried/PRONOM (delegates to PronomIdentificationService)';

    public function handle(PronomIdentificationService $svc): int
    {
        if ($this->option('risk')) {
            $dist = $svc->riskDistribution();   // [{risk_level, count}, ...]
            $this->info('=== PRONOM risk distribution ===');
            foreach ($dist as $row) $this->line(sprintf("  %-15s %d", $row->risk_level ?? '-', (int) ($row->count ?? 0)));
            return self::SUCCESS;
        }

        if ($doId = $this->option('digital-object-id')) {
            $r = $svc->identifyDigitalObject((int) $doId);
            $this->info("do={$doId} pronom=" . ($r['pronom_id'] ?? '?') . " risk=" . ($r['risk_level'] ?? '?'));
            return self::SUCCESS;
        }

        $r = $svc->batchIdentify((int) $this->option('limit'));
        $this->info("done; processed=" . ($r['processed'] ?? 0) . " identified=" . ($r['identified'] ?? 0) . " failed=" . ($r['failed'] ?? 0));
        return self::SUCCESS;
    }
}
