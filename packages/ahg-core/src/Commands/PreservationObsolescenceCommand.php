<?php

namespace AhgCore\Commands;

use AhgPreservation\Services\PreservationService;
use Illuminate\Console\Command;

class PreservationObsolescenceCommand extends Command
{
    protected $signature = 'ahg:preservation-obsolescence
        {--limit=100 : Max high-risk format objects to list}
        {--format=table : Output format (table or json)}';

    protected $description = 'Format obsolescence report — at-risk PRONOM IDs and their object counts';

    public function handle(PreservationService $svc): int
    {
        $atRisk = $svc->getAtRiskFormats();
        $highRiskObjects = $svc->getHighRiskFormatObjects((int) $this->option('limit'));

        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'at_risk_formats' => $atRisk,
                'high_risk_objects' => $highRiskObjects,
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info("=== at-risk formats ({$atRisk->count()}) ===");
        foreach ($atRisk as $f) {
            $this->line(sprintf("  %-12s %-30s risk=%-10s object_count=%d",
                $f->pronom_id ?? '-', mb_strimwidth((string) ($f->name ?? ''), 0, 30, '...'),
                $f->risk_level ?? '-', $f->object_count ?? 0));
        }
        $this->info("\n=== sample high-risk objects ({$highRiskObjects->count()}) ===");
        foreach ($highRiskObjects as $o) {
            $this->line(sprintf("  do=%-7s pronom=%-10s name=%s", $o->digital_object_id ?? '-', $o->pronom_id ?? '-', mb_strimwidth((string) ($o->name ?? ''), 0, 80, '...')));
        }
        return self::SUCCESS;
    }
}
