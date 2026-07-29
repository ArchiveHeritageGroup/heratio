<?php

/**
 * archaeology_context - stratigraphic unit ("layer") recording. Phase 1 of the
 * stratigraphic-context module (heratio#1428). Adds the context entity and links
 * finds to it; stratigraphic relationships (Harris Matrix) land in Phase 2.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archaeology_context')) {
            Schema::create('archaeology_context', function (Blueprint $table) {
                $table->id();

                // Descriptive record. Title, ACL, notes and the context's plan /
                // section drawings (digital objects) live there - same pairing
                // archaeology_site and archaeology_object already use.
                $table->unsignedInteger('information_object_id')->nullable()->index();

                // The dig this context belongs to.
                $table->unsignedBigInteger('site_id')->index();

                // Context number - unique within a site (e.g. "1002"). Kept as a
                // string because single-context recording numbers are not always
                // integers ([1003], SF221, A.14).
                $table->string('context_number', 50)->index();

                // Controlled vocabulary, not free text: deposit / cut / fill /
                // layer / surface / masonry / skeleton / structure. Terms can
                // carry ICIP protocols (term_protocol) for sensitive contexts.
                $table->unsignedInteger('context_type_id')->nullable()->index();

                $table->text('description')->nullable();
                $table->text('interpretation')->nullable();

                // The "layer" geometry - upper and lower excavated surfaces.
                $table->decimal('top_elevation_m', 8, 3)->nullable();
                $table->decimal('bottom_elevation_m', 8, 3)->nullable();

                // Provenance of excavation.
                $table->string('excavation_reference', 100)->nullable()
                    ->comment('Trench / square / spit');
                $table->string('excavator', 255)->nullable();
                $table->date('excavation_date')->nullable();

                // Site phasing / period grouping (controlled term).
                $table->unsignedInteger('phase_id')->nullable()->index();

                // Dating as strings (archaeological convention: "c. 1400 AD", "2500 BP").
                $table->string('date_earliest', 50)->nullable();
                $table->string('date_latest', 50)->nullable();
                $table->text('dating_note')->nullable();

                $table->string('status', 30)->default('active')->index();
                $table->timestamps();

                $table->unique(['site_id', 'context_number'], 'archctx_site_number_unique');
            });
        }

        // Link finds to their context (replacing the free-text context_reference).
        if (Schema::hasTable('archaeology_object') && ! Schema::hasColumn('archaeology_object', 'context_id')) {
            Schema::table('archaeology_object', function (Blueprint $table) {
                $table->unsignedBigInteger('context_id')->nullable()->index()->after('site_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('archaeology_object') && Schema::hasColumn('archaeology_object', 'context_id')) {
            Schema::table('archaeology_object', function (Blueprint $table) {
                $table->dropColumn('context_id');
            });
        }
        Schema::dropIfExists('archaeology_context');
    }
};
