<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SRU server query log (#1281). SruController already inserts a row per
     * searchRetrieve, but guards on Schema::hasTable - so query logging has
     * been silently disabled because the table was never created. Creating it
     * activates the existing logging. Columns match the controller insert
     * (PSIS ahgLibraryPlugin parity), plus an optional `error` column.
     */
    public function up(): void
    {
        if (Schema::hasTable('library_sru_log')) {
            return;
        }

        Schema::create('library_sru_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('query')->nullable();
            $table->text('cql_query')->nullable()->comment('The parsed/converted CQL query');
            $table->unsignedInteger('result_count')->default(0);
            $table->decimal('duration_ms', 10, 1)->nullable();
            $table->text('error')->nullable();
            $table->string('remote_addr', 45)->nullable();
            $table->string('api_key_hint', 64)->nullable()->comment('SHA-256 prefix of API key used (not the key itself)');
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'idx_created_at');
            $table->index('result_count', 'idx_result_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_sru_log');
    }
};
