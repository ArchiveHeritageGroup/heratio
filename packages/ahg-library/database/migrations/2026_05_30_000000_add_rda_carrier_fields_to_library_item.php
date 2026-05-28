<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds RDA carrier and content type fields to library_item for CJK/Scripta
     * supported content type, carrier type, and instance type columns.
     */
    public function up(): void
    {
        Schema::table('library_item', function (Blueprint $table) {
            $table->string('content_type', 100)->nullable()->after('physical_details');
            $table->string('carrier_type', 100)->nullable()->after('content_type');
            $table->string('instance_type', 100)->nullable()->after('carrier_type');
        });
    }

    public function down(): void
    {
        Schema::table('library_item', function (Blueprint $table) {
            $table->dropColumn(['content_type', 'carrier_type', 'instance_type']);
        });
    }
};
