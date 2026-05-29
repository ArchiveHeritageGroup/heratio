<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Serials subscription tracking (heratio#1092). Promotes the table that
 * LibrarySerialService::ensureSubscriptionTable() previously created on demand
 * into a first-class migration so fresh installs ship with it. Idempotent: the
 * service guard + this CREATE-if-missing coexist safely on existing databases.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_serial_subscription')) {
            Schema::create('library_serial_subscription', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('serial_id');
                $table->date('subscription_start')->nullable();
                $table->date('subscription_end')->nullable();
                $table->decimal('subscription_cost', 10, 2)->nullable();
                $table->string('notification_email', 255)->nullable();
                $table->unsignedTinyInteger('auto_claim_max')->default(3);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique('serial_id', 'serial_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_serial_subscription');
    }
};
