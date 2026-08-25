<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1492 - provenance for documents FETCHED from an external source.
 *
 * research_workspace_file already carried checksum/checksum_type/mime_type,
 * which covers "is this the same bytes". These two columns cover "where did it
 * come from and when", without which a fetched document is indistinguishable
 * from one a researcher uploaded by hand - and that distinction matters for a
 * research pack that has to defend its sources.
 *
 * Nullable because every existing row, and every future hand-upload, has no
 * source URL. Absent and empty are the same thing here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research_workspace_file')) {
            return;
        }

        Schema::table('research_workspace_file', function (Blueprint $table) {
            if (! Schema::hasColumn('research_workspace_file', 'source_url')) {
                $table->string('source_url', 1024)->nullable()->after('checksum_type');
            }
            if (! Schema::hasColumn('research_workspace_file', 'fetched_at')) {
                $table->dateTime('fetched_at')->nullable()->after('source_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('research_workspace_file')) {
            return;
        }

        Schema::table('research_workspace_file', function (Blueprint $table) {
            foreach (['source_url', 'fetched_at'] as $col) {
                if (Schema::hasColumn('research_workspace_file', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
