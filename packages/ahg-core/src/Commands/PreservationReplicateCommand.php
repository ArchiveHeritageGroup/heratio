<?php

namespace AhgCore\Commands;

use AhgPreservation\Services\PreservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreservationReplicateCommand extends Command
{
    protected $signature = 'ahg:preservation-replicate
        {--target= : Replication target name (default: all enabled targets)}
        {--package-id= : Replicate a specific package by id (default: any unreplicated)}
        {--limit=20 : Max packages per target per run}
        {--dry-run : Simulate without executing}';

    protected $description = 'Sync OAIS packages to replication targets defined in ahg_preservation_targets';

    public function handle(PreservationService $svc): int
    {
        $targets = $svc->getReplicationTargets();
        if ($targetName = $this->option('target')) {
            $targets = $targets->filter(fn($t) => ($t->name ?? '') === $targetName);
        }
        if ($targets->isEmpty()) {
            $this->warn('no enabled replication targets configured');
            return self::SUCCESS;
        }
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');
        $singlePackage = $this->option('package-id');

        $totalOK = 0; $totalFail = 0;
        foreach ($targets as $t) {
            $this->info("--- target: {$t->name} ({$t->kind}) ---");

            // Pick packages not yet replicated to this target.
            $q = DB::table('preservation_package as p')
                ->leftJoin('preservation_event as pe', function ($j) use ($t) {
                    $j->on('pe.package_id', '=', 'p.id')
                      ->where('pe.event_type', '=', 'replicate')
                      ->where('pe.detail', 'like', '%' . $t->name . '%')
                      ->where('pe.outcome', '=', 'success');
                })
                ->whereNull('pe.id')
                ->where('p.status', 'completed');
            if ($singlePackage) $q->where('p.id', (int) $singlePackage);
            $rows = $q->orderBy('p.id')->limit($limit)->get(['p.id', 'p.bag_path']);
            $this->info("  packages to replicate: {$rows->count()}" . ($dry ? ' (dry-run)' : ''));

            foreach ($rows as $r) {
                if ($dry) { $this->line("  would replicate package={$r->id} bag={$r->bag_path}"); $totalOK++; continue; }
                // Best-effort: copy bag dir to target; for now log the intent and let ops wire the
                // actual transport (rclone/rsync/aws s3) per target kind.
                $svc->logEvent(0, null, 'replicate', json_encode(['target' => $t->name, 'package_id' => $r->id]), 'pending');
                $this->line("  queued replicate package={$r->id} target={$t->name}");
                $totalOK++;
            }
        }

        $this->info("done; ok={$totalOK} fail={$totalFail}");
        return self::SUCCESS;
    }
}
