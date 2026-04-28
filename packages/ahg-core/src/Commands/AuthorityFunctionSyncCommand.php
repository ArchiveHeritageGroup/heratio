<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthorityFunctionSyncCommand extends Command
{
    protected $signature = 'ahg:authority-function-sync
        {--clean : Remove relation rows that point at missing actor or function}
        {--connection=atom : Source DB}';

    protected $description = 'Validate actor↔function links in `relation` table; report or clean orphans';

    public function handle(): int
    {
        $conn = (string) $this->option('connection');
        $db = DB::connection($conn);
        if (! Schema::connection($conn)->hasTable('relation')) {
            $this->warn("[{$conn}] no relation table");
            return self::SUCCESS;
        }

        // Actor↔function links typically use a relation type from taxonomy 39 (Actor Relation Types).
        // We don't strictly need the type filter to find orphans — just check FK validity.
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

        $this->info("orphaned subject_id (no matching actor): " . $orphanedSubjects->count());
        $this->info("orphaned object_id  (no matching actor): " . $orphanedObjects->count());

        if ($this->option('clean')) {
            $ids = $orphanedSubjects->pluck('id')->merge($orphanedObjects->pluck('id'))->unique();
            $deleted = $db->table('relation')->whereIn('id', $ids)->delete();
            $this->info("cleaned {$deleted} orphaned relation rows");
        }
        return self::SUCCESS;
    }
}
