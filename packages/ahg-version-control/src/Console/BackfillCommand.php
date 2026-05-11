<?php

/**
 * php artisan ahg:version-backfill [--entity=information_object,actor] [--batch=500] [--dry-run] [--user-id=N]
 *
 * Phase L — backfill v1 baselines for entities that have no version history.
 * Idempotent: entities that already have any version row are skipped.
 *
 * @phase L
 */

namespace AhgVersionControl\Console;

use AhgVersionControl\Services\SnapshotBuilder;
use AhgVersionControl\Services\VersionWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCommand extends Command
{
    protected $signature = 'ahg:version-backfill
        {--entity=information_object,actor : CSV of entity types}
        {--batch=500 : Batch size}
        {--dry-run : Print what would be backfilled without writing}
        {--user-id= : created_by for the v1 rows}';

    protected $description = 'Create v1 baseline versions for entities that have no version history';

    public function handle(SnapshotBuilder $builder, VersionWriter $writer): int
    {
        $entities = array_filter(
            array_map('trim', explode(',', (string) $this->option('entity'))),
            fn ($e) => in_array($e, ['information_object', 'actor'], true),
        );
        if (empty($entities)) {
            $this->error('--entity must be information_object, actor, or both');
            return self::FAILURE;
        }
        $batch = max(50, (int) $this->option('batch'));
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;

        foreach ($entities as $entityType) {
            $cfg = $entityType === 'actor'
                ? ['base' => 'actor', 'ver' => 'actor_version', 'fk' => 'actor_id']
                : ['base' => 'information_object', 'ver' => 'information_object_version', 'fk' => 'information_object_id'];

            $startedAt = microtime(true);
            $this->info("backfill: {$entityType} — scanning…");

            $todo = DB::table($cfg['base'])
                ->leftJoin($cfg['ver'], $cfg['ver'] . '.' . $cfg['fk'], '=', $cfg['base'] . '.id')
                ->whereNull($cfg['ver'] . '.' . $cfg['fk'])
                ->pluck($cfg['base'] . '.id')
                ->all();
            $total = count($todo);
            $this->info("backfill: {$entityType} — {$total} entity/entities to backfill");

            if ($total === 0) {
                continue;
            }
            if ($dryRun) {
                $this->warn('[dry-run] — no rows would be written');
                continue;
            }

            $processed = 0;
            $errors = 0;
            $chunks = array_chunk($todo, $batch);
            foreach ($chunks as $chunkIdx => $chunk) {
                foreach ($chunk as $entityId) {
                    try {
                        $snapshot = $entityType === 'actor'
                            ? $builder->buildForActor((int) $entityId)
                            : $builder->buildForInformationObject((int) $entityId);
                        $writer->write(
                            entityType: $entityType,
                            entityId: (int) $entityId,
                            snapshot: $snapshot,
                            changeSummary: 'Initial backfill (v1 baseline)',
                            userId: $userId,
                        );
                        $processed++;
                    } catch (\Throwable $e) {
                        $errors++;
                        \Log::warning("ahg:version-backfill {$entityType}/{$entityId} failed", ['error' => $e->getMessage()]);
                    }
                }
                $elapsed = microtime(true) - $startedAt;
                $rate = $elapsed > 0 ? round($processed / $elapsed, 1) : 0;
                $this->line(sprintf(
                    "backfill: %s — batch %d/%d · processed=%d · errors=%d · rate=%.1f/s",
                    $entityType, $chunkIdx + 1, count($chunks), $processed, $errors, $rate,
                ));
            }

            $elapsed = microtime(true) - $startedAt;
            $rate = $elapsed > 0 ? round($processed / $elapsed, 1) : 0;
            $this->info(sprintf(
                "backfill: %s — DONE · processed=%d · errors=%d · total=%.1fs · rate=%.1f/s",
                $entityType, $processed, $errors, $elapsed, $rate,
            ));
        }

        return self::SUCCESS;
    }
}
