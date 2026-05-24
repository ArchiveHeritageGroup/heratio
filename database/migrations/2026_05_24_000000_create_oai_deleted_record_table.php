<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the oai_deleted_record table — tombstones for records previously
 * disseminated via OAI-PMH that have since been removed or unpublished.
 *
 * Backs the Phase 4 change of <deletedRecord>no</deletedRecord> to "transient":
 * once we record a tombstone here, the OAI server emits a <header status="deleted">
 * for that identifier so downstream harvesters can clean up their copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oai_deleted_record')) {
            return;
        }

        Schema::create('oai_deleted_record', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('oai_local_identifier')->unique();
            $table->dateTime('deleted_at')->useCurrent();
            $table->string('reason', 255)->nullable();
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oai_deleted_record');
    }
};
