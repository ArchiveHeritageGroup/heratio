<?php

/**
 * archaeology_context_relationship - the stratigraphic (Harris Matrix) edges
 * between contexts. #1428 Phase 2.
 *
 * Each logical relationship is stored as two rows (both directions) so a context
 * sheet can show its own relationships without a UNION; the service keeps the
 * mirror in step (above<->below, cuts<->cut_by, fills<->filled_by; same_as /
 * bonds_with / abuts are symmetric).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('archaeology_context_relationship')) {
            return;
        }

        Schema::create('archaeology_context_relationship', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('context_id')->index();
            $table->unsignedBigInteger('related_context_id')->index();
            // above / below / cuts / cut_by / fills / filled_by / same_as /
            // bonds_with / abuts
            $table->string('relationship_type', 20);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(
                ['context_id', 'related_context_id', 'relationship_type'],
                'archctxrel_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archaeology_context_relationship');
    }
};
