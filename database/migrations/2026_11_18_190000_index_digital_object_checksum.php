<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1745: every upload now looks for an identical file already in the
 * catalogue by checksum. Without an index that lookup scans digital_object.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('digital_object') || ! Schema::hasColumn('digital_object', 'checksum')) {
            return;
        }
        if (! DB::select("SHOW INDEX FROM digital_object WHERE Key_name = 'idx_do_checksum'")) {
            DB::statement('CREATE INDEX idx_do_checksum ON digital_object (checksum(64))');
        }
    }

    public function down(): void
    {
        if (DB::select("SHOW INDEX FROM digital_object WHERE Key_name = 'idx_do_checksum'")) {
            DB::statement('DROP INDEX idx_do_checksum ON digital_object');
        }
    }
};
