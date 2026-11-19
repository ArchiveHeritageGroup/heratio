<?php

/**
 * RecordsArchiveCommand - heratio#1760: the automated archiving tier.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A declared record that has been inactive for records_archive_after_days
 * (default 0: off) moves to the archive tier on its own: its declaration
 * becomes "archived" (still read-only) and the move is logged. Records under
 * an active legal hold stay where they are. Destruction is not automated on
 * purpose: it still needs the disposal approval.
 *
 * ponytail: the tier is a status, not a storage move. Moving archived masters
 * to cheaper storage is the upgrade path (a storage-class setting per tier).
 */
class RecordsArchiveCommand extends Command
{
    protected $signature = 'ahg:records-archive {--dry-run : List what would move, change nothing}';

    protected $description = 'Move declared records inactive past the configured period to the archive tier';

    public function handle(): int
    {
        $days = AhgSettingsService::getInt('records_archive_after_days', 0);
        if ($days <= 0 || ! Schema::hasTable('record_declaration')) {
            $this->info('Automatic archiving is off (records_archive_after_days = 0).');

            return self::SUCCESS;
        }
        $cutoff = now()->subDays($days);
        $q = DB::table('record_declaration as d')
            ->join('information_object as io', 'io.id', '=', 'd.information_object_id')
            ->join('object as o', 'o.id', '=', 'io.id')
            ->where('d.status', 'declared')
            ->where('d.declared_at', '<=', $cutoff)
            ->where('o.updated_at', '<=', $cutoff);
        if (Schema::hasTable('integrity_legal_hold')) {
            $q->whereNotExists(fn ($s) => $s->from('integrity_legal_hold as h')->whereColumn('h.information_object_id', 'd.information_object_id')->where('h.status', 'active'));
        }
        $rows = $q->get(['d.id', 'd.information_object_id']);
        if ($this->option('dry-run')) {
            $this->info($rows->count().' record(s) would move to the archive tier.');

            return self::SUCCESS;
        }
        foreach ($rows as $r) {
            DB::table('record_declaration')->where('id', $r->id)->update([
                'status' => 'archived', 'archived_at' => now(), 'updated_at' => now(),
                'notes' => DB::raw("CONCAT(COALESCE(notes, ''), '\nMoved to the archive tier automatically after ".(int) $days." days inactive.')"),
            ]);
        }
        $this->info($rows->count().' record(s) moved to the archive tier.');

        return self::SUCCESS;
    }
}
