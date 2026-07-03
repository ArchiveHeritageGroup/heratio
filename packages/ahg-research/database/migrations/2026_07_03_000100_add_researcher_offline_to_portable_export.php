<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Researcher offline packages (Phase 1–2).
 *
 * A researcher can take a *group* of records (research project / collection /
 * workspace / favourites folder) offline as an editable portable-export bundle,
 * scoped by their own view rights. We reuse the existing portable_export
 * pipeline and tag the rows so they can be:
 *   - listed/owned per researcher (researcher_user_id),
 *   - traced back to the group they came from (group_source + group_ref),
 *   - verified on sync-back (sync_token — embedded in the bundle manifest and
 *     echoed by the researcher-sync.json the offline viewer produces).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portable_export')) {
            return;
        }

        Schema::table('portable_export', function (Blueprint $table) {
            if (! Schema::hasColumn('portable_export', 'researcher_user_id')) {
                $table->integer('researcher_user_id')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('portable_export', 'group_source')) {
                // project | collection | workspace | favorites
                $table->string('group_source', 20)->nullable()->after('researcher_user_id');
            }
            if (! Schema::hasColumn('portable_export', 'group_ref')) {
                $table->integer('group_ref')->nullable()->after('group_source');
            }
            if (! Schema::hasColumn('portable_export', 'sync_token')) {
                $table->string('sync_token', 64)->nullable()->after('group_ref');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('portable_export')) {
            return;
        }

        Schema::table('portable_export', function (Blueprint $table) {
            foreach (['researcher_user_id', 'group_source', 'group_ref', 'sync_token'] as $col) {
                if (Schema::hasColumn('portable_export', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
