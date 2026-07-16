<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1388 Phase 1.1 - term-plus-protocol-plus-owner.
 *
 * Attaches a community access protocol (TK / BC label + access condition +
 * owning community) to a taxonomy term, without touching AtoM's nested-set
 * `term` table. A term may carry more than one protocol facet (e.g. a TK label
 * and a BC label); the enforcement engine takes the strictest condition.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('term_protocol')) {
            return;
        }
        Schema::create('term_protocol', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('term_id');
            $table->string('label_family', 8)->nullable();   // tk | bc
            $table->string('label_code', 64)->nullable();    // e.g. tk_secret, bc_provenance
            // open | attribution | non_commercial | community_voice
            // | restricted | sacred_secret | seasonal | gendered
            $table->string('access_condition', 32)->default('open');
            $table->unsignedBigInteger('owner_actor_id')->nullable();  // source community (an authority record)
            $table->string('region_module', 64)->nullable(); // owning per-region plugin
            $table->string('pid', 255)->nullable();          // sovereign PID (DOCiD) once minted
            $table->boolean('no_equivalent')->default(false); // "no Western equivalent" - a valid state
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('term_id');
            $table->index('owner_actor_id');
            $table->index('access_condition');
            // No hard FKs: AtoM `term`/`actor` ids are plain ints and cross-engine
            // FKs are brittle here; the enforcement joins are index-backed.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_protocol');
    }
};
