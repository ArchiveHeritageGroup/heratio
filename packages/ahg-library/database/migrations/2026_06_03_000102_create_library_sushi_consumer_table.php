<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * SUSHI consumer registry (heratio#1096). SushiServerController::authorise()
 * already queries this table (customer_id + requestor_id + api_key_hash) but no
 * migration existed. This publishes it as a first-class table so per-consortium
 * partner credentials can be issued for the SUSHI server endpoint.
 *
 * api_key_hash is sha256 of the issued key - the raw key is shown to the
 * partner once at issue time and never stored in clear.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_sushi_consumer')) {
            Schema::create('library_sushi_consumer', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('customer_id', 100);
                $table->string('requestor_id', 100);
                $table->string('api_key_hash', 64); // sha256 hex
                $table->string('name', 255)->nullable();
                $table->string('contact_email', 255)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['customer_id', 'requestor_id'], 'uk_sushi_consumer');
                $table->index('api_key_hash', 'idx_sushi_consumer_key');
                $table->index('active', 'idx_sushi_consumer_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_sushi_consumer');
    }
};
