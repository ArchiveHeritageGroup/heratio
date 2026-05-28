<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the library_z3950_target table for managing Z39.50 target hosts.
     */
    public function up(): void
    {
        if (! Schema::hasTable('library_z3950_target')) {
            Schema::create('library_z3950_target', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('host', 255);
                $table->unsignedInteger('port')->default(210);
                $table->string('database_name', 255)->default('Default');
                $table->string('syntax', 50)->default('USmarc');
                $table->string('element_set', 10)->default('F');
                $table->string('username', 255)->nullable();
                $table->string('password', 255)->nullable();
                $table->tinyInteger('active')->default(1);
                $table->smallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_z3950_target');
    }
};
