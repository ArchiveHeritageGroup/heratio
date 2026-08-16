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
            $targets = $targets->filter(fn ($t) => ($t->name ?? '') === $targetName);
        }
        if ($targets->isEmpty()) {
            $this->warn('no enabled replication targets configured');

            return self::SUCCESS;
        }
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');
        $singlePackage = $this->option('package-id');

        $totalOK = 0;
        $totalFail = 0;
        foreach ($targets as $t) {
            // target_type, not kind: preservation_replication_target has no `kind`
            // column, so this threw "Undefined property: stdClass::$kind" the moment
            // an instance actually had a replication target configured.
            $this->info("--- target: {$t->name} ({$t->target_type}) ---");

            // Pick packages not yet replicated to this target.
            // preservation_event has no package_id, and its columns are
            // event_detail / event_outcome - the original join named three columns
            // that do not exist, so this threw the moment an instance actually had
            // a replication target. Events are linked to a package only through the
            // JSON this command itself writes into event_detail, so match on that.
            $q = DB::table('preservation_package as p')
                ->whereNotExists(function ($sub) use ($t) {
                    $sub->select(DB::raw(1))
                        ->from('preservation_event as pe')
                        ->where('pe.event_type', '=', 'replicate')
                        ->where('pe.event_detail', 'like', '%'.$t->name.'%')
                        ->whereColumn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(pe.event_detail, '$.package_id'))"), '=', 'p.id');
                })
                // 'exported' is the state a built package reaches; 'completed' was
                // never a value this column takes, so nothing was ever selected.
                ->whereIn('p.status', ['completed', 'exported']);
            if ($singlePackage) {
                $q->where('p.id', (int) $singlePackage);
            }
            // export_path, not bag_path: preservation_package has no bag_path column,
            // so this threw "Unknown column 'p.bag_path'" as soon as a replication
            // target existed. export_path is where the built package was written.
            $rows = $q->orderBy('p.id')->limit($limit)->get(['p.id', 'p.export_path']);
            $this->info("  packages to replicate: {$rows->count()}".($dry ? ' (dry-run)' : ''));

            foreach ($rows as $r) {
                if ($dry) {
                    $this->line("  would replicate package={$r->id} bag={$r->export_path}");
                    $totalOK++;

                    continue;
                }
                // Best-effort: copy bag dir to target; for now log the intent and let ops wire the
                // actual transport (rclone/rsync/aws s3) per target kind.
                // null, not 0: digital_object_id is a nullable FK to digital_object.id and
                // there is no row 0, so passing 0 violated the constraint and every
                // replication event insert failed. A package-level event has no single
                // digital object, which is exactly what null means here.
                $svc->logEvent(null, null, 'replicate', json_encode(['target' => $t->name, 'package_id' => $r->id]), 'pending');
                $this->line("  queued replicate package={$r->id} target={$t->name}");
                $totalOK++;
            }
        }

        $this->info("done; ok={$totalOK} fail={$totalFail}");

        return self::SUCCESS;
    }
}
