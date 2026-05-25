<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the ric_occupation table — backs the RiC-O Occupation entity
 * (rico:Occupation) missing from ahg-ric per the #660 audit.
 *
 * An Occupation is a role, profession, or position held by an actor over a
 * time-span. Models ISAAR(CPF) section 5.2.6 / rico:hasOrHadOccupation.
 *
 * Phase 1: just the table + minimal CRUD. Position/Mechanism subtypes are
 * deferred to a later phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ric_occupation')) {
            return;
        }

        Schema::create('ric_occupation', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Note: actor.id is INT (signed) in base AtoM, not BIGINT. FK column
            // type must match the referenced PK exactly or MySQL refuses the FK.
            $table->integer('actor_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('source_culture', 16)->nullable();
            $table->timestamps();

            $table->index('actor_id');
            $table->index(['start_date', 'end_date'], 'ric_occupation_date_range_idx');
            $table->index('is_current');

            $table->foreign('actor_id')
                ->references('id')->on('actor')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ric_occupation');
    }
};
