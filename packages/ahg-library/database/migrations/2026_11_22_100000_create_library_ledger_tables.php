<?php

/**
 * Invoicing sub-ledger tables and dropdowns (heratio#1475 item 5).
 *
 * @author    Dr Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared((string) file_get_contents(__DIR__ . '/../ledger_install.sql'));
        if (Schema::hasTable('ahg_dropdown')) {
            DB::unprepared((string) file_get_contents(__DIR__ . '/../seed_ledger_dropdowns.sql'));
        }
    }

    public function down(): void
    {
        // Financial records are never dropped by a rollback.
    }
};
