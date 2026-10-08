<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1530. Custom field values now go into the finding aid and the
 * exports. A definition can opt out (an internal working field) with
 * include_in_export = 0; existing fields default to included.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_field_definition') && ! Schema::hasColumn('custom_field_definition', 'include_in_export')) {
            Schema::table('custom_field_definition', function (Blueprint $t) {
                $t->boolean('include_in_export')->default(true)->after('is_repeatable');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('custom_field_definition', 'include_in_export')) {
            Schema::table('custom_field_definition', fn (Blueprint $t) => $t->dropColumn('include_in_export'));
        }
    }
};
