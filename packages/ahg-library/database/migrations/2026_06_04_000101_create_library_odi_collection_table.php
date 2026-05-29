<?php

/**
 * Create the library_odi_collection table.
 *
 * Stores Open Discovery Initiative (ODI) quality-scorecard metrics for each
 * library collection (a collection is identified by its parent
 * information_object id). The four headline ODI conformance metrics plus a
 * derived composite quality_score are cached here and refreshed by the
 * ahg:library-odi-refresh console command / OdiScorecardService.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('library_odi_collection')) {
            return;
        }

        Schema::create('library_odi_collection', function (Blueprint $table) {
            $table->bigIncrements('id');
            // collection_id = the parent information_object grouping the items.
            $table->unsignedBigInteger('collection_id')->index();
            $table->string('collection_title', 500)->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->boolean('link_resolver_present')->default(false);
            $table->decimal('oa_percentage', 5, 2)->default(0);
            $table->unsignedInteger('preprints_indexed')->default(0);
            $table->unsignedInteger('orcid_in_records')->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique('collection_id', 'uq_library_odi_collection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_odi_collection');
    }
};
