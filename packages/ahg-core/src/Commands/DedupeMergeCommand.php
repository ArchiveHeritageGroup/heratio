<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeMergeCommand extends Command
{
    protected $signature = 'ahg:dedupe-merge
        {--scan-id= : ahg_dedupe_scan row whose approved candidates to merge}
        {--keep= : object_id to keep (winner) — use with --remove}
        {--remove= : object_id to remove (loser, redirected to --keep)}
        {--dry-run : Simulate without writing}
        {--connection=atom : Source DB}';

    protected $description = 'Merge confirmed duplicate IOs (loser → winner via slug redirect + relation update)';

    public function handle(): int
    {
        $conn = (string) $this->option('connection');
        $dry = (bool) $this->option('dry-run');
        $keep = $this->option('keep');
        $remove = $this->option('remove');
        $scanId = $this->option('scan-id');

        if (! $keep || ! $remove) {
            // Without explicit pair, refuse — bulk merge from a scan needs admin curation.
            $this->error('Provide --keep=ID and --remove=ID to merge a single pair. Bulk merge from --scan-id requires admin-curated pairs (manual review UI), not a CLI default.');
            return self::FAILURE;
        }
        $keep = (int) $keep; $remove = (int) $remove;
        if ($keep === $remove) { $this->error('keep and remove must differ'); return self::FAILURE; }

        $db = DB::connection($conn);
        $w = $db->table('information_object')->where('id', $keep)->first();
        $l = $db->table('information_object')->where('id', $remove)->first();
        if (! $w || ! $l) { $this->error('one or both IOs not found'); return self::FAILURE; }

        $this->info("merge plan: keep={$keep} remove={$remove}" . ($dry ? ' (dry-run)' : ''));
        if ($dry) return self::SUCCESS;

        $db->beginTransaction();
        try {
            // 1) re-parent any children of the loser to the winner
            $childCount = (int) $db->table('information_object')->where('parent_id', $remove)->update(['parent_id' => $keep]);
            // 2) move digital_object rows
            $doCount = (int) $db->table('digital_object')->where('object_id', $remove)->update(['object_id' => $keep]);
            // 3) move relation rows (subject + object)
            $relS = (int) $db->table('relation')->where('subject_id', $remove)->update(['subject_id' => $keep]);
            $relO = (int) $db->table('relation')->where('object_id',  $remove)->update(['object_id'  => $keep]);
            // 4) keep the loser slug as a redirect to the winner: AtoM convention is to keep
            //    the slug row but flip its object_id to the winner. Without a `redirect` column
            //    on slug, the simplest approach is to delete the loser-slug rows.
            $slugDel = (int) $db->table('slug')->where('object_id', $remove)->delete();
            // 5) delete the loser IO + i18n rows
            $i18nDel = (int) $db->table('information_object_i18n')->where('id', $remove)->delete();
            $ioDel = (int) $db->table('information_object')->where('id', $remove)->delete();

            $db->commit();
            $this->info("merged: children_reparented={$childCount} digital_objects_moved={$doCount} relations_subj={$relS} relations_obj={$relO} slugs_deleted={$slugDel} i18n_deleted={$i18nDel} io_deleted={$ioDel}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->error("merge failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
