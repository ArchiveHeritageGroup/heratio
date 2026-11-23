<?php

/**
 * Offline circulation tables and dropdowns - #1474 item 4.
 *
 * The statements live in database/offline_circulation.sql so a migrated
 * instance and a fresh install run exactly the same definition.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('ahg_dropdown')) {
            return;
        }

        DB::unprepared(file_get_contents(__DIR__ . '/../offline_circulation.sql'));
    }

    public function down(): void
    {
        // Deliberately empty: these tables hold the record of real loans.
    }
};
