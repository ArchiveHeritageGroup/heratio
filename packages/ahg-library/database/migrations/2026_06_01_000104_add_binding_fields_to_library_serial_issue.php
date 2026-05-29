<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Link serial issues to their binding unit + shelf (heratio#1092). Adds
 * binding_id (FK target library_binding.id), shelf_location and bound_at to the
 * existing library_serial_issue table. Each column is added only if missing so
 * the migration is safe on databases where install.sql already created the base
 * table.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_serial_issue')) {
            return;
        }

        Schema::table('library_serial_issue', function (Blueprint $table) {
            if (!Schema::hasColumn('library_serial_issue', 'binding_id')) {
                $table->unsignedBigInteger('binding_id')->nullable()->after('status');
                $table->index('binding_id', 'idx_library_serial_issue_binding');
            }
            if (!Schema::hasColumn('library_serial_issue', 'shelf_location')) {
                $table->string('shelf_location', 255)->nullable()->after('binding_id');
            }
            if (!Schema::hasColumn('library_serial_issue', 'bound_at')) {
                $table->date('bound_at')->nullable()->after('shelf_location');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('library_serial_issue')) {
            return;
        }

        Schema::table('library_serial_issue', function (Blueprint $table) {
            if (Schema::hasColumn('library_serial_issue', 'binding_id')) {
                $table->dropIndex('idx_library_serial_issue_binding');
                $table->dropColumn('binding_id');
            }
            if (Schema::hasColumn('library_serial_issue', 'shelf_location')) {
                $table->dropColumn('shelf_location');
            }
            if (Schema::hasColumn('library_serial_issue', 'bound_at')) {
                $table->dropColumn('bound_at');
            }
        });
    }
};
