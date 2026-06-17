<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MARC control fields + FRBR override metadata on library_item (#1281).
     * heratio already parses the MARC leader / 005 / 008 (Marc21DecoderService,
     * CopyCataloguingService) but had nowhere to persist them; work_key exists,
     * but not the override-type marker or the free-text description. Idempotent.
     */
    public function up(): void
    {
        if (! Schema::hasTable('library_item')) {
            return;
        }

        Schema::table('library_item', function (Blueprint $table) {
            if (! Schema::hasColumn('library_item', 'marc_leader')) {
                $table->string('marc_leader', 24)->nullable()->comment('MARC 24-byte leader');
            }
            if (! Schema::hasColumn('library_item', 'marc_005')) {
                $table->string('marc_005', 16)->nullable()->comment('MARC 005 latest transaction datetime');
            }
            if (! Schema::hasColumn('library_item', 'marc_008')) {
                $table->string('marc_008', 40)->nullable()->comment('MARC 008 fixed-length data elements');
            }
            if (! Schema::hasColumn('library_item', 'frbr_override_type')) {
                $table->string('frbr_override_type', 32)->nullable()->comment('How work_key was set: auto | manual');
            }
            if (! Schema::hasColumn('library_item', 'description')) {
                $table->text('description')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('library_item')) {
            return;
        }
        Schema::table('library_item', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('library_item', 'marc_leader') ? 'marc_leader' : null,
                Schema::hasColumn('library_item', 'marc_005') ? 'marc_005' : null,
                Schema::hasColumn('library_item', 'marc_008') ? 'marc_008' : null,
                Schema::hasColumn('library_item', 'frbr_override_type') ? 'frbr_override_type' : null,
                Schema::hasColumn('library_item', 'description') ? 'description' : null,
            ]);
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
