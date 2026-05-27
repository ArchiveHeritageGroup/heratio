<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * #763 FRBR force-group / force-split overrides. Cataloguers can pin
     * two library_item rows to the same work-key (force_group) or pull them
     * apart (force_split), bypassing the algorithmic key.
     */
    public function up(): void
    {
        if (Schema::hasTable('library_work_override')) {
            return;
        }
        Schema::create('library_work_override', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('library_item_id');
            $t->enum('mode', ['force_group', 'force_split']);
            // For force_group: target work-key the item should adopt.
            // For force_split: synthesized work-key prefixed with split:
            $t->string('override_key', 64);
            $t->string('reason', 500)->nullable();
            $t->unsignedBigInteger('cataloguer_user_id')->nullable();
            $t->timestamps();

            $t->index('library_item_id', 'ix_library_work_override_item');
            $t->index('override_key', 'ix_library_work_override_key');
            $t->unique(['library_item_id', 'mode'], 'uq_library_work_override_item_mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_work_override');
    }
};
