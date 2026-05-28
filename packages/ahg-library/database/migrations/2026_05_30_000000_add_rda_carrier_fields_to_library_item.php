<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds RDA carrier and content type fields to library_item.
     * Adds content_type (336$a), carrier_type (337$a), instance_type (338$a).
     * Idempotent: skips if any column already exists.
     */
    public function up(): void
    {
        if (! Schema::hasTable('library_item')) {
            return;
        }

        Schema::table('library_item', function (Blueprint $table) {
            if (! Schema::hasColumn('library_item', 'content_type')) {
                $table->string('content_type', 100)->nullable()->after('physical_details');
            }
            if (! Schema::hasColumn('library_item', 'carrier_type')) {
                $table->string('carrier_type', 100)->nullable()->after('content_type');
            }
            if (! Schema::hasColumn('library_item', 'instance_type')) {
                $table->string('instance_type', 100)->nullable()->after('carrier_type');
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
                Schema::hasColumn('library_item', 'content_type') ? 'content_type' : null,
                Schema::hasColumn('library_item', 'carrier_type') ? 'carrier_type' : null,
                Schema::hasColumn('library_item', 'instance_type') ? 'instance_type' : null,
            ]);
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
