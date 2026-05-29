<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Serial issue claims (heratio#1092). A claim is raised against a serial (and
 * optionally a specific issue row) when an expected issue fails to arrive. The
 * daily claim-alert command emails the subscription contact and records the
 * claim here. `status` uses VARCHAR (ahg_dropdown taxonomy library_claim_status),
 * never an ENUM.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_claim')) {
            Schema::create('library_claim', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('serial_id');
                $table->unsignedBigInteger('issue_id')->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->string('claimed_by', 255)->nullable();
                $table->text('reason')->nullable();
                $table->string('status', 32)->default('open'); // open | sent | resolved | cancelled
                $table->timestamps();

                $table->index('serial_id', 'idx_library_claim_serial');
                $table->index('issue_id', 'idx_library_claim_issue');
                $table->index('status', 'idx_library_claim_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_claim');
    }
};
