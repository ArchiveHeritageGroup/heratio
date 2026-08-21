<?php

/**
 * Give orphan NER entities a ledger row to belong to (#1472).
 *
 * The backfill in 2026_08_21_110000 attributes each extraction row its own
 * entities via `ahg_ner_entity.extraction_id`. On dev that reconciled exactly.
 * On heratio.org it did not: 2,123 entities stored against 2,108 accounted for,
 * and 61 objects holding entities against 47 the privacy statistics counted.
 *
 * The difference is 15 entities with `extraction_id IS NULL` - written by a code
 * path that saved entities without ever opening a ledger row, so there is nothing
 * for the backfill to attribute them to. On heratio.org they are 14 objects, all
 * scanned on 2026-08-14. Dev and sasa have none.
 *
 * Leaving them stranded would leave the privacy surface still under-reporting the
 * objects that contain detected entities, which is the whole point of the issue.
 *
 * WHAT IS RECONSTRUCTED, AND FROM WHAT EVIDENCE. One ledger row per
 * (object_id, day-the-entities-were-created), because entities written together
 * on one day for one object are one scan:
 *
 *   object_id     - the entity's own object_id
 *   extracted_at  - MIN(created_at) of the entities being adopted
 *   entity_count  - how many are being adopted
 *   status        - 'completed'; these entities exist, so the scan plainly finished
 *   backend_used  - 'local', matching every other row in the table
 *
 * The entities are then linked to the row, so the ledger reconciles and STAYS
 * reconciled - a later re-run of the backfill will recount them correctly rather
 * than orphaning them again.
 *
 * Idempotent: a second run finds no entities with a null extraction_id.
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

        $groups = DB::table('ahg_ner_entity')
            ->selectRaw('object_id, DATE(created_at) AS day, COUNT(*) AS n, MIN(created_at) AS first_seen')
            ->whereNull('extraction_id')
            ->whereNotNull('object_id')
            ->groupBy('object_id', 'day')
            ->get();

        if ($groups->isEmpty()) {
            return;
        }

        $rows = 0;
        $adopted = 0;

        foreach ($groups as $g) {
            DB::transaction(function () use ($g, &$rows, &$adopted) {
                $id = (int) DB::table('ahg_ner_extraction')->insertGetId([
                    'object_id' => (int) $g->object_id,
                    'backend_used' => 'local',
                    'status' => 'completed',
                    'entity_count' => (int) $g->n,
                    'extracted_at' => $g->first_seen,
                ]);

                $adopted += DB::table('ahg_ner_entity')
                    ->whereNull('extraction_id')
                    ->where('object_id', (int) $g->object_id)
                    ->whereDate('created_at', $g->day)
                    ->update(['extraction_id' => $id]);

                $rows++;
            });
        }

        $ledger = (int) DB::table('ahg_ner_extraction')->sum('entity_count');
        $stored = (int) DB::table('ahg_ner_entity')->count();

        Log::info("#1472 orphan adoption: {$rows} ledger row(s) reconstructed, {$adopted} entities adopted; "
            ."ledger now accounts for {$ledger} of {$stored} stored entities.");

        if ($ledger !== $stored) {
            Log::warning("#1472 ledger still does not reconcile: {$ledger} counted vs {$stored} stored - investigate.");
        }
    }

    public function down(): void
    {
        // Not reversible: the previous state was entities belonging to no
        // recorded run at all, which carried no information to restore.
    }
};
