<?php

/**
 * Reconstruct the NER extraction ledger from the entities it never recorded (#1472).
 *
 * `ahg_ner_extraction` rows were opened with status='pending' and entity_count=0
 * and never closed - nothing in the codebase ever updated either column. So the
 * table showed 192 pending rows and nothing completed since 25 January, while
 * 2,108 entities sat in `ahg_ner_entity` and 171 of those "pending" rows had
 * their entities all along.
 *
 * The writers are fixed (NerExtractionLedger). This repairs the history.
 *
 * WHAT CAN BE RECONSTRUCTED, AND WHAT CANNOT:
 *
 *   - A row with entities linked to it by `ahg_ner_entity.extraction_id`: the
 *     scan plainly completed, and those linked rows ARE the count. Mark it
 *     completed with that exact number. All 2,108 entity rows carry the link, so
 *     every run that ever produced an entity is recoverable this way.
 *   - A row with no linked entities: unknowable. It might have been a clean scan
 *     that found nothing, or a scan that failed. Those are exactly the two cases
 *     this issue exists to keep apart, so inventing an outcome would repeat the
 *     original mistake. Left as 'pending', which now means precisely "legacy row
 *     of indeterminate outcome" - new code never writes that status.
 *
 * NOT counted per OBJECT. A first draft of this migration fell back to counting
 * an object's entities when a row had no link, and applied that total to EVERY
 * extraction row for the object - 237 rows across 66 objects - inflating the
 * ledger to 13,328 entities against 2,108 that exist. Attribution is per run or
 * it is nothing.
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
    public function up(): void
    {
        if (! Schema::hasTable('ahg_ner_extraction') || ! Schema::hasTable('ahg_ner_entity')) {
            return;
        }

        $before = DB::table('ahg_ner_extraction')->where('status', 'pending')->count();
        if ($before === 0) {
            return;
        }

        // Attribute each run its OWN entities, via the extraction_id link.
        DB::statement("
            UPDATE ahg_ner_extraction x
              JOIN (SELECT extraction_id, COUNT(*) AS n
                      FROM ahg_ner_entity
                     WHERE extraction_id IS NOT NULL
                     GROUP BY extraction_id) e
                ON e.extraction_id = x.id
               SET x.status = 'completed', x.entity_count = e.n
        ");

        // Anything with no linked entities carries no evidence of its outcome.
        // Idempotent and self-correcting: it also repairs a row that an earlier
        // run of this migration wrongly marked completed.
        DB::statement("
            UPDATE ahg_ner_extraction x
             LEFT JOIN (SELECT DISTINCT extraction_id FROM ahg_ner_entity WHERE extraction_id IS NOT NULL) e
                ON e.extraction_id = x.id
               SET x.status = 'pending', x.entity_count = 0
             WHERE e.extraction_id IS NULL
        ");

        $after = DB::table('ahg_ner_extraction')->where('status', 'pending')->count();
        $completed = DB::table('ahg_ner_extraction')->where('status', 'completed')->count();
        $entities = (int) DB::table('ahg_ner_extraction')->sum('entity_count');

        Log::info("#1472 NER ledger backfill: pending {$before} -> {$after}; "
            ."{$completed} rows now completed, entity_count total now {$entities}. "
            .'Remaining pending rows are legacy rows whose outcome cannot be reconstructed '
            .'(object has no entities - could be a clean scan or a failed one).');
    }

    public function down(): void
    {
        // Not reversible: the previous state was "no outcome recorded at all",
        // which carried no information to restore.
    }
};
