<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1574. A scan of a record's metadata says nothing about what its
 * scanned pages show, so only a scan that read the files' text ('files') can
 * clear them for visitors where screening is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ahg_pii_scan_report') && ! Schema::hasColumn('ahg_pii_scan_report', 'coverage')) {
            Schema::table('ahg_pii_scan_report', function (Blueprint $t) {
                $t->string('coverage', 16)->default('metadata')->after('jurisdiction')
                    ->comment('metadata = description fields only; files = the digital objects\' text was read');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ahg_pii_scan_report', 'coverage')) {
            Schema::table('ahg_pii_scan_report', fn (Blueprint $t) => $t->dropColumn('coverage'));
        }
    }
};
