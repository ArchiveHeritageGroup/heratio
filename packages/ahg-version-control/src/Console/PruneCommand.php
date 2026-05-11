<?php

/**
 * php artisan ahg:version-prune [--entity=…] [--retain-count=N] [--retain-days=N] [--dry-run]
 *
 * Phase M — apply retention rules to version history.
 *
 * Keep rules (a version is KEPT if any are true):
 *   - version_number = 1 (baseline always kept)
 *   - retain_count > 0 AND version_number > (max_version - retain_count)
 *   - retain_days  > 0 AND created_at > (now - retain_days)
 *
 * Both rules zero → nothing pruned (default).
 *
 * @phase M
 */

namespace AhgVersionControl\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneCommand extends Command
{
    protected $signature = 'ahg:version-prune
        {--entity=information_object,actor : CSV of entity types}
        {--retain-count= : Override retain_count setting}
        {--retain-days= : Override retain_days setting}
        {--dry-run : Report what would be pruned without deleting}';

    protected $description = 'Apply retention rules to version history (preserves v1 + most-recent N)';

    public function handle(): int
    {
        $entities = array_filter(
            array_map('trim', explode(',', (string) $this->option('entity'))),
            fn ($e) => in_array($e, ['information_object', 'actor'], true),
        );
        if (empty($entities)) {
            $this->error('--entity must be information_object, actor, or both');
            return self::FAILURE;
        }

        $retainCount = $this->option('retain-count') !== null
            ? (int) $this->option('retain-count')
            : (int) $this->readSetting('version_control.retain_count', '0');
        $retainDays = $this->option('retain-days') !== null
            ? (int) $this->option('retain-days')
            : (int) $this->readSetting('version_control.retain_days', '0');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'prune: retain_count=%d  retain_days=%d  dry_run=%s',
            $retainCount, $retainDays, $dryRun ? 'yes' : 'no',
        ));

        if ($retainCount <= 0 && $retainDays <= 0) {
            $this->warn('Both retention rules are 0 — nothing to do.');
            return self::SUCCESS;
        }

        foreach ($entities as $entityType) {
            $cfg = $entityType === 'actor'
                ? ['table' => 'actor_version', 'fk' => 'actor_id']
                : ['table' => 'information_object_version', 'fk' => 'information_object_id'];

            $this->line("prune: {$entityType} — scanning…");
            $deleted = $this->prune($cfg['table'], $cfg['fk'], $retainCount, $retainDays, $dryRun);
            $this->info("prune: {$entityType} — " . ($dryRun ? 'would prune' : 'pruned') . " {$deleted} row(s)");
        }

        return self::SUCCESS;
    }

    private function readSetting(string $key, string $default): string
    {
        try {
            $v = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            return is_string($v) ? $v : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function prune(string $table, string $fk, int $retainCount, int $retainDays, bool $dryRun): int
    {
        $cutoff = $retainDays > 0
            ? date('Y-m-d H:i:s', time() - 86400 * $retainDays)
            : null;

        $maxVersions = DB::table($table)
            ->select($fk, DB::raw('MAX(version_number) AS mx'))
            ->groupBy($fk)
            ->get()
            ->all();

        $toDelete = [];
        foreach ($maxVersions as $row) {
            $entityId = (int) $row->{$fk};
            $maxVersion = (int) $row->mx;
            $cutoffCount = $retainCount > 0 ? ($maxVersion - $retainCount) : null;

            $q = DB::table($table)
                ->where($fk, $entityId)
                ->where('version_number', '!=', 1);

            if ($cutoffCount !== null && $cutoff !== null) {
                $q->where('version_number', '<=', $cutoffCount)
                  ->where('created_at', '<', $cutoff);
            } elseif ($cutoffCount !== null) {
                $q->where('version_number', '<=', $cutoffCount);
            } elseif ($cutoff !== null) {
                $q->where('created_at', '<', $cutoff);
            }

            foreach ($q->pluck('id')->all() as $id) {
                $toDelete[] = (int) $id;
            }
        }

        if (empty($toDelete)) {
            return 0;
        }
        if ($dryRun) {
            return count($toDelete);
        }

        $deleted = 0;
        foreach (array_chunk($toDelete, 1000) as $chunk) {
            $deleted += DB::table($table)->whereIn('id', $chunk)->delete();
        }
        return $deleted;
    }
}
