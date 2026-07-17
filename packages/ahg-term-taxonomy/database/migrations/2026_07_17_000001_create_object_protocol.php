<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1388 / #1406 Phase P1 - direct object-level community protocol.
 *
 * Sibling of `term_protocol`: attaches a TK / BC label + access condition +
 * owning community directly to an object (information_object or digital_object),
 * for cases where the protocol belongs to the item itself rather than to a
 * taxonomy term tagged on it. The enforcement engine ({@see TermProtocolGate})
 * evaluates the strictest of {direct object protocol, inherited term protocols}.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('object_protocol')) {
            return;
        }
        Schema::create('object_protocol', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('target_type', 32)->default('information_object'); // information_object | digital_object
            $table->unsignedBigInteger('target_id');
            $table->string('label_family', 8)->nullable();   // tk | bc
            $table->string('label_code', 64)->nullable();    // e.g. tk_secret, bc_provenance
            // open | attribution | non_commercial | community_voice
            // | restricted | sacred_secret | seasonal | gendered
            $table->string('access_condition', 32)->default('open');
            $table->unsignedBigInteger('owner_actor_id')->nullable();  // source community (an authority record)
            $table->string('region_module', 64)->nullable(); // owning per-region plugin
            $table->string('pid', 255)->nullable();          // sovereign PID (DOCiD) once minted
            $table->boolean('no_equivalent')->default(false); // "no Western equivalent" - a valid state
            $table->boolean('is_notice')->default(false);    // Notice (advisory) vs Label (community-authored/enforcing)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
            $table->index('owner_actor_id');
            $table->index('access_condition');
            // No hard FKs: AtoM object ids are plain ints and cross-engine FKs are
            // brittle here; the enforcement joins are index-backed.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('object_protocol');
    }
};
