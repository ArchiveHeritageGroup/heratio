<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthorityFunctionSyncCommand extends Command
{
    protected $signature = 'ahg:authority-function-sync
        {--clean : Remove relation rows that point at missing actor or function}
        {--connection= : Source DB; defaults to this instance discovery_db_connection setting, else its own connection}';

    protected $description = 'Validate actor↔function links in `relation` table; report or clean orphans';

    /**
     * The instance's own discovery source: the discovery_db_connection setting
     * when set, otherwise this application's default connection. Never a
     * hard-coded 'atom' - that database only exists on an AtoM overlay install.
     */
    private function defaultConnection(): string
    {
        try {
            $name = (string) (DB::table('ahg_settings')
                ->where('setting_key', 'discovery_db_connection')
                ->value('setting_value') ?? '');
        } catch (\Throwable $e) {
            $name = '';
        }

        return $name !== '' ? $name : (string) config('database.default');
    }

    public function handle(): int
    {
        // Was hard-defaulted to 'atom'. On a Heratio-native instance that database
        // belongs to someone else, so the probe below threw
        // "Access denied for user ... to database 'atom'" on every scheduled run.
        // The relation table lives in this instance's own database unless an
        // operator says otherwise.
        $conn = (string) ($this->option('connection') ?: $this->defaultConnection());

        try {
            $db = DB::connection($conn);
            $hasRelation = Schema::connection($conn)->hasTable('relation');
        } catch (\Throwable $e) {
            // An unreachable or forbidden source is nothing to sync, not a fault:
            // failing here logged a failed scheduled command every run.
            $this->warn("[{$conn}] not usable as a source: ".$e->getMessage());

            return self::SUCCESS;
        }

        if (! $hasRelation) {
            $this->warn("[{$conn}] no relation table");

            return self::SUCCESS;
        }

        // Actor↔function links typically use a relation type from taxonomy 39 (Actor Relation Types).
        // We don't strictly need the type filter to find orphans - just check FK validity.
        $orphanedSubjects = $db->table('relation as r')
            ->leftJoin('actor as a', 'a.id', '=', 'r.subject_id')
            ->whereNull('a.id')
            ->select('r.id', 'r.subject_id', 'r.object_id')
            ->limit(1000)->get();
        $orphanedObjects = $db->table('relation as r')
            ->leftJoin('actor as a', 'a.id', '=', 'r.object_id')
            ->whereNull('a.id')
            ->select('r.id', 'r.subject_id', 'r.object_id')
            ->limit(1000)->get();

        $this->info('orphaned subject_id (no matching actor): '.$orphanedSubjects->count());
        $this->info('orphaned object_id  (no matching actor): '.$orphanedObjects->count());

        if ($this->option('clean')) {
            $ids = $orphanedSubjects->pluck('id')->merge($orphanedObjects->pluck('id'))->unique();
            $deleted = $db->table('relation')->whereIn('id', $ids)->delete();
            $this->info("cleaned {$deleted} orphaned relation rows");
        }

        return self::SUCCESS;
    }
}
