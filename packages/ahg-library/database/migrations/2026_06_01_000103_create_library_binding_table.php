<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Serial binding units (heratio#1092). Records the binding of a run of issues
 * (a volume range) into a single physical bound volume, with a shelf location.
 * Individual issues are linked back to a binding via library_serial_issue.binding_id.
 * `status` uses VARCHAR (ahg_dropdown taxonomy library_binding_status), not an ENUM.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_binding')) {
            Schema::create('library_binding', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('serial_id');
                $table->string('volume_range', 120)->default('');
                $table->string('status', 32)->default('pending'); // pending | at_bindery | bound | shelved
                $table->date('bound_at')->nullable();
                $table->string('location', 255)->nullable();
                $table->timestamps();

                $table->index('serial_id', 'idx_library_binding_serial');
                $table->index('status', 'idx_library_binding_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_binding');
    }
};
