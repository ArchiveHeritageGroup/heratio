<?php

/**
 * #1464 - drop the tables the consolidation superseded.
 *
 * Each was migrated to its successor in an earlier release and now has ZERO
 * live code references (verified by sweeping packages/ and app/ for the table
 * name, discounting comments and CREATE/DROP statements):
 *
 *   rights_embargo, rights_embargo_i18n   -> embargo            (v1.154.579)
 *   extended_rights, extended_rights_i18n -> rights_record      (v1.154.582)
 *   extended_rights_tk_label              -> icip_tk_label      (v1.154.581)
 *   rights_object_tk_label                -> icip_tk_label      (never held a
 *       row and its four service methods had no callers at all; they were
 *       removed in this release, so the table is now unreferenced)
 *
 * KEPT DELIBERATELY:
 *   rights_embargo_log  - not a duplicate. It is the embargo audit trail, keyed
 *                         by embargo id, and it keeps working against the
 *                         surviving `embargo` table.
 *   agreement_rights_vocabulary - 25 curated donor-agreement terms
 *                         (USE_RESEARCH, USE_EDUCATION, ...) that nothing reads
 *                         yet. Authored reference data for a feature never
 *                         wired up. Whether to wire it or abandon it is a
 *                         question for a human; a cleanup migration should not
 *                         answer it by deleting.
 *
 * Data was carried to the successors by the earlier migrations, and a
 * pre-drop mysqldump of every instance was taken at release time. This drops
 * the emptied originals only after their replacements have been live.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Dropped only once its successor is present and carries the data. */
    private const SUPERSEDED = [
        'rights_embargo_i18n' => 'embargo',
        'rights_embargo' => 'embargo',
        'extended_rights_tk_label' => 'icip_tk_label',
        'extended_rights_i18n' => 'rights_record',
        'extended_rights' => 'rights_record',
        'rights_object_tk_label' => 'icip_tk_label',
    ];

    public function up(): void
    {
        $this->releaseEmbargoLogConstraint();

        foreach (self::SUPERSEDED as $table => $successor) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Never drop into a void: if the successor is missing, the data has
            // nowhere to have gone and the source must stay.
            if (! Schema::hasTable($successor)) {
                Log::warning("#1464: kept `{$table}` - successor `{$successor}` is absent on this install.");

                continue;
            }

            $rows = (int) DB::table($table)->count();
            $successorRows = (int) DB::table($successor)->count();

            // An emptied source is fine, and so is a populated one whose
            // successor also has rows (that is the migrated state). A populated
            // source with an EMPTY successor means the migration never ran here.
            if ($rows > 0 && $successorRows === 0) {
                Log::warning("#1464: kept `{$table}` - it holds {$rows} row(s) but `{$successor}` is empty, so the data was never carried over.");

                continue;
            }

            Schema::drop($table);
            Log::info("#1464: dropped `{$table}` (superseded by `{$successor}`).");
        }
    }

    public function down(): void
    {
        // Not reverted. Recreating empty shells would not restore anything, and
        // the code no longer reads them. The pre-drop dumps are the restore path.
    }

    /**
     * rights_embargo_log.embargo_id has a foreign key onto rights_embargo, so
     * the constraint has to go before the parent can be dropped.
     *
     * The constraint is NOT repointed at `embargo`. Those ids belong to the old
     * rights_embargo id space, and the consolidation MERGED rows rather than
     * copying them - so an id that happens to exist in `embargo` is very likely
     * a different embargo altogether. Manufacturing that linkage would be worse
     * than losing it. The log stays as an append-only historical record whose
     * ids refer to the retired table; do not join it to `embargo`.
     */
    private function releaseEmbargoLogConstraint(): void
    {
        if (! Schema::hasTable('rights_embargo_log')) {
            return;
        }

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE'
            .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            .' AND REFERENCED_TABLE_NAME = ?',
            ['rights_embargo_log', 'rights_embargo']
        );

        foreach ($constraints as $c) {
            try {
                DB::statement('ALTER TABLE `rights_embargo_log` DROP FOREIGN KEY `'.$c->CONSTRAINT_NAME.'`');
                Log::info("#1464: dropped FK {$c->CONSTRAINT_NAME} so rights_embargo could be retired; rights_embargo_log is kept as a historical audit trail.");
            } catch (\Throwable $e) {
                Log::warning('#1464: could not drop FK '.$c->CONSTRAINT_NAME.': '.$e->getMessage());
            }
        }
    }
};
