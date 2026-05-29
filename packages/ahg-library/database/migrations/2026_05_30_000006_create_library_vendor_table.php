<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Library acquisitions vendor entity (heratio#1100). `library_order.vendor_id`
 * already exists as a column; this publishes its FK target so vendors become a
 * first-class managed entity (local + international) rather than inline strings.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_vendor')) {
            Schema::create('library_vendor', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('vendor_code', 50)->unique();
                $table->string('name', 255);
                $table->string('vendor_type', 30)->default('local'); // local | international
                $table->string('account_number', 100)->nullable();
                $table->string('contact_name', 255)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('website', 255)->nullable();
                $table->string('address', 500)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('country', 120)->nullable();
                $table->string('currency', 8)->default('ZAR');
                $table->string('san', 20)->nullable();          // Standard Address Number (book trade)
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('vendor_type');
                $table->index('is_active');
                $table->index('name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_vendor');
    }
};
