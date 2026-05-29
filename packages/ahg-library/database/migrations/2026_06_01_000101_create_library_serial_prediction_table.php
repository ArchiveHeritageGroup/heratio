<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Persisted issue predictions for serials (heratio#1092). The prediction engine
 * (LibrarySerialService + LibrarySerialEnumerationParser) writes the upcoming
 * expected issues here so the claim-alert command and the UI can read a stable
 * forecast rather than recomputing on every request.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_serial_prediction')) {
            Schema::create('library_serial_prediction', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('serial_id');
                $table->string('volume', 32)->default('');
                $table->string('issue_number', 32)->default('');
                $table->date('expected_date')->nullable();
                $table->integer('days_until')->default(0);
                $table->timestamp('created_at')->nullable();

                $table->index('serial_id', 'idx_library_serial_prediction_serial');
                $table->index('expected_date', 'idx_library_serial_prediction_expected');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_serial_prediction');
    }
};
