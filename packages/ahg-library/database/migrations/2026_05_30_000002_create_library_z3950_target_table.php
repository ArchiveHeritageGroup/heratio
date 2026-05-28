<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create library_z3950_target — Z39.50 server configuration for copy
     * cataloguing. Idempotent: fully skips if table already exists (may have
     * been created by the partial batch-22 run of migration
     * 2026_05_01_000001).
     */
    public function up(): void
    {
        if (! Schema::hasTable('library_z3950_target')) {
            Schema::create('library_z3950_target', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255)->comment('Display name for the target');
                $table->string('host', 255)->comment('Hostname or IP address');
                $table->unsignedInteger('port')->default(210)->comment('Z39.50 port, default 210');
                $table->string('database_name', 255)->default('Default')
                    ->comment('Target database / database set name');
                $table->string('syntax', 50)->default('USmarc')
                    ->comment('Record syntax: USmarc | MARC21 | XML | SUTRS');
                $table->string('element_set', 10)->default('F')
                    ->comment('Element set: F (full) | B (brief) | S (suggested)');
                $table->string('username', 255)->nullable()
                    ->comment('Authentication username (if required)');
                $table->string('password', 255)->nullable()
                    ->comment('Authentication password');
                $table->boolean('active')->default(true)
                    ->comment('Include this target in search lists');
                $table->unsignedSmallInteger('sort_order')->default(0)
                    ->comment('Display order in dropdown');
                $table->timestamps();

                $table->index(['active', 'sort_order'], 'idx_z3950_target_active_sort');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_z3950_target');
    }
};
