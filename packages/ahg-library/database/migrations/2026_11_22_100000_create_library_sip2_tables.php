<?php

/**
 * SIP2 self-check tables - #1475 item 2. Additive; the DDL lives in
 * database/install_sip2.sql so fresh installs and migrations share it.
 *
 * Copyright (C) 2026 Johan Pieterse
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(__DIR__ . '/../install_sip2.sql'));
    }

    public function down(): void
    {
        // Deliberately empty: these tables hold payment records and PINs.
    }
};
