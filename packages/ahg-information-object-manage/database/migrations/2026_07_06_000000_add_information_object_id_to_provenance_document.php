<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: ProvenanceService::getDocuments() queries provenance_document by
 * information_object_id, but the consolidate_provenance_stacks migration
 * retired provenance_record and left provenance_document with only
 * provenance_record_id / provenance_event_id — so the column never existed and
 * the /provenance page 500'd ("Unknown column 'information_object_id'").
 *
 * This adds the per-IO link column and backfills it from the retired record
 * model (directly via provenance_record_id, else via the event chain). Guarded
 * + idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provenance_document')) {
            return;
        }

        if (! Schema::hasColumn('provenance_document', 'information_object_id')) {
            Schema::table('provenance_document', function (Blueprint $t) {
                $t->unsignedBigInteger('information_object_id')->nullable()->after('id');
                $t->index('information_object_id', 'idx_provdoc_io');
            });
        }

        // Backfill only where the legacy link columns actually exist — the
        // provenance_document schema varies across instances (some have
        // provenance_record_id / provenance_event_id, some don't).
        $retiredUsable = Schema::hasTable('provenance_record_retired')
            && Schema::hasColumn('provenance_record_retired', 'information_object_id');

        if ($retiredUsable && Schema::hasColumn('provenance_document', 'provenance_record_id')) {
            // Direct: document -> retired record.
            DB::statement('UPDATE provenance_document pd
                JOIN provenance_record_retired prr ON prr.id = pd.provenance_record_id
                SET pd.information_object_id = prr.information_object_id
                WHERE pd.information_object_id IS NULL AND pd.provenance_record_id IS NOT NULL');
        }

        if ($retiredUsable
            && Schema::hasColumn('provenance_document', 'provenance_event_id')
            && Schema::hasTable('provenance_event')
            && Schema::hasColumn('provenance_event', 'provenance_record_id')) {
            // Via the event chain when the document links by event, not record.
            DB::statement('UPDATE provenance_document pd
                JOIN provenance_event pe ON pe.id = pd.provenance_event_id
                JOIN provenance_record_retired prr ON prr.id = pe.provenance_record_id
                SET pd.information_object_id = prr.information_object_id
                WHERE pd.information_object_id IS NULL AND pd.provenance_event_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('provenance_document', 'information_object_id')) {
            Schema::table('provenance_document', function (Blueprint $t) {
                $t->dropIndex('idx_provdoc_io');
                $t->dropColumn('information_object_id');
            });
        }
    }
};
