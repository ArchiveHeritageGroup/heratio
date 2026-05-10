<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.B — sharepoint_user_mapping table.
 * Mirror of atom-ahg-plugins/ahgSharePointPlugin/database/migrations/20260510_phase2b_user_mapping.sql.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sharepoint_user_mapping')) {
            return;
        }
        Schema::create('sharepoint_user_mapping', function (Blueprint $table) {
            $table->id();
            $table->string('aad_object_id', 64)->unique()->comment('AAD oid claim');
            $table->string('aad_upn', 255)->nullable();
            $table->string('aad_email', 255)->nullable();
            $table->integer('atom_user_id')->comment('FK to user.id');
            $table->string('created_by', 20)->default('auto')->comment('auto, manual, admin');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('last_seen_at')->nullable();
            $table->index('atom_user_id', 'idx_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharepoint_user_mapping');
    }
};
