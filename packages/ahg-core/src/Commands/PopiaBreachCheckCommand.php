<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopiaBreachCheckCommand extends Command
{
    protected $signature = 'ahg:popia-breach-check
        {--json : Output report as JSON}';

    protected $description = 'POPIA breach check — flag incidents that need regulator/subject notification (72-hour rule)';

    public function handle(): int
    {
        $now = now();
        // POPIA s22(1) — 72 hours to notify the Regulator after becoming aware.
        $deadline = $now->copy()->subHours(72);

        $rows = DB::table('privacy_breach_incident')
            ->select('id','reference','breach_type','severity','discovered_date','regulator_notified','subjects_notified','individuals_affected','notification_date')
            ->orderBy('discovered_date', 'desc')
            ->limit(200)
            ->get();

        $overdueRegulator = $rows->filter(fn($r) => ! $r->regulator_notified && $r->discovered_date && $r->discovered_date < $deadline);
        $overdueSubjects  = $rows->filter(fn($r) => ! $r->subjects_notified && ($r->severity ?? '') === 'high' && $r->discovered_date && $r->discovered_date < $now->copy()->subDays(7));

        if ($this->option('json')) {
            $this->line(json_encode([
                'recent'             => $rows,
                'overdue_regulator'  => array_values($overdueRegulator->all()),
                'overdue_subjects'   => array_values($overdueSubjects->all()),
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('=== POPIA breach status (last 200 incidents) ===');
        $this->line("  recent total:               {$rows->count()}");
        $this->line("  overdue regulator notify:   {$overdueRegulator->count()}");
        $this->line("  overdue subjects notify:    {$overdueSubjects->count()}");

        if ($overdueRegulator->isNotEmpty()) {
            $this->warn("\nincidents past 72-hour deadline without regulator notification:");
            foreach ($overdueRegulator as $r) {
                $this->line(sprintf("  #%-5d %s  %s  severity=%s discovered=%s individuals=%d",
                    $r->id, $r->reference, $r->breach_type, $r->severity ?? '-', $r->discovered_date, $r->individuals_affected ?? 0));
            }
        }
        if ($overdueSubjects->isNotEmpty()) {
            $this->warn("\nhigh-severity incidents without subject notification (>7 days old):");
            foreach ($overdueSubjects as $r) {
                $this->line(sprintf("  #%-5d %s  individuals=%d", $r->id, $r->reference, $r->individuals_affected ?? 0));
            }
        }
        return $overdueRegulator->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
