<?php

/**
 * #1586 - versioned Portable Export releases. Additive: new tables and new
 * dropdown rows only. The definitions live in install.sql, so a fresh install
 * and an upgrade cannot drift apart.
 */

use AhgPortableExport\Services\ReleaseSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ReleaseSchema::install();
    }

    public function down(): void
    {
        // Deliberately empty: these tables record DOIs that exist outside this system.
    }
};
