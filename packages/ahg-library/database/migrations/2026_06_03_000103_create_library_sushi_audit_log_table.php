<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * SUSHI audit log (heratio#1096 acceptance: "who requested which report").
 * SushiServerController writes one row per report / members / reports request
 * so the operator can see which consortium partner harvested what, and when.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('library_sushi_audit_log')) {
            Schema::create('library_sushi_audit_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('customer_id', 100)->nullable();
                $table->string('requestor_id', 100)->nullable();
                $table->string('report_id', 40)->nullable();  // PR | TR | DR | IR | _members | _reports | _status
                $table->date('begin_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('ip', 45)->nullable();          // IPv4 / IPv6
                $table->string('user_agent', 255)->nullable();
                $table->boolean('authorised')->default(true);
                $table->timestamp('requested_at')->useCurrent();

                $table->index('customer_id', 'idx_sushi_audit_customer');
                $table->index('report_id', 'idx_sushi_audit_report');
                $table->index('requested_at', 'idx_sushi_audit_when');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_sushi_audit_log');
    }
};
